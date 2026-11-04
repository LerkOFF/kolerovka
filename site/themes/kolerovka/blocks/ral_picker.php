<?php require_once dirname(__DIR__) . '/lib.php'; ?><?php /** @var array<string,mixed> $data */ /** @var string $id */ /** @var bool $editing */
$ways = array_values(array_filter($data['ways'] ?? [], static fn($w) => !empty($w['show'])));
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
                <div class="ral" role="list">
<?php foreach (kl_ral() as $c): ?>
                    <button type="button" class="ral-chip ral-<?= ag_e(preg_replace('/\D/', '', $c['code'])) ?>" role="listitem" data-ral="<?= ag_e($c['code'] . ' (' . $c['name'] . ')') ?>" aria-label="<?= ag_e($c['code'] . ', ' . $c['name']) ?>">
                        <span class="ral-code"><?= ag_e($c['code']) ?></span>
                        <span class="ral-name"><?= ag_e($c['name']) ?></span>
                    </button>
<?php endforeach; ?>
                </div>
<?php if (($data['note'] ?? '') !== ''): ?>
                <p class="fine"><?= ag_e($data['note']) ?></p>
<?php endif; ?>
            </div>
        </section>
