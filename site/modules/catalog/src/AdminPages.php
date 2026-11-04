<?php
declare(strict_types=1);

namespace AgeCMS\Modules\catalog;

use AgeCMS\Actor;
use AgeCMS\ModuleRequest;
use AgeCMS\ModuleResponse;
use AgeCMS\NotFoundException;
use Throwable;

/** Раздел «Каталог» в админке. Ядро уже проверило вход, право и защитный токен; тело HTML печатается как есть, поэтому всё экранируется здесь. */
final class AdminPages
{
    private const BASE = '/admin/m/catalog';

    public static function handle(ModuleRequest $request, Actor $actor): ModuleResponse
    {
        $path = substr($request->path, strlen(self::BASE)) ?: '/';
        $post = $request->method === 'POST';

        if ($path === '/') {
            return $post ? self::bulk($request) : self::index($request->query);
        }
        if ($path === '/import' && !$post) {
            return self::importForm();
        }
        if ($path === '/import/upload' && $post) {
            return self::importRun($request);
        }
        if ($path === '/export' && !$post) {
            return self::export();
        }
        if (preg_match('~^/product/(new|\d{1,10})(/image)?$~', $path, $m) === 1) {
            if (($m[2] ?? '') === '/image') {
                return self::image($request, (string)$m[1]);
            }
            return $post ? self::save($request, (string)$m[1]) : self::form((string)$m[1], $request->query);
        }
        throw new NotFoundException('Страница не найдена');
    }

    // ---- список ----

