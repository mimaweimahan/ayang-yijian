#!/usr/bin/env bash
# Web / 通用入口：根据 APP_ROLE 准备目录权限后 exec 主进程
set -euo pipefail

mkdir -p /var/www/html/runtime/{cache,session,log,api/log,admin/log,admin/temp} \
         /var/www/html/public/uploads \
         /var/www/html/public/tmp
chown -R www-data:www-data /var/www/html/runtime /var/www/html/public/uploads /var/www/html/public/tmp 2>/dev/null || true

# 若编排注入了 DB_* 且尚无 .env，可在此生成（可选增强；默认依赖挂载的租户 .env）
if [[ ! -f /var/www/html/.env && -n "${DB_NAME:-}" ]]; then
  cat > /var/www/html/.env <<EOF
APP_DEBUG = false

[APP]
DEFAULT_TIMEZONE = ${TZ:-Asia/Shanghai}
HOST =
FRONTEND_INVITE_BASE = ${APP_URL:-}

[DATABASE]
TYPE = mysql
HOSTNAME = ${DB_HOST:-127.0.0.1}
DATABASE = ${DB_NAME}
USERNAME = ${DB_USER:-root}
PASSWORD = ${DB_PASS:-}
HOSTPORT = ${DB_PORT:-3306}
CHARSET = utf8
DEBUG = false
prefix = ${DB_PREFIX:-mod_}

[LANG]
default_lang = zh-cn

[system]
stop = false

[USER]
REG_EMAIL_REQUIRED = false
CAN_MODIFY_BIND_ADDRESS = true
CHECK_USER_ADDRESS = true
PRODUCT_TYPE = 2
ADMIN_GOOGLE_AUTH = false
WITHDRAWAL_DISABLE_MSG = Your withdrawal has been rejected by the system. Please contact online customer service for advice.
EOF
  chown www-data:www-data /var/www/html/.env
  chmod 640 /var/www/html/.env
fi

echo "[entrypoint] TENANT_ID=${TENANT_ID:-?} APP_ROLE=${APP_ROLE:-web}"
exec "$@"
