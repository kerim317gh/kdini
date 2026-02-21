#!/bin/zsh
set -euo pipefail

repo_dir="$(cd "$(dirname "$0")/.." && pwd)"
bin_dir="$HOME/bin"
zshrc="$HOME/.zshrc"

mkdir -p "$bin_dir"
chmod +x "$repo_dir/tools/kdini"
chmod +x "$repo_dir/tools/manage_menu.sh"
chmod +x "$repo_dir/tools/start_filament_panel.sh"
ln -sf "$repo_dir/tools/kdini" "$bin_dir/kdini"

path_line='export PATH="$HOME/bin:$PATH"'
if [[ -f "$zshrc" ]]; then
  if ! grep -qxF "$path_line" "$zshrc" >/dev/null 2>&1; then
    echo "$path_line" >> "$zshrc"
  fi
else
  echo "$path_line" > "$zshrc"
fi

if [[ -d "$HOME/Desktop" ]]; then
  panel_cmd="$HOME/Desktop/Kdini-Panel.command"
  cat > "$panel_cmd" <<EOF
#!/bin/zsh
cd "$repo_dir"
./tools/start_filament_panel.sh
EOF
  chmod +x "$panel_cmd"
fi

cat <<'EOF'
Shortcut installation complete.

Next:
1) Run: source ~/.zshrc
2) Use:
   kdini status
   kdini pull
   kdini push "your message"
   kdini reorganize
   kdini panel
   kdini panel-legacy
   kdini menu
EOF