    /** @param array<string,mixed> $q */
    private static function index(array $q): ModuleResponse
    {
        $f = [];
        foreach (['q', 'brand', 'type', 'tint', 'visible', 'noprice'] as $k) {
            $f[$k] = mb_substr(trim((string)($q[$k] ?? '')), 0, 80);
        }
        $page = max(1, min((int)($q['str'] ?? 1), 1000));
        $data = Catalog::list($f, $page, Catalog::ADMIN_PER_PAGE, 'p.id DESC');
        $pages = max(1, (int)ceil($data['total'] / Catalog::ADMIN_PER_PAGE));

        $opt = static fn(string $v, string $label, string $cur): string => '<option value="' . ag_e($v) . '"' . ($v === $cur ? ' selected' : '') . '>' . ag_e($label) . '</option>';
        $brands = $opt('', 'Все', $f['brand']);
        foreach (Catalog::brands(true) as $b) {
            $brands .= $opt($b, $b, $f['brand']);
        }
        $types = $opt('', 'Любой', $f['type']);
        foreach (Catalog::TYPES as $k => $l) {
            $types .= $opt($k, $l, $f['type']);
        }
        $filters = '<form class="row" method="get" action="' . self::BASE . '/">'
            . '<label class="f"><span>Поиск</span><input class="in" type="search" name="q" value="' . ag_e($f['q']) . '" placeholder="Название, бренд, линейка"></label>'
            . '<label class="f"><span>Бренд</span><select class="in" name="brand">' . $brands . '</select></label>'
            . '<label class="f"><span>Тип</span><select class="in" name="type">' . $types . '</select></label>'
            . '<label class="f"><span>Колеровка</span><select class="in" name="tint">' . $opt('', 'Любая', $f['tint']) . $opt('1', 'Колеруется', $f['tint']) . $opt('0', 'Не колеруется', $f['tint']) . '</select></label>'
            . '<label class="f"><span>Показ</span><select class="in" name="visible">' . $opt('', 'Все', $f['visible']) . $opt('1', 'Показан', $f['visible']) . $opt('0', 'Скрыт', $f['visible']) . '</select></label>'
            . '<label class="f"><span>Цена</span><select class="in" name="noprice">' . $opt('', 'Любая', $f['noprice']) . $opt('1', 'Не указана', $f['noprice']) . '</select></label>'
            . '<button class="btn primary" type="submit">Найти</button></form>';

        $rows = '';
        foreach ($data['items'] as $p) {
            $rows .= '<tr><td><input type="checkbox" name="ids[]" value="' . $p['id'] . '" aria-label="Выбрать ' . ag_e($p['name']) . '"></td>'
                . '<td><a href="' . self::BASE . '/product/' . $p['id'] . '"><b>' . ag_e($p['name']) . '</b></a><div class="muted">' . ag_e($p['line']) . '</div></td>'
                . '<td>' . ag_e($p['brand']) . '</td>'
                . '<td>' . ($p['tint'] ? '<span class="tag ok">да</span>' : '<span class="muted">нет</span>') . '</td>'
                . '<td class="num r">' . ($p['price_from'] !== null ? 'от ' . ag_e(Catalog::money($p['price_from'])) : '<span class="muted">нет</span>') . '</td>'
                . '<td>' . ag_e(Catalog::STOCK[$p['in_stock']] ?? '') . '</td>'
                . '<td>' . ($p['visible'] ? 'показан' : '<span class="muted">скрыт</span>') . '</td>'
                . '<td class="r"><a class="btn ghost sm" href="' . self::BASE . '/product/' . $p['id'] . '">Изменить</a></td></tr>';
        }
        $table = $rows === ''
            ? '<p class="muted">' . (Catalog::count() === 0 ? 'В каталоге пока нет товаров. Добавьте первый или загрузите прайс в CSV.' : 'По этим условиям ничего не найдено.') . '</p>'
            : '<form method="post" action="' . self::BASE . '/">' . ag_admin_csrf_field()
                . '<div class="tbl-scroll"><table class="tbl"><thead><tr><th></th><th>Название</th><th>Бренд</th><th>Колеруется</th><th class="r">Цена</th><th>Наличие</th><th>Показ</th><th></th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
                . '<div class="panel-b"><div class="row center">'
                . '<label class="f"><span>С отмеченными</span><select class="in" name="action">'
                . '<option value="show">Показать на сайте</option><option value="hide">Скрыть</option><option value="tint_on">Включить колеровку</option><option value="tint_off">Выключить колеровку</option>'
                . '<option value="price">Изменить цены на процент</option><option value="delete">Удалить</option></select></label>'
                . '<label class="f"><span>Процент (для цен, например 5 или -10)</span><input class="in" type="number" name="percent" min="-90" max="500" step="1" value="0"></label>'
                . '<button class="btn" type="submit">Применить</button></div>'
                . '<small class="hint">Удаление и изменение цен нельзя отменить. Для цен можно сначала скачать выгрузку.</small></div></form>';

        $nav = '';
        if ($pages > 1) {
            $qs = array_filter($f, static fn($v): bool => $v !== '');
            $nav = '<div class="panel-b row center">';
            if ($page > 1) {
                $nav .= '<a class="btn sm" href="' . ag_e(self::BASE . '/?' . http_build_query($qs + ['str' => $page - 1])) . '">Назад</a>';
            }
            $nav .= '<span class="muted">Страница ' . $page . ' из ' . $pages . '</span>';
            if ($page < $pages) {
                $nav .= '<a class="btn sm" href="' . ag_e(self::BASE . '/?' . http_build_query($qs + ['str' => $page + 1])) . '">Дальше</a>';
            }
            $nav .= '</div>';
        }

        $notice = '';
        if (isset($q['done'])) {
            $notice = '<div class="notice ok"><div>' . ag_e((string)$q['done']) . '</div></div>';
        }
        $head = '<div class="panel-h"><h2>Товары: ' . $data['total'] . '</h2>'
            . '<a class="btn" href="' . self::BASE . '/import">Импорт и экспорт</a> '
            . '<a class="btn primary" href="' . self::BASE . '/product/new">Добавить товар</a></div>';
        $body = $notice . '<div class="panel">' . $head . '<div class="panel-b">' . $filters . '</div>' . $table . $nav . '</div>';
        return ModuleResponse::html('Каталог красок', $body);
    }

