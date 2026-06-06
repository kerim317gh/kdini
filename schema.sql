
BEGIN TRANSACTION;
CREATE TABLE IF NOT EXISTS "book_categories" (
	"book_id"	INTEGER,
	"category_id"	INTEGER
);
CREATE TABLE IF NOT EXISTS "categories" (
	"id"	INTEGER,
	"title"	TEXT, sort_order INTEGER, icon TEXT,
	PRIMARY KEY("id")
);
CREATE TABLE IF NOT EXISTS "chapters" (
	"id"	INTEGER,
	"title"	TEXT,
	"parent_id"	INTEGER,
	"category_id"	INTEGER, icon TEXT, title_fa TEXT, title_en TEXT, title_tr TEXT, title_ru TEXT, title_tk TEXT,
	PRIMARY KEY("id"),
	FOREIGN KEY("category_id") REFERENCES "categories"("id"),
	FOREIGN KEY("parent_id") REFERENCES "chapters"("id")
);
CREATE TABLE IF NOT EXISTS "cities" (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    time_offset REAL NOT NULL
, lat REAL, lng REAL, timezone TEXT, country_code TEXT, province TEXT);
CREATE TABLE IF NOT EXISTS "content" (
	"chapters_id"	INTEGER,
	"text"	TEXT,
	"text_fa"	TEXT,
	"text_turkmen"	TEXT
, "kotob_id"	INTEGER, text_en TEXT, text_tr TEXT, text_ru TEXT);
CREATE TABLE IF NOT EXISTS content_audio (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  kotob_id INTEGER NOT NULL,
  chapters_id INTEGER NOT NULL,
  lang TEXT NOT NULL,
  narrator TEXT,
  title TEXT,
  url TEXT NOT NULL,
  checksum TEXT,
  bytes INTEGER,
  duration_ms INTEGER,
  local_path TEXT,
  is_downloaded INTEGER NOT NULL DEFAULT 0,
  selected INTEGER NOT NULL DEFAULT 0,
  created_at INTEGER
, image_url TEXT);
CREATE TABLE IF NOT EXISTS "dua" (
	"id"	INTEGER NOT NULL,
	"title"	TEXT,
	"id_parent"	INTEGER,
	"image"	TEXT,
	"tarjome"	TEXT, favorite INTEGER DEFAULT 0, onvan TEXT,
	PRIMARY KEY("id")
);
CREATE TABLE IF NOT EXISTS events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  hijri_month INTEGER ,
  hijri_day INTEGER ,
  title TEXT ,
  description TEXT
, daily_note TEXT, daily_dua TEXT, is_holiday INTEGER DEFAULT 0);
CREATE TABLE IF NOT EXISTS joze30_audio (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    surah_id INTEGER NOT NULL,
    type TEXT NOT NULL,
    ayah_number INTEGER,
    reciter TEXT,
    url TEXT,
    local_path TEXT,
    is_downloaded INTEGER DEFAULT 0,
    timings TEXT, lang TEXT NOT NULL DEFAULT 'ar', bytes INTEGER, duration_ms INTEGER, created_at INTEGER, image_url TEXT,
    FOREIGN KEY (surah_id) REFERENCES joze30_surahs(surah_id)
);
CREATE TABLE IF NOT EXISTS joze30_ayahs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    surah_id INTEGER NOT NULL,
    ayah_number INTEGER NOT NULL,
    text TEXT NOT NULL,
    FOREIGN KEY (surah_id) REFERENCES joze30_surahs(surah_id)
);
CREATE TABLE IF NOT EXISTS joze30_surahs (
    surah_id INTEGER PRIMARY KEY,
    name_ar   TEXT NOT NULL,
    name_fa   TEXT,
    total_ayat INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS "kotob" (
    "id" INTEGER PRIMARY KEY,
    "title" TEXT,
    "description" TEXT,
    "current_version" TEXT,
    "latest_version" TEXT,
    "sql_download_url" TEXT,
    "is_default" INTEGER DEFAULT 0,
    "is_downloaded" INTEGER DEFAULT 0,
    "cover_image_url" TEXT
, status TEXT);
CREATE TABLE IF NOT EXISTS "maktab_audio" (
	"id"	INTEGER,
	"lesson_id"	INTEGER NOT NULL,
	"reciter"	TEXT,
	"type"	TEXT,
	"url"	TEXT,
	"timings"	TEXT,
	"local_path"	TEXT,
	"is_downloaded"	INTEGER DEFAULT 0,
	"bytes"	INTEGER,
	"selected"	INTEGER DEFAULT 0,
	PRIMARY KEY("id")
);
CREATE TABLE IF NOT EXISTS maktab_lessons (
    id INTEGER PRIMARY KEY,
    level_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    content_text TEXT,
    sort_order INTEGER DEFAULT 0
);
CREATE TABLE IF NOT EXISTS oqat (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    month INTEGER,
    day INTEGER,
    emsak TEXT,
    fajr TEXT,
    sunrise TEXT,
    dhuhr TEXT,
    asr TEXT,
    maghrib TEXT,
    isha TEXT
);
CREATE TABLE IF NOT EXISTS solar_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    solar_month INTEGER NOT NULL CHECK(solar_month BETWEEN 1 AND 12),
    solar_day INTEGER NOT NULL CHECK(solar_day BETWEEN 1 AND 31),
    title TEXT,
    is_holiday INTEGER DEFAULT 0,
    daily_note TEXT,
    daily_dua TEXT
);
CREATE INDEX idx_cities_latlng ON cities(lat, lng);
CREATE INDEX idx_cities_name ON cities(name);
CREATE INDEX idx_cities_province ON cities(province);
CREATE INDEX idx_content_audio_lookup ON content_audio(kotob_id, chapters_id, lang);
CREATE UNIQUE INDEX ux_content_audio_item ON content_audio(kotob_id, chapters_id, lang, url);
COMMIT;
