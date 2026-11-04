(function () {
    'use strict';
    document.documentElement.classList.add('js');

    // Меню на телефоне.
    var btn = document.querySelector('.menu-btn');
    var nav = document.getElementById('nav');
    if (btn && nav) {
        btn.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // Появление блоков при прокрутке: IntersectionObserver, без обработчика scroll.
    var items = document.querySelectorAll('.bento .cell, .steps li, .grid-cards .card, .swatches .sw, .way');
    items.forEach(function (el) { el.classList.add('reveal'); });
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); } });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });
        items.forEach(function (el) { io.observe(el); });
    } else {
        items.forEach(function (el) { el.classList.add('is-in'); });
    }

    // Выбор оттенка RAL подставляется в комментарий заявки на колеровку.
    var picked = null;
    var comment = document.querySelector('[data-tint-comment]');
    var note = document.querySelector('[data-ral-picked]');
    document.querySelectorAll('.ral-chip').forEach(function (chip) {
        chip.setAttribute('aria-pressed', 'false');
        chip.addEventListener('click', function () {
            document.querySelectorAll('.ral-chip[aria-pressed="true"]').forEach(function (c) { c.setAttribute('aria-pressed', 'false'); });
            chip.setAttribute('aria-pressed', 'true');
            var line = 'Оттенок: ' + chip.dataset.ral;
            if (note) { note.hidden = false; note.textContent = line + '. Он добавлен в заявку.'; }
            if (comment) {
                comment.value = comment.value.replace(/^Оттенок: .*\n?/m, '').trim();
                comment.value = (line + '\n' + comment.value).trim();
                picked = line;
            }
        });
    });

    // Форма обратной связи: почта и текст собираются в одно поле message, которое понимает ядро.
    document.querySelectorAll('form[data-lead]').forEach(function (form) {
        var email = form.querySelector('[data-contact-email]');
        var text = form.querySelector('[data-contact-text]');
        var msg = form.querySelector('[data-contact-message]');
        if (!msg) { return; }
        var sync = function () {
            var parts = [];
            if (email && email.value.trim() !== '') { parts.push('Почта: ' + email.value.trim()); }
            if (text && text.value.trim() !== '') { parts.push(text.value.trim()); }
            msg.value = parts.join('\n');
        };
        [email, text].forEach(function (el) { if (el) { el.addEventListener('input', sync); } });
        form.addEventListener('submit', sync, true);
    });
}());
