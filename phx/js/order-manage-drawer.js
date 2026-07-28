(function () {
    'use strict';

    var overlay = null;
    var iframe = null;

    function ensureShell() {
        if (overlay) return;

        overlay = document.createElement('div');
        overlay.className = 'order-manage-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Sipariş düzenle');
        overlay.innerHTML =
            '<div class="order-manage-overlay__backdrop" data-om-close></div>' +
            '<div class="order-manage-overlay__panel">' +
            '  <div class="order-manage-overlay__head">' +
            '    <div>' +
            '      <div class="order-manage-overlay__eyebrow"><i class="fas fa-receipt"></i> Sipariş düzenle</div>' +
            '      <h2 class="order-manage-overlay__title" id="orderManageModalTitle">Yükleniyor…</h2>' +
            '    </div>' +
            '    <div class="order-manage-overlay__actions">' +
            '      <button type="button" class="btn btn-sm btn-light" id="orderManageModalReload" title="Yenile"><i class="fas fa-sync-alt"></i></button>' +
            '      <button type="button" class="order-manage-overlay__close" id="orderManageModalClose" aria-label="Kapat"><i class="fas fa-times"></i></button>' +
            '    </div>' +
            '  </div>' +
            '  <div class="order-manage-overlay__body">' +
            '    <div class="order-manage-overlay__loading" id="orderManageModalLoading"><i class="fas fa-spinner fa-spin"></i> Yükleniyor…</div>' +
            '    <iframe class="order-manage-overlay__frame" id="orderManageModalFrame" title="Sipariş düzenleme"></iframe>' +
            '  </div>' +
            '</div>';

        document.body.appendChild(overlay);

        overlay.querySelector('#orderManageModalClose').addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            closeOrderManage();
        });

        overlay.querySelector('[data-om-close]').addEventListener('click', closeOrderManage);

        overlay.querySelector('.order-manage-overlay__panel').addEventListener('click', function (e) {
            e.stopPropagation();
        });

        overlay.querySelector('#orderManageModalReload').addEventListener('click', function (e) {
            e.preventDefault();
            if (iframe && iframe.src && iframe.src !== 'about:blank') {
                iframe.src = iframe.src;
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay && overlay.classList.contains('is-open')) {
                closeOrderManage();
            }
        });

        window.addEventListener('message', function (e) {
            if (!e.data || e.data.type !== 'order-manage-saved') return;
            closeOrderManage();
            setTimeout(function () { location.reload(); }, 200);
        });
    }

    function buildEmbedUrl(url) {
        try {
            var u = new URL(url, window.location.href);
            u.searchParams.set('embed', '1');
            u.searchParams.set('popup', '1');
            return u.toString();
        } catch (err) {
            var sep = url.indexOf('?') >= 0 ? '&' : '?';
            return url + sep + 'embed=1&popup=1';
        }
    }

    function openOrderManage(url) {
        ensureShell();

        var match = url.match(/order_id=(\d+)/);
        var orderId = match ? match[1] : '';
        document.getElementById('orderManageModalTitle').textContent = orderId ? 'Sipariş #' + orderId : 'Sipariş';

        var loading = document.getElementById('orderManageModalLoading');
        iframe = document.getElementById('orderManageModalFrame');
        loading.style.display = 'flex';
        iframe.classList.remove('is-ready');

        iframe.onload = function () {
            loading.style.display = 'none';
            iframe.classList.add('is-ready');
        };
        iframe.src = buildEmbedUrl(url);

        overlay.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        document.getElementById('orderManageModalClose').focus();
    }

    function closeOrderManage() {
        if (!overlay) return;
        overlay.classList.remove('is-open');
        document.body.style.overflow = '';
        if (iframe) {
            iframe.src = 'about:blank';
            iframe.classList.remove('is-ready');
        }
    }

    window.openOrderManage = openOrderManage;
    window.closeOrderManage = closeOrderManage;
})();
