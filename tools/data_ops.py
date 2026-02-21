#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import re
import sqlite3
import sys
from collections import Counter
from pathlib import Path
from typing import Any, Iterable


BOOKS_JSON = "json/books_metadata.json"
AUDIO_JSON = "json/content_audio_metadata.json"
STRUCTURE_JSON = "json/structure_metadata.json"


def _eprint(msg: str) -> None:
    print(msg, file=sys.stderr)


def _read_json(path: Path) -> Any:
    with path.open("r", encoding="utf-8") as f:
        return json.load(f)


def _as_int(value: Any) -> int | None:
    if value is None:
        return None
    if isinstance(value, bool):
        return int(value)
    if isinstance(value, int):
        return value
    if isinstance(value, float):
        if value.is_integer():
            return int(value)
        return None
    text = str(value).strip()
    if text == "":
        return None
    try:
        return int(text)
    except ValueError:
        return None


def _normalize_book_id(value: Any) -> int | None:
    v = _as_int(value)
    if v is None:
        return None
    if v in (0, -1):
        return None
    return v


def _sql_quote(value: Any) -> str:
    if value is None:
        return "NULL"
    if isinstance(value, bool):
        return "1" if value else "0"
    if isinstance(value, (int, float)):
        return str(value)
    if isinstance(value, (bytes, bytearray)):
        return "X'" + bytes(value).hex() + "'"
    text = str(value).replace("'", "''")
    return f"'{text}'"


def _pick_default_db(repo_root: Path) -> Path:
    env = Path.cwd()
    candidates = [
        repo_root.parent / "kdini/kdini/assets/books.db",
        repo_root / "assets/books.db",
        env / "assets/books.db",
    ]
    for c in candidates:
        if c.exists():
            return c
    return candidates[0]


def _table_exists(conn: sqlite3.Connection, table: str) -> bool:
    row = conn.execute(
        "SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1",
        (table,),
    ).fetchone()
    return row is not None


def _fetch_count(conn: sqlite3.Connection, table: str) -> int:
    if not _table_exists(conn, table):
        return 0
    row = conn.execute(f"SELECT COUNT(*) FROM {table}").fetchone()
    return int(row[0]) if row else 0


def _sorted_ids(values: Iterable[Any]) -> list[int]:
    out: set[int] = set()
    for value in values:
        iv = _as_int(value)
        if iv is not None:
            out.add(iv)
    return sorted(out)


