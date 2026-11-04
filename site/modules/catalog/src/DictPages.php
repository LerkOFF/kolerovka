<?php
declare(strict_types=1);

namespace AgeCMS\Modules\catalog;

use AgeCMS\ModuleRequest;
use AgeCMS\ModuleResponse;

/**
 * Страница «Справочники» в админке: бренды, линейки и типы красок. Ядро уже проверило вход, право и защитный токен;
 * тело HTML печатается как есть, поэтому всё экранируется здесь. Своих скриптов у страницы нет (политика безопасности ядра),
 * поэтому каждая таблица сохраняется своей кнопкой, а удаление идёт отдельной формой с подтверждением.
 */
final class DictPages
{
    private const BASE = '/admin/m/catalog';
    private const URL = self::BASE . '/dict';

    public static function handle(ModuleRequest $request): ModuleResponse
    {
        if ($request->method !== 'POST') {
            return self::page($request->query);
        }
        $b = $request->body;
        $op = (string)($b['op'] ?? '');
        $int = static fn(mixed $v): ?int => trim((string)$v) === '' ? null : (int)$v;
        $done = '';
        $errors = [];
        $report = static function (array $r) use (&$done, &$errors): void {
            $done = $r['done'] > 0 ? 'Сохранено изменений: ' . $r['done'] . '.' : 'Изменений не было.';
            $errors = $r['errors'];
        };
        $result = static function (string $err, string $ok) use (&$done, &$errors): void {
            if ($err === '') {
                $done = $ok;
            } else {
                $errors[] = $err;
            }
        };

        switch ($op) {
            case 'brands':
                $report(Dict::saveBrands(is_array($b['b'] ?? null) ? $b['b'] : []));
                break;
            case 'lines':
                $report(Dict::saveLines(is_array($b['l'] ?? null) ? $b['l'] : []));
                break;
            case 'types':
                $report(Dict::saveTypes(is_array($b['t'] ?? null) ? $b['t'] : []));
                break;
            case 'add_brand':
                $result(Dict::addBrand((string)($b['name'] ?? ''), $int($b['sort'] ?? ''), !empty($b['in_filter']), !empty($b['in_tint'])), 'Бренд добавлен.');
                break;
            case 'add_line':
                $result(Dict::addLine((int)($b['brand_id'] ?? 0), (string)($b['name'] ?? ''), $int($b['sort'] ?? '')), 'Линейка добавлена.');
                break;
            case 'add_type':
                $result(Dict::addType((string)($b['code'] ?? ''), (string)($b['name'] ?? ''), $int($b['sort'] ?? '')), 'Тип добавлен.');
                break;
            case 'del_brand':
                $result(Dict::deleteBrand((int)($b['id'] ?? 0)), 'Бренд удалён.');
                break;
            case 'del_line':
                $result(Dict::deleteLine((int)($b['id'] ?? 0)), 'Линейка удалена.');
                break;
            case 'del_type':
                $result(Dict::deleteType((int)($b['id'] ?? 0)), 'Тип удалён.');
                break;
            default:
                $errors[] = 'Неизвестное действие.';
        }
        $qs = [];
        if ($done !== '') {
            $qs['done'] = $done;
        }
        if ($errors !== []) {
            $qs['err'] = implode("\n", array_slice($errors, 0, 5));
        }
        $anchor = ['brands' => 'brands', 'add_brand' => 'brands', 'del_brand' => 'brands', 'lines' => 'lines', 'add_line' => 'lines', 'del_line' => 'lines'][$op] ?? 'types';
        return ModuleResponse::redirect(self::URL . ($qs !== [] ? '?' . http_build_query($qs, '', '&', PHP_QUERY_RFC3986) : '') . '#' . $anchor);
    }

    /** @param array<string,mixed> $q */
    private static function page(array $q): ModuleResponse
    {
        $notice = '';
        if (($q['done'] ?? '') !== '') {
            $notice .= '<div class="notice ok"><div>' . ag_e(mb_substr((string)$q['done'], 0, 300)) . '</div></div>';
        }
        if (($q['err'] ?? '') !== '') {
            $notice .= '<div class="notice error"><div>';
            foreach (explode("\n", mb_substr((string)$q['err'], 0, 1500)) as $e) {
                $notice .= '<p>' . ag_e($e) . '</p>';
            }
            $notice .= '</div></div>';
        }
        return ModuleResponse::html('Справочники', $notice . self::help() . self::brands() . self::lines() . self::types());
    }

