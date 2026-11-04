-- Каталог красок: товары и варианты фасовки с ценой.
CREATE TABLE mod_catalog_products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL,
    brand TEXT NOT NULL DEFAULT '',
    name TEXT NOT NULL,
    line TEXT NOT NULL DEFAULT '',
    type TEXT NOT NULL DEFAULT 'interior',
    finish TEXT NOT NULL DEFAULT '',
    base TEXT NOT NULL DEFAULT '',
    use TEXT NOT NULL DEFAULT '',
    description TEXT NOT NULL DEFAULT '',
    tint INTEGER NOT NULL DEFAULT 0,
    featured INTEGER NOT NULL DEFAULT 0,
    visible INTEGER NOT NULL DEFAULT 1,
    in_stock INTEGER NOT NULL DEFAULT 1,
    color TEXT NOT NULL DEFAULT 'stone',
    images TEXT NOT NULL DEFAULT '[]',
    specs TEXT NOT NULL DEFAULT '{}',
    source_url TEXT NOT NULL DEFAULT '',
    search TEXT NOT NULL DEFAULT '',
    sort INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL DEFAULT 0,
    updated_at INTEGER NOT NULL DEFAULT 0
);
CREATE UNIQUE INDEX mod_catalog_products_slug ON mod_catalog_products (slug);
CREATE INDEX mod_catalog_products_list ON mod_catalog_products (visible, tint, sort, id);
CREATE INDEX mod_catalog_products_brand ON mod_catalog_products (brand, visible);
CREATE INDEX mod_catalog_products_type ON mod_catalog_products (type, visible);

CREATE TABLE mod_catalog_variants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER NOT NULL,
    label TEXT NOT NULL,
    price INTEGER NOT NULL DEFAULT 0,
    sort INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX mod_catalog_variants_product ON mod_catalog_variants (product_id, sort);
