#!/usr/bin/env python3
"""渲染 docker-compose.template.yml"""
import argparse, re, sys
from pathlib import Path

REQUIRED = [
    "TENANT_ID", "DB_NAME", "PORT", "DB_USER", "DB_PASS",
    "MYSQL_ROOT_PASSWORD", "BUILD_CONTEXT", "DOCKER_DIR", "TENANT_CODE_DIR",
]
OPTIONAL_DEFAULTS = {
    "DB_HOST": "mysql",
    "DB_PORT": "3306",
    "DB_PREFIX": "mod_",
    "APP_URL": "",
    "TZ": "Asia/Shanghai",
    "IMAGE_TAG": "tenant-app:php74",
    "WORKER_SLEEP": "30",
}

def load_env_file(path: Path) -> dict:
    data = {}
    if not path.exists():
        return data
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        data[k.strip()] = v.strip().strip("'").strip('"')
    return data

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("-e", "--env-file", type=Path, required=True)
    ap.add_argument("-t", "--template", type=Path, default=Path("docker-compose.template.yml"))
    ap.add_argument("-o", "--output", type=Path, default=Path("docker-compose.yml"))
    ap.add_argument("--set", action="append", default=[])
    args = ap.parse_args()

    vals = dict(OPTIONAL_DEFAULTS)
    vals.update(load_env_file(args.env_file))
    for item in args.set:
        k, _, v = item.partition("=")
        vals[k] = v
    if not vals.get("APP_URL"):
        vals["APP_URL"] = f"http://{vals.get('TENANT_ID', 'tenant')}.example.com"

    missing = [k for k in REQUIRED if not vals.get(k)]
    if missing:
        sys.exit("缺少变量: " + ", ".join(missing))

    text = args.template.read_text(encoding="utf-8")
    for k in sorted(vals.keys(), key=len, reverse=True):
        text = text.replace("{{" + k + "}}", str(vals[k]))
    left = sorted(set(re.findall(r"\{\{[A-Z0-9_]+\}\}", text)))
    if left:
        sys.exit("未替换占位符: " + ", ".join(left))
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(text, encoding="utf-8")
    print(f"OK -> {args.output}")

if __name__ == "__main__":
    main()
