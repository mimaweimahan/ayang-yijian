#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
将业务 SQL 备份清洗为租户母模 base_template.sql。

用法:
  python3 clean_db.py
  python3 clean_db.py -i xinayang_xxx.sql.gz -o base_template.sql
"""

from __future__ import annotations

import argparse
import gzip
import io
import re
import sys
from pathlib import Path
from typing import Iterable, List, Optional, TextIO, Tuple

# ---------------------------------------------------------------------------
# 表分类（前缀表名，与 dump 中一致）
# ---------------------------------------------------------------------------

# 业务交易 / 用户数据：只保留 CREATE，丢弃全部 INSERT
CLEAR_TABLES = {
    "mod_user",  # 前台用户（超管在 mod_admin）
    "mod_wallet_log",  # 资金流水
    "mod_order",  # 订单
    "mod_recharge",  # 充值
    "mod_recharge2",  # 充值扩展
    "mod_withdraw",  # 提现
    "mod_graborders",  # 自动派单/任务开关记录
    "mod_users_order_repeat",  # 任务/订单重复记录
    "mod_schedule",  # 用户排单设置
    "mod_bank",  # 用户绑定银行卡/钱包
    "mod_bank_log",  # 绑卡/钱包修改日志
    "mod_admin_log",  # 后台操作日志（含登录痕迹）
    "mod_message",  # 用户消息
    "mod_sign",  # 签到记录
    "mod_day_fill",  # 每日首充统计
    "mod_stat",  # 统计数据
}

# 过滤后只保留指定超管账号
FILTER_ADMIN_TABLE = "mod_admin"
KEEP_ADMIN_USERNAME = "admin"

# 必须保留完整 INSERT 的基础系统表
# 说明：本库无独立「字典 / 省市区 / 权限节点」表；
#       权限节点数据内嵌于 mod_role.rules；系统参数在 mod_configure。
KEEP_TABLES = {
    "mod_configure",  # 默认系统配置
    "mod_role",  # 管理员角色 + 权限节点规则
    "mod_level",  # 等级等系统内置参数（模板基础数据）
    "mod_goods",
    "mod_goods2",
    "mod_goods3",
    "mod_goods_bat",
    "mod_announcement",
    "mod_app_roll",
    "mod_hotsales",
}

CREATE_TABLE_RE = re.compile(r"^CREATE TABLE `([^`]+)`")
INSERT_INTO_RE = re.compile(r"^INSERT INTO `([^`]+)`\s+VALUES\s+", re.IGNORECASE)
DROP_TABLE_RE = re.compile(r"^DROP TABLE IF EXISTS `([^`]+)`")
AUTO_INC_RE = re.compile(r"AUTO_INCREMENT=\d+", re.IGNORECASE)


def open_text(path: Path) -> TextIO:
    if str(path).endswith(".gz"):
        return io.TextIOWrapper(
            gzip.open(path, "rb"), encoding="utf-8", errors="replace", newline=""
        )
    return path.open("r", encoding="utf-8", errors="replace", newline="")


def split_sql_values(values_blob: str) -> List[str]:
    """
    将 INSERT ... VALUES (row1),(row2),...; 拆成单个 '(...)' 行字符串。
    正确处理引号、转义与嵌套括号。
    """
    s = values_blob.strip()
    if s.endswith(";"):
        s = s[:-1].rstrip()

    rows: List[str] = []
    i = 0
    n = len(s)
    while i < n:
        while i < n and s[i] in " \t\r\n,":
            i += 1
        if i >= n:
            break
        if s[i] != "(":
            raise ValueError(f"期望 '(' 开头的 VALUES 行, 得到: {s[i:i+40]!r}")
        start = i
        depth = 0
        in_str = False
        escape = False
        quote = ""
        while i < n:
            ch = s[i]
            if in_str:
                if escape:
                    escape = False
                elif ch == "\\":
                    escape = True
                elif ch == quote:
                    # MySQL 用 '' 表示引号转义
                    if quote == "'" and i + 1 < n and s[i + 1] == "'":
                        i += 2
                        continue
                    in_str = False
            else:
                if ch in ("'", '"'):
                    in_str = True
                    quote = ch
                elif ch == "(":
                    depth += 1
                elif ch == ")":
                    depth -= 1
                    if depth == 0:
                        i += 1
                        rows.append(s[start:i])
                        break
            i += 1
        else:
            raise ValueError("VALUES 括号未闭合")
    return rows


def extract_admin_username(row: str) -> Optional[str]:
    """
    mod_admin 行格式:
      (id, role_id, user_id, 'username', 'password', ...)
    取第 4 个字段（username）。
    """
    assert row.startswith("(") and row.endswith(")")
    inner = row[1:-1]
    fields: List[str] = []
    i = 0
    n = len(inner)
    while i < n:
        while i < n and inner[i] in " \t\r\n":
            i += 1
        if i >= n:
            break
        if inner[i] == "'":
            i += 1
            buf: List[str] = []
            while i < n:
                ch = inner[i]
                if ch == "\\" and i + 1 < n:
                    buf.append(inner[i + 1])
                    i += 2
                    continue
                if ch == "'":
                    if i + 1 < n and inner[i + 1] == "'":
                        buf.append("'")
                        i += 2
                        continue
                    i += 1
                    break
                buf.append(ch)
                i += 1
            fields.append("".join(buf))
        else:
            j = i
            while j < n and inner[j] != ",":
                j += 1
            fields.append(inner[i:j].strip())
            i = j
        if i < n and inner[i] == ",":
            i += 1
        if len(fields) >= 4:
            break
    if len(fields) < 4:
        return None
    return fields[3]


def rewrite_auto_increment(create_sql: str, next_ai: int) -> str:
    if AUTO_INC_RE.search(create_sql):
        return AUTO_INC_RE.sub(f"AUTO_INCREMENT={next_ai}", create_sql)
    # 无 AUTO_INCREMENT=N 时插到 ENGINE 前
    return re.sub(
        r"(\) ENGINE=)",
        f") ENGINE=",
        create_sql,
        count=1,
    )


def patch_create_line(line: str, table: str, next_ai: Optional[int]) -> str:
    if next_ai is None:
        return line
    if "AUTO_INCREMENT=" in line.upper() or line.rstrip().endswith(";"):
        # AUTO_INCREMENT 通常在 CREATE 末尾行
        if AUTO_INC_RE.search(line):
            return AUTO_INC_RE.sub(f"AUTO_INCREMENT={next_ai}", line)
    return line


def process(
    inp: TextIO,
    out: TextIO,
    keep_admin: str = KEEP_ADMIN_USERNAME,
) -> dict:
    stats = {
        "cleared_inserts": 0,
        "kept_inserts": 0,
        "admin_kept": 0,
        "admin_dropped": 0,
        "tables_seen": set(),
    }

    out.write("-- ------------------------------------------------------------\n")
    out.write("-- base_template.sql  (generated by clean_db.py)\n")
    out.write("-- 业务交易数据已清空；保留系统配置 / 角色权限等基础数据。\n")
    out.write(f"-- 后台超管仅保留 username = {keep_admin!r}\n")
    out.write("-- ------------------------------------------------------------\n\n")
    out.write("SET NAMES utf8mb4;\n")
    out.write("SET FOREIGN_KEY_CHECKS = 0;\n\n")

    # 状态机：缓冲 CREATE TABLE 整段，便于改写 AUTO_INCREMENT
    create_buf: List[str] = []
    create_table: Optional[str] = None
    pending_admin_rows: Optional[List[str]] = None

    def flush_create(next_ai: Optional[int] = None) -> None:
        nonlocal create_buf, create_table
        if not create_buf:
            return
        text = "".join(create_buf)
        if next_ai is not None:
            if AUTO_INC_RE.search(text):
                text = AUTO_INC_RE.sub(f"AUTO_INCREMENT={next_ai}", text)
            else:
                text = re.sub(
                    r"(\) ENGINE=)",
                    f") AUTO_INCREMENT={next_ai} ENGINE=",
                    text,
                    count=1,
                    flags=re.IGNORECASE,
                )
        out.write(text)
        create_buf = []
        create_table = None

    for raw in inp:
        # 跳过原 dump 头里的外键/模式设置，统一由脚本控制
        stripped = raw.strip()
        if stripped.startswith("/*!40014 SET @OLD_FOREIGN_KEY_CHECKS") or stripped.startswith(
            "/*!40014 SET FOREIGN_KEY_CHECKS"
        ):
            continue
        if stripped.startswith("SET FOREIGN_KEY_CHECKS"):
            continue
        if stripped in (
            "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;",
            "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;",
            "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;",
            "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;",
            "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;",
            "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;",
            "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;",
            "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;",
        ):
            # 结尾恢复块改由脚本统一写出
            continue

        m_create = CREATE_TABLE_RE.match(raw)
        if m_create:
            flush_create(
                next_ai=1
                if create_table in CLEAR_TABLES
                else (2 if create_table == FILTER_ADMIN_TABLE else None)
            )
            # 注意：CREATE 尚未读完，AUTO_INCREMENT 在末行；先缓存
            create_table = m_create.group(1)
            stats["tables_seen"].add(create_table)
            create_buf = [raw]
            continue

        if create_buf:
            create_buf.append(raw)
            if stripped.endswith(";"):
                # CREATE 结束：按表类型设定 AUTO_INCREMENT
                if create_table in CLEAR_TABLES:
                    flush_create(next_ai=1)
                elif create_table == FILTER_ADMIN_TABLE:
                    # admin id=1 时下一自增为 2；若过滤后为空则仍为 1
                    flush_create(next_ai=2)
                else:
                    flush_create(next_ai=None)
            continue

        m_insert = INSERT_INTO_RE.match(raw)
        if m_insert:
            table = m_insert.group(1)
            stats["tables_seen"].add(table)

            if table in CLEAR_TABLES:
                stats["cleared_inserts"] += 1
                continue

            if table == FILTER_ADMIN_TABLE:
                values_part = raw[m_insert.end() :]
                rows = split_sql_values(values_part)
                kept: List[str] = []
                for row in rows:
                    uname = extract_admin_username(row)
                    if uname == keep_admin:
                        kept.append(row)
                        stats["admin_kept"] += 1
                    else:
                        stats["admin_dropped"] += 1
                if kept:
                    out.write(
                        f"INSERT INTO `{table}` VALUES\n"
                        + ",\n".join(kept)
                        + ";\n"
                    )
                continue

            # KEEP 或未分类表：原样保留
            stats["kept_inserts"] += 1
            out.write(raw)
            continue

        # 其它行（DROP / LOCK / UNLOCK / 注释 / 指令）原样输出
        out.write(raw)

    flush_create()

    out.write("\nSET FOREIGN_KEY_CHECKS = 1;\n")
    return stats


def default_input(root: Path) -> Path:
    candidates = sorted(
        root.glob("xinayang_*_mysql_data.sql.gz"),
        key=lambda p: p.stat().st_mtime,
        reverse=True,
    )
    if candidates:
        return candidates[0]
    plain = sorted(
        list(root.glob("*.sql.gz")) + list(root.glob("*.sql")),
        key=lambda p: p.stat().st_mtime,
        reverse=True,
    )
    plain = [p for p in plain if p.name != "base_template.sql"]
    if not plain:
        raise FileNotFoundError("未找到输入 SQL（*.sql / *.sql.gz）")
    return plain[0]


def main(argv: Optional[Iterable[str]] = None) -> int:
    root = Path(__file__).resolve().parent
    parser = argparse.ArgumentParser(description="清洗业务库 SQL，生成 base_template.sql")
    parser.add_argument(
        "-i",
        "--input",
        type=Path,
        default=None,
        help="输入 dump（.sql 或 .sql.gz），默认取最新 xinayang_*_mysql_data.sql.gz",
    )
    parser.add_argument(
        "-o",
        "--output",
        type=Path,
        default=root / "base_template.sql",
        help="输出文件，默认 ./base_template.sql",
    )
    parser.add_argument(
        "--admin",
        default=KEEP_ADMIN_USERNAME,
        help=f"保留的超级管理员用户名，默认 {KEEP_ADMIN_USERNAME}",
    )
    args = parser.parse_args(list(argv) if argv is not None else None)

    src = args.input or default_input(root)
    dst = args.output

    print(f"[clean_db] input : {src}")
    print(f"[clean_db] output: {dst}")
    print(f"[clean_db] clear tables ({len(CLEAR_TABLES)}): {', '.join(sorted(CLEAR_TABLES))}")
    print(f"[clean_db] keep  tables ({len(KEEP_TABLES)}): {', '.join(sorted(KEEP_TABLES))}")
    print(f"[clean_db] admin keep username = {args.admin!r}")

    with open_text(src) as inp, dst.open("w", encoding="utf-8", newline="\n") as out:
        stats = process(inp, out, keep_admin=args.admin)

    print("[clean_db] done.")
    print(f"  tables seen      : {len(stats['tables_seen'])}")
    print(f"  cleared inserts  : {stats['cleared_inserts']}")
    print(f"  kept inserts     : {stats['kept_inserts']}")
    print(f"  admin kept/drop  : {stats['admin_kept']}/{stats['admin_dropped']}")
    print(f"  output size      : {dst.stat().st_size / (1024 * 1024):.2f} MB")

    unknown = stats["tables_seen"] - CLEAR_TABLES - KEEP_TABLES - {FILTER_ADMIN_TABLE}
    if unknown:
        print(
            f"  [warn] 未显式分类、已原样保留 INSERT 的表: {', '.join(sorted(unknown))}"
        )
    if stats["admin_kept"] != 1:
        print(
            f"  [warn] 期望保留 1 个 admin，实际保留 {stats['admin_kept']} 个，请检查源数据",
            file=sys.stderr,
        )
        return 2
    return 0


if __name__ == "__main__":
    sys.exit(main())
