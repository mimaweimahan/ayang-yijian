# 新 VPS 一键部署指南（纯 Docker，无宝塔）

## 推荐：一条命令跑通（域名可后配）

母模已在 VPS 目录后（git clone 或解压 tar）：

```bash
cd /opt/ayang-yijian   # 或你的母模目录
bash bootstrap.sh
# 等价: bash bootstrap.sh agent01 'Pass888!'
# 指定端口: bash bootstrap.sh agent01 'Pass888!' 18001
# 已有域名: bash bootstrap.sh agent01 'Pass888!' 18001 --domain yourdomain.com
```

`bootstrap.sh` 会依次：

1. `install_vps.sh` — 装 Docker/依赖、写配置、构建 `tenant-app:php74`（nginx PATHINFO + H5 `/static` 已打进镜像）  
2. `oneclick_tenant.sh` — 开通租户；`BASE_DOMAIN` 仍为占位时自动用 **公网 IP:端口** 写 `APP_URL` / `FRONTEND_INVITE_BASE`

开通后访问：`http://公网IP:18001/` ，后台：`/ayangshuadan222.php`（账号 `admin` / 你设的密码）。

仅再开租户（环境已就绪）：

```bash
./oneclick_tenant.sh agent02 'Pass888!'
```

## 和「本机已有母模」的差别

| 本机（已有代码） | 新 VPS |
|------------------|--------|
| Docker 可能已装 | 要先装 Docker |
| 母模已在磁盘 | 要先把母模包传上去或 git clone |
| 可直接 `oneclick_tenant.sh` | 推荐 `bash bootstrap.sh` 一次搞定 |

## 分步流程（可选）

### A. 在母模机打包

```bash
cd /path/to/mother   # 含 base_template.sql 的目录
./pack_mother.sh
# 得到 mother_template_YYYYMMDD_HHMM.tar.gz
```

### B. 传到新 VPS 并解压

```bash
scp mother_template_*.tar.gz root@新VPS_IP:/opt/
ssh root@新VPS_IP
mkdir -p /opt/mother && tar -xzf /opt/mother_template_*.tar.gz -C /opt/mother --strip-components=1
cd /opt/mother
bash bootstrap.sh agent01 'Pass888!'
```

### C. DNS 与入口（可后做）

1. 解析：`*.yourdomain.com` → VPS 公网 IP  
2. 安全组放行：**22** 与租户端口（默认 **18001+**）；上反代后再开 80/443  
3. 改 `deploy/oneclick_tenant.conf` 的 `BASE_DOMAIN`，并更新租户 `.env` 的 `APP_URL` / `FRONTEND_INVITE_BASE`  
4. Caddy / Traefik / Nginx 反代到 `127.0.0.1:分配端口`  

## 清单：新 VPS 还缺什么就补什么

- [ ] 母模包里有 `base_template.sql`（没有先在母模机 `python3 clean_db.py`）  
- [ ] 母模包里有 `vendor/`（或接受首次业务缺依赖；建议打包时带上）  
- [ ] Docker 能拉镜像（访问 Docker Hub；国内可配镜像加速）  
- [ ] 磁盘建议 ≥ 40G（镜像 + 每租户 MySQL 卷）  
- [ ] 改过 `docker/nginx` 或 `Dockerfile` 后，删指纹或显式重建：`docker build -t tenant-app:php74 -f docker/Dockerfile .`  

母版已固化：后台超管 `Index` 空邀请码保护、nginx PATHINFO、H5 `/static` 映射、开通时 `.env` chown UID33、合部署清外域 API 基址。

## 常用命令

```bash
# 再开租户
./oneclick_tenant.sh agent02 'Pass888!'

# 看栈
docker compose -f instances/agent01/docker-compose.yml ps
docker compose -f instances/agent01/docker-compose.yml logs -f
```
