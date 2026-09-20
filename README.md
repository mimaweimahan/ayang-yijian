# ayang-yijian — 任务系统 Docker 多租户母模

纯 Docker 一键开通，**不依赖宝塔**。每租户独立：`mysql` + `app` + `worker` + `cron`。

## 全新机器：一条命令（先 upgrade → 装 git → 拉公开库 → 部署）

CentOS / RHEL / Alma / Rocky（yum），公开仓库无需 Token：

```bash
yum upgrade -y && yum install -y git && \
git clone -b skysc-bianyi https://github.com/mimaweimahan/ayang-yijian.git /opt/ayang-yijian && \
cd /opt/ayang-yijian && bash bare_vps_install.sh
```

等价含义：

1. `yum upgrade -y`  
2. `yum install -y git`  
3. `git clone` 公开库分支 `skysc-bianyi`  
4. `bare_vps_install.sh` → `bootstrap.sh`（Docker + 镜像 + 开通租户）

指定租户/密码/端口：

```bash
cd /opt/ayang-yijian && bash bare_vps_install.sh agent01 'Pass888!' 18001
```

Debian / Ubuntu 将前两步换成：

```bash
apt-get update -y && apt-get upgrade -y && apt-get install -y git
```

## 仓库里关键文件

| 文件 | 作用 |
|------|------|
| `bare_vps_install.sh` | **裸机一条龙**（upgrade → git → 拉库 → bootstrap） |
| `bootstrap.sh` | 装 Docker + 构建镜像 + 开通租户（母模已在磁盘时） |
| `base_template.sql` | 清洗后的母模库 |
| `oneclick_tenant.sh` | 仅开通租户 |
| `install_vps.sh` | 仅初始化 Docker/目录 |
| `pack_mother.sh` | 母模机打包 |
| `docs/VPS_DEPLOY.md` | 部署说明 |

## 母模已在磁盘时

```bash
cd /opt/ayang-yijian
bash bootstrap.sh
# 或: bash bootstrap.sh agent01 'Pass888!' 18001
```

再开租户：

```bash
./oneclick_tenant.sh agent02 'Pass888!'
```

## 说明

- 超管密码：纯 `md5(明文)`，无盐  
- 容器内 `DB_HOST=mysql`  
- 无域名时自动用公网 IP:端口；域名可后配  
- 安全组放行 **22** 与租户端口（默认 **18001+**）

详见 [docs/VPS_DEPLOY.md](docs/VPS_DEPLOY.md)。
