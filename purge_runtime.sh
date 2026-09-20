#!/usr/bin/env bash
# =============================================================================
# purge_runtime.sh — 一键清理运行时垃圾，可选净化 uploads
#
# 用法:
#   ./purge_runtime.sh                  # 仅清 runtime / tmp / *.log（安全默认）
#   ./purge_runtime.sh --uploads        # 净化 uploads：保留系统图 + 商品图，删用户私货
#   ./purge_runtime.sh --all            # runtime + uploads（同上，默认保留商品图）
#   ./purge_runtime.sh --uploads --no-goods  # 不保留商品图（仅系统占位/配置图）
#   ./purge_runtime.sh --dry-run        # 只打印将删除的内容
# =============================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

DRY_RUN=0
DO_UPLOADS=0
KEEP_GOODS=1   # 默认保留 base_template 中的商品图

for arg in "$@"; do
  case "$arg" in
    --dry-run) DRY_RUN=1 ;;
    --uploads) DO_UPLOADS=1 ;;
    --keep-goods) KEEP_GOODS=1 ;;
    --no-goods) KEEP_GOODS=0 ;;
    --all) DO_UPLOADS=1 ;;
    -h|--help)
      sed -n '2,14p' "$0"
      exit 0
      ;;
    *)
      echo "未知参数: $arg" >&2
      exit 1
      ;;
  esac
done

log() { printf '[purge] %s\n' "$*"; }

run_rm() {
  # run_rm <path...>
  if [[ "$DRY_RUN" -eq 1 ]]; then
    log "DRY-RUN rm -rf $*"
  else
    rm -rf "$@"
  fi
}

ensure_gitkeep_tree() {
  local dir="$1"
  mkdir -p "$dir"
  if [[ "$DRY_RUN" -eq 0 ]]; then
    : >"$dir/.gitkeep"
  else
    log "DRY-RUN touch $dir/.gitkeep"
  fi
}

# ---------------------------------------------------------------------------
# 1) 运行时缓存：可安全清空，但必须保留目录骨架
# ---------------------------------------------------------------------------
RUNTIME_KEEP_DIRS=(
  "runtime"
  "runtime/cache"
  "runtime/session"
  "runtime/admin"
  "runtime/admin/log"
  "runtime/admin/temp"
  "runtime/api"
  "runtime/api/log"
  "public/tmp"
)

log "清理 runtime / 临时日志（保留目录结构）..."

# 删除 runtime 下所有内容，再重建骨架
if [[ -d runtime ]]; then
  if [[ "$DRY_RUN" -eq 1 ]]; then
    log "DRY-RUN: 将清空 runtime/ 下全部文件与子目录内容"
    find runtime -mindepth 1 -maxdepth 3 \( -type f -o -type d \) 2>/dev/null | head -40 || true
  else
    # 不删除 runtime 本身，只清内部
    find runtime -mindepth 1 -maxdepth 1 -exec rm -rf {} +
  fi
fi

for d in "${RUNTIME_KEEP_DIRS[@]}"; do
  ensure_gitkeep_tree "$d"
done

# 根目录与其它散落日志
EXTRA_LOGS=(
  "runtime/cron_reset_orders.log"
  "public/tmp/entry_access.log"
)
for f in "${EXTRA_LOGS[@]}"; do
  [[ -e "$f" ]] && run_rm "$f"
done

# 常见误生成目录（本项目若存在则清空）
for d in storage/logs storage/framework/cache storage/framework/sessions \
         storage/framework/views bootstrap/cache temp cache logs; do
  if [[ -d "$d" ]]; then
    log "发现 $d ，清空内容并保留目录"
    if [[ "$DRY_RUN" -eq 1 ]]; then
      log "DRY-RUN clear $d/*"
    else
      find "$d" -mindepth 1 -maxdepth 1 -exec rm -rf {} +
      : >"$d/.gitkeep"
    fi
  fi
done

