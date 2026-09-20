#!/usr/bin/env bash
# =============================================================================
# pack_mother.sh — 在「母模机」打包，便于拷到新 VPS
#
# 用法:
#   ./pack_mother.sh
#   ./pack_mother.sh /tmp/mother_release
#
# 产出: mother_template_YYYYMMDD_HHMM.tar.gz（含 base_template.sql / docker / 代码）
# =============================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OUT_DIR="${1:-$ROOT}"
STAMP="$(date +%Y%m%d_%H%M)"
NAME="mother_template_${STAMP}"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

cd "$ROOT"
[[ -f base_template.sql ]] || { echo "缺少 base_template.sql，先: python3 clean_db.py"; exit 1; }
[[ -f oneclick_tenant.sh ]] || { echo "缺少 oneclick_tenant.sh"; exit 1; }

echo "[pack] 同步母模到暂存目录 ..."
mkdir -p "$STAGE/$NAME"
rsync -a \
  --exclude '.git/' \
  --exclude '.env' \
  --exclude 'runtime/' \
  --exclude 'instances/' \
  --exclude 'xinayang_*.sql*' \
  --exclude '*.tar.gz' \
  --exclude 'database/backup/' \
  --exclude 'public/uploads/20*/' \
  --exclude 'ayang.sgesge.com_*.tar.gz' \
  --exclude 'backend.zip' \
  ./ "$STAGE/$NAME/"

# 保留系统白名单图：若 uploads 被排除过多，至少确保目录存在
mkdir -p "$STAGE/$NAME/public/uploads" "$STAGE/$NAME/runtime"

ARCHIVE="$OUT_DIR/${NAME}.tar.gz"
tar -C "$STAGE" -czf "$ARCHIVE" "$NAME"
echo "[pack] OK => $ARCHIVE"
ls -lh "$ARCHIVE"
echo
echo "传到新 VPS 示例:"
echo "  scp $ARCHIVE root@新VPS_IP:/opt/"
echo "  ssh root@新VPS_IP"
echo "  mkdir -p /opt/mother && tar -xzf /opt/${NAME}.tar.gz -C /opt/mother --strip-components=1"
echo "  cd /opt/mother && bash bootstrap.sh agent01 'Pass888!'"
