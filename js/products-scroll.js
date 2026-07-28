/**
 * Ürünler bölümüne git — menü CTA, slider, hash (ana sayfa).
 * Tek yumuşak JS animasyonu (#products-heading).
 */
(function() {
    if (window.__PRODUCTS_SCROLL_INIT__) {
        return;
    }
    window.__PRODUCTS_SCROLL_INIT__ = true;

    var INDEX_PRODUCTS_URL = 'index.php#products-heading';
    var SCROLL_MIN_MS = 720;
    var SCROLL_MAX_MS = 1100;
    var SCROLL_MS_PER_PX = 0.58;

    var scrollAnimId = null;

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

    function readScrollTop() {
        return window.pageYOffset
            || document.documentElement.scrollTop
            || document.body.scrollTop
            || 0;
    }

    function setScrollTop(y) {
        var top = Math.max(0, Math.round(y));
        window.scrollTo(0, top);
        document.documentElement.scrollTop = top;
        document.body.scrollTop = top;
    }

    function computeTargetY(el) {
        var styles = window.getComputedStyle(el);
        var marginTop = parseFloat(styles.scrollMarginTop) || 0;
        if (!marginTop) {
            marginTop = document.querySelector('.countdown-banner') ? 162 : 94;
        }
        return el.getBoundingClientRect().top + readScrollTop() - marginTop;
    }

    /** Yavaş başlayıp hızlanma hissi vermeyen, simetrik yumuşak eğri */
    function easeInOutQuad(t) {
        return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
    }

    function durationForDistance(px) {
        return Math.min(SCROLL_MAX_MS, Math.max(SCROLL_MIN_MS, Math.round(Math.abs(px) * SCROLL_MS_PER_PX)));
    }

    function cancelScrollAnimation() {
        if (scrollAnimId) {
            cancelAnimationFrame(scrollAnimId);
            scrollAnimId = null;
        }
    }

    function animateScrollTo(targetY, done) {
        cancelScrollAnimation();

        var startY = readScrollTop();
        var delta = targetY - startY;

        if (Math.abs(delta) < 2) {
            setScrollTop(targetY);
            if (done) {
                done();
            }
            return;
        }

        var duration = durationForDistance(delta);
        var startTime = null;

        function step(now) {
            if (startTime === null) {
                startTime = now;
            }
            var elapsed = now - startTime;
            var t = Math.min(1, elapsed / duration);
            var y = Math.round(startY + delta * easeInOutQuad(t));
            setScrollTop(y);

            if (t < 1) {
                scrollAnimId = requestAnimationFrame(step);
            } else {
                scrollAnimId = null;
                setScrollTop(targetY);
                if (done) {
                    done();
                }
            }
        }

        scrollAnimId = requestAnimationFrame(step);
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
        var smooth = opts.smooth !== false && !prefersReducedMotion();
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

        function runScroll() {
            var targetY = computeTargetY(el);

            if (!smooth) {
                cancelScrollAnimation();
                setScrollTop(targetY);
                if (updateHash) {
                    updateProductsHash();
                }
                return;
            }

            animateScrollTo(targetY, function() {
                if (updateHash) {
                    updateProductsHash();
                }
            });
        }

        window.requestAnimationFrame(function() {
            window.setTimeout(runScroll, 72);
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
        var videoTrigger = node.closest('.js-hp-product-popup');
        if (videoTrigger) {
            return null;
        }
        return node.closest('[data-go-products], .js-scroll-to-products, .slider-image');
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

        if (link && link.getAttribute('href') && !isOnIndex()) {
            return;
        }

        e.preventDefault();
        e.stopImmediatePropagation();

        goToProductsSection({ smooth: true, updateHash: true });
    }, true);

    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter' && e.key !== ' ') {
            return;
        }
        var trigger = e.target && e.target.closest ? e.target.closest('[data-go-products], .slider-image[tabindex]') : null;
        if (!trigger || !isOnIndex()) {
            return;
        }
        e.preventDefault();
        goToProductsSection({ smooth: true, updateHash: true });
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
