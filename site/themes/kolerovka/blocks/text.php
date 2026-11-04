<?php require_once dirname(__DIR__) . '/lib.php'; ?><?php /** @var array<string,mixed> $data */ /** @var string $id */ /** @var bool $editing */ ?>
        <section class="section section-flush" id="<?= ag_e($id) ?>">
            <div class="wrap prose">
<?php if (($data['heading'] ?? '') !== ''): ?>
                <h2<?= ag_edit('heading') ?>><?= ag_e($data['heading']) ?></h2>
<?php endif; ?>
                <div class="rich"<?= ag_edit_rich('body') ?>><?= ag_rich($data['body'] ?? '') ?></div>
            </div>
        </section>
