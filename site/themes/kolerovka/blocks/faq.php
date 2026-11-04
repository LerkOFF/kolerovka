<?php require_once dirname(__DIR__) . '/lib.php'; ?><?php /** @var array<string,mixed> $data */ /** @var string $id */ /** @var bool $editing */ ?>
<?php
$items = array_values(array_filter($data['items'] ?? [], static fn($i): bool => is_array($i) && trim((string)($i['q'] ?? '')) !== '' && trim((string)($i['a'] ?? '')) !== ''));
$schema = [];
foreach ($items as $i) {
    $schema[] = ['@type' => 'Question', 'name' => (string)$i['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => (string)$i['a']]];
}
?>
        <section class="section section-flush" id="<?= ag_e($id) ?>">
            <div class="wrap prose">
<?php if (($data['heading'] ?? '') !== ''): ?>
                <h2<?= ag_edit('heading') ?>><?= ag_e($data['heading']) ?></h2>
<?php endif; ?>
                <div class="faq">
<?php foreach ($items as $n => $i): ?>
                    <details class="faq-item"<?= $n === 0 ? ' open' : '' ?>>
                        <summary><span><?= ag_e((string)$i['q']) ?></span><?= kl_icon('caret-down', 'ic faq-ic') ?></summary>
                        <p><?= ag_e((string)$i['a']) ?></p>
                    </details>
<?php endforeach; ?>
                </div>
            </div>
<?php if ($schema !== []): ?>
            <script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $schema], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>
        </section>
