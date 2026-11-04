<?php
declare(strict_types=1);

/** Помощники темы. Подключается из layout.php и блоков. */

if (!function_exists('kl_asset')) {
    /** Адрес файла темы с отметкой версии. */
    function kl_asset(string $file): string
    {
        $path = AGECMS_ROOT . '/themes/kolerovka/assets/' . $file;
        return '/themes/kolerovka/assets/' . $file . '?v=' . (is_file($path) ? filemtime($path) : 1);
    }

    /** Значок Phosphor (regular) из assets/icons, цвет берёт у текста. */
    function kl_icon(string $name, string $class = 'ic'): string
    {
        static $cache = [];
        $name = preg_replace('/[^a-z0-9-]/', '', $name) ?? '';
        if (!isset($cache[$name])) {
            $file = AGECMS_ROOT . '/themes/kolerovka/assets/icons/' . $name . '.svg';
            $cache[$name] = is_file($file) ? (string)file_get_contents($file) : '';
        }
        return $cache[$name] === '' ? '' : str_replace('<svg ', '<svg class="' . ag_e($class) . '" aria-hidden="true" focusable="false" ', $cache[$name]);
    }

    /** Класс каталога из модуля catalog (подгружается сам, если модуль не вызывался в этом запросе) или null. */
    function kl_catalog(): ?string
    {
        $class = '\\AgeCMS\\Modules\\catalog\\Catalog';
        if (!class_exists($class, false)) {
            $dir = AGECMS_ROOT . '/modules/catalog/src';
            if (!is_file($dir . '/Catalog.php')) {
                return null;
            }
            foreach (['Slug.php', 'Catalog.php'] as $f) {
                if (is_file($dir . '/' . $f)) {
                    require_once $dir . '/' . $f;
                }
            }
        }
        return class_exists($class, false) ? $class : null;
    }

    function kl_tel(string $label): string
    {
        $digits = preg_replace('/\D+/', '', $label) ?? '';
        if (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7' . substr($digits, 1);
        }
        return 'tel:+' . $digits;
    }

    /** Ссылка на мессенджер: полный адрес остаётся как есть, ник Telegram дополняется. */
    function kl_link(string $kind, string $value): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('~^https?://~', $value) === 1) {
            return $value;
        }
        return $kind === 'telegram' ? 'https://t.me/' . ltrim($value, '@') : '';
    }

    /** Цвета RAL для плиток и выбора оттенка. @return list<array{code:string,name:string,hex:string}> */
    function kl_ral(): array
    {
        $file = AGECMS_ROOT . '/themes/kolerovka/data/ral.json';
        $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : [];
        return is_array($data) ? $data : [];
    }

    /** Общие данные шапки, подвала и контактов из настроек сайта и темы. @return array<string,string> */
    function kl_site(): array
    {
        return [
            'name' => ag_setting('site_name', 'Колеровка красок'),
            'phone' => ag_setting('org_phone'),
            'email' => ag_setting('org_email'),
            'address' => ag_setting('org_address'),
            'legal_name' => ag_setting('org_legal_name'),
            'inn' => ag_setting('org_inn'),
            'hours' => ag_setting('theme.header.hours'),
            'telegram' => kl_link('telegram', ag_setting('theme.footer.telegram')),
            'max' => kl_link('max', ag_setting('theme.footer.max')),
            'map' => ag_setting('theme.footer.map_embed'),
            'requisites' => ag_setting('theme.footer.requisites'),
        ];
    }
}
