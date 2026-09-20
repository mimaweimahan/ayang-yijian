# 基于 PHP 7.4-FPM + Nginx（兼容本项目 PHP 7.3+ 语法；生产可改为 7.3 镜像）
FROM php:7.4-fpm-bullseye

ENV DEBIAN_FRONTEND=noninteractive \
    TZ=Asia/Shanghai

# 系统依赖 + Nginx + Supervisor + curl
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        curl \
        cron \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        unzip \
        git \
        ca-certificates \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mysqli \
        bcmath \
        gd \
        zip \
        opcache \
        mbstring \
    && rm -rf /var/lib/apt/lists/*

# supercronic：容器友好的 crontab 执行器
ARG SUPERCRONIC_URL=https://github.com/aptible/supercronic/releases/download/v0.2.33/supercronic-linux-amd64
RUN curl -fsSL -o /usr/local/bin/supercronic "$SUPERCRONIC_URL" \
    && chmod +x /usr/local/bin/supercronic

# Nginx / PHP / Supervisor 配置
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-tenant.ini
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/supervisor/supervisord.worker.conf /etc/supervisor/supervisord.worker.conf
COPY docker/supervisor/app.conf /etc/supervisor/conf.d/app.conf
COPY docker/supervisor/worker.conf /etc/supervisor/conf.d/worker.conf
COPY docker/scripts/entrypoint-app.sh /usr/local/bin/entrypoint-app.sh
COPY docker/scripts/worker-loop.sh /usr/local/bin/worker-loop.sh
RUN chmod +x /usr/local/bin/entrypoint-app.sh /usr/local/bin/worker-loop.sh \
    && ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default \
    && rm -f /etc/nginx/sites-enabled/default.bak \
    && mkdir -p /var/www/html /var/log/supervisor \
    && chown -R www-data:www-data /var/www/html

WORKDIR /var/www/html

# 默认以 Web 角色启动（php-fpm + nginx）；worker/cron 在 compose 中覆盖 command
EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint-app.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
