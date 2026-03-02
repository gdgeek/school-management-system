# 生产环境部署清单

## 一、部署前检查

### 环境变量与密钥

- [ ] 复制 `.env.production` 为 `.env`，填写所有实际值
- [ ] `DB_PASSWORD` 已设置为强密码
- [ ] `JWT_SECRET` 已使用 `openssl rand -hex 32` 生成
- [ ] `REDIS_PASSWORD` 已配置（如 Redis 启用了认证）
- [ ] `CORS_ALLOWED_ORIGINS` 已设置为实际域名
- [ ] `VITE_API_BASE_URL` 指向正确的后端 API 地址
- [ ] `APP_DEBUG=false` 已确认
- [ ] `.env` 文件不在版本控制中（已在 `.gitignore`）

### DNS 与 SSL

- [ ] 前端域名 DNS 已解析到服务器 IP
- [ ] 后端 API 域名 DNS 已解析到服务器 IP
- [ ] SSL 证书已申请并安装（Let's Encrypt 或其他 CA）
- [ ] HTTPS 重定向已配置
- [ ] 证书自动续期已设置（`certbot renew`）

### 基础设施

- [ ] Docker 20.10+ 和 Docker Compose 2.0+ 已安装
- [ ] MySQL 8.0+ 可从服务器访问
- [ ] Redis 6.0+ 可从服务器访问
- [ ] 服务器防火墙仅开放 80/443 端口
- [ ] 磁盘空间充足（建议 ≥10GB 可用）

### 数据库

- [ ] 数据库表结构已验证（`php migrations/verify_tables.php`）
- [ ] 数据库用户权限已按最小权限原则配置
- [ ] 数据库备份已执行

---

## 二、部署步骤

### 1. 拉取代码

```bash
git clone git@github.com:gdgeek/school-management-system.git
cd school-management-system
git checkout main  # 或指定的 release tag
```

### 2. 配置环境

```bash
cp .env.production .env
# 编辑 .env，填写实际的数据库、Redis、JWT 等配置
```

### 3. 构建并启动

```bash
docker compose -f docker-compose.production.yml build --no-cache
docker compose -f docker-compose.production.yml up -d
```

### 4. 验证启动

```bash
# 检查容器状态（全部应为 healthy）
docker compose -f docker-compose.production.yml ps

# 健康检查
curl -s http://localhost:8084/health

# 版本信息
curl -s http://localhost:8084/version
```

---

## 三、部署后验证

### 服务可用性

- [ ] `curl http://localhost:8084/health` 返回 `{"status":"ok"}`
- [ ] 前端页面 http://localhost:3002 可正常加载
- [ ] API 端点 http://localhost:8084/api/schools 可正常响应
- [ ] 所有容器状态为 `healthy`

### 功能验证

- [ ] 从主系统跳转到学校管理系统，认证 token 传递正常
- [ ] 学校 CRUD 操作正常
- [ ] 班级、教师、学生管理功能正常
- [ ] 小组管理功能正常
- [ ] 权限控制生效（非管理员无法执行管理操作）

### 安全验证

- [ ] HTTPS 访问正常，HTTP 自动重定向
- [ ] CORS 仅允许配置的域名
- [ ] 无认证的 API 请求返回 401
- [ ] `APP_DEBUG=false` 确认（错误页面不暴露堆栈信息）
- [ ] 请求频率限制生效

---

## 四、回滚流程

如果部署后发现严重问题，按以下步骤回滚：

### 1. 停止当前服务

```bash
docker compose -f docker-compose.production.yml down
```

### 2. 切换到上一个稳定版本

```bash
git checkout <previous-stable-tag>
```

### 3. 重新构建并启动

```bash
docker compose -f docker-compose.production.yml build --no-cache
docker compose -f docker-compose.production.yml up -d
```

### 4. 数据库回滚（如有迁移变更）

```bash
# 从备份恢复
mysql -h <DB_HOST> -u <DB_USER> -p <DB_NAME> < backup_YYYYMMDD.sql
```

---

## 五、监控设置

### 日志查看

```bash
# 实时日志
docker compose -f docker-compose.production.yml logs -f

# 单个服务日志
docker compose -f docker-compose.production.yml logs -f backend
docker compose -f docker-compose.production.yml logs -f nginx

# 应用日志
docker compose -f docker-compose.production.yml exec backend ls runtime/logs/
```

### 健康检查监控

建议配置定时任务监控健康端点：

```bash
# crontab -e
*/5 * * * * curl -sf http://localhost:8084/health || echo "School Management System DOWN" | mail -s "Alert" admin@example.com
```

### 资源监控

```bash
# 容器资源使用
docker stats --no-stream

# 磁盘使用
docker system df
```

### 数据库备份（定时）

```bash
# crontab -e
0 2 * * * mysqldump -h <DB_HOST> -u <DB_USER> -p<DB_PASSWORD> xrugc \
  edu_school edu_class edu_teacher edu_student \
  group group_user class_group \
  > /backups/school_$(date +\%Y\%m\%d).sql
```