    private static function help(): string
    {
        return '<div class="panel"><div class="panel-h"><h2>Что это и как пользоваться</h2><a class="btn" href="' . self::BASE . '/">К товарам</a></div><div class="panel-b">'
            . '<p>Здесь вы решаете, какие бренды, линейки и типы красок есть в карточке товара и в фильтрах каталога на сайте. Раньше список брендов собирался сам из товаров, теперь им управляете вы.</p>'
            . '<ul>'
            . '<li><b>Бренды.</b> Галочка «В фильтре каталога» показывает бренд в списке «Бренд» на странице каталога. Галочка «В блоке на колеровке» показывает бренд кнопкой в блоке «Что можно заколеровать». Если товаров бренда нет на сайте (все скрыты) или у блока нет колеруемых товаров, бренд не появится, даже когда галочки стоят.</li>'
            . '<li><b>Переименовать</b> бренд или линейку: измените название и нажмите «Сохранить». Название сразу поменяется у всех товаров.</li>'
            . '<li><b>Скрыть</b> бренд: снимите обе галочки. Бренд пропадёт с сайта, товары останутся. <b>Удалить</b> можно только бренд без товаров (вместе с его линейками). Если товары есть, сайт предложит скрыть.</li>'
            . '<li><b>Линейки</b> относятся к бренду. В карточке товара они выбираются из списка, сгруппированного по брендам, пустое значение разрешено. В фильтре каталога появляется поле «Линейка», если в линейках есть показанные товары.</li>'
            . '<li><b>Типы.</b> Ключ (interior, facade, universal) нужен для загрузки прайса и не меняется, а название «Интерьерная» и другие можно менять свободно. Свой тип добавьте внизу: ключ латиницей и название. Базовые три типа удалить нельзя, их можно убрать из фильтра галочкой.</li>'
            . '<li><b>Порядок</b>: чем меньше число, тем выше в списках. Можно писать 10, 20, 30, чтобы потом вставлять между ними.</li>'
            . '<li>Новый бренд или линейка создаются сами, если указать их в карточке товара («Другой бренд») или в файле прайса CSV. Потом их можно переставить и настроить здесь.</li>'
            . '<li>Изменения сразу видны на сайте (кэш страниц сбрасывается сам).</li>'
            . '</ul></div></div>';
    }

    private static function form(string $op, string $inner, string $extra = ''): string
    {
        return '<form method="post" action="' . self::URL . '"' . $extra . '>' . ag_admin_csrf_field() . '<input type="hidden" name="op" value="' . $op . '">' . $inner . '</form>';
    }

    private static function chk(string $name, bool $on, string $label): string
    {
        return '<label class="chk"><input type="checkbox" name="' . $name . '" value="1"' . ($on ? ' checked' : '') . ' aria-label="' . ag_e($label) . '"></label>';
    }

    private static function delete(string $op, int $id, string $title): string
    {
        return '<form id="' . $op . $id . '" method="post" action="' . self::URL . '" data-confirm="' . ag_e($title) . '" data-confirm-text="Это нельзя отменить." data-confirm-ok="Удалить" data-danger>'
            . ag_admin_csrf_field() . '<input type="hidden" name="op" value="' . $op . '"><input type="hidden" name="id" value="' . $id . '"></form>';
    }

    private static function delButton(string $op, int $id): string
    {
        return '<button class="btn sm danger" type="submit" form="' . $op . $id . '">Удалить</button>';
    }

    private static function count(array $r): string
    {
        $total = (int)$r['total'];
        return $total === 0 ? '<span class="muted">нет</span>' : (string)$total . (isset($r['shown']) && (int)$r['shown'] !== $total ? ' <span class="muted">(на сайте ' . (int)$r['shown'] . ')</span>' : '');
    }

    private static function brands(): string
    {
        $rows = '';
        $dels = '';
        foreach (Dict::brandRows() as $r) {
            $id = (int)$r['id'];
            $hidden = (int)$r['in_filter'] === 0 && (int)$r['in_tint'] === 0;
            $rows .= '<tr><td><input class="in" type="text" name="b[' . $id . '][name]" value="' . ag_e($r['name']) . '" maxlength="' . Dict::NAME_MAX . '" aria-label="Название бренда" required>'
                . ($hidden ? ' <span class="tag plain">скрыт</span>' : '') . '</td>'
                . '<td><input class="in" type="number" name="b[' . $id . '][sort]" value="' . (int)$r['sort'] . '" aria-label="Порядок"></td>'
                . '<td>' . self::chk('b[' . $id . '][in_filter]', (int)$r['in_filter'] === 1, 'В фильтре каталога') . '</td>'
                . '<td>' . self::chk('b[' . $id . '][in_tint]', (int)$r['in_tint'] === 1, 'В блоке брендов на колеровке') . '</td>'
                . '<td class="num">' . self::count($r) . '</td>'
                . '<td class="r">' . self::delButton('del_brand', $id) . '</td></tr>';
            $dels .= self::delete('del_brand', $id, 'Удалить бренд «' . $r['name'] . '»?');
        }
        $table = $rows === ''
            ? '<div class="panel-b"><p class="muted">Брендов пока нет. Добавьте первый ниже или сохраните товар: бренд создастся сам.</p></div>'
            : self::form('brands', '<div class="tbl-scroll"><table class="tbl"><thead><tr><th>Название</th><th>Порядок</th><th>В фильтре каталога</th><th>В блоке на колеровке</th><th>Товаров</th><th></th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
                . '<div class="panel-b"><button class="btn primary" type="submit">Сохранить бренды</button></div>') . $dels;
        $add = self::form('add_brand', '<div class="row center">'
            . '<label class="f"><span>Новый бренд</span><input class="in" type="text" name="name" maxlength="' . Dict::NAME_MAX . '" required></label>'
            . '<label class="f"><span>Порядок (пусто = в конец)</span><input class="in" type="number" name="sort"></label>'
            . '<label class="chk"><input type="checkbox" name="in_filter" value="1" checked> В фильтре каталога</label>'
            . '<label class="chk"><input type="checkbox" name="in_tint" value="1" checked> В блоке на колеровке</label>'
            . '<button class="btn" type="submit">Добавить бренд</button></div>');
        return '<div class="panel" id="brands"><div class="panel-h"><h2>Бренды</h2></div>' . $table . '<div class="panel-b">' . $add . '</div></div>';
    }

