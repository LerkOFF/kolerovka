<?php
declare(strict_types=1);

namespace AgeCMS\Modules\catalog;

use AgeCMS\Database;
use PDO;
use Throwable;

/**
 * Справочники каталога: бренды, линейки (привязаны к бренду) и типы. Владелец ведёт их в админке («Справочники»).
 * Товар хранит название бренда и линейки и ключ типа, поэтому переименование здесь обновляет и товары.
 * Бренд или линейка, которых нет в справочнике, создаются сами при сохранении товара и при импорте.
 */
final class Dict
{
    /** Типы, которые всегда есть в справочнике: их ключи использует импорт, удалить их нельзя. */
    public const DEFAULT_TYPES = ['interior' => 'Интерьерная', 'facade' => 'Фасадная', 'universal' => 'Универсальная'];
    public const NAME_MAX = 80;

    /** @var array<string,string>|null */
    private static ?array $types = null;

    public static function reset(): void
    {
        self::$types = null;
    }

    public static function clean(string $s, int $max = self::NAME_MAX): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $s) ?? $s), 0, $max);
    }

    // ---- чтение для сайта и форм ----

    /** @return array<string,string> ключ => название, все типы в порядке справочника */
    public static function typeNames(): array
    {
        if (self::$types === null) {
            try {
                $rows = Database::rows('SELECT code, name FROM mod_catalog_types ORDER BY sort, id');
            } catch (Throwable) {
                $rows = [];
            }
            self::$types = [];
            foreach ($rows as $r) {
                self::$types[(string)$r['code']] = (string)$r['name'];
            }
            if (self::$types === []) {
                self::$types = self::DEFAULT_TYPES;
            }
        }
        return self::$types;
    }

    public static function typeName(string $code): string
    {
        return self::typeNames()[$code] ?? '';
    }

    /** Ключ типа по ключу или по названию (для импорта); пустая строка, если такого типа нет. */
    public static function resolveType(string $value): string
    {
        $value = trim($value);
        $types = self::typeNames();
        if (isset($types[$value])) {
            return $value;
        }
        foreach ($types as $code => $name) {
            if (mb_strtolower($name) === mb_strtolower($value) || $code === strtolower($value)) {
                return (string)$code;
            }
        }
        return '';
    }

    /** @return list<string> все бренды справочника по порядку (для формы товара и админки) */
    public static function brandNames(): array
    {
        return array_map(static fn(array $r): string => (string)$r['name'], Database::rows('SELECT name FROM mod_catalog_brands ORDER BY sort, name COLLATE NOCASE, id'));
    }

    /** @return list<string> бренды для фильтра каталога: включены, и есть хотя бы один показанный товар ($tintOnly: только колеруемые, когда прочие краски скрыты) */
    public static function filterBrands(bool $tintOnly = false): array
    {
        return array_map(static fn(array $r): string => (string)$r['name'], Database::rows(
            'SELECT b.name FROM mod_catalog_brands b WHERE b.in_filter = 1 AND ' . self::shown('b.name', $tintOnly) . ' ORDER BY b.sort, b.name COLLATE NOCASE, b.id'
        ));
    }

    /** @return list<string> бренды для блока на странице колеровки: включены, и есть показанный колеруемый товар */
    public static function tintBrands(): array
    {
        return array_map(static fn(array $r): string => (string)$r['name'], Database::rows(
            'SELECT b.name FROM mod_catalog_brands b WHERE b.in_tint = 1 AND ' . self::shown('b.name', true) . ' ORDER BY b.sort, b.name COLLATE NOCASE, b.id'
        ));
    }

    /** @return array<string,string> ключ => название для фильтра каталога: включены, и есть показанный товар */
    public static function filterTypes(bool $tintOnly = false): array
    {
        $out = [];
        foreach (Database::rows('SELECT t.code, t.name FROM mod_catalog_types t WHERE t.in_filter = 1 AND EXISTS (SELECT 1 FROM mod_catalog_products p WHERE p.type = t.code AND p.visible = 1' . ($tintOnly ? ' AND p.tint = 1' : '') . ') ORDER BY t.sort, t.id') as $r) {
            $out[(string)$r['code']] = (string)$r['name'];
        }
        return $out;
    }

    /**
     * Линейки для фильтра каталога: бренд показывается в фильтре, у линейки есть показанный товар.
     * @return list<array{brand:string,name:string}>
     */
    public static function filterLines(string $brand = '', bool $tintOnly = false): array
    {
        $sql = 'SELECT b.name AS brand, l.name FROM mod_catalog_lines l JOIN mod_catalog_brands b ON b.id = l.brand_id'
            . ' WHERE b.in_filter = 1 AND EXISTS (SELECT 1 FROM mod_catalog_products p WHERE p.brand = b.name COLLATE NOCASE AND p.line = l.name COLLATE NOCASE AND p.visible = 1' . ($tintOnly ? ' AND p.tint = 1' : '') . ')';
        $params = [];
        if ($brand !== '') {
            $sql .= ' AND b.name = ?';
            $params[] = $brand;
        }
        return array_map(static fn(array $r): array => ['brand' => (string)$r['brand'], 'name' => (string)$r['name']],
            Database::rows($sql . ' ORDER BY b.sort, b.name COLLATE NOCASE, l.sort, l.name COLLATE NOCASE, l.id', $params));
    }

    /** @return list<array{id:int,brand:string,name:string}> все линейки для формы товара */
    public static function allLines(): array
    {
        return array_map(static fn(array $r): array => ['id' => (int)$r['id'], 'brand' => (string)$r['brand'], 'name' => (string)$r['name']],
            Database::rows('SELECT l.id, b.name AS brand, l.name FROM mod_catalog_lines l JOIN mod_catalog_brands b ON b.id = l.brand_id ORDER BY b.sort, b.name COLLATE NOCASE, l.sort, l.name COLLATE NOCASE, l.id'));
    }

    /** @return array<string,mixed>|null линейка с названием её бренда */
    public static function line(int $id): ?array
    {
        return Database::row('SELECT l.id, l.name, b.name AS brand FROM mod_catalog_lines l JOIN mod_catalog_brands b ON b.id = l.brand_id WHERE l.id = ?', [$id]);
    }

    /** Условие «у бренда есть показанный товар» (колеруемый, если $tint). */
    private static function shown(string $col, bool $tint = false): string
    {
        return "EXISTS (SELECT 1 FROM mod_catalog_products p WHERE p.brand = {$col} COLLATE NOCASE AND p.visible = 1" . ($tint ? ' AND p.tint = 1' : '') . ')';
    }

    // ---- запись из товара и импорта ----

    /**
     * Приводит бренд и линейку к написанию справочника и создаёт недостающие записи.
     * Вызывается внутри записи товара. Линейка без бренда в справочник не попадает.
     *
     * @return array{0:string,1:string} [бренд, линейка]
     */
    public static function ensure(PDO $pdo, string $brand, string $line): array
    {
        $brand = self::clean($brand);
        $line = self::clean($line);
        if ($brand === '') {
            return ['', $line];
        }
        $now = time();
        $s = $pdo->prepare('SELECT id, name FROM mod_catalog_brands WHERE name = ?');
        $s->execute([$brand]);
        $b = $s->fetch(PDO::FETCH_ASSOC);
        if ($b === false) {
            $pdo->prepare('INSERT INTO mod_catalog_brands (name, sort, in_filter, in_tint, created_at) VALUES (?, (SELECT COALESCE(MAX(sort), 0) + 10 FROM mod_catalog_brands), 1, 1, ?)')->execute([$brand, $now]);
            $brandId = (int)$pdo->lastInsertId();
        } else {
            $brandId = (int)$b['id'];
            $brand = (string)$b['name'];
        }
        if ($line !== '') {
            $s = $pdo->prepare('SELECT name FROM mod_catalog_lines WHERE brand_id = ? AND name = ?');
            $s->execute([$brandId, $line]);
            $found = $s->fetchColumn();
            if ($found === false) {
                $pdo->prepare('INSERT INTO mod_catalog_lines (brand_id, name, sort, created_at) VALUES (?, ?, (SELECT COALESCE(MAX(sort), 0) + 10 FROM mod_catalog_lines WHERE brand_id = ?), ?)')->execute([$brandId, $line, $brandId, $now]);
            } else {
                $line = (string)$found;
            }
        }
        return [$brand, $line];
    }

    // ---- данные для страницы «Справочники» ----

    /** @return list<array<string,mixed>> */
    public static function brandRows(): array
    {
        return Database::rows('SELECT b.*, (SELECT COUNT(*) FROM mod_catalog_products p WHERE p.brand = b.name COLLATE NOCASE) AS total, (SELECT COUNT(*) FROM mod_catalog_products p WHERE p.brand = b.name COLLATE NOCASE AND p.visible = 1) AS shown FROM mod_catalog_brands b ORDER BY b.sort, b.name COLLATE NOCASE, b.id');
    }

    /** @return list<array<string,mixed>> */
    public static function lineRows(): array
    {
        return Database::rows('SELECT l.*, b.name AS brand, (SELECT COUNT(*) FROM mod_catalog_products p WHERE p.brand = b.name COLLATE NOCASE AND p.line = l.name COLLATE NOCASE) AS total FROM mod_catalog_lines l JOIN mod_catalog_brands b ON b.id = l.brand_id ORDER BY b.sort, b.name COLLATE NOCASE, b.id, l.sort, l.name COLLATE NOCASE, l.id');
    }

    /** @return list<array<string,mixed>> */
    public static function typeRows(): array
    {
        return Database::rows('SELECT t.*, (SELECT COUNT(*) FROM mod_catalog_products p WHERE p.type = t.code) AS total FROM mod_catalog_types t ORDER BY t.sort, t.id');
    }

    // ---- правки со страницы «Справочники»: каждая возвращает ['done' => число, 'errors' => список] ----

    /**
     * Сохраняет таблицу брендов: название (переименование обновляет товары), порядок, два флага показа.
     * @param array<mixed> $rows id => [name, sort, in_filter, in_tint]
     * @return array{done:int,errors:list<string>}
     */
    public static function saveBrands(array $rows): array
    {
        $res = Database::write(static function (PDO $pdo) use ($rows): array {
            $done = 0;
            $errors = [];
            foreach ($rows as $id => $in) {
                $id = (int)$id;
                if ($id <= 0 || !is_array($in)) {
                    continue;
                }
                $s = $pdo->prepare('SELECT name, sort, in_filter, in_tint FROM mod_catalog_brands WHERE id = ?');
                $s->execute([$id]);
                $cur = $s->fetch(PDO::FETCH_ASSOC);
                if ($cur === false) {
                    continue;
                }
                $name = self::clean((string)($in['name'] ?? ''));
                $sort = self::sort($in['sort'] ?? 0);
                $filter = empty($in['in_filter']) ? 0 : 1;
                $tint = empty($in['in_tint']) ? 0 : 1;
                $rename = false;
                if ($name === '') {
                    $errors[] = 'Название бренда «' . $cur['name'] . '» не может быть пустым.';
                    $name = (string)$cur['name'];
                } elseif ($name !== $cur['name']) {
                    $u = $pdo->prepare('SELECT 1 FROM mod_catalog_brands WHERE name = ? AND id != ?');
                    $u->execute([$name, $id]);
                    if ($u->fetchColumn() !== false) {
                        $errors[] = 'Бренд «' . $name . '» уже есть, «' . $cur['name'] . '» не переименован.';
                        $name = (string)$cur['name'];
                    } else {
                        $rename = true;
                    }
                }
                if (!$rename && $sort === (int)$cur['sort'] && $filter === (int)$cur['in_filter'] && $tint === (int)$cur['in_tint']) {
                    continue;
                }
                $pdo->prepare('UPDATE mod_catalog_brands SET name = ?, sort = ?, in_filter = ?, in_tint = ? WHERE id = ?')->execute([$name, $sort, $filter, $tint, $id]);
                if ($rename) {
                    $pdo->prepare('UPDATE mod_catalog_products SET brand = ?, updated_at = ? WHERE brand = ? COLLATE NOCASE')->execute([$name, time(), $cur['name']]);
                    self::reindex($pdo, 'brand = ?', [$name]);
                }
                $done++;
            }
            return ['done' => $done, 'errors' => $errors];
        });
        self::changed();
        return $res;
    }

    /**
     * @param array<mixed> $rows id => [name, sort]
     * @return array{done:int,errors:list<string>}
     */
    public static function saveLines(array $rows): array
    {
        $res = Database::write(static function (PDO $pdo) use ($rows): array {
            $done = 0;
            $errors = [];
            foreach ($rows as $id => $in) {
                $id = (int)$id;
                if ($id <= 0 || !is_array($in)) {
                    continue;
                }
                $s = $pdo->prepare('SELECT l.name, l.sort, l.brand_id, b.name AS brand FROM mod_catalog_lines l JOIN mod_catalog_brands b ON b.id = l.brand_id WHERE l.id = ?');
                $s->execute([$id]);
                $cur = $s->fetch(PDO::FETCH_ASSOC);
                if ($cur === false) {
                    continue;
                }
                $name = self::clean((string)($in['name'] ?? ''));
                $sort = self::sort($in['sort'] ?? 0);
                $rename = false;
                if ($name === '') {
                    $errors[] = 'Название линейки «' . $cur['name'] . '» не может быть пустым.';
                    $name = (string)$cur['name'];
                } elseif ($name !== $cur['name']) {
                    $u = $pdo->prepare('SELECT 1 FROM mod_catalog_lines WHERE brand_id = ? AND name = ? AND id != ?');
                    $u->execute([$cur['brand_id'], $name, $id]);
                    if ($u->fetchColumn() !== false) {
                        $errors[] = 'У бренда ' . $cur['brand'] . ' уже есть линейка «' . $name . '», «' . $cur['name'] . '» не переименована.';
                        $name = (string)$cur['name'];
                    } else {
                        $rename = true;
                    }
                }
                if (!$rename && $sort === (int)$cur['sort']) {
                    continue;
                }
                $pdo->prepare('UPDATE mod_catalog_lines SET name = ?, sort = ? WHERE id = ?')->execute([$name, $sort, $id]);
                if ($rename) {
                    $pdo->prepare('UPDATE mod_catalog_products SET line = ?, updated_at = ? WHERE line = ? COLLATE NOCASE AND brand = ? COLLATE NOCASE')->execute([$name, time(), $cur['name'], $cur['brand']]);
                    self::reindex($pdo, 'brand = ? COLLATE NOCASE AND line = ?', [$cur['brand'], $name]);
                }
                $done++;
            }
            return ['done' => $done, 'errors' => $errors];
        });
        self::changed();
        return $res;
    }

    /**
     * Названия типов, порядок и показ в фильтре. Ключ не меняется.
     * @param array<mixed> $rows id => [name, sort, in_filter]
     * @return array{done:int,errors:list<string>}
     */
    public static function saveTypes(array $rows): array
    {
        $res = Database::write(static function (PDO $pdo) use ($rows): array {
            $done = 0;
            $errors = [];
            foreach ($rows as $id => $in) {
                $id = (int)$id;
                if ($id <= 0 || !is_array($in)) {
                    continue;
                }
                $s = $pdo->prepare('SELECT code, name, sort, in_filter FROM mod_catalog_types WHERE id = ?');
                $s->execute([$id]);
                $cur = $s->fetch(PDO::FETCH_ASSOC);
                if ($cur === false) {
                    continue;
                }
                $name = self::clean((string)($in['name'] ?? ''), 60);
                if ($name === '') {
                    $errors[] = 'Название типа «' . $cur['code'] . '» не может быть пустым.';
                    $name = (string)$cur['name'];
                }
                $sort = self::sort($in['sort'] ?? 0);
                $filter = empty($in['in_filter']) ? 0 : 1;
                if ($name === $cur['name'] && $sort === (int)$cur['sort'] && $filter === (int)$cur['in_filter']) {
                    continue;
                }
                $pdo->prepare('UPDATE mod_catalog_types SET name = ?, sort = ?, in_filter = ? WHERE id = ?')->execute([$name, $sort, $filter, $id]);
                $done++;
            }
            return ['done' => $done, 'errors' => $errors];
        });
        self::changed();
        return $res;
    }

    /** @return string пусто при успехе, иначе причина */
    public static function addBrand(string $name, ?int $sort, bool $filter, bool $tint): string
    {
        $name = self::clean($name);
        if ($name === '') {
            return 'Укажите название бренда.';
        }
        $err = Database::write(static function (PDO $pdo) use ($name, $sort, $filter, $tint): string {
            $s = $pdo->prepare('SELECT 1 FROM mod_catalog_brands WHERE name = ?');
            $s->execute([$name]);
            if ($s->fetchColumn() !== false) {
                return 'Бренд «' . $name . '» уже есть.';
            }
            $pdo->prepare('INSERT INTO mod_catalog_brands (name, sort, in_filter, in_tint, created_at) VALUES (?, COALESCE(?, (SELECT COALESCE(MAX(sort), 0) + 10 FROM mod_catalog_brands)), ?, ?, ?)')
                ->execute([$name, $sort, $filter ? 1 : 0, $tint ? 1 : 0, time()]);
            return '';
        });
        self::changed();
        return (string)$err;
    }

    public static function addLine(int $brandId, string $name, ?int $sort): string
    {
        $name = self::clean($name);
        if ($name === '') {
            return 'Укажите название линейки.';
        }
        $err = Database::write(static function (PDO $pdo) use ($brandId, $name, $sort): string {
            $b = $pdo->prepare('SELECT name FROM mod_catalog_brands WHERE id = ?');
            $b->execute([$brandId]);
            $brand = $b->fetchColumn();
            if ($brand === false) {
                return 'Выберите бренд для линейки.';
            }
            $s = $pdo->prepare('SELECT 1 FROM mod_catalog_lines WHERE brand_id = ? AND name = ?');
            $s->execute([$brandId, $name]);
            if ($s->fetchColumn() !== false) {
                return 'У бренда ' . $brand . ' уже есть линейка «' . $name . '».';
            }
            $pdo->prepare('INSERT INTO mod_catalog_lines (brand_id, name, sort, created_at) VALUES (?, ?, COALESCE(?, (SELECT COALESCE(MAX(sort), 0) + 10 FROM mod_catalog_lines WHERE brand_id = ?)), ?)')
                ->execute([$brandId, $name, $sort, $brandId, time()]);
            return '';
        });
        self::changed();
        return (string)$err;
    }

    public static function addType(string $code, string $name, ?int $sort): string
    {
        $code = strtolower(trim($code));
        $name = self::clean($name, 60);
        if (preg_match('/^[a-z][a-z0-9_-]{1,30}$/', $code) !== 1) {
            return 'Ключ типа: латинские буквы, цифры, дефис и подчёркивание, от 2 до 31 знака, начинается с буквы.';
        }
        if ($name === '') {
            return 'Укажите название типа.';
        }
        $err = Database::write(static function (PDO $pdo) use ($code, $name, $sort): string {
            $s = $pdo->prepare('SELECT 1 FROM mod_catalog_types WHERE code = ?');
            $s->execute([$code]);
            if ($s->fetchColumn() !== false) {
                return 'Тип с ключом ' . $code . ' уже есть.';
            }
            $pdo->prepare('INSERT INTO mod_catalog_types (code, name, sort, in_filter) VALUES (?, ?, COALESCE(?, (SELECT COALESCE(MAX(sort), 0) + 10 FROM mod_catalog_types)), 1)')
                ->execute([$code, $name, $sort]);
            return '';
        });
        self::changed();
        return (string)$err;
    }

    /** Бренд без товаров удаляется вместе со своими линейками. */
    public static function deleteBrand(int $id): string
    {
        $err = Database::write(static function (PDO $pdo) use ($id): string {
            $s = $pdo->prepare('SELECT name FROM mod_catalog_brands WHERE id = ?');
            $s->execute([$id]);
            $name = $s->fetchColumn();
            if ($name === false) {
                return 'Бренд не найден.';
            }
            $c = $pdo->prepare('SELECT COUNT(*) FROM mod_catalog_products WHERE brand = ? COLLATE NOCASE');
            $c->execute([$name]);
            $n = (int)$c->fetchColumn();
            if ($n > 0) {
                return 'У бренда «' . $name . '» есть товары (' . $n . '), удалить нельзя. Снимите обе галочки «показывать»: бренд пропадёт с сайта, товары останутся.';
            }
            $pdo->prepare('DELETE FROM mod_catalog_lines WHERE brand_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM mod_catalog_brands WHERE id = ?')->execute([$id]);
            return '';
        });
        self::changed();
        return (string)$err;
    }

    public static function deleteLine(int $id): string
    {
        $err = Database::write(static function (PDO $pdo) use ($id): string {
            $s = $pdo->prepare('SELECT l.name, b.name AS brand FROM mod_catalog_lines l JOIN mod_catalog_brands b ON b.id = l.brand_id WHERE l.id = ?');
            $s->execute([$id]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                return 'Линейка не найдена.';
            }
            $c = $pdo->prepare('SELECT COUNT(*) FROM mod_catalog_products WHERE brand = ? COLLATE NOCASE AND line = ? COLLATE NOCASE');
            $c->execute([$row['brand'], $row['name']]);
            $n = (int)$c->fetchColumn();
            if ($n > 0) {
                return 'В линейке «' . $row['name'] . '» бренда ' . $row['brand'] . ' есть товары (' . $n . '), удалить нельзя. Сначала перенесите товары в другую линейку.';
            }
            $pdo->prepare('DELETE FROM mod_catalog_lines WHERE id = ?')->execute([$id]);
            return '';
        });
        self::changed();
        return (string)$err;
    }

    public static function deleteType(int $id): string
    {
        $err = Database::write(static function (PDO $pdo) use ($id): string {
            $s = $pdo->prepare('SELECT code, name FROM mod_catalog_types WHERE id = ?');
            $s->execute([$id]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                return 'Тип не найден.';
            }
            if (isset(self::DEFAULT_TYPES[$row['code']])) {
                return 'Тип «' . $row['name'] . '» базовый, его ключ нужен импорту. Его можно переименовать или убрать из фильтра.';
            }
            $c = $pdo->prepare('SELECT COUNT(*) FROM mod_catalog_products WHERE type = ?');
            $c->execute([$row['code']]);
            $n = (int)$c->fetchColumn();
            if ($n > 0) {
                return 'У типа «' . $row['name'] . '» есть товары (' . $n . '), удалить нельзя. Уберите его из фильтра галочкой.';
            }
            $pdo->prepare('DELETE FROM mod_catalog_types WHERE id = ?')->execute([$id]);
            return '';
        });
        self::changed();
        return (string)$err;
    }

    // ---- внутреннее ----

    private static function sort(mixed $v): int
    {
        return max(-100000, min(100000, (int)$v));
    }

    /** Пересчитывает поле поиска у товаров после переименования. @param list<string> $params */
    private static function reindex(PDO $pdo, string $where, array $params): void
    {
        $s = $pdo->prepare("SELECT id, brand, name, line, use FROM mod_catalog_products WHERE {$where}");
        $s->execute($params);
        $u = $pdo->prepare('UPDATE mod_catalog_products SET search = ? WHERE id = ?');
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $u->execute([Catalog::searchText((string)$p['brand'], (string)$p['name'], (string)$p['line'], (string)$p['use']), $p['id']]);
        }
    }

    private static function changed(): void
    {
        self::reset();
        Catalog::flush([]);
    }
}
