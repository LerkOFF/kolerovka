<?php require_once dirname(__DIR__) . '/lib.php'; ?><?php /** @var array<string,mixed> $data */ /** @var string $id */ /** @var bool $editing */
$chips = array_slice(kl_ral(), 0, 12);
?>
        <section class="hero" id="<?= ag_e($id) ?>">
            <div class="wrap hero-grid">
                <div class="hero-copy">
                    <h1<?= ag_edit('h1') ?>><?= ag_e($data['h1'] ?? '') ?></h1>
<?php if (($data['text'] ?? '') !== ''): ?>
                    <p class="lead"<?= ag_edit('text', true) ?>><?= ag_e($data['text']) ?></p>
<?php endif; ?>
                    <div class="cta-row">
<?php if (($data['cta_text'] ?? '') !== ''): ?>
                        <a class="btn btn-primary" href="<?= ag_e($data['cta_url'] ?? '/kolerovka#zayavka') ?>"<?= ag_edit('cta_text') ?>><?= ag_e($data['cta_text']) ?></a>
<?php endif; ?>
<?php if (($data['cta2_text'] ?? '') !== ''): ?>
                        <a class="btn btn-ghost" href="<?= ag_e($data['cta2_url'] ?? '/katalog') ?>"<?= ag_edit('cta2_text') ?>><?= ag_e($data['cta2_text']) ?></a>
<?php endif; ?>
                    </div>
                </div>
                <div class="swatches" aria-label="Примеры оттенков RAL">
<?php foreach ($chips as $i => $c): ?>
                    <figure class="sw sw-<?= $i + 1 ?> ral-<?= ag_e(preg_replace('/\D/', '', $c['code'])) ?>"><figcaption><?= ag_e($c['code']) ?></figcaption></figure>
<?php endforeach; ?>
                </div>
            </div>
        </section>
