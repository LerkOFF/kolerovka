<?php require_once dirname(__DIR__) . '/lib.php'; ?><?php /** @var array<string,mixed> $data */ /** @var string $id */ /** @var bool $editing */
$catalog = kl_catalog();
try {
    $items = $catalog !== null ? $catalog::featured((int)($data['limit'] ?? 6)) : [];
} catch (\Throwable) {
    $items = [];
}
?>
        <section class="section section-tint" id="<?= ag_e($id) ?>">
            <div class="wrap">
                <div class="head-row">
                    <h2<?= ag_edit('heading') ?>><?= ag_e($data['heading'] ?? 'Популярные позиции') ?></h2>
                    <a class="link-arrow" href="/katalog">Весь каталог <?= kl_icon('arrow-right') ?></a>
                </div>
<?php if ($items === []): ?>
                <p class="muted">Каталог скоро пополнится.</p>
<?php else: ?>
                <div class="grid-cards">
<?php foreach ($items as $row): ?>
                    <?= $catalog::card($row) ?>

<?php endforeach; ?>
                </div>
<?php endif; ?>
            </div>
        </section>
