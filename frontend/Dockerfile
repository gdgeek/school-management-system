# ============================================================
# 学校管理系统 - 前端独立部署镜像
# 支持运行时通过环境变量配置 API 地址
# 构建: docker build -t school-mgmt-frontend .
# 运行: docker run -p 3002:80 -e API_BASE_URL=http://api.example.com school-mgmt-frontend
# ============================================================

# --- Stage 1: 构建 ---
FROM node:20-alpine AS builder

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY . .
RUN npm run build

# --- Stage 2: 生产镜像 ---
FROM nginx:alpine

# 复制构建产物
COPY --from=builder /app/dist /usr/share/nginx/html

# 复制 nginx 配置模板
COPY docker/nginx.conf.template /etc/nginx/templates/default.conf.template

# 复制启动脚本（运行时替换 API 地址）
COPY docker/entrypoint.sh /docker-entrypoint.d/40-env-config.sh
RUN chmod +x /docker-entrypoint.d/40-env-config.sh

# 默认环境变量
ENV API_BASE_URL=http://localhost:8084
ENV MAIN_SYSTEM_URL=http://localhost:8080

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --retries=3 --start-period=10s \
    CMD wget --quiet --tries=1 --spider http://127.0.0.1/ || exit 1
