/**
 * phxcore0 Admin — tema geçiş kontrolcüsü (aydınlık / karanlık).
 * Görünüm (CSS) ile ayrık; tercih tarayıcıda saklanır. Varsayılan: aydınlık.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'phx_theme';
    var root = document.documentElement;

    function currentTheme() {
        var t = root.getAttribute('data-theme');
        return t === 'dark' ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        var t = theme === 'dark' ? 'dark' : 'light';
        root.setAttribute('data-theme', t);
        root.setAttribute('data-bs-theme', t);
        try { localStorage.setItem(STORAGE_KEY, t); } catch (e) {}
        updateButton(t);
    }

    function updateButton(t) {
        var btn = document.getElementById('adminThemeToggle');
        if (!btn) return;
        var next = t === 'dark' ? 'aydınlık' : 'karanlık';
        btn.setAttribute('title', 'Tema: ' + (t === 'dark' ? 'karanlık' : 'aydınlık') + ' — ' + next + ' moda geç');
        btn.setAttribute('aria-pressed', t === 'dark' ? 'true' : 'false');
    }

    function toggle() {
        applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
    }

    document.addEventListener('DOMContentLoaded', function () {
        updateButton(currentTheme());
        var btn = document.getElementById('adminThemeToggle');
        if (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                toggle();
            });
        }
    });

    // Alt+T ile hızlı geçiş (form alanında değilken)
    document.addEventListener('keydown', function (e) {
        if (!e.altKey || e.ctrlKey || e.metaKey) return;
        var el = document.activeElement;
        var tag = el ? (el.tagName || '').toLowerCase() : '';
        if (tag === 'input' || tag === 'textarea' || tag === 'select' || (el && el.isContentEditable)) return;
        if ((e.key || '').toLowerCase() === 't') {
            e.preventDefault();
            toggle();
        }
    });
})();
