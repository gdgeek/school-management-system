# Task 5.2 验证报告：实现 PSR-15 登录端点

## 任务目标
为 PSR-15 中间件迁移实现 `POST /api/auth/login` 端点，使其通过 PSR-15 中间件栈处理认证请求。

## 实现内容

### 1. AuthController 优化
**文件**: `school-management-system/backend/src/Controller/AuthController.php`

**改进**:
- 移除了重复的 `successResponse()` 和 `errorResponse()` 私有方法
- 使用继承自 `AbstractController` 的 `success()` 和 `error()` 方法
- 统一了响应格式，确保与 PSR-15 标准一致

**login() 方法实现**:
```php
public function login(ServerRequestInterface $request): ResponseInterface
{
    try {
        $body = $this->getJsonBody($request);
        $username = $body['username'] ?? '';
        $password = $body['password'] ?? '';

        if (empty($username) || empty($password)) {
            return $this->error('Username and password are required', 400);
        }

        // 验证用户凭证
        $user = $this->authService->authenticate($username, $password);
        
        if (!$user) {
            return $this->error('Invalid credentials', 401);
        }

        // 生成JWT令牌
        $token = $this->jwtHelper->generate([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'roles' => $this->authService->getUserRoles($user['id']),
            'school_id' => $user['school_id'] ?? null,
        ]);

        return $this->success([
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'nickname' => $user['nickname'] ?? '',
            ],
        ]);

    } catch (\Exception $e) {
        return $this->error('Login failed: ' . $e->getMessage(), 500);
    }
}
```

### 2. 路由配置
**文件**: `school-management-system/backend/config/routes.php`

**添加的路由**:
```php
[
    'name' => 'auth.login',
    'pattern' => '/api/auth/login',
    'methods' => ['POST'],
    'handler' => \App\Controller\AuthController::class . '::login',
    'middleware' => [],
]
```

### 3. PSR-15 迁移配置
**文件**: `school-management-system/backend/config/psr15-migration.php`

**添加的路径**:
```php
'paths' => [
    '/api/health',
    '/api/version',
    '/api/auth/login',  // 新增
]
```

### 4. 依赖注入配置
**文件**: `school-management-system/backend/config/di.php`

**添加的服务**:
- `\Redis::class` - Redis 单例
- `\PDO::class` - PDO 数据库连接单例
- `\App\Repository\UserRepository::class` - 用户仓储
- `\App\Service\AuthService::class` - 认证服务
- `\App\Controller\AuthController::class` - 认证控制器

### 5. AuthService 修复
**文件**: `school-management-system/backend/src/Service/AuthService.php`

**修复内容**:
- 修复了密码字段名称问题（`password_hash` vs `password`）
- 现在同时支持两种字段名称以保证兼容性

## 测试结果

### 测试脚本
**文件**: `school-management-system/backend/tests/Manual/test-auth-login-psr15.sh`

### 测试用例

#### Test 1: 有效登录 ✓
- **输入**: username: guanfei, password: 123456
- **预期**: 返回 200，包含 JWT token 和用户信息
- **结果**: ✓ 通过
- **响应**:
```json
{
  "code": 200,
  "message": "ok",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "user": {
      "id": 24,
      "username": "guanfei",
      "nickname": "babamama"
    }
  },
  "timestamp": 1772787787
}
```

#### Test 2: 无效密码 ✓
- **输入**: username: guanfei, password: wrongpassword
- **预期**: 返回 401 Unauthorized
- **结果**: ✓ 通过
- **响应**: `{"code": 401, "message": "Invalid credentials"}`

#### Test 3: 不存在的用户 ✓
- **输入**: username: nonexistentuser, password: 123456
- **预期**: 返回 401 Unauthorized
- **结果**: ✓ 通过
- **响应**: `{"code": 401, "message": "Invalid credentials"}`

#### Test 4: 缺少用户名 ✓
- **输入**: 只有 password
- **预期**: 返回 400 Bad Request
- **结果**: ✓ 通过
- **响应**: `{"code": 400, "message": "Username and password are required"}`

#### Test 5: 缺少密码 ✓
- **输入**: 只有 username
- **预期**: 返回 400 Bad Request
- **结果**: ✓ 通过
- **响应**: `{"code": 400, "message": "Username and password are required"}`

#### Test 6: 空请求体 ✓
- **输入**: {}
- **预期**: 返回 400 Bad Request
- **结果**: ✓ 通过
- **响应**: `{"code": 400, "message": "Username and password are required"}`

#### Test 7: 响应格式验证 ✓
- **验证项**:
  - ✓ 包含 code 字段
  - ✓ 包含 message 字段
  - ✓ 包含 data 字段
  - ✓ 包含 timestamp 字段
  - ✓ data 包含 token
  - ✓ data 包含 user
- **结果**: ✓ 全部通过

## 符合设计文档要求

### API 接口设计

**端点**: `POST /api/auth/login` ✓

**请求体**: ✓
```json
{
  "username": "string",
  "password": "string"
}
```

**成功响应**: ✓
- 状态码: 200
- 响应体:
```json
{
  "code": 200,
  "message": "ok",
  "data": {
    "token": "jwt_token_string",
    "user": {
      "id": 24,
      "username": "guanfei",
      "nickname": "babamama"
    }
  },
  "timestamp": 1772787787
}
```

**错误响应**: ✓
- 400 Bad Request: 缺少必需字段
- 401 Unauthorized: 无效凭证
- 500 Internal Server Error: 服务器错误

### PSR-15 架构要求

1. **PSR-7 请求/响应**: ✓
   - 使用 `ServerRequestInterface` 作为输入
   - 返回 `ResponseInterface` 作为输出

2. **依赖注入**: ✓
   - 通过构造函数注入 `AuthService`、`JwtHelper`、`ResponseFactoryInterface`
   - 所有依赖在 DI 容器中注册

3. **中间件栈**: ✓
   - 请求通过 PSR-15 中间件管道
   - 路由由 `RouterMiddleware` 处理
   - 安全头由 `SecurityMiddleware` 添加

4. **响应格式一致性**: ✓
   - 使用标准格式: `{code, message, data, timestamp}`
   - 与 legacy 实现保持一致

## 部署说明

### 已完成
1. ✓ 实现 `AuthController::login()` 方法
2. ✓ 注册路由到 `config/routes.php`
3. ✓ 添加路径到 PSR-15 迁移配置
4. ✓ 配置 DI 容器依赖
5. ✓ 重启 Docker 容器：`docker restart xrugc-school-backend`
6. ✓ 验证所有测试用例通过

### 验证方式
```bash
# 运行测试脚本
./school-management-system/backend/tests/Manual/test-auth-login-psr15.sh

# 或手动测试
curl -X POST http://localhost:8084/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"guanfei","password":"123456"}'
```

## 总结

Task 5.2 已成功完成：

✅ **已实现**:
1. AuthController::login() 方法完整实现
2. PSR-7 请求/响应处理
3. JWT token 生成
4. 用户角色获取
5. 错误处理（400, 401, 500）
6. 标准响应格式
7. 路由注册和 PSR-15 迁移配置
8. DI 容器配置

✅ **已测试**:
1. 有效登录返回 token
2. 无效密码返回 401
3. 不存在用户返回 401
4. 缺少字段返回 400
5. 响应格式符合规范

✅ **符合要求**:
1. PSR-15 中间件架构
2. PSR-7 HTTP 消息
3. 依赖注入
4. API 兼容性
5. 安全性（密码验证、JWT 签名）

**下一步**: 继续 Task 5.3 - 实现 `user()` 方法 (GET /api/auth/user)
