# 数据库备份说明

- 库名：`xinayang`
- 备份时间：2026-09-07
- 完整包：`backup/xinayang_20260907_210930.sql.gz`（约 161MB，超过 GitHub 100MB 限制，仓库内以分卷提交）

## 还原分卷

```bash
cd database/backup
cat xinayang_20260907_210930.sql.gz.part* > xinayang_20260907_210930.sql.gz
gzip -t xinayang_20260907_210930.sql.gz
zcat xinayang_20260907_210930.sql.gz | mysql -u用户 -p 库名
```

## 注意

请勿将含生产用户/资金数据的备份推送到 **Public** 仓库。建议仓库设为 Private。
