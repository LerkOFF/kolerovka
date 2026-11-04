<?php require_once dirname(__DIR__) . '/lib.php'; ?><?php /** @var array<string,mixed> $data */ /** @var string $id */ /** @var string $slug */ /** @var bool $editing */ ?>
        <section class="section" id="<?= ag_e($id) ?>">
            <div class="wrap split">
                <div>
                    <h2<?= ag_edit('heading') ?>><?= ag_e($data['heading'] ?? '') ?></h2>
<?php if (($data['text'] ?? '') !== ''): ?>
                    <p class="lead"<?= ag_edit('text', true) ?>><?= ag_e($data['text']) ?></p>
<?php endif; ?>
<?php if (($data['bullets'] ?? []) !== []): ?>
                    <ul class="ticks">
<?php foreach ($data['bullets'] as $b): ?>
                        <li><?= kl_icon('check-circle') ?><span><?= ag_e($b['text'] ?? '') ?></span></li>
<?php endforeach; ?>
                    </ul>
<?php endif; ?>
<?php if (($data['cta_text'] ?? '') !== ''): ?>
                    <a class="btn btn-primary" href="<?= ag_e($data['cta_url'] ?? '/kolerovka') ?>"<?= ag_edit('cta_text') ?>><?= ag_e($data['cta_text']) ?></a>
<?php endif; ?>
                </div>
                <div class="panel">
                    <h3><?= ag_e($data['form_title'] ?? 'Быстрая заявка') ?></h3>
<?php $mode = 'tint'; $success = 'Спасибо, заявка отправлена. Мы перезвоним.'; $button = 'Отправить заявку'; require dirname(__DIR__) . '/form.php'; ?>
                </div>
            </div>
        </section>
