# 部署文档

## 前置条件

### Docker 部署（推荐）

| 软件 | 最低版本 | 说明 |
|------|----------|------|
| Docker | 20.10+ | 容器运行时 |
| Docker Compose | 2.0+ | 容器编排 |
| MySQL | 8.0+ | 共享数据库（宿主机或外部服务） |
| Redis | 6.0+ | 共享缓存（宿主机或外部服务） |

### 手动部署

| 软件 | 最低版本 | 说明 |
|------|----------|------|
| Node.js | 18.0+ | 前端构建 |
| PHP | 8.1+ | 后端运行时 |
| Composer | 2.0+ | PHP 依赖管理 |
| Nginx | 1.20+ | Web 服务器 |
| MySQL | 8.0+ | 数据库 |
| Redis | 6.0+ | 缓存 |

---

## 环境变量配置

复制 `.env.example` 为 `.env` 并修改配置：

```bash
cp .env.example .env
```

`.env` 文件说明：

```dotenv
# 数据库配置（连接主系统共享的 MySQL）
DB_HOST=localhost          # 数据库主机
DB_NAME=xrugc             # 数据库名称
DB_USER=root              # 数据库用户名
DB_PASSWORD=your_password # 数据库密码

# Redis 配置（连接主系统共享的 Redis）
REDIS_HOST=localhost       # Redis 主机
REDIS_PORT=6379           # Redis 端口

# JWT 配置
JWT_SECRET=your-secret-key-change-this-in-production  # 必须修改！

# 应用配置
APP_ENV=production         # 环境：development / production
APP_DEBUG=false            # 调试模式（生产环境必须为 false）
```

> **安全提示**: 生产环境必须修改 `JWT_SECRET` 为随机强密钥，可使用 `openssl rand -hex 32` 生成。

---

## Docker 部署

### 1. 克隆代码

```bash
git clone <repository-url>
cd school-management-system
```

### 2. 配置环境变量

```bash
cp .env.example .env
# 编辑 .env 文件，填写正确的数据库和 Redis 连接信息
```

### 3. 启动服务

```bash
docker-compose up -d
```

### 4. 验证部署

```bash
# 检查容器状态
docker-compose ps

# 检查健康状态
curl http://localhost:8084/health

# 查看日志
docker-compose logs -f backend
```

### 5. 访问系统

- 前端应用: http://localhost:3002
- 后端 API: http://localhost:8084/api
- 健康检查: http://localhost:8084/health

### 常用 Docker 命令

```bash
# 停止服务
docker-compose down

# 重启服务
docker-compose restart

# 重新构建并启动
docker-compose up -d --build

# 查看实时日志
docker-compose logs -f

# 进入后端容器
docker-compose exec backend sh

# 进入前端容器
docker-compose exec frontend sh
```

---

## 手动部署

### 前端部署

```bash
cd frontend

# 安装依赖
npm install

# 构建生产版本
npm run build

# 构建产物在 dist/ 目录
ls dist/
```

将 `dist/` 目录内容部署到 Nginx 静态文件目录。

### 后端部署

```bash
cd backend

# 安装 PHP 依赖
composer install --no-dev --optimize-autoloader

# 配置环境变量
cp .env.example .env
# 编辑 .env 填写配置

# 设置目录权限
chmod -R 755 .
chmod -R 777 runtime/
```

---

## Nginx 配置（生产环境）

### 前端 Nginx 配置

```nginx
server {
    listen 80;
    server_name school.example.com;
    root /var/www/school-frontend/dist;
    index index.html;

    # 重定向 HTTP 到 HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name school.example.com;
    root /var/www/school-frontend/dist;
    index index.html;

    # SSL 证书
    ssl_certificate /etc/ssl/certs/school.example.com.crt;
    ssl_certificate_key /etc/ssl/private/school.example.com.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Gzip 压缩
    gzip on;
    gzip_types text/plain text/css application/json application/javascript;

    # SPA 路由
    location / {
        try_files $uri $uri/ /index.html;
    }

    # API 反向代理
    location /api {
        proxy_pass http://127.0.0.1:8084;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # 静态资源缓存
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### 后端 Nginx 配置

```nginx
server {
    listen 8084;
    server_name localhost;
    root /var/www/school-backend/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }
}
```

---

## SSL/HTTPS 配置

### 使用 Let's Encrypt（免费证书）

```bash
# 安装 Certbot
apt-get install certbot python3-certbot-nginx

# 申请证书
certbot --nginx -d school.example.com

# 自动续期（添加到 crontab）
0 12 * * * /usr/bin/certbot renew --quiet
```

---

## 监控和健康检查

### 健康检查端点

| 端点 | 说明 |
|------|------|
| `GET /health` | 基础健康检查，返回 `{"status":"ok"}` |
| `GET /health/detailed` | 详细检查，包含数据库和 Redis 状态 |
| `GET /version` | 版本信息 |

### 使用 Docker 健康检查

`docker-compose.yml` 已配置健康检查，可通过以下命令查看：

```bash
docker-compose ps
# 状态列显示 (healthy) 或 (unhealthy)
```

### 日志查看

```bash
# Docker 日志
docker-compose logs -f backend
docker-compose logs -f nginx
docker-compose logs -f frontend

# 应用日志（挂载到 backend_logs 卷）
docker-compose exec backend ls runtime/logs/
```

---

## 数据库迁移

首次部署时，确认数据库表已存在（学校管理系统使用主系统的现有表结构）：

```bash
# 进入后端容器验证表结构
docker-compose exec backend php migrations/verify_tables.php
```

---

## 备份和恢复

### 数据库备份

```bash
# 备份（在宿主机执行）
mysqldump -h localhost -u root -p xrugc \
  edu_school edu_class edu_teacher edu_student \
  group group_user class_group \
  > backup_$(date +%Y%m%d).sql
```

### 数据库恢复

```bash
mysql -h localhost -u root -p xrugc < backup_20240101.sql
```

---

## 常见问题

**Q: 容器启动后无法连接数据库**

检查 `.env` 中的 `DB_HOST`。Docker 容器内访问宿主机服务需使用 `host.docker.internal`（已在 `docker-compose.yml` 中配置 `extra_hosts`）。

**Q: JWT 验证失败**

确认 `JWT_SECRET` 与主系统使用的密钥一致（如果需要验证主系统的 token）。

**Q: 前端无法访问 API**

检查 Nginx 反向代理配置，确认 `/api` 路径正确代理到后端服务。

**Q: Redis 连接失败**

确认 Redis 服务正在运行，且 `REDIS_HOST` 和 `REDIS_PORT` 配置正确。
