#!/usr/bin/env bash
# =============================================================================
# oneclick_tenant.sh — 纯 Docker 租户一键开通（不依赖宝塔）
#
# 每租户独立: mysql + app + worker + cron
#
# 用法:
#   ./oneclick_tenant.sh <租户前缀> <超管密码> [宿主机HTTP端口]
#   ./oneclick_tenant.sh agent01 'Pass888!'
#   ./oneclick_tenant.sh agent01 'Pass888!' 18001
#
# 超管密码算法: 纯 md5($pass)，无盐（见 app/common/model/Admin.php）
# =============================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

if [[ -t 1 ]]; then
  C_RED=$'\033[1;31m'; C_GRN=$'\033[1;32m'; C_YLW=$'\033[1;33m'
  C_CYN=$'\033[1;36m'; C_BLD=$'\033[1m'; C_RST=$'\033[0m'
else
  C_RED=; C_GRN=; C_YLW=; C_CYN=; C_BLD=; C_RST=
fi
log()  { printf '%s[oneclick]%s %s\n' "$C_CYN" "$C_RST" "$*"; }
ok()   { printf '%s[ok]%s %s\n' "$C_GRN" "$C_RST" "$*"; }
warn() { printf '%s[warn]%s %s\n' "$C_YLW" "$C_RST" "$*"; }
die()  { printf '%s[error]%s %s\n' "$C_RED" "$C_RST" "$*" >&2; exit 1; }

TENANT="${1:-}"
ADMIN_PASS_PLAIN="${2:-}"
PORT_ARG="${3:-}"

[[ -n "$TENANT" && -n "$ADMIN_PASS_PLAIN" ]] || die "用法: $0 <租户前缀> <超管密码> [端口]
示例: $0 agent01 'Pass888!'"

[[ "$TENANT" =~ ^[a-zA-Z0-9][a-zA-Z0-9_-]{0,31}$ ]] || die "租户前缀非法: $TENANT"

# ---------- 配置（纯 Docker，无宝塔项）----------
BASE_DOMAIN="${BASE_DOMAIN:-example.com}"
TENANTS_ROOT="${TENANTS_ROOT:-/var/www/tenants}"
INSTANCES_ROOT="${INSTANCES_ROOT:-$ROOT/instances}"
DB_NAME_PREFIX="${DB_NAME_PREFIX:-db_}"
DB_TABLE_PREFIX="${DB_TABLE_PREFIX:-mod_}"
IMAGE_TAG="${IMAGE_TAG:-tenant-app:php74}"
MYSQL_IMAGE_NOTE="mysql:5.7"
WORKER_SLEEP="${WORKER_SLEEP:-30}"
TZ_NAME="${TZ_NAME:-Asia/Shanghai}"
ADMIN_ENTRY="${ADMIN_ENTRY:-ayangshuadan222.php}"
PORT_BASE="${PORT_BASE:-18001}"
PORT_MAX="${PORT_MAX:-18999}"
SKIP_DOCKER_BUILD="${SKIP_DOCKER_BUILD:-0}"
REQUIRE_REAL_DOMAIN="${REQUIRE_REAL_DOMAIN:-0}"
# 访问模式: auto=占位域名则走公网IP:端口；ip=强制IP；domain=强制租户.域名
ACCESS_MODE="${ACCESS_MODE:-auto}"
PUBLIC_IP="${PUBLIC_IP:-}"
# 对外 URL 协议（入口反代自行终结 TLS 时用 https）
APP_SCHEME="${APP_SCHEME:-http}"

for f in "$ROOT/deploy/oneclick_tenant.conf" "$ROOT/oneclick_tenant.conf" /etc/oneclick_tenant.conf; do
  [[ -f "$f" ]] || continue
  # shellcheck disable=SC1090
  source "$f"
  log "已加载: $f"
done

require() { command -v "$1" >/dev/null 2>&1 || die "缺少命令: $1 — $2"; }
require docker "请先在本机执行: bash install_vps.sh"
require python3 ""
require rsync ""

if docker compose version >/dev/null 2>&1; then
  DC=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
  DC=(docker-compose)
else
  die "需要 docker compose 插件"
fi

