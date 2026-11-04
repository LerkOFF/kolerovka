<?php
declare(strict_types=1);

namespace AgeCMS\Modules\catalog;

use AgeCMS\Cache;
use AgeCMS\Database;
use AgeCMS\ModuleSettings;
use AgeCMS\ModuleSitemap;
use PDO;
use Throwable;

require_once __DIR__ . '/Dict.php';

/**
 * Каталог красок: чтение, запись и публичное API для блоков темы.
 * Бренды, линейки и типы ведутся в справочниках (класс Dict): названия на сайте и в формах берутся оттуда.
 * Строка товара в API: id, slug, brand, name, line, type, finish, base, use, description, tint, featured, visible, in_stock,
 * color, images (список), specs (словарь), source_url, price_from (int|null), variants (только у item()).
 */
final class Catalog
{
    public const PER_PAGE = 24;
    public const ADMIN_PER_PAGE = 50;
    /** Ключи палитры плиток-заглушек: цвета задаёт тема классами tile-<ключ> (inline style запрещён CSP). */
    public const PALETTE = ['stone' => 'Камень', 'linen' => 'Лён', 'grey' => 'Серый', 'slate' => 'Сланец', 'taupe' => 'Тоуп', 'olive' => 'Олива', 'wood' => 'Дерево', 'graphite' => 'Графит', 'blue' => 'Синий', 'green' => 'Зелёный', 'red' => 'Красный', 'yellow' => 'Жёлтый'];
    public const STOCK = [0 => 'Нет в наличии', 1 => 'В наличии', 2 => 'Под заказ'];

    private const SELECT = 'p.*, (SELECT MIN(v.price) FROM mod_catalog_variants v WHERE v.product_id = p.id AND v.price > 0) AS price_from';

    public static function setting(string $key, mixed $fallback = null): mixed
    {
        try {
            return ModuleSettings::get('catalog', $key, $fallback);
        } catch (Throwable) {
            return $fallback;
        }
    }

    /** @param array<string,mixed> $r @return array<string,mixed> */
    public static function row(array $r): array
    {
        $images = json_decode((string)($r['images'] ?? '[]'), true);
        $specs = json_decode((string)($r['specs'] ?? '{}'), true);
        $r['images'] = is_array($images) ? array_values(array_filter($images, 'is_string')) : [];
        $r['specs'] = is_array($specs) ? $specs : [];
        foreach (['id', 'tint', 'featured', 'visible', 'in_stock', 'sort', 'created_at', 'updated_at'] as $k) {
            $r[$k] = (int)($r[$k] ?? 0);
        }
        $r['price_from'] = isset($r['price_from']) && $r['price_from'] !== null ? (int)$r['price_from'] : null;
        return $r;
    }

    /**
     * Список с фильтрами. Ключи $f: q, brand, line, type, tint ('1'|'0'), visible ('1'|'0'), noprice ('1').
     * Порядок: сначала колеруемые, потом прочие, внутри по sort и номеру.
     *
     * @param array<string,mixed> $f
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public static function list(array $f, int $page, int $per, string $order = 'p.tint DESC, p.sort, p.id'): array
    {
        $where = ['1 = 1'];
        $params = [];
        $q = mb_strtolower(trim((string)($f['q'] ?? '')));
        if ($q !== '') {
            $where[] = "p.search LIKE ? ESCAPE '\\'";
            $params[] = '%' . addcslashes($q, '%_\\') . '%';
        }
        if (($f['brand'] ?? '') !== '') {
            $where[] = 'p.brand = ?';
            $params[] = (string)$f['brand'];
        }
        if (($f['line'] ?? '') !== '') {
            $where[] = 'p.line = ?';
            $params[] = (string)$f['line'];
        }
        if (isset(Dict::typeNames()[(string)($f['type'] ?? '')])) {
            $where[] = 'p.type = ?';
            $params[] = (string)$f['type'];
        }
        foreach (['tint', 'visible'] as $k) {
            if (in_array((string)($f[$k] ?? ''), ['0', '1'], true)) {
                $where[] = "p.{$k} = ?";
                $params[] = (int)$f[$k];
            }
        }
        if (($f['noprice'] ?? '') === '1') {
            $where[] = 'NOT EXISTS (SELECT 1 FROM mod_catalog_variants v WHERE v.product_id = p.id AND v.price > 0)';
        }
        $w = implode(' AND ', $where);
        $total = (int)Database::value("SELECT COUNT(*) FROM mod_catalog_products p WHERE {$w}", $params);
        $rows = Database::rows('SELECT ' . self::SELECT . " FROM mod_catalog_products p WHERE {$w} ORDER BY {$order} LIMIT " . max(1, $per) . ' OFFSET ' . (max(1, $page) - 1) * max(1, $per), $params);
        return ['items' => array_map([self::class, 'row'], $rows), 'total' => $total];
    }

    /** @return array<string,mixed>|null с вариантами */
    public static function item(string $slug, bool $onlyVisible = true): ?array
    {
        $r = Database::row('SELECT ' . self::SELECT . ' FROM mod_catalog_products p WHERE p.slug = ?' . ($onlyVisible ? ' AND p.visible = 1' : ''), [$slug]);
        return $r === null ? null : self::withVariants(self::row($r));
    }

