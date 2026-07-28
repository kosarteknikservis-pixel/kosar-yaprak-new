<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/admin_quick_menu.php';

$admin_mobile_nav_items = admin_mobile_nav_items($admin_vitrin_href ?? null);
if ($admin_mobile_nav_items === []) {
    return;
}
?>
<nav class="admin-bottom-nav" aria-label="Mobil kısayol menüsü">
    <?php foreach ($admin_mobile_nav_items as $navItem): ?>
        <?php if (($navItem['action'] ?? '') === 'toggle-sidebar'): ?>
            <button type="button" class="admin-bottom-nav__item" data-action="toggle-sidebar" aria-label="Kenar menüsünü aç">
                <i class="fas <?= htmlspecialchars((string) $navItem['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                <span><?= htmlspecialchars((string) $navItem['label'], ENT_QUOTES, 'UTF-8') ?></span>
            </button>
        <?php else: ?>
            <a href="<?= admin_href((string) $navItem['href']) ?>"
               class="admin-bottom-nav__item<?= !empty($navItem['active']) ? ' is-active' : '' ?>">
                <i class="fas <?= htmlspecialchars((string) $navItem['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                <span><?= htmlspecialchars((string) $navItem['label'], ENT_QUOTES, 'UTF-8') ?></span>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