    private static function lines(): string
    {
        $rows = '';
        $dels = '';
        foreach (Dict::lineRows() as $r) {
            $id = (int)$r['id'];
            $rows .= '<tr><td>' . ag_e($r['brand']) . '</td>'
                . '<td><input class="in" type="text" name="l[' . $id . '][name]" value="' . ag_e($r['name']) . '" maxlength="' . Dict::NAME_MAX . '" aria-label="Название линейки" required></td>'
                . '<td><input class="in" type="number" name="l[' . $id . '][sort]" value="' . (int)$r['sort'] . '" aria-label="Порядок"></td>'
                . '<td class="num">' . self::count($r) . '</td>'
                . '<td class="r">' . self::delButton('del_line', $id) . '</td></tr>';
            $dels .= self::delete('del_line', $id, 'Удалить линейку «' . $r['name'] . '» бренда ' . $r['brand'] . '?');
        }
        $table = $rows === ''
            ? '<div class="panel-b"><p class="muted">Линеек пока нет. Линейка не обязательна: у товара её можно не указывать.</p></div>'
            : self::form('lines', '<div class="tbl-scroll"><table class="tbl"><thead><tr><th>Бренд</th><th>Название</th><th>Порядок</th><th>Товаров</th><th></th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
                . '<div class="panel-b"><button class="btn primary" type="submit">Сохранить линейки</button></div>') . $dels;
        $brands = '<option value="">- выберите бренд -</option>';
        foreach (Dict::brandRows() as $b) {
            $brands .= '<option value="' . (int)$b['id'] . '">' . ag_e($b['name']) . '</option>';
        }
        $add = self::form('add_line', '<div class="row center">'
            . '<label class="f"><span>Бренд</span><select class="in" name="brand_id" required>' . $brands . '</select></label>'
            . '<label class="f"><span>Новая линейка</span><input class="in" type="text" name="name" maxlength="' . Dict::NAME_MAX . '" required></label>'
            . '<label class="f"><span>Порядок (пусто = в конец)</span><input class="in" type="number" name="sort"></label>'
            . '<button class="btn" type="submit">Добавить линейку</button></div>');
        return '<div class="panel" id="lines"><div class="panel-h"><h2>Линейки</h2></div>' . $table . '<div class="panel-b">' . $add . '</div></div>';
    }

    private static function types(): string
    {
        $rows = '';
        $dels = '';
        foreach (Dict::typeRows() as $r) {
            $id = (int)$r['id'];
            $base = isset(Dict::DEFAULT_TYPES[(string)$r['code']]);
            $rows .= '<tr><td><code>' . ag_e($r['code']) . '</code></td>'
                . '<td><input class="in" type="text" name="t[' . $id . '][name]" value="' . ag_e($r['name']) . '" maxlength="60" aria-label="Название типа" required></td>'
                . '<td><input class="in" type="number" name="t[' . $id . '][sort]" value="' . (int)$r['sort'] . '" aria-label="Порядок"></td>'
                . '<td>' . self::chk('t[' . $id . '][in_filter]', (int)$r['in_filter'] === 1, 'В фильтре каталога') . '</td>'
                . '<td class="num">' . self::count($r) . '</td>'
                . '<td class="r">' . ($base ? '<span class="muted">базовый</span>' : self::delButton('del_type', $id)) . '</td></tr>';
            if (!$base) {
                $dels .= self::delete('del_type', $id, 'Удалить тип «' . $r['name'] . '»?');
            }
        }
        $table = self::form('types', '<div class="tbl-scroll"><table class="tbl"><thead><tr><th>Ключ</th><th>Название</th><th>Порядок</th><th>В фильтре каталога</th><th>Товаров</th><th></th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
            . '<div class="panel-b"><button class="btn primary" type="submit">Сохранить типы</button></div>') . $dels;
        $add = self::form('add_type', '<div class="row center">'
            . '<label class="f"><span>Ключ (латиницей, не меняется)</span><input class="in" type="text" name="code" maxlength="31" pattern="[a-z][a-z0-9_\-]{1,30}" placeholder="например, floor" required></label>'
            . '<label class="f"><span>Название</span><input class="in" type="text" name="name" maxlength="60" required></label>'
            . '<label class="f"><span>Порядок (пусто = в конец)</span><input class="in" type="number" name="sort"></label>'
            . '<button class="btn" type="submit">Добавить тип</button></div>');
        return '<div class="panel" id="types"><div class="panel-h"><h2>Типы</h2></div>' . $table . '<div class="panel-b">' . $add . '</div></div>';
    }
}