def run_doctor(repo_root: Path, db_path: Path) -> int:
    books_path = repo_root / BOOKS_JSON
    audio_path = repo_root / AUDIO_JSON
    structure_path = repo_root / STRUCTURE_JSON

    missing_files = [str(p) for p in (books_path, audio_path, structure_path) if not p.exists()]
    if missing_files:
        _eprint("Error: required metadata files are missing:")
        for p in missing_files:
            _eprint(f"- {p}")
        return 2

    books_data = _read_json(books_path)
    audio_data = _read_json(audio_path)
    structure_data = _read_json(structure_path)

    if not isinstance(books_data, list):
        _eprint(f"Error: {books_path} must be a JSON array")
        return 2
    if not isinstance(audio_data, list):
        _eprint(f"Error: {audio_path} must be a JSON array")
        return 2
    if not isinstance(structure_data, dict):
        _eprint(f"Error: {structure_path} must be a JSON object")
        return 2

    book_ids_raw = [item.get("id") if isinstance(item, dict) else None for item in books_data]
    book_ids = [b for b in (_as_int(v) for v in book_ids_raw) if b is not None]
    dup_book_ids = sorted([k for k, c in Counter(book_ids).items() if c > 1])
    invalid_book_id_rows = sum(1 for v in book_ids_raw if _as_int(v) is None)

    categories = structure_data.get("categories")
    chapters = structure_data.get("chapters")
    categories = categories if isinstance(categories, list) else []
    chapters = chapters if isinstance(chapters, list) else []

    cat_ids_raw = [item.get("id") if isinstance(item, dict) else None for item in categories]
    ch_ids_raw = [item.get("id") if isinstance(item, dict) else None for item in chapters]
    cat_ids = [c for c in (_as_int(v) for v in cat_ids_raw) if c is not None]
    ch_ids = [c for c in (_as_int(v) for v in ch_ids_raw) if c is not None]
    dup_cat_ids = sorted([k for k, c in Counter(cat_ids).items() if c > 1])
    dup_ch_ids = sorted([k for k, c in Counter(ch_ids).items() if c > 1])

    audio_missing_required = 0
    audio_bad_book_ref = 0
    audio_bad_chapter_ref = 0
    audio_keys: list[tuple[int | None, int | None, str, str]] = []

    book_id_set = set(book_ids)
    chapter_id_set = set(ch_ids)

    for row in audio_data:
        if not isinstance(row, dict):
            audio_missing_required += 1
            continue
        kid = _normalize_book_id(row.get("kotob_id") or row.get("book_id") or row.get("kotobId"))
        chid = _as_int(row.get("chapters_id") or row.get("chapter_id") or row.get("chapterId"))
        lang = str(row.get("lang") or row.get("language") or "").strip().lower()
        url = str(row.get("url") or row.get("audio_url") or row.get("download_url") or "").strip()
        if chid is None or url == "":
            audio_missing_required += 1
            continue
        if kid is not None and kid not in book_id_set:
            audio_bad_book_ref += 1
        if chid not in chapter_id_set:
            audio_bad_chapter_ref += 1
        audio_keys.append((kid, chid, lang, url))

    dup_audio_entries = sum(1 for _, c in Counter(audio_keys).items() if c > 1)

    db_exists = db_path.exists()
    db_stats: dict[str, Any] = {
        "kotob_count": 0,
        "content_count": 0,
        "content_audio_count": 0,
        "categories_count": 0,
        "chapters_count": 0,
        "db_book_ids": [],
        "content_book_ids": [],
        "content_rows_by_book": [],
        "bookless_content_rows": 0,
        "dup_content_pairs": 0,
        "orphan_content_books": 0,
        "orphan_content_chapters": 0,
    }

    if db_exists:
        conn = sqlite3.connect(str(db_path))
        conn.row_factory = sqlite3.Row

        db_stats["kotob_count"] = _fetch_count(conn, "kotob")
        db_stats["content_count"] = _fetch_count(conn, "content")
        db_stats["content_audio_count"] = _fetch_count(conn, "content_audio")
        db_stats["categories_count"] = _fetch_count(conn, "categories")
        db_stats["chapters_count"] = _fetch_count(conn, "chapters")

        db_book_ids: list[int] = []
        if _table_exists(conn, "kotob"):
            db_book_ids = _sorted_ids(r[0] for r in conn.execute("SELECT id FROM kotob"))
        db_stats["db_book_ids"] = db_book_ids

        content_book_ids: list[int] = []
        rows_by_book: list[tuple[int, int]] = []
        if _table_exists(conn, "content"):
            raw_ids = [r[0] for r in conn.execute("SELECT DISTINCT kotob_id FROM content")]
            content_book_ids = sorted(
                b for b in (_normalize_book_id(v) for v in raw_ids) if b is not None
            )
            db_stats["content_book_ids"] = content_book_ids

            for row in conn.execute("SELECT kotob_id, COUNT(*) AS c FROM content GROUP BY kotob_id"):
                kid = _normalize_book_id(row[0])
                if kid is not None:
                    rows_by_book.append((kid, int(row[1])))
            rows_by_book.sort(key=lambda x: (-x[1], x[0]))
            db_stats["content_rows_by_book"] = rows_by_book

            db_stats["bookless_content_rows"] = int(
                conn.execute(
                    """
                    SELECT COUNT(*)
                    FROM content
                    WHERE kotob_id IS NULL
                       OR TRIM(CAST(kotob_id AS TEXT)) = ''
                       OR CAST(kotob_id AS INTEGER) IN (0, -1)
                    """
                ).fetchone()[0]
            )

            db_stats["dup_content_pairs"] = int(
                conn.execute(
                    """
                    SELECT COUNT(*)
                    FROM (
                      SELECT chapters_id, kotob_id, COUNT(*) AS c
                      FROM content
                      GROUP BY chapters_id, kotob_id
                      HAVING c > 1
                    ) t
                    """
                ).fetchone()[0]
            )

            if _table_exists(conn, "kotob"):
                db_stats["orphan_content_books"] = int(
                    conn.execute(
                        """
                        SELECT COUNT(*)
                        FROM content c
                        WHERE c.kotob_id IS NOT NULL
                          AND TRIM(CAST(c.kotob_id AS TEXT)) <> ''
                          AND CAST(c.kotob_id AS INTEGER) NOT IN (0, -1)
                          AND NOT EXISTS (
                            SELECT 1 FROM kotob k WHERE CAST(k.id AS INTEGER) = CAST(c.kotob_id AS INTEGER)
                          )
                        """
                    ).fetchone()[0]
                )

            if _table_exists(conn, "chapters"):
                db_stats["orphan_content_chapters"] = int(
                    conn.execute(
                        """
                        SELECT COUNT(*)
                        FROM content c
                        WHERE c.chapters_id IS NOT NULL
                          AND NOT EXISTS (
                            SELECT 1 FROM chapters ch WHERE CAST(ch.id AS INTEGER) = CAST(c.chapters_id AS INTEGER)
                          )
                        """
                    ).fetchone()[0]
                )

        conn.close()

    print("== KDINI Data Doctor ==")
    print(f"Repo: {repo_root}")
    print(f"DB:   {db_path} {'(found)' if db_exists else '(missing)'}")
    print()

    print("[Books Metadata]")
    print(f"- rows: {len(books_data)}")
    print(f"- unique IDs: {len(set(book_ids))}")
    print(f"- rows with invalid ID: {invalid_book_id_rows}")
    print(f"- duplicate IDs: {len(dup_book_ids)}{(' -> ' + ', '.join(map(str, dup_book_ids))) if dup_book_ids else ''}")
    print()

    print("[Structure Metadata]")
    print(f"- schema: {structure_data.get('schema')}")
    print(f"- data_version: {structure_data.get('data_version')}")
    print(f"- categories: {len(categories)} (dup IDs: {len(dup_cat_ids)})")
    print(f"- chapters: {len(chapters)} (dup IDs: {len(dup_ch_ids)})")
    print()

    print("[Audio Metadata]")
    print(f"- rows: {len(audio_data)}")
    print(f"- duplicate key rows (book+chapter+lang+url): {dup_audio_entries}")
    print(f"- rows missing required fields (chapter/url): {audio_missing_required}")
    print(f"- rows referencing unknown book IDs: {audio_bad_book_ref}")
    print(f"- rows referencing unknown chapter IDs: {audio_bad_chapter_ref}")
    print()

    if db_exists:
        print("[SQLite]")
        print(f"- kotob rows: {db_stats['kotob_count']}")
        print(f"- content rows: {db_stats['content_count']}")
        print(f"- content_audio rows: {db_stats['content_audio_count']}")
        print(f"- categories rows: {db_stats['categories_count']}")
        print(f"- chapters rows: {db_stats['chapters_count']}")
        print(f"- content rows with missing/invalid kotob_id: {db_stats['bookless_content_rows']}")
        print(f"- duplicate content pairs (chapters_id+kotob_id): {db_stats['dup_content_pairs']}")
        print(f"- content rows with unknown kotob_id: {db_stats['orphan_content_books']}")
        print(f"- content rows with unknown chapter_id: {db_stats['orphan_content_chapters']}")
        top_rows = db_stats["content_rows_by_book"][:8]
        if top_rows:
            top = ", ".join(f"{bid}:{cnt}" for bid, cnt in top_rows)
            print(f"- top content books (book_id:rows): {top}")
        print()

        db_book_set = set(db_stats["db_book_ids"])
        content_book_set = set(db_stats["content_book_ids"])

        missing_in_db = sorted(book_id_set - db_book_set)
        local_only_books = sorted(db_book_set - book_id_set)
        content_without_metadata = sorted(content_book_set - book_id_set)

        print("[Cross-check]")
        print(f"- metadata books missing in DB.kotob: {len(missing_in_db)}")
        if missing_in_db:
            print(f"  IDs: {', '.join(map(str, missing_in_db[:30]))}")
        print(f"- local DB books not in metadata: {len(local_only_books)}")
        if local_only_books:
            print(f"  IDs: {', '.join(map(str, local_only_books[:30]))}")
        print(f"- content books not in metadata: {len(content_without_metadata)}")
        if content_without_metadata:
            print(f"  IDs: {', '.join(map(str, content_without_metadata[:30]))}")
        print()

    print("[Actionable]")
    print("1) For local-only books, export SQL patch and add metadata row before pushing.")
    print("2) Keep book IDs stable; never reuse an old ID for another book.")
    print("3) Keep one source of truth for structure IDs (chapters/categories) and update via JSON upsert.")
    print("4) For SQL book updates, use DELETE by kotob_id + INSERT to avoid duplicates.")

    return 0


