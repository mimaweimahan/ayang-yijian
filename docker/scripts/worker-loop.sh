#!/usr/bin/env bash
# 常驻 Worker：循环执行监控/巡检；异常退出由 supervisord 拉起整进程
set -euo pipefail

cd /var/www/html
SLEEP_SEC="${WORKER_SLEEP:-30}"
TENANT_ID="${TENANT_ID:-unknown}"

echo "[worker:${TENANT_ID}] start loop, sleep=${SLEEP_SEC}s"

while true; do
  echo "[worker:${TENANT_ID}] $(date '+%F %T') tick"
  # 订单/钱包一致性巡检（ThinkPHP 命令）
  php think monitor || echo "[worker:${TENANT_ID}] monitor exited $?"
  # 预留：后续可在此追加派单队列消费，例如:
  # php think dispatchOrders || true
  sleep "${SLEEP_SEC}"
done
