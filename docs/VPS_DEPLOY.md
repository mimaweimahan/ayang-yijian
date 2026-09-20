# 新 VPS 一键部署指南（纯 Docker，无宝塔）

## 全新机器：一条命令

顺序固定：**yum upgrade → 装 git → clone → bare_vps_install**（域名可后配）。

```bash
yum upgrade -y && yum install -y git && \
git clone -b skysc-bianyi https://github.com/mimaweimahan/ayang-yijian.git /opt/ayang-yijian && \
cd /opt/ayang-yijian && bash bare_vps_install.sh
```

公开仓库，无需 Token。`bare_vps_install.sh` 会再跑：系统升级 → 确保 git → 拉/更新代码 → `bootstrap.sh`（Docker + 镜像 + 开通 `agent01`）。

---

## 母模已在目录时

```bash
cd /opt/ayang-yijian
bash bootstrap.sh
# bash bootstrap.sh agent01 'Pass888!' 18001
# bash bootstrap.sh agent01 'Pass888!' 18001 --domain yourdomain.com
```

仅再开租户：

```bash
./oneclick_tenant.sh agent02 'Pass888!'
```

## DNS（可后做）

1. `*.yourdomain.com` → 公网 IP  
2. 安全组：**22** + 租户端口 **18001+**  
3. 反代到 `127.0.0.1:分配端口`，并改 `.env` 的 `APP_URL` / `FRONTEND_INVITE_BASE`

## 清单

- [ ] 仓库已设为 **Public**（或至少可匿名 clone）  
- [ ] 母模含 `base_template.sql` / `vendor`（建议）  
- [ ] 磁盘 ≥ 40G  

## 常用命令

```bash
./oneclick_tenant.sh agent02 'Pass888!'
docker compose -f instances/agent01/docker-compose.yml ps
docker compose -f instances/agent01/docker-compose.yml logs -f
```