    /** @return array<string,mixed>|null */
    public static function byId(int $id): ?array
    {
        $r = Database::row('SELECT ' . self::SELECT . ' FROM mod_catalog_products p WHERE p.id = ?', [$id]);
        return $r === null ? null : self::withVariants(self::row($r));
    }

    /** @param array<string,mixed> $p @return array<string,mixed> */
    private static function withVariants(array $p): array
    {
        $p['variants'] = Database::rows('SELECT label, price FROM mod_catalog_variants WHERE product_id = ? ORDER BY sort, id', [$p['id']]);
        return $p;
    }

    /** Витрина главной: featured и колеруемые, добавляем свежими колеруемыми. @return list<array<string,mixed>> */
    public static function featured(int $n): array
    {
        $n = max(1, $n);
        $rows = Database::rows('SELECT ' . self::SELECT . ' FROM mod_catalog_products p WHERE p.visible = 1 AND p.tint = 1 ORDER BY p.featured DESC, p.sort, p.updated_at DESC, p.id LIMIT ' . $n);
        return array_map([self::class, 'row'], $rows);
    }

    /** @return list<string> бренды для блока на странице колеровки: из справочника, с флагом и с показанным колеруемым товаром */
    public static function tintBrands(): array
    {
        return Dict::tintBrands();
    }

    /** @return list<string> бренды из справочника по порядку; без $all только те, что включены в фильтр каталога и имеют показанные товары */
    public static function brands(bool $all = false, bool $tintOnly = false): array
    {
        return $all ? Dict::brandNames() : Dict::filterBrands($tintOnly);
    }

    /** Строка для поиска по товару: нижний регистр, бренд, название, линейка, применение. */
    public static function searchText(string $brand, string $name, string $line, string $use): string
    {
        return mb_strtolower($brand . ' ' . $name . ' ' . $line . ' ' . $use);
    }

    public static function count(): int
    {
        return (int)Database::value('SELECT COUNT(*) FROM mod_catalog_products');
    }

    /** Карточка товара для сетки: та же вёрстка в каталоге и на главной. @param array<string,mixed> $p */
    public static function card(array $p): string
    {
        $badge = '';
        if (self::setting('show_tint_badge', true) === true) {
            $badge = $p['tint'] ? '<span class="badge badge-on">Доступна колеровка</span>' : '<span class="badge badge-off">Без колеровки</span>';
        }
        $price = '';
        if (self::setting('show_prices', false) === true && $p['price_from'] !== null) {
            $price = '<span class="card-meta card-price">от ' . self::money($p['price_from']) . '</span>';
        }
        $tile = $p['images'] !== []
            ? '<span class="card-tile has-photo" aria-hidden="true"><img src="' . ag_e($p['images'][0]) . '" alt="" loading="lazy"><span class="card-brand">' . ag_e($p['brand']) . '</span></span>'
            : '<span class="card-tile tile-' . self::tile((string)$p['color']) . '" aria-hidden="true"><span class="card-brand">' . ag_e($p['brand']) . '</span></span>';
        return '<a class="card" href="/katalog/' . ag_e($p['slug']) . '">' . $tile
            . '<span class="card-body"><span class="card-title">' . ag_e($p['name']) . '</span>'
            . '<span class="card-meta">' . ag_e(trim(Dict::typeName((string)$p['type']) . ($p['finish'] !== '' ? ', ' . mb_strtolower((string)$p['finish']) : ''), ', ')) . '</span>'
            . $price . $badge . '</span></a>';
    }

