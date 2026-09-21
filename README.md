# ayang-yijian — 任务系统 Docker 多租户母模

纯 Docker 一键开通，**不依赖宝塔**。每租户独立：`mysql` + `app` + `worker` + `cron`。

## 全新机器：一条命令（推荐）

公开仓库，root 执行即可（顺序：upgrade → 装 git → clone → Docker/镜像/开租户）：

```bash
curl -fsSL https://raw.githubusercontent.com/mimaweimahan/ayang-yijian/skysc-bianyi/bare_vps_install.sh | bash
```

指定租户/密码/端口：

```bash
curl -fsSL https://raw.githubusercontent.com/mimaweimahan/ayang-yijian/skysc-bianyi/bare_vps_install.sh | bash -s -- agent01 'Pass888!' 18001
```

> 不要只跑到 `git clone` 就停。必须把管道/`bare_vps_install.sh` 跑完。

### 等价分步（须连续执行完）

```bash
yum upgrade -y && yum install -y git && \
git clone -b skysc-bianyi https://github.com/mimaweimahan/ayang-yijian.git /opt/ayang-yijian && \
cd /opt/ayang-yijian && bash bootstrap.sh
```

说明：clone 之后应直接 `bootstrap.sh`（升级和 git 已在前面做过）。若再跑 `bare_vps_install.sh` 会重复 upgrade，但也能跑通。

## 仓库里关键文件

| 文件 | 作用 |
|------|------|
| `bare_vps_install.sh` | **裸机一条龙**（upgrade → git → 拉库 → bootstrap） |
| `bootstrap.sh` | 装 Docker + 构建镜像 + 开通租户 |
| `oneclick_tenant.sh` | 仅开通租户 |
| `install_vps.sh` | 仅初始化 Docker/目录 |
| `docs/VPS_DEPLOY.md` | 部署说明 |

## 母模已在磁盘时

```bash
cd /opt/ayang-yijian
bash bootstrap.sh
```

再开租户：`./oneclick_tenant.sh agent02 'Pass888!'`

## 说明

- 超管密码：纯 `md5(明文)`，无盐  
- 无域名时自动用公网 IP:端口  
- 安全组放行 **22** 与 **18001+**

详见 [docs/VPS_DEPLOY.md](docs/VPS_DEPLOY.md)。
