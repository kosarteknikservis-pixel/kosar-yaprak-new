/**
 * Ürünler bölümüne git — yalnızca menü CTA ve açık bağlantılar (ana sayfa).
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
    var TAP_MOVE_PX = 12;

    var scrollAnimId = null;
    var touchMoved = false;
    var touchStartX = 0;
    var touchStartY = 0;
    var lastTouchEndedAt = 0;

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

    function isMobileLike() {
        if (window.matchMedia) {
            if (window.matchMedia('(max-width: 768px)').matches) {
                return true;
            }
            if (window.matchMedia('(pointer: coarse)').matches) {
                return true;
            }
            if (window.matchMedia('(hover: none)').matches) {
                return true;
            }
        }
        if (typeof navigator !== 'undefined' && Number(navigator.maxTouchPoints) > 0 && window.innerWidth <= 1024) {
            return true;
        }
        return false;
    }

    function shouldAnimateSmooth() {
        if (prefersReducedMotion() || isMobileLike()) {
            return false;
        }
        return true;
    }

    function registerTouchGuards() {
        document.addEventListener('touchstart', function(event) {
            touchMoved = false;
            cancelScrollAnimation();
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

        document.addEventListener('touchend', function() {
            lastTouchEndedAt = Date.now();
        }, { passive: true });

        document.addEventListener('wheel', cancelScrollAnimation, { passive: true });
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

        if (!shouldAnimateSmooth()) {
            setScrollTop(targetY);
            if (done) {
                done();
            }
            return;
        }

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

        if (isMobileLike()) {
            runScroll();
        } else {
            window.requestAnimationFrame(function() {
                window.setTimeout(runScroll, 72);
            });
        }

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

    function isExplicitProductsTrigger(node) {
        if (!node || !node.closest) {
            return null;
        }
        if (node.closest('.js-hp-product-popup, .slider-image')) {
            return null;
        }
        return node.closest('.custom-menu-cta[data-go-products], .custom-menu-link[data-go-products], .js-scroll-to-products');
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

    function shouldIgnoreTap(event) {
        if (touchMoved) {
            return true;
        }
        if (event.pointerType === 'touch' && touchMoved) {
            return true;
        }
        if (isMobileLike() && lastTouchEndedAt && (Date.now() - lastTouchEndedAt) > 500) {
            return false;
        }
        return false;
    }

    document.addEventListener('click', function(e) {
        var target = e.target;
        var trigger = isExplicitProductsTrigger(target);
        var link = target && target.closest ? target.closest('a[href]') : null;

        if (!trigger && link && (link.classList.contains('js-scroll-to-products') || hrefPointsToProducts((link.getAttribute('href') || '').trim()))) {
            trigger = link;
        }

        if (!trigger) {
            return;
        }

        if (shouldIgnoreTap(e)) {
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
        var trigger = isExplicitProductsTrigger(e.target);
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
