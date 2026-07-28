(function () {
    'use strict';

    var root = window.ADMIN_WEB_ROOT || '';
    function api(path) {
        return (root ? root + '/' : '') + path;
    }

    var backdrop = null;
    var drawer = null;

    function ensureShell() {
        if (drawer) return;
        backdrop = document.createElement('div');
        backdrop.className = 'record-drawer-backdrop';
        backdrop.addEventListener('click', closeDrawer);

        drawer = document.createElement('aside');
        drawer.className = 'record-drawer';
        drawer.setAttribute('role', 'dialog');
        drawer.setAttribute('aria-modal', 'true');
        drawer.innerHTML =
            '<div class="record-drawer__head">' +
            '  <div><h2 class="record-drawer__title" id="recordDrawerTitle"></h2><div class="record-drawer__meta" id="recordDrawerMeta"></div></div>' +
            '  <button type="button" class="record-drawer__close" aria-label="Kapat"><i class="fas fa-times"></i></button>' +
            '</div>' +
            '<div class="record-drawer__body" id="recordDrawerBody"></div>' +
            '<div class="record-drawer__foot" id="recordDrawerFoot"></div>';

        document.body.appendChild(backdrop);
        document.body.appendChild(drawer);
        drawer.querySelector('.record-drawer__close').addEventListener('click', closeDrawer);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeDrawer();
        });
    }

    function openDrawer() {
        ensureShell();
        backdrop.classList.add('is-open');
        drawer.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function closeDrawer() {
        if (!drawer) return;
        backdrop.classList.remove('is-open');
        drawer.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function fmtDate(s) {
        if (!s) return '—';
        try {
            return new Date(s.replace(' ', 'T')).toLocaleString('tr-TR');
        } catch (e) {
            return s;
        }
    }

    function renderSupport(rec) {
        var open = rec.is_open;
        document.getElementById('recordDrawerTitle').textContent = rec.subject || 'Destek talebi';
        document.getElementById('recordDrawerMeta').innerHTML =
            '<span class="record-status-pill ' + (open ? 'record-status-pill--open' : 'record-status-pill--done') + '">' +
            esc(rec.status || 'Beklemede') + '</span> · ' + esc(fmtDate(rec.created_at));

        document.getElementById('recordDrawerBody').innerHTML =
            '<div class="record-field"><div class="record-field__label">Gönderen</div><div class="record-field__value"><strong>' + esc(rec.name) + '</strong></div></div>' +
            '<div class="record-field"><div class="record-field__label">E-posta</div><div class="record-field__value"><a href="mailto:' + esc(rec.email) + '">' + esc(rec.email) + '</a></div></div>' +
            '<div class="record-field"><div class="record-field__label">Telefon</div><div class="record-field__value">' + (rec.phone_html || esc(rec.phone)) + '</div></div>' +
            '<div class="record-field"><div class="record-field__label">Konu</div><div class="record-field__value">' + esc(rec.subject) + '</div></div>' +
            '<div class="record-field"><div class="record-field__label">Mesaj</div><div class="record-field__value record-field__value--box">' + esc(rec.note) + '</div></div>' +
            (rec.response ? '<div class="record-field"><div class="record-field__label">Önceki cevap</div><div class="record-field__value record-field__value--box">' + esc(rec.response) + '</div></div>' : '');

        document.getElementById('recordDrawerFoot').innerHTML =
            '<form id="recordDrawerForm" class="d-flex flex-column gap-2">' +
            '<textarea class="form-control" name="response" rows="4" placeholder="Cevabınızı yazın…" required></textarea>' +
            '<div class="d-flex gap-2 flex-wrap">' +
            '<button type="submit" class="btn btn-success"><i class="fas fa-paper-plane me-1"></i> Cevapla & kaydet</button>' +
            '<button type="button" class="btn btn-outline-secondary" id="recordDrawerCloseBtn">Kapat</button>' +
            (open ? '<button type="button" class="btn btn-outline-danger ms-auto" id="recordDrawerCloseTicket"><i class="fas fa-check me-1"></i> Kapat (cevapsız)</button>' : '') +
            '</div></form>';

        document.getElementById('recordDrawerCloseBtn').addEventListener('click', closeDrawer);
        document.getElementById('recordDrawerForm').addEventListener('submit', function (e) {
            e.preventDefault();
            postAction('support', 'respond', rec.id, { response: e.target.response.value });
        });
        var closeTicket = document.getElementById('recordDrawerCloseTicket');
        if (closeTicket) {
            closeTicket.addEventListener('click', function () {
                postAction('support', 'close', rec.id, {});
            });
        }
    }

    function renderDealer(rec) {
        var done = rec.is_approved;
        document.getElementById('recordDrawerTitle').textContent = rec.company_name || rec.name;
        document.getElementById('recordDrawerMeta').innerHTML =
            '<span class="record-status-pill ' + (done ? 'record-status-pill--done' : 'record-status-pill--open') + '">' +
            (done ? 'Arandı' : 'Beklemede') + '</span> · ' + esc(fmtDate(rec.created_at));

        document.getElementById('recordDrawerBody').innerHTML =
            '<div class="record-field"><div class="record-field__label">Yetkili</div><div class="record-field__value"><strong>' + esc(rec.name) + '</strong></div></div>' +
            '<div class="record-field"><div class="record-field__label">E-posta</div><div class="record-field__value"><a href="mailto:' + esc(rec.email) + '">' + esc(rec.email) + '</a></div></div>' +
            '<div class="record-field"><div class="record-field__label">Telefon</div><div class="record-field__value">' + (rec.phone_html || esc(rec.phone)) + '</div></div>' +
            '<div class="record-field"><div class="record-field__label">Şirket</div><div class="record-field__value">' + esc(rec.company_name) + '</div></div>' +
            '<div class="record-field"><div class="record-field__label">Tür</div><div class="record-field__value">' + esc(rec.role) + '</div></div>' +
            '<div class="record-field"><div class="record-field__label">Adres</div><div class="record-field__value record-field__value--box">' + esc(rec.address) + '</div></div>' +
            (rec.note ? '<div class="record-field"><div class="record-field__label">Not</div><div class="record-field__value record-field__value--box">' + esc(rec.note) + '</div></div>' : '');

        document.getElementById('recordDrawerFoot').innerHTML =
            '<div class="d-flex gap-2 flex-wrap">' +
            (!done ? '<button type="button" class="btn btn-success" id="recordDrawerApprove"><i class="fas fa-phone me-1"></i> Arandı olarak işaretle</button>' :
                '<button type="button" class="btn btn-outline-warning" id="recordDrawerReopen"><i class="fas fa-undo me-1"></i> Beklemede yap</button>') +
            '<a href="orders.php?customer_phone=' + encodeURIComponent(rec.phone || '') + '" class="btn btn-outline-primary"><i class="fas fa-shopping-cart me-1"></i> Sipariş ara</a>' +
            '<button type="button" class="btn btn-outline-secondary ms-auto" id="recordDrawerCloseBtn">Kapat</button>' +
            '</div>';

        document.getElementById('recordDrawerCloseBtn').addEventListener('click', closeDrawer);
        var approve = document.getElementById('recordDrawerApprove');
        if (approve) approve.addEventListener('click', function () { postAction('dealer', 'approve', rec.id, {}); });
        var reopen = document.getElementById('recordDrawerReopen');
        if (reopen) reopen.addEventListener('click', function () { postAction('dealer', 'reopen', rec.id, {}); });
    }

    function postAction(type, action, id, extra) {
        var fd = new FormData();
        fd.append('type', type);
        fd.append('action', action);
        fd.append('id', String(id));
        Object.keys(extra || {}).forEach(function (k) { fd.append(k, extra[k]); });

        fetch(api('ajax/record_action.php'), { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) throw new Error(data.error || 'İşlem başarısız');
                if (window.Swal) {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message || 'Kaydedildi', timer: 2200, showConfirmButton: false });
                }
                closeDrawer();
                setTimeout(function () { location.reload(); }, 400);
            })
            .catch(function (err) {
                if (window.Swal) Swal.fire({ icon: 'error', title: 'Hata', text: err.message || 'İşlem başarısız' });
                else alert(err.message || 'İşlem başarısız');
            });
    }

    function openRecord(type, id) {
        openDrawer();
        document.getElementById('recordDrawerBody').innerHTML = '<div class="text-center text-muted py-5"><i class="fas fa-spinner fa-spin"></i> Yükleniyor…</div>';
        document.getElementById('recordDrawerFoot').innerHTML = '';

        fetch(api('ajax/record_detail.php?type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(id)), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) throw new Error(data.error || 'Kayıt yüklenemedi');
                if (data.type === 'support') renderSupport(data.record);
                else if (data.type === 'dealer') renderDealer(data.record);
            })
            .catch(function (err) {
                document.getElementById('recordDrawerBody').innerHTML = '<div class="alert alert-danger">' + esc(err.message) + '</div>';
            });
    }

    document.addEventListener('click', function (e) {
        var row = e.target.closest('[data-record-type][data-record-id]');
        if (!row) return;
        if (e.target.closest('a, button, input, label, form')) return;
        openRecord(row.getAttribute('data-record-type'), row.getAttribute('data-record-id'));
    });

    window.openAdminRecord = openRecord;
})();
