# 架构设计文档

## 系统概述

学校管理系统（School Management System）是从 XR UGC 主系统中分离出来的独立服务，专注于学校、班级、教师、学生和小组的管理。系统采用前后端分离架构，与主系统共享 MySQL 数据库和 Redis，通过统一认证实现无缝集成。

### 设计原则

- **架构解耦**: 独立代码库，独立部署，不依赖主系统代码
- **数据共享**: 共享数据库和 Redis，保证数据一致性
- **无缝集成**: 统一认证，用户无需重复登录
- **安全优先**: JWT 认证、RBAC 权限控制、输入验证、请求限流
- **性能优化**: Redis 缓存、数据库索引、分页查询、虚拟滚动

---

## 系统架构

### 整体架构

```
┌─────────────────────────────────────────────────────────────┐
│                         用户浏览器                           │
└──────────────┬──────────────────────────┬───────────────────┘
               │                          │
               ▼                          ▼
┌──────────────────────┐    ┌──────────────────────────────────┐
│      主系统           │    │         学校管理系统              │
│  Vue2 + Yii2         │    │  Vue3 + TypeScript + Yii3 + PHP  │
│  Port: 80            │    │  Frontend: 3002  Backend: 8084   │
└──────────┬───────────┘    └──────────────┬───────────────────┘
           │                               │
           └──────────────┬────────────────┘
                          │
               ┌──────────▼──────────┐
               │    共享数据层        │
               │  MySQL + Redis      │
               └─────────────────────┘
```

### 学校管理系统内部架构

```
┌─────────────────────────────────────────────────────────────┐
│                    前端容器 (Port 3002)                       │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  Nginx                                              │    │
│  │  ├── 静态文件服务 (Vue3 SPA)                         │    │
│  │  └── /api/* → 反向代理 → 后端容器                    │    │
│  └─────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
                          │ /api/*
                          ▼
┌─────────────────────────────────────────────────────────────┐
│                    后端容器 (Port 8084)                       │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  Nginx (反向代理)                                    │    │
│  │  └── PHP-FPM (Port 9000)                            │    │
│  │       └── Yii3 应用                                  │    │
│  │            ├── 中间件层 (Auth/CORS/RateLimit)        │    │
│  │            ├── 控制器层 (Controller)                 │    │
│  │            ├── 服务层 (Service)                      │    │
│  │            ├── 数据访问层 (Repository)               │    │
│  │            └── 模型层 (Model)                        │    │
│  └─────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
```

---

## 前端架构

### 技术栈

| 技术 | 版本 | 用途 |
|------|------|------|
| Vue 3 | 3.x | 前端框架，Composition API |
| TypeScript | 5.x | 类型安全 |
| Vite | 5.x | 构建工具 |
| Vue Router | 4.x | 路由管理 |
| Pinia | 2.x | 状态管理 |
| Axios | 1.x | HTTP 客户端 |
| Element Plus | 2.x | UI 组件库 |

### 目录结构

```
frontend/src/
├── api/              # API 接口封装（按模块分文件）
├── assets/           # 静态资源
├── components/       # 可复用组件
│   └── common/       # 通用组件（Header、Sidebar、VirtualList 等）
├── locales/          # 国际化语言包（zh-CN、en）
├── router/           # Vue Router 路由配置
├── stores/           # Pinia 状态管理
├── types/            # TypeScript 类型定义
├── utils/            # 工具函数（request、auth、validators）
└── views/            # 页面视图组件
    ├── schools/      # 学校管理页面
    ├── classes/      # 班级管理页面
    ├── teachers/     # 教师管理页面
    ├── students/     # 学生管理页面
    └── groups/       # 小组管理页面
```

### 状态管理

使用 Pinia 管理全局状态，主要 Store：

- `useAuthStore` — 用户认证状态（token、用户信息、登录/登出）
- `useSchoolStore` — 学校列表和当前学校
- `useClassStore` — 班级列表和当前班级
- `useUserStore` — 用户信息缓存

### 路由设计

```
/                     → 重定向到 /schools
/login                → 登录页（接收 token 参数完成 SSO）
/schools              → 学校列表
/schools/:id          → 学校详情
/classes              → 班级列表
/classes/:id          → 班级详情
/teachers             → 教师列表
/students             → 学生列表
/groups               → 小组列表
/groups/:id           → 小组详情
```

所有路由（除 `/login`）均需要认证，通过路由守卫（Navigation Guard）检查 token。

### HTTP 请求封装

`utils/request.ts` 基于 Axios 封装，提供：
- 自动附加 `Authorization: Bearer <token>` 请求头
- 统一错误处理（401 自动跳转登录、403 提示无权限）
- 请求/响应拦截器
- 超时配置（默认 30s）

---

## 后端架构

### 技术栈

| 技术 | 版本 | 用途 |
|------|------|------|
| PHP | 8.1+ | 运行环境 |
| Yii3 | 3.x | Web 框架 |
| PHP-FPM | 8.1 | FastCGI 进程管理 |
| MySQL | 8.0+ | 主数据库 |
| Redis | 6.0+ | 缓存和会话存储 |
| JWT | HS256 | 无状态认证令牌 |

### 分层架构

