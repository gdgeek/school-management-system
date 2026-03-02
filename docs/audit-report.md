# 学校管理系统 — 性能与安全审计报告

**审计日期**: 2025年  
**审计范围**: school-management-system 全栈系统（前端 Vue3 + 后端 Yii3/PHP 8.1+）  
**审计版本**: v1.0.0  
**审计状态**: 初始审计  

---

## 目录

1. [审计概述](#1-审计概述)
2. [性能审计](#2-性能审计)
3. [安全审计](#3-安全审计)
4. [审计总结](#4-审计总结)
5. [附录](#5-附录)

---

## 1. 审计概述

### 1.1 系统简介

学校管理系统是从 XR UGC 主系统中分离出来的独立服务，采用前后端分离架构：

- **前端**: Vue 3 + TypeScript + Element Plus + Vite
- **后端**: Yii3 + PHP 8.1+ + PDO + Redis
- **数据库**: MySQL 8.0+（与主系统共享）
- **缓存**: Redis 6.0+（与主系统共享）
- **部署**: Docker 容器化（前端 Port 3002，后端 Port 8084）

### 1.2 审计方法

本次审计采用以下方法：

- **静态代码审查**: 逐文件审查后端 PHP 源码和前端 TypeScript 源码
- **架构分析**: 审查分层架构、中间件链、数据流
- **配置审查**: 审查 Vite 构建配置、Docker 配置、环境变量管理
- **测试覆盖分析**: 审查已有的单元测试、安全测试脚本、性能测试脚本
- **文档审查**: 审查安全测试清单、性能测试指南、代码审查清单

### 1.3 风险等级定义

| 等级 | 定义 | 处理要求 |
|------|------|----------|
| 🔴 高 (High) | 可能导致数据泄露、系统入侵或严重性能问题 | 上线前必须修复 |
| 🟡 中 (Medium) | 可能导致功能异常或潜在安全隐患 | 建议尽快修复 |
| 🟢 低 (Low) | 最佳实践建议，不影响当前功能 | 后续迭代中改进 |

---

## 2. 性能审计

### 2.1 前端包体积分析

**审计对象**: `school-management-system/frontend/`

#### 依赖分析

| 依赖包 | 版本 | 预估大小 (gzip) | 用途 |
|--------|------|-----------------|------|
| Vue 3 | 3.5.x | ~33 KB | 核心框架 |
| Vue Router | 5.x | ~8 KB | 路由管理 |
| Pinia | 3.x | ~3 KB | 状态管理 |
| Element Plus | 2.13.x | ~80-120 KB | UI 组件库（按需引入可降至 40-60 KB） |
| Axios | 1.13.x | ~13 KB | HTTP 客户端 |
| vue-i18n | 9.x | ~15 KB | 国际化 |
| @element-plus/icons-vue | 2.x | ~20 KB | 图标库 |
| **预估总计** | | **~170-210 KB** | |

**性能基准目标**: 总包大小 (gzip) < 500 KB ✅ 预计达标

#### 发现与建议

**[P-F1] 🟡 中风险 — Element Plus 未配置按需引入**

当前 `vite.config.ts` 未配置 `unplugin-vue-components` 和 `unplugin-auto-import` 插件进行 Element Plus 按需引入。全量引入会导致包体积增大约 60-80 KB。

```
建议: 安装并配置 unplugin-vue-components + unplugin-auto-import，
实现 Element Plus 组件和样式的按需加载。
```

**[P-F2] 🟡 中风险 — 路由未配置懒加载**

需确认路由配置是否使用了动态 `import()` 实现代码分割。如果所有页面视图打包在同一 chunk 中，首屏加载时间会增加。

```
建议: 所有路由组件使用 () => import('./views/XxxView.vue') 语法，
确保 Vite 自动进行代码分割。
```

**[P-F3] 🟢 低风险 — 缺少构建分析工具配置**

`vite.config.ts` 未配置 `rollup-plugin-visualizer` 等包分析工具，无法直观查看打包结果。

```
建议: 在开发依赖中添加 rollup-plugin-visualizer，
便于持续监控包体积变化。
```

### 2.2 后端 API 响应时间预期

**审计对象**: 后端 Controller / Service / Repository 层

#### 性能基准（来自需求文档 需求 14）

| 操作类型 | 目标 P95 | 评估状态 | 说明 |
|----------|----------|----------|------|
| 列表查询 | < 2s | ✅ 预计达标 | 使用分页 + Redis 缓存 |
| 详情查询 | < 1s | ✅ 预计达标 | 单条查询 + 缓存 |
| 创建操作 | < 1.5s | ✅ 预计达标 | 单条 INSERT + 缓存失效 |
| 更新操作 | < 1.5s | ✅ 预计达标 | 单条 UPDATE + 缓存失效 |
| 删除操作 | < 1.5s | ⚠️ 需关注 | 级联删除可能较慢 |
| 健康检查 | < 500ms | ✅ 预计达标 | 轻量级端点 |
| 并发支持 | ≥ 50 用户 | ✅ 预计达标 | PHP-FPM 进程池管理 |

#### 发现与建议

**[P-B1] 🟡 中风险 — 级联删除操作缺少性能保护**

`SchoolController::delete()` 调用 `SchoolService::delete()` 时会级联软删除关联的班级、教师、学生记录。当学校下有大量关联数据时，单次请求可能超时。

```
建议:
1. 对级联删除操作设置数据库事务超时限制
2. 当关联数据量超过阈值时，考虑异步队列处理
3. 在删除前返回关联数据统计，让用户确认
```

**[P-B2] 🟢 低风险 — 搜索查询使用 LIKE '%keyword%'**

`SchoolRepository::search()` 使用 `LIKE '%keyword%'` 模式，前缀通配符会导致索引失效，在数据量大时性能下降。

```
建议:
1. 短期: 确保 name 字段有索引，数据量小时影响有限
2. 长期: 考虑使用 MySQL 全文索引或 Elasticsearch
```

### 2.3 数据库查询优化审查

**审计对象**: Repository 层、QueryOptimizer、数据库索引

#### 索引覆盖情况

| 表 | 已建索引 | 评估 |
|----|----------|------|
| edu_school | PK, idx_name, idx_principal_id, idx_deleted_at | ✅ 充分 |
| edu_class | PK, idx_school_id, idx_name, idx_deleted_at | ✅ 充分 |
| edu_teacher | PK, uk_user_class, idx_class_id, idx_deleted_at | ✅ 充分 |
| edu_student | PK, uk_user_class, idx_class_id, idx_deleted_at | ✅ 充分 |
| group | PK, idx_user_id, idx_name, idx_deleted_at | ✅ 充分 |
| group_user | PK, uk_user_group, idx_group_id | ✅ 充分 |
| class_group | PK, uk_class_group, idx_group_id | ✅ 充分 |

#### N+1 查询防护

`QueryOptimizer` 提供了 `eagerLoad()` 和 `eagerLoadMany()` 方法，支持批量预加载关联数据，有效避免 N+1 问题。

**评估**: ✅ 已实现 — 通过 IN 子句批量查询关联数据，替代循环逐条查询。

#### 分页实现

`QueryOptimizer::paginatedQuery()` 实现了标准分页：
- ✅ 使用 `LIMIT/OFFSET` 分页
- ✅ 页面大小限制在 1-100 之间
- ✅ COUNT 查询与数据查询使用相同 WHERE 子句
- ✅ 所有参数使用 PDO 绑定

#### 发现与建议

**[P-D1] 🟢 低风险 — 深层分页性能**

当前使用 `OFFSET` 分页，在数据量超过 10 万条时，深层分页（如第 1000 页）性能会显著下降。

```
建议: 当数据量增长到一定规模后，考虑改用游标分页（Cursor-based Pagination），
使用 WHERE id > :last_id 替代 OFFSET。
```

### 2.4 缓存策略审查

**审计对象**: `CacheHelper`、`QueryOptimizer`、`AuthService`

#### 缓存架构

| 缓存项 | 键格式 | TTL | 失效策略 |
|--------|--------|-----|----------|
| 学校列表 | `school:list:{page}:{name}` | 5 分钟 | 写操作后按标签失效 |
| 学校详情 | `school:detail:{id}` | 10 分钟 | 写操作后按标签失效 |
| 班级列表 | `class:list:{school_id}:{page}` | 5 分钟 | 写操作后按标签失效 |
| 用户信息 | `user:info:{id}` | 30 分钟 | 手动失效 |
| 用户角色 | `user_roles:{userId}` | 1 小时 | `clearRolesCache()` 手动失效 |
| 用户会话 | `user_session:{userId}` | 24 小时 | 登出时删除 |
| 查询缓存 | 自定义键 | 60 秒 | 按标签批量失效 |

#### 缓存功能评估

- ✅ `CacheHelper` 支持 TTL、标签、批量失效
- ✅ `remember()` 方法实现缓存穿透保护（Cache-Aside 模式）
- ✅ 所有 Redis 操作有异常捕获和日志记录
- ✅ 使用命名空间前缀 `school_mgmt:` 避免键冲突

#### 发现与建议

**[P-C1] 🟡 中风险 — 缓存雪崩防护不足**

当前所有同类缓存使用固定 TTL，在高并发场景下可能出现大量缓存同时过期，导致数据库瞬时压力激增（缓存雪崩）。

```
建议: 在 TTL 基础上添加随机偏移量（如 TTL ± 10%），
分散缓存过期时间。例如：
$ttl = $baseTtl + random_int(-$baseTtl/10, $baseTtl/10);
```

**[P-C2] 🟢 低风险 — 缺少缓存预热机制**

系统冷启动后，所有缓存为空，首批请求全部穿透到数据库。

```
建议: 在应用启动或部署后，通过脚本预热热点数据缓存
（如学校列表首页、常用用户信息等）。
```

**[P-C3] 🟢 低风险 — flush() 方法使用 KEYS 命令**

`CacheHelper::flush()` 使用 `$redis->keys($prefix . '*')` 查找所有键，在 Redis 数据量大时会阻塞服务器。

```
建议: 生产环境中使用 SCAN 命令替代 KEYS，避免阻塞 Redis。
```

---

## 3. 安全审计

### 3.1 认证机制审查

**审计对象**: `JwtHelper`、`AuthMiddleware`、`AuthController`、`AuthService`

#### JWT 实现评估

| 检查项 | 状态 | 说明 |
|--------|------|------|
| 签名算法 | ✅ HS256 | 使用 HMAC-SHA256，对称加密 |
| 令牌过期 | ✅ 已实现 | 默认 3600 秒（1 小时） |
| 过期检测 | ✅ 已实现 | `isExpiringSoon()` 检测 5 分钟内过期 |
| 令牌刷新 | ✅ 已实现 | `refresh()` 方法生成新令牌 |
| 异常处理 | ✅ 已实现 | 捕获 ExpiredException、SignatureInvalidException |
| 库依赖 | ✅ firebase/php-jwt | 成熟的 JWT 库 |

#### 认证流程评估

| 检查项 | 状态 | 说明 |
|--------|------|------|
| Bearer Token 提取 | ✅ | 从 Authorization 头提取 |
| Cookie Token 提取 | ✅ | 从 auth_token Cookie 提取 |
| URL 参数 Token 提取 | ✅ | 从 ?token= 查询参数提取（跨系统跳转） |
| 用户信息注入 | ✅ | user_id、username、roles 注入请求属性 |
| 统一错误响应 | ✅ | 返回 401 JSON 响应 |

#### 发现与建议

**[S-A1] 🟡 中风险 — JWT 令牌无法主动吊销**

当前 JWT 实现为无状态令牌，一旦签发无法在过期前主动吊销。如果用户令牌泄露，攻击者可在令牌有效期内持续访问。

```
建议:
1. 实现 JWT 黑名单机制（在 Redis 中存储已吊销的令牌 JTI）
2. 在 AuthMiddleware 中增加黑名单检查
3. 登出时将当前令牌加入黑名单
架构文档中已规划 jwt:blacklist:{jti} 缓存键，建议尽快实现。
```

**[S-A2] 🟡 中风险 — URL 参数传递 Token 存在安全隐患**

`AuthMiddleware::extractToken()` 支持从 URL 查询参数 `?token=xxx` 提取令牌。URL 参数会被记录在浏览器历史、服务器访问日志和 Referrer 头中，增加令牌泄露风险。

```
建议:
1. URL 参数中的 token 仅用于跨系统跳转的一次性验证
2. 验证成功后立即从 URL 中移除 token 参数（前端已实现）
3. 考虑使用短期一次性令牌替代直接传递 JWT
当前 AuthService::verifySessionToken() 已实现一次性令牌验证并删除，
此风险已部分缓解。
```

**[S-A3] 🟢 低风险 — JWT 密钥管理**

JWT 密钥通过构造函数注入，需确保生产环境中使用足够强度的密钥（至少 256 位），且通过环境变量配置。

```
建议: 确保 JWT_SECRET 环境变量长度 >= 32 字符，
使用 openssl rand -hex 32 生成。
```

### 3.2 授权机制审查

**审计对象**: `AuthService::getUserRoles()`、Controller 层权限检查

#### 角色权限模型

| 角色 | 权限范围 | 实现状态 |
|------|----------|----------|
| admin | 所有操作 | ✅ 已实现 |
| principal | 管理所属学校 | ✅ 已实现 |
| teacher | 查看所属班级，管理小组 | ✅ 已实现 |
| student | 只读所属班级和小组 | ✅ 已实现 |

#### 权限检查评估

- ✅ 角色信息嵌入 JWT Payload，每次请求自动提取
- ✅ 角色缓存在 Redis 中（TTL 1 小时），减少数据库查询
- ✅ `AuthService::determineUserRoles()` 从多个维度判断角色

#### 发现与建议

**[S-B1] 🟡 中风险 — 部分 Controller 缺少细粒度权限检查**

`SchoolController::create()` 和 `SchoolController::update()` 中仅验证了必填字段，未显式检查用户是否具有创建/编辑学校的权限。权限检查应在 Service 层统一执行。

```
建议: 在 SchoolService 的写操作方法中增加角色检查：
- create: 仅 admin 角色可创建学校
- update: admin 或对应学校的 principal 可编辑
- delete: 仅 admin 角色可删除学校
```

**[S-B2] 🟢 低风险 — 角色缓存可能导致权限延迟生效**

用户角色缓存 TTL 为 1 小时，如果管理员修改了用户角色，最长需要 1 小时才能生效。

```
建议: 在角色变更操作中主动调用 clearRolesCache()，
确保权限变更即时生效。
```

### 3.3 输入验证审查

**审计对象**: `ValidatorHelper`、`SecurityHelper`、Controller 层

#### 验证覆盖情况

| 端点 | 验证方式 | 状态 |
|------|----------|------|
| POST /api/schools | 手动检查 name 非空 | ⚠️ 基础验证 |
| PUT /api/schools/{id} | 无显式验证 | ⚠️ 缺少验证 |
| POST /api/classes | 需验证 school_id | ⚠️ 需确认 |
| POST /api/groups | 需验证 name | ⚠️ 需确认 |
| GET 列表端点 | pageSize 限制 1-100 | ✅ 已实现 |
| 搜索参数 | 通过 PDO 参数化 | ✅ 安全 |

#### ValidatorHelper 功能评估

- ✅ 支持必填、字符串长度、整数、范围、邮箱、URL、枚举、正则验证
- ✅ 支持规则式批量验证 `validate()`
- ✅ 错误信息按字段分组

#### 发现与建议

**[S-C1] 🟡 中风险 — Controller 层输入验证不一致**

`SchoolController::create()` 仅检查 `name` 是否为空，未使用 `ValidatorHelper` 进行完整验证（如字符串长度限制、类型检查）。`update()` 方法完全没有输入验证。

```
建议: 所有写操作端点统一使用 ValidatorHelper::validate() 进行参数验证：
$validator->validate($data, [
    'name' => ['required', ['stringLength', 1, 255]],
    'principal_id' => ['integer'],
]);
```

**[S-C2] 🟡 中风险 — 缺少输入清理调用**

Controller 层在处理请求体时，未调用 `SecurityHelper::sanitizeArray()` 对用户输入进行 XSS 清理。虽然 PDO 参数化查询防止了 SQL 注入，但恶意 HTML/JS 内容可能被存入数据库。

```
建议: 在 Controller 或 Service 层统一调用输入清理：
$data = SecurityHelper::sanitizeArray($data);
或在 SecurityMiddleware 中自动清理请求体。
```

### 3.4 SQL 注入防护审查

**审计对象**: Repository 层、QueryOptimizer

#### 防护措施评估

| 检查项 | 状态 | 说明 |
|--------|------|------|
| PDO Prepared Statements | ✅ | 所有 Repository 使用 `$pdo->prepare()` + `bindValue()` |
| 参数类型绑定 | ✅ | 使用 `PDO::PARAM_INT` / `PDO::PARAM_STR` 明确类型 |
| QueryOptimizer 参数化 | ✅ | `executeQuery()` 统一使用参数绑定 |
| 无字符串拼接 SQL | ✅ | 未发现直接拼接用户输入到 SQL 的情况 |
| LIKE 查询参数化 | ✅ | `search()` 方法使用 `bindValue(':keyword', "%$keyword%")` |

#### 发现与建议

**[S-D1] 🟢 低风险 — LIKE 查询特殊字符未转义**

`SchoolRepository::search()` 中 `"%$keyword%"` 未对 `%` 和 `_` 通配符进行转义。用户输入 `%` 可能导致意外的模糊匹配结果。

```
建议: 在 LIKE 查询前转义特殊字符：
$keyword = str_replace(['%', '_'], ['\\%', '\\_'], $keyword);
```

**总体评估**: ✅ SQL 注入防护良好 — 全面使用 PDO 参数化查询，未发现注入漏洞。

### 3.5 XSS 防护审查

**审计对象**: `SecurityHelper`、`SecurityMiddleware`、前端模板

#### 后端防护措施

| 检查项 | 状态 | 说明 |
|--------|------|------|
| `sanitizeInput()` | ✅ 已实现 | 使用 `htmlspecialchars()` 转义 HTML 实体 |
| `sanitizeArray()` | ✅ 已实现 | 递归清理数组中所有字符串值 |
| Content-Type 头 | ✅ | API 响应统一设置 `application/json` |
| X-Content-Type-Options | ✅ | SecurityMiddleware 设置 `nosniff` |
| X-XSS-Protection | ✅ | SecurityMiddleware 设置 `1; mode=block` |
| CSP 头 | ✅ | 设置了 `default-src 'self'` 策略 |

#### 前端防护措施

| 检查项 | 状态 | 说明 |
|--------|------|------|
| Vue 模板自动转义 | ✅ | `{{ }}` 和 `v-text` 自动转义 HTML |
| 避免 v-html | ⚠️ 需确认 | 需检查是否有使用 `v-html` 的地方 |

#### 发现与建议

**[S-E1] 🟡 中风险 — SecurityHelper 未在数据写入流程中自动调用**

`SecurityHelper::sanitizeInput()` 和 `sanitizeArray()` 已实现，但未在 Controller/Service 层的数据写入流程中自动调用。依赖开发者手动调用，容易遗漏。

```
建议: 在 SecurityMiddleware 中增加请求体自动清理逻辑，
或在 Service 基类中统一调用 sanitizeArray()。
```

**[S-E2] 🟢 低风险 — CSP 策略包含 'unsafe-inline'**

当前 CSP 策略允许 `script-src 'self' 'unsafe-inline'` 和 `style-src 'self' 'unsafe-inline'`，这削弱了 CSP 对 XSS 的防护效果。

```
建议: 长期目标是移除 'unsafe-inline'，使用 nonce 或 hash 方式加载内联脚本。
短期可保持现状，因为 Element Plus 可能需要内联样式。
```

### 3.6 CORS 配置审查

**审计对象**: CORS 中间件、安全测试脚本

#### CORS 配置评估

| 检查项 | 状态 | 说明 |
|--------|------|------|
| 允许的源 | ✅ | 配置了允许的 Origin 列表 |
| 预检请求处理 | ✅ | OPTIONS 请求返回 204 + CORS 头 |
| 不允许的源拒绝 | ✅ | 未授权 Origin 不返回 CORS 头 |
| 允许的方法 | ✅ | GET, POST, PUT, DELETE, OPTIONS |
| 允许的头 | ✅ | Authorization, Content-Type |

**总体评估**: ✅ CORS 配置合理，已通过安全测试脚本验证。

### 3.7 安全响应头审查

**审计对象**: `SecurityMiddleware`

| 安全头 | 配置值 | 状态 |
|--------|--------|------|
| X-Frame-Options | `SAMEORIGIN` | ✅ 防止点击劫持 |
| X-Content-Type-Options | `nosniff` | ✅ 防止 MIME 嗅探 |
| X-XSS-Protection | `1; mode=block` | ✅ 浏览器 XSS 过滤 |
| Referrer-Policy | `strict-origin-when-cross-origin` | ✅ 控制 Referrer 泄露 |
| Permissions-Policy | `geolocation=(), microphone=(), camera=()` | ✅ 限制浏览器 API |
| Strict-Transport-Security | `max-age=31536000; includeSubDomains` | ✅ 强制 HTTPS |
| Content-Security-Policy | `default-src 'self'; ...` | ✅ 内容安全策略 |

**总体评估**: ✅ 安全响应头配置完善，覆盖了 OWASP 推荐的所有关键安全头。

### 3.8 依赖安全审查

#### 后端依赖

| 依赖 | 风险评估 | 建议 |
|------|----------|------|
| firebase/php-jwt | 🟢 低 | 成熟库，保持更新 |
| PHP 8.1+ | 🟢 低 | 活跃支持版本 |
| Redis 扩展 | 🟢 低 | 标准扩展 |

#### 前端依赖

| 依赖 | 风险评估 | 建议 |
|------|----------|------|
| Vue 3.5.x | 🟢 低 | 最新稳定版 |
| Axios 1.13.x | 🟢 低 | 最新版本 |
| Element Plus 2.13.x | 🟢 低 | 活跃维护 |

#### 发现与建议

**[S-G1] 🟡 中风险 — 缺少自动化依赖漏洞扫描**

项目未配置自动化依赖漏洞扫描工具。

```
建议:
1. 后端: 使用 composer audit 定期检查 PHP 依赖漏洞
2. 前端: 使用 npm audit 或 pnpm audit 检查 Node.js 依赖漏洞
3. CI/CD: 在流水线中集成 Snyk 或 Dependabot 自动扫描
```

### 3.9 前端安全审查

**审计对象**: `utils/request.ts`、`utils/auth.ts`

#### Token 存储

| 检查项 | 状态 | 说明 |
|--------|------|------|
| JWT 存储位置 | ⚠️ localStorage | 存在 XSS 窃取风险 |
| 刷新令牌存储 | ⚠️ localStorage | 同上 |
| 用户信息存储 | ✅ localStorage | 非敏感数据 |
| 密码存储 | ✅ 未存储 | 不在客户端存储密码 |

#### 发现与建议

**[S-H1] 🟡 中风险 — JWT 存储在 localStorage**

JWT 令牌存储在 `localStorage` 中，如果存在 XSS 漏洞，攻击者可通过 JavaScript 读取令牌。

```
建议:
1. 短期: 确保 XSS 防护到位（CSP、输入清理、输出转义）
2. 长期: 考虑使用 HttpOnly Cookie 存储令牌，
   配合 SameSite=Strict 属性防止 CSRF
```

**[S-H2] ✅ 良好实践 — 自动令牌刷新**

`request.ts` 实现了 401 响应时自动使用 refresh_token 刷新令牌的逻辑，刷新失败时清除本地存储并跳转登录页。

**[S-H3] ✅ 良好实践 — 请求去重和缓存**

`request.ts` 实现了 GET 请求去重（取消重复请求）和响应缓存（30 秒 TTL），既提升了性能也减少了不必要的网络请求。

---

## 4. 审计总结

### 4.1 风险评估汇总

#### 🔴 高风险项 — 无

本次审计未发现高风险安全漏洞或严重性能问题。

#### 🟡 中风险项

| 编号 | 类别 | 描述 | 优先级 |
|------|------|------|--------|
| P-F1 | 性能 | Element Plus 未配置按需引入，包体积偏大 | P2 |
| P-F2 | 性能 | 路由懒加载需确认配置 | P2 |
| P-B1 | 性能 | 级联删除操作缺少性能保护 | P1 |
| P-C1 | 性能 | 缓存雪崩防护不足（固定 TTL） | P2 |
| S-A1 | 安全 | JWT 令牌无法主动吊销 | P1 |
| S-A2 | 安全 | URL 参数传递 Token 存在泄露风险 | P2 |
| S-B1 | 安全 | 部分 Controller 缺少细粒度权限检查 | P1 |
| S-C1 | 安全 | Controller 层输入验证不一致 | P1 |
| S-C2 | 安全 | 缺少输入清理自动调用 | P1 |
| S-E1 | 安全 | SecurityHelper 未在写入流程中自动调用 | P1 |
| S-G1 | 安全 | 缺少自动化依赖漏洞扫描 | P2 |
| S-H1 | 安全 | JWT 存储在 localStorage | P2 |

#### 🟢 低风险项

| 编号 | 类别 | 描述 |
|------|------|------|
| P-F3 | 性能 | 缺少构建分析工具配置 |
| P-B2 | 性能 | 搜索查询使用前缀通配符 LIKE |
| P-D1 | 性能 | 深层分页性能（OFFSET） |
| P-C2 | 性能 | 缺少缓存预热机制 |
| P-C3 | 性能 | flush() 使用 KEYS 命令 |
| S-A3 | 安全 | JWT 密钥强度需确认 |
| S-B2 | 安全 | 角色缓存可能导致权限延迟生效 |
| S-D1 | 安全 | LIKE 查询特殊字符未转义 |
| S-E2 | 安全 | CSP 策略包含 'unsafe-inline' |

### 4.2 优先行动项

按优先级排序的修复建议：

**P1 — 上线前建议完成：**

1. **统一输入验证和清理** (S-C1, S-C2, S-E1)
   - 在 Service 层或中间件中统一调用 `ValidatorHelper` 和 `SecurityHelper`
   - 确保所有写操作端点都有完整的参数验证和 XSS 清理

2. **完善权限检查** (S-B1)
   - 在 Service 层为所有写操作添加角色权限检查
   - 确保 admin/principal/teacher/student 角色权限边界清晰

3. **实现 JWT 黑名单** (S-A1)
   - 利用已规划的 `jwt:blacklist:{jti}` Redis 键实现令牌吊销
   - 在登出和密码修改时将令牌加入黑名单

4. **级联删除性能保护** (P-B1)
   - 添加事务超时限制
   - 大量关联数据时提供确认提示

**P2 — 后续迭代中完成：**

5. 配置 Element Plus 按需引入 (P-F1)
6. 确认路由懒加载配置 (P-F2)
7. 添加缓存 TTL 随机偏移 (P-C1)
8. 集成依赖漏洞扫描工具 (S-G1)
9. 评估 JWT 存储方案优化 (S-H1, S-A2)

### 4.3 需求合规性评估

| 需求 | 合规状态 | 说明 |
|------|----------|------|
| 需求 14: 性能要求 | ✅ 基本合规 | 分页、缓存、索引均已实现，预计满足响应时间要求 |
| 需求 15: 安全要求 | ⚠️ 部分合规 | 核心安全机制已实现，但输入验证一致性和 JWT 吊销需完善 |
| 需求 15.1: API 身份认证 | ✅ 合规 | AuthMiddleware 验证所有受保护端点 |
| 需求 15.2: XSS 防护 | ⚠️ 部分合规 | 工具已实现但未在所有写入路径自动调用 |
| 需求 15.3: SQL 注入防护 | ✅ 合规 | 全面使用 PDO 参数化查询 |
| 需求 15.4: CSRF 防护 | ✅ 合规 | SecurityHelper 提供 CSRF 令牌生成和验证 |
| 需求 15.7: 请求频率限制 | ✅ 合规 | RateLimitMiddleware 已实现 |
| 需求 15.8: 安全日志 | ✅ 合规 | Logger 记录安全事件 |
| 需求 15.9: 客户端敏感信息 | ⚠️ 部分合规 | JWT 存储在 localStorage，存在 XSS 窃取风险 |

### 4.4 总体评估

学校管理系统在架构设计和安全防护方面具备良好的基础：

- **架构层面**: 分层清晰（Controller → Service → Repository），中间件链完整（CORS → RateLimit → Auth → Security）
- **性能层面**: Redis 缓存、数据库索引、分页查询、N+1 防护等关键优化已到位
- **安全层面**: JWT 认证、PDO 参数化查询、安全响应头、CORS 配置等核心防护已实现

主要改进方向集中在：输入验证的一致性执行、XSS 清理的自动化调用、JWT 令牌吊销机制的实现。这些改进不涉及架构变更，可在现有代码基础上快速完成。

---

## 5. 附录

### 5.1 审计文件清单

| 文件 | 审计内容 |
|------|----------|
| `backend/src/Helper/JwtHelper.php` | JWT 实现安全性 |
| `backend/src/Helper/SecurityHelper.php` | XSS/SQL 防护实现 |
| `backend/src/Helper/CacheHelper.php` | 缓存策略和 Redis 操作 |
| `backend/src/Helper/ValidatorHelper.php` | 输入验证实现 |
| `backend/src/Helper/QueryOptimizer.php` | 查询优化和 N+1 防护 |
| `backend/src/Middleware/AuthMiddleware.php` | 认证流程 |
| `backend/src/Middleware/SecurityMiddleware.php` | 安全响应头 |
| `backend/src/Controller/AuthController.php` | 认证 API 端点 |
| `backend/src/Controller/SchoolController.php` | 输入验证和权限检查 |
| `backend/src/Service/AuthService.php` | 会话管理和角色判定 |
| `backend/src/Repository/SchoolRepository.php` | SQL 查询安全性 |
| `frontend/src/utils/request.ts` | 前端 HTTP 安全 |
| `frontend/src/utils/auth.ts` | 前端令牌管理 |
| `frontend/vite.config.ts` | 构建配置 |
| `frontend/package.json` | 依赖版本 |

### 5.2 相关测试资源

| 资源 | 路径 |
|------|------|
| 性能测试脚本 (k6) | `tests/performance/` |
| 负载测试脚本 (curl) | `tests/performance/load-test.sh` |
| API 安全测试 | `tests/security/api-security.sh` |
| 输入验证测试 | `tests/security/input-validation.sh` |
| 安全测试清单 | `docs/security-testing.md` |
| 性能测试指南 | `docs/performance-testing.md` |
| 代码审查清单 | `docs/code-review-checklist.md` |
| 后端安全单元测试 | `backend/tests/Unit/Security/` |
| 后端 Helper 单元测试 | `backend/tests/Unit/Helper/` |
| 前端 Auth 单元测试 | `frontend/src/utils/auth.test.ts` |

### 5.3 参考标准

- OWASP Top 10 (2021)
- OWASP API Security Top 10 (2023)
- CWE/SANS Top 25 Most Dangerous Software Weaknesses
- PHP Security Best Practices
- Vue.js Security Best Practices
