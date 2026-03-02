# 学校管理系统

从 XR UGC 主系统分离的独立学校管理服务，用于管理学校、班级、教师、学生和学习小组。

## 功能特性

- **学校管理** — 创建、编辑、删除学校，管理校长信息
- **班级管理** — 在学校下创建班级，查看班级教师和学生
- **教师管理** — 将用户分配为班级教师
- **学生管理** — 将用户分配为班级学生
- **小组管理** — 创建学习小组，管理成员和关联班级
- **单点登录** — 与主系统共享会话，无需重复登录
- **权限控制** — 基于角色的访问控制（管理员/校长/教师/学生）

## 技术栈

| 层次 | 技术 |
|------|------|
| 前端框架 | Vue 3 + TypeScript + Vite |
| 前端状态 | Pinia |
| 前端路由 | Vue Router 4 |
| UI 组件库 | Element Plus |
| 后端框架 | Yii3 + PHP 8.1 |
| 数据库 | MySQL 8.0（与主系统共享）|
| 缓存 | Redis 6.0（与主系统共享）|
| 认证 | JWT + Redis 会话 |
| 容器化 | Docker + Docker Compose |

## 快速开始

### Docker 部署（推荐）

```bash
# 1. 克隆代码
git clone <repository-url>
cd school-management-system

# 2. 配置环境变量
cp .env.example .env
# 编辑 .env，填写数据库和 Redis 连接信息

# 3. 启动服务
docker-compose up -d

# 4. 访问系统
# 前端: http://localhost:3002
# API:  http://localhost:8084/api
```

### 本地开发

```bash
# 前端
cd frontend
npm install
npm run dev        # http://localhost:5173

# 后端
cd backend
composer install
php -S localhost:8083 -t public/
```

## 项目结构

```
school-management-system/
├── frontend/              # Vue3 + TypeScript 前端应用
│   ├── src/
│   │   ├── api/           # API 接口封装
│   │   ├── components/    # 可复用组件
│   │   ├── stores/        # Pinia 状态管理
│   │   ├── views/         # 页面视图
│   │   └── utils/         # 工具函数
│   └── Dockerfile
├── backend/               # Yii3 + PHP 后端 API
│   ├── src/
│   │   ├── Controller/    # 控制器层
│   │   ├── Service/       # 业务逻辑层
│   │   ├── Repository/    # 数据访问层
│   │   ├── Model/         # 数据模型
│   │   ├── Middleware/    # 中间件
│   │   └── Helper/        # 辅助类
│   ├── migrations/        # 数据库迁移脚本
│   └── Dockerfile
├── docs/                  # 项目文档
│   ├── api.md             # API 文档
│   ├── architecture.md    # 架构设计文档
│   ├── database.md        # 数据库设计文档
│   ├── deployment.md      # 部署文档
│   ├── development.md     # 开发指南
│   └── user-manual.md     # 用户手册
├── docker-compose.yml     # Docker 编排配置
├── .env.example           # 环境变量示例
└── README.md
```

## 架构概览

```
用户浏览器
    │
    ├── 主系统 (Vue2 + Yii2)
    │       │ 单点登录跳转
    │       ▼
    └── 学校管理系统
            ├── 前端 (Vue3, Port 3002)
            │       └── Nginx 静态服务 + API 代理
            └── 后端 (Yii3, Port 8084)
                    └── PHP-FPM + Nginx
                            │
                    ┌───────┴───────┐
                    │               │
                  MySQL           Redis
               (共享数据库)      (共享缓存)
```

## 文档

| 文档 | 说明 |
|------|------|
| [API 文档](docs/api.md) | 所有 API 端点的请求/响应说明 |
| [数据库设计](docs/database.md) | 表结构、字段说明和 ER 图 |
| [架构设计](docs/architecture.md) | 系统架构、技术选型和数据流 |
| [部署文档](docs/deployment.md) | Docker 和手动部署步骤 |
| [开发指南](docs/development.md) | 开发环境搭建和编码规范 |
| [用户手册](docs/user-manual.md) | 功能使用说明 |

## 环境要求

- Docker 20.10+ 和 Docker Compose 2.0+（Docker 部署）
- 或 Node.js 18+、PHP 8.1+、Composer 2.0+（手动部署）
- MySQL 8.0+（共享主系统数据库）
- Redis 6.0+（共享主系统 Redis）

## 贡献指南

1. Fork 本仓库
2. 从 `develop` 分支创建功能分支：`git checkout -b feature/your-feature`
3. 提交代码，遵循 [Conventional Commits](https://www.conventionalcommits.org/) 规范
4. 推送分支并创建 Pull Request，目标分支为 `develop`
5. 等待代码审查通过后合并

详细开发规范请参考[开发指南](docs/development.md)。

## 许可证

[MIT License](../LICENSE)