[[ -f "$ROOT/base_template.sql" ]] || die "缺少 base_template.sql，先执行: python3 clean_db.py"
[[ -f "$ROOT/docker-compose.template.yml" ]] || die "缺少 docker-compose.template.yml"
[[ -f "$ROOT/docker/Dockerfile" ]] || die "缺少 docker/Dockerfile"
[[ -f "$ROOT/.env.example" ]] || die "缺少 .env.example"

if [[ "$REQUIRE_REAL_DOMAIN" == "1" ]]; then
  if [[ "$BASE_DOMAIN" == "example.com" || "$BASE_DOMAIN" == "yourdomain.com" ]]; then
    die "请先在 deploy/oneclick_tenant.conf 设置 BASE_DOMAIN（并配置 DNS *.域名）"
  fi
fi

DB_NAME="${DB_NAME_PREFIX}${TENANT}"
DB_USER="$DB_NAME"
CODE_DIR="${TENANTS_ROOT}/${TENANT}"
INST_DIR="${INSTANCES_ROOT}/${TENANT}"
COMPOSE_FILE="${INST_DIR}/docker-compose.yml"
PORT_REGISTRY="${INSTANCES_ROOT}/.ports_allocated"
SQL_FILE="$ROOT/base_template.sql"

[[ ! -e "$CODE_DIR" ]] || die "已存在: $CODE_DIR （重装请先删目录与 docker 资源）"
[[ ! -e "$INST_DIR" ]] || die "已存在: $INST_DIR"

