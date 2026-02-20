# kdini

## Local Git Workflow (Mac)

This repo now includes local tools so you can manage everything from your Mac without GitHub web editing.

### 1) One-command commit + push

```bash
cd /Users/kerim/Documents/kdini_manage_clone
./tools/git_quick_push.sh "your commit message"
```

If you do not pass a message, the script creates a timestamp message automatically.

### 2) Reorganize assets automatically

```bash
cd /Users/kerim/Documents/kdini_manage_clone
./tools/reorganize_assets.sh
```

This script:
- Ensures `json/`, `kotob/`, `update/` exist.
- Moves root JSON metadata files into `json/` and `update/`.
- Moves `*.sql`, `*.sql.gz`, `*.db` from root into `kotob/`.
- Updates SQL URLs in `json/books_metadata.json` from:
  - `.../main/file.sql`
  - to `.../main/kotob/file.sql`

### 3) Offline web control panel

```bash
cd /Users/kerim/Documents/kdini_manage_clone
./tools/start_panel.sh
```

Panel URL:
- `http://127.0.0.1:8787`

From the panel you can:
- Edit key files directly.
- Run Pull (rebase).
- Run Reorganize Assets.
- Run Commit & Push with one button.

### 4) One command toolkit (`kdini`)

Install shortcut once:

```bash
cd /Users/kerim/Documents/kdini_manage_clone
./tools/install_shortcuts.sh
source ~/.zshrc
```

Then use:

```bash
kdini status
kdini pull
kdini reorganize
kdini push "your message"
kdini panel
kdini menu
```

Also, a desktop launcher is created:
- `~/Desktop/Kdini-Panel.command`
