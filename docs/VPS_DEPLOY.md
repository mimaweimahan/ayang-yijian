# 新 VPS 一键部署指南（纯 Docker，无宝塔）

## 完整命令（clone 已在脚本内）

```bash
yum upgrade -y && yum install -y git curl && \
curl -fsSL https://raw.githubusercontent.com/mimaweimahan/ayang-yijian/skysc-bianyi/bare_vps_install.sh \
  | bash -s -- agent01 'Pass888!' 18001
```

不要再外层写 `git clone`；`bare_vps_install.sh` 会 clone 到 `/opt/ayang-yijian` 再 `bootstrap.sh`。

## 数据库是否持久？

**是。** MySQL 挂载到宿主机目录：

```text
/var/lib/ayang-mysql/agent01  →  容器 /var/lib/mysql
```

代码目录：

```text
/var/www/tenants/agent01  →  容器 /var/www/html
```

脚本开通时会自动创建上述目录，**不必**再单独做「目录同步库」的额外工具。

注意：`docker compose down -v` 会删命名卷；当前 MySQL 已改为绑定宿主机路径，一般 `down` 不删数据，但删掉 `/var/lib/ayang-mysql/租户` 仍会丢库。

## 再开租户

```bash
cd /opt/ayang-yijian && ./oneclick_tenant.sh agent02 'Pass888!'
```