# ---------- 端口分配 ----------
port_in_use() {
  local p="$1"
  ss -lnt 2>/dev/null | awk '{print $4}' | grep -qE ":${p}$" && return 0
  docker ps --format '{{.Ports}}' 2>/dev/null | grep -qE ":${p}->" && return 0
  [[ -f "$PORT_REGISTRY" ]] && grep -qx "$p" "$PORT_REGISTRY" && return 0
  grep -Rsl "^PORT=${p}$" "$INSTANCES_ROOT"/*/env.compose 2>/dev/null | grep -q . && return 0
  return 1
}
alloc_port() {
  local p="$PORT_BASE"
  while port_in_use "$p"; do
    p=$((p + 1))
    [[ $p -le $PORT_MAX ]] || die "端口池耗尽 ${PORT_BASE}-${PORT_MAX}"
  done
  echo "$p"
}
if [[ -n "$PORT_ARG" ]]; then
  PORT="$PORT_ARG"
  port_in_use "$PORT" && die "端口 $PORT 已被占用"
else
  PORT="$(alloc_port)"
fi

# ---------- 对外访问地址（域名可后配：占位域名 → 公网 IP:端口）----------
detect_public_ip() {
  local ip=""
  ip="$(curl -fsS --max-time 5 ifconfig.me 2>/dev/null || true)"
  [[ -z "$ip" ]] && ip="$(curl -fsS --max-time 5 icanhazip.com 2>/dev/null || true)"
  [[ -z "$ip" ]] && ip="$(curl -fsS --max-time 5 ip.sb 2>/dev/null || true)"
  echo "$ip" | tr -d '[:space:]'
}
_use_ip=0
case "$ACCESS_MODE" in
  ip) _use_ip=1 ;;
  domain) _use_ip=0 ;;
  auto|*)
    if [[ "$BASE_DOMAIN" == "example.com" || "$BASE_DOMAIN" == "yourdomain.com" || -z "$BASE_DOMAIN" ]]; then
      _use_ip=1
    fi
    ;;
esac
if [[ "$_use_ip" -eq 1 ]]; then
  [[ -n "$PUBLIC_IP" ]] || PUBLIC_IP="$(detect_public_ip)"
  [[ -n "$PUBLIC_IP" ]] || die "无法探测公网 IP，请手动: PUBLIC_IP=x.x.x.x ACCESS_MODE=ip $0 ..."
  DOMAIN="${PUBLIC_IP}"
  APP_URL="${APP_SCHEME}://${PUBLIC_IP}:${PORT}"
  log "访问模式=IP（域名后配）| APP_URL=$APP_URL"
else
  DOMAIN="${TENANT}.${BASE_DOMAIN}"
  APP_URL="${APP_SCHEME}://${DOMAIN}"
  log "访问模式=域名 | APP_URL=$APP_URL （请确保反代到 :$PORT）"
fi

DB_PASS="$(python3 -c 'import secrets,string;print("".join(secrets.choice(string.ascii_letters+string.digits)for _ in range(20)))')"
MYSQL_ROOT_PASSWORD="$(python3 -c 'import secrets,string;print("".join(secrets.choice(string.ascii_letters+string.digits)for _ in range(24)))')"
# 与 Admin.php 一致：纯 md5(明文)，无盐（用 python，宿主无需装 PHP）
ADMIN_HASH="$(ADMIN_PASS_PLAIN="$ADMIN_PASS_PLAIN" python3 -c 'import hashlib,os;print(hashlib.md5(os.environ["ADMIN_PASS_PLAIN"].encode()).hexdigest())')"

log "纯 Docker 开通 | 租户=$TENANT | 访问=$APP_URL | 端口=$PORT | 库容器=mysql_${TENANT}"

# ---------- 1) 代码 ----------
log "[1/5] 复制母模 => $CODE_DIR"
mkdir -p "$TENANTS_ROOT" "$INST_DIR"
rsync -a \
  --exclude '.git/' --exclude '.env' --exclude 'runtime/' --exclude 'instances/' \
  --exclude 'xinayang_*.sql*' --exclude 'base_template.sql' --exclude '*.tar.gz' \
  --exclude 'database/backup/' \
  "$ROOT/" "$CODE_DIR/"
mkdir -p "$CODE_DIR/runtime/"{cache,session,api/log,admin/log,admin/temp} \
         "$CODE_DIR/public/uploads" "$CODE_DIR/public/tmp"

# .env：DB_HOST 固定 mysql（compose 服务名）
python3 - "$ROOT/.env.example" "$CODE_DIR/.env" <<PY
import sys
from pathlib import Path
text = Path(sys.argv[1]).read_text(encoding="utf-8")
repl = {
    "{{APP_DEBUG}}": "false",
    "{{APP_TIMEZONE}}": "${TZ_NAME}",
    "{{APP_HOST}}": "",
    "{{FRONTEND_INVITE_BASE}}": "${APP_URL}",
    "{{DB_TYPE}}": "mysql",
    "{{DB_HOST}}": "mysql",
    "{{DB_NAME}}": "${DB_NAME}",
    "{{DB_USER}}": "${DB_USER}",
    "{{DB_PASS}}": "${DB_PASS}",
    "{{DB_PORT}}": "3306",
    "{{DB_CHARSET}}": "utf8",
    "{{DB_PREFIX}}": "${DB_TABLE_PREFIX}",
    "{{CACHE_DRIVER}}": "file",
    "{{REDIS_HOST}}": "127.0.0.1",
    "{{REDIS_PORT}}": "6379",
    "{{REDIS_PASSWORD}}": "",
    "{{REDIS_SELECT}}": "0",
    "{{REDIS_TIMEOUT}}": "0",
    "{{REDIS_EXPIRE}}": "0",
    "{{LANG_DEFAULT}}": "zh-cn",
    "{{SYSTEM_STOP}}": "false",
    "{{USER_REG_EMAIL_REQUIRED}}": "false",
    "{{USER_CAN_MODIFY_BIND_ADDRESS}}": "true",
    "{{USER_CHECK_USER_ADDRESS}}": "true",
    "{{USER_PRODUCT_TYPE}}": "2",
    "{{USER_ADMIN_GOOGLE_AUTH}}": "false",
    "{{USER_WITHDRAWAL_DISABLE_MSG}}": "Your withdrawal has been rejected by the system. Please contact online customer service for advice.",
    "{{FILESYSTEM_DRIVER}}": "local",
    "{{LOG_CHANNEL}}": "file",
}
for k, v in repl.items():
    text = text.replace(k, v)
if "HOSTNAME = 127.0.0.1" in text or "HOSTNAME = localhost" in text:
    raise SystemExit("拒绝: DB HOST 不能是 127.0.0.1/localhost")
Path(sys.argv[2]).write_text(text, encoding="utf-8")
PY
chmod 640 "$CODE_DIR/.env"
# 容器内 PHP 为 www-data(UID 33)；宿主未必有同名用户，统一 chown 数值 UID
chown -R 33:33 "$CODE_DIR/runtime" "$CODE_DIR/public/uploads" "$CODE_DIR/public/tmp" "$CODE_DIR/.env" 2>/dev/null || true
# H5 合部署：静态资源软链（nginx alias 为双保险）
if [[ -d "$CODE_DIR/public/h5/static" ]]; then
  mkdir -p "$CODE_DIR/public/static"
  [[ -e "$CODE_DIR/public/static/js" ]] || ln -sfn ../h5/static/js "$CODE_DIR/public/static/js"
  [[ -e "$CODE_DIR/public/static/images" ]] || ln -sfn ../h5/static/images "$CODE_DIR/public/static/images"
  [[ -e "$CODE_DIR/public/static/index.css" ]] || ln -sfn ../h5/static/index.css "$CODE_DIR/public/static/index.css"
fi
# 合部署 / IP：清掉 H5 写死的外域 API，走同域
python3 - "$CODE_DIR" <<'PY' 2>/dev/null || true
import re, sys
from pathlib import Path
js_dir = Path(sys.argv[1]) / "public/h5/static/js"
if js_dir.is_dir():
    for p in js_dir.glob("index.*.js"):
        text = p.read_text(encoding="utf-8", errors="ignore")
        new = re.sub(r'API_BASE_DIRECT\s*[:=]\s*["\']https?://[^"\']+["\']', 'API_BASE_DIRECT:""', text)
        new = re.sub(r'baseUrl\s*:\s*["\']https?://[^"\']+["\']', 'baseUrl:""', new)
        if new != text:
            p.write_text(new, encoding="utf-8")
PY
ok "代码与 .env 就绪（DB_HOST=mysql）"

# ---------- 2) 渲染 compose ----------
log "[2/5] 渲染 docker-compose.yml"
cat > "${INST_DIR}/env.compose" <<EOF
TENANT_ID=${TENANT}
DB_NAME=${DB_NAME}
PORT=${PORT}
DB_HOST=mysql
DB_PORT=3306
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
DB_PREFIX=${DB_TABLE_PREFIX}
MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}
APP_URL=${APP_URL}
TZ=${TZ_NAME}
IMAGE_TAG=${IMAGE_TAG}
TENANT_CODE_DIR=${CODE_DIR}
WORKER_SLEEP=${WORKER_SLEEP}
BUILD_CONTEXT=${ROOT}
DOCKER_DIR=${ROOT}/docker
EOF

python3 "$ROOT/docker/render_compose.py" \
  -e "${INST_DIR}/env.compose" \
  -t "$ROOT/docker-compose.template.yml" \
  -o "$COMPOSE_FILE"
ok "$COMPOSE_FILE"

# ---------- 3) 构建并启动 ----------
log "[3/5] 构建并启动 mysql/app/worker/cron"
if [[ "$SKIP_DOCKER_BUILD" != "1" ]]; then
  "${DC[@]}" -f "$COMPOSE_FILE" build
fi
"${DC[@]}" -f "$COMPOSE_FILE" up -d

log "等待 mysql_${TENANT} healthy ..."
for i in $(seq 1 60); do
  st="$(docker inspect -f '{{.State.Health.Status}}' "mysql_${TENANT}" 2>/dev/null || echo starting)"
  [[ "$st" == "healthy" ]] && break
  sleep 2
  [[ $i -eq 60 ]] && die "MySQL 未就绪，请: docker logs mysql_${TENANT}"
done
ok "MySQL healthy"

# ---------- 4) 导入母模 SQL + 改超管密码 ----------
log "[4/5] 导入 base_template.sql 并设置 admin 密码"
docker exec -i "mysql_${TENANT}" \
  mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" "${DB_NAME}" < "$SQL_FILE"

docker exec "mysql_${TENANT}" \
  mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" "${DB_NAME}" \
  -e "UPDATE \`mod_admin\` SET \`password\`='${ADMIN_HASH}', \`update_time\`=UNIX_TIMESTAMP() WHERE \`username\`='admin' LIMIT 1;"

CNT="$(docker exec "mysql_${TENANT}" \
  mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" "${DB_NAME}" -Nse \
  "SELECT COUNT(*) FROM \`mod_admin\` WHERE \`username\`='admin' AND \`password\`='${ADMIN_HASH}';")"
[[ "$CNT" == "1" ]] || die "超管密码写入失败"
ok "SQL 导入完成（admin=纯MD5）"

# 冒烟：app → mysql
if docker exec "app_${TENANT}" php -r "
\$m=@new mysqli('mysql', '${DB_USER}', '${DB_PASS}', '${DB_NAME}', 3306);
exit(\$m->connect_errno ? 1 : 0);
" 2>/dev/null; then
  ok "app → mysql 连通正常"
else
  warn "app 连库冒烟失败，请查 docker logs app_${TENANT}"
fi

mkdir -p "$INSTANCES_ROOT"
echo "$PORT" >> "$PORT_REGISTRY"
sort -n -u "$PORT_REGISTRY" -o "$PORT_REGISTRY" 2>/dev/null || true

"${DC[@]}" -f "$COMPOSE_FILE" ps

# ---------- 5) 凭证 ----------
log "[5/5] 写入凭证"
cat > "${INST_DIR}/TENANT_INFO.txt" <<EOF
TENANT_ID=${TENANT}
DOMAIN=${DOMAIN}
APP_URL=${APP_URL}
PORT=${PORT}
CODE_DIR=${CODE_DIR}
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}
DB_HOST=mysql
ADMIN_USER=admin
ADMIN_PASS=${ADMIN_PASS_PLAIN}
ADMIN_PASS_ALGO=md5_plain_no_salt
ADMIN_URL=${APP_URL}/${ADMIN_ENTRY}
COMPOSE_FILE=${COMPOSE_FILE}
STACK=pure_docker
CREATED_AT=$(date '+%F %T')
EOF
chmod 600 "${INST_DIR}/TENANT_INFO.txt"

DIRECT="http://127.0.0.1:${PORT}/"
ADMIN_URL="${APP_URL}/${ADMIN_ENTRY}"

printf '\n'
printf '%s============================================================%s\n' "$C_BLD" "$C_RST"
printf '%s  纯 Docker 开通成功  %s%s\n' "$C_GRN" "$TENANT" "$C_RST"
printf '%s============================================================%s\n' "$C_BLD" "$C_RST"
printf '  %s本机访问%s  : %s%s%s\n' "$C_YLW" "$C_RST" "$C_CYN" "$DIRECT" "$C_RST"
printf '  %s对外访问%s  : %s%s/%s\n' "$C_YLW" "$C_RST" "$C_CYN" "$APP_URL" "$C_RST"
printf '  %s后台入口%s  : %s%s%s\n' "$C_YLW" "$C_RST" "$C_CYN" "$ADMIN_URL" "$C_RST"
printf '  %s超管账号%s  : %sadmin%s\n' "$C_YLW" "$C_RST" "$C_GRN" "$C_RST"
printf '  %s超管密码%s  : %s%s%s  (纯MD5无盐)\n' "$C_YLW" "$C_RST" "$C_RED" "$ADMIN_PASS_PLAIN" "$C_RST"
printf '  %sHTTP端口%s  : %s\n' "$C_YLW" "$C_RST" "$PORT"
printf '  %s容器%s      : mysql_%s / app_%s / worker_%s / cron_%s\n' "$C_YLW" "$C_RST" "$TENANT" "$TENANT" "$TENANT" "$TENANT"
printf '  %s凭证%s      : %s\n' "$C_YLW" "$C_RST" "${INST_DIR}/TENANT_INFO.txt"
printf '%s============================================================%s\n' "$C_BLD" "$C_RST"
if [[ "$_use_ip" -eq 1 ]]; then
  printf '%s说明%s: 当前为 IP:端口 访问；域名后配时改 .env 的 APP_URL/FRONTEND_INVITE_BASE 并反代到 127.0.0.1:%s\n' "$C_YLW" "$C_RST" "$PORT"
else
  printf '%s说明%s: 请将域名反代到 127.0.0.1:%s ；DNS: *.%s → 公网 IP\n' "$C_YLW" "$C_RST" "$PORT" "$BASE_DOMAIN"
fi
printf '  日志: docker compose -f %s logs -f\n' "$COMPOSE_FILE"
printf '  停止: docker compose -f %s down\n' "$COMPOSE_FILE"
printf '\n'
