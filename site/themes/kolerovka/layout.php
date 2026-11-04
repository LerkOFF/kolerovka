<?php
/**
 * Макет темы: светлая и тёмная схемы по системе, одна шапка до 72 пикселей, подвал с контактами.
 *
 * @var array<string,mixed> $doc
 * @var string $slug
 * @var array<string,string> $settings
 * @var array{header:array<string,mixed>,footer:array<string,mixed>} $themeSettings
 * @var string $head
 * @var string $content
 * @var array{header:list<array{title:string,url:string}>,footer:list<array{title:string,url:string}>} $menus
 * @var bool $editing
 * @var string $csrf
 */
require_once __DIR__ . '/lib.php';

$site = kl_site();
$tel = $site['phone'] !== '' ? kl_tel($site['phone']) : '';
$path = '/' . ($slug === 'index' ? '' : $slug);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <?= $head ?>

    <link rel="icon" href="<?= ag_e('/favicon.ico?v=' . (is_file(AGECMS_ROOT . '/favicon.ico') ? filemtime(AGECMS_ROOT . '/favicon.ico') : 1)) ?>" sizes="48x48">
    <link rel="icon" href="<?= ag_e(kl_asset('favicon-16.png')) ?>" type="image/png" sizes="16x16">
    <link rel="icon" href="<?= ag_e(kl_asset('favicon-32.png')) ?>" type="image/png" sizes="32x32">
    <link rel="icon" href="<?= ag_e(kl_asset('favicon-48.png')) ?>" type="image/png" sizes="48x48">
    <link rel="icon" href="<?= ag_e(kl_asset('favicon-192.png')) ?>" type="image/png" sizes="192x192">
    <link rel="apple-touch-icon" href="<?= ag_e(kl_asset('apple-touch-icon.png')) ?>" sizes="180x180">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#f4f5f7" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#101216" media="(prefers-color-scheme: dark)">
    <link rel="preload" href="/themes/kolerovka/assets/fonts/onest-cyrillic.woff2" as="font" type="font/woff2" crossorigin>
    <script src="<?= ag_e(kl_asset('theme-init.js')) ?>"></script>
    <link rel="stylesheet" href="<?= ag_e(kl_asset('theme.css')) ?>">
    <link rel="stylesheet" href="<?= ag_e(kl_asset('ral.css')) ?>">
    <link rel="stylesheet" href="<?= ag_e(kl_asset('ral-ui.css')) ?>">
<?php if ($editing): ?>
    <meta name="ag-csrf" content="<?= ag_e($csrf) ?>">
    <script src="<?= ag_e('/assets/editor.js?v=' . (is_file(AGECMS_ROOT . '/assets/editor.js') ? filemtime(AGECMS_ROOT . '/assets/editor.js') : 1)) ?>" defer></script>
<?php endif; ?>
    <script src="<?= ag_e('/assets/app.js?v=' . (is_file(AGECMS_ROOT . '/assets/app.js') ? filemtime(AGECMS_ROOT . '/assets/app.js') : 1)) ?>" defer></script>
    <script src="<?= ag_e(kl_asset('theme.js')) ?>" defer></script>
</head>
<body class="<?= $editing ? 'is-editing' : '' ?>">
    <a class="skip" href="#main">К содержимому</a>
    <header class="site-header" data-ag-region="header">
        <div class="wrap bar">
            <a class="brand" href="/" aria-label="<?= ag_e($site['name']) ?>, на главную">
                <span class="brand-mark" aria-hidden="true"><img src="<?= ag_e(kl_asset('logo-mark.png')) ?>" alt="" width="40" height="40"></span>
                <span class="brand-name"><?= ag_e($site['name']) ?></span>
            </a>
            <nav class="nav" id="nav" aria-label="Основное меню" data-ag-region="menu">
<?php foreach ($menus['header'] as $item): ?>
                <a href="<?= ag_e($item['url']) ?>"<?= ($item['url'] === $path || ($item['url'] !== '/' && str_starts_with($path, $item['url']))) ? ' aria-current="page"' : '' ?>><?= ag_e($item['title']) ?></a>
<?php endforeach; ?>
            </nav>
            <div class="bar-actions">
<?php if ($tel !== ''): ?>
                <a class="phone" href="<?= ag_e($tel) ?>"><?= kl_icon('phone') ?><span><?= ag_e($site['phone']) ?></span></a>
<?php endif; ?>
<?php if ($site['telegram'] !== ''): ?>
                <a class="icon-btn" href="<?= ag_e($site['telegram']) ?>" aria-label="Написать в Telegram" rel="noopener"><?= kl_icon('telegram-logo') ?></a>
<?php endif; ?>
                <a class="btn btn-primary btn-sm hide-sm" href="/kolerovka#zayavka">Заказать колеровку</a>
                <button class="icon-btn theme-btn" type="button" aria-label="Сменить тему: светлая или тёмная" title="Тема"><?= kl_icon('sun', 'ic ic-sun') ?><?= kl_icon('moon', 'ic ic-moon') ?></button>
                <button class="icon-btn menu-btn" type="button" aria-expanded="false" aria-controls="nav" aria-label="Меню"><?= kl_icon('list') ?></button>
            </div>
        </div>
    </header>

    <main id="main">
<?= $content ?>

    </main>

    <footer class="site-footer" data-ag-region="footer">
        <div class="wrap foot">
            <div>
                <a class="brand" href="/"><span class="brand-mark" aria-hidden="true"><img src="<?= ag_e(kl_asset('logo-mark.png')) ?>" alt="" width="40" height="40"></span><span class="brand-name"><?= ag_e($site['name']) ?></span></a>
                <p class="muted">Премиальные краски и колеровка.<?php if ($site['address'] !== ''): ?><br><?= ag_e($site['address']) ?><?php endif; ?></p>
<?php if ($site['requisites'] !== ''): ?>
                <p class="fine"><?= nl2br(ag_e($site['requisites']), false) ?></p>
<?php elseif ($site['legal_name'] !== ''): ?>
                <p class="fine"><?= ag_e($site['legal_name']) ?><?= $site['inn'] !== '' ? ', ИНН ' . ag_e($site['inn']) : '' ?></p>
<?php endif; ?>
            </div>
            <nav class="foot-col" aria-label="Нижнее меню">
                <h2 class="foot-h">Разделы</h2>
<?php foreach (array_merge($menus['header'], $menus['footer'] ?? []) as $item): ?>
                <a href="<?= ag_e($item['url']) ?>"><?= ag_e($item['title']) ?></a>
<?php endforeach; ?>
            </nav>
            <div class="foot-col">
                <h2 class="foot-h">Связь</h2>
<?php if ($tel !== ''): ?><a href="<?= ag_e($tel) ?>"><?= ag_e($site['phone']) ?></a><?php else: ?><span class="muted">Телефон будет указан</span><?php endif; ?>
<?php if ($site['email'] !== ''): ?><a href="mailto:<?= ag_e($site['email']) ?>"><?= ag_e($site['email']) ?></a><?php endif; ?>
<?php if ($site['telegram'] !== ''): ?><a href="<?= ag_e($site['telegram']) ?>" rel="noopener">Telegram</a><?php endif; ?>
<?php if ($site['max'] !== ''): ?><a href="<?= ag_e($site['max']) ?>" rel="noopener">Max</a><?php endif; ?>
            </div>
            <div class="foot-col">
                <h2 class="foot-h">Документы</h2>
                <a href="/politika">Политика обработки персональных данных</a>
            </div>
        </div>
    </footer>
</body>
</html>
