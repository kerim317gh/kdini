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
kdini doctor
kdini inspect-sql /path/to/book.sql
kdini export-sql 3
kdini panel
kdini panel-legacy
kdini menu
```

### Doctor check (recommended before push)

```bash
cd /Users/kerim/Documents/kdini_manage_clone
kdini doctor
```

Checks:
- JSON duplicates / invalid IDs
- DB vs metadata mismatches
- bookless `content` rows
- invalid audio references

### Inspect SQL patch file

```bash
cd /Users/kerim/Documents/kdini_manage_clone
kdini inspect-sql /Users/kerim/Desktop/BookProject/listkotob/hedaye2/hedaye_vs_fa.sql
```

Shows:
- number of `INSERT INTO content`
- presence of `DELETE FROM content`
- target `kotob_id` values
- transaction markers (`BEGIN/COMMIT`)

### Export local-only books from DB to SQL

If a book exists only in local DB and not as SQL file on GitHub:

```bash
cd /Users/kerim/Documents/kdini_manage_clone
kdini export-sql <book_id>
```

Generated files:
- `kotob/book_<book_id>.sql` (ready-to-commit SQL patch)
- `kotob/book_<book_id>.book.json` (metadata snippet for `json/books_metadata.json`)

Desktop launcher:
- `~/Desktop/Kdini-Panel.command`
