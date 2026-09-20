#!/usr/bin/env bash
# Web / 通用入口：根据 APP_ROLE 准备目录权限后 exec 主进程
set -euo pipefail

mkdir -p /var/www/html/runtime/{cache,session,log,api/log,admin/log,admin/temp} \
         /var/www/html/public/uploads \
         /var/www/html/public/tmp
chown -R www-data:www-data /var/www/html/runtime /var/www/html/public/uploads /var/www/html/public/tmp 2>/dev/null || true

# H5 publicPath=/ ：保证 /static 在无 nginx alias 时也能落到 h5/static（保留 admin）
if [[ -d /var/www/html/public/h5/static ]]; then
  mkdir -p /var/www/html/public/static
  if [[ ! -e /var/www/html/public/static/js ]]; then
    ln -sfn ../h5/static/js /var/www/html/public/static/js 2>/dev/null || true
  fi
  if [[ ! -e /var/www/html/public/static/index.css && -f /var/www/html/public/h5/static/index.css ]]; then
    ln -sfn ../h5/static/index.css /var/www/html/public/static/index.css 2>/dev/null || true
  fi
  if [[ ! -e /var/www/html/public/static/images && -d /var/www/html/public/h5/static/images ]]; then
    ln -sfn ../h5/static/images /var/www/html/public/static/images 2>/dev/null || true
  fi
fi

# 合部署 / IP 访问：把写死外域的 API 基址改成同域（空串）
if [[ -d /var/www/html/public/h5/static/js ]]; then
  python3 - <<'PY' 2>/dev/null || true
import re
from pathlib import Path
root = Path("/var/www/html/public/h5/static/js")
ext_re = re.compile(
    r'(API_BASE_DIRECT|baseUrl)\s*[:=]\s*["\']https?://[^"\']+["\']'
)
empty_api = r'\1:""'
empty_base = r'\1:""'
for p in root.glob("index.*.js"):
    text = p.read_text(encoding="utf-8", errors="ignore")
    new = re.sub(
        r'API_BASE_DIRECT\s*[:=]\s*["\']https?://[^"\']+["\']',
        'API_BASE_DIRECT:""',
        text,
    )
    new = re.sub(
        r'baseUrl\s*:\s*["\']https?://[^"\']+["\']',
        'baseUrl:""',
        new,
    )
    if new != text:
        p.write_text(new, encoding="utf-8")
PY
fi

# 确保 PHP(www-data) 可读 .env
if [[ -f /var/www/html/.env ]]; then
  chown www-data:www-data /var/www/html/.env 2>/dev/null || true
  chmod 640 /var/www/html/.env 2>/dev/null || true
fi

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
