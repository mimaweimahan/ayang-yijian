# 纯 Docker 多租户部署（不依赖宝塔）

## 一键开通

```bash
cd /www/wwwroot/ayang.sgesge.com   # 或你的母模目录

# 可选：改域名后缀
cp deploy/oneclick_tenant.conf.example deploy/oneclick_tenant.conf

# 需要已安装 Docker
curl -fsSL https://get.docker.com | bash
systemctl enable --now docker

./oneclick_tenant.sh agent01 'Pass888!'
```

每个租户自动拉起 **4 个容器**：

| 容器 | 作用 |
|------|------|
| `mysql_{id}` | 独占 MySQL 5.7 + 独立数据卷 |
| `app_{id}` | Nginx + PHP-FPM，映射宿主机端口 |
| `worker_{id}` | 常驻任务（崩溃自动重启） |
| `cron_{id}` | 定时任务（含每天 12:01 订单重置） |

`.env` 内 `DB_HOST=mysql`（Compose 服务名），**不会**写 `127.0.0.1`。

## 域名 / HTTPS

脚本不碰宝塔。在任意入口（Caddy / Traefik / 自建 Nginx）反代到：

`http://127.0.0.1:{分配的端口}`

DNS：`*.你的域名` → 服务器公网 IP。

## 运维

```bash
docker compose -f instances/agent01/docker-compose.yml logs -f
docker compose -f instances/agent01/docker-compose.yml down
# 连删数据卷（慎用）:
docker compose -f instances/agent01/docker-compose.yml down -v
```
