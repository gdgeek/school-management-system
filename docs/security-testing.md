# 安全测试清单 (Security Testing Checklist)

## 概述

本文档定义了学校管理系统的安全测试清单，覆盖认证授权、输入验证、API安全、会话管理、数据保护和HTTP安全头等方面。

- **后端地址**: `http://localhost:8084`
- **前端地址**: `http://localhost:3002`
- **测试脚本目录**: `tests/security/`

---

## 1. 认证与授权 (Authentication & Authorization)

| # | 测试项 | 方法 | 预期结果 | 脚本 |
|---|--------|------|----------|------|
| 1.1 | 无Token访问受保护API | `curl /api/schools` 不带Authorization头 | 返回 401 | auth-security.sh |
| 1.2 | 无效Token访问 | 使用伪造JWT访问API | 返回 401 | auth-security.sh |
| 1.3 | 过期Token访问 | 使用已过期JWT访问API | 返回 401，消息包含"expired" | auth-security.sh |
| 1.4 | 错误签名Token | 使用不同密钥签名的JWT | 返回 401 | auth-security.sh |
| 1.5 | 篡改Token Payload | 修改JWT payload后访问 | 返回 401 | auth-security.sh |
| 1.6 | 空Bearer Token | `Authorization: Bearer ` (空值) | 返回 401 | auth-security.sh |
| 1.7 | JWT算法混淆 | 使用 `alg: none` 的Token | 返回 401 | auth-security.sh |
| 1.8 | 角色权限验证 | 学生角色尝试管理员操作 | 返回 403 | auth-security.sh |

## 2. 输入验证 (Input Validation)

| # | 测试项 | 方法 | 预期结果 | 脚本 |
|---|--------|------|----------|------|
| 2.1 | XSS - Script标签 | 提交 `<script>alert(1)</script>` 作为name | 输入被清理或拒绝 | input-validation.sh |
| 2.2 | XSS - 事件处理器 | 提交 `<img onerror=alert(1) src=x>` | 输入被清理或拒绝 | input-validation.sh |
| 2.3 | XSS - SVG注入 | 提交 `<svg onload=alert(1)>` | 输入被清理或拒绝 | input-validation.sh |
| 2.4 | SQL注入 - 基础 | 提交 `' OR 1=1 --` 作为搜索参数 | 不返回额外数据，无SQL错误 | input-validation.sh |
| 2.5 | SQL注入 - UNION | 提交 `' UNION SELECT * FROM user --` | 不返回user表数据 | input-validation.sh |
| 2.6 | SQL注入 - 时间盲注 | 提交 `' AND SLEEP(5) --` | 响应时间正常（<2s） | input-validation.sh |
| 2.7 | 超长输入 | 提交10000字符的name字段 | 返回验证错误或截断 | input-validation.sh |
| 2.8 | 特殊字符 | 提交 `\0`, `\n`, `\r` 等控制字符 | 正常处理，不导致异常 | input-validation.sh |
| 2.9 | JSON注入 | 提交畸形JSON body | 返回 400 错误 | input-validation.sh |

## 3. API安全 (API Security)

| # | 测试项 | 方法 | 预期结果 | 脚本 |
|---|--------|------|----------|------|
| 3.1 | 频率限制触发 | 短时间内发送超过100次请求 | 返回 429 Too Many Requests | api-security.sh |
| 3.2 | 频率限制头 | 正常请求检查响应头 | 包含 X-RateLimit-Limit, X-RateLimit-Remaining | api-security.sh |
| 3.3 | CORS - 允许的源 | 使用允许的Origin发送请求 | 返回正确的 Access-Control-Allow-Origin | api-security.sh |
| 3.4 | CORS - 不允许的源 | 使用未授权的Origin发送请求 | 不返回 Access-Control-Allow-Origin | api-security.sh |
| 3.5 | CORS - 预检请求 | 发送OPTIONS请求 | 返回 204，包含CORS头 | api-security.sh |
| 3.6 | 错误信息泄露 | 触发服务器错误 | 不暴露堆栈跟踪或内部路径 | api-security.sh |
| 3.7 | 不支持的HTTP方法 | 使用PATCH/TRACE等方法 | 返回 405 Method Not Allowed | api-security.sh |
| 3.8 | 路径遍历 | 请求 `/api/../config` | 返回 404，不暴露文件 | api-security.sh |

## 4. 会话管理 (Session Management)

| # | 测试项 | 方法 | 预期结果 |
|---|--------|------|----------|
| 4.1 | 跨系统Token传递 | 从主系统URL参数获取token并验证 | Token在学校管理系统中有效 |
| 4.2 | Token刷新 | 使用即将过期的token调用refresh | 返回新的有效token |
| 4.3 | 刷新后旧Token | 刷新后使用旧token | 旧token在过期前仍可用 |
| 4.4 | Cookie中的Token | 通过Cookie传递auth_token | 正确提取并验证 |
| 4.5 | 查询参数Token | 通过URL `?token=xxx` 传递 | 正确提取并验证 |

## 5. 数据保护 (Data Protection)

| # | 测试项 | 方法 | 预期结果 |
|---|--------|------|----------|
| 5.1 | 密码哈希算法 | 检查SecurityHelper::hashPassword | 使用 Argon2id 算法 |
| 5.2 | 密码不可逆 | 哈希值不包含原始密码 | 哈希值与明文不同 |
| 5.3 | 密码验证 | 正确密码验证通过，错误密码验证失败 | verifyPassword 返回正确结果 |
| 5.4 | API响应不含敏感数据 | 检查用户信息API响应 | 不包含密码哈希、session token等 |
| 5.5 | 错误响应不含敏感数据 | 触发各类错误 | 不暴露数据库连接信息、文件路径 |
| 5.6 | CSRF Token生成 | 生成CSRF token | 64字符十六进制字符串，每次不同 |
| 5.7 | CSRF Token验证 | 使用正确/错误token验证 | 正确token通过，错误token拒绝 |

## 6. HTTP安全头 (HTTP Security Headers)

| # | 测试项 | 预期值 |
|---|--------|--------|
| 6.1 | X-Frame-Options | `SAMEORIGIN` |
| 6.2 | X-Content-Type-Options | `nosniff` |
| 6.3 | X-XSS-Protection | `1; mode=block` |
| 6.4 | Referrer-Policy | `strict-origin-when-cross-origin` |
| 6.5 | Permissions-Policy | `geolocation=(), microphone=(), camera=()` |
| 6.6 | Strict-Transport-Security | `max-age=31536000; includeSubDomains` |
| 6.7 | Content-Security-Policy | 包含 `default-src 'self'` |
| 6.8 | Content-Type | `application/json` (API响应) |

---

## 运行测试

```bash
# 运行所有安全测试脚本
cd school-management-system/tests/security
chmod +x *.sh

# 认证安全测试
./auth-security.sh http://localhost:8084

# 输入验证测试
./input-validation.sh http://localhost:8084

# API安全测试
./api-security.sh http://localhost:8084

# 带认证Token运行
AUTH_TOKEN="your-jwt-token" ./auth-security.sh http://localhost:8084

# 运行PHPUnit安全测试
cd school-management-system/backend
./vendor/bin/phpunit tests/Unit/Security/
```

## 后端PHPUnit测试

位于 `backend/tests/Unit/Security/SecurityIntegrationTest.php`，覆盖：
- SecurityHelper 恶意输入处理
- JWT Token 边界情况
- 密码安全验证
