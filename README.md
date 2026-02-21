# kdini

## Daily workflow (Mac)

### 1) Quick Git push

```bash
cd /Users/kerim/Documents/kdini_manage_clone
./tools/git_quick_push.sh "your commit message"
```

### 2) Reorganize assets + fix SQL links

```bash
cd /Users/kerim/Documents/kdini_manage_clone
./tools/reorganize_assets.sh
```

This script:
- Ensures `json/`, `kotob/`, `update/` exist.
- Moves root metadata files into `json/` and `update/`.
- Moves root `*.sql`, `*.sql.gz`, `*.db` into `kotob/`.
- Updates SQL URLs in `json/books_metadata.json` to `.../main/kotob/...`.

## Real Filament Panel (recommended)

### Start panel

```bash
cd /Users/kerim/Documents/kdini_manage_clone
./tools/start_filament_panel.sh
```

Default URL:
- `http://127.0.0.1:8890/admin/login`

Default login (created automatically if missing):
- Email: `www.kerim317gh@gmail.com`
- Password: `Kerim@2026!`

### What you can manage in Filament

- Books table: `json/books_metadata.json`
- Audio table: `json/content_audio_metadata.json`
- Structure tables (categories + chapters): `json/structure_metadata.json`
- App update form: `update/update.json`
- Git operations (pull, reorganize, commit & push)

## Legacy Python panel (optional)

```bash
cd /Users/kerim/Documents/kdini_manage_clone
./tools/start_panel.sh
```

URL:
- `http://127.0.0.1:8787`

## `kdini` command toolkit

Install once:

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
kdini panel-legacy
kdini menu
```

Desktop launcher:
- `~/Desktop/Kdini-Panel.command`