    private static function bulk(ModuleRequest $request): ModuleResponse
    {
        $action = (string)($request->body['action'] ?? '');
        $ids = is_array($request->body['ids'] ?? null) ? $request->body['ids'] : [];
        if ($ids === []) {
            return ModuleResponse::redirect(self::BASE . '/?done=' . rawurlencode('Ничего не отмечено.'));
        }
        $percent = max(-90, min(500, (int)($request->body['percent'] ?? 0)));
        if (!in_array($action, ['show', 'hide', 'tint_on', 'tint_off', 'price', 'delete'], true) || ($action === 'price' && $percent === 0)) {
            return ModuleResponse::redirect(self::BASE . '/?done=' . rawurlencode('Выберите действие (для цен укажите процент, не равный нулю).'));
        }
        $n = Catalog::bulk($action, $ids, $percent);
        $label = ['show' => 'показано', 'hide' => 'скрыто', 'tint_on' => 'колеровка включена', 'tint_off' => 'колеровка выключена', 'price' => 'цены изменены на ' . $percent . '%', 'delete' => 'удалено'][$action];
        return ModuleResponse::redirect(self::BASE . '/?done=' . rawurlencode("Готово: {$label}, позиций затронуто: {$n}."));
    }

    // ---- карточка товара ----

    /** @param array<string,mixed> $q */
    private static function form(string $id, array $q, string $error = '', ?array $draft = null): ModuleResponse
    {
        $p = $draft;
        if ($p === null) {
            if ($id === 'new') {
                $p = ['id' => 0, 'slug' => '', 'brand' => '', 'name' => '', 'line' => '', 'type' => 'interior', 'finish' => '', 'base' => '', 'use' => '', 'description' => '', 'tint' => 1, 'featured' => 0, 'visible' => 1, 'in_stock' => 1, 'color' => '#dddddd', 'images' => [], 'specs' => [], 'source_url' => '', 'sort' => 0, 'variants' => []];
            } else {
                $p = Catalog::byId((int)$id);
                if ($p === null) {
                    throw new NotFoundException('Товар не найден');
                }
            }
        }
        $isNew = (int)$p['id'] === 0;
        $variants = '';
        foreach ($p['variants'] as $v) {
            $variants .= $v['label'] . ' | ' . ((int)$v['price'] > 0 ? (int)$v['price'] : '') . "\n";
        }
        $specs = '';
        foreach ($p['specs'] as $k => $v) {
            $specs .= $k . ': ' . $v . "\n";
        }
        $sel = static function (string $name, array $opts, $cur): string {
            $h = '<select class="in" name="' . $name . '">';
            foreach ($opts as $k => $l) {
                $h .= '<option value="' . ag_e((string)$k) . '"' . ((string)$k === (string)$cur ? ' selected' : '') . '>' . ag_e($l) . '</option>';
            }
            return $h . '</select>';
        };
        $chk = static fn(string $name, string $label, $on): string => '<label class="chk"><input type="checkbox" name="' . $name . '" value="1"' . ($on ? ' checked' : '') . '> ' . ag_e($label) . '</label>';
        $datalist = '<datalist id="brands">';
        foreach (Catalog::brands(true) as $b) {
            $datalist .= '<option value="' . ag_e($b) . '">';
        }
        $datalist .= '</datalist>';

        $notice = '';
        if ($error !== '') {
            $notice = '<div class="notice error"><div>' . ag_e($error) . '</div></div>';
        } elseif (isset($q['saved'])) {
            $notice = '<div class="notice ok"><div>Сохранено.' . ((string)$p['slug'] !== '' && (int)$p['visible'] === 1 ? ' <a href="/katalog/' . ag_e($p['slug']) . '" target="_blank" rel="noopener">Открыть на сайте</a>' : '') . '</div></div>';
        }

        $text = static fn(string $name, string $label, $val, string $extra = ''): string => '<label class="f"><span>' . ag_e($label) . '</span><input class="in" type="text" name="' . $name . '" value="' . ag_e((string)$val) . '" maxlength="200"' . $extra . '></label>';

        $main = '<form method="post" action="' . self::BASE . '/product/' . ($isNew ? 'new' : (int)$p['id']) . '">' . ag_admin_csrf_field()
            . '<div class="panel"><div class="panel-h"><h2>' . ($isNew ? 'Новый товар' : ag_e($p['brand'] . ' ' . $p['name'])) . '</h2></div><div class="panel-b">'
            . '<div class="row">' . '<label class="f"><span>Бренд</span><input class="in" type="text" name="brand" list="brands" value="' . ag_e($p['brand']) . '" maxlength="80" required></label>'
            . $text('name', 'Название', $p['name'], ' required') . $text('line', 'Линейка', $p['line']) . '</div>' . $datalist
            . '<div class="row">' . '<label class="f"><span>Тип</span>' . $sel('type', Catalog::TYPES, $p['type']) . '</label>'
            . $text('finish', 'Степень блеска', $p['finish']) . $text('base', 'Основа', $p['base']) . $text('use', 'Применение', $p['use']) . '</div>'
            . '<label class="f"><span>Описание</span><textarea class="in" name="description" rows="5" maxlength="4000">' . ag_e($p['description']) . '</textarea><small class="hint">Берите текст с официального сайта бренда, без копирования чужих фото.</small></label>'
            . '<div class="row">'
            . '<label class="f"><span>Наличие</span>' . $sel('in_stock', Catalog::STOCK, $p['in_stock']) . '</label>'
            . '<label class="f"><span>Цвет плитки-заглушки</span>' . $sel('color', Catalog::PALETTE, Catalog::tile((string)$p['color'])) . '</label>'
            . '<label class="f"><span>Порядок (меньше = выше)</span><input class="in" type="number" name="sort" value="' . (int)$p['sort'] . '"></label>'
            . $text('source_url', 'Страница на сайте бренда', $p['source_url']) . '</div>'
            . '<div class="row">' . $chk('tint', 'Доступна колеровка', $p['tint']) . $chk('featured', 'Показывать на главной', $p['featured']) . $chk('visible', 'Показывать на сайте', $p['visible']) . '</div>'
            . '<div class="row"><label class="f"><span>Фасовка и цена, по строке: «объём | цена»</span><textarea class="in" name="variants" rows="5" placeholder="0,9 л | 1890&#10;2,7 л | 4890&#10;9 л | 14900">' . ag_e($variants) . '</textarea><small class="hint">Цена в рублях без копеек. Пустая цена значит «по запросу». Цены на сайте видны, только если включена настройка «Показывать цены».</small></label>'
            . '<label class="f"><span>Характеристики, по строке: «название: значение»</span><textarea class="in" name="specs" rows="5" placeholder="Расход: 8-10 м2/л&#10;Сухой остаток: 40%">' . ag_e($specs) . '</textarea></label></div>'
            . ($isNew ? '<label class="f"><span>Адрес на сайте (необязательно, латиницей)</span><input class="in" type="text" name="slug" maxlength="64" pattern="[a-z0-9-]*" placeholder="создастся из названия"></label>' : '<p class="muted">Адрес: /katalog/' . ag_e($p['slug']) . '</p><input type="hidden" name="slug" value="' . ag_e($p['slug']) . '">')
            . '<input type="hidden" name="images" value="' . ag_e(implode("\n", $p['images'])) . '">'
            . '<div class="row"><button class="btn primary" type="submit">Сохранить</button><a class="btn ghost" href="' . self::BASE . '/">К списку</a></div>'
            . '</div></div></form>';

        $photos = '';
        if (!$isNew) {
            $list = '';
            foreach ($p['images'] as $i => $url) {
                $list .= '<div class="row center"><img src="' . ag_e($url) . '" alt="" width="72" height="72"> <code>' . ag_e($url) . '</code>'
                    . '<form method="post" action="' . self::BASE . '/product/' . (int)$p['id'] . '/image" enctype="multipart/form-data">' . ag_admin_csrf_field()
                    . '<input type="hidden" name="remove" value="' . $i . '"><button class="btn sm danger" type="submit">Убрать</button></form></div>';
            }
            $photos = '<div class="panel"><div class="panel-h"><h2>Фото (до 4)</h2></div><div class="panel-b">'
                . ($list !== '' ? $list : '<p class="muted">Фото нет: на сайте покажется плитка с цветом.</p>')
                . (count($p['images']) < 4
                    ? '<form method="post" action="' . self::BASE . '/product/' . (int)$p['id'] . '/image" enctype="multipart/form-data">' . ag_admin_csrf_field()
                        . '<label class="f"><span>Добавить фото</span><input class="in" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required><small class="hint">JPEG, PNG или WebP, до 8 МБ. Сайт уменьшит и сожмёт картинку сам.</small></label>'
                        . '<button class="btn" type="submit">Загрузить</button></form>'
                    : '<p class="muted">Загружено максимум фото. Уберите лишнее, чтобы добавить новое.</p>')
                . '</div></div>';
        } else {
            $photos = '<p class="muted">Фото можно добавить после первого сохранения.</p>';
        }
        return ModuleResponse::html($isNew ? 'Новый товар' : 'Товар', $notice . $main . $photos, $error !== '' ? 422 : 200);
    }

