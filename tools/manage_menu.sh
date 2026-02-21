#!/bin/zsh
set -euo pipefail

repo_dir="$(cd "$(dirname "$0")/.." && pwd)"
cd "$repo_dir"

show_menu() {
  echo
  echo "=== KDINI MENU ==="
  echo "1) Status"
  echo "2) Pull (rebase)"
  echo "3) Reorganize assets"
  echo "4) Commit and push"
  echo "5) Open Filament panel (recommended)"
  echo "6) Open legacy Python panel"
  echo "7) Quit"
  echo -n "Choose [1-7]: "
}

while true; do
  show_menu
  read -r choice
  case "$choice" in
    1)
      git status --short -b
      ;;
    2)
      branch="$(git branch --show-current)"
      git pull --rebase origin "$branch"
      ;;
    3)
      ./tools/reorganize_assets.sh
      ;;
    4)
      echo -n "Commit message (empty = auto): "
      read -r msg
      ./tools/git_quick_push.sh "$msg"
      ;;
    5)
      echo -n "Port (default 8890): "
      read -r port
      port="${port:-8890}"
      ./tools/start_filament_panel.sh "$port"
      ;;
    6)
      echo -n "Port (default 8787): "
      read -r port
      port="${port:-8787}"
      ./tools/start_panel.sh "$port"
      ;;
    7)
      echo "Bye."
      exit 0
      ;;
    *)
      echo "Invalid choice."
      ;;
  esac
done