    public static function money(int $rub): string
    {
        return number_format($rub, 0, ',', "\u{00A0}") . "\u{00A0}₽";
    }

    /** Ключ палитры; всё незнакомое (в том числе старый hex) становится stone. */
    public static function tile(string $c): string
    {
        $c = strtolower(trim($c));
        return isset(self::PALETTE[$c]) ? $c : 'stone';
    }

    /**
     * Создаёт или обновляет товар по адресу и заменяет его варианты. Возвращает id.
     * Бренд и линейка, которых нет в справочнике, добавляются в него сами.
     * Поля $product: slug (необязателен), brand, name (обязательно), line, type (ключ или название типа), finish, base, use, description, tint, featured,
     * visible, in_stock, color, images (список), specs (словарь), source_url, sort.
     *
     * @param array<string,mixed> $product
     * @param list<array{label:string,price:int}>|null $variants null оставляет варианты как есть
     */
    public static function upsert(array $product, ?array $variants, bool $flush = true): int
    {
        $name = trim((string)($product['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Название товара обязательно');
        }
        $brand = trim((string)($product['brand'] ?? ''));
        $type = Dict::resolveType((string)($product['type'] ?? '')) ?: 'interior';
        $images = array_values(array_filter(array_map('strval', (array)($product['images'] ?? [])), static fn(string $u): bool => preg_match('~^(/|https://)[^\s"<>]+$~', $u) === 1));
        $specs = [];
        foreach ((array)($product['specs'] ?? []) as $k => $v) {
            if (trim((string)$k) !== '' && trim((string)$v) !== '') {
                $specs[trim((string)$k)] = trim((string)$v);
            }
        }
        $line = trim((string)($product['line'] ?? ''));
        $use = trim((string)($product['use'] ?? ''));
        $now = time();
        $slugIn = strtolower(trim((string)($product['slug'] ?? '')));
        if ($slugIn !== '' && preg_match('/^[a-z0-9-]{1,64}$/', $slugIn) !== 1) {
            $slugIn = Slug::make($slugIn);
        }
        $id = (int)($product['id'] ?? 0);

        $result = Database::write(function (PDO $pdo) use ($product, $variants, $name, $brand, $type, $images, $specs, $line, $use, $now, $slugIn, $id): array {
            $existing = null;
            if ($id > 0) {
                $s = $pdo->prepare('SELECT id, slug FROM mod_catalog_products WHERE id = ?');
                $s->execute([$id]);
                $existing = $s->fetch(PDO::FETCH_ASSOC) ?: null;
            } elseif ($slugIn !== '') {
                $s = $pdo->prepare('SELECT id, slug FROM mod_catalog_products WHERE slug = ?');
                $s->execute([$slugIn]);
                $existing = $s->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            $taken = static function (string $slug) use ($pdo, $existing): bool {
                $s = $pdo->prepare('SELECT id FROM mod_catalog_products WHERE slug = ?');
                $s->execute([$slug]);
                $found = $s->fetchColumn();
                return $found !== false && (int)$found !== (int)($existing['id'] ?? 0);
            };
            [$brand, $line] = Dict::ensure($pdo, $brand, $line);
            $slug = $slugIn !== '' ? Slug::unique($slugIn, $taken) : ($existing !== null ? (string)$existing['slug'] : Slug::unique(Slug::make($brand . ' ' . $name), $taken));
            $values = [
                $slug, $brand, $name, $line, $type, trim((string)($product['finish'] ?? '')), trim((string)($product['base'] ?? '')), $use,
                trim((string)($product['description'] ?? '')), !empty($product['tint']) ? 1 : 0, !empty($product['featured']) ? 1 : 0,
                array_key_exists('visible', $product) && empty($product['visible']) ? 0 : 1,
                max(0, min(2, (int)($product['in_stock'] ?? 1))), self::tile((string)($product['color'] ?? '')),
                json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), json_encode((object)$specs, JSON_UNESCAPED_UNICODE),
                trim((string)($product['source_url'] ?? '')), self::searchText($brand, $name, $line, $use), (int)($product['sort'] ?? 0), $now,
            ];
            $new = $existing === null;
            if ($new) {
                $pdo->prepare('INSERT INTO mod_catalog_products (slug, brand, name, line, type, finish, base, use, description, tint, featured, visible, in_stock, color, images, specs, source_url, search, sort, updated_at, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([...$values, $now]);
                $pid = (int)$pdo->lastInsertId();
            } else {
                $pdo->prepare('UPDATE mod_catalog_products SET slug=?, brand=?, name=?, line=?, type=?, finish=?, base=?, use=?, description=?, tint=?, featured=?, visible=?, in_stock=?, color=?, images=?, specs=?, source_url=?, search=?, sort=?, updated_at=? WHERE id=?')
                    ->execute([...$values, (int)$existing['id']]);
                $pid = (int)$existing['id'];
            }
            if ($variants !== null) {
                $pdo->prepare('DELETE FROM mod_catalog_variants WHERE product_id = ?')->execute([$pid]);
                $ins = $pdo->prepare('INSERT INTO mod_catalog_variants (product_id, label, price, sort) VALUES (?,?,?,?)');
                foreach (array_values($variants) as $i => $v) {
                    $label = trim((string)($v['label'] ?? ''));
                    if ($label !== '') {
                        $ins->execute([$pid, mb_substr($label, 0, 40), max(0, (int)($v['price'] ?? 0)), $i]);
                    }
                }
            }
            return ['id' => $pid, 'slug' => $slug, 'new' => $new];
        });

        if ($flush) {
            self::flush($result['new'] ? [$result['slug']] : []);
        }
        return (int)$result['id'];
    }

    /**
     * Загрузка строк CSV или демо-данных: строки с одним slug склеиваются в один товар с вариантами.
     * Строка: поля товара плюс volume и price (вариант). Возвращает created, updated и errors (список «строка N: причина»).
     *
     * @param list<array<string,mixed>> $rows
     * @return array{created:int,updated:int,errors:list<string>}
     */
    public static function importRows(array $rows): array
    {
        $groups = [];
        $errors = [];
        foreach ($rows as $n => $row) {
            $line = (int)($row['_line'] ?? $n + 1);
            $name = trim((string)($row['name'] ?? ''));
            $slug = trim((string)($row['slug'] ?? ''));
            if ($name === '' && $slug === '') {
                $errors[] = "строка {$line}: нет названия";
                continue;
            }
            $key = $slug !== '' ? 'slug:' . $slug : 'name:' . mb_strtolower(trim((string)($row['brand'] ?? '')) . ' ' . $name);
            if (!isset($groups[$key])) {
                $groups[$key] = ['product' => $row, 'variants' => null, 'line' => $line];
            }
            $variants = $row['variants'] ?? null;
            if (is_array($variants)) {
                $groups[$key]['variants'] = array_merge($groups[$key]['variants'] ?? [], $variants);
            } elseif (trim((string)($row['volume'] ?? '')) !== '') {
                $groups[$key]['variants'][] = ['label' => trim((string)$row['volume']), 'price' => (int)preg_replace('/\D/', '', (string)($row['price'] ?? '0'))];
            }
        }
        $created = $updated = 0;
        $touched = [];
        foreach ($groups as $g) {
            try {
                $p = $g['product'];
                $slugIn = strtolower(trim((string)($p['slug'] ?? '')));
                $cur = $slugIn !== '' ? Database::row('SELECT * FROM mod_catalog_products WHERE slug = ?', [$slugIn]) : null;
                $existed = $cur !== null;
                if ($cur !== null) {
                    // Колонки, которых нет в файле или пустые (например только slug и цена), остаются как были.
                    $cur = self::row($cur);
                    foreach (['brand', 'name', 'line', 'type', 'finish', 'base', 'use', 'description', 'tint', 'featured', 'visible', 'in_stock', 'color', 'images', 'specs', 'source_url', 'sort'] as $k) {
                        if (!array_key_exists($k, $p) || (is_string($p[$k]) && trim($p[$k]) === '')) {
                            $p[$k] = $cur[$k];
                        }
                    }
                } elseif (trim((string)($p['name'] ?? '')) === '') {
                    throw new \InvalidArgumentException('нет названия');
                }
                self::upsert($p, $g['variants'], false);
                $existed ? $updated++ : $created++;
                $touched[] = true;
            } catch (Throwable $e) {
                $errors[] = 'строка ' . $g['line'] . ': ' . $e->getMessage();
            }
        }
        if ($touched !== []) {
            self::flush([]);
        }
        return ['created' => $created, 'updated' => $updated, 'errors' => $errors];
    }

    /** @param list<int> $ids */
    public static function bulk(string $action, array $ids, int $percent = 0): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $i): bool => $i > 0)));
        if ($ids === []) {
            return 0;
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $now = time();
        $n = Database::write(function (PDO $pdo) use ($action, $ids, $marks, $percent, $now): int {
            $run = static function (string $sql, array $params) use ($pdo): int {
                $s = $pdo->prepare($sql);
                $s->execute($params);
                return $s->rowCount();
            };
            return match ($action) {
                'show' => $run("UPDATE mod_catalog_products SET visible = 1, updated_at = ? WHERE id IN ({$marks})", [$now, ...$ids]),
                'hide' => $run("UPDATE mod_catalog_products SET visible = 0, updated_at = ? WHERE id IN ({$marks})", [$now, ...$ids]),
                'tint_on' => $run("UPDATE mod_catalog_products SET tint = 1, updated_at = ? WHERE id IN ({$marks})", [$now, ...$ids]),
                'tint_off' => $run("UPDATE mod_catalog_products SET tint = 0, updated_at = ? WHERE id IN ({$marks})", [$now, ...$ids]),
                'price' => $run("UPDATE mod_catalog_variants SET price = MAX(1, CAST(ROUND(price * (100 + ?) / 100.0) AS INTEGER)) WHERE price > 0 AND product_id IN ({$marks})", [$percent, ...$ids]),
                'delete' => (function () use ($run, $marks, $ids): int {
                    $run("DELETE FROM mod_catalog_variants WHERE product_id IN ({$marks})", $ids);
                    return $run("DELETE FROM mod_catalog_products WHERE id IN ({$marks})", $ids);
                })(),
                default => 0,
            };
        });
        self::flush([]);
        return (int)$n;
    }

    /** Единственное место, где после записи сбрасывается кэш страниц (блоки темы читают каталог). */
    public static function flush(array $newSlugs): void
    {
        Dict::reset();
        try {
            Cache::invalidate(['all']);
        } catch (Throwable) {
        }
        if ($newSlugs !== []) {
            try {
                ModuleSitemap::ping('catalog', ['/katalog', ...array_map(static fn(string $s): string => '/katalog/' . $s, array_slice($newSlugs, 0, 50))]);
            } catch (Throwable) {
            }
        }
    }

    /** @return list<array{path:string,lastmod:string}> */
    public static function sitemap(int $limit): array
    {
        $out = [['path' => '/katalog', 'lastmod' => gmdate('Y-m-d', (int)(Database::value('SELECT MAX(updated_at) FROM mod_catalog_products WHERE visible = 1') ?: time()))]];
        foreach (Database::rows('SELECT slug, updated_at FROM mod_catalog_products WHERE visible = 1 ORDER BY updated_at DESC, id LIMIT ' . max(1, $limit - 1)) as $r) {
            $out[] = ['path' => '/katalog/' . $r['slug'], 'lastmod' => gmdate('Y-m-d', (int)$r['updated_at'])];
        }
        return $out;
    }
}
