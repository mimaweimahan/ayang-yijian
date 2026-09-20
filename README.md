# ayang-yijian — 任务系统 Docker 多租户母模

纯 Docker 一键开通，**不依赖宝塔**。每租户独立：`mysql` + `app` + `worker` + `cron`。

## 仓库里关键文件

| 文件 | 作用 |
|------|------|
| `base_template.sql` | 清洗后的母模库（仅保留系统数据 + admin） |
| `oneclick_tenant.sh` | 一键开通租户 |
| `install_vps.sh` | 新 VPS 初始化（装 Docker 等） |
| `pack_mother.sh` | 母模机打包 |
| `docker-compose.template.yml` | 租户 Compose 模板 |
| `docker/` | 镜像 / Supervisor / Crontab（订单重置 **每天 12:01**） |
| `clean_db.py` / `purge_runtime.sh` | 库清洗与运行时清理 |
| `docs/VPS_DEPLOY.md` | 新机器部署说明 |

## 新 VPS 快速开始

```bash
# 1) 克隆
git clone https://github.com/mimaweimahan/ayang-yijian.git
cd ayang-yijian

# 2) 初始化环境
bash install_vps.sh --domain yourdomain.com --build-image

# 3) 开通租户
./oneclick_tenant.sh agent01 'Pass888!'
```

本机已有 Docker 时可直接：

```bash
./oneclick_tenant.sh agent01 'Pass888!'
```

## 说明

- 超管密码算法：纯 `md5(明文)`，无盐（与 `app/common/model/Admin.php` 一致）
- 容器内 `DB_HOST=mysql`（Compose 服务名），不是 `127.0.0.1`
- 域名 / HTTPS 请用 Caddy、Traefik 或自建 Nginx 反代到 `127.0.0.1:分配端口`
- DNS 建议：`*.yourdomain.com` → 服务器公网 IP

详见 [docs/VPS_DEPLOY.md](docs/VPS_DEPLOY.md) 与 [docker/README.md](docker/README.md)。
