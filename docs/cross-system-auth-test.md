# 跨系统认证测试指南

## 概述

本文档描述如何测试主系统（Yii2）与学校管理系统（Vue3 + Yii3）之间的跨系统认证流程。

---

## 认证流程说明

```
用户（已登录主系统）
  │
  ▼
点击主系统导航栏中的「学校管理」按钮
  │
  ▼
主系统 SchoolManagementController::actionRedirect()
  ├─ 检查用户是否已登录（未登录则跳转到登录页）
  ├─ 生成随机 token（64位十六进制字符串）
  ├─ 写入 Redis：school_mgmt_token:{token} = {"user_id": X, "created_at": T}，TTL = 60秒
  └─ 重定向到：http://localhost:3002?session_token={token}
  │
  ▼
学校管理前端接收 URL 参数 session_token
  │
  ▼
前端调用 POST /api/auth/verify，body: { session_token: "{token}" }
  │
  ▼
学校管理后端 AuthService::verifySessionToken()
  ├─ 从 Redis 读取 school_mgmt_token:{token}
  ├─ 验证成功后立即删除该 key（一次性令牌）
  ├─ 查询数据库获取用户信息
  └─ 返回 JWT token 给前端
  │
  ▼
前端保存 JWT，完成登录，展示学校管理界面
```

---

## 测试前准备

1. 确保主系统（Yii2）运行在 `http://localhost:8080`（或实际地址）
2. 确保学校管理后端运行在 `http://localhost:8081`
3. 确保学校管理前端运行在 `http://localhost:3002`
4. 确保 Redis 服务正常运行
5. 确保两个系统连接到同一个 Redis 实例

---

## 测试步骤

### 步骤 1：验证主系统导航链接

1. 打开主系统，使用有效账号登录
2. 检查顶部导航栏是否出现「学校管理」按钮（带 `fa-graduation-cap` 图标）
3. 未登录状态下，该按钮不应显示

**预期结果**：已登录用户可以看到「学校管理」导航按钮。

---

### 步骤 2：验证 Token 生成与 Redis 写入

1. 点击「学校管理」按钮
2. 在浏览器开发者工具的 Network 面板中观察请求
3. 请求应命中 `/school-management/redirect`，然后发生 302 重定向
4. 重定向目标 URL 格式应为：`http://localhost:3002?session_token=<64位十六进制字符串>`

**手动验证 Redis**（可选）：
```bash
# 在 Redis CLI 中执行（需在重定向发生后 60 秒内）
redis-cli KEYS "school_mgmt_token:*"
redis-cli GET "school_mgmt_token:<token值>"
# 预期输出：{"user_id":123,"created_at":1234567890}
```

---

### 步骤 3：验证学校管理系统自动登录

1. 浏览器跳转到学校管理系统后，应自动完成登录
2. 页面应直接显示学校管理界面，而非登录页
3. 顶部导航栏应显示当前用户的昵称和头像

**预期结果**：无需手动输入账号密码，自动完成认证。

---

### 步骤 4：验证 Token 一次性使用

1. 复制步骤 2 中的完整跳转 URL（含 `session_token` 参数）
2. 在新标签页中再次访问该 URL
3. 应跳转到登录页或显示认证失败提示

**预期结果**：同一 token 只能使用一次，重复使用应失败。

---

### 步骤 5：验证 Token 过期

1. 获取一个跳转 URL（含 `session_token`）
2. 等待 60 秒后再访问该 URL
3. 应跳转到登录页或显示认证失败提示

**预期结果**：token 在 60 秒后自动失效。

---

### 步骤 6：验证「返回主系统」功能

1. 在学校管理系统中，点击顶部导航栏的「返回主系统」按钮
2. 应跳转回主系统首页

**预期结果**：成功跳转回主系统，且主系统登录状态保持不变。

---

### 步骤 7：验证未登录跳转保护

1. 在主系统中退出登录
2. 直接访问 `/school-management/redirect`
3. 应被重定向到主系统登录页

**预期结果**：未认证用户无法直接访问跳转接口。

---

## API 接口测试

### POST /api/auth/verify

**请求**：
```http
POST http://localhost:8081/api/auth/verify
Content-Type: application/json

{
  "session_token": "<从主系统获取的token>"
}
```

**成功响应（200）**：
```json
{
  "code": 200,
  "message": "Success",
  "data": {
    "token": "<JWT token>",
    "user": {
      "id": 123,
      "username": "teacher01",
      "nickname": "张老师",
      "avatar": "http://..."
    }
  },
  "timestamp": 1234567890
}
```

**失败响应（401）**：
```json
{
  "code": 401,
  "message": "Invalid or expired session token",
  "timestamp": 1234567890
}
```

---

## 常见问题与解决方案

### 问题 1：点击「学校管理」后提示 Redis 连接失败

**原因**：主系统未正确配置 Redis 组件，或 Redis 服务未启动。

**解决方案**：
- 检查主系统 `common/config/main-local.php` 中的 Redis 配置
- 确认 Redis 服务正在运行：`redis-cli ping`（应返回 `PONG`）

---

### 问题 2：跳转到学校管理系统后停留在登录页

**原因**：
- Token 已过期（超过 60 秒）
- Redis key 前缀不匹配（主系统写入的 key 与学校管理系统读取的 key 不一致）
- 学校管理后端无法连接到 Redis

**解决方案**：
- 确认主系统写入 key 格式为 `school_mgmt_token:{token}`
- 确认学校管理后端读取同样的 key 格式
- 检查两个系统是否连接到同一 Redis 实例（host、port、database 编号一致）

---

### 问题 3：Token 验证成功但用户信息获取失败

**原因**：学校管理系统数据库中找不到对应的用户记录（user_id 不存在）。

**解决方案**：
- 确认两个系统共享同一个 MySQL 数据库
- 检查 `user` 表中是否存在对应的用户记录

---

### 问题 4：CORS 错误

**原因**：学校管理后端未正确配置跨域策略。

**解决方案**：
- 检查学校管理后端的 CORS 中间件配置
- 确认 `VITE_API_BASE_URL` 环境变量指向正确的后端地址

---

### 问题 5：「返回主系统」按钮跳转地址错误

**原因**：前端环境变量 `VITE_MAIN_SYSTEM_URL` 未配置。

**解决方案**：
在 `school-management-system/frontend/.env` 或 `.env.local` 中添加：
```env
VITE_MAIN_SYSTEM_URL=http://localhost:8080
```

---

## 环境变量配置参考

### 主系统（backend/advanced/backend/config/params.php）
```php
'schoolManagementUrl' => 'http://localhost:3002',
```

### 学校管理前端（.env.local）
```env
VITE_API_BASE_URL=http://localhost:8081
VITE_MAIN_SYSTEM_URL=http://localhost:8080
```

### 学校管理后端（.env）
```env
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0
```
