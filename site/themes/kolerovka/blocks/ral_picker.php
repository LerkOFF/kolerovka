<?php require_once dirname(__DIR__) . '/lib.php'; ?><?php /** @var array<string,mixed> $data */ /** @var string $id */ /** @var bool $editing */
$ways = array_values(array_filter($data['ways'] ?? [], static fn($w) => !empty($w['show'])));
$families = kl_ral_families();
$groups = [];
foreach (kl_ral() as $c) {
    $groups[substr($c['code'], 0, 1)][] = $c;
}
$total = 0;
foreach ($groups as $list) {
    $total += count($list);
}
$uid = preg_replace('/[^a-z0-9_-]/i', '', $id);
?>
        <section class="section" id="<?= ag_e($id) ?>">
            <div class="wrap">
                <h2<?= ag_edit('heading') ?>><?= ag_e($data['heading'] ?? 'Как выбрать цвет') ?></h2>
<?php if ($ways !== []): ?>
                <div class="ways">
<?php foreach ($ways as $w): ?>
                    <div class="way"><h3><?= ag_e($w['title'] ?? '') ?></h3><p><?= ag_e($w['text'] ?? '') ?></p></div>
<?php endforeach; ?>
                </div>
<?php endif; ?>
                <div class="ral-picker" data-ral-picker>
                    <div class="ral-tools" data-ral-tools hidden>
                        <div class="ral-search">
                            <label for="<?= ag_e($uid) ?>-q">Найти по коду или названию</label>
                            <input type="search" id="<?= ag_e($uid) ?>-q" placeholder="Например, RAL 6021 или зелен" autocomplete="off" spellcheck="false" data-ral-search>
                        </div>
                        <div class="ral-tabs" role="group" aria-label="Семейство цветов RAL">
                            <button type="button" class="ral-tab" data-ral-family="all" aria-pressed="true"><span>Все</span><span class="ral-tab-note"><?= (int)$total ?></span></button>
<?php foreach ($families as $digit => $label): ?>
<?php if (isset($groups[$digit])): ?>
                            <button type="button" class="ral-tab" data-ral-family="<?= ag_e((string)$digit) ?>" aria-pressed="false"><span><?= ag_e($label) ?></span><span class="ral-tab-note">RAL <?= ag_e($digit) ?>xxx</span></button>
<?php endif; ?>
<?php endforeach; ?>
                        </div>
                        <p class="ral-status" role="status" aria-live="polite" data-ral-status></p>
                    </div>
<?php foreach ($families as $digit => $label): ?>
<?php if (!isset($groups[$digit])) { continue; } ?>
                    <div class="ral-group" data-ral-group="<?= ag_e((string)$digit) ?>">
                        <h3 class="ral-group-title" id="<?= ag_e($uid) ?>-f<?= ag_e((string)$digit) ?>"><?= ag_e($label) ?> <span>RAL <?= ag_e($groups[$digit][0]['code']) ?>-<?= ag_e($groups[$digit][count($groups[$digit]) - 1]['code']) ?></span></h3>
                        <ul class="ral-grid" aria-labelledby="<?= ag_e($uid) ?>-f<?= ag_e((string)$digit) ?>">
<?php foreach ($groups[$digit] as $c): ?>
<?php $find = str_replace('ё', 'е', mb_strtolower('ral ' . $c['code'] . ' ' . $c['name'])); ?>
                            <li data-ral-item data-find="<?= ag_e($find) ?>"><button type="button" class="ral-chip ral-<?= ag_e($c['code']) ?><?= kl_ral_is_light($c['hex']) ? ' is-light' : ' is-dark' ?>" data-ral="<?= ag_e('RAL ' . $c['code'] . ' (' . $c['name'] . ')') ?>" aria-label="<?= ag_e('RAL ' . $c['code'] . ', ' . $c['name']) ?>">
                                <span class="ral-code">RAL <?= ag_e($c['code']) ?></span>
                                <span class="ral-name"><?= ag_e($c['name']) ?></span>
                            </button></li>
<?php endforeach; ?>
                        </ul>
                    </div>
<?php endforeach; ?>
                    <p class="ral-empty" data-ral-empty hidden>Ничего не нашлось. Проверьте код или название, либо назовите оттенок в заявке: подберём по образцу или по NCS.</p>
                </div>
<?php if (($data['note'] ?? '') !== ''): ?>
                <p class="fine"><?= ag_e($data['note']) ?></p>
<?php endif; ?>
            </div>
        </section>
