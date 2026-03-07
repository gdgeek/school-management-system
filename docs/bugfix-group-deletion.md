# Bug 修复：删除小组时未级联删除班级

## 问题描述

删除小组时，关联的班级没有被实际删除，只删除了班级-小组的关联关系。

## 测试结果

```
步骤 4: 删除小组
-------------------
✓ 删除小组成功

步骤 5: 验证级联删除
-------------------
✗ 班级未删除  <-- 问题：班级应该被删除但实际没有
✓ 小组已删除
```

## 根本原因

在 `GroupService::delete()` 方法中，虽然代码尝试删除关联的班级，但实现有问题：

### 原始错误代码
```php
// 删除关联的班级
foreach ($classRelations as $relation) {
    $this->classRepository->delete($relation->class_id);
}
```

问题：
1. `ClassRepository::delete()` 只删除班级记录本身
2. 没有删除班级下的教师和学生
3. 没有先删除班级-小组关联关系

## 修复方案

### 修改后的代码

```php
/**
 * 删除小组（级联删除关联的班级）
 */
public function delete(int $id): bool
{
    $group = $this->groupRepository->findById($id);
    
    if (!$group) {
        return false;
    }
    
    return $this->dbHelper->transaction(function() use ($id) {
        // 获取关联的班级
        $classRelations = $this->classGroupRepository->findByGroupId($id);
        
        // 删除关联的班级（包括班级下的教师和学生）
        foreach ($classRelations as $relation) {
            $classId = $relation->class_id;
            
            // 删除班级下的教师和学生
            $this->teacherRepository->deleteByClassId($classId);
            $this->studentRepository->deleteByClassId($classId);
            
            // 删除班级-小组关联
            $this->classGroupRepository->delete($classId, $id);
            
            // 删除班级
            $this->classRepository->delete($classId);
        }
        
        // 删除小组成员
        $this->groupUserRepository->deleteByGroupId($id);
        
        // 删除小组
        return $this->groupRepository->delete($id);
    });
}
```

### 需要添加的依赖注入

在 `GroupService` 构造函数中添加：
```php
public function __construct(
    private GroupRepository $groupRepository,
    private GroupUserRepository $groupUserRepository,
    private ClassGroupRepository $classGroupRepository,
    private UserRepository $userRepository,
    private ClassRepository $classRepository,
    private TeacherRepository $teacherRepository,  // 新增
    private StudentRepository $studentRepository,  // 新增
    private DatabaseHelper $dbHelper
) {}
```

在文件顶部添加 use 语句：
```php
use App\Repository\TeacherRepository;
use App\Repository\StudentRepository;
```

## 删除顺序

正确的删除顺序（从叶子节点到根节点）：
1. 删除教师（依赖班级）
2. 删除学生（依赖班级）
3. 删除班级-小组关联（依赖班级和小组）
4. 删除班级
5. 删除小组成员（依赖小组）
6. 删除小组

## 修改的文件

- `school-management-system/backend/src/Service/GroupService.php`

## 验证方法

运行测试脚本：
```bash
cd school-management-system/backend
./tests/Manual/test-delete-group-api.sh
```

预期结果：
```
步骤 5: 验证级联删除
-------------------
✓ 班级已删除
✓ 小组已删除
✓ 班级-小组关联已删除
✓ 教师已删除
✓ 学生已删除
```

## 注意事项

1. 所有删除操作在事务中执行，确保原子性
2. 删除小组会级联删除所有关联的班级，操作不可逆
3. 前端已添加警告提示："删除小组将同时删除所有关联的班级！"
4. 建议在生产环境添加软删除机制

## 相关文档

- [班级和小组删除功能说明](./class-group-deletion.md)
- [删除功能测试指南](../backend/tests/Manual/DeleteClassGroupTest.md)
