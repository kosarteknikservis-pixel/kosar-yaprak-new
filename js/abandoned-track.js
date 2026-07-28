(function (global) {
    'use strict';

    var cfg = global.__abandonedConfig;
    if (!cfg || !cfg.enabled) {
        return;
    }

    var fired = { scroll: false, products: false };
    var debounceTimer = null;
    var activeProduct = cfg.product && cfg.product.id ? Object.assign({}, cfg.product) : null;

    function scrollStorageKey() {
        return 'yk_scroll_' + (cfg.page || 'page');
    }

    function productsStorageKey() {
        return 'yk_products_' + (cfg.page || 'page');
    }

    function triggerAlreadyFired(type) {
        if (fired[type]) {
            return true;
        }
        try {
            return sessionStorage.getItem(type === 'scroll' ? scrollStorageKey() : productsStorageKey()) === '1';
        } catch (e) {
            return false;
        }
    }

    function markTriggerFired(type) {
        fired[type] = true;
        try {
            sessionStorage.setItem(type === 'scroll' ? scrollStorageKey() : productsStorageKey(), '1');
        } catch (e) {
            /* noop */
        }
    }

    function readField(id) {
        if (!id) {
            return '';
        }
        var el = document.getElementById(id);
        if (!el) {
            return '';
        }
        return String(el.value || '').trim();
    }

    function persistDraft(ad, tel) {
        try {
            if (ad) {
                sessionStorage.setItem('yk_draft_ad', ad);
            }
            if (tel) {
                sessionStorage.setItem('yk_draft_tel', tel);
            }
        } catch (e) {
            /* noop */
        }
    }

    function readDraft() {
        var ad = '';
        var tel = '';
        try {
            ad = sessionStorage.getItem('yk_draft_ad') || '';
            tel = sessionStorage.getItem('yk_draft_tel') || '';
        } catch (e) {
            /* noop */
        }
        return { ad: ad.trim(), tel: tel.trim() };
    }

    function resolveProduct() {
        if (activeProduct && activeProduct.id) {
            return activeProduct;
        }
        if (cfg.product && cfg.product.id) {
            return cfg.product;
        }

        var cards = document.querySelectorAll('[data-meta-price="true"]');
        if (cards.length) {
            var node = cards[0];
            return {
                id: parseInt(node.getAttribute('data-product-id') || '0', 10) || 0,
                name: node.getAttribute('data-product-name') || '',
                price: String(node.textContent || '').trim(),
            };
        }

        return { id: 0, name: '', price: '' };
    }

    function canSend(ad, tel, product) {
        if (ad || tel) {
            return true;
        }
        if (!cfg.productOnly) {
            return false;
        }
        return !!(product.id || product.name);
    }

    function send(reason) {
        if (reason === 'scroll' && triggerAlreadyFired('scroll')) {
            return;
        }
        if ((reason === 'products' || reason === 'product_click' || reason === 'order_page') && triggerAlreadyFired('products')) {
            return;
        }

        var fields = cfg.fields || {};
        var ad = readField(fields.name);
        var tel = readField(fields.phone);
        var draft = readDraft();

        if (!ad && draft.ad) {
            ad = draft.ad;
        }
        if (!tel && draft.tel) {
            tel = draft.tel;
        }

        if (ad || tel) {
            persistDraft(ad, tel);
        }

        var product = resolveProduct();
        if (!canSend(ad, tel, product)) {
            return;
        }

        if (reason === 'scroll') {
            markTriggerFired('scroll');
        }
        if (reason === 'products' || reason === 'product_click' || reason === 'order_page') {
            markTriggerFired('products');
        }

        var fd = new FormData();
        fd.append('ad', ad);
        fd.append('tel', tel);
        fd.append('urun', product.name || '');
        fd.append('fiyat', product.price || '');
        if (product.id) {
            fd.append('product_id', String(product.id));
        }
        fd.append('trigger', reason || 'unknown');

        var url = cfg.endpoint || 'ajax/abandoned_save.php';
        fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function () {
            if (reason === 'scroll') {
                fired.scroll = false;
                try {
                    sessionStorage.removeItem(scrollStorageKey());
                } catch (e) {
                    /* noop */
                }
            }
            if (reason === 'products') {
                fired.products = false;
                try {
                    sessionStorage.removeItem(productsStorageKey());
                } catch (e) {
                    /* noop */
                }
            }
        });
    }

    function scheduleSend(reason) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            send(reason);
        }, 400);
    }

    function bindFormTriggers() {
        if (cfg.page === 'order') {
            return;
        }
        if (!cfg.triggers || !cfg.triggers.form) {
            return;
        }
        var fields = cfg.fields || {};
        [fields.name, fields.phone].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) {
                return;
            }
            el.addEventListener('blur', function () {
                scheduleSend('form');
            });
            el.addEventListener('input', function () {
                persistDraft(readField(fields.name), readField(fields.phone));
            });
        });
    }

    function bindScrollTrigger() {
        if (cfg.page === 'order') {
            return;
        }
        if (!cfg.triggers || !cfg.triggers.scroll) {
            return;
        }
        var pct = parseInt(String(cfg.scrollPct || 50), 10);
        if (isNaN(pct) || pct < 10) {
            pct = 50;
        }

        function checkScroll() {
            if (triggerAlreadyFired('scroll')) {
                return;
            }
            var doc = document.documentElement;
            var scrollTop = global.pageYOffset || doc.scrollTop || 0;
            var maxScroll = Math.max(1, (doc.scrollHeight || 0) - (global.innerHeight || doc.clientHeight || 0));
            if ((scrollTop / maxScroll) * 100 >= pct) {
                scheduleSend('scroll');
            }
        }

        global.addEventListener('scroll', checkScroll, { passive: true });
        checkScroll();
    }

    function bindProductsTrigger() {
        if (!cfg.triggers || !cfg.triggers.products) {
            return;
        }
        var selector = cfg.productsSelector || '#products, #products-heading';
        var targets = document.querySelectorAll(selector);
        if (!targets.length) {
            return;
        }

        if (typeof global.IntersectionObserver === 'undefined') {
            scheduleSend('products');
            return;
        }

        var obs = new global.IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting && entry.intersectionRatio >= 0.15) {
                        scheduleSend('products');
                    }
                });
            },
            { threshold: [0.15, 0.35, 0.5] }
        );

        targets.forEach(function (el) {
            obs.observe(el);
        });
    }

    function bindProductCards() {
        var cards = document.querySelectorAll('article.hp-product-card, article.product');
        if (!cards.length || typeof global.IntersectionObserver === 'undefined') {
            return;
        }

        var cardObserver = new global.IntersectionObserver(
            function (entries) {
                var best = null;
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) {
                        return;
                    }
                    var meta = entry.target.querySelector('[data-meta-price="true"]');
                    if (!meta) {
                        return;
                    }
                    if (!best || entry.intersectionRatio > best.ratio) {
                        best = {
                            ratio: entry.intersectionRatio,
                            id: parseInt(meta.getAttribute('data-product-id') || '0', 10) || 0,
                            name: meta.getAttribute('data-product-name') || '',
                            price: String(meta.textContent || '').trim(),
                        };
                    }
                });
                if (best && best.id) {
                    activeProduct = { id: best.id, name: best.name, price: best.price };
                }
            },
            { threshold: [0.25, 0.5, 0.75] }
        );

        cards.forEach(function (card) {
            cardObserver.observe(card);
        });
    }

    function bindOrderPageTrigger() {
        if (cfg.page === 'order') {
            return;
        }
        if (!cfg.triggers || !cfg.triggers.orderPage) {
            return;
        }
        scheduleSend('order_page');
    }

    function bindProductLinks() {
        if (!cfg.triggers || !cfg.triggers.products) {
            return;
        }
        document.querySelectorAll('a[href*="order.php"], a[href*="product_id="]').forEach(function (link) {
            link.addEventListener('click', function () {
                var href = link.getAttribute('href') || '';
                var match = href.match(/product_id=(\d+)/);
                if (match) {
                    activeProduct = Object.assign({}, activeProduct || {}, { id: parseInt(match[1], 10) || 0 });
                }
                scheduleSend('product_click');
            });
        });
    }

    function init() {
        bindFormTriggers();
        bindScrollTrigger();
        bindProductsTrigger();
        bindProductCards();
        bindProductLinks();
        bindOrderPageTrigger();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(typeof window !== 'undefined' ? window : this);
