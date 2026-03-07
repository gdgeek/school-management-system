# 学校管理系统 - 复用原项目 RBAC 权限管理方案

## 概述

学校管理系统可以完全复用原项目（XR UGC 主系统）的 RBAC（基于角色的访问控制）权限管理体系，因为两个系统共享同一个 MySQL 数据库。这样可以实现：

- 统一的用户角色管理
- 一致的权限控制逻辑
- 减少重复开发工作
- 保持权限体系的一致性

---

## 原项目 RBAC 架构

### 数据库表结构

原项目使用 Yii2 的 `yii\rbac\DbManager` 实现 RBAC，涉及以下数据库表：

| 表名 | 说明 |
|------|------|
| `auth_item` | 存储角色（Role）和权限（Permission） |
| `auth_assignment` | 用户与角色的关联关系 |
| `auth_item_child` | 角色/权限的层级关系 |
| `auth_rule` | 自定义权限规则 |

#### auth_item 表结构

```sql
CREATE TABLE `auth_item` (
  `name` varchar(64) NOT NULL,           -- 角色/权限名称
  `type` int(11) NOT NULL,               -- 类型：1=角色，2=权限
  `description` text,                    -- 描述
  `rule_name` varchar(64) DEFAULT NULL,  -- 关联的规则名称
  `data` blob,                           -- 额外数据
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`name`)
);
```

#### auth_assignment 表结构

```sql
CREATE TABLE `auth_assignment` (
  `item_name` varchar(64) NOT NULL,      -- 角色/权限名称
  `user_id` varchar(64) NOT NULL,        -- 用户 ID
  `created_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`item_name`, `user_id`),
  FOREIGN KEY (`item_name`) REFERENCES `auth_item` (`name`)
);
```

### 现有角色体系

根据原项目代码分析，主系统已经定义了以下角色：

| 角色名称 | 说明 | 权限范围 |
|---------|------|---------|
| `guest` | 访客 | 基础浏览权限 |
| `user` | 普通用户 | 资源管理、项目创建 |
| `manager` | 管理员 | 用户管理、内容审核 |
| `admin` | 系统管理员 | 全部管理权限 |
| `root` | 超级管理员 | 所有权限 + 系统配置 |

---

## 复用方案设计

### 方案一：直接使用 Yii RBAC（推荐）

学校管理系统后端也使用 Yii3 框架，可以直接集成 Yii 的 RBAC 组件。

#### 1. 配置 AuthManager

在学校管理系统后端配置文件中添加：

```php
// school-management-system/backend/config/common.php

return [
    // ... 其他配置
    
    'components' => [
        'authManager' => [
            'class' => 'yii\rbac\DbManager',
            'cache' => 'cache', // 使用缓存提升性能
        ],
        
        'cache' => [
            'class' => 'yii\redis\Cache',
            'redis' => [
                'hostname' => getenv('REDIS_HOST') ?: 'localhost',
                'port' => getenv('REDIS_PORT') ?: 6379,
                'database' => 0,
            ],
        ],
    ],
];
```

#### 2. 在中间件中集成权限检查

修改 `AuthMiddleware.php`，添加 RBAC 权限检查：

```php
<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use App\Helper\JwtHelper;
use App\Helper\ResponseHelper;
use Yii;

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private JwtHelper $jwtHelper,
        private ResponseHelper $responseHelper
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $token = $this->extractToken($request);
        
        if (!$token) {
            return $this->responseHelper->error('Unauthorized', 401);
        }
        
        try {
            $payload = $this->jwtHelper->verify($token);
            $userId = $payload['user_id'];
            
            // 将用户 ID 注入到请求属性中
            $request = $request->withAttribute('user_id', $userId);
            $request = $request->withAttribute('user_roles', $payload['roles'] ?? []);
            
            // 设置 Yii 用户组件（用于 RBAC 检查）
            Yii::$app->user->setIdentity([
                'id' => $userId,
                'username' => $payload['username'] ?? '',
            ]);
            
            return $handler->handle($request);
        } catch (\Exception $e) {
            return $this->responseHelper->error('Invalid token', 401);
        }
    }
    
    private function extractToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');
        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
```

#### 3. 创建权限检查辅助类

```php
<?php

namespace App\Helper;

use Yii;

class RbacHelper
{
    /**
     * 检查当前用户是否有指定权限
     */
    public static function can(string $permissionName, array $params = []): bool
    {
        $userId = Yii::$app->user->id;
        if (!$userId) {
            return false;
        }
        
        return Yii::$app->authManager->checkAccess($userId, $permissionName, $params);
    }
    
    /**
     * 检查用户是否有指定角色
     */
    public static function hasRole(int $userId, string $roleName): bool
    {
        $roles = Yii::$app->authManager->getRolesByUser($userId);
        return isset($roles[$roleName]);
    }
    
