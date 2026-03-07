# Task 5.2 验证报告：更新学生删除路由

## 任务目标
更新 `index.php` 中的 `DELETE /api/students/{id}` 路由，添加：
1. JWT 认证检查
2. 权限验证（仅教师和管理员）
3. 返回 204 No Content
4. 正确处理自动离开小组（由 StudentService 处理）

## 实现内容

### 代码更改
在 `school-management-system/backend/public/index.php` 中更新了学生删除路由：

```php
// DELETE /api/students/{id} — 移除学生
case preg_match('#^/api/students/(\d+)$#', $path, $m) === 1 && $method === 'DELETE':
    // 步骤 1: 验证 JWT token
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        apiError('Token required', 401);
        break;
    }
    
    try {
        // 步骤 2: 验证 token 并获取用户信息
        $token = $matches[1];
        $payload = $jwtHelper->verify($token);
        $currentUserId = (int)($payload['user_id'] ?? 0);
        $roles = $payload['roles'] ?? [];
        
        // 确保 roles 是数组
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        
        // 步骤 3: 验证权限（仅教师和管理员）
        if (!in_array('teacher', $roles) && !in_array('admin', $roles)) {
            apiError('Only teachers and admins can remove students', 403);
            break;
        }
        
        // 步骤 4: 调用 Service 删除学生（自动离开小组）
        $ok = $studentService->delete((int)$m[1]);
        
        // 步骤 5: 返回响应
        if ($ok) {
            // 返回 204 No Content
            http_response_code(204);
            exit;
        } else {
            apiError('Student not found', 404);
        }
        
    } catch (\App\Exception\UnauthorizedException $e) {
        apiError($e->getMessage(), 401);
    } catch (\Throwable $e) {
        apiError('Failed to delete student: ' . $e->getMessage(), 500);
    }
    break;
```

### 关键特性

1. **JWT 认证检查**：
   - 检查 `Authorization: Bearer {token}` 头
   - 未提供 token 返回 401
   - 无效 token 返回 401

2. **权限验证**：
   - 验证用户角色是 `teacher` 或 `admin`
   - 其他角色（如 `student`）返回 403 Forbidden

3. **返回 204 No Content**：
   - 删除成功时返回 204 状态码（无响应体）
   - 符合 RESTful API 最佳实践

4. **自动离开小组**：
   - 由 `StudentService::delete()` 方法处理
   - 使用事务确保原子性
   - 从班级关联的所有小组中移除用户

## 测试结果

### 测试 1: 认证和权限检查
**测试脚本**: `test-student-delete-simple-auth.sh`

**结果**:
```
✓ 未认证删除正确返回 401
✓ 无效 token 正确返回 401
✓ 学生角色正确返回 403 Forbidden
```

**验证内容**:
- ✅ 未认证请求返回 401
- ✅ 无效 token 返回 401
- ✅ 学生角色返回 403
- ⚠️ 教师/管理员角色测试需要教师账号（当前测试账号是学生）

### 测试 2: 自动离开小组功能
**测试脚本**: `test-student-auto-leave-groups.sh`

**状态**: 需要教师账号才能完整测试

**已验证**:
- ✅ 权限检查生效（学生账号无法创建/删除学生）
- ✅ StudentService::delete() 方法已实现自动离开小组逻辑（Task 1.3）

**待验证**:
- ⏳ 使用教师账号完整测试删除流程
- ⏳ 验证返回 204 No Content
- ⏳ 验证学生从所有小组中自动移除

## 符合设计文档要求

### API 接口设计（设计文档 2. 删除学生）

**端点**: `DELETE /api/students/{id}`

**请求头**: ✅
```
Authorization: Bearer {jwt_token}
```

**成功响应**: ✅
- 状态码: 204 No Content
- 响应体: 无

**错误响应**: ✅
- 401 Unauthorized: 未认证
- 403 Forbidden: 无权限（新增）
- 404 Not Found: 学生不存在

### 安全考虑（设计文档）

**权限验证**: ✅
- 只有教师或管理员可以删除学生
- 在 `index.php` 路由层进行权限检查
- 返回 403 而不是 422

**认证**: ✅
- 验证 JWT token
- 返回 401 而不是 422

## 部署说明

### 已完成
1. ✅ 修改 `index.php` 代码
2. ✅ 重启 Docker 容器：`docker restart xrugc-school-backend`
3. ✅ 验证认证和权限检查

### 注意事项
- StudentService::delete() 已在 Task 1.3 中实现自动离开小组功能
- 使用事务确保删除操作的原子性
- 删除学生时会自动从班级关联的所有小组中移除

## 总结

Task 5.2 已成功完成：

✅ **已实现**:
1. JWT 认证检查（401）
2. 权限验证（403 for non-teacher/admin）
3. 返回 204 No Content
4. 调用 StudentService::delete() 处理自动离开小组
5. 错误处理（401, 403, 404, 500）

✅ **已测试**:
1. 未认证请求返回 401
2. 无效 token 返回 401
3. 学生角色返回 403

⚠️ **限制**:
- 完整的端到端测试需要教师或管理员账号
- 当前测试账号（guanfei）是学生角色

**建议**: 创建教师账号用于完整的集成测试，或者在数据库中将 guanfei 用户添加教师角色。
