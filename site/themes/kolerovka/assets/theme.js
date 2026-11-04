(function () {
    'use strict';
    document.documentElement.classList.add('js');

    // Переключатель темы: светлая или тёмная, выбор хранится в браузере.
    var themeBtn = document.querySelector('.theme-btn');
    if (themeBtn) {
        themeBtn.addEventListener('click', function () {
            var root = document.documentElement;
            var cur = root.getAttribute('data-theme') || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            var next = cur === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            try { localStorage.setItem('kl-theme', next); } catch (e) {}
        });
    }

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
    var chips = document.querySelectorAll('.ral-chip');
    var pressed = null;
    chips.forEach(function (chip) {
        chip.setAttribute('aria-pressed', 'false');
        chip.addEventListener('click', function () {
            if (pressed) { pressed.setAttribute('aria-pressed', 'false'); }
            chip.setAttribute('aria-pressed', 'true');
            pressed = chip;
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

    // Каталог RAL: фильтр по семейству и поиск по коду или названию. Без скрипта все плитки видны.
    document.querySelectorAll('[data-ral-picker]').forEach(function (picker) {
        var tools = picker.querySelector('[data-ral-tools]');
        var search = picker.querySelector('[data-ral-search]');
        var tabs = picker.querySelectorAll('[data-ral-family]');
        var groups = picker.querySelectorAll('[data-ral-group]');
        var items = picker.querySelectorAll('[data-ral-item]');
        var status = picker.querySelector('[data-ral-status]');
        var empty = picker.querySelector('[data-ral-empty]');
        if (!tools || !search) { return; }
        tools.hidden = false;
        var family = 'all';
        var norm = function (v) { return v.toLowerCase().replace(/ё/g, 'е').replace(/\s+/g, ' ').trim(); };
        var apply = function () {
            var q = norm(search.value);
            var words = q === '' ? [] : q.split(' ');
            var shown = 0;
            groups.forEach(function (g) {
                var inFamily = family === 'all' || g.getAttribute('data-ral-group') === family;
                var any = 0;
                g.querySelectorAll('[data-ral-item]').forEach(function (li) {
                    var hay = li.getAttribute('data-find') || '';
                    var ok = inFamily && words.every(function (w) { return hay.indexOf(w) !== -1; });
                    li.hidden = !ok;
                    if (ok) { any++; }
                });
                g.hidden = any === 0;
                shown += any;
            });
            if (status) {
                status.textContent = shown === 0 ? 'Ничего не найдено' : 'Показано оттенков: ' + shown + ' из ' + items.length;
            }
            if (empty) { empty.hidden = shown !== 0; }
        };
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                family = tab.getAttribute('data-ral-family');
                tabs.forEach(function (t) { t.setAttribute('aria-pressed', t === tab ? 'true' : 'false'); });
                apply();
            });
        });
        search.addEventListener('input', apply);
        search.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && search.value !== '') { search.value = ''; apply(); }
        });
        apply();
    });
}());
