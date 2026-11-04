-- Справочники: бренды, линейки и типы красок. Владелец настраивает их в админке («Справочники»),
-- товары по-прежнему хранят название бренда и линейки и ключ типа.
CREATE TABLE mod_catalog_brands (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL COLLATE NOCASE,
    sort INTEGER NOT NULL DEFAULT 0,
    in_filter INTEGER NOT NULL DEFAULT 1,
    in_tint INTEGER NOT NULL DEFAULT 1,
    created_at INTEGER NOT NULL DEFAULT 0
);
CREATE UNIQUE INDEX mod_catalog_brands_name ON mod_catalog_brands (name);

CREATE TABLE mod_catalog_lines (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    brand_id INTEGER NOT NULL,
    name TEXT NOT NULL COLLATE NOCASE,
    sort INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL DEFAULT 0
);
CREATE UNIQUE INDEX mod_catalog_lines_name ON mod_catalog_lines (brand_id, name);

CREATE TABLE mod_catalog_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL,
    name TEXT NOT NULL,
    sort INTEGER NOT NULL DEFAULT 0,
    in_filter INTEGER NOT NULL DEFAULT 1
);
CREATE UNIQUE INDEX mod_catalog_types_code ON mod_catalog_types (code);

-- Наполнение из уже загруженных товаров, чтобы на живом сайте ничего не пропало.
INSERT INTO mod_catalog_brands (name, sort, in_filter, in_tint, created_at)
SELECT n, ROW_NUMBER() OVER (ORDER BY n COLLATE NOCASE) * 10, 1, 1, CAST(strftime('%s', 'now') AS INTEGER)
FROM (SELECT MIN(brand) AS n FROM mod_catalog_products WHERE brand != '' GROUP BY brand COLLATE NOCASE);

UPDATE mod_catalog_products SET brand = (SELECT b.name FROM mod_catalog_brands b WHERE b.name = mod_catalog_products.brand) WHERE brand != '';

INSERT INTO mod_catalog_lines (brand_id, name, sort, created_at)
SELECT brand_id, n, ROW_NUMBER() OVER (PARTITION BY brand_id ORDER BY n COLLATE NOCASE) * 10, CAST(strftime('%s', 'now') AS INTEGER)
FROM (
    SELECT b.id AS brand_id, MIN(p.line) AS n
    FROM mod_catalog_products p
    JOIN mod_catalog_brands b ON b.name = p.brand
    WHERE p.line != ''
    GROUP BY b.id, p.line COLLATE NOCASE
);

UPDATE mod_catalog_products SET line = (
    SELECT l.name FROM mod_catalog_lines l JOIN mod_catalog_brands b ON b.id = l.brand_id
    WHERE b.name = mod_catalog_products.brand AND l.name = mod_catalog_products.line
) WHERE line != '' AND EXISTS (
    SELECT 1 FROM mod_catalog_lines l JOIN mod_catalog_brands b ON b.id = l.brand_id
    WHERE b.name = mod_catalog_products.brand AND l.name = mod_catalog_products.line
);

INSERT INTO mod_catalog_types (code, name, sort, in_filter) VALUES ('interior', 'Интерьерная', 10, 1);
INSERT INTO mod_catalog_types (code, name, sort, in_filter) VALUES ('facade', 'Фасадная', 20, 1);
INSERT INTO mod_catalog_types (code, name, sort, in_filter) VALUES ('universal', 'Универсальная', 30, 1);
INSERT OR IGNORE INTO mod_catalog_types (code, name, sort, in_filter)
SELECT DISTINCT type, type, 100, 1 FROM mod_catalog_products WHERE type != '';
