<?php
require 'db.php';
require_once __DIR__ . '/includes/i18n.php';
i18n_boot($pdo);
require_once __DIR__ . '/includes/order_lookup.php';
require_once __DIR__ . '/includes/info_pages.php';

date_default_timezone_set('Europe/Istanbul');
info_page_track_view($pdo, 'sorgula.php');

$message = '';
$order = null;
$form_submitted = false;
$showModal = false;
$modalType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_phone = $_POST['customer_phone'] ?? '';
    $form_submitted = true;

    if ($customer_phone === '') {
        $message = t('query.enter_phone_err', 'Lütfen telefon numaranızı giriniz.');
        $showModal = true;
        $modalType = 'error';
    } else {
        $result = order_lookup_by_phone($pdo, (string) $customer_phone);
        if ($result['ok'] ?? false) {
            $order = is_array($result['order'] ?? null) ? $result['order'] : null;
            $showModal = $order !== null;
            $modalType = 'success';
        } else {
            $message = (string) ($result['message'] ?? t('query.not_found_msg', 'Sipariş bulunamadı.'));
            $showModal = true;
            $modalType = 'error';
        }
    }
}

$statusClass = '';
$statusName = '';
$orderNotes = '';
$orderVariants = '';
$customerNotes = '';
if ($order) {
    $statusClass = order_lookup_status_css_class((string) ($order['status_id'] ?? ''));
    $statusName = (string) ($order['status_name'] ?? '');
    $orderNotes = (string) ($order['order_notes'] ?? '');
    $orderVariants = (string) ($order['variants'] ?? '');
    $customerNotes = (string) ($order['customer_notes'] ?? '');
}

ob_start();
?>
<div class="sss-form-panel sss-form-panel--lookup">
    <form method="POST" class="info-form" id="sorgulaForm">
        <div class="form-group">
            <label for="customer_phone"><?= te('query.phone', 'Telefon numaranız') ?></label>
            <p class="sss-form-hint"><?= te('query.phone_hint', 'Sipariş sırasında girdiğiniz numarayı yazın.') ?></p>
            <div class="sss-input-icon-wrap">
                <span class="sss-input-icon" aria-hidden="true"><i class="fas fa-phone"></i></span>
                <input type="tel" class="form-control" id="customer_phone" name="customer_phone"
                       placeholder="Örn: 5xx xxx xx xx" required pattern="[0-9\s]*" inputmode="numeric"
                       oninput="this.value=this.value.replace(/[^0-9\s]/g,'');" maxlength="15"
                       value="<?= htmlspecialchars((string) ($_POST['customer_phone'] ?? '')) ?>">
            </div>
        </div>
        <button type="submit" class="info-form__submit"><i class="fas fa-search"></i> <?= te('query.submit', 'Sorgula') ?></button>
    </form>
</div>
<?php
$slotHtml = (string) ob_get_clean();

