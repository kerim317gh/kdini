#!/usr/bin/env python3
"""
استخراج محتوای یک کتاب از جدول content و ذخیره به‌صورت فایل SQL

استفاده:
    python export_content.py --db مسیر/دیتابیس.db --book-id 5 --output book_5_content.sql
"""

import sqlite3
import argparse
import sys

def main():
    parser = argparse.ArgumentParser(description='خروجی گرفتن از جدول content برای یک کتاب')
    parser.add_argument('--db', required=True, help='مسیر فایل دیتابیس مادر')
    parser.add_argument('--book-id', type=int, required=True, help='شناسه کتاب (kotob_id)')
    parser.add_argument('--output', required=True, help='مسیر فایل SQL خروجی')
    args = parser.parse_args()

    # اتصال به دیتابیس مادر
    conn = sqlite3.connect(args.db)
    conn.row_factory = sqlite3.Row

    # فقط ستون‌های سازگار با اپ را صادر کن
    table_info = conn.execute("PRAGMA table_info(content)").fetchall()
    existing_columns = {row['name'] for row in table_info}
    preferred_columns = [
        'chapters_id',
        'text',
        'text_fa',
        'text_turkmen',
        'kotob_id',
    ]
    export_columns = [col for col in preferred_columns if col in existing_columns]

    if 'chapters_id' not in export_columns or 'text' not in export_columns or 'kotob_id' not in export_columns:
        print('ستون‌های ضروری content برای خروجی در دیتابیس یافت نشد.')
        conn.close()
        sys.exit(1)

    columns_sql = ', '.join(export_columns)
    rows = conn.execute(
        f"SELECT {columns_sql} FROM content WHERE kotob_id = ? ORDER BY chapters_id ASC",
        (args.book_id,)
    ).fetchall()

    if not rows:
        print(f"هیچ محتوایی برای کتاب با شناسه {args.book_id} یافت نشد.")
        conn.close()
        sys.exit(0)

    conn.close()

    # نوشتن فایل SQL خروجی سازگار با اپ
    with open(args.output, 'w', encoding='utf-8') as f:
        f.write("BEGIN TRANSACTION;\n")
        f.write(f"DELETE FROM content WHERE kotob_id = {args.book_id};\n\n")

        quoted_columns = ', '.join(f'"{col}"' for col in export_columns)
        for row in rows:
            row_dict = dict(row)
            values = [row_dict[col] for col in export_columns]
            escaped_values = []
            for val in values:
                if val is None:
                    escaped_values.append('NULL')
                elif isinstance(val, (int, float)):
                    escaped_values.append(str(val))
                else:
                    escaped = str(val).replace("'", "''")
                    escaped_values.append(f"'{escaped}'")
            insert_stmt = f"INSERT INTO \"content\" ({quoted_columns}) VALUES ({','.join(escaped_values)});"
            f.write(insert_stmt + '\n')

        f.write("COMMIT;\n")

    print(f"{len(rows)} ردیف از جدول content با موفقیت در {args.output} ذخیره شد.")

if __name__ == '__main__':
    main()