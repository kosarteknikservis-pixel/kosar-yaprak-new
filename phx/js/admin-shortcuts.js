(function () {
    'use strict';

    var modal = document.getElementById('adminShortcuts');
    if (!modal) return;

    var body = document.body;

    function isTyping() {
        var el = document.activeElement;
        if (!el) return false;
        var tag = (el.tagName || '').toLowerCase();
        return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable;
    }

    function openShortcuts() {
        modal.hidden = false;
        document.documentElement.classList.add('admin-shortcuts-open');
    }

    function closeShortcuts() {
        modal.hidden = true;
        document.documentElement.classList.remove('admin-shortcuts-open');
    }

    function go(url) {
        if (url) window.location.href = url;
    }

    modal.querySelectorAll('[data-shortcuts-close]').forEach(function (el) {
        el.addEventListener('click', closeShortcuts);
    });

    var trigger = document.getElementById('adminShortcutsTrigger');
    if (trigger) {
        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            modal.hidden ? openShortcuts() : closeShortcuts();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (!modal.hidden) {
                e.preventDefault();
                closeShortcuts();
            }
            return;
        }

        if (isTyping()) return;

        if (e.key === '?' && !e.ctrlKey && !e.metaKey && !e.altKey) {
            e.preventDefault();
            modal.hidden ? openShortcuts() : closeShortcuts();
            return;
        }

        if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey) {
            var search = document.getElementById('adminMenuSearchInput');
            if (search) {
                e.preventDefault();
                search.focus();
                search.select();
            }
            return;
        }

        if (!e.altKey || e.ctrlKey || e.metaKey) return;

        var k = e.key.toLowerCase();
        var map = {
            d: body.getAttribute('data-url-dashboard'),
            o: body.getAttribute('data-url-orders'),
            y: body.getAttribute('data-url-abandoned'),
            s: body.getAttribute('data-url-support')
        };

        if (map[k]) {
            e.preventDefault();
            go(map[k]);
        }
    });
})();
