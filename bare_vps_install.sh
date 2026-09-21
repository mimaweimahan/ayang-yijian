#!/usr/bin/env bash
# =============================================================================
# bare_vps_install.sh — 裸机一条龙（clone 已在本脚本内，外层不必再写 git clone）
#   1) yum upgrade（Debian 则 apt upgrade）
#   2) 安装 git
#   3) git clone -b skysc-bianyi → /opt/ayang-yijian
#   4) bash bootstrap.sh（Docker + 镜像 + 开通租户）
#
# 推荐外层命令（upgrade + 装 git/curl 后交给本脚本）:
#
#   yum upgrade -y && yum install -y git curl && \
#   curl -fsSL https://raw.githubusercontent.com/mimaweimahan/ayang-yijian/skysc-bianyi/bare_vps_install.sh \
#     | bash -s -- agent01 'Pass888!' 18001
#
# 或一条管道（本脚本内也会再 upgrade / 装 git / clone）:
#
#   curl -fsSL https://raw.githubusercontent.com/mimaweimahan/ayang-yijian/skysc-bianyi/bare_vps_install.sh \
#     | bash -s -- agent01 'Pass888!' 18001
# =============================================================================
# 若含 CRLF，去 \r 后重入（必须在 set -euo 之前）
if grep -q $'\r' "$0" 2>/dev/null; then
  _t="$(mktemp)"
  tr -d '\r' < "$0" > "$_t"
  chmod +x "$_t"
  exec bash "$_t" "$@"
fi
set -euo pipefail

C_RED=$'\033[1;31m'; C_GRN=$'\033[1;32m'; C_YLW=$'\033[1;33m'
C_CYN=$'\033[1;36m'; C_RST=$'\033[0m'
log()  { printf '%s[bare]%s %s\n' "$C_CYN" "$C_RST" "$*"; }
ok()   { printf '%s[ok]%s %s\n' "$C_GRN" "$C_RST" "$*"; }
die()  { printf '%s[error]%s %s\n' "$C_RED" "$C_RST" "$*" >&2; exit 1; }

[[ "$(id -u)" -eq 0 ]] || die "请使用 root 运行"

REPO_URL="${REPO_URL:-https://github.com/mimaweimahan/ayang-yijian.git}"
REPO_BRANCH="${REPO_BRANCH:-skysc-bianyi}"
INSTALL_DIR="${INSTALL_DIR:-/opt/ayang-yijian}"
GITHUB_TOKEN="${GITHUB_TOKEN:-}"

TENANT="agent01"
ADMIN_PASS="Pass888!"
PORT_ARG=""
DOMAIN_ARG=""

POSITIONAL=()
while [[ $# -gt 0 ]]; do
  case "$1" in
    --domain) DOMAIN_ARG="${2:-}"; shift 2 ;;
    -h|--help) sed -n '2,28p' "$0"; exit 0 ;;
    --*) die "未知参数: $1" ;;
    *) POSITIONAL+=("$1"); shift ;;
  esac
done
set -- "${POSITIONAL[@]+"${POSITIONAL[@]}"}"
[[ -n "${1:-}" ]] && TENANT="$1"
[[ -n "${2:-}" ]] && ADMIN_PASS="$2"
[[ -n "${3:-}" ]] && PORT_ARG="$3"

# 若当前脚本已在带 bootstrap.sh 的仓库里，优先用该目录
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ -f "$SCRIPT_DIR/bootstrap.sh" && -f "$SCRIPT_DIR/base_template.sql" ]]; then
  INSTALL_DIR="$SCRIPT_DIR"
fi

# ---------- 1) 系统升级（强制先做）----------
log "[1/4] 系统升级..."
if command -v yum >/dev/null 2>&1; then
  yum upgrade -y
elif command -v dnf >/dev/null 2>&1; then
  dnf upgrade -y
elif command -v apt-get >/dev/null 2>&1; then
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -y
  apt-get upgrade -y