    /**
     * 获取用户的所有角色
     */
    public static function getUserRoles(int $userId): array
    {
        $roles = Yii::$app->authManager->getRolesByUser($userId);
        return array_keys($roles);
    }
    
    /**
     * 为用户分配角色
     */
    public static function assignRole(int $userId, string $roleName): bool
    {
        $role = Yii::$app->authManager->getRole($roleName);
        if (!$role) {
            return false;
        }
        
        try {
            Yii::$app->authManager->assign($role, $userId);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 撤销用户的角色
     */
    public static function revokeRole(int $userId, string $roleName): bool
    {
        $role = Yii::$app->authManager->getRole($roleName);
        if (!$role) {
            return false;
        }
        
        try {
            Yii::$app->authManager->revoke($role, $userId);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
```

#### 4. 在 Service 层使用权限检查

```php
<?php

namespace App\Service;

use App\Helper\RbacHelper;
use App\Exception\ForbiddenException;

class SchoolService
{
    public function create(array $data): array
    {
        // 检查权限：只有管理员可以创建学校
        if (!RbacHelper::can('createSchool')) {
            throw new ForbiddenException('You do not have permission to create schools');
        }
        
        // ... 创建学校的逻辑
    }
    
    public function update(int $id, array $data): ?array
    {
        $school = $this->schoolRepository->findById($id);
        
        if (!$school) {
            return null;
        }
        
        // 检查权限：管理员或该学校的校长可以更新
        $canUpdate = RbacHelper::can('updateSchool') || 
                     $this->isPrincipal($school['id'], Yii::$app->user->id);
        
        if (!$canUpdate) {
            throw new ForbiddenException('You do not have permission to update this school');
        }
        
        // ... 更新学校的逻辑
    }
}
```

---

### 方案二：简化版 - 仅使用角色表（当前实现）

如果不想引入完整的 Yii RBAC 组件，可以直接查询 `auth_assignment` 表获取用户角色。

#### 优点
- 实现简单，无需额外依赖
- 性能更好（直接查询，无框架开销）
- 适合权限需求简单的场景

#### 缺点
- 无法使用复杂的权限规则
- 无法实现权限继承
- 需要手动维护权限逻辑

#### 实现示例

```php
<?php

namespace App\Repository;

use PDO;

class UserRepository
{
    public function __construct(private PDO $db) {}
    
    /**
     * 获取用户的所有角色
     */
    public function getUserRoles(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT item_name 
            FROM auth_assignment 
            WHERE user_id = :user_id
        ");
        
        $stmt->execute(['user_id' => $userId]);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * 检查用户是否有指定角色
     */
    public function hasRole(int $userId, string $roleName): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM auth_assignment 
            WHERE user_id = :user_id AND item_name = :role_name
        ");
        
        $stmt->execute([
            'user_id' => $userId,
            'role_name' => $roleName,
        ]);
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * 检查用户是否有任一指定角色
     */
    public function hasAnyRole(int $userId, array $roleNames): bool
    {
        if (empty($roleNames)) {
            return false;
        }
        
        $placeholders = implode(',', array_fill(0, count($roleNames), '?'));
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM auth_assignment 
            WHERE user_id = ? AND item_name IN ($placeholders)
        ");
        
        $stmt->execute(array_merge([$userId], $roleNames));
        
        return $stmt->fetchColumn() > 0;
    }
}
```

---

## 学校管理系统权限定义

### 建议的权限结构

#### 角色定义

| 角色 | 说明 | 继承自 |
|------|------|--------|
| `school_admin` | 学校管理员 | `manager` |
| `principal` | 校长 | `school_admin` |
| `teacher` | 教师 | `user` |
| `student` | 学生 | `user` |

#### 权限定义

```php
// 学校管理权限
'manageSchools'      => '管理所有学校',
'viewSchool'         => '查看学校信息',
'createSchool'       => '创建学校',
'updateSchool'       => '更新学校信息',
'deleteSchool'       => '删除学校',

// 班级管理权限
'manageClasses'      => '管理班级',
'viewClass'          => '查看班级信息',
'createClass'        => '创建班级',
'updateClass'        => '更新班级信息',
'deleteClass'        => '删除班级',

// 教师管理权限
'manageTeachers'     => '管理教师',
'addTeacher'         => '添加教师',
'removeTeacher'      => '移除教师',

// 学生管理权限
'manageStudents'     => '管理学生',
'addStudent'         => '添加学生',
'removeStudent'      => '移除学生',

// 小组管理权限
'manageGroups'       => '管理小组',
'createGroup'        => '创建小组',
'updateGroup'        => '更新小组',
'deleteGroup'        => '删除小组',
```

### 初始化 RBAC 数据的脚本

```php
<?php
// school-management-system/backend/scripts/init-rbac.php

use yii\rbac\DbManager;

$authManager = Yii::$app->authManager;

// 创建权限
$permissions = [
    'viewSchool' => '查看学校信息',
    'createSchool' => '创建学校',
    'updateSchool' => '更新学校信息',
    'deleteSchool' => '删除学校',
    'viewClass' => '查看班级信息',
    'createClass' => '创建班级',
    'updateClass' => '更新班级信息',
    'deleteClass' => '删除班级',
    'manageTeachers' => '管理教师',
    'manageStudents' => '管理学生',
    'manageGroups' => '管理小组',
];

foreach ($permissions as $name => $description) {
    $permission = $authManager->createPermission($name);
    $permission->description = $description;
    $authManager->add($permission);
}

// 创建角色
$student = $authManager->createRole('student');
$student->description = '学生';
$authManager->add($student);
$authManager->addChild($student, $authManager->getPermission('viewSchool'));
$authManager->addChild($student, $authManager->getPermission('viewClass'));

$teacher = $authManager->createRole('teacher');
$teacher->description = '教师';
$authManager->add($teacher);
$authManager->addChild($teacher, $student); // 继承学生权限
$authManager->addChild($teacher, $authManager->getPermission('manageGroups'));
$authManager->addChild($teacher, $authManager->getPermission('manageStudents'));

$principal = $authManager->createRole('principal');
$principal->description = '校长';
$authManager->add($principal);
$authManager->addChild($principal, $teacher); // 继承教师权限
$authManager->addChild($principal, $authManager->getPermission('createClass'));
$authManager->addChild($principal, $authManager->getPermission('updateClass'));
$authManager->addChild($principal, $authManager->getPermission('deleteClass'));
$authManager->addChild($principal, $authManager->getPermission('manageTeachers'));

$schoolAdmin = $authManager->createRole('school_admin');
$schoolAdmin->description = '学校管理员';
$authManager->add($schoolAdmin);
$authManager->addChild($schoolAdmin, $principal); // 继承校长权限
$authManager->addChild($schoolAdmin, $authManager->getPermission('createSchool'));
$authManager->addChild($schoolAdmin, $authManager->getPermission('updateSchool'));
$authManager->addChild($schoolAdmin, $authManager->getPermission('deleteSchool'));

echo "RBAC 初始化完成！\n";
```

---

## 实施步骤

### 1. 数据库准备

确认原项目数据库中已存在 RBAC 表：
- `auth_item`
- `auth_assignment`
- `auth_item_child`
- `auth_rule`

### 2. 后端集成

1. 安装 Yii RBAC 组件（如果使用方案一）
2. 配置 `authManager` 组件
3. 创建 `RbacHelper` 辅助类
4. 在 Service 层添加权限检查
5. 运行 RBAC 初始化脚本

### 3. 前端集成

前端从 JWT payload 中获取用户角色，控制 UI 显示：

```typescript
// frontend/src/utils/permission.ts

import { useAuthStore } from '@/stores/auth'

export function hasRole(roleName: string): boolean {
  const authStore = useAuthStore()
  return authStore.user?.roles?.includes(roleName) ?? false
}

export function hasAnyRole(roleNames: string[]): boolean {
  const authStore = useAuthStore()
  const userRoles = authStore.user?.roles ?? []
  return roleNames.some(role => userRoles.includes(role))
}

export function canManageSchools(): boolean {
  return hasAnyRole(['admin', 'school_admin', 'root'])
}

export function canManageClasses(): boolean {
  return hasAnyRole(['admin', 'school_admin', 'principal', 'root'])
}
```

在 Vue 组件中使用：

```vue
<template>
  <div>
    <button v-if="canManageSchools()" @click="createSchool">
      创建学校
    </button>
  </div>
</template>

<script setup lang="ts">
import { canManageSchools } from '@/utils/permission'
</script>
```

---

## 优势总结

### 复用原项目 RBAC 的优势

1. **统一管理**: 所有用户的角色和权限在一个地方管理
2. **数据一致性**: 共享数据库，无需同步权限数据
3. **减少开发**: 无需重新设计和实现权限系统
4. **易于维护**: 权限变更只需在一处修改
5. **灵活扩展**: 可以轻松添加新角色和权限
6. **成熟稳定**: Yii RBAC 是经过验证的成熟方案

### 注意事项

1. **性能优化**: 使用 Redis 缓存权限检查结果
2. **权限粒度**: 根据实际需求定义合适的权限粒度
3. **向后兼容**: 确保新增权限不影响原系统
4. **文档维护**: 及时更新权限文档
5. **测试覆盖**: 为权限检查编写单元测试

---

## 参考资料

- [Yii2 RBAC 官方文档](https://www.yiiframework.com/doc/guide/2.0/en/security-authorization)
- [Yii3 RBAC 组件](https://github.com/yiisoft/rbac)
- 原项目 RBAC 实现：`backend/advanced/common/rbac/`
- 原项目权限配置：`backend/advanced/api/config/main.php`
