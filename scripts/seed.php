<?php
declare(strict_types=1);

/**
 * Загружает страницы, меню, настройки и товары из content/ в сайт. Можно запускать повторно:
 * существующие страницы обновляются (каждая правка остаётся в истории ревизий ядра).
 *
 *   php scripts/seed.php <корень собранного сайта>
 *
 * Товары загружаются только при пустом каталоге (--products принудительно), чтобы правки владельца
 * в админке не затирались повторным запуском.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = realpath($argv[1] ?? '');
if ($root === false || !is_file($root . '/core/bootstrap.php')) {
    fwrite(STDERR, "Usage: php scripts/seed.php <assembled site root> [--products]\n");
    exit(1);
}
define('AGECMS_ROOT', $root);
require $root . '/core/bootstrap.php';

use AgeCMS\Actor;
use AgeCMS\Cache;
use AgeCMS\Content;
use AgeCMS\Database;
use AgeCMS\Pages;
use AgeCMS\SkillRegistry;

$content = dirname(__DIR__) . '/content';
$actor = Actor::cli('kolerovka-seed');
$json = static fn(string $file): array => json_decode((string)file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

Database::write(static function (PDO $pdo): void {
    $pdo->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)')->execute(['active_theme', 'kolerovka']);
});

$settings = $json($content . '/settings.json');
$direct = array_intersect_key($settings, array_flip(['org_phone', 'org_address']));
Content::setSettings($actor, array_diff_key($settings, $direct));
Database::write(static function (PDO $pdo) use ($direct): void {
    $stmt = $pdo->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)');
    foreach ($direct as $key => $value) {
        $stmt->execute([$key, $value]);
    }
});

foreach (glob($content . '/pages/*.json') ?: [] as $file) {
    $slug = basename($file, '.json');
    $fields = $json($file);
    $result = Pages::row($slug) === null ? Content::createPage($actor, $slug, $fields) : Content::updatePage($actor, $slug, $fields);
    echo str_pad($slug, 28) . (($result['changed'] ?? true) ? 'записана' : 'без изменений') . "\n";
}

foreach ($json($content . '/menus.json') as $name => $items) {
    Content::setMenu($actor, $name, $items);
    echo str_pad('меню ' . $name, 28) . "ok\n";
}

// Товары: модуль catalog создаёт свои таблицы при загрузке.
SkillRegistry::boot();
$catalog = '\\AgeCMS\\Modules\\catalog\\Catalog';
if (class_exists($catalog)) {
    $has = (int)Database::value('SELECT COUNT(*) FROM mod_catalog_products');
    if ($has === 0 || in_array('--products', $argv, true)) {
        $report = $catalog::importRows($json($content . '/products.json'));
        echo str_pad('товары', 28) . json_encode($report, JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo str_pad('товары', 28) . "уже есть ($has), не трогаю\n";
    }
} else {
    echo "Модуль catalog не загружен, товары пропущены\n";
}

Cache::invalidate(['all']);
echo "Готово.\n";
