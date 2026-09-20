# 新 VPS 一键部署指南（纯 Docker，无宝塔）

## 和「本机已有母模」的差别

| 本机（已有代码） | 新 VPS |
|------------------|--------|
| Docker 可能已装 | 要先装 Docker |
| 母模已在磁盘 | 要先把母模包传上去 |
| 可直接 `oneclick_tenant.sh` | 先 `install_vps.sh`，再开通租户 |

## 标准流程（推荐）

### A. 在母模机打包

```bash
cd /path/to/mother   # 含 base_template.sql 的目录
./pack_mother.sh
# 得到 mother_template_YYYYMMDD_HHMM.tar.gz
```

### B. 传到新 VPS 并初始化

```bash
scp mother_template_*.tar.gz root@新VPS_IP:/opt/
ssh root@新VPS_IP

mkdir -p /opt/mother
tar -xzf /opt/mother_template_*.tar.gz -C /opt/mother --strip-components=1
cd /opt/mother

bash install_vps.sh --domain yourdomain.com --build-image
```

`install_vps.sh` 会：

1. 安装 python3 / rsync / curl 等  
2. 安装 Docker + Compose  
3. 创建 `/var/www/tenants`、`deploy/oneclick_tenant.conf`  
4. （可选）预构建 `tenant-app:php74` 镜像  

### C. 开通第一个租户

```bash
./oneclick_tenant.sh agent01 'Pass888!'
```

### D. DNS 与入口

1. 解析：`*.yourdomain.com` → VPS 公网 IP  
2. 安全组放行：**22 / 80 / 443**（租户 18001+ 可只对本机，由入口反代）  
3. 用 Caddy / Traefik / Nginx 把域名反代到 `127.0.0.1:分配端口`  

## 清单：新 VPS 还缺什么就补什么

- [ ] 母模包里有 `base_template.sql`（没有先在母模机 `python3 clean_db.py`）  
- [ ] 母模包里有 `vendor/`（或接受首次业务缺依赖；建议打包时带上）  
- [ ] `deploy/oneclick_tenant.conf` 里 `BASE_DOMAIN` 已改  
- [ ] Docker 能拉镜像（访问 Docker Hub；国内可配镜像加速）  
- [ ] 磁盘建议 ≥ 40G（镜像 + 每租户 MySQL 卷）  

## 常用命令

```bash
# 再开租户
./oneclick_tenant.sh agent02 'Pass888!'

# 看栈
docker compose -f instances/agent01/docker-compose.yml ps
docker compose -f instances/agent01/docker-compose.yml logs -f
```
