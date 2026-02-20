#!/usr/bin/env python3
from __future__ import annotations

import argparse
import html
import json
import re
import subprocess
from http import HTTPStatus
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlparse

REPO_DIR = Path(__file__).resolve().parent.parent
BOOKS_JSON_REL = "json/books_metadata.json"
AUDIO_JSON_REL = "json/content_audio_metadata.json"
STRUCTURE_JSON_REL = "json/structure_metadata.json"
UPDATE_JSON_REL = "update/update.json"
DEFAULT_EDIT_FILES = [
    BOOKS_JSON_REL,
    AUDIO_JSON_REL,
    STRUCTURE_JSON_REL,
    UPDATE_JSON_REL,
    "README.md",
]
MAX_EDIT_SIZE = 2_000_000
URL_KEYS = ("sql_download_url", "download_url", "url")
RAW_SQL_PATTERN = re.compile(
    r"(https://raw\.githubusercontent\.com/kerim317gh/kdini/refs/heads/main/)(?!kotob/)([^\"\s]+\.(?:sql|sql\.gz|db))"
)


def run_cmd(cmd: list[str], timeout: int = 180) -> tuple[int, str]:
    try:
        proc = subprocess.run(
            cmd,
            cwd=REPO_DIR,
            text=True,
            capture_output=True,
            timeout=timeout,
            check=False,
        )
    except subprocess.TimeoutExpired:
        return 124, f"زمان اجرای دستور تمام شد: {' '.join(cmd)}"

    output = ""
    if proc.stdout:
        output += proc.stdout
    if proc.stderr:
        output += proc.stderr
    return proc.returncode, output.strip()


def resolve_repo_path(rel_path: str) -> Path:
    rel_path = rel_path.strip().replace("\\", "/")
    if not rel_path:
        raise ValueError("مسیر فایل خالی است.")

    full_path = (REPO_DIR / rel_path).resolve()
    repo_root = REPO_DIR.resolve()
    if full_path != repo_root and repo_root not in full_path.parents:
        raise ValueError("فایل باید داخل ریپو باشد.")
    return full_path


def read_json_file(rel_path: str) -> object:
    path = resolve_repo_path(rel_path)
    return json.loads(path.read_text(encoding="utf-8"))


def write_json_file(rel_path: str, data: object) -> None:
    path = resolve_repo_path(rel_path)
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def to_int_or_keep(raw: str, allow_none: bool = False) -> object:
    value = raw.strip()
    if value == "":
        return None if allow_none else ""
    try:
        return int(value)
    except ValueError:
        return value


def to_bool(raw: str) -> bool:
    return raw.strip().lower() in {"1", "true", "yes", "on"}


def get_first_url(book: dict) -> str:
    for key in URL_KEYS:
        val = book.get(key)
        if isinstance(val, str) and val.strip():
            return val.strip()
    return ""


def shorten(text: str, max_len: int = 70) -> str:
    if len(text) <= max_len:
        return text
    return text[: max_len - 1] + "…"


def normalize_books_urls() -> tuple[int, str]:
    data = read_json_file(BOOKS_JSON_REL)
    if not isinstance(data, list):
        raise ValueError("ساختار books_metadata.json باید آرایه باشد.")

    changes = 0
    for row in data:
        if not isinstance(row, dict):
            continue
        for key in URL_KEYS:
            val = row.get(key)
            if not isinstance(val, str):
                continue
            new_val = RAW_SQL_PATTERN.sub(r"\1kotob/\2", val)
            if new_val != val:
                row[key] = new_val
                changes += 1

    if changes > 0:
        write_json_file(BOOKS_JSON_REL, data)
    return changes, "اصلاح لینک‌ها انجام شد."


def commit_and_push(message: str) -> tuple[bool, str]:
    logs: list[str] = []

    code, out = run_cmd(["git", "add", "-A"])
    logs.append("$ git add -A")
    if out:
        logs.append(out)
    if code != 0:
        return False, "\n".join(logs)

    diff = subprocess.run(["git", "diff", "--cached", "--quiet"], cwd=REPO_DIR, check=False)
    if diff.returncode == 0:
        logs.append("تغییری برای کامیت وجود ندارد.")
        return True, "\n".join(logs)
    if diff.returncode != 1:
        logs.append("بررسی تغییرات stage شده با خطا روبه‌رو شد.")
        return False, "\n".join(logs)

    code, out = run_cmd(["git", "commit", "-m", message])
    logs.append(f"$ git commit -m {message!r}")
    if out:
        logs.append(out)
    if code != 0:
        return False, "\n".join(logs)

    code, branch = run_cmd(["git", "branch", "--show-current"])
    branch = branch.strip() if code == 0 else "main"

    code, out = run_cmd(["git", "push", "origin", branch])
    logs.append(f"$ git push origin {branch}")
    if out:
        logs.append(out)
    return code == 0, "\n".join(logs)


def run_reorganize() -> tuple[bool, str]:
    script = REPO_DIR / "tools" / "reorganize_assets.sh"
    if not script.exists():
        return False, f"اسکریپت پیدا نشد: {script}"

    code, out = run_cmd(["zsh", str(script)])
    logs = [f"$ zsh {script.relative_to(REPO_DIR)}"]
    if out:
        logs.append(out)
    return code == 0, "\n".join(logs)