    private static function save(ModuleRequest $request, string $id): ModuleResponse
    {
        $b = $request->body;
        $variants = [];
        foreach (preg_split('/\R/u', (string)($b['variants'] ?? '')) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$label, $price] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            $variants[] = ['label' => $label, 'price' => (int)preg_replace('/\D/', '', $price)];
        }
        $specs = [];
        foreach (preg_split('/\R/u', (string)($b['specs'] ?? '')) ?: [] as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = array_map('trim', explode(':', $line, 2));
                if ($k !== '' && $v !== '') {
                    $specs[$k] = $v;
                }
            }
        }
        $images = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string)($b['images'] ?? '')) ?: [])));
        $product = [
            'id' => $id === 'new' ? 0 : (int)$id,
            'slug' => (string)($b['slug'] ?? ''), 'brand' => (string)($b['brand'] ?? ''), 'name' => (string)($b['name'] ?? ''),
            'line' => (string)($b['line'] ?? ''), 'type' => (string)($b['type'] ?? 'interior'), 'finish' => (string)($b['finish'] ?? ''),
            'base' => (string)($b['base'] ?? ''), 'use' => (string)($b['use'] ?? ''), 'description' => (string)($b['description'] ?? ''),
            'tint' => !empty($b['tint']), 'featured' => !empty($b['featured']), 'visible' => !empty($b['visible']),
            'in_stock' => (int)($b['in_stock'] ?? 1), 'color' => (string)($b['color'] ?? ''), 'images' => $images, 'specs' => $specs,
            'source_url' => (string)($b['source_url'] ?? ''), 'sort' => (int)($b['sort'] ?? 0),
        ];
        if (trim($product['name']) === '' || trim($product['brand']) === '') {
            $draft = $product + ['variants' => $variants];
            $draft['tint'] = (int)$draft['tint'];
            $draft['featured'] = (int)$draft['featured'];
            $draft['visible'] = (int)$draft['visible'];
            return self::form($id, [], 'Укажите бренд и название.', $draft);
        }
        try {
            $pid = Catalog::upsert($product, $variants);
        } catch (Throwable $e) {
            return self::form($id, [], 'Не удалось сохранить: ' . $e->getMessage(), $product + ['variants' => $variants, 'tint' => (int)$product['tint'], 'featured' => (int)$product['featured'], 'visible' => (int)$product['visible']]);
        }
        return ModuleResponse::redirect(self::BASE . '/product/' . $pid . '?saved=1');
    }

    private static function image(ModuleRequest $request, string $id): ModuleResponse
    {
        $p = $id === 'new' ? null : Catalog::byId((int)$id);
        if ($p === null) {
            throw new NotFoundException('Товар не найден');
        }
        $images = $p['images'];
        if (isset($request->body['remove'])) {
            unset($images[(int)$request->body['remove']]);
            $images = array_values($images);
        }
        $error = '';
        foreach ($request->files as $file) {
            if (count($images) >= 4) {
                break;
            }
            try {
                $images[] = $file->toMedia();
            } catch (Throwable $e) {
                $error = 'Не удалось добавить фото: ' . $e->getMessage();
            }
        }
        $keep = $p;
        unset($keep['variants']);
        Catalog::upsert(['images' => $images, 'id' => $p['id']] + $keep, null);
        return $error !== ''
            ? self::form((string)$p['id'], [], $error)
            : ModuleResponse::redirect(self::BASE . '/product/' . $p['id'] . '?saved=1');
    }

    // ---- импорт и экспорт ----

    private static function importForm(?string $report = null): ModuleResponse
    {
        $body = $report ?? '';
        $body .= '<div class="panel"><div class="panel-h"><h2>Импорт и экспорт</h2><a class="btn" href="' . self::BASE . '/export">Скачать выгрузку</a></div><div class="panel-b">'
            . '<form method="post" action="' . self::BASE . '/import/upload" enctype="multipart/form-data">' . ag_admin_csrf_field()
            . '<label class="f"><span>Файл CSV (разделитель «;», кодировка UTF-8)</span><input class="in" type="file" name="csv" accept=".csv,text/csv,text/plain" required>'
            . '<small class="hint">Товары ищутся по колонке slug. Если такого товара нет, он создаётся. Пустые ячейки и отсутствующие колонки оставляют прежние значения, поэтому можно загрузить файл только со slug, volume и price для обновления цен. Для товара, у которого в файле есть volume, фасовки заменяются целиком.</small></label>'
            . '<button class="btn primary" type="submit">Загрузить</button></form></div></div>'
            . '<div class="panel"><div class="panel-h"><h2>Формат файла</h2></div><div class="panel-b">'
            . '<p>Первая строка: названия колонок. Порядок любой, лишние колонки пропускаются.</p>'
            . '<p><code>' . ag_e(implode(';', Csv::COLUMNS)) . '</code></p>'
            . '<ul><li>Одна строка на фасовку: у товара с тремя объёмами три строки с одним slug.</li>'
            . '<li><b>type</b>: interior, facade или universal. <b>tint</b>, <b>featured</b>, <b>visible</b>: 1 или 0 (также «да» и «нет»).</li>'
            . '<li><b>in_stock</b>: 1 в наличии, 2 под заказ, 0 нет. <b>color</b>: цвет плитки-заглушки, один из ключей: ' . ag_e(implode(', ', array_keys(Catalog::PALETTE))) . '.</li>'
            . '<li><b>price</b>: рубли без копеек. Проще всего сначала скачать выгрузку и править её в таблице.</li></ul></div></div>';
        return ModuleResponse::html('Импорт и экспорт', $body);
    }

    private static function importRun(ModuleRequest $request): ModuleResponse
    {
        $file = $request->files[0] ?? null;
        if ($file === null) {
            return self::importForm('<div class="notice error"><div>Файл не получен.</div></div>');
        }
        $parsed = Csv::parse($file->contents());
        $errors = $parsed['errors'];
        $created = $updated = 0;
        if ($parsed['rows'] !== []) {
            $res = Catalog::importRows($parsed['rows']);
            $created = $res['created'];
            $updated = $res['updated'];
            $errors = array_merge($errors, $res['errors']);
        }
        $ok = $created + $updated > 0;
        $html = '<div class="notice ' . ($errors === [] && $ok ? 'ok' : ($ok ? 'warn' : 'error')) . '"><div><p>Создано: <b>' . $created . '</b>, обновлено: <b>' . $updated . '</b>, ошибок: <b>' . count($errors) . '</b>.</p>';
        if ($errors !== []) {
            $html .= '<ul>';
            foreach (array_slice($errors, 0, 50) as $e) {
                $html .= '<li>' . ag_e($e) . '</li>';
            }
            $html .= (count($errors) > 50 ? '<li>и ещё ' . (count($errors) - 50) . '</li>' : '') . '</ul>';
        }
        $html .= '<p><a href="' . self::BASE . '/">К списку товаров</a></p></div></div>';
        return self::importForm($html);
    }

    /**
     * ModuleResponse не умеет отдавать файл, поэтому выгрузка показывается на странице:
     * ссылка «Скачать» (data-адрес) и поле для копирования.
     */
    private static function export(): ModuleResponse
    {
        $csv = Csv::export();
        $link = strlen($csv) < 400000
            ? '<a class="btn primary" download="catalog.csv" href="data:text/csv;charset=utf-8,' . ag_e(rawurlencode($csv)) . '">Скачать catalog.csv</a>'
            : '<p class="muted">Файл большой: скопируйте текст ниже в таблицу.</p>';
        $body = '<div class="panel"><div class="panel-h"><h2>Выгрузка каталога</h2><a class="btn" href="' . self::BASE . '/import">Назад</a></div><div class="panel-b">'
            . '<p>Файл открывается в Excel и LibreOffice: разделитель «;», кодировка UTF-8. Правьте цены и загружайте обратно через «Импорт».</p>' . $link
            . '<label class="f"><span>Текст файла</span><textarea class="in" rows="14" readonly>' . ag_e($csv) . '</textarea></label></div></div>';
        return ModuleResponse::html('Выгрузка каталога', $body);
    }
}
