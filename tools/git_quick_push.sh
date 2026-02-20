#!/bin/zsh
set -euo pipefail

repo_dir="$(cd "$(dirname "$0")/.." && pwd)"
cd "$repo_dir"

message="${1:-}"
if [[ -z "$message" ]]; then
  message="Update $(date '+%Y-%m-%d %H:%M')"
fi

git add -A

if git diff --cached --quiet; then
  echo "No changes to commit."
  exit 0
fi

git commit -m "$message"
branch="$(git branch --show-current)"
git push origin "$branch"

echo "Done: pushed to origin/$branch"
