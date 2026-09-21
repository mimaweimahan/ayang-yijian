# 新 VPS 一键部署指南（纯 Docker，无宝塔）

## 全新机器：一条命令（推荐）

```bash
curl -fsSL https://raw.githubusercontent.com/mimaweimahan/ayang-yijian/skysc-bianyi/bare_vps_install.sh | bash
```

公开仓库，无需 Token。脚本内顺序：`yum upgrade` → 装 git → clone → `bootstrap.sh`。

指定参数：

```bash
curl -fsSL https://raw.githubusercontent.com/mimaweimahan/ayang-yijian/skysc-bianyi/bare_vps_install.sh | bash -s -- agent01 'Pass888!' 18001
```

### 等价分步（必须一口气跑完，勿只 clone）

```bash
yum upgrade -y && yum install -y git && \
git clone -b skysc-bianyi https://github.com/mimaweimahan/ayang-yijian.git /opt/ayang-yijian && \
cd /opt/ayang-yijian && bash bootstrap.sh
```

## 母模已在目录时

```bash
cd /opt/ayang-yijian && bash bootstrap.sh
```

再开租户：`./oneclick_tenant.sh agent02 'Pass888!'`

## DNS（可后做）

安全组放行 **22** + **18001+**；域名后配再反代。

## 常用命令

```bash
./oneclick_tenant.sh agent02 'Pass888!'
docker compose -f instances/agent01/docker-compose.yml ps
```
