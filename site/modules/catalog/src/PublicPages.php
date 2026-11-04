<?php
declare(strict_types=1);

namespace AgeCMS\Modules\catalog;

use AgeCMS\ModuleResponse;
use AgeCMS\Seo;

/** Публичные страницы /katalog и /katalog/{slug}. Вёрстка использует классы темы, иконок здесь нет. */
final class PublicPages
{
    /** @param array<string,mixed> $q query */
    public static function listing(array $q): ModuleResponse
    {
        $f = [
            'q' => mb_substr(trim((string)($q['q'] ?? '')), 0, 80),
            'brand' => mb_substr((string)($q['brand'] ?? ''), 0, 60),
            'line' => mb_substr((string)($q['line'] ?? ''), 0, 60),
            'type' => (string)($q['type'] ?? ''),
            'tint' => in_array((string)($q['tint'] ?? ''), ['0', '1'], true) ? (string)$q['tint'] : '',
            'visible' => '1',
        ];
        $other = Catalog::setting('other_paints', true) === true;
        if (!$other) {
            $f['tint'] = '1';
        }
        $badge = Catalog::setting('show_tint_badge', true) === true;
        $page = max(1, min((int)($q['str'] ?? 1), 500));
        $data = Catalog::list($f, $page, Catalog::PER_PAGE);
        $pages = max(1, (int)ceil($data['total'] / Catalog::PER_PAGE));
        $filtered = $f['q'] !== '' || $f['brand'] !== '' || $f['line'] !== '' || $f['type'] !== '' || ($other && $f['tint'] !== '');

        $opt = static fn(string $value, string $label, string $current): string => '<option value="' . ag_e($value) . '"' . ($value === $current ? ' selected' : '') . '>' . ag_e($label) . '</option>';
        // Списки фильтра берутся из справочников (порядок и флаги «показывать»). Значение из адреса, которого в списке нет, остаётся выбранным.
        $brandNames = Catalog::brands(false, !$other);
        if ($f['brand'] !== '' && !in_array($f['brand'], $brandNames, true)) {
            $brandNames[] = $f['brand'];
        }
        $brands = $opt('', 'Все бренды', $f['brand']);
        foreach ($brandNames as $b) {
            $brands .= $opt($b, $b, $f['brand']);
        }
        $typeNames = Dict::filterTypes(!$other);
        if ($f['type'] !== '' && !isset($typeNames[$f['type']]) && Dict::typeName($f['type']) !== '') {
            $typeNames[$f['type']] = Dict::typeName($f['type']);
        }
        $types = $opt('', 'Любой', $f['type']);
        foreach ($typeNames as $k => $label) {
            $types .= $opt((string)$k, $label, $f['type']);
        }
        $lineSel = '';
        $groups = [];
        foreach (Dict::filterLines($f['brand'], !$other) as $l) {
            $groups[$l['brand']][] = $l['name'];
        }
        if ($groups !== []) {
            $lines = $opt('', 'Любая', $f['line']);
            foreach ($groups as $gb => $names) {
                $lines .= ($f['brand'] === '' ? '<optgroup label="' . ag_e((string)$gb) . '">' : '');
                foreach ($names as $n) {
                    $lines .= $opt($n, $n, $f['line']);
                }
                $lines .= ($f['brand'] === '' ? '</optgroup>' : '');
            }
            $lineSel = '<div class="field"><label for="line">Линейка</label><select id="line" name="line">' . $lines . '</select></div>';
        }
        $tintSel = '';
        if ($badge) {
            $tintSel = '<div class="field"><label for="tint">Колеровка</label><select id="tint" name="tint">'
                . $opt('', 'Не важно', $f['tint']) . $opt('1', 'Колеруется', $f['tint']) . ($other ? $opt('0', 'Не колеруется', $f['tint']) : '') . '</select></div>';
        }
        $form = '<form class="filters" method="get" action="/katalog">'
            . '<div class="field"><label for="q">Поиск</label><input id="q" name="q" type="search" maxlength="80" value="' . ag_e($f['q']) . '" placeholder="Название или бренд"></div>'
            . '<div class="field"><label for="brand">Бренд</label><select id="brand" name="brand">' . $brands . '</select></div>'
            . '<div class="field"><label for="type">Тип</label><select id="type" name="type">' . $types . '</select></div>'
            . $lineSel
            . $tintSel
            . '<div class="filter-actions"><button class="btn btn-primary btn-sm" type="submit">Показать</button>'
            . ($filtered ? '<a class="btn btn-ghost btn-sm" href="/katalog">Сбросить</a>' : '') . '</div></form>';

        if ($data['items'] === []) {
            $results = Catalog::count() === 0
                ? '<div class="empty"><h2>Каталог скоро появится</h2><p>Мы наполняем каталог. А пока позвоните или напишите нам: подберём краску и расскажем про колеровку.</p><a class="btn btn-primary" href="/kontakty">Контакты</a></div>'
                : '<div class="empty"><h2>Ничего не нашлось</h2><p>Попробуйте убрать часть условий или <a href="/katalog">сбросить фильтры</a>. Если нужной краски нет в каталоге, позвоните: подберём.</p></div>';
        } else {
            $results = '';
            $group = null;
            $open = false;
            foreach ($data['items'] as $p) {
                $g = $p['tint'] ? 'premium' : 'other';
                if ($g !== $group) {
                    $results .= ($open ? '</div>' : '');
                    $results .= $g === 'premium'
                        ? '<h2 class="group-h">Премиум, колеруются</h2>'
                        : '<h2 class="group-h">Прочие краски</h2><p class="muted group-note">Эти краски продаются без колеровки.</p>';
                    $results .= '<div class="grid-cards">';
                    $group = $g;
                    $open = true;
                }
                $results .= Catalog::card($p);
            }
            $results .= $open ? '</div>' : '';
            if ($pages > 1) {
                $qs = array_filter(['q' => $f['q'], 'brand' => $f['brand'], 'line' => $f['line'], 'type' => $f['type'], 'tint' => $f['tint']], static fn($v): bool => $v !== '');
                $href = static fn(int $n): string => '/katalog?' . http_build_query($qs + ['str' => $n]);
                $results .= '<nav class="pager" aria-label="Страницы">'
                    . ($page > 1 ? '<a class="btn btn-ghost btn-sm" href="' . ag_e($href($page - 1)) . '" rel="prev">Назад</a>' : '')
                    . '<span class="muted">Страница ' . $page . ' из ' . $pages . '</span>'
                    . ($page < $pages ? '<a class="btn btn-ghost btn-sm" href="' . ag_e($href($page + 1)) . '" rel="next">Дальше</a>' : '')
                    . '</nav>';
            }
        }

        $body = '<section class="page-head"><div class="wrap"><h1>Каталог красок</h1>'
            . '<p class="lead">Колеруются только премиальные краски. Позиции с меткой можно заколеровать в нужный цвет.</p></div></section>'
            . '<section class="section section-flush"><div class="wrap catalog">' . $form . '<div class="results">' . $results . '</div></div></section>';
        // Адрес у всех страниц списка один (/katalog), поэтому заголовок и описание второй и следующих страниц отличаются номером.
        $suffix = $page > 1 ? ', страница ' . $page : '';
        return ModuleResponse::html('Каталог красок' . $suffix, $body)
            ->withDescription('Каталог премиальных красок с колеровкой' . $suffix . ': бренды, типы, наличие. Подберём цвет по образцу или по каталогу RAL.');
    }

