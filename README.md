# ayang-yijian — 任务系统 Docker 多租户母模

纯 Docker 一键开通，**不依赖宝塔**。每租户独立：`mysql` + `app` + `worker` + `cron`。

## 仓库里关键文件

| 文件 | 作用 |
|------|------|
| `bootstrap.sh` | **全流程一键**（装环境 + 构建镜像 + 开通租户，域名可后配） |
| `base_template.sql` | 清洗后的母模库（仅保留系统数据 + admin） |
| `oneclick_tenant.sh` | 仅开通租户（环境已就绪时） |
| `install_vps.sh` | 新 VPS 初始化（装 Docker 等） |
| `pack_mother.sh` | 母模机打包 |
| `docker-compose.template.yml` | 租户 Compose 模板 |
| `docker/` | 镜像 / Supervisor / Crontab（订单重置 **每天 12:01**） |
| `clean_db.py` / `purge_runtime.sh` | 库清洗与运行时清理 |
| `docs/VPS_DEPLOY.md` | 新机器部署说明 |

## 新 VPS 快速开始

```bash
git clone https://github.com/mimaweimahan/ayang-yijian.git
cd ayang-yijian
# 可选: git checkout skysc-bianyi   # 新 H5 分支

bash bootstrap.sh
# 或: bash bootstrap.sh agent01 'Pass888!' 18001
```

占位域名时自动用 **公网 IP:端口** 访问；有域名再加 `--domain yourdomain.com`。

环境已就绪、只再开租户：

```bash
./oneclick_tenant.sh agent02 'Pass888!'
```

## 说明

- 超管密码算法：纯 `md5(明文)`，无盐（与 `app/common/model/Admin.php` 一致）
- 容器内 `DB_HOST=mysql`（Compose 服务名），不是 `127.0.0.1`
- 域名 / HTTPS 可后配：反代到 `127.0.0.1:分配端口`
- 安全组先放行 **22** 与租户端口（默认 **18001+**）

详见 [docs/VPS_DEPLOY.md](docs/VPS_DEPLOY.md) 与 [docker/README.md](docker/README.md)。