```
HTTP 请求
    │
    ▼
┌─────────────────────────────────┐
│         中间件层 (Middleware)    │
│  CorsMiddleware                 │  跨域处理
│  RateLimitMiddleware            │  请求限流
│  AuthMiddleware                 │  JWT 认证
│  SecurityMiddleware             │  安全防护（XSS/SQL注入）
└─────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────┐
│         控制器层 (Controller)   │
│  SchoolController               │  处理 HTTP 请求/响应
│  ClassController                │  参数提取和验证
│  TeacherController              │  调用 Service 层
│  StudentController              │
│  GroupController                │
│  AuthController                 │
│  HealthController               │
└─────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────┐
│         服务层 (Service)        │
│  SchoolService                  │  业务逻辑处理
│  ClassService                   │  事务管理
│  TeacherService                 │  权限检查
│  StudentService                 │  缓存管理
│  GroupService                   │
│  AuthService                    │
└─────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────┐
│      数据访问层 (Repository)    │
│  SchoolRepository               │  数据库查询封装
│  ClassRepository                │  软删除过滤
│  TeacherRepository              │  分页查询
│  StudentRepository              │  关联查询优化
│  GroupRepository                │
│  UserRepository                 │
└─────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────┐
│         模型层 (Model)          │
│  School / EduClass / Teacher    │  ActiveRecord 模型
│  Student / Group / User         │  字段映射
└─────────────────────────────────┘
```

### 辅助类

| 类 | 职责 |
|----|------|
| `JwtHelper` | JWT 令牌生成、验证、解析 |
| `CacheHelper` | Redis 缓存操作封装 |
| `ResponseHelper` | 统一 JSON 响应格式 |
| `ValidatorHelper` | 请求参数验证 |
| `SecurityHelper` | XSS 过滤、SQL 注入防护 |
| `Logger` | 结构化日志记录 |
| `QueryOptimizer` | 数据库查询优化工具 |

---

## 认证流程

### 跨系统单点登录（SSO）

```
用户在主系统登录
       │
       ▼
主系统生成 Session Token → 存入 Redis
       │
       ▼
用户点击"学校管理"链接
URL: http://school.example.com/login?token=<session_token>
       │
       ▼
学校管理前端接收 token 参数
       │
       ▼
POST /api/auth/verify { token: "<session_token>" }
       │
       ▼
后端从 Redis 验证 Session Token
       │
       ├── 有效 → 生成 JWT → 返回给前端
       │
       └── 无效 → 重定向到主系统登录页
       │
       ▼
前端存储 JWT（localStorage）
       │
       ▼
后续所有 API 请求携带 Authorization: Bearer <jwt>
```

### JWT 令牌结构

```json
{
  "header": { "alg": "HS256", "typ": "JWT" },
  "payload": {
    "user_id": 123,
    "username": "teacher01",
    "roles": ["teacher"],
    "exp": 1700086400,
    "iat": 1700000000
  }
}
```

### 权限控制（RBAC）

| 角色 | 权限 |
|------|------|
| admin（系统管理员）| 所有操作 |
| principal（校长）| 管理所属学校的所有数据 |
| teacher（教师）| 查看所属班级数据，管理小组 |
| student（学生）| 只读，查看所属班级和小组 |

---

## 数据流

### 创建学校数据流

```
前端表单提交
    │
    ▼
POST /api/schools
    │
    ▼
AuthMiddleware 验证 JWT
    │
    ▼
SchoolController::create()
  └── 提取并验证请求参数
    │
    ▼
SchoolService::create()
  ├── 检查用户权限（需要 admin 角色）
  ├── 开启数据库事务
  ├── SchoolRepository::create()
  ├── 提交事务
  └── 清除相关缓存
    │
    ▼
ResponseHelper::success(201, $school)
    │
    ▼
前端更新列表
```

### 缓存策略

| 数据 | 缓存键 | TTL |
|------|--------|-----|
| 学校列表 | `school:list:{page}:{name}` | 5 分钟 |
| 学校详情 | `school:detail:{id}` | 10 分钟 |
| 班级列表 | `class:list:{school_id}:{page}` | 5 分钟 |
| 用户信息 | `user:info:{id}` | 30 分钟 |
| JWT 黑名单 | `jwt:blacklist:{jti}` | 令牌剩余有效期 |

写操作后自动清除相关缓存键，保证数据一致性。

---

## 安全设计

- **认证**: 所有 API 端点（除健康检查）均需要有效 JWT
- **XSS 防护**: 所有用户输入经过 `SecurityHelper::sanitize()` 处理
- **SQL 注入防护**: 使用参数化查询（PDO prepared statements）
- **请求限流**: `RateLimitMiddleware` 限制每 IP 每分钟最多 60 次请求
- **CORS**: 只允许配置的来源域名跨域访问
- **HTTPS**: 生产环境强制使用 HTTPS（通过 Nginx 配置）
- **日志**: 所有安全事件（认证失败、权限拒绝）记录到日志

---

## 性能设计

- **Redis 缓存**: 热点数据缓存，减少数据库查询
- **分页查询**: 所有列表接口强制分页，每页最多 100 条
- **数据库索引**: 关键查询字段均建立索引
- **避免 N+1**: Repository 层使用 JOIN 查询关联数据
- **虚拟滚动**: 前端大列表使用 `VirtualList` 组件
- **图片懒加载**: 前端图片使用懒加载
- **Gzip 压缩**: Nginx 开启 gzip 压缩响应体
