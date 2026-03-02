# 代码审查清单 (Code Review Checklist)

本清单用于 PR 审查时逐项检查代码质量、架构合规性、安全性、性能和部署配置。审查人员应根据变更范围选择适用的检查项。

---

## 1. 代码质量标准 (Code Quality Standards)

### 1.1 PHP 编码规范 (PSR-12)

- [ ] 代码遵循 PSR-12 编码风格（可通过 `composer cs-check` 验证）
- [ ] 类名使用 PascalCase（如 `SchoolService`、`ClassController`）
- [ ] 方法名使用 camelCase（如 `findById`、`getList`）
- [ ] 常量使用 UPPER_SNAKE_CASE（如 `MAX_PAGE_SIZE`）
- [ ] 所有方法声明了参数类型和返回类型
- [ ] 使用严格类型声明 `declare(strict_types=1)`
- [ ] 命名空间与目录结构一致（`App\Controller\`、`App\Service\` 等）
- [ ] 每个文件只包含一个类

### 1.2 TypeScript / Vue3 编码规范

- [ ] 避免使用 `any` 类型，所有变量和函数有明确类型
- [ ] Vue 组件使用 `<script setup>` 语法（Composition API）
- [ ] 组件文件名使用 PascalCase（如 `SchoolList.vue`）
- [ ] 函数和变量使用 camelCase
- [ ] 常量使用 UPPER_SNAKE_CASE
- [ ] 类型定义放在 `src/types/` 目录下
- [ ] API 接口封装放在 `src/api/` 目录下，按模块分文件
- [ ] 无未使用的导入、变量或函数

### 1.3 命名规范

- [ ] 变量名和函数名具有描述性，能表达其用途
- [ ] 布尔变量使用 `is`/`has`/`can` 前缀（如 `isLoading`、`hasPermission`）
- [ ] 事件处理函数使用 `handle` 前缀（如 `handleSubmit`、`handleDelete`）
- [ ] API 方法名与 HTTP 动作对应（`getSchools`、`createSchool`、`updateSchool`、`deleteSchool`）
- [ ] 数据库字段使用 snake_case（如 `school_id`、`created_at`）

### 1.4 代码文档

- [ ] 所有 PHP 公共方法有 PHPDoc 注释（`@param`、`@return`、`@throws`）
- [ ] 所有 TypeScript 公共函数有 JSDoc/TSDoc 注释
- [ ] 复杂业务逻辑有行内注释说明意图
- [ ] 接口（interface）和类型（type）定义有文档注释
- [ ] 不包含被注释掉的无用代码
- [ ] TODO/FIXME 注释附带了负责人或 issue 编号

---

## 2. 架构审查 (Architecture Review)

### 2.1 关注点分离 (Controller → Service → Repository)

- [ ] Controller 只负责 HTTP 请求/响应处理，不包含业务逻辑
- [ ] Service 层封装所有业务逻辑、权限检查和事务管理
- [ ] Repository 层封装所有数据库查询，不包含业务逻辑
- [ ] Model 层仅定义数据结构和字段映射
- [ ] 不存在跨层调用（如 Controller 直接调用 Repository）

### 2.2 依赖注入

- [ ] 使用构造函数注入依赖，不使用 `new` 直接实例化服务类
- [ ] 依赖通过接口或类型声明，便于测试和替换
- [ ] 前端 Pinia Store 通过 `useXxxStore()` 获取，不直接导入内部状态

### 2.3 错误处理一致性

- [ ] 后端使用自定义异常类（`ValidationException`、`NotFoundException`、`UnauthorizedException`、`ForbiddenException`）
- [ ] 异常在 Controller 层或中间件统一捕获并转换为 JSON 响应
- [ ] 前端通过 Axios 拦截器统一处理 HTTP 错误（401 跳转登录、403 提示无权限）
- [ ] 不存在裸 `try/catch` 吞掉异常而不记录日志的情况

### 2.4 API 响应格式一致性

- [ ] 所有成功响应使用 `ResponseHelper::success()` 返回统一格式：`{ code, message, data, timestamp }`
- [ ] 所有错误响应使用 `ResponseHelper::error()` 返回统一格式：`{ code, message, errors, timestamp }`
- [ ] 列表接口返回分页结构：`{ items, pagination: { total, page, pageSize, totalPages } }`
- [ ] HTTP 状态码使用正确（200 成功、201 创建、400 参数错误、401 未认证、403 无权限、404 不存在）

---

## 3. 安全审查 (Security Review)

### 3.1 输入验证

- [ ] 所有 API 端点对请求参数进行验证（使用 `ValidatorHelper`）
- [ ] 验证规则覆盖必填字段、类型、长度、格式
- [ ] 分页参数有合理的上下限（page >= 1，pageSize <= 100）
- [ ] ID 参数验证为正整数
- [ ] 文件上传验证文件类型和大小

### 3.2 SQL 注入防护

- [ ] 所有数据库查询使用参数化查询（PDO prepared statements）
- [ ] 不存在字符串拼接 SQL 语句的情况
- [ ] 动态排序字段使用白名单验证（不直接拼接用户输入的字段名）
- [ ] `LIKE` 查询对特殊字符（`%`、`_`）进行转义

### 3.3 XSS 防护

- [ ] 所有用户输入经过 `SecurityHelper::sanitize()` 处理后再存储
- [ ] 前端使用 `v-text` 或 `{{ }}` 输出文本（自动转义），避免 `v-html`
- [ ] API 响应的 Content-Type 设置为 `application/json`
- [ ] 设置了 `X-Content-Type-Options: nosniff` 响应头

### 3.4 认证与授权

- [ ] 所有受保护的 API 端点经过 `AuthMiddleware` 验证 JWT
- [ ] JWT 令牌设置了合理的过期时间
- [ ] 权限检查在 Service 层执行，不仅依赖前端路由守卫
- [ ] 删除和修改操作验证用户对目标资源的所有权/权限
- [ ] 健康检查端点（`/health`、`/version`）不需要认证

### 3.5 敏感数据处理

- [ ] JWT 密钥通过环境变量配置，不硬编码在代码中
- [ ] 数据库密码通过环境变量配置
- [ ] 日志中不记录完整的 JWT 令牌或密码
- [ ] 前端不在 `localStorage` 中存储密码等敏感信息（JWT token 除外）
- [ ] `.env` 文件已加入 `.gitignore`

---

## 4. 性能审查 (Performance Review)

### 4.1 N+1 查询防护

- [ ] 列表查询使用 JOIN 一次性获取关联数据，而非循环中逐条查询
- [ ] Repository 层的关联查询使用 `QueryOptimizer` 优化
- [ ] 批量操作使用批量 SQL（`INSERT INTO ... VALUES (...), (...)`），而非循环单条插入

### 4.2 缓存使用

- [ ] 频繁读取的数据使用 `CacheHelper` 缓存到 Redis
- [ ] 缓存键命名规范：`{模块}:{操作}:{参数}`（如 `school:detail:123`）
- [ ] 写操作后清除相关缓存，保证数据一致性
- [ ] 缓存设置了合理的 TTL（列表 5 分钟、详情 10 分钟、用户信息 30 分钟）
- [ ] 不缓存包含用户特定权限的数据（避免权限泄露）

### 4.3 分页

- [ ] 所有列表 API 端点实现了分页
- [ ] 默认每页 20 条，最大不超过 100 条
- [ ] 分页响应包含 `total`、`page`、`pageSize`、`totalPages`
- [ ] 前端列表页面使用分页组件或虚拟滚动

### 4.4 前端性能

- [ ] 大列表使用 `VirtualList` 虚拟滚动组件
- [ ] 图片使用懒加载（`loading="lazy"` 或 Intersection Observer）
- [ ] 路由组件使用懒加载（`() => import(...)`）
- [ ] 避免在模板中使用复杂计算，使用 `computed` 缓存
- [ ] 不在 `watch` 或生命周期钩子中执行不必要的 API 请求

---

## 5. 测试审查 (Testing Review)

### 5.1 测试覆盖

- [ ] 新增的 Service 层方法有对应的单元测试
- [ ] 新增的 Helper/工具函数有对应的单元测试
- [ ] 关键业务流程有集成测试覆盖
- [ ] 前端工具函数（`utils/`）有对应的测试文件

### 5.2 边界情况

- [ ] 测试覆盖空输入、空列表、null 值等边界情况
- [ ] 测试覆盖权限不足的场景（期望返回 403）
- [ ] 测试覆盖资源不存在的场景（期望返回 404）
- [ ] 测试覆盖参数验证失败的场景（期望返回 400）
- [ ] 测试覆盖重复数据的场景（如重复添加教师）

### 5.3 Mock 使用

- [ ] 单元测试中 Mock 外部依赖（数据库、Redis、HTTP 请求）
- [ ] 集成测试中尽量使用真实依赖，减少 Mock
- [ ] Mock 数据与真实数据结构一致
- [ ] 不使用 Mock 来掩盖实际的代码缺陷

---

## 6. 部署审查 (Deployment Review)

### 6.1 Docker 配置

- [ ] `Dockerfile` 使用多阶段构建，减小镜像体积
- [ ] 基础镜像指定了具体版本标签（不使用 `latest`）
- [ ] 容器以非 root 用户运行
- [ ] `docker-compose.yml` 中服务依赖关系正确（`depends_on`）
- [ ] 容器端口映射正确（前端 3002、后端 8084）

### 6.2 环境变量管理

- [ ] 所有可配置项通过环境变量注入，不硬编码
- [ ] `.env.example` 包含所有必需的环境变量及说明
- [ ] 敏感环境变量（密码、密钥）不提交到代码仓库
- [ ] `docker-compose.yml` 中使用 `env_file` 或 `environment` 正确传递变量

### 6.3 健康检查

- [ ] 后端提供 `/health` 端点，检查数据库和 Redis 连接状态
- [ ] 后端提供 `/version` 端点，返回应用版本信息
- [ ] Docker 容器配置了 `healthcheck`
- [ ] 健康检查端点响应时间在 1 秒以内

### 6.4 日志配置

- [ ] 后端使用 `Logger` 类记录结构化日志
- [ ] 日志级别配置合理（生产环境 WARNING 及以上，开发环境 DEBUG）
- [ ] 日志包含请求 ID，便于追踪
- [ ] 日志文件配置了轮转策略，防止磁盘占满
- [ ] 安全事件（认证失败、权限拒绝、异常输入）记录到日志

---

## 审查流程说明

### 审查优先级

1. **P0 - 必须修复**: 安全漏洞、数据丢失风险、生产环境崩溃
2. **P1 - 应该修复**: 架构违规、性能问题、缺少错误处理
3. **P2 - 建议改进**: 代码风格、命名优化、文档补充

### 审查步骤

1. 阅读 PR 描述，了解变更目的和范围
2. 根据变更范围选择适用的检查项
3. 逐项检查代码，标记通过/不通过
4. 对不通过的项目添加具体的修改建议
5. 确认所有 P0/P1 问题修复后批准合并
