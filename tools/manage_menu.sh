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
  echo "7) Doctor check (DB + JSON)"
  echo "8) Inspect SQL patch file"
  echo "9) Export one book from DB to SQL"
  echo "10) Quit"
  echo -n "Choose [1-10]: "
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
      echo -n "DB path (default: /Users/kerim/Documents/kdini/kdini/assets/books.db): "
      read -r db_path
      db_path="${db_path:-/Users/kerim/Documents/kdini/kdini/assets/books.db}"
      ./tools/kdini doctor "$db_path"
      ;;
    8)
      echo -n "SQL file path: "
      read -r sql_path
      if [[ -z "${sql_path:-}" ]]; then
        echo "SQL path is required."
        continue
      fi
      ./tools/kdini inspect-sql "$sql_path"
      ;;
    9)
      echo -n "Book ID: "
      read -r book_id
      if [[ -z "${book_id:-}" ]]; then
        echo "Book ID is required."
        continue
      fi
      echo -n "DB path (default: /Users/kerim/Documents/kdini/kdini/assets/books.db): "
      read -r db_path
      db_path="${db_path:-/Users/kerim/Documents/kdini/kdini/assets/books.db}"
      echo -n "Output SQL path (default: kotob/book_<id>.sql): "
      read -r out_sql
      out_sql="${out_sql:-$repo_dir/kotob/book_${book_id}.sql}"
      ./tools/kdini export-sql "$book_id" "$db_path" "$out_sql"
      ;;
    10)
      echo "Bye."
      exit 0
      ;;
    *)
      echo "Invalid choice."
      ;;
  esac
done
