#!/usr/bin/env bash
# =============================================================================
# install_vps.sh — 新 VPS 裸机初始化（纯 Docker，不装宝塔）
#
# 在新机器上：
#   1) 把母模包传到服务器并解压，或 git clone 后进入目录
#   2) bash install_vps.sh
#   3) ./oneclick_tenant.sh agent01 'Pass888!'
#
# 可选参数:
#   --domain example.com     写入 BASE_DOMAIN
#   --skip-docker            已装 Docker 时跳过安装
#   --build-image            初始化后预构建应用镜像
# =============================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

C_RED=$'\033[1;31m'; C_GRN=$'\033[1;32m'; C_YLW=$'\033[1;33m'
C_CYN=$'\033[1;36m'; C_RST=$'\033[0m'
log()  { printf '%s[vps]%s %s\n' "$C_CYN" "$C_RST" "$*"; }
ok()   { printf '%s[ok]%s %s\n' "$C_GRN" "$C_RST" "$*"; }
warn() { printf '%s[warn]%s %s\n' "$C_YLW" "$C_RST" "$*"; }
die()  { printf '%s[error]%s %s\n' "$C_RED" "$C_RST" "$*" >&2; exit 1; }

DOMAIN_ARG=""
SKIP_DOCKER=0
BUILD_IMAGE=0
while [[ $# -gt 0 ]]; do
  case "$1" in
    --domain) DOMAIN_ARG="${2:-}"; shift 2 ;;
    --skip-docker) SKIP_DOCKER=1; shift ;;
    --build-image) BUILD_IMAGE=1; shift ;;
    -h|--help)
      sed -n '2,14p' "$0"; exit 0 ;;
    *) die "未知参数: $1" ;;
  esac
done

[[ "$(id -u)" -eq 0 ]] || die "请使用 root 运行（或 sudo bash install_vps.sh）"

[[ -f "$ROOT/oneclick_tenant.sh" ]] || die "请在母模根目录执行（缺少 oneclick_tenant.sh）"
[[ -f "$ROOT/docker-compose.template.yml" ]] || die "缺少 docker-compose.template.yml"
[[ -f "$ROOT/docker/Dockerfile" ]] || die "缺少 docker/Dockerfile"
[[ -f "$ROOT/base_template.sql" ]] || die "缺少 base_template.sql
请在母模机先: python3 clean_db.py
再打包传到本机。"
[[ -f "$ROOT/.env.example" ]] || die "缺少 .env.example"

# ---------- OS ----------
. /etc/os-release 2>/dev/null || true
ID_LIKE="${ID_LIKE:-}"
OS_ID="${ID:-unknown}"
log "系统: ${PRETTY_NAME:-$OS_ID}"

pkg_install() {
  if command -v apt-get >/dev/null 2>&1; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -y
    apt-get install -y "$@"
  elif command -v dnf >/dev/null 2>&1; then
    dnf install -y "$@"
  elif command -v yum >/dev/null 2>&1; then
    yum install -y "$@"
  else
    die "不支持的包管理器，请手动安装: $*"
  fi
}

# ---------- 基础工具 ----------
log "[1/5] 安装基础工具 (python3/rsync/curl/git) ..."
if command -v apt-get >/dev/null 2>&1; then
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -y
  apt-get install -y python3 rsync curl git ca-certificates
elif command -v dnf >/dev/null 2>&1; then
  dnf install -y python3 rsync curl git
elif command -v yum >/dev/null 2>&1; then
  yum install -y python3 rsync curl git
else
  warn "未识别包管理器，请确保已安装 python3 rsync curl"
fi
command -v python3 >/dev/null || die "需要 python3"
command -v rsync >/dev/null || die "需要 rsync"
ok "基础工具就绪"

# ---------- Docker ----------
if [[ "$SKIP_DOCKER" -eq 1 ]]; then
  log "[2/5] 跳过 Docker 安装"
else
  log "[2/5] 安装 Docker Engine + Compose 插件 ..."
  if ! command -v docker >/dev/null 2>&1; then
    curl -fsSL https://get.docker.com | bash
  else
    ok "Docker 已存在: $(docker --version)"
  fi
  systemctl enable docker >/dev/null 2>&1 || true
  systemctl start docker
