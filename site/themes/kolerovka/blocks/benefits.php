<?php require_once dirname(__DIR__) . '/lib.php'; ?><?php /** @var array<string,mixed> $data */ /** @var string $id */ /** @var bool $editing */
$items = $data['items'] ?? [];
$accent = 'ral-6021';
$shape = ['cell-wide cell-ink', 'cell-swatch', '', 'cell-wide'];
?>
        <section class="section" id="<?= ag_e($id) ?>">
            <div class="wrap">
<?php if (($data['heading'] ?? '') !== ''): ?>
                <h2<?= ag_edit('heading') ?>><?= ag_e($data['heading']) ?></h2>
<?php endif; ?>
                <div class="bento">
<?php foreach ($items as $i => $item): ?>
                    <div class="cell <?= ag_e($shape[$i] ?? '') ?><?= ($shape[$i] ?? '') === 'cell-swatch' ? ' ' . $accent : '' ?>">
<?php if (($item['icon'] ?? '') !== '' && ($shape[$i] ?? '') !== 'cell-swatch'): ?>
                        <?= kl_icon($item['icon'], 'ic ic-lg') ?>
<?php endif; ?>
                        <h3><?= ag_e($item['title'] ?? '') ?></h3>
                        <p><?= ag_e($item['text'] ?? '') ?></p>
                    </div>
<?php endforeach; ?>
                </div>
            </div>
        </section>
