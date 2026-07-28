(function (global) {
    'use strict';

    var payload = global.__abandonedOrderPayload;
    if (!payload) {
        return;
    }

    var debounceTimer = null;
    var lastSentKey = '';
    var lastSeenName = '';
    var lastSeenPhone = '';
    var initialPrefillDone = false;
    var initialProductDone = false;

    function canSendContactOrProduct(ad, tel) {
        if (ad || tel) {
            return true;
        }
        if (!payload.allowProductOnly) {
            return false;
        }
        var product = payload.product || {};
        return !!(product.id || product.name);
    }

    function readVal(id) {
        var el = document.getElementById(id);
        if (!el) {
            return '';
        }
        return String(el.value || '').trim();
    }

    function send(reason) {
        var nameId = (payload.fields && payload.fields.name) || 'customer_name';
        var phoneId = (payload.fields && payload.fields.phone) || 'customer_phone';
        var ad = readVal(nameId);
        var tel = readVal(phoneId);

        if (!canSendContactOrProduct(ad, tel)) {
            return;
        }

        var key = ad + '\n' + tel;
        if (key === lastSentKey && reason !== 'blur' && String(reason).indexOf('php_prefill') !== 0 && reason !== 'order_page') {
            return;
        }
        lastSentKey = key;

        var product = payload.product || {};
        var fd = new FormData();
        fd.append('ad', ad);
        fd.append('tel', tel);
        fd.append('urun', product.name || '');
        fd.append('fiyat', product.price || '');
        if (product.id) {
            fd.append('product_id', String(product.id));
        }
        fd.append('trigger', reason || 'form');

        fetch(payload.endpoint || 'ajax/abandoned_save.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
        }).catch(function () {
            lastSentKey = '';
        });
    }

    function scheduleSend(reason) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            send(reason);
        }, reason === 'blur' ? 500 : 350);
    }

    function injectAutofillStyle() {
        if (document.getElementById('yk-autofill-style')) {
            return;
        }
        var style = document.createElement('style');
        style.id = 'yk-autofill-style';
        style.textContent = '@keyframes ykAutofillStart{} input:-webkit-autofill{animation:ykAutofillStart .01s}';
        document.head.appendChild(style);
    }

    /** Chrome: readonly + focus — yalnızca boş alanlarda, klavye zorlamadan */
    function triggerBrowserAutofill(nameEl, phoneEl) {
        if (!payload.triggerBrowserAutofill) {
            return;
        }

        function unlockAndFocus(el, delay) {
            if (!el || (el.value || '').trim() !== '') {
                return;
            }
            global.setTimeout(function () {
                try {
                    el.setAttribute('readonly', 'readonly');
                    el.focus({ preventScroll: true });
                    global.setTimeout(function () {
                        el.removeAttribute('readonly');
                    }, 80);
                } catch (e) {
                    /* noop */
                }
            }, delay);
        }

        unlockAndFocus(nameEl, 600);
        unlockAndFocus(phoneEl, 1400);
    }

    function watchValueChanges(nameEl, phoneEl) {
        var ticks = 0;
        var maxTicks = 40;
        var interval = global.setInterval(function () {
            ticks += 1;
            var n = (nameEl.value || '').trim();
            var p = (phoneEl.value || '').trim();
            if (n !== lastSeenName || p !== lastSeenPhone) {
                lastSeenName = n;
                lastSeenPhone = p;
                if (n || p) {
                    scheduleSend('value_watch');
                }
            }
            if (ticks >= maxTicks) {
                global.clearInterval(interval);
            }
        }, 250);
    }

    /** PHP value attribute ile gelen prefill — JS tekrar yazmaz, tek kayıt */
    function notifyPrefilledOnce(nameEl, phoneEl) {
        if (initialPrefillDone) {
            return;
        }
        var ad = (nameEl.value || '').trim();
        var tel = (phoneEl.value || '').trim();
        if (!ad && !tel) {
            return;
        }
        initialPrefillDone = true;
        var pf = payload.prefill || {};
        scheduleSend(pf.source ? 'php_prefill_' + pf.source : 'php_prefill');
    }

    function notifyProductVisitOnce(nameEl, phoneEl) {
        if (initialProductDone || !payload.allowProductOnly) {
            return;
        }
        var ad = (nameEl.value || '').trim();
        var tel = (phoneEl.value || '').trim();
        if (ad || tel) {
            return;
        }
        var product = payload.product || {};
        if (!product.id && !product.name) {
            return;
        }
        initialProductDone = true;
        scheduleSend('order_page');
    }

    function bindAutofillCapture() {
        var nameId = (payload.fields && payload.fields.name) || 'customer_name';
        var phoneId = (payload.fields && payload.fields.phone) || 'customer_phone';
        var nameEl = document.getElementById(nameId);
        var phoneEl = document.getElementById(phoneId);
        if (!nameEl || !phoneEl) {
            return;
        }

        injectAutofillStyle();
        lastSeenName = (nameEl.value || '').trim();
        lastSeenPhone = (phoneEl.value || '').trim();
        notifyPrefilledOnce(nameEl, phoneEl);
        notifyProductVisitOnce(nameEl, phoneEl);

        [nameEl, phoneEl].forEach(function (el) {
            el.addEventListener('blur', function () {
                scheduleSend('blur');
            });
            el.addEventListener('input', function () {
                scheduleSend('input');
            });
            el.addEventListener('change', function () {
                scheduleSend('change');
            });
            el.addEventListener('animationstart', function (e) {
                if (e.animationName === 'ykAutofillStart') {
                    scheduleSend('autofill');
                }
            });
        });

        triggerBrowserAutofill(nameEl, phoneEl);
        watchValueChanges(nameEl, phoneEl);

        [900, 1800, 3000, 5000].forEach(function (ms) {
            global.setTimeout(function () {
                scheduleSend('autofill_poll');
            }, ms);
        });

        global.addEventListener('pageshow', function () {
            global.setTimeout(function () {
                scheduleSend('pageshow');
            }, 350);
        });
    }

    function init() {
        bindAutofillCapture();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(typeof window !== 'undefined' ? window : this);