def run_export_sql(db_path: Path, book_id: int, out_path: Path, meta_out: Path | None) -> int:
    if not db_path.exists():
        _eprint(f"Error: DB file not found: {db_path}")
        return 2

    conn = sqlite3.connect(str(db_path))
    conn.row_factory = sqlite3.Row

    if not _table_exists(conn, "content"):
        _eprint("Error: table 'content' not found in DB")
        conn.close()
        return 2

    cols = [row[1] for row in conn.execute("PRAGMA table_info(content)").fetchall()]
    if not cols:
        _eprint("Error: could not read content table columns")
        conn.close()
        return 2

    all_rows = [dict(r) for r in conn.execute("SELECT * FROM content").fetchall()]
    selected = [r for r in all_rows if _normalize_book_id(r.get("kotob_id")) == book_id]

    if not selected:
        _eprint(f"Error: no rows found in content for book_id={book_id}")
        conn.close()
        return 3

    selected.sort(key=lambda r: (_as_int(r.get("chapters_id")) or 0, str(r.get("chapters_id") or "")))

    out_path.parent.mkdir(parents=True, exist_ok=True)

    lines: list[str] = []
    lines.append("BEGIN TRANSACTION;")
    lines.append(f"DELETE FROM content WHERE kotob_id = {book_id};")

    cols_sql = ", ".join(cols)
    for row in selected:
        vals = []
        for c in cols:
            if c == "kotob_id":
                vals.append(_sql_quote(book_id))
            else:
                vals.append(_sql_quote(row.get(c)))
        lines.append(f"INSERT INTO content ({cols_sql}) VALUES ({', '.join(vals)});")

    lines.append("COMMIT;")
    out_path.write_text("\n".join(lines) + "\n", encoding="utf-8")

    print(f"Exported {len(selected)} content rows for book_id={book_id}")
    print(f"SQL file: {out_path}")

    if meta_out is not None:
        meta_out.parent.mkdir(parents=True, exist_ok=True)
        snippet = None
        if _table_exists(conn, "kotob"):
            row = conn.execute("SELECT * FROM kotob WHERE id = ? LIMIT 1", (book_id,)).fetchone()
            if row is not None:
                d = dict(row)
                snippet = {
                    "id": _as_int(d.get("id")) or book_id,
                    "title": d.get("title"),
                    "description": d.get("description"),
                    "version": d.get("latest_version") or d.get("current_version"),
                    "latest_version": d.get("latest_version"),
                    "sql_download_url": d.get("sql_download_url"),
                    "is_default": _as_int(d.get("is_default")) or 0,
                    "is_downloaded_on_device": _as_int(d.get("is_downloaded")) or 0,
                    "status": d.get("status"),
                }
        if snippet is None:
            snippet = {
                "id": book_id,
                "title": "",
                "description": "",
                "version": "",
                "latest_version": "",
                "sql_download_url": "",
                "is_default": 0,
                "is_downloaded_on_device": 0,
                "status": "active",
            }

        meta_out.write_text(json.dumps(snippet, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        print(f"Book metadata snippet: {meta_out}")

    conn.close()
    return 0


def run_inspect_sql(sql_path: Path) -> int:
    if not sql_path.exists():
        _eprint(f"Error: SQL file not found: {sql_path}")
        return 2

    text = sql_path.read_text(encoding="utf-8", errors="replace")

    begin_count = len(re.findall(r"\bBEGIN\s+TRANSACTION\b", text, flags=re.IGNORECASE))
    commit_count = len(re.findall(r"\bCOMMIT\b", text, flags=re.IGNORECASE))
    rollback_count = len(re.findall(r"\bROLLBACK\b", text, flags=re.IGNORECASE))
    delete_content_count = len(
        re.findall(r"\bDELETE\s+FROM\s+content\b", text, flags=re.IGNORECASE)
    )
    insert_content_count = len(
        re.findall(r"\bINSERT\s+INTO\s+content\b", text, flags=re.IGNORECASE)
    )

    delete_book_ids = sorted(
        {
            int(m.group(1))
            for m in re.finditer(
                r"\bDELETE\s+FROM\s+content\s+WHERE\s+kotob_id\s*=\s*(-?\d+)",
                text,
                flags=re.IGNORECASE,
            )
        }
    )

    print("== SQL Inspect ==")
    print(f"File: {sql_path}")
    print(f"- BEGIN TRANSACTION: {begin_count}")
    print(f"- COMMIT: {commit_count}")
    print(f"- ROLLBACK: {rollback_count}")
    print(f"- DELETE FROM content: {delete_content_count}")
    print(f"- INSERT INTO content: {insert_content_count}")
    print(
        "- DELETE targets (kotob_id): "
        + (", ".join(map(str, delete_book_ids)) if delete_book_ids else "none")
    )

    if insert_content_count > 0 and delete_content_count == 0:
        print("Warning: INSERT exists but DELETE for content is missing (risk of duplicates).")
    if begin_count == 0 or commit_count == 0:
        print("Warning: transaction markers are incomplete.")

    return 0


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="KDINI data operations toolkit")
    parser.add_argument(
        "--repo-root",
        default=str(Path(__file__).resolve().parent.parent),
        help="Path to kdini_manage_clone root (default: parent of tools)",
    )

    sub = parser.add_subparsers(dest="command", required=True)

    p_doctor = sub.add_parser("doctor", help="Analyze metadata + SQLite consistency")
    p_doctor.add_argument("--db", default=None, help="Path to books.db")

    p_export = sub.add_parser("export-sql", help="Export one book content from DB to SQL patch")
    p_export.add_argument("--db", required=True, help="Path to books.db")
    p_export.add_argument("--book-id", required=True, type=int, help="Book ID (kotob_id)")
    p_export.add_argument(
        "--out",
        required=True,
        help="Output SQL file path",
    )
    p_export.add_argument(
        "--meta-out",
        default=None,
        help="Optional output path for metadata JSON snippet of this book",
    )

    p_inspect = sub.add_parser("inspect-sql", help="Inspect SQL patch file quickly")
    p_inspect.add_argument("--sql", required=True, help="Path to SQL file")

    return parser


def main() -> int:
    parser = build_parser()
    args = parser.parse_args()

    repo_root = Path(args.repo_root).resolve()

    if args.command == "doctor":
        db_arg = Path(args.db).expanduser().resolve() if args.db else _pick_default_db(repo_root).resolve()
        return run_doctor(repo_root=repo_root, db_path=db_arg)

    if args.command == "export-sql":
        db_path = Path(args.db).expanduser().resolve()
        out_path = Path(args.out).expanduser().resolve()
        meta_out = Path(args.meta_out).expanduser().resolve() if args.meta_out else None
        return run_export_sql(db_path=db_path, book_id=args.book_id, out_path=out_path, meta_out=meta_out)

    if args.command == "inspect-sql":
        sql_path = Path(args.sql).expanduser().resolve()
        return run_inspect_sql(sql_path=sql_path)

    return 1


if __name__ == "__main__":
    raise SystemExit(main())
