#!/bin/zsh
set -euo pipefail

repo_dir="$(cd "$(dirname "$0")/.." && pwd)"
cd "$repo_dir"

port="${1:-8787}"
url="http://127.0.0.1:${port}"

echo "Starting panel at ${url}"
if command -v open >/dev/null 2>&1; then
  open "$url" >/dev/null 2>&1 || true
fi

python3 tools/control_panel.py --host 127.0.0.1 --port "$port"
