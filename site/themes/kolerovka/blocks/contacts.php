<?php require_once dirname(__DIR__) . '/lib.php'; ?><?php /** @var array<string,mixed> $data */ /** @var string $id */ /** @var bool $editing */
$s = kl_site();
$rows = [];
$hours = array_filter(array_map('trim', preg_split('/\R/u', $s['hours']) ?: []));
?>
        <section class="section" id="<?= ag_e($id) ?>">
            <div class="wrap split">
                <div>
                    <h2<?= ag_edit('heading') ?>><?= ag_e($data['heading'] ?? 'Где нас найти') ?></h2>
                    <dl class="contact-list">
                        <div><dt><?= kl_icon('map-pin') ?>Адрес</dt><dd><?= $s['address'] !== '' ? ag_e($s['address']) : '<span class="muted">будет указан</span>' ?></dd></div>
                        <div><dt><?= kl_icon('phone') ?>Телефон</dt><dd><?= $s['phone'] !== '' ? '<a href="' . ag_e(kl_tel($s['phone'])) . '">' . ag_e($s['phone']) . '</a>' : '<span class="muted">будет указан</span>' ?></dd></div>
<?php if ($s['email'] !== ''): ?>
                        <div><dt><?= kl_icon('envelope-simple') ?>Почта</dt><dd><a href="mailto:<?= ag_e($s['email']) ?>"><?= ag_e($s['email']) ?></a></dd></div>
<?php endif; ?>
                        <div><dt><?= kl_icon('clock') ?>График</dt><dd><?php if ($hours === []): ?><span class="muted">будет указан</span><?php else: foreach ($hours as $line): ?><?= ag_e($line) ?><br><?php endforeach; endif; ?></dd></div>
                    </dl>
<?php if (!empty($data['show_cta']) && ($s['phone'] !== '' || $s['telegram'] !== '' || $s['max'] !== '' || $s['address'] !== '')): ?>
                    <div class="cta-row">
<?php if ($s['phone'] !== ''): ?><a class="btn btn-primary" href="<?= ag_e(kl_tel($s['phone'])) ?>">Позвонить</a><?php endif; ?>
<?php if ($s['telegram'] !== ''): ?><a class="btn btn-ghost" href="<?= ag_e($s['telegram']) ?>" rel="noopener">Telegram</a><?php endif; ?>
<?php if ($s['max'] !== ''): ?><a class="btn btn-ghost" href="<?= ag_e($s['max']) ?>" rel="noopener">Max</a><?php endif; ?>
<?php if ($s['address'] !== ''): ?><a class="btn btn-ghost" href="<?= ag_e('https://yandex.ru/maps/?text=' . rawurlencode($s['address'])) ?>" rel="noopener" target="_blank">Маршрут на карте</a><?php endif; ?>
                    </div>
<?php endif; ?>
                </div>
                <div class="map">
<?php if ($s['map'] !== ''): ?>
                    <iframe src="<?= ag_e($s['map']) ?>" title="Карта проезда" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
<?php else: ?>
                    <div class="map-empty"><?= kl_icon('map-pin', 'ic ic-lg') ?><p>Карта появится, когда будет готова точка на Яндекс.Картах.</p></div>
<?php endif; ?>
                </div>
            </div>
        </section>
