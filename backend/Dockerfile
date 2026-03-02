# ============================================================
# 学校管理系统 - 后端独立部署镜像
# 内置 PHP-FPM + Nginx，可单独运行
# 构建: docker build -t school-mgmt-backend .
# 运行: docker run -p 8084:80 --env-file .env school-mgmt-backend
# ============================================================

# --- Stage 1: Composer 依赖安装 ---
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --ignore-platform-reqs

# --- Stage 2: 生产镜像 (PHP-FPM + Nginx) ---
FROM php:8.1-fpm-alpine

# 安装系统依赖和 PHP 扩展
RUN apk add --no-cache \
        nginx \
        supervisor \
        curl \
        libpng-dev \
        libzip-dev \
        zip \
        unzip \
    && docker-php-ext-install pdo pdo_mysql zip opcache \
    && apk add --no-cache pcre-dev $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del pcre-dev $PHPIZE_DEPS

# PHP 生产优化
RUN { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.enable_cli=0'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# PHP-FPM 配置优化
RUN { \
    echo '[www]'; \
    echo 'pm = dynamic'; \
    echo 'pm.max_children = 20'; \
    echo 'pm.start_servers = 4'; \
    echo 'pm.min_spare_servers = 2'; \
    echo 'pm.max_spare_servers = 6'; \
    echo 'pm.max_requests = 500'; \
    } > /usr/local/etc/php-fpm.d/zz-production.conf

WORKDIR /var/www/html

# 复制应用代码
COPY . .

# 复制 Composer 依赖
COPY --from=vendor /app/vendor ./vendor

# Nginx 配置 — PHP-FPM 走 127.0.0.1
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Supervisor 配置 — 同时管理 nginx + php-fpm
COPY docker/supervisord.conf /etc/supervisord.conf

# 创建运行时目录
RUN mkdir -p /var/www/html/runtime/logs \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /run/nginx

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --retries=3 --start-period=20s \
    CMD wget --quiet --tries=1 --spider http://127.0.0.1/health || exit 1

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
