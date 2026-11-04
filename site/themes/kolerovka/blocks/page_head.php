<?php require_once dirname(__DIR__) . '/lib.php'; ?><?php /** @var array<string,mixed> $data */ /** @var string $id */ /** @var bool $editing */ ?>
        <section class="page-head" id="<?= ag_e($id) ?>">
            <div class="wrap">
                <h1<?= ag_edit('h1') ?>><?= ag_e($data['h1'] ?? '') ?></h1>
<?php if (($data['text'] ?? '') !== '' || $editing): ?>
                <p class="lead"<?= ag_edit('text', true) ?>><?= ag_e($data['text'] ?? '') ?></p>
<?php endif; ?>
            </div>
        </section>
