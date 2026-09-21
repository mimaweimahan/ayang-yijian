# 基于 PHP 7.4-FPM + Nginx（兼容本项目 PHP 7.3+ 语法）
# bullseye 已 EOL：只用 archive.debian.org/main（勿用 deb.debian.org / debian-security）
FROM php:7.4-fpm-bullseye

ENV DEBIAN_FRONTEND=noninteractive \
    TZ=Asia/Shanghai

RUN set -eux; \
    printf '%s\n' \
      'deb http://archive.debian.org/debian bullseye main contrib non-free' \
      'deb http://archive.debian.org/debian bullseye-updates main contrib non-free' \
      > /etc/apt/sources.list; \
    rm -f /etc/apt/sources.list.d/*; \
    apt-get -o Acquire::Check-Valid-Until=false update; \
    apt-get -o Acquire::Check-Valid-Until=false install -y --no-install-recommends \
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

ARG SUPERCRONIC_URL=https://github.com/aptible/supercronic/releases/download/v0.2.33/supercronic-linux-amd64
RUN curl -fsSL -o /usr/local/bin/supercronic "$SUPERCRONIC_URL" \
    && chmod +x /usr/local/bin/supercronic

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

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint-app.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
