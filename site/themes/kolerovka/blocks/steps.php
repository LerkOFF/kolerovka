<?php require_once dirname(__DIR__) . '/lib.php'; ?><?php /** @var array<string,mixed> $data */ /** @var string $id */ /** @var bool $editing */ ?>
        <section class="section section-flush" id="<?= ag_e($id) ?>">
            <div class="wrap">
<?php if (($data['heading'] ?? '') !== ''): ?>
                <h2<?= ag_edit('heading') ?>><?= ag_e($data['heading']) ?></h2>
<?php endif; ?>
                <ol class="steps">
<?php foreach ($data['items'] ?? [] as $item): ?>
                    <li>
                        <?= kl_icon($item['icon'] ?? 'drop', 'ic ic-lg') ?>
                        <h3><?= ag_e($item['title'] ?? '') ?></h3>
                        <p><?= ag_e($item['text'] ?? '') ?></p>
                    </li>
<?php endforeach; ?>
                </ol>
            </div>
        </section>
