    </div><!-- .page-content -->
</div><!-- .main-wrapper -->
<?php include __DIR__ . '/partials/admin_shortcuts.php'; ?>
<?php include __DIR__ . '/partials/admin_mobile_nav.php'; ?>
<script src="<?= htmlspecialchars(admin_asset('js/admin-shortcuts.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script>
function toggleSidebar() {
    var s = document.getElementById('sidebar');
    var o = document.getElementById('sidebarOverlay');
    if (s) s.classList.toggle('open');
    if (o) o.classList.toggle('open');
}
function closeSidebar() {
    var s = document.getElementById('sidebar');
    var o = document.getElementById('sidebarOverlay');
    if (s) s.classList.remove('open');
    if (o) o.classList.remove('open');
}
(function () {
    var KEY = 'adminSidebarScrollY';
    var sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    try {
        var saved = sessionStorage.getItem(KEY);
        if (saved !== null && saved !== '') {
            sidebar.scrollTop = parseInt(saved, 10) || 0;
        }
    } catch (e) {}
    var t;
    sidebar.addEventListener('scroll', function () {
        clearTimeout(t);
        t = setTimeout(function () {
            try {
                sessionStorage.setItem(KEY, String(sidebar.scrollTop));
            } catch (e) {}
        }, 100);
    }, { passive: true });
})();
(function () {
    document.querySelectorAll('[data-action="toggle-sidebar"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (typeof toggleSidebar === 'function') toggleSidebar();
        });
    });
})();
</script>
</body>
</html>
