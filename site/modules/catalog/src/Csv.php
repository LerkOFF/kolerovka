<?php
declare(strict_types=1);

namespace AgeCMS\Modules\catalog;

use AgeCMS\Database;

/** CSV каталога: разделитель «;», UTF-8, одна строка на вариант фасовки. */
final class Csv
{
    public const COLUMNS = ['slug', 'brand', 'name', 'line', 'type', 'finish', 'base', 'use', 'tint', 'featured', 'visible', 'in_stock', 'color', 'volume', 'price', 'description'];

    /** @return array{rows:list<array<string,mixed>>,errors:list<string>} */
    public static function parse(string $text): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
        $h = fopen('php://memory', 'r+');
        fwrite($h, $text);
        rewind($h);
        $header = null;
        $rows = [];
        $errors = [];
        $line = 0;
        while (($cells = fgetcsv($h, 0, ';', '"', '')) !== false) {
            $line++;
            if ($cells === [null] || implode('', array_map('strval', $cells)) === '') {
                continue;
            }
            if ($header === null) {
                $header = array_map(static fn($c): string => strtolower(trim((string)$c)), $cells);
                if (!in_array('name', $header, true) && !in_array('slug', $header, true)) {
                    $errors[] = 'В первой строке нужны названия колонок (slug, brand, name, ...). Скачайте выгрузку как образец.';
                    break;
                }
                continue;
            }
            $row = ['_line' => $line];
            foreach ($header as $i => $col) {
                if (in_array($col, self::COLUMNS, true)) {
                    $row[$col] = trim((string)($cells[$i] ?? ''));
                }
            }
            foreach (['tint', 'featured', 'visible'] as $k) {
                if (array_key_exists($k, $row)) {
                    $row[$k] = in_array(mb_strtolower((string)$row[$k]), ['1', 'да', 'yes', 'true', 'y'], true) ? 1 : 0;
                }
            }
            if (isset($row['in_stock'])) {
                $row['in_stock'] = match (mb_strtolower((string)$row['in_stock'])) {
                    '0', 'нет' => 0, '2', 'под заказ' => 2, default => 1,
                };
            }
            $rows[] = $row;
        }
        fclose($h);
        return ['rows' => $rows, 'errors' => $errors];
    }

    public static function export(): string
    {
        $h = fopen('php://memory', 'r+');
        fwrite($h, "\xEF\xBB\xBF");
        fputcsv($h, self::COLUMNS, ';', '"', '');
        $variants = [];
        foreach (Database::rows('SELECT product_id, label, price FROM mod_catalog_variants ORDER BY product_id, sort, id') as $v) {
            $variants[(int)$v['product_id']][] = $v;
        }
        foreach (Database::rows('SELECT * FROM mod_catalog_products ORDER BY id') as $p) {
            $base = [$p['slug'], $p['brand'], $p['name'], $p['line'], $p['type'], $p['finish'], $p['base'], $p['use'], $p['tint'], $p['featured'], $p['visible'], $p['in_stock'], $p['color']];
            $list = $variants[(int)$p['id']] ?? [['label' => '', 'price' => '']];
            foreach ($list as $i => $v) {
                fputcsv($h, [...$base, $v['label'], $v['price'], $i === 0 ? $p['description'] : ''], ';', '"', '');
            }
        }
        rewind($h);
        $out = (string)stream_get_contents($h);
        fclose($h);
        return $out;
    }
}