    public static function product(array $p): ModuleResponse
    {
        $showPrices = Catalog::setting('show_prices', false) === true;
        $badgeOn = Catalog::setting('show_tint_badge', true) === true;
        $name = trim($p['brand'] . ' ' . $p['name']);

        $images = array_slice($p['images'], 0, 4);
        if ($images !== []) {
            $thumbs = '';
            foreach (array_slice($images, 1) as $i => $url) {
                $thumbs .= '<img src="' . ag_e($url) . '" alt="' . ag_e($name . ', фото ' . ($i + 2)) . '" loading="lazy">';
            }
            $gallery = '<div class="gallery"><div class="gallery-main"><img src="' . ag_e($images[0]) . '" alt="' . ag_e($name) . '"></div>'
                . ($thumbs !== '' ? '<div class="gallery-thumbs">' . $thumbs . '</div>' : '') . '</div>';
        } else {
            $tile = 'tile-' . Catalog::tile((string)$p['color']);
            $gallery = '<div class="gallery"><div class="gallery-main ' . $tile . '" role="img" aria-label="Фото будет добавлено: ' . ag_e($name) . '"><span class="ph">Здесь будет фото</span></div>'
                . '<div class="gallery-thumbs" aria-hidden="true"><span class="' . $tile . '"></span><span class="' . $tile . '"></span><span class="' . $tile . '"></span></div></div>';
        }

        $rows = ['Бренд' => $p['brand'], 'Линейка' => $p['line'], 'Тип' => Dict::typeName((string)$p['type']), 'Степень блеска' => $p['finish'], 'Основа' => $p['base'], 'Применение' => $p['use']] + $p['specs'];
        $dl = '';
        foreach ($rows as $k => $v) {
            if (trim((string)$v) !== '') {
                $dl .= '<div><dt>' . ag_e((string)$k) . '</dt><dd>' . ag_e((string)$v) . '</dd></div>';
            }
        }

        $stock = Catalog::STOCK[$p['in_stock']] ?? '';
        $prices = '';
        if ($showPrices && $p['variants'] !== []) {
            $prices = '<ul class="variants">';
            foreach ($p['variants'] as $v) {
                $prices .= '<li><span>' . ag_e($v['label']) . '</span><b>' . ((int)$v['price'] > 0 ? ag_e(Catalog::money((int)$v['price'])) : 'по запросу') . '</b></li>';
            }
            $prices .= '</ul>';
        }
        $fine = $showPrices ? 'Цена зависит от цвета: за колеровку тёмных и насыщенных оттенков может быть доплата.' : 'Цену и наличие уточняйте у менеджера: они зависят от объёма и цвета.';
        $hint = trim((string)Catalog::setting('phone_hint', ''));

        $badge = $badgeOn ? '<p class="badge ' . ($p['tint'] ? 'badge-on">Доступна колеровка' : 'badge-off">Без колеровки') . '</p>' : '';
        $info = '<div class="product-info"><p class="muted">' . ag_e($p['brand']) . '</p><h1>' . ag_e($p['name']) . '</h1>' . $badge
            . ($stock !== '' ? '<p class="muted">' . ag_e($stock) . '</p>' : '')
            . ($p['description'] !== '' ? '<p>' . ag_e($p['description']) . '</p>' : '')
            . $prices . ($dl !== '' ? '<dl class="specs">' . $dl . '</dl>' : '')
            . '<div class="cta-row"><a class="btn btn-primary" href="#forma">Уточнить наличие</a>'
            . ($p['tint'] ? '<a class="btn btn-ghost" href="/kolerovka">Про колеровку</a>' : '') . '</div>'
            . ($hint !== '' ? '<p class="fine">' . ag_e($hint) . '</p>' : '') . '<p class="fine">' . ag_e($fine) . '</p></div>';

        $lead = self::leadForm($name);
        $body = self::productJsonLd($p, $name, $showPrices)
            . '<section class="section section-flush"><div class="wrap">'
            . '<nav class="crumbs" aria-label="Путь"><a href="/katalog">Каталог</a><span>' . ag_e($p['brand']) . '</span></nav>'
            . '<div class="product">' . $gallery . $info . '</div></div></section>'
            . '<section class="section section-tint"><div class="wrap split"><div><h2>Уточнить наличие</h2>'
            . '<p class="lead">Оставьте номер, и мы перезвоним: подтвердим наличие, цену и срок колеровки.</p></div>'
            . '<div class="panel">' . $lead . '</div></div></section>';

        $desc = trim($name . '. ' . ($p['tint'] ? 'Доступна колеровка. ' : '') . ($p['description'] !== '' ? mb_substr($p['description'], 0, 160) : ''));
        return ModuleResponse::html($name, $body)->withDescription($desc);
    }

