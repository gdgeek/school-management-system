# 班级和小组删除功能说明

## 功能概述

实现了班级和小组之间的级联删除逻辑：

1. **删除小组** → 必须删除关联的班级（级联删除）
2. **删除班级** → 可选择是否删除关联的小组（用户决定）

## 业务规则

### 删除小组
- 删除小组时，系统会自动删除所有关联的班级
- 删除顺序：
  1. 删除关联的班级
  2. 删除小组成员关系
  3. 删除班级-小组关联关系
  4. 删除小组本身
- 前端会显示警告提示："删除小组将同时删除所有关联的班级！"

### 删除班级
- 删除班级时，用户可以选择是否删除关联的小组
- 删除流程：
  1. 第一步：确认是否删除班级
  2. 第二步：询问是否删除关联的小组
     - 选择"删除小组"：同时删除班级和关联的小组
     - 选择"保留小组"：只删除班级，保留小组
- 删除顺序：
  1. 根据用户选择，删除或保留小组
  2. 删除班级-小组关联关系
  3. 删除班级下的教师和学生
  4. 删除班级本身

## 技术实现

### 后端实现

#### ClassService.php
```php
public function delete(int $id, bool $deleteGroups = false): bool
```
- 新增 `$deleteGroups` 参数，默认为 `false`
- 根据参数决定是否删除关联的小组

#### ClassController.php
```php
public function delete(ServerRequestInterface $request, int $id): ResponseInterface
```
- 从查询参数获取 `deleteGroups` 参数
- 传递给 Service 层处理

#### GroupService.php
```php
public function delete(int $id): bool
```
- 删除小组前，先删除所有关联的班级
- 使用事务确保数据一致性

### 前端实现

#### API 层 (class.ts)
```typescript
export function deleteClass(id: number, deleteGroups = false)
```
- 新增 `deleteGroups` 参数，默认为 `false`
- 通过查询参数传递给后端

#### 班级列表页面 (ClassList.vue)
- 两步确认流程：
  1. 确认删除班级
  2. 询问是否删除关联的小组
- 使用 `distinguishCancelAndClose` 区分取消和关闭操作

#### 小组列表页面 (GroupList.vue)
- 增强警告提示，明确告知会删除关联的班级
- 使用 `error` 类型的确认框，提高警示性

## API 接口

### 删除班级
```
DELETE /api/classes/{id}?deleteGroups={true|false}
```

参数：
- `id`: 班级ID（路径参数）
- `deleteGroups`: 是否删除关联的小组（查询参数，可选，默认 false）

响应：
```json
{
  "code": 200,
  "message": "Class deleted successfully",
  "data": []
}
```

### 删除小组
```
DELETE /api/groups/{id}
```

参数：
- `id`: 小组ID（路径参数）

响应：
```json
{
  "code": 200,
  "message": "Group deleted successfully",
  "data": []
}
```

## 测试场景

### 场景 1：删除班级，保留小组
1. 创建班级和小组，建立关联
2. 删除班级，选择"保留小组"
3. 验证：班级被删除，小组仍存在

### 场景 2：删除班级，同时删除小组
1. 创建班级和小组，建立关联
2. 删除班级，选择"删除小组"
3. 验证：班级和小组都被删除

### 场景 3：删除小组，级联删除班级
1. 创建小组和班级，建立关联
2. 删除小组
3. 验证：小组和关联的班级都被删除

## 注意事项

1. 所有删除操作都在事务中执行，确保数据一致性
2. 删除小组会级联删除班级，操作不可逆，需谨慎
3. 删除班级时默认保留小组，避免误删
4. 建议在生产环境添加软删除机制
5. 考虑添加删除日志，便于追溯和恢复

## 相关文件

### 后端
- `school-management-system/backend/src/Service/ClassService.php`
- `school-management-system/backend/src/Service/GroupService.php`
- `school-management-system/backend/src/Controller/ClassController.php`
- `school-management-system/backend/src/Controller/GroupController.php`

### 前端
- `school-management-system/frontend/src/api/class.ts`
- `school-management-system/frontend/src/views/classes/ClassList.vue`
- `school-management-system/frontend/src/views/groups/GroupList.vue`

### 测试
- `school-management-system/backend/tests/Manual/DeleteClassGroupTest.md`