class PanelHandler(BaseHTTPRequestHandler):
    def _send_html(self, body: str, status: int = HTTPStatus.OK) -> None:
        payload = body.encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.send_header("Content-Length", str(len(payload)))
        self.end_headers()
        self.wfile.write(payload)

    def _parse_post(self) -> dict[str, list[str]]:
        length = int(self.headers.get("Content-Length", "0"))
        raw = self.rfile.read(length).decode("utf-8")
        return parse_qs(raw, keep_blank_values=True)

    def _load_array(self, rel_path: str, label: str) -> list[dict]:
        data = read_json_file(rel_path)
        if not isinstance(data, list):
            raise ValueError(f"ساختار فایل {label} باید آرایه باشد.")
        return [row for row in data if isinstance(row, dict)]

    def _load_books(self) -> list[dict]:
        return self._load_array(BOOKS_JSON_REL, "books_metadata")

    def _load_audio(self) -> list[dict]:
        return self._load_array(AUDIO_JSON_REL, "content_audio_metadata")

    def _load_structure(self) -> dict:
        data = read_json_file(STRUCTURE_JSON_REL)
        if not isinstance(data, dict):
            raise ValueError("ساختار structure_metadata.json باید آبجکت باشد.")
        if not isinstance(data.get("categories", []), list):
            raise ValueError("کلید categories باید آرایه باشد.")
        if not isinstance(data.get("chapters", []), list):
            raise ValueError("کلید chapters باید آرایه باشد.")
        return data

    def _base_layout(
        self,
        *,
        title: str,
        content: str,
        notice: str = "",
        cmd_output: str = "",
    ) -> str:
        notice_block = f"<div class='notice'>{html.escape(notice)}</div>" if notice else ""
        output_block = (
            "<div class='card'><h3>خروجی آخرین عملیات</h3>"
            f"<pre class='cli'>{html.escape(cmd_output)}</pre></div>"
            if cmd_output
            else ""
        )

        return f"""<!doctype html>
<html lang=\"fa\" dir=\"rtl\">
<head>
  <meta charset=\"utf-8\">
  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
  <title>{html.escape(title)}</title>
  <style>
    :root {{
      --bg: #f3f4f6;
      --panel: #ffffff;
      --text: #111827;
      --muted: #6b7280;
      --border: #e5e7eb;
      --primary: #f59e0b;
      --primary-hover: #d97706;
      --secondary: #0f172a;
      --accent: #14b8a6;
      --notice-bg: #fffbeb;
      --notice-border: #fcd34d;
      --shadow: 0 14px 30px -22px rgba(15, 23, 42, 0.35);
    }}
    * {{ box-sizing: border-box; }}
    body {{
      margin: 0;
      color: var(--text);
      background:
        radial-gradient(circle at 90% 0%, #fff7e6 0%, transparent 38%),
        radial-gradient(circle at 10% 100%, #e6fffb 0%, transparent 28%),
        var(--bg);
      font-family: \"Vazirmatn\", \"IRANSans\", Tahoma, \"Segoe UI\", sans-serif;
      line-height: 1.6;
    }}
    .container {{ max-width: 1250px; margin: 0 auto; padding: 22px; }}
    .topbar {{
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 16px;
    }}
    .brand h1 {{ margin: 0; font-size: 24px; }}
    .brand p {{ margin: 4px 0 0; color: var(--muted); font-size: 13px; }}
    .nav {{ display: flex; gap: 8px; flex-wrap: wrap; }}
    .nav a {{
      color: var(--secondary);
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 10px;
      text-decoration: none;
      font-size: 13px;
      padding: 7px 10px;
      font-weight: 700;
    }}
    .nav a:hover {{ border-color: var(--primary); color: var(--primary-hover); }}
    .notice {{
      background: var(--notice-bg);
      border: 1px solid var(--notice-border);
      border-radius: 10px;
      padding: 10px 12px;
      margin-bottom: 14px;
      font-size: 14px;
    }}
    .grid {{
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 12px;
      margin-bottom: 14px;
    }}
    .card {{
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: 14px;
      box-shadow: var(--shadow);
      padding: 14px;
      margin-bottom: 14px;
    }}
    .card h2, .card h3 {{ margin: 0 0 10px; }}
    .muted {{ color: var(--muted); font-size: 13px; }}
    .toolbar {{
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
      margin-bottom: 12px;
    }}
    form.inline {{ display: inline-flex; gap: 8px; align-items: center; flex-wrap: wrap; }}
    input[type=text], input[type=number], input[type=datetime-local], textarea {{
      width: 100%;
      border: 1px solid #d1d5db;
      border-radius: 10px;
      padding: 9px 10px;
      font-size: 14px;
      background: #fff;
      color: var(--text);
    }}
    textarea {{ min-height: 120px; resize: vertical; }}
    .field {{ margin-bottom: 12px; }}
    .field label {{ display: block; margin-bottom: 6px; font-weight: 700; font-size: 13px; }}
    .btn {{
      border: 1px solid transparent;
      border-radius: 10px;
      padding: 8px 12px;
      cursor: pointer;
      font-size: 13px;
      font-weight: 800;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 36px;
    }}
    .btn.primary {{ background: var(--primary); color: #fff; }}
    .btn.primary:hover {{ background: var(--primary-hover); }}
    .btn.dark {{ background: var(--secondary); color: #fff; }}
    .btn.dark:hover {{ opacity: 0.92; }}
    .btn.ghost {{ background: #fff; border-color: var(--border); color: var(--secondary); }}
    .btn.ghost:hover {{ border-color: var(--primary); color: var(--primary-hover); }}
    .btn.teal {{ background: var(--accent); color: #fff; }}
    .btn.teal:hover {{ opacity: 0.92; }}
    .two-col {{
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 12px;
    }}
    .table-wrap {{ overflow-x: auto; border: 1px solid var(--border); border-radius: 12px; }}
    table {{ width: 100%; border-collapse: collapse; min-width: 860px; background: #fff; }}
    th, td {{ border-bottom: 1px solid var(--border); padding: 10px; font-size: 13px; text-align: right; vertical-align: top; }}
    th {{ background: #fafafa; font-weight: 900; white-space: nowrap; }}
    tr:nth-child(even) td {{ background: #fcfcfd; }}
    .empty {{ padding: 14px; color: var(--muted); font-size: 14px; }}
    .pill {{
      display: inline-block;
      padding: 2px 8px;
      border-radius: 999px;
      background: #eff6ff;
      color: #1e40af;
      font-size: 12px;
      font-weight: 700;
      white-space: nowrap;
    }}
    .kpi {{ font-size: 24px; font-weight: 900; margin: 0; }}
    .kpi-label {{ font-size: 12px; color: var(--muted); margin-top: 4px; }}
    pre.cli {{
      direction: ltr;
      text-align: left;
      background: #0b1220;
      color: #dbeafe;
      border-radius: 10px;
      padding: 12px;
      overflow-x: auto;
      white-space: pre-wrap;
      word-break: break-word;
      margin: 0;
      font-size: 12px;
    }}
    code.mono {{ direction: ltr; unicode-bidi: plaintext; }}
    .checkbox {{ display: inline-flex; gap: 8px; align-items: center; font-weight: 700; }}
    @media (max-width: 720px) {{
      .container {{ padding: 14px; }}
      .brand h1 {{ font-size: 20px; }}
      .nav a {{ font-size: 12px; padding: 7px 10px; }}
    }}
  </style>
</head>
<body>
  <div class=\"container\">
    <div class=\"topbar\">
      <div class=\"brand\">
        <h1>کنترل پنل آفلاین KDINI</h1>
        <p>مدیریت محلی فایل‌ها، متادیتا و Git بدون نیاز به ویرایش سخت در GitHub</p>
      </div>
      <div class=\"nav\">
        <a href=\"/\">داشبورد</a>
        <a href=\"/books\">کتاب‌ها</a>
        <a href=\"/audio\">فایل‌های صوتی</a>
        <a href=\"/structure?section=categories\">ساختار (دسته‌ها)</a>
        <a href=\"/structure?section=chapters\">ساختار (فصل‌ها)</a>
        <a href=\"/app-update\">آپدیت برنامه</a>
      </div>
    </div>
    {notice_block}
    {content}
    {output_block}
  </div>
</body>
</html>"""

    def _render_dashboard(self, notice: str = "", cmd_output: str = "") -> str:
        _, status_out = run_cmd(["git", "status", "--short", "-b"])
        _, commits_out = run_cmd(["git", "log", "--oneline", "-n", "6"])
        _, remote_out = run_cmd(["git", "remote", "-v"])

        books_count = "-"
        audio_count = "-"
        cats_count = "-"
        chaps_count = "-"
        app_version = "-"

        try:
            books_count = str(len(self._load_books()))
        except Exception:
            pass
        try:
            audio_count = str(len(self._load_audio()))
        except Exception:
            pass
        try:
            structure = self._load_structure()
            cats_count = str(len([x for x in structure.get("categories", []) if isinstance(x, dict)]))
            chaps_count = str(len([x for x in structure.get("chapters", []) if isinstance(x, dict)]))
        except Exception:
            pass
        try:
            app_update = read_json_file(UPDATE_JSON_REL)
            if isinstance(app_update, dict):
                app_version = str(app_update.get("version", "-"))
        except Exception:
            pass

        file_links = "".join(
            (
                "<tr>"
                f"<td><code class='mono'>{html.escape(path)}</code></td>"
                f"<td><a class='btn ghost' href='/edit?file={html.escape(path)}'>ویرایش خام</a></td>"
                "</tr>"
            )
            for path in DEFAULT_EDIT_FILES
        )

        content = f"""
<div class=\"grid\">
  <div class=\"card\"><p class=\"kpi\">{books_count}</p><div class=\"kpi-label\">تعداد کتاب‌ها</div></div>
  <div class=\"card\"><p class=\"kpi\">{audio_count}</p><div class=\"kpi-label\">رکوردهای صوتی</div></div>
  <div class=\"card\"><p class=\"kpi\">{cats_count}</p><div class=\"kpi-label\">دسته‌بندی‌ها</div></div>
  <div class=\"card\"><p class=\"kpi\">{chaps_count}</p><div class=\"kpi-label\">فصل‌ها</div></div>
  <div class=\"card\"><p class=\"kpi\">{html.escape(app_version)}</p><div class=\"kpi-label\">نسخه برنامه</div></div>
</div>

<div class=\"grid\">
  <div class=\"card\">
    <h2>عملیات سریع Git</h2>
    <p class=\"muted\">با یک کلیک Pull، مرتب‌سازی فایل‌ها، یا Commit & Push را اجرا کن.</p>
    <div class=\"toolbar\">
      <form class=\"inline\" method=\"post\" action=\"/run\">
        <input type=\"hidden\" name=\"action\" value=\"pull\">
        <button class=\"btn dark\" type=\"submit\">Pull (rebase)</button>
      </form>
      <form class=\"inline\" method=\"post\" action=\"/run\">
        <input type=\"hidden\" name=\"action\" value=\"reorganize\">
        <button class=\"btn teal\" type=\"submit\">مرتب‌سازی json/kotob/update</button>
      </form>
    </div>
    <form method=\"post\" action=\"/run\">
      <input type=\"hidden\" name=\"action\" value=\"push\">
      <div class=\"field\">
        <label for=\"msg\">پیام کامیت</label>
        <input id=\"msg\" type=\"text\" name=\"message\" value=\"بروزرسانی از پنل آفلاین\" required>
      </div>
      <button class=\"btn primary\" type=\"submit\">Commit & Push</button>
    </form>
  </div>

  <div class=\"card\">
    <h2>مدیریت متادیتا</h2>
    <p class=\"muted\">برای هر فایل، نمای جدول و فرم ویرایش جداگانه داری.</p>
    <div class=\"toolbar\">
      <a class=\"btn primary\" href=\"/books\">مدیریت کتاب‌ها</a>
      <a class=\"btn ghost\" href=\"/audio\">مدیریت صوت</a>
      <a class=\"btn ghost\" href=\"/structure?section=categories\">ساختار</a>
      <a class=\"btn ghost\" href=\"/app-update\">آپدیت برنامه</a>
    </div>
  </div>
</div>

<div class=\"card\">
  <h2>فایل‌های اصلی</h2>
  <div class=\"table-wrap\">
    <table>
      <thead><tr><th>مسیر فایل</th><th>عملیات</th></tr></thead>
      <tbody>{file_links}</tbody>
    </table>
  </div>
  <form class=\"inline\" method=\"get\" action=\"/edit\" style=\"margin-top:10px\"> 
    <input type=\"text\" name=\"file\" placeholder=\"مثال: json/books_metadata.json\">
    <button class=\"btn ghost\" type=\"submit\">باز کردن فایل دلخواه</button>
  </form>
</div>

<div class=\"grid\">
  <div class=\"card\"><h3>وضعیت مخزن</h3><pre class=\"cli\">{html.escape(status_out)}</pre></div>
  <div class=\"card\"><h3>ریموت‌ها</h3><pre class=\"cli\">{html.escape(remote_out)}</pre></div>
  <div class=\"card\"><h3>آخرین کامیت‌ها</h3><pre class=\"cli\">{html.escape(commits_out)}</pre></div>
</div>
"""

        return self._base_layout(
            title="داشبورد کنترل پنل KDINI",
            content=content,
            notice=notice,
            cmd_output=cmd_output,
        )

    def _render_books(self, notice: str = "", cmd_output: str = "", q: str = "") -> str:
        try:
            books = self._load_books()
        except Exception as exc:  # noqa: BLE001
            return self._base_layout(
                title="مدیریت کتاب‌ها",
                content="<div class='card'><h2>خطا</h2><p>خواندن فایل کتاب‌ها ممکن نشد.</p></div>",
                notice=f"خطا: {exc}",
                cmd_output=cmd_output,
            )

        query = q.strip().lower()
        rows: list[str] = []
        shown = 0

        for idx, row in enumerate(books):
            bid = row.get("id", "")
            title = str(row.get("title", ""))
            version = str(row.get("version", ""))
            status = str(row.get("status", ""))
            url = get_first_url(row)
            haystack = " ".join([str(bid), title, version, status, url]).lower()
            if query and query not in haystack:
                continue

            shown += 1
            safe_url = html.escape(url)
            url_cell = (
                f"<a href='{safe_url}' target='_blank' rel='noreferrer'>{html.escape(shorten(url, 80))}</a>"
                if url
                else "<span class='muted'>ندارد</span>"
            )
            rows.append(
                "<tr>"
                f"<td><span class='pill'>{idx + 1}</span></td>"
                f"<td>{html.escape(str(bid))}</td>"
                f"<td>{html.escape(title)}</td>"
                f"<td>{html.escape(version)}</td>"
                f"<td>{html.escape(status)}</td>"
                f"<td>{url_cell}</td>"
                f"<td><a class='btn ghost' href='/book-edit?idx={idx}'>ویرایش</a></td>"
                "</tr>"
            )

        rows_html = "\n".join(rows)
        empty = "" if rows else "<div class='empty'>موردی برای نمایش وجود ندارد.</div>"

        content = f"""
<div class=\"card\">
  <h2>مدیریت کتاب‌ها</h2>
  <p class=\"muted\">منبع: <code class='mono'>{BOOKS_JSON_REL}</code> | تعداد کل: {len(books)} | نمایش: {shown}</p>
  <div class=\"toolbar\">
    <form class=\"inline\" method=\"get\" action=\"/books\">
      <input type=\"text\" name=\"q\" value=\"{html.escape(q)}\" placeholder=\"جستجو: id، عنوان، وضعیت، لینک\">
      <button class=\"btn ghost\" type=\"submit\">جستجو</button>
      <a class=\"btn ghost\" href=\"/books\">پاک کردن</a>
    </form>
    <form class=\"inline\" method=\"post\" action=\"/books-action\">
      <input type=\"hidden\" name=\"action\" value=\"fix_urls\">
      <button class=\"btn teal\" type=\"submit\">اصلاح لینک‌ها به kotob</button>
    </form>
  </div>

  <div class=\"table-wrap\">
    <table>
      <thead>
        <tr><th>#</th><th>id</th><th>عنوان</th><th>نسخه</th><th>وضعیت</th><th>لینک دانلود</th><th>عملیات</th></tr>
      </thead>
      <tbody>{rows_html}</tbody>
    </table>
    {empty}
  </div>
</div>
"""

        return self._base_layout(
            title="مدیریت کتاب‌ها",
            content=content,
            notice=notice,
            cmd_output=cmd_output,
        )

    def _render_book_edit(self, idx: int, notice: str = "", cmd_output: str = "") -> str:
        try:
            books = self._load_books()
            if idx < 0 or idx >= len(books):
                return self._render_books(notice="ردیف انتخاب‌شده معتبر نیست.")
            row = books[idx]
        except Exception as exc:  # noqa: BLE001
            return self._render_books(notice=f"خطا: {exc}")

        def val(key: str) -> str:
            v = row.get(key)
            return "" if v is None else str(v)

        content = f"""
<div class=\"card\">
  <h2>ویرایش ردیف کتاب</h2>
  <p class=\"muted\">ردیف: {idx + 1}</p>
  <form method=\"post\" action=\"/book-save\">
    <input type=\"hidden\" name=\"idx\" value=\"{idx}\">
    <div class=\"two-col\">
      <div class=\"field\"><label>id</label><input type=\"number\" name=\"id\" value=\"{html.escape(val('id'))}\"></div>
      <div class=\"field\"><label>version</label><input type=\"text\" name=\"version\" value=\"{html.escape(val('version'))}\"></div>
      <div class=\"field\"><label>status</label><input type=\"text\" name=\"status\" value=\"{html.escape(val('status'))}\"></div>
      <div class=\"field\"><label>is_default</label><input type=\"number\" name=\"is_default\" value=\"{html.escape(val('is_default'))}\"></div>
      <div class=\"field\"><label>is_downloaded_on_device</label><input type=\"number\" name=\"is_downloaded_on_device\" value=\"{html.escape(val('is_downloaded_on_device'))}\"></div>
    </div>
    <div class=\"field\"><label>title</label><input type=\"text\" name=\"title\" value=\"{html.escape(val('title'))}\"></div>
    <div class=\"field\"><label>sql_download_url</label><input type=\"text\" name=\"sql_download_url\" value=\"{html.escape(val('sql_download_url'))}\"></div>
    <div class=\"field\"><label>download_url</label><input type=\"text\" name=\"download_url\" value=\"{html.escape(val('download_url'))}\"></div>
    <div class=\"field\"><label>url</label><input type=\"text\" name=\"url\" value=\"{html.escape(val('url'))}\"></div>
    <div class=\"field\"><label>description</label><textarea name=\"description\">{html.escape(val('description'))}</textarea></div>
    <div class=\"toolbar\">
      <button class=\"btn primary\" type=\"submit\">ذخیره</button>
      <a class=\"btn ghost\" href=\"/books\">بازگشت</a>
    </div>
  </form>
</div>
"""

        return self._base_layout(
            title="ویرایش کتاب",
            content=content,
            notice=notice,
            cmd_output=cmd_output,
        )

    def _render_audio(self, notice: str = "", cmd_output: str = "", q: str = "") -> str:
        try:
            audio_rows = self._load_audio()
        except Exception as exc:  # noqa: BLE001
            return self._base_layout(
                title="مدیریت صوت",
                content="<div class='card'><h2>خطا</h2><p>خواندن فایل صوت‌ها ممکن نشد.</p></div>",
                notice=f"خطا: {exc}",
                cmd_output=cmd_output,
            )

        query = q.strip().lower()
        rows: list[str] = []
        shown = 0

        for idx, row in enumerate(audio_rows):
            kotob_id = row.get("kotob_id")
            chapters_id = row.get("chapters_id")
            lang = str(row.get("lang", ""))
            narrator = str(row.get("narrator", ""))
            title = str(row.get("title", ""))
            url = str(row.get("url", ""))

            haystack = " ".join([str(kotob_id), str(chapters_id), lang, narrator, title, url]).lower()
            if query and query not in haystack:
                continue

            shown += 1
            safe_url = html.escape(url)
            url_cell = (
                f"<a href='{safe_url}' target='_blank' rel='noreferrer'>{html.escape(shorten(url, 72))}</a>"
                if url
                else "<span class='muted'>ندارد</span>"
            )
            rows.append(
                "<tr>"
                f"<td><span class='pill'>{idx + 1}</span></td>"
                f"<td>{html.escape(str(kotob_id))}</td>"
                f"<td>{html.escape(str(chapters_id))}</td>"
                f"<td>{html.escape(lang)}</td>"
                f"<td>{html.escape(narrator)}</td>"
                f"<td>{html.escape(title)}</td>"
                f"<td>{url_cell}</td>"
                f"<td><a class='btn ghost' href='/audio-edit?idx={idx}'>ویرایش</a></td>"
                "</tr>"
            )

        rows_html = "\n".join(rows)
        empty = "" if rows else "<div class='empty'>موردی برای نمایش وجود ندارد.</div>"

        content = f"""
<div class=\"card\">
  <h2>مدیریت فایل‌های صوتی</h2>
  <p class=\"muted\">منبع: <code class='mono'>{AUDIO_JSON_REL}</code> | تعداد کل: {len(audio_rows)} | نمایش: {shown}</p>
  <div class=\"toolbar\">
    <form class=\"inline\" method=\"get\" action=\"/audio\">
      <input type=\"text\" name=\"q\" value=\"{html.escape(q)}\" placeholder=\"جستجو: kotob_id، title، narrator، url\">
      <button class=\"btn ghost\" type=\"submit\">جستجو</button>
      <a class=\"btn ghost\" href=\"/audio\">پاک کردن</a>
    </form>
  </div>

  <div class=\"table-wrap\">
    <table>
      <thead>
        <tr><th>#</th><th>kotob_id</th><th>chapters_id</th><th>lang</th><th>narrator</th><th>title</th><th>url</th><th>عملیات</th></tr>
      </thead>
      <tbody>{rows_html}</tbody>
    </table>
    {empty}
  </div>
</div>
"""

        return self._base_layout(
            title="مدیریت صوت",
            content=content,
            notice=notice,
            cmd_output=cmd_output,
        )

    def _render_audio_edit(self, idx: int, notice: str = "", cmd_output: str = "") -> str:
        try:
            audio_rows = self._load_audio()
            if idx < 0 or idx >= len(audio_rows):
                return self._render_audio(notice="ردیف انتخاب‌شده معتبر نیست.")
            row = audio_rows[idx]
        except Exception as exc:  # noqa: BLE001
            return self._render_audio(notice=f"خطا: {exc}")

        def val(key: str) -> str:
            v = row.get(key)
            return "" if v is None else str(v)

        content = f"""
<div class=\"card\">
  <h2>ویرایش ردیف صوت</h2>
  <p class=\"muted\">ردیف: {idx + 1}</p>
  <form method=\"post\" action=\"/audio-save\">
    <input type=\"hidden\" name=\"idx\" value=\"{idx}\">
    <div class=\"two-col\">
      <div class=\"field\"><label>kotob_id</label><input type=\"number\" name=\"kotob_id\" value=\"{html.escape(val('kotob_id'))}\"></div>
      <div class=\"field\"><label>chapters_id</label><input type=\"number\" name=\"chapters_id\" value=\"{html.escape(val('chapters_id'))}\"></div>
      <div class=\"field\"><label>lang</label><input type=\"text\" name=\"lang\" value=\"{html.escape(val('lang'))}\"></div>
      <div class=\"field\"><label>narrator</label><input type=\"text\" name=\"narrator\" value=\"{html.escape(val('narrator'))}\"></div>
    </div>
    <div class=\"field\"><label>title</label><input type=\"text\" name=\"title\" value=\"{html.escape(val('title'))}\"></div>
    <div class=\"field\"><label>url</label><input type=\"text\" name=\"url\" value=\"{html.escape(val('url'))}\"></div>
    <div class=\"toolbar\">
      <button class=\"btn primary\" type=\"submit\">ذخیره</button>
      <a class=\"btn ghost\" href=\"/audio\">بازگشت</a>
    </div>
  </form>
</div>
"""

        return self._base_layout(
            title="ویرایش صوت",
            content=content,
            notice=notice,
            cmd_output=cmd_output,
        )

    def _render_structure(
        self,
        section: str = "categories",
        notice: str = "",
        cmd_output: str = "",
        q: str = "",
    ) -> str:
        if section not in {"categories", "chapters"}:
            section = "categories"

        try:
            structure = self._load_structure()
            rows_data = [row for row in structure.get(section, []) if isinstance(row, dict)]
        except Exception as exc:  # noqa: BLE001
            return self._base_layout(
                title="مدیریت ساختار",
                content="<div class='card'><h2>خطا</h2><p>خواندن فایل ساختار ممکن نشد.</p></div>",
                notice=f"خطا: {exc}",
                cmd_output=cmd_output,
            )

        query = q.strip().lower()
        rows: list[str] = []
        shown = 0

        for idx, row in enumerate(rows_data):
            if section == "categories":
                rid = row.get("id", "")
                title = str(row.get("title", ""))
                sort_order = row.get("sort_order", "")
                icon = str(row.get("icon", ""))
                haystack = " ".join([str(rid), title, str(sort_order), icon]).lower()
                if query and query not in haystack:
                    continue
                shown += 1
                rows.append(
                    "<tr>"
                    f"<td><span class='pill'>{idx + 1}</span></td>"
                    f"<td>{html.escape(str(rid))}</td>"
                    f"<td>{html.escape(title)}</td>"
                    f"<td>{html.escape(str(sort_order))}</td>"
                    f"<td>{html.escape(icon)}</td>"
                    f"<td><a class='btn ghost' href='/structure-edit?section=categories&idx={idx}'>ویرایش</a></td>"
                    "</tr>"
                )
            else:
                rid = row.get("id", "")
                category_id = row.get("category_id", "")
                parent_id = row.get("parent_id", "")
                title = str(row.get("title", ""))
                icon = str(row.get("icon", ""))
                haystack = " ".join([str(rid), str(category_id), str(parent_id), title, icon]).lower()
                if query and query not in haystack:
                    continue
                shown += 1
                rows.append(
                    "<tr>"
                    f"<td><span class='pill'>{idx + 1}</span></td>"
                    f"<td>{html.escape(str(rid))}</td>"
                    f"<td>{html.escape(str(category_id))}</td>"
                    f"<td>{html.escape(str(parent_id))}</td>"
                    f"<td>{html.escape(title)}</td>"
                    f"<td>{html.escape(icon)}</td>"
                    f"<td><a class='btn ghost' href='/structure-edit?section=chapters&idx={idx}'>ویرایش</a></td>"
                    "</tr>"
                )

        rows_html = "\n".join(rows)
        empty = "" if rows else "<div class='empty'>موردی برای نمایش وجود ندارد.</div>"

        if section == "categories":
            headers = "<tr><th>#</th><th>id</th><th>title</th><th>sort_order</th><th>icon</th><th>عملیات</th></tr>"
        else:
            headers = "<tr><th>#</th><th>id</th><th>category_id</th><th>parent_id</th><th>title</th><th>icon</th><th>عملیات</th></tr>"

        content = f"""
<div class=\"card\">
  <h2>مدیریت ساختار ({'دسته‌بندی‌ها' if section == 'categories' else 'فصل‌ها'})</h2>
  <p class=\"muted\">منبع: <code class='mono'>{STRUCTURE_JSON_REL}</code> | تعداد کل: {len(rows_data)} | نمایش: {shown}</p>

  <div class=\"toolbar\">
    <a class=\"btn {'primary' if section == 'categories' else 'ghost'}\" href=\"/structure?section=categories\">دسته‌بندی‌ها</a>
    <a class=\"btn {'primary' if section == 'chapters' else 'ghost'}\" href=\"/structure?section=chapters\">فصل‌ها</a>
  </div>

  <div class=\"toolbar\">
    <form class=\"inline\" method=\"get\" action=\"/structure\">
      <input type=\"hidden\" name=\"section\" value=\"{section}\">
      <input type=\"text\" name=\"q\" value=\"{html.escape(q)}\" placeholder=\"جستجو در ردیف‌های این بخش\">
      <button class=\"btn ghost\" type=\"submit\">جستجو</button>
      <a class=\"btn ghost\" href=\"/structure?section={section}\">پاک کردن</a>
    </form>
  </div>

  <div class=\"table-wrap\">
    <table>
      <thead>{headers}</thead>
      <tbody>{rows_html}</tbody>
    </table>
    {empty}
  </div>
</div>
"""

        return self._base_layout(
            title="مدیریت ساختار",
            content=content,
            notice=notice,
            cmd_output=cmd_output,
        )

    def _render_structure_edit(
        self,
        section: str,
        idx: int,
        notice: str = "",
        cmd_output: str = "",
    ) -> str:
        if section not in {"categories", "chapters"}:
            return self._render_structure(notice="بخش نامعتبر است.")

        try:
            structure = self._load_structure()
            rows_data = [row for row in structure.get(section, []) if isinstance(row, dict)]
            if idx < 0 or idx >= len(rows_data):
                return self._render_structure(section=section, notice="ردیف انتخاب‌شده معتبر نیست.")
            row = rows_data[idx]
        except Exception as exc:  # noqa: BLE001
            return self._render_structure(section=section, notice=f"خطا: {exc}")

        def val(key: str) -> str:
            v = row.get(key)
            return "" if v is None else str(v)

        if section == "categories":
            fields = f"""
    <div class=\"two-col\">
      <div class=\"field\"><label>id</label><input type=\"number\" name=\"id\" value=\"{html.escape(val('id'))}\"></div>
      <div class=\"field\"><label>sort_order</label><input type=\"number\" name=\"sort_order\" value=\"{html.escape(val('sort_order'))}\"></div>
    </div>
    <div class=\"field\"><label>title</label><input type=\"text\" name=\"title\" value=\"{html.escape(val('title'))}\"></div>
    <div class=\"field\"><label>icon</label><input type=\"text\" name=\"icon\" value=\"{html.escape(val('icon'))}\"></div>
"""
        else:
            fields = f"""
    <div class=\"two-col\">
      <div class=\"field\"><label>id</label><input type=\"number\" name=\"id\" value=\"{html.escape(val('id'))}\"></div>
      <div class=\"field\"><label>category_id</label><input type=\"number\" name=\"category_id\" value=\"{html.escape(val('category_id'))}\"></div>
      <div class=\"field\"><label>parent_id</label><input type=\"number\" name=\"parent_id\" value=\"{html.escape(val('parent_id'))}\"></div>
    </div>
    <div class=\"field\"><label>title</label><input type=\"text\" name=\"title\" value=\"{html.escape(val('title'))}\"></div>
    <div class=\"field\"><label>icon</label><input type=\"text\" name=\"icon\" value=\"{html.escape(val('icon'))}\"></div>
"""

        content = f"""
<div class=\"card\">
  <h2>ویرایش ساختار ({'دسته‌بندی' if section == 'categories' else 'فصل'})</h2>
  <p class=\"muted\">ردیف: {idx + 1}</p>
  <form method=\"post\" action=\"/structure-save\">
    <input type=\"hidden\" name=\"section\" value=\"{section}\">
    <input type=\"hidden\" name=\"idx\" value=\"{idx}\">
    {fields}
    <div class=\"toolbar\">
      <button class=\"btn primary\" type=\"submit\">ذخیره</button>
      <a class=\"btn ghost\" href=\"/structure?section={section}\">بازگشت</a>
    </div>
  </form>
</div>
"""

        return self._base_layout(
            title="ویرایش ساختار",
            content=content,
            notice=notice,
            cmd_output=cmd_output,
        )

    def _render_app_update(self, notice: str = "", cmd_output: str = "") -> str:
        try:
            payload = read_json_file(UPDATE_JSON_REL)
            if not isinstance(payload, dict):
                raise ValueError("ساختار update.json باید آبجکت باشد.")
        except Exception as exc:  # noqa: BLE001
            return self._base_layout(
                title="مدیریت آپدیت برنامه",
                content="<div class='card'><h2>خطا</h2><p>خواندن update.json ممکن نشد.</p></div>",
                notice=f"خطا: {exc}",
                cmd_output=cmd_output,
            )

        def val(key: str) -> str:
            v = payload.get(key)
            return "" if v is None else str(v)

        changes = payload.get("changes", [])
        if isinstance(changes, list):
            changes_text = "\n".join(str(x) for x in changes)
        else:
            changes_text = str(changes)

        mandatory_checked = "checked" if bool(payload.get("mandatory", False)) else ""

        content = f"""
<div class=\"card\">
  <h2>مدیریت آپدیت برنامه</h2>
  <p class=\"muted\">منبع: <code class='mono'>{UPDATE_JSON_REL}</code></p>
  <form method=\"post\" action=\"/app-update-save\">
    <div class=\"two-col\">
      <div class=\"field\"><label>app</label><input type=\"text\" name=\"app\" value=\"{html.escape(val('app'))}\"></div>
      <div class=\"field\"><label>platform</label><input type=\"text\" name=\"platform\" value=\"{html.escape(val('platform'))}\"></div>
      <div class=\"field\"><label>version</label><input type=\"text\" name=\"version\" value=\"{html.escape(val('version'))}\"></div>
      <div class=\"field\"><label>build</label><input type=\"number\" name=\"build\" value=\"{html.escape(val('build'))}\"></div>
      <div class=\"field\"><label>released_at</label><input type=\"text\" name=\"released_at\" value=\"{html.escape(val('released_at'))}\"></div>
      <div class=\"field\"><label>download_url</label><input type=\"text\" name=\"download_url\" value=\"{html.escape(val('download_url'))}\"></div>
    </div>

    <div class=\"field\">
      <label class=\"checkbox\">
        <input type=\"checkbox\" name=\"mandatory\" value=\"1\" {mandatory_checked}>
        آپدیت اجباری است
      </label>
    </div>

    <div class=\"field\">
      <label>changes (هر خط یک مورد)</label>
      <textarea name=\"changes\">{html.escape(changes_text)}</textarea>
    </div>

    <div class=\"toolbar\">
      <button class=\"btn primary\" type=\"submit\">ذخیره</button>
      <a class=\"btn ghost\" href=\"/\">بازگشت به داشبورد</a>
    </div>
  </form>
</div>
"""

        return self._base_layout(
            title="مدیریت آپدیت برنامه",
            content=content,
            notice=notice,
            cmd_output=cmd_output,
        )

    def _render_edit(self, rel_file: str, notice: str = "", cmd_output: str = "") -> str:
        try:
            file_path = resolve_repo_path(rel_file)
            if not file_path.exists():
                return self._render_dashboard(notice=f"فایل پیدا نشد: {rel_file}")
            if file_path.is_dir():
                return self._render_dashboard(notice=f"مسیر فایل نیست: {rel_file}")
            if file_path.stat().st_size > MAX_EDIT_SIZE:
                return self._render_dashboard(notice=f"حجم فایل برای ویرایش مرورگر زیاد است: {rel_file}")
            text = file_path.read_text(encoding="utf-8")
        except Exception as exc:  # noqa: BLE001
            return self._render_dashboard(notice=f"باز کردن فایل ناموفق بود: {exc}")

        content = f"""
<div class=\"card\">
  <h2>ویرایش خام فایل</h2>
  <p class=\"muted\"><code class='mono'>{html.escape(rel_file)}</code></p>
  <form method=\"post\" action=\"/save\">
    <input type=\"hidden\" name=\"file\" value=\"{html.escape(rel_file)}\">
    <div class=\"field\">
      <textarea name=\"content\" style=\"min-height:72vh; direction:ltr; text-align:left; font-family:ui-monospace, Menlo, Monaco, Consolas, monospace;\">{html.escape(text)}</textarea>
    </div>
    <div class=\"toolbar\">
      <button class=\"btn primary\" type=\"submit\">ذخیره فایل</button>
      <a class=\"btn ghost\" href=\"/\">بازگشت</a>
    </div>
  </form>
</div>
"""
        return self._base_layout(
            title="ویرایش فایل",
            content=content,
            notice=notice,
            cmd_output=cmd_output,
        )

    def do_GET(self) -> None:  # noqa: N802
        parsed = urlparse(self.path)

        if parsed.path == "/":
            self._send_html(self._render_dashboard())
            return

        if parsed.path == "/books":
            params = parse_qs(parsed.query, keep_blank_values=True)
            q = params.get("q", [""])[0]
            self._send_html(self._render_books(q=q))
            return

        if parsed.path == "/book-edit":
            params = parse_qs(parsed.query, keep_blank_values=True)
            try:
                idx = int(params.get("idx", [""])[0])
            except ValueError:
                self._send_html(self._render_books(notice="ردیف نامعتبر است."))
                return
            self._send_html(self._render_book_edit(idx))
            return

        if parsed.path == "/audio":
            params = parse_qs(parsed.query, keep_blank_values=True)
            q = params.get("q", [""])[0]
            self._send_html(self._render_audio(q=q))
            return

        if parsed.path == "/audio-edit":
            params = parse_qs(parsed.query, keep_blank_values=True)
            try:
                idx = int(params.get("idx", [""])[0])
            except ValueError:
                self._send_html(self._render_audio(notice="ردیف نامعتبر است."))
                return
            self._send_html(self._render_audio_edit(idx))
            return

        if parsed.path == "/structure":
            params = parse_qs(parsed.query, keep_blank_values=True)
            section = params.get("section", ["categories"])[0]
            q = params.get("q", [""])[0]
            self._send_html(self._render_structure(section=section, q=q))
            return

        if parsed.path == "/structure-edit":
            params = parse_qs(parsed.query, keep_blank_values=True)
            section = params.get("section", ["categories"])[0]
            try:
                idx = int(params.get("idx", [""])[0])
            except ValueError:
                self._send_html(self._render_structure(section=section, notice="ردیف نامعتبر است."))
                return
            self._send_html(self._render_structure_edit(section=section, idx=idx))
            return

        if parsed.path == "/app-update":
            self._send_html(self._render_app_update())
            return

        if parsed.path == "/edit":
            params = parse_qs(parsed.query, keep_blank_values=True)
            rel_file = params.get("file", [""])[0]
            self._send_html(self._render_edit(rel_file))
            return

        self._send_html("<h1>Not Found</h1>", status=HTTPStatus.NOT_FOUND)

    def do_POST(self) -> None:  # noqa: N802
        parsed = urlparse(self.path)

        if parsed.path == "/run":
            form = self._parse_post()
            action = form.get("action", [""])[0]

            if action == "pull":
                ok, logs = self._run_pull()
                notice = "Pull انجام شد." if ok else "Pull با خطا متوقف شد."
                self._send_html(self._render_dashboard(notice=notice, cmd_output=logs))
                return

            if action == "reorganize":
                ok, logs = run_reorganize()
                notice = "مرتب‌سازی فایل‌ها انجام شد." if ok else "مرتب‌سازی فایل‌ها خطا داشت."
                self._send_html(self._render_dashboard(notice=notice, cmd_output=logs))
                return

            if action == "push":
                message = form.get("message", [""])[0].strip() or "بروزرسانی از پنل آفلاین"
                ok, logs = commit_and_push(message)
                notice = "Commit و Push انجام شد." if ok else "Commit یا Push با خطا مواجه شد."
                self._send_html(self._render_dashboard(notice=notice, cmd_output=logs))
                return

            self._send_html(self._render_dashboard(notice="عملیات ناشناخته است."))
            return

        if parsed.path == "/books-action":
            form = self._parse_post()
            action = form.get("action", [""])[0]
            if action == "fix_urls":
                try:
                    changed, msg = normalize_books_urls()
                    self._send_html(self._render_books(notice=f"{msg} تعداد اصلاح: {changed}"))
                    return
                except Exception as exc:  # noqa: BLE001
                    self._send_html(self._render_books(notice=f"خطا در اصلاح لینک‌ها: {exc}"))
                    return
            self._send_html(self._render_books(notice="عملیات نامعتبر است."))
            return

        if parsed.path == "/book-save":
            form = self._parse_post()
            try:
                idx = int(form.get("idx", [""])[0])
            except ValueError:
                self._send_html(self._render_books(notice="ردیف نامعتبر است."))
                return

            try:
                books = self._load_books()
                if idx < 0 or idx >= len(books):
                    self._send_html(self._render_books(notice="ردیف پیدا نشد."))
                    return

                row = books[idx]
                for key in (
                    "title",
                    "version",
                    "status",
                    "description",
                    "sql_download_url",
                    "download_url",
                    "url",
                ):
                    value = form.get(key, [""])[0].strip()
                    if key in URL_KEYS and value == "":
                        row[key] = None
                    else:
                        row[key] = value

                for int_key in ("id", "is_default", "is_downloaded_on_device"):
                    raw = form.get(int_key, [""])[0]
                    val = to_int_or_keep(raw, allow_none=False)
                    if val != "":
                        row[int_key] = val

                write_json_file(BOOKS_JSON_REL, books)
                self._send_html(self._render_book_edit(idx, notice="ردیف کتاب ذخیره شد."))
                return
            except Exception as exc:  # noqa: BLE001
                self._send_html(self._render_books(notice=f"ذخیره با خطا مواجه شد: {exc}"))
                return

        if parsed.path == "/audio-save":
            form = self._parse_post()
            try:
                idx = int(form.get("idx", [""])[0])
            except ValueError:
                self._send_html(self._render_audio(notice="ردیف نامعتبر است."))
                return

            try:
                audio_rows = self._load_audio()
                if idx < 0 or idx >= len(audio_rows):
                    self._send_html(self._render_audio(notice="ردیف پیدا نشد."))
                    return

                row = audio_rows[idx]
                row["kotob_id"] = to_int_or_keep(form.get("kotob_id", [""])[0], allow_none=True)
                row["chapters_id"] = to_int_or_keep(form.get("chapters_id", [""])[0], allow_none=False)
                row["lang"] = form.get("lang", [""])[0].strip()
                row["narrator"] = form.get("narrator", [""])[0].strip()
                row["title"] = form.get("title", [""])[0].strip()
                row["url"] = form.get("url", [""])[0].strip()

                write_json_file(AUDIO_JSON_REL, audio_rows)
                self._send_html(self._render_audio_edit(idx, notice="ردیف صوت ذخیره شد."))
                return
            except Exception as exc:  # noqa: BLE001
                self._send_html(self._render_audio(notice=f"ذخیره با خطا مواجه شد: {exc}"))
                return

        if parsed.path == "/structure-save":
            form = self._parse_post()
            section = form.get("section", ["categories"])[0]
            if section not in {"categories", "chapters"}:
                self._send_html(self._render_structure(notice="بخش نامعتبر است."))
                return

            try:
                idx = int(form.get("idx", [""])[0])
            except ValueError:
                self._send_html(self._render_structure(section=section, notice="ردیف نامعتبر است."))
                return

            try:
                structure = self._load_structure()
                rows_data = [x for x in structure.get(section, []) if isinstance(x, dict)]
                if idx < 0 or idx >= len(rows_data):
                    self._send_html(self._render_structure(section=section, notice="ردیف پیدا نشد."))
                    return

                row = rows_data[idx]
                row["id"] = to_int_or_keep(form.get("id", [""])[0], allow_none=False)
                row["title"] = form.get("title", [""])[0].strip()
                row["icon"] = form.get("icon", [""])[0].strip()

                if section == "categories":
                    row["sort_order"] = to_int_or_keep(form.get("sort_order", [""])[0], allow_none=False)
                else:
                    row["category_id"] = to_int_or_keep(form.get("category_id", [""])[0], allow_none=False)
                    row["parent_id"] = to_int_or_keep(form.get("parent_id", [""])[0], allow_none=False)

                # rows_data references structure[section] dict elements, so writing structure is enough.
                write_json_file(STRUCTURE_JSON_REL, structure)
                self._send_html(self._render_structure_edit(section, idx, notice="ردیف ساختار ذخیره شد."))
                return
            except Exception as exc:  # noqa: BLE001
                self._send_html(self._render_structure(section=section, notice=f"ذخیره با خطا مواجه شد: {exc}"))
                return

        if parsed.path == "/app-update-save":
            form = self._parse_post()
            try:
                payload = read_json_file(UPDATE_JSON_REL)
                if not isinstance(payload, dict):
                    payload = {}

                payload["app"] = form.get("app", [""])[0].strip()
                payload["platform"] = form.get("platform", [""])[0].strip()
                payload["version"] = form.get("version", [""])[0].strip()

                build_raw = form.get("build", [""])[0]
                build_val = to_int_or_keep(build_raw, allow_none=False)
                payload["build"] = build_val if build_val != "" else 0

                payload["released_at"] = form.get("released_at", [""])[0].strip()
                payload["mandatory"] = to_bool(form.get("mandatory", ["0"])[0])
                payload["download_url"] = form.get("download_url", [""])[0].strip()

                changes_text = form.get("changes", [""])[0]
                payload["changes"] = [line.strip() for line in changes_text.splitlines() if line.strip()]

                write_json_file(UPDATE_JSON_REL, payload)
                self._send_html(self._render_app_update(notice="تنظیمات آپدیت ذخیره شد."))
                return
            except Exception as exc:  # noqa: BLE001
                self._send_html(self._render_app_update(notice=f"ذخیره با خطا مواجه شد: {exc}"))
                return

        if parsed.path == "/save":
            form = self._parse_post()
            rel_file = form.get("file", [""])[0]
            content = form.get("content", [""])[0]
            try:
                file_path = resolve_repo_path(rel_file)
                file_path.parent.mkdir(parents=True, exist_ok=True)
                file_path.write_text(content, encoding="utf-8")
                self._send_html(self._render_edit(rel_file, notice="فایل ذخیره شد."))
                return
            except Exception as exc:  # noqa: BLE001
                self._send_html(self._render_dashboard(notice=f"ذخیره فایل ناموفق بود: {exc}"))
                return

        self._send_html("<h1>Not Found</h1>", status=HTTPStatus.NOT_FOUND)

    def _run_pull(self) -> tuple[bool, str]:
        logs: list[str] = []
        code, branch = run_cmd(["git", "branch", "--show-current"])
        branch = branch.strip() if code == 0 else "main"

        code, out = run_cmd(["git", "pull", "--rebase", "origin", branch])
        logs.append(f"$ git pull --rebase origin {branch}")
        if out:
            logs.append(out)
        return code == 0, "\n".join(logs)


def main() -> int:
    parser = argparse.ArgumentParser(description="کنترل پنل آفلاین مدیریت ریپو")
    parser.add_argument("--host", default="127.0.0.1")
    parser.add_argument("--port", type=int, default=8787)
    args = parser.parse_args()

    server = ThreadingHTTPServer((args.host, args.port), PanelHandler)
    print(f"Panel running at http://{args.host}:{args.port}")
    print(f"Repo: {REPO_DIR}")
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nStopped.")
    finally:
        server.server_close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