$afterShellHtml = '';
if ($showModal) {
    ob_start();
    ?>
<div class="sorgula-modal-overlay is-open" id="sorgulaModal" role="presentation">
    <div class="sorgula-modal" role="dialog" aria-modal="true" aria-labelledby="sorgulaModalTitle">
        <button type="button" class="sorgula-modal__close" id="sorgulaModalClose" aria-label="<?= te('query.close', 'Kapat') ?>">
            <i class="fas fa-times"></i>
        </button>

        <?php if ($modalType === 'error'): ?>
            <div class="sorgula-modal__icon sorgula-modal__icon--error">
                <i class="fas fa-circle-exclamation"></i>
            </div>
            <h2 class="sorgula-modal__title" id="sorgulaModalTitle"><?= te('query.not_found', 'Sonuç Bulunamadı') ?></h2>
            <p class="sorgula-modal__message"><?= htmlspecialchars($message) ?></p>
            <button type="button" class="sorgula-modal__btn sorgula-modal__btn--primary" data-close-modal><?= te('query.retry', 'Tekrar Dene') ?></button>
        <?php else: ?>
            <div class="sorgula-modal__status <?= htmlspecialchars($statusClass) ?>">
                <i class="fas fa-box-open"></i>
                <?= htmlspecialchars($statusName) ?>
            </div>
            <h2 class="sorgula-modal__title" id="sorgulaModalTitle"><?= te('query.details', 'Sipariş Detayları') ?></h2>

            <dl class="sorgula-detail-list">
                <?php if (! empty($order['reference'])): ?>
                    <div class="sorgula-detail-row">
                        <dt><?= te('query.order_no', 'Sipariş No') ?></dt>
                        <dd><?= htmlspecialchars((string) $order['reference']) ?></dd>
                    </div>
                <?php endif; ?>
                <div class="sorgula-detail-row">
                    <dt><?= te('common.name', 'Ad Soyad') ?></dt>
                    <dd><?= htmlspecialchars((string) ($order['customer_name'] ?? '')) ?></dd>
                </div>
                <div class="sorgula-detail-row">
                    <dt><?= te('common.phone', 'Telefon') ?></dt>
                    <dd><?= htmlspecialchars((string) ($order['customer_phone'] ?? '')) ?></dd>
                </div>
                <div class="sorgula-detail-row">
                    <dt><?= te('common.address', 'Adres') ?></dt>
                    <dd>
                        <?= htmlspecialchars((string) ($order['customer_address'] ?? '')) ?>
                        <?php if (! empty($order['customer_district']) || ! empty($order['customer_city'])): ?>
                            <br><span class="text-muted"><?= htmlspecialchars((string) ($order['customer_district'] ?? '')) ?><?php if (! empty($order['customer_city'])): ?>, <?= htmlspecialchars((string) ($order['customer_city'] ?? '')) ?><?php endif; ?></span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div class="sorgula-detail-row">
                    <dt><?= te('query.product', 'Ürün') ?></dt>
                    <dd><?= htmlspecialchars((string) ($order['product_name'] ?? '')) ?></dd>
                </div>
                <div class="sorgula-detail-row sorgula-detail-row--highlight">
                    <dt><?= te('query.amount', 'Tutar') ?></dt>
                    <dd><?= function_exists('money') ? htmlspecialchars(money((float) ($order['price'] ?? 0))) : number_format((float) ($order['price'] ?? 0), 2, ',', '.') . ' TL' ?></dd>
                </div>
                <?php if ($orderVariants !== ''): ?>
                    <div class="sorgula-detail-row">
                        <dt><?= te('thankyou.variants', 'Varyantlar') ?></dt>
                        <dd><?= htmlspecialchars($orderVariants) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($orderNotes !== ''): ?>
                    <div class="sorgula-detail-row">
                        <dt><?= te('query.order_note', 'Sipariş Notu') ?></dt>
                        <dd><?= htmlspecialchars($orderNotes) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($customerNotes !== ''): ?>
                    <div class="sorgula-detail-row">
                        <dt><?= te('query.customer_note', 'Müşteri Notu') ?></dt>
                        <dd><?= htmlspecialchars($customerNotes) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if (! empty($order['cargo_company'])): ?>
                    <div class="sorgula-detail-row">
                        <dt><?= te('query.shipping', 'Kargo') ?></dt>
                        <dd><?= htmlspecialchars((string) $order['cargo_company']) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
            <button type="button" class="sorgula-modal__btn sorgula-modal__btn--primary" data-close-modal><?= te('query.ok', 'Tamam') ?></button>
        <?php endif; ?>
    </div>
</div>
    <?php
    $afterShellHtml = (string) ob_get_clean();
}

$extraScripts = <<<'JS'
<script>
(function () {
    var modal = document.getElementById('sorgulaModal');
    if (!modal) return;

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('sorgula-modal-open');
        if (window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.delete('auto');
            window.history.replaceState({}, '', url.pathname);
        }
    }

    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('sorgula-modal-open');

    modal.addEventListener('click', function (e) {
        if (!e.target.closest('.sorgula-modal')) {
            closeModal();
        }
    });

    var dialog = modal.querySelector('.sorgula-modal');
    if (dialog) {
        dialog.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    }

    var closeBtn = document.getElementById('sorgulaModalClose');
    if (closeBtn) {
        closeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            closeModal();
        });
    }

    modal.querySelectorAll('[data-close-modal]').forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });
})();
JS;
$extraScripts .= '<script>if(typeof window.paTrackSafe==="function"){window.paTrackSafe("page_order_lookup");';
if ($form_submitted) {
    $extraScripts .= 'window.paTrackSafe("order_lookup_submit");';
}
$extraScripts .= '}</script>';

info_page_render($pdo, [
    'page_file' => 'sorgula.php',
    'title' => t('query.title', 'Sipariş Sorgula'),
    'body_html' => '',
    'show_faq' => false,
    'slot_html' => $slotHtml,
    'after_shell_html' => $afterShellHtml,
    'extra_head' => '<link rel="stylesheet" href="css/sorgula.css">',
    'extra_scripts' => $extraScripts,
    'page_class' => 'sss-page-view--lookup',
    'hero_badge' => t('query.hero_badge', 'Sipariş takibi'),
    'hero_badge_icon' => 'fa-search',
    'hero_lead' => t('query.hero_lead', 'Telefon numaranızla sipariş durumunuzu anında görüntüleyin.'),
    'trust_pills' => [
        ['icon' => 'fa-bolt', 'label' => t('query.trust_instant', 'Anlık sonuç')],
        ['icon' => 'fa-truck', 'label' => t('shop.fast_shipping', 'Hızlı kargo')],
        ['icon' => 'fa-headset', 'label' => t('query.trust_support', 'Destek hattı')],
    ],
    'cta_title' => t('query.cta_title', 'Yeni sipariş mi vereceksiniz?'),
    'cta_text' => t('query.cta_text', 'Ürünlerimize göz atın veya destek talebi oluşturun.'),
]);
