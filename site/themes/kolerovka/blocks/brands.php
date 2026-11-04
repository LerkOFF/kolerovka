<?php require_once dirname(__DIR__) . '/lib.php'; ?><?php /** @var array<string,mixed> $data */ /** @var string $id */ /** @var bool $editing */
$catalog = kl_catalog();
try {
    $brands = $catalog !== null ? $catalog::tintBrands() : [];
} catch (\Throwable) {
    $brands = [];
}
?>
        <section class="section section-tint" id="<?= ag_e($id) ?>">
            <div class="wrap">
                <h2<?= ag_edit('heading') ?>><?= ag_e($data['heading'] ?? 'Что можно заколеровать') ?></h2>
<?php if (($data['text'] ?? '') !== ''): ?>
                <p class="lead"<?= ag_edit('text', true) ?>><?= ag_e($data['text']) ?></p>
<?php endif; ?>
                <ul class="chips">
<?php foreach ($brands as $b): ?>
                    <li><a href="/katalog?brand=<?= ag_e(rawurlencode((string)$b)) ?>&amp;tint=1"><?= ag_e((string)$b) ?></a></li>
<?php endforeach; ?>
                </ul>
<?php if (($data['note'] ?? '') !== ''): ?>
                <p class="fine"><?= ag_e($data['note']) ?></p>
<?php endif; ?>
            </div>
        </section>
