<?php require_once dirname(__DIR__) . '/lib.php'; ?><?php /** @var array<string,mixed> $data */ /** @var string $id */ /** @var string $slug */ /** @var bool $editing */ ?>
        <section class="section section-tint" id="<?= ag_e($id) ?>">
            <div class="wrap split">
                <div>
                    <h2<?= ag_edit('heading') ?>><?= ag_e($data['heading'] ?? 'Заявка на колеровку') ?></h2>
<?php if (($data['text'] ?? '') !== ''): ?>
                    <p class="lead"<?= ag_edit('text', true) ?>><?= ag_e($data['text']) ?></p>
<?php endif; ?>
                    <p class="fine" data-ral-picked hidden></p>
                </div>
                <div class="panel">
<?php $mode = ($data['mode'] ?? 'tint') === 'contact' ? 'contact' : 'tint';
$success = (string)($data['success_text'] ?? 'Спасибо, заявка отправлена. Мы перезвоним.');
$button = (string)($data['button_text'] ?? ($mode === 'tint' ? 'Отправить заявку' : 'Отправить сообщение'));
require dirname(__DIR__) . '/form.php'; ?>
                </div>
            </div>
        </section>