else
  die "未识别 yum/dnf/apt"
fi
ok "系统升级完成"

# ---------- 2) 安装 git ----------
log "[2/4] 安装 git..."
if ! command -v git >/dev/null 2>&1; then
  if command -v yum >/dev/null 2>&1; then
    yum install -y git ca-certificates curl
  elif command -v dnf >/dev/null 2>&1; then
    dnf install -y git ca-certificates curl
  elif command -v apt-get >/dev/null 2>&1; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get install -y git ca-certificates curl
  fi
else
  # 确保 curl 也在（探测公网 IP 等）
  if command -v yum >/dev/null 2>&1; then
    yum install -y git ca-certificates curl
  elif command -v dnf >/dev/null 2>&1; then
    dnf install -y git ca-certificates curl
  elif command -v apt-get >/dev/null 2>&1; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get install -y git ca-certificates curl
  fi
fi
command -v git >/dev/null || die "git 安装失败"
ok "$(git --version | head -1)"

# ---------- 3) 拉取 / 更新母模 ----------
log "[3/4] 同步仓库 $REPO_BRANCH => $INSTALL_DIR ..."
CLONE_URL="$REPO_URL"
if [[ -n "$GITHUB_TOKEN" ]]; then
  CLONE_URL="$(echo "$REPO_URL" | sed -E "s#https://github.com/#https://x-access-token:${GITHUB_TOKEN}@github.com/#")"
fi

if [[ -d "$INSTALL_DIR/.git" ]]; then
  git -C "$INSTALL_DIR" remote set-url origin "$CLONE_URL" 2>/dev/null || true
  git -C "$INSTALL_DIR" fetch origin
  git -C "$INSTALL_DIR" checkout "$REPO_BRANCH"
  git -C "$INSTALL_DIR" pull --ff-only origin "$REPO_BRANCH" || \
    git -C "$INSTALL_DIR" reset --hard "origin/$REPO_BRANCH"
  git -C "$INSTALL_DIR" remote set-url origin "$REPO_URL" 2>/dev/null || true
elif [[ -f "$INSTALL_DIR/bootstrap.sh" && -f "$INSTALL_DIR/base_template.sql" ]]; then
  ok "检测到本地母模（无 .git），跳过 clone，直接部署"
else
  mkdir -p "$(dirname "$INSTALL_DIR")"
  if [[ -e "$INSTALL_DIR" ]]; then
    die "目标已存在且不是可用母模目录: $INSTALL_DIR"
  fi
  git clone -b "$REPO_BRANCH" "$CLONE_URL" "$INSTALL_DIR"
  git -C "$INSTALL_DIR" remote set-url origin "$REPO_URL" 2>/dev/null || true
fi
ok "代码就绪"

# ---------- 4) bootstrap ----------
log "[4/4] 执行 bootstrap.sh ..."
cd "$INSTALL_DIR"
# 防止 Windows/CRLF 导致 set -o pipefail 立刻失败
sed -i 's/\r$//' bootstrap.sh oneclick_tenant.sh install_vps.sh bare_vps_install.sh docker/scripts/*.sh 2>/dev/null || true
chmod +x bootstrap.sh oneclick_tenant.sh install_vps.sh bare_vps_install.sh 2>/dev/null || true
[[ -f ./bootstrap.sh ]] || die "缺少 bootstrap.sh"
bash -n ./bootstrap.sh || die "bootstrap.sh 语法错误（请检查是否 CRLF）"

BOOT_ARGS=("$TENANT" "$ADMIN_PASS")
[[ -n "$PORT_ARG" ]] && BOOT_ARGS+=("$PORT_ARG")
[[ -n "$DOMAIN_ARG" ]] && BOOT_ARGS+=(--domain "$DOMAIN_ARG")

bash ./bootstrap.sh "${BOOT_ARGS[@]}"

ok "裸机一键全部完成"