    /**
     * Разметка Product для поисковиков. Модульные страницы ядро выводит без своих строк в head, поэтому блок идёт в начале страницы:
     * это данные (application/ld+json), не исполняемый код. Предложения (offers) только при включённом показе цен и ценах у вариантов.
     *
     * @param array<string,mixed> $p
     */
    private static function productJsonLd(array $p, string $name, bool $showPrices): string
    {
        $site = Seo::siteUrl();
        $abs = static fn(string $u): string => str_starts_with($u, '/') ? $site . $u : $u;
        $url = Seo::url('katalog/' . $p['slug']);
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            '@id' => $url . '#product',
            'name' => $name,
            'sku' => (string)$p['slug'],
            'url' => $url,
        ];
        if (trim((string)$p['brand']) !== '') {
            $data['brand'] = ['@type' => 'Brand', 'name' => (string)$p['brand']];
        }
        $text = trim((string)$p['description']);
        $data['description'] = $text !== '' ? $text : $name;
        $imgs = array_map($abs, array_slice($p['images'], 0, 4));
        if ($imgs !== []) {
            $data['image'] = $imgs;
        }
        $category = Dict::typeName((string)$p['type']);
        if ($category !== '') {
            $data['category'] = $category;
        }
        if ($showPrices) {
            $availability = [0 => 'OutOfStock', 1 => 'InStock', 2 => 'PreOrder'][(int)$p['in_stock']] ?? null;
            $offers = [];
            foreach ($p['variants'] as $v) {
                if ((int)$v['price'] > 0) {
                    $offer = ['@type' => 'Offer', 'url' => $url, 'priceCurrency' => 'RUB', 'price' => (int)$v['price']];
                    if (trim((string)$v['label']) !== '') {
                        $offer['name'] = (string)$v['label'];
                    }
                    if ($availability !== null) {
                        $offer['availability'] = 'https://schema.org/' . $availability;
                    }
                    $offers[] = $offer;
                }
            }
            if ($offers !== []) {
                $data['offers'] = count($offers) === 1 ? $offers[0] : $offers;
            }
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR);
        return '<script type="application/ld+json">' . $json . '</script>';
    }

    /** Форма заявки: её отправляет core assets/app.js на /_/lead, токен он берёт из /_/token. */
    private static function leadForm(string $productName): string
    {
        return '<form class="form" id="forma" data-lead data-page="katalog" data-success="Спасибо, заявка отправлена. Мы перезвоним." method="post" action="#">'
            . '<div class="field"><label for="f-name">Имя</label><input id="f-name" name="name" type="text" maxlength="100" autocomplete="given-name"></div>'
            . '<div class="field"><label for="f-phone">Телефон</label><input id="f-phone" name="phone" type="tel" maxlength="30" autocomplete="tel" inputmode="tel" placeholder="+7 (___) ___-__-__" required></div>'
            . '<div class="field"><label for="f-msg">Сообщение</label><textarea id="f-msg" name="message" rows="3" maxlength="2000">' . ag_e('Товар: ' . $productName . "\n") . '</textarea></div>'
            . '<input class="hp" name="website_hp" type="text" tabindex="-1" autocomplete="off" aria-hidden="true">'
            . '<input name="token" type="hidden" value="">'
            . '<label class="check"><input type="checkbox" name="consent" value="1" required><span>Согласен на обработку персональных данных по <a href="/politika">политике</a></span></label>'
            . '<button class="btn btn-primary" type="submit">Отправить заявку</button>'
            . '<p class="form-status" role="status"></p></form>';
    }
}
