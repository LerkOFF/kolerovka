<?php
declare(strict_types=1);

// Роутер для `php -S` на локальном экземпляре: повторяет правила .htaccess ядра и отдаёт файлы темы.
$root = dirname(__DIR__) . '/.dev-root';
$root = getenv('KOL_WWW') ?: $root;
$path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
if ($path === '/admin' || $path === '/admin/') {
    $path = '/admin/index.php';
}
if (preg_match('~^/admin/(index|api|mcp|install|upload)\.php$~', $path) === 1) {
    $_SERVER['SCRIPT_NAME'] = $path;
    require $root . $path;
    return true;
}
$uploads = getenv('AGECMS_UPLOADS_DIR');
if ($uploads !== false && $uploads !== '' && preg_match('~^/assets/uploads/([a-f0-9]{20}\.(webp|avif))$~', $path, $m) === 1 && is_file($uploads . '/' . $m[1])) {
    header('Content-Type: image/' . $m[2]);
    readfile($uploads . '/' . $m[1]);
    return true;
}
if (preg_match('~^/(assets(?!/cache/)|themes)/[A-Za-z0-9_./-]+$~', $path) === 1 && !str_contains($path, '..') && is_file($root . $path)) {
    return false;
}
require $root . '/index.php';
return true;
