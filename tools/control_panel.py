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
DEFAULT_EDIT_FILES = [
    BOOKS_JSON_REL,
    "json/content_audio_metadata.json",
    "json/structure_metadata.json",
    "update/update.json",
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


def get_book_url(book: dict) -> str:
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
    for book in data:
        if not isinstance(book, dict):
            continue
        for key in URL_KEYS:
            val = book.get(key)
            if not isinstance(val, str):
                continue
            new_val = RAW_SQL_PATTERN.sub(r"\1kotob/\2", val)
            if new_val != val:
                book[key] = new_val
                changes += 1

    if changes > 0:
        write_json_file(BOOKS_JSON_REL, data)

    return changes, "لینک‌ها بررسی و بروزرسانی شد."


def commit_and_push(message: str) -> tuple[bool, str]:
    logs: list[str] = []

    code, out = run_cmd(["git", "add", "-A"])
    logs.append("$ git add -A")
    if out:
        logs.append(out)
    if code != 0:
        return False, "\n".join(logs)

    diff = subprocess.run(
        ["git", "diff", "--cached", "--quiet"],
        cwd=REPO_DIR,
        check=False,
    )
    if diff.returncode == 0:
        logs.append("تغییری برای کامیت وجود ندارد.")
        return True, "\n".join(logs)
    if diff.returncode != 1:
        logs.append("بررسی diff با خطا مواجه شد.")
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
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
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
      font-family: "Vazirmatn", "IRANSans", Tahoma, "Segoe UI", sans-serif;
      line-height: 1.6;
    }}
    .container {{ max-width: 1200px; margin: 0 auto; padding: 22px; }}
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
      font-size: 14px;
      padding: 8px 12px;
      font-weight: 600;
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
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 14px;
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
    input[type=text], input[type=number], textarea {{
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
    .field label {{ display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; }}
    .btn {{
      border: 1px solid transparent;
      border-radius: 10px;
      padding: 8px 12px;
      cursor: pointer;
      font-size: 13px;
      font-weight: 700;
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
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 12px;
    }}
    .table-wrap {{ overflow-x: auto; border: 1px solid var(--border); border-radius: 12px; }}
    table {{ width: 100%; border-collapse: collapse; min-width: 880px; background: #fff; }}
    th, td {{ border-bottom: 1px solid var(--border); padding: 10px; font-size: 13px; text-align: right; vertical-align: top; }}
    th {{ background: #fafafa; font-weight: 800; white-space: nowrap; }}
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
    @media (max-width: 720px) {{
      .container {{ padding: 14px; }}
      .brand h1 {{ font-size: 20px; }}
      .nav a {{ font-size: 12px; padding: 7px 10px; }}
    }}
  </style>
</head>
<body>
  <div class="container">
    <div class="topbar">
      <div class="brand">
        <h1>کنترل پنل آفلاین KDINI</h1>
        <p>مدیریت محلی فایل‌ها و Git بدون نیاز به ادیتور GitHub</p>
      </div>
      <div class="nav">
        <a href="/">داشبورد</a>
        <a href="/books">مدیریت کتاب‌ها</a>
        <a href="/edit?file={html.escape(BOOKS_JSON_REL)}">ویرایش خام JSON</a>
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

        file_links = "".join(
            (
                "<tr>"
                f"<td><code class='mono'>{html.escape(path)}</code></td>"
                f"<td><a class='btn ghost' href='/edit?file={html.escape(path)}'>ویرایش فایل</a></td>"
                "</tr>"
            )
            for path in DEFAULT_EDIT_FILES
        )

        content = f"""
<div class="grid">
  <div class="card">
    <h2>عملیات سریع Git</h2>
    <p class="muted">با یک کلیک Pull / مرتب‌سازی / Commit & Push انجام بده.</p>
    <div class="toolbar">
      <form class="inline" method="post" action="/run">
        <input type="hidden" name="action" value="pull">
        <button class="btn dark" type="submit">Pull (rebase)</button>
      </form>
      <form class="inline" method="post" action="/run">
        <input type="hidden" name="action" value="reorganize">
        <button class="btn teal" type="submit">مرتب‌سازی فایل‌ها</button>
      </form>
    </div>
    <form method="post" action="/run">
      <input type="hidden" name="action" value="push">
      <div class="field">
        <label for="msg">پیام کامیت</label>
        <input id="msg" type="text" name="message" value="بروزرسانی از پنل آفلاین" required>
      </div>
      <button class="btn primary" type="submit">Commit & Push</button>
    </form>
  </div>
  <div class="card">
    <h2>مدیریت کتاب‌ها</h2>
    <p class="muted">نمایش و ویرایش جدول `books_metadata.json` شبیه پنل‌های مدیریتی.</p>
    <div class="toolbar">
      <a class="btn primary" href="/books">ورود به جدول کتاب‌ها</a>
      <a class="btn ghost" href="/edit?file={html.escape(BOOKS_JSON_REL)}">ویرایش خام JSON</a>
    </div>
  </div>
</div>

<div class="card">
  <h2>فایل‌های مهم</h2>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>مسیر فایل</th><th>عملیات</th></tr>
      </thead>
      <tbody>
        {file_links}
      </tbody>
    </table>
  </div>
  <form class="inline" method="get" action="/edit" style="margin-top: 10px;">
    <input type="text" name="file" placeholder="مثال: json/books_metadata.json">
    <button class="btn ghost" type="submit">باز کردن فایل دلخواه</button>
  </form>
</div>

<div class="grid">
  <div class="card">
    <h3>وضعیت مخزن</h3>
    <pre class="cli">{html.escape(status_out)}</pre>
  </div>
  <div class="card">
    <h3>ریموت‌ها</h3>
    <pre class="cli">{html.escape(remote_out)}</pre>
  </div>
  <div class="card">
    <h3>آخرین کامیت‌ها</h3>
    <pre class="cli">{html.escape(commits_out)}</pre>
  </div>
</div>
"""

        return self._base_layout(
            title="داشبورد کنترل پنل KDINI",
            content=content,
            notice=notice,
            cmd_output=cmd_output,
        )

    def _load_books(self) -> list[dict]:
        data = read_json_file(BOOKS_JSON_REL)
        if not isinstance(data, list):
            raise ValueError("books_metadata.json باید لیست باشد.")

        out: list[dict] = []
        for item in data:
            if isinstance(item, dict):
                out.append(item)
        return out

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
        visible_count = 0

        for idx, book in enumerate(books):
            bid = book.get("id", "")
            title = str(book.get("title", ""))
            version = str(book.get("version", ""))
            status = str(book.get("status", ""))
            url = get_book_url(book)
            haystack = " ".join([str(bid), title, version, status, url]).lower()

            if query and query not in haystack:
                continue

            visible_count += 1
            safe_url = html.escape(url)
            url_cell = (
                f"<a href='{safe_url}' target='_blank' rel='noreferrer'>{html.escape(shorten(url, 78))}</a>"
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

        rows_html = "\n".join(rows) if rows else ""
        empty = "" if rows else "<div class='empty'>هیچ موردی برای نمایش پیدا نشد.</div>"

        content = f"""
<div class="card">
  <h2>جدول کتاب‌ها</h2>
  <p class="muted">منبع داده: <code class='mono'>{html.escape(BOOKS_JSON_REL)}</code> | تعداد کل: {len(books)} | نمایش: {visible_count}</p>
  <div class="toolbar">
    <form class="inline" method="get" action="/books">
      <input type="text" name="q" value="{html.escape(q)}" placeholder="جستجو بر اساس عنوان، شناسه، وضعیت یا لینک">
      <button class="btn ghost" type="submit">جستجو</button>
      <a class="btn ghost" href="/books">پاک کردن</a>
    </form>
    <form class="inline" method="post" action="/books-action">
      <input type="hidden" name="action" value="fix_urls">
      <button class="btn teal" type="submit">اصلاح خودکار لینک‌ها به kotob</button>
    </form>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>id</th>
          <th>عنوان</th>
          <th>نسخه</th>
          <th>وضعیت</th>
          <th>لینک دانلود</th>
          <th>عملیات</th>
        </tr>
      </thead>
      <tbody>
        {rows_html}
      </tbody>
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
            book = books[idx]
        except Exception as exc:  # noqa: BLE001
            return self._render_books(notice=f"خطا: {exc}")

        def val(key: str) -> str:
            item = book.get(key)
            if item is None:
                return ""
            return str(item)

        content = f"""
<div class="card">
  <h2>ویرایش کتاب</h2>
  <p class="muted">ردیف: {idx + 1}</p>
  <form method="post" action="/book-save">
    <input type="hidden" name="idx" value="{idx}">
    <div class="two-col">
      <div class="field">
        <label>id</label>
        <input type="number" name="id" value="{html.escape(val('id'))}">
      </div>
      <div class="field">
        <label>version</label>
        <input type="text" name="version" value="{html.escape(val('version'))}">
      </div>
      <div class="field">
        <label>status</label>
        <input type="text" name="status" value="{html.escape(val('status'))}">
      </div>
      <div class="field">
        <label>is_default</label>
        <input type="number" name="is_default" value="{html.escape(val('is_default'))}">
      </div>
      <div class="field">
        <label>is_downloaded_on_device</label>
        <input type="number" name="is_downloaded_on_device" value="{html.escape(val('is_downloaded_on_device'))}">
      </div>
    </div>
    <div class="field">
      <label>title</label>
      <input type="text" name="title" value="{html.escape(val('title'))}">
    </div>
    <div class="field">
      <label>sql_download_url</label>
      <input type="text" name="sql_download_url" value="{html.escape(val('sql_download_url'))}">
    </div>
    <div class="field">
      <label>download_url</label>
      <input type="text" name="download_url" value="{html.escape(val('download_url'))}">
    </div>
    <div class="field">
      <label>url</label>
      <input type="text" name="url" value="{html.escape(val('url'))}">
    </div>
    <div class="field">
      <label>description</label>
      <textarea name="description">{html.escape(val('description'))}</textarea>
    </div>
    <div class="toolbar">
      <button class="btn primary" type="submit">ذخیره تغییرات</button>
      <a class="btn ghost" href="/books">بازگشت به جدول</a>
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

    def _render_edit(self, rel_file: str, notice: str = "", cmd_output: str = "") -> str:
        try:
            file_path = resolve_repo_path(rel_file)
            if not file_path.exists():
                return self._render_dashboard(notice=f"فایل پیدا نشد: {rel_file}")
            if file_path.is_dir():
                return self._render_dashboard(notice=f"مسیر فایل نیست: {rel_file}")
            if file_path.stat().st_size > MAX_EDIT_SIZE:
                return self._render_dashboard(notice=f"حجم فایل برای ویرایش مرورگر زیاد است: {rel_file}")
            content_text = file_path.read_text(encoding="utf-8")
        except Exception as exc:  # noqa: BLE001
            return self._render_dashboard(notice=f"باز کردن فایل ناموفق بود: {exc}")

        content = f"""
<div class="card">
  <h2>ویرایش فایل</h2>
  <p class="muted"><code class='mono'>{html.escape(rel_file)}</code></p>
  <form method="post" action="/save">
    <input type="hidden" name="file" value="{html.escape(rel_file)}">
    <div class="field">
      <textarea name="content" style="min-height: 72vh; direction: ltr; text-align: left; font-family: ui-monospace, Menlo, Monaco, Consolas, monospace;">{html.escape(content_text)}</textarea>
    </div>
    <div class="toolbar">
      <button class="btn primary" type="submit">ذخیره فایل</button>
      <a class="btn ghost" href="/">بازگشت به داشبورد</a>
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
            idx_raw = params.get("idx", [""])[0]
            try:
                idx = int(idx_raw)
            except ValueError:
                self._send_html(self._render_books(notice="شناسه ردیف نامعتبر است."))
                return
            self._send_html(self._render_book_edit(idx=idx))
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
                message = form.get("message", [""])[0].strip()
                if not message:
                    message = "بروزرسانی از پنل آفلاین"
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
                    notice = f"{msg} تعداد لینک‌های اصلاح‌شده: {changed}"
                    self._send_html(self._render_books(notice=notice))
                    return
                except Exception as exc:  # noqa: BLE001
                    self._send_html(self._render_books(notice=f"خطا در اصلاح لینک‌ها: {exc}"))
                    return

            self._send_html(self._render_books(notice="عملیات نامعتبر بود."))
            return

        if parsed.path == "/book-save":
            form = self._parse_post()
            idx_raw = form.get("idx", [""])[0]
            try:
                idx = int(idx_raw)
            except ValueError:
                self._send_html(self._render_books(notice="ردیف نامعتبر است."))
                return

            try:
                books = self._load_books()
                if idx < 0 or idx >= len(books):
                    self._send_html(self._render_books(notice="ردیف پیدا نشد."))
                    return

                book = books[idx]

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
                        book[key] = None
                    else:
                        book[key] = value

                for int_key in ("id", "is_default", "is_downloaded_on_device"):
                    raw_value = form.get(int_key, [""])[0].strip()
                    if raw_value == "":
                        continue
                    try:
                        book[int_key] = int(raw_value)
                    except ValueError:
                        book[int_key] = raw_value

                write_json_file(BOOKS_JSON_REL, books)
                self._send_html(self._render_book_edit(idx, notice="ردیف با موفقیت ذخیره شد."))
                return
            except Exception as exc:  # noqa: BLE001
                self._send_html(self._render_books(notice=f"ذخیره با خطا مواجه شد: {exc}"))
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
