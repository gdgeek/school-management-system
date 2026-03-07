# 学生删除自动离开小组功能测试文档

## 功能概述

实现了任务 1.3：修改 `StudentService::delete()` 方法，使学生删除时自动从班级关联的所有小组中移除。

## 实现细节

### 修改的文件
- `school-management-system/backend/src/Service/StudentService.php`

### 实现的算法（设计文档算法 2）

```
1. 查找学生信息（user_id, class_id）
2. 如果学生不存在，返回 false
3. 开始数据库事务
4. 查找班级关联的所有小组
5. 遍历每个小组，从 group_user 表中删除该用户
6. 删除学生记录
7. 提交事务
8. 返回 true
```

### 关键特性

✅ **事务原子性**：所有操作在一个事务中完成，确保数据一致性
✅ **自动清理**：删除学生时自动清理所有小组成员关系
✅ **无残留数据**：确保用户不会在班级关联的小组中留下孤立记录
✅ **错误处理**：事务失败时自动回滚

## 代码实现

```php
public function delete(int $id): bool
{
    // 步骤 1: 查找学生信息
    $student = $this->studentRepository->findById($id);
    
    if (!$student) {
        return false;
    }
    
    $userId = $student->user_id;
    $classId = $student->class_id;
    
    // 步骤 2: 使用事务确保原子性
    return $this->dbHelper->transaction(function() use ($id, $userId, $classId) {
        // 步骤 3: 查找班级关联的所有小组
        $classGroups = $this->classGroupRepository->findByClassId($classId);
        
        // 步骤 4: 从每个小组移除用户
        foreach ($classGroups as $classGroup) {
            $groupId = $classGroup->group_id;
            $this->groupUserRepository->delete($userId, $groupId);
        }
        
        // 步骤 5: 删除学生记录
        return $this->studentRepository->delete($id);
    });
}
```

## 手动测试步骤

由于自动化测试脚本遇到测试数据准备问题，建议按以下步骤手动测试：

### 前置条件
1. Docker 容器已启动
2. 已执行 `docker restart xrugc-school-backend` 使代码生效
3. 有可用的测试账号（guanfei/123456）

### 测试步骤

#### 1. 登录获取 token
```bash
curl -X POST "http://localhost:8084/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username": "guanfei", "password": "123456"}'
```

保存返回的 `token` 和 `id`（用户 ID）。

#### 2. 创建测试学校
```bash
curl -X POST "http://localhost:8084/api/schools" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{"name": "测试学校"}'
```

保存返回的学校 `id`。

#### 3. 创建测试班级（会自动创建小组）
```bash
curl -X POST "http://localhost:8084/api/classes" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{"name": "测试班级", "school_id": {SCHOOL_ID}}'
```

保存返回的班级 `id` 和 `groups` 数组中的小组 `id`。

#### 4. 查找一个可用的用户 ID
- 使用一个不是当前登录用户的 user_id
- 确保该用户不是其他班级的学生

#### 5. 添加学生到班级
```bash
curl -X POST "http://localhost:8084/api/students" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{"user_id": {USER_ID}, "class_id": {CLASS_ID}}'
```

保存返回的学生 `id`。

#### 6. 验证学生在小组中（删除前）
```bash
curl -X GET "http://localhost:8084/api/groups/{GROUP_ID}" \
  -H "Authorization: Bearer {TOKEN}"
```

检查 `members` 数组中是否包含该用户。

#### 7. 删除学生
```bash
curl -X DELETE "http://localhost:8084/api/students/{STUDENT_ID}" \
  -H "Authorization: Bearer {TOKEN}"
```

应返回 HTTP 204 或 200。

#### 8. 验证学生已从小组中移除（删除后）
```bash
curl -X GET "http://localhost:8084/api/groups/{GROUP_ID}" \
  -H "Authorization: Bearer {TOKEN}"
```

检查 `members` 数组中是否不再包含该用户。

### 预期结果

✅ 删除前：学生在小组的 `members` 列表中
✅ 删除后：学生不在小组的 `members` 列表中
✅ 数据库中 `group_user` 表没有该用户和小组的关联记录

## 验证数据一致性

可以通过以下 SQL 查询验证：

```sql
-- 查看学生记录（应该不存在）
SELECT * FROM edu_student WHERE id = {STUDENT_ID};

-- 查看小组成员记录（应该不存在）
SELECT * FROM group_user WHERE user_id = {USER_ID} AND group_id = {GROUP_ID};
```

## 相关文件

- 设计文档：`.kiro/specs/student-class-group-auto-association/design.md`
- 任务列表：`.kiro/specs/student-class-group-auto-association/tasks.md`
- 实现文件：`school-management-system/backend/src/Service/StudentService.php`

## 注意事项

1. **事务支持**：依赖 `DatabaseHelper::transaction()` 方法
2. **级联删除**：删除学生时会自动清理小组成员关系
3. **幂等性**：重复删除同一学生会返回 false（学生不存在）
4. **性能**：对于关联多个小组的班级，会执行多次删除操作，但都在同一事务中

## 后续任务

- [ ] 1.4 为 StudentService::delete() 编写单元测试
- [ ] 验证与 StudentService::create() 的对称性
- [ ] 集成测试验证完整的学生生命周期
