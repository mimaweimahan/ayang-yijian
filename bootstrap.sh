#!/usr/bin/env bash
# =============================================================================
# bootstrap.sh — 新 VPS 全流程一键（纯 Docker，域名可后配）
#
# 覆盖：装依赖/Docker → 构建镜像 → 开通首个租户（默认纯 IP:端口）
#
# 前提：已在本目录放好母模（含 base_template.sql / .env.example / docker/）
#   方式 A: git clone ... && cd ayang-yijian && git checkout skysc-bianyi
#   方式 B: tar 解压母模包到本目录
#
# 用法:
#   bash bootstrap.sh
#   bash bootstrap.sh agent01 'Pass888!'
#   bash bootstrap.sh agent01 'Pass888!' 18001
#   bash bootstrap.sh agent01 'Pass888!' 18001 --domain example.com   # 有域名时
#
# 环境变量（可选）:
#   ACCESS_MODE=ip|domain|auto   默认 auto（占位域名则走公网IP）
#   PUBLIC_IP=x.x.x.x            探测失败时手动指定
#   SKIP_DOCKER=1                已装 Docker 时跳过安装
# =============================================================================
# 若含 CRLF，去 \r 后重入（必须在 set -euo 之前）
if grep -q $'\r' "$0" 2>/dev/null; then
  _t="$(mktemp)"
  tr -d '\r' < "$0" > "$_t"
  chmod +x "$_t"
  exec bash "$_t" "$@"
fi
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"
sed -i 's/\r$//' "$ROOT/bootstrap.sh" "$ROOT/oneclick_tenant.sh" "$ROOT/install_vps.sh" 2>/dev/null || true

C_RED=$'\033[1;31m'; C_GRN=$'\033[1;32m'; C_YLW=$'\033[1;33m'
C_CYN=$'\033[1;36m'; C_BLD=$'\033[1m'; C_RST=$'\033[0m'
log()  { printf '%s[bootstrap]%s %s\n' "$C_CYN" "$C_RST" "$*"; }
ok()   { printf '%s[ok]%s %s\n' "$C_GRN" "$C_RST" "$*"; }
die()  { printf '%s[error]%s %s\n' "$C_RED" "$C_RST" "$*" >&2; exit 1; }

TENANT="agent01"
ADMIN_PASS="Pass888!"
PORT_ARG=""
DOMAIN_ARG=""
SKIP_DOCKER="${SKIP_DOCKER:-0}"

# 解析：位置参数 + 可选 --domain / --skip-docker
POSITIONAL=()
while [[ $# -gt 0 ]]; do
  case "$1" in
    --domain)
      DOMAIN_ARG="${2:-}"; shift 2 ;;
    --skip-docker)
      SKIP_DOCKER=1; shift ;;
    -h|--help)
      sed -n '2,22p' "$0"; exit 0 ;;
    --*)
      die "未知参数: $1" ;;
    *)
      POSITIONAL+=("$1"); shift ;;
  esac
done
set -- "${POSITIONAL[@]+"${POSITIONAL[@]}"}"
[[ -n "${1:-}" ]] && TENANT="$1"
[[ -n "${2:-}" ]] && ADMIN_PASS="$2"
[[ -n "${3:-}" ]] && PORT_ARG="$3"

[[ "$(id -u)" -eq 0 ]] || die "请使用 root 运行: bash bootstrap.sh ..."

[[ -f "$ROOT/install_vps.sh" ]] || die "缺少 install_vps.sh（请在母模根目录执行）"
[[ -f "$ROOT/oneclick_tenant.sh" ]] || die "缺少 oneclick_tenant.sh"
[[ -f "$ROOT/base_template.sql" ]] || die "缺少 base_template.sql，先: python3 clean_db.py"
[[ -f "$ROOT/.env.example" ]] || die "缺少 .env.example"
[[ -f "$ROOT/docker/Dockerfile" ]] || die "缺少 docker/Dockerfile"
[[ -f "$ROOT/docker/nginx/default.conf" ]] || die "缺少 docker/nginx/default.conf"

chmod +x "$ROOT/install_vps.sh" "$ROOT/oneclick_tenant.sh" "$ROOT/docker/scripts/"*.sh 2>/dev/null || true

# ---------- 1) 初始化环境 + 镜像 ----------
log "===== [1/2] 初始化 VPS（Docker + 目录 + 镜像）====="
INSTALL_ARGS=()
[[ "$SKIP_DOCKER" == "1" ]] && INSTALL_ARGS+=(--skip-docker)
[[ -n "$DOMAIN_ARG" ]] && INSTALL_ARGS+=(--domain "$DOMAIN_ARG")

NEED_BUILD=1
if docker image inspect tenant-app:php74 >/dev/null 2>&1; then
  # 若镜像已存在仍建议带上当前 nginx 修复重建；用指纹文件判断是否强制
  CONF_MARK="$ROOT/instances/.image_nginx_fingerprint"
  CUR_FP="$(cksum "$ROOT/docker/nginx/default.conf" "$ROOT/docker/Dockerfile" 2>/dev/null | cksum | awk '{print $1}')"
  OLD_FP="$(cat "$CONF_MARK" 2>/dev/null || true)"
  if [[ -n "$CUR_FP" && "$CUR_FP" == "$OLD_FP" ]]; then
    NEED_BUILD=0
    ok "镜像 tenant-app:php74 已是当前 Dockerfile/nginx，跳过重建"
  else
    log "检测到 Dockerfile/nginx 变更或首次构建，将重建镜像"
  fi
fi

if [[ "$NEED_BUILD" -eq 1 ]]; then
  INSTALL_ARGS+=(--build-image)
fi

bash "$ROOT/install_vps.sh" "${INSTALL_ARGS[@]+"${INSTALL_ARGS[@]}"}"

if [[ "$NEED_BUILD" -eq 1 ]]; then
  mkdir -p "$ROOT/instances"
  cksum "$ROOT/docker/nginx/default.conf" "$ROOT/docker/Dockerfile" 2>/dev/null | cksum | awk '{print $1}' \
    > "$ROOT/instances/.image_nginx_fingerprint"
fi

# ---------- 2) 开通租户 ----------
log "===== [2/2] 开通租户 $TENANT ======"
export ACCESS_MODE="${ACCESS_MODE:-auto}"
[[ -n "${PUBLIC_IP:-}" ]] && export PUBLIC_IP

if [[ -n "$DOMAIN_ARG" ]]; then
  export ACCESS_MODE=domain
  # install_vps 已写 BASE_DOMAIN；确保 oneclick 读到
fi

OC_ARGS=("$TENANT" "$ADMIN_PASS")
[[ -n "$PORT_ARG" ]] && OC_ARGS+=("$PORT_ARG")

bash "$ROOT/oneclick_tenant.sh" "${OC_ARGS[@]}"

ok "全流程完成"
printf '%s提示%s: 安全组放行 22 与租户端口（默认 18001+）；域名后配即可。\n' "$C_YLW" "$C_RST"
