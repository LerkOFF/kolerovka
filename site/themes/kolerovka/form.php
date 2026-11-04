<?php
/** Общая форма заявки для блоков. Параметры: $mode (tint|contact), $success, $button, $slug. */
$uid = 'f' . substr(md5($id ?? uniqid('', true)), 0, 6);
?>
                    <form class="form" data-lead data-page="<?= ag_e($slug) ?>" data-success="<?= ag_e($success) ?>" method="post" action="#" novalidate>
                        <div class="field"><label for="<?= $uid ?>-n">Имя</label><input id="<?= $uid ?>-n" name="name" type="text" maxlength="100" autocomplete="given-name" required></div>
                        <div class="field"><label for="<?= $uid ?>-p">Телефон</label><input id="<?= $uid ?>-p" name="phone" type="tel" maxlength="30" autocomplete="tel" inputmode="tel" placeholder="+7 ___ ___-__-__" required></div>
<?php if ($mode === 'contact'): ?>
                        <div class="field"><label for="<?= $uid ?>-m">Сообщение</label><textarea id="<?= $uid ?>-m" name="message" rows="4" maxlength="1800"></textarea></div>
<?php else: ?>
                        <div class="field"><label for="<?= $uid ?>-m">Что нужно заколеровать</label><textarea id="<?= $uid ?>-m" name="message" rows="4" maxlength="1800" data-tint-comment></textarea></div>
<?php endif; ?>
                        <input class="hp" name="website_hp" type="text" tabindex="-1" autocomplete="off" aria-hidden="true">
                        <input name="token" type="hidden" value="">
                        <label class="check"><input type="checkbox" name="consent" value="1" required><span>Согласен на обработку персональных данных по <a href="/politika">политике</a></span></label>
                        <button class="btn btn-primary" type="submit"><?= ag_e($button) ?></button>
                        <p class="form-status" role="status"></p>
                    </form>