# ---------------------------------------------------------------------------
# 2) uploads：保留系统占位 / 配置引用图，清空用户私货
# ---------------------------------------------------------------------------
purge_uploads() {
  local uploads="public/uploads"
  [[ -d "$uploads" ]] || { log "无 $uploads，跳过"; return 0; }

  local keep_list
  keep_list="$(mktemp)"
  trap 'rm -f "$keep_list"' RETURN

  log "生成 uploads 白名单（商品图默认保留: KEEP_GOODS=$KEEP_GOODS）..."

  # (a) 根目录非日期文件（如 index-img-hotel-title.png）
  find "$uploads" -maxdepth 1 -type f ! -name '.gitkeep' -printf '%P\n' >>"$keep_list" || true

  # (b) 显式清单（可手写补充）
  if [[ -f deploy/uploads_keep.txt ]]; then
    log "合并 deploy/uploads_keep.txt"
    # 允许写 /uploads/xxx 或 xxx
    sed -e 's/\r$//' -e '/^[[:space:]]*#/d' -e '/^[[:space:]]*$/d' \
        -e 's#^/uploads/##' -e 's#^uploads/##' deploy/uploads_keep.txt >>"$keep_list" || true
  fi

  # (c) 从 base_template.sql 抽取系统表 + 商品表引用的图片
  if [[ -f base_template.sql ]]; then
    python3 - "$KEEP_GOODS" <<'PY' >>"$keep_list"
import re, sys
from pathlib import Path

keep_goods = sys.argv[1] == "1"
tables = {"mod_configure", "mod_level", "mod_announcement", "mod_app_roll", "mod_hotsales"}
if keep_goods:
    tables |= {"mod_goods", "mod_goods2", "mod_goods3", "mod_goods_bat"}

path = Path("base_template.sql")
cur = None
found = set()
with path.open(encoding="utf-8", errors="replace") as f:
    for line in f:
        m = re.match(r"INSERT INTO `([^`]+)`", line)
        if m:
            cur = m.group(1)
        if cur not in tables:
            continue
        # mysqldump 可能双重转义 \/ → 去掉反斜杠再匹配
        flat = line.replace("\\", "")
        for u in re.findall(r"/uploads/([A-Za-z0-9_./-]+)", flat):
            found.add(u)

for u in sorted(found):
    print(u)
PY
  else
    log "未找到 base_template.sql，仅保留 uploads 根目录文件 + uploads_keep.txt"
  fi

  # 规范化去重
  sort -u "$keep_list" -o "$keep_list"
  local keep_count
  keep_count="$(wc -l <"$keep_list" | tr -d ' ')"
  log "白名单条目: $keep_count"

  # 删除「日期目录」下不在白名单的文件
  local deleted=0 kept=0
  while IFS= read -r -d '' file; do
    rel="${file#public/uploads/}"
    if grep -Fxq "$rel" "$keep_list"; then
      kept=$((kept + 1))
      continue
    fi
    if [[ "$DRY_RUN" -eq 1 ]]; then
      # 避免刷屏：只统计
      deleted=$((deleted + 1))
    else
      rm -f "$file"
      deleted=$((deleted + 1))
    fi
  done < <(find "$uploads" -type f ! -name '.gitkeep' -print0)

  # 删除空的日期子目录
  if [[ "$DRY_RUN" -eq 0 ]]; then
    find "$uploads" -mindepth 1 -type d -empty -delete 2>/dev/null || true
  fi

  ensure_gitkeep_tree "$uploads"
  log "uploads 完成: 删除文件约 $deleted 个, 命中白名单保留约 $kept 个"
  if [[ "$DRY_RUN" -eq 1 ]]; then
    log "DRY-RUN 白名单预览(前 30 条):"
    head -30 "$keep_list" | sed 's/^/  /'
  fi
}

if [[ "$DO_UPLOADS" -eq 1 ]]; then
  purge_uploads
else
  log "跳过 uploads（如需净化请加 --uploads 或 --all）"
fi

log "完成。工作目录: $ROOT"
if [[ "$DRY_RUN" -eq 1 ]]; then
  log "当前为 dry-run，未实际删除。"
fi