fi

command -v docker >/dev/null || die "Docker 未安装成功"
if docker compose version >/dev/null 2>&1; then
  ok "docker compose: $(docker compose version --short 2>/dev/null || docker compose version | head -1)"
elif command -v docker-compose >/dev/null 2>&1; then
  warn "仅有 docker-compose 独立命令，建议安装 compose plugin"
else
  # 尝试装插件
  if command -v apt-get >/dev/null 2>&1; then
    apt-get install -y docker-compose-plugin || true
  elif command -v dnf >/dev/null 2>&1; then
    dnf install -y docker-compose-plugin || true
  fi
  docker compose version >/dev/null 2>&1 || die "缺少 docker compose，请手动安装 docker-compose-plugin"
fi

# ---------- 目录与配置 ----------
log "[3/5] 创建目录与配置 ..."
mkdir -p /var/www/tenants "$ROOT/instances"
chmod +x "$ROOT/oneclick_tenant.sh" "$ROOT/docker/scripts/"*.sh 2>/dev/null || true

CONF="$ROOT/deploy/oneclick_tenant.conf"
if [[ ! -f "$CONF" ]]; then
  cp "$ROOT/deploy/oneclick_tenant.conf.example" "$CONF"
  ok "已生成 $CONF"
fi
if [[ -n "$DOMAIN_ARG" ]]; then
  if grep -q '^BASE_DOMAIN=' "$CONF"; then
    sed -i "s/^BASE_DOMAIN=.*/BASE_DOMAIN=${DOMAIN_ARG}/" "$CONF"
  else
    echo "BASE_DOMAIN=${DOMAIN_ARG}" >> "$CONF"
  fi
  ok "BASE_DOMAIN=${DOMAIN_ARG}"
fi

# vendor：镜像构建不依赖宿主 vendor，但 rsync 到租户目录时最好有
if [[ ! -d "$ROOT/vendor" ]]; then
  warn "母模缺少 vendor/。若租户目录需要本地依赖，请在有 PHP 的机器 composer install 后打进母模包。"
  warn "纯 Docker 运行时依赖镜像内 PHP；业务代码仍建议带上 vendor。"
fi

# ---------- 防火墙提示 ----------
log "[4/5] 防火墙提示（脚本不强制改规则）"
warn "云厂商安全组 / ufw / firewalld 请至少放行: 22, 80, 443"
warn "租户应用端口默认 18001+（若只用入口反代到本机端口，可不对公网放行 18001）"

# ---------- 预构建镜像 ----------
if [[ "$BUILD_IMAGE" -eq 1 ]]; then
  log "[5/5] 预构建应用镜像 tenant-app:php74 ..."
  docker build -t tenant-app:php74 -f "$ROOT/docker/Dockerfile" "$ROOT"
  # 后续开通可设 SKIP_DOCKER_BUILD=1
  if grep -q '^SKIP_DOCKER_BUILD=' "$CONF" 2>/dev/null; then
    sed -i 's/^SKIP_DOCKER_BUILD=.*/SKIP_DOCKER_BUILD=1/' "$CONF"
  else
    echo "SKIP_DOCKER_BUILD=1" >> "$CONF"
  fi
  ok "镜像已构建，后续开通将跳过 build"
else
  log "[5/5] 跳过预构建（首次 oneclick 时会自动 build）"
fi

PUBLIC_IP="$(curl -fsS --max-time 5 ifconfig.me 2>/dev/null || curl -fsS --max-time 5 icanhazip.com 2>/dev/null || echo '未知')"

printf '\n'
ok "VPS 母模环境已就绪"
printf '%s下一步:%s\n' "$C_YLW" "$C_RST"
printf '  1) DNS: *.你的域名  A → %s\n' "$PUBLIC_IP"
printf '  2) 编辑配置: vi %s\n' "$CONF"
printf '  3) 开通租户: %s/oneclick_tenant.sh agent01 '\''Pass888!'\''\n' "$ROOT"
printf '  4) 入口反代(可选): Caddy/Traefik/Nginx → 127.0.0.1:分配端口\n'
printf '\n'
