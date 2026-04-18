#!/usr/bin/env python3
"""
convert_mysql_to_pg.py
======================
Konversi file SQL export dari phpMyAdmin (MySQL/FreeSQLDatabase)
ke format PostgreSQL yang siap diimport ke Supabase.

Cara pakai:
  python3 convert_mysql_to_pg.py backup.sql output_supabase.sql

Butuh Python 3.6+, tidak perlu install library tambahan.
"""

import sys
import re

def convert(sql: str) -> str:

    # --------------------------------------------------
    # 1. Hapus header & perintah khusus MySQL
    # --------------------------------------------------
    sql = re.sub(r'/\*!.*?\*/;?\s*\n?', '', sql, flags=re.DOTALL)
    sql = re.sub(r'--[^\n]*\n', '\n', sql)
    sql = re.sub(r'SET\s+SQL_MODE\s*=.*?;\n', '', sql, flags=re.IGNORECASE)
    sql = re.sub(r'SET\s+time_zone\s*=.*?;\n', '', sql, flags=re.IGNORECASE)
    sql = re.sub(r'SET\s+NAMES\s*.*?;\n', '', sql, flags=re.IGNORECASE)
    sql = re.sub(r'SET\s+FOREIGN_KEY_CHECKS\s*=.*?;\n', '', sql, flags=re.IGNORECASE)
    sql = re.sub(r'SET\s+UNIQUE_CHECKS\s*=.*?;\n', '', sql, flags=re.IGNORECASE)
    sql = re.sub(r'SET\s+CHARACTER_SET_CLIENT\s*=.*?;\n', '', sql, flags=re.IGNORECASE)
    sql = re.sub(r'SET\s+CHARACTER_SET_RESULTS\s*=.*?;\n', '', sql, flags=re.IGNORECASE)
    sql = re.sub(r'SET\s+COLLATION_CONNECTION\s*=.*?;\n', '', sql, flags=re.IGNORECASE)
    sql = re.sub(r'LOCK\s+TABLES.*?;\n', '', sql, flags=re.IGNORECASE)
    sql = re.sub(r'UNLOCK\s+TABLES.*?;\n', '', sql, flags=re.IGNORECASE)

    # --------------------------------------------------
    # 2. Hapus opsi tabel MySQL di akhir CREATE TABLE
    # --------------------------------------------------
    sql = re.sub(r'\)\s*ENGINE\s*=\s*\w+[^;]*;', ');', sql, flags=re.IGNORECASE)
    sql = re.sub(r'AUTO_INCREMENT\s*=\s*\d+', '', sql, flags=re.IGNORECASE)
    sql = re.sub(r'DEFAULT\s+CHARSET\s*=\s*[\w]+', '', sql, flags=re.IGNORECASE)
    sql = re.sub(r'COLLATE\s*=?\s*[\w]+', '', sql, flags=re.IGNORECASE)
    sql = re.sub(r'ROW_FORMAT\s*=\s*\w+', '', sql, flags=re.IGNORECASE)
    sql = re.sub(r'COMMENT\s*=\s*\'[^\']*\'', '', sql, flags=re.IGNORECASE)

    # --------------------------------------------------
    # 3. Tipe data MySQL → PostgreSQL
    # --------------------------------------------------
    # AUTO_INCREMENT → SERIAL (harus duluan)
    sql = re.sub(
        r'\bint\s*\(\d+\)\s+NOT\s+NULL\s+AUTO_INCREMENT',
        'SERIAL NOT NULL',
        sql, flags=re.IGNORECASE
    )
    sql = re.sub(
        r'\bint\s*\(\d+\)\s+AUTO_INCREMENT',
        'SERIAL',
        sql, flags=re.IGNORECASE
    )
    sql = re.sub(r'\bAUTO_INCREMENT\b', '', sql, flags=re.IGNORECASE)

    # Integer types
    sql = re.sub(r'\bTINYINT\s*\(\d+\)', 'SMALLINT', sql, flags=re.IGNORECASE)
    sql = re.sub(r'\bSMALLINT\s*\(\d+\)', 'SMALLINT', sql, flags=re.IGNORECASE)
    sql = re.sub(r'\bMEDIUMINT\s*\(\d+\)', 'INTEGER', sql, flags=re.IGNORECASE)
    sql = re.sub(r'\bBIGINT\s*\(\d+\)', 'BIGINT', sql, flags=re.IGNORECASE)
    sql = re.sub(r'\bINT\s*\(\d+\)', 'INTEGER', sql, flags=re.IGNORECASE)
    sql = re.sub(r'\bINTEGER\s*\(\d+\)', 'INTEGER', sql, flags=re.IGNORECASE)

    # String types
    sql = re.sub(r'\bTINYTEXT\b', 'TEXT', sql, flags=re.IGNORECASE)
    sql = re.sub(r'\bMEDIUMTEXT\b', 'TEXT', sql, flags=re.IGNORECASE)
    sql = re.sub(r'\bLONGTEXT\b', 'TEXT', sql, flags=re.IGNORECASE)

    # Date/Time types
    sql = re.sub(r'\bDATETIME\b', 'TIMESTAMP', sql, flags=re.IGNORECASE)

    # Numeric types
    sql = re.sub(r'\bDOUBLE\b', 'DOUBLE PRECISION', sql, flags=re.IGNORECASE)
    sql = re.sub(r'\bFLOAT\b', 'REAL', sql, flags=re.IGNORECASE)

    # --------------------------------------------------
    # 4. Backtick → double-quote (identifier PostgreSQL)
    # --------------------------------------------------
    sql = re.sub(r'`(\w+)`', r'"\1"', sql)

    # --------------------------------------------------
    # 5. Nilai default tidak valid di PostgreSQL
    # --------------------------------------------------
    sql = re.sub(r"DEFAULT\s+'0000-00-00 00:00:00'", 'DEFAULT NULL', sql, flags=re.IGNORECASE)
    sql = re.sub(r"DEFAULT\s+'0000-00-00'", 'DEFAULT NULL', sql, flags=re.IGNORECASE)

    # --------------------------------------------------
    # 6. Hapus KEY / INDEX inline di CREATE TABLE
    #    (PostgreSQL pakai CREATE INDEX terpisah)
    # --------------------------------------------------
    sql = re.sub(r'^\s*(KEY|INDEX)\s+`?\w+`?\s*\(.*?\),?\s*$', '', sql, flags=re.MULTILINE | re.IGNORECASE)

    # --------------------------------------------------
    # 7. Bersihkan trailing koma sebelum tutup kurung
    # --------------------------------------------------
    sql = re.sub(r',\s*\n\s*\)', '\n)', sql)

    # --------------------------------------------------
    # 8. Tambah header PostgreSQL
    # --------------------------------------------------
    header = """-- ============================================================
-- File ini dikonversi dari MySQL ke PostgreSQL
-- Siap diimport ke Supabase SQL Editor
-- ============================================================

SET client_encoding = 'UTF8';

"""
    sql = header + sql.strip()

    # Bersihkan baris kosong berlebih
    sql = re.sub(r'\n{3,}', '\n\n', sql)

    return sql


if __name__ == '__main__':
    if len(sys.argv) < 3:
        print("Cara pakai: python3 convert_mysql_to_pg.py backup.sql output_supabase.sql")
        sys.exit(1)

    input_file  = sys.argv[1]
    output_file = sys.argv[2]

    with open(input_file, 'r', encoding='utf-8', errors='ignore') as f:
        mysql_sql = f.read()

    pg_sql = convert(mysql_sql)

    with open(output_file, 'w', encoding='utf-8') as f:
        f.write(pg_sql)

    print(f"✅ Konversi selesai!")
    print(f"   Input  : {input_file}")
    print(f"   Output : {output_file}")
    print(f"   Selanjutnya: paste isi {output_file} ke Supabase SQL Editor → Run")
