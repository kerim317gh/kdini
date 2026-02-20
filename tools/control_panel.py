#!/usr/bin/env python3
from __future__ import annotations

import argparse
import html
import subprocess
from http import HTTPStatus
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlparse

REPO_DIR = Path(__file__).resolve().parent.parent
DEFAULT_EDIT_FILES = [
    "json/books_metadata.json",
    "json/content_audio_metadata.json",
    "json/structure_metadata.json",
    "update/update.json",
    "README.md",
]
MAX_EDIT_SIZE = 2_000_000


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
        return 124, f"Command timed out: {' '.join(cmd)}"

    output = ""
    if proc.stdout:
        output += proc.stdout
    if proc.stderr:
        output += proc.stderr
    return proc.returncode, output.strip()


def resolve_repo_path(rel_path: str) -> Path:
    rel_path = rel_path.strip().replace("\\", "/")
    if not rel_path:
        raise ValueError("File path is required.")

    full_path = (REPO_DIR / rel_path).resolve()
    repo_root = REPO_DIR.resolve()
    if full_path != repo_root and repo_root not in full_path.parents:
        raise ValueError("File path must stay inside repo.")
    return full_path


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
        logs.append("No changes to commit.")
        return True, "\n".join(logs)
    if diff.returncode != 1:
        logs.append("git diff --cached --quiet failed.")
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
        return False, f"Missing script: {script}"

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

    def _render_page(self, notice: str = "", cmd_output: str = "") -> str:
        _, status_out = run_cmd(["git", "status", "--short", "-b"])
        _, commits_out = run_cmd(["git", "log", "--oneline", "-n", "5"])
        _, remote_out = run_cmd(["git", "remote", "-v"])

        notice_block = (
            f"<div class='notice'>{html.escape(notice)}</div>" if notice else ""
        )
        output_block = (
            "<h3>Last Command Output</h3>"
            f"<pre>{html.escape(cmd_output)}</pre>"
            if cmd_output
            else ""
        )

        links = "\n".join(
            [
                (
                    "<li><a href='/edit?file="
                    f"{html.escape(path)}'>{html.escape(path)}</a></li>"
                )
                for path in DEFAULT_EDIT_FILES
            ]
        )

        return f"""<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kdini Offline Panel</title>
  <style>
    body {{
      font-family: ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      margin: 24px;
      background: #f5f7fb;
      color: #1d2433;
    }}
    .card {{
      background: white;
      border: 1px solid #dce3ef;
      border-radius: 12px;
      padding: 16px;
      margin-bottom: 16px;
    }}
    .notice {{
      background: #ddf4ff;
      border: 1px solid #a5d8ff;
      border-radius: 8px;
      padding: 10px;
      margin-bottom: 12px;
    }}
    pre {{
      background: #0f172a;
      color: #dbeafe;
      padding: 12px;
      border-radius: 8px;
      overflow-x: auto;
      white-space: pre-wrap;
      word-break: break-word;
    }}
    form {{
      margin: 8px 0;
    }}
    input[type=text] {{
      width: 100%;
      max-width: 560px;
      padding: 8px;
      border: 1px solid #cdd7e5;
      border-radius: 8px;
    }}
    button {{
      border: 1px solid #0b61ff;
      background: #0b61ff;
      color: white;
      border-radius: 8px;
      padding: 8px 14px;
      cursor: pointer;
    }}
    a {{
      color: #0b61ff;
      text-decoration: none;
    }}
  </style>
</head>
<body>
  <h1>Kdini Offline Panel</h1>
  <p><strong>Repo:</strong> {html.escape(str(REPO_DIR))}</p>
  {notice_block}
  <div class="card">
    <h2>Git Actions</h2>
    <form method="post" action="/run">
      <input type="hidden" name="action" value="pull">
      <button type="submit">Pull (rebase)</button>
    </form>
    <form method="post" action="/run">
      <input type="hidden" name="action" value="reorganize">
      <button type="submit">Reorganize Assets</button>
    </form>
    <form method="post" action="/run">
      <input type="hidden" name="action" value="push">
      <input type="text" name="message" value="Update from offline panel" required>
      <button type="submit">Commit &amp; Push</button>
    </form>
  </div>
  <div class="card">
    <h2>Edit Files</h2>
    <ul>
      {links}
    </ul>
    <form method="get" action="/edit">
      <input type="text" name="file" placeholder="custom/path/file.ext">
      <button type="submit">Open File</button>
    </form>
  </div>
  <div class="card">
    <h2>Status</h2>
    <pre>{html.escape(status_out)}</pre>
    <h3>Remotes</h3>
    <pre>{html.escape(remote_out)}</pre>
    <h3>Last 5 Commits</h3>
    <pre>{html.escape(commits_out)}</pre>
  </div>
  <div class="card">
    {output_block}
  </div>
</body>
</html>"""

    def _render_edit(self, rel_file: str, notice: str = "") -> str:
        notice_block = (
            f"<div class='notice'>{html.escape(notice)}</div>" if notice else ""
        )
        try:
            file_path = resolve_repo_path(rel_file)
            if not file_path.exists():
                return self._render_page(notice=f"File not found: {rel_file}")
            if file_path.is_dir():
                return self._render_page(notice=f"Not a file: {rel_file}")
            if file_path.stat().st_size > MAX_EDIT_SIZE:
                return self._render_page(
                    notice=f"File too large for browser editor: {rel_file}"
                )
            content = file_path.read_text(encoding="utf-8")
        except Exception as exc:  # noqa: BLE001
            return self._render_page(notice=f"Cannot open file: {exc}")

        return f"""<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit {html.escape(rel_file)}</title>
  <style>
    body {{
      font-family: ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      margin: 24px;
      background: #f5f7fb;
      color: #1d2433;
    }}
    .notice {{
      background: #ddf4ff;
      border: 1px solid #a5d8ff;
      border-radius: 8px;
      padding: 10px;
      margin-bottom: 12px;
    }}
    textarea {{
      width: 100%;
      min-height: 70vh;
      font-family: ui-monospace, Menlo, Monaco, Consolas, "Courier New", monospace;
      font-size: 13px;
      line-height: 1.4;
      padding: 12px;
      border: 1px solid #cdd7e5;
      border-radius: 8px;
      box-sizing: border-box;
    }}
    button {{
      border: 1px solid #0b61ff;
      background: #0b61ff;
      color: white;
      border-radius: 8px;
      padding: 8px 14px;
      cursor: pointer;
      margin-top: 10px;
    }}
    a {{
      color: #0b61ff;
      text-decoration: none;
    }}
  </style>
</head>
<body>
  <p><a href="/">Back to panel</a></p>
  <h1>{html.escape(rel_file)}</h1>
  {notice_block}
  <form method="post" action="/save">
    <input type="hidden" name="file" value="{html.escape(rel_file)}">
    <textarea name="content">{html.escape(content)}</textarea>
    <button type="submit">Save File</button>
  </form>
</body>
</html>"""

    def do_GET(self) -> None:  # noqa: N802
        parsed = urlparse(self.path)
        if parsed.path == "/":
            self._send_html(self._render_page())
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
                notice = "Pull completed." if ok else "Pull failed."
                self._send_html(self._render_page(notice=notice, cmd_output=logs))
                return

            if action == "reorganize":
                ok, logs = run_reorganize()
                notice = "Reorganize completed." if ok else "Reorganize failed."
                self._send_html(self._render_page(notice=notice, cmd_output=logs))
                return

            if action == "push":
                message = form.get("message", [""])[0].strip()
                if not message:
                    message = "Update from offline panel"
                ok, logs = commit_and_push(message)
                notice = "Commit & push completed." if ok else "Commit or push failed."
                self._send_html(self._render_page(notice=notice, cmd_output=logs))
                return

            self._send_html(self._render_page(notice="Unknown action."))
            return

        if parsed.path == "/save":
            form = self._parse_post()
            rel_file = form.get("file", [""])[0]
            content = form.get("content", [""])[0]
            try:
                file_path = resolve_repo_path(rel_file)
                file_path.parent.mkdir(parents=True, exist_ok=True)
                file_path.write_text(content, encoding="utf-8")
                self._send_html(self._render_edit(rel_file, notice="File saved."))
                return
            except Exception as exc:  # noqa: BLE001
                self._send_html(self._render_page(notice=f"Save failed: {exc}"))
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
    parser = argparse.ArgumentParser(description="Offline web control panel for this repo.")
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
