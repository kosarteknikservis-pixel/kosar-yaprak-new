/**
 * Ürünler bölümüne git — slider, menü CTA, hash (ana sayfa).
 * Yumuşak kaydırma tarayıcı native scroll ile (donma/jank azaltır).
 */
(function() {
    if (window.__PRODUCTS_SCROLL_INIT__) {
        return;
    }
    window.__PRODUCTS_SCROLL_INIT__ = true;

    var INDEX_PRODUCTS_URL = 'index.php#products-heading';
    var TAP_MOVE_PX = 12;
    var SCROLL_QUIET_MS = 90;
    var SCROLL_MAX_WAIT_MS = 1400;

    var touchMoved = false;
    var touchStartX = 0;
    var touchStartY = 0;

    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }

    function getTarget() {
        return document.getElementById('products-heading')
            || document.querySelector('section#products.homepage-products-scope')
            || document.getElementById('products');
    }

    function isOnIndex() {
        return !!(document.body && (
            document.body.classList.contains('homepage-view')
            || document.getElementById('products-heading')
            || document.querySelector('section#products.homepage-products-scope')
        ));
    }

    function prefersReducedMotion() {
        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    function shouldAnimateSmooth() {
        return !prefersReducedMotion();
    }

    function registerTouchGuards() {
        document.addEventListener('touchstart', function(event) {
            touchMoved = false;
            if (event.touches && event.touches[0]) {
                touchStartX = event.touches[0].clientX;
                touchStartY = event.touches[0].clientY;
            }
        }, { passive: true });

        document.addEventListener('touchmove', function(event) {
            if (!event.touches || !event.touches[0]) {
                return;
            }
            var dx = Math.abs(event.touches[0].clientX - touchStartX);
            var dy = Math.abs(event.touches[0].clientY - touchStartY);
            if (dx > TAP_MOVE_PX || dy > TAP_MOVE_PX) {
                touchMoved = true;
            }
        }, { passive: true });
    }

    registerTouchGuards();

    function closeMenuIfOpen() {
        if (typeof window.closeAppMenu === 'function') {
            window.closeAppMenu();
        } else if (document.body) {
            document.body.classList.remove('app-menu-open');
        }
        var menu = document.getElementById('custom-menu');
        if (menu) {
            menu.classList.remove('open');
        }
    }

    function setScrollActive(active) {
        window.__PRODUCTS_SCROLL_ACTIVE__ = !!active;
        if (document.body) {
            document.body.classList.toggle('is-products-scrolling', !!active);
        }
    }

    function waitForScrollEnd(done, maxWaitMs) {
        var finished = false;
        var maxWait = maxWaitMs || SCROLL_MAX_WAIT_MS;

        function finish() {
            if (finished) {
                return;
            }
            finished = true;
            window.removeEventListener('scroll', onScroll);
            setScrollActive(false);
            if (done) {
                done();
            }
        }

        if ('onscrollend' in window) {
            var onEnd = function() {
                window.removeEventListener('scrollend', onEnd);
                finish();
            };
            window.addEventListener('scrollend', onEnd, { once: true });
            window.setTimeout(finish, maxWait);
            return;
        }

        var quietTimer = null;
        function onScroll() {
            window.clearTimeout(quietTimer);
            quietTimer = window.setTimeout(finish, SCROLL_QUIET_MS);
        }

        window.addEventListener('scroll', onScroll, { passive: true });
        window.setTimeout(finish, maxWait);
        onScroll();
    }

    function scrollTargetIntoView(el, smooth, done) {
        if (!el) {
            if (done) {
                done();
            }
            return;
        }

        var behavior = smooth && shouldAnimateSmooth() ? 'smooth' : 'auto';

        if (behavior === 'smooth') {
            setScrollActive(true);
            try {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } catch (err) {
                el.scrollIntoView(true);
                setScrollActive(false);
                if (done) {
                    done();
                }
                return;
            }
            waitForScrollEnd(done);
            return;
        }

        el.scrollIntoView({ behavior: 'auto', block: 'start' });
        if (done) {
            done();
        }
    }

    function updateProductsHash() {
        if (history.replaceState) {
            history.replaceState(null, '', '#products-heading');
        } else {
            location.hash = 'products-heading';
        }
    }

    /**
     * @param {{ smooth?: boolean, updateHash?: boolean }} options
     */
    function goToProductsSection(options) {
        var opts = options || {};
        var smooth = opts.smooth !== false && shouldAnimateSmooth();
        var updateHash = opts.updateHash !== false;

        if (!isOnIndex()) {
            window.location.href = INDEX_PRODUCTS_URL;
            return false;
        }

        var el = getTarget();
        if (!el) {
            window.location.href = INDEX_PRODUCTS_URL;
            return false;
        }

        closeMenuIfOpen();

        window.requestAnimationFrame(function() {
            window.requestAnimationFrame(function() {
                scrollTargetIntoView(el, smooth, function() {
                    if (updateHash) {
                        updateProductsHash();
                    }
                });
            });
        });

        return true;
    }

    /** Eski API uyumluluğu */
    function scrollToProducts(attempt, options) {
        var n = typeof attempt === 'number' ? attempt : 0;
        if (!getTarget() && n < 15) {
            window.setTimeout(function() {
                scrollToProducts(n + 1, options);
            }, 100);
            return false;
        }
        return goToProductsSection({
            smooth: !options || options.smooth !== false,
            updateHash: true
        });
    }

    window.goToProductsSection = goToProductsSection;
    window.scrollToProductsHeading = scrollToProducts;
    window.scrollToProducts = scrollToProducts;

    function isGoProductsTrigger(node) {
        if (!node || !node.closest) {
            return null;
        }
        if (node.closest('.js-hp-product-popup')) {
            return null;
        }
        return node.closest('[data-go-products], .js-scroll-to-products');
    }

    function hrefPointsToProducts(href) {
        if (!href) {
            return false;
        }
        return href === '#products-heading'
            || href === '#products'
            || /index\.php#products-heading/i.test(href)
            || /index\.php#products(?:[?#]|$)/i.test(href)
            || /#products-heading(?:[?#]|$)/i.test(href);
    }

    function shouldIgnoreTap() {
        return touchMoved;
    }

    document.addEventListener('click', function(e) {
        var target = e.target;
        var trigger = isGoProductsTrigger(target);
        var link = target && target.closest ? target.closest('a[href]') : null;

        if (!trigger && link && (link.classList.contains('js-scroll-to-products') || hrefPointsToProducts((link.getAttribute('href') || '').trim()))) {
            trigger = link;
        }

        if (!trigger) {
            return;
        }

        if (shouldIgnoreTap()) {
            return;
        }

        if (link && link.getAttribute('href') && !isOnIndex()) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        goToProductsSection({ updateHash: true });
    }, false);

    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter' && e.key !== ' ') {
            return;
        }
        var trigger = isGoProductsTrigger(e.target);
        if (!trigger || !isOnIndex()) {
            return;
        }
        e.preventDefault();
        goToProductsSection({ updateHash: true });
    });

    function runFromHash() {
        var h = (location.hash || '').replace(/^#/, '');
        if (h !== 'products-heading' && h !== 'products') {
            return;
        }
        if (!isOnIndex()) {
            return;
        }
        goToProductsSection({ smooth: false, updateHash: false });
    }

    document.addEventListener('DOMContentLoaded', function() {
        window.setTimeout(runFromHash, 80);
        window.setTimeout(runFromHash, 400);
    });

    window.addEventListener('load', function() {
        window.setTimeout(runFromHash, 120);
    });

    window.addEventListener('hashchange', function() {
        runFromHash();
    });
})();
