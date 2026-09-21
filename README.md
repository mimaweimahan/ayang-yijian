# ayang-yijian — 任务系统 Docker 多租户母模

纯 Docker 一键开通，**不依赖宝塔**。每租户独立：`mysql` + `app` + `worker` + `cron`。

## 全新机器完整命令

`git clone` **已写在** `bare_vps_install.sh` 里，外层不要再手写 clone。

```bash
yum upgrade -y && yum install -y git curl && \
curl -fsSL https://raw.githubusercontent.com/mimaweimahan/ayang-yijian/skysc-bianyi/bare_vps_install.sh \
  | bash -s -- agent01 'Pass888!' 18001
```

双栈建议：

```bash
yum upgrade -y && yum install -y git curl && \
PUBLIC_IP=你的IPv4 curl -fsSL https://raw.githubusercontent.com/mimaweimahan/ayang-yijian/skysc-bianyi/bare_vps_install.sh \
  | bash -s -- agent01 'Pass888!' 18001
```

脚本内顺序：upgrade → 装 git → `git clone -b skysc-bianyi` → `/opt/ayang-yijian` → `bootstrap.sh`。

## 数据持久化（不用自己另建 MySQL 同步逻辑）

| 内容 | 宿主机路径 | 容器内 |
|------|------------|--------|
| **MySQL 数据** | `/var/lib/ayang-mysql/{租户}/` | `/var/lib/mysql` |
| **业务代码** | `/var/www/tenants/{租户}/` | `/var/www/html` |
| **runtime** | Docker 卷 `runtime_{租户}` | `/var/www/html/runtime` |

- 容器重启 / `docker compose down`（**不加** `-v`）→ **库不丢**  
- `docker compose down -v` 或删掉 `/var/lib/ayang-mysql/{租户}` → **库会丢**  
- 备份：打包 `/var/lib/ayang-mysql/agent01` 即可  

开通脚本会自动 `mkdir -p /var/lib/ayang-mysql/{租户}`，无需手工建目录。

## 再开租户

```bash
cd /opt/ayang-yijian
./oneclick_tenant.sh agent02 'Pass888!'
```

## 说明

- 超管：纯 `md5(明文)` 无盐；后台入口见开通输出  
- 无域名用公网 IPv4:端口；域名可后配  
- 安全组放行 **22** 与 **18001+**

详见 [docs/VPS_DEPLOY.md](docs/VPS_DEPLOY.md)。
