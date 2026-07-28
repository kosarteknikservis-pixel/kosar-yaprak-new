# phxcore0 Admin — Canli Tur & Dogrulama Listesi

> Otomatik uretildi: 2026-07-09 18:15  
> Bu dosya `generate_docs.py` ile uretilir, `build_tour.py` ile calistirilir.

## 1. Giris & Yol

_Panel yeni /phx yolundan acilir; eski /admin kapali olmali._

- [ ] Giris sayfasi /phx/login.php acilyor  <!-- check: GET /phx/login.php 200 contains:login -->
- [ ] Giris CSS'i yukleniyor  <!-- check: GET /phx/css/login.css 200 -->
- [ ] Eski /admin/login.php kapali (404)  <!-- check: GET /admin/login.php 404 -->

## 2. Tema (Aydinlik / Karanlik)

_Varsayilan aydinlik; menu aydinlik; karanlik tema toggle ile._

- [ ] Tema kontrolcusu JS yukleniyor  <!-- check: GET /phx/js/admin-theme.js 200 contains:data-theme -->
- [ ] Karanlik tema CSS tanimli  <!-- check: GET /phx/css/modern_admin.css 200 contains:[data-theme="dark"] -->
- [ ] Aydinlik menu (sidebar-bg beyaz)  <!-- check: GET /phx/css/modern_admin.css 200 contains:--sidebar-bg: #ffffff -->
- [ ] Tema gecis butonu stili var  <!-- check: GET /phx/css/modern_admin.css 200 contains:.topbar-theme-btn -->

## 3. Klavye Kisayollari

_Kisayol rozetleri (kbd) artik renkli ve gorunur._

- [ ] kbd metin rengi tanimli (gorunur)  <!-- check: GET /phx/css/admin-panel-ui.css 200 contains:#3730a3 -->
- [ ] Kisayol modal paneli tema uyumlu  <!-- check: GET /phx/css/admin-panel-ui.css 200 contains:var(--surface -->

## 4. Menu Tasarimi

_Sol menu canli indigo vurgu, aktif pill, ikonlar renkli._

- [ ] Aktif nav vurgu rengi tanimli  <!-- check: GET /phx/css/modern_admin.css 200 contains:--sidebar-active-text -->
- [ ] Nav ikon renk degiskeni tanimli  <!-- check: GET /phx/css/modern_admin.css 200 contains:--sidebar-icon -->

## 5. Vitrin Sagligi

_Musteriye acik sayfalar sorunsuz calisiyor._

- [ ] Ana sayfa aciliyor  <!-- check: GET / 200 -->
- [ ] Siparis sayfasi aciliyor  <!-- check: GET /order.php 200 -->

## 6. Landing Page (Acilis) Sistemi

_Blok tabanli acilis sayfalari: vitrin render, bloklar, temiz URL, editor, gorsel yukleme._

- [ ] Landing CSS yukleniyor  <!-- check: GET /css/landing.css 200 contains:.lp-hero -->
- [ ] Yayindaki demo sayfa aciliyor  <!-- check: GET /landing.php?slug=demo-kampanya 200 noerror contains:lp-hero -->
- [ ] Hero blogu render ediliyor  <!-- check: GET /landing.php?slug=demo-kampanya 200 contains:lp-hero__title -->
- [ ] Geri sayim blogu render ediliyor  <!-- check: GET /landing.php?slug=demo-kampanya 200 contains:lp-cd__timer -->
- [ ] Urun blogu (ad/fiyat/gorsel) render ediliyor  <!-- check: GET /landing.php?slug=demo-kampanya 200 contains:lp-product -->
- [ ] Yorumlar blogu render ediliyor  <!-- check: GET /landing.php?slug=demo-kampanya 200 contains:lp-review -->
- [ ] SSS blogu render ediliyor  <!-- check: GET /landing.php?slug=demo-kampanya 200 contains:lp-faq__item -->
- [ ] Yok/taslak slug 404 veriyor  <!-- check: GET /landing.php?slug=___olmayan-sayfa___ 404 -->
- [ ] Temiz URL /l/<slug> calisiyor  <!-- check: GET /l/demo-kampanya 200 contains:lp-hero -->
- [ ] Admin liste sayfasi aciliyor  <!-- check: GET /phx/landing_pages.php 200 auth noerror contains:Landing sayfalar -->
- [ ] Admin editor aciliyor  <!-- check: GET /phx/landing_edit.php?id=1 200 auth noerror contains:Bloklar -->
- [ ] Editor blok karti render ediliyor  <!-- check: GET /phx/landing_edit.php?id=1 200 auth noerror contains:lp-block -->
- [ ] Editor tema secici var  <!-- check: GET /phx/landing_edit.php?id=1 200 auth noerror contains:Aurora -->
- [ ] Gorsel yukleme ucu CSRF'siz reddediyor (403)  <!-- check: GET /phx/landing_upload.php 403 auth -->

## 7. Cok Dilli & Para Birimi (i18n)

_On yuz TR/EN/AR dil degisimi, RTL yon, para birimi donusumu ve admin yonetimi._

- [ ] RTL stil dosyasi yukleniyor  <!-- check: GET /css/rtl.css 200 contains:dir="rtl" -->
- [ ] Dil/para secici on yuzde render ediliyor  <!-- check: GET / 200 noerror contains:site-switcher -->
- [ ] Ingilizce ceviri uygulaniyor (Track Order)  <!-- check: GET /?lang=en 200 noerror contains:Track Order -->
- [ ] Arapca ceviri uygulaniyor (menu.order_query)  <!-- check: GET /?lang=ar 200 noerror contains:تتبع الطلب -->
- [ ] Arapcada RTL etkin (rtl.css yukleniyor)  <!-- check: GET /?lang=ar 200 noerror contains:css/rtl.css -->
- [ ] Para birimi degistirme baglantisi var (USD)  <!-- check: GET /?cur=USD 200 noerror contains:cur=USD -->
- [ ] Admin diller sayfasi aciliyor  <!-- check: GET /phx/languages.php 200 auth noerror contains:Diller -->
- [ ] Para birimleri sekmesi + kur guncelle  <!-- check: GET /phx/languages.php?tab=currencies 200 auth noerror contains:Kurları güncelle -->
- [ ] Ceviri editoru sekmesi  <!-- check: GET /phx/languages.php?tab=translations 200 auth noerror contains:Arayüz çevirileri -->
- [ ] Ice/disa aktar sekmesi  <!-- check: GET /phx/languages.php?tab=io 200 auth noerror contains:Dışa aktar -->
- [ ] JSON disa aktarma calisiyor  <!-- check: GET /phx/languages.php?export=json 200 auth contains:menu.home -->

## 8. Tum Menu Sayfalari (kimlik dogrulamali)

_Her menu sayfasi giris yapilarak gezilir; HTTP 200 ve PHP hatasi olmamasi beklenir._

- [ ] Menu: index.php  <!-- check: GET /phx/index.php 200 auth noerror -->
- [ ] Menu: order_manual.php  <!-- check: GET /phx/order_manual.php 200 auth noerror -->
- [ ] Menu: abandoned_settings.php  <!-- check: GET /phx/abandoned_settings.php 200 auth noerror -->
- [ ] Menu: manage_order_status.php  <!-- check: GET /phx/manage_order_status.php 200 auth noerror -->
- [ ] Menu: order_lookup_settings.php  <!-- check: GET /phx/order_lookup_settings.php 200 auth noerror -->
- [ ] Menu: export.php  <!-- check: GET /phx/export.php 200 auth noerror -->
- [ ] Menu: cargo_csv_export.php  <!-- check: GET /phx/cargo_csv_export.php 200 auth noerror -->
- [ ] Menu: quick_notes.php  <!-- check: GET /phx/quick_notes.php 200 auth noerror -->
- [ ] Menu: manage_payment_methods.php  <!-- check: GET /phx/manage_payment_methods.php 200 auth noerror -->
- [ ] Menu: manage_locations.php  <!-- check: GET /phx/manage_locations.php 200 auth noerror -->
- [ ] Menu: bank_accounts.php  <!-- check: GET /phx/bank_accounts.php 200 auth noerror -->
- [ ] Menu: products.php  <!-- check: GET /phx/products.php 200 auth noerror -->
- [ ] Menu: variant_management.php  <!-- check: GET /phx/variant_management.php 200 auth noerror -->
- [ ] Menu: call_confirmation.php  <!-- check: GET /phx/call_confirmation.php 200 auth noerror -->
- [ ] Menu: cirolar.php  <!-- check: GET /phx/cirolar.php 200 auth noerror -->
- [ ] Menu: final.php  <!-- check: GET /phx/final.php 200 auth noerror -->
- [ ] Menu: admin_log.php  <!-- check: GET /phx/admin_log.php 200 auth noerror -->
- [ ] Menu: review_intro.php  <!-- check: GET /phx/review_intro.php 200 auth noerror -->
- [ ] Menu: reviews.php  <!-- check: GET /phx/reviews.php 200 auth noerror -->
- [ ] Menu: order_image_maker.php  <!-- check: GET /phx/order_image_maker.php 200 auth noerror -->
- [ ] Menu: user_management.php  <!-- check: GET /phx/user_management.php 200 auth noerror -->
- [ ] Menu: admin_slider.php  <!-- check: GET /phx/admin_slider.php 200 auth noerror -->
- [ ] Menu: homepage_products_section.php  <!-- check: GET /phx/homepage_products_section.php 200 auth noerror -->
- [ ] Menu: order_page_ui.php  <!-- check: GET /phx/order_page_ui.php 200 auth noerror -->
- [ ] Menu: admin_footer.php  <!-- check: GET /phx/admin_footer.php 200 auth noerror -->
- [ ] Menu: buttons.php  <!-- check: GET /phx/buttons.php 200 auth noerror -->
- [ ] Menu: theme_backup.php  <!-- check: GET /phx/theme_backup.php 200 auth noerror -->
- [ ] Menu: cms_pages.php  <!-- check: GET /phx/cms_pages.php 200 auth noerror -->
- [ ] Menu: landing_pages.php  <!-- check: GET /phx/landing_pages.php 200 auth noerror -->
- [ ] Menu: languages.php  <!-- check: GET /phx/languages.php 200 auth noerror -->
- [ ] Menu: sss.php  <!-- check: GET /phx/sss.php 200 auth noerror -->
- [ ] Menu: custom_forms.php  <!-- check: GET /phx/custom_forms.php 200 auth noerror -->
- [ ] Menu: admin_meta.php  <!-- check: GET /phx/admin_meta.php 200 auth noerror -->
- [ ] Menu: conversion_api_settings.php  <!-- check: GET /phx/conversion_api_settings.php 200 auth noerror -->
- [ ] Menu: attribution_settings.php  <!-- check: GET /phx/attribution_settings.php 200 auth noerror -->
- [ ] Menu: attribution_orders.php  <!-- check: GET /phx/attribution_orders.php 200 auth noerror -->
- [ ] Menu: fake_notifications.php  <!-- check: GET /phx/fake_notifications.php 200 auth noerror -->
- [ ] Menu: admin_notification_settings.php  <!-- check: GET /phx/admin_notification_settings.php 200 auth noerror -->
- [ ] Menu: countdown_settings.php  <!-- check: GET /phx/countdown_settings.php 200 auth noerror -->
- [ ] Menu: carkifelek_settings.php  <!-- check: GET /phx/carkifelek_settings.php 200 auth noerror -->
- [ ] Menu: carkifelek_logs.php  <!-- check: GET /phx/carkifelek_logs.php 200 auth noerror -->
- [ ] Menu: telegram_settings.php  <!-- check: GET /phx/telegram_settings.php 200 auth noerror -->
- [ ] Menu: netgsm_settings.php  <!-- check: GET /phx/netgsm_settings.php 200 auth noerror -->
- [ ] Menu: parasut_settings.php  <!-- check: GET /phx/parasut_settings.php 200 auth noerror -->
- [ ] Menu: paytr_settings.php  <!-- check: GET /phx/paytr_settings.php 200 auth noerror -->
- [ ] Menu: iyzico_settings.php  <!-- check: GET /phx/iyzico_settings.php 200 auth noerror -->
- [ ] Menu: admin_smtp_settings.php  <!-- check: GET /phx/admin_smtp_settings.php 200 auth noerror -->
- [ ] Menu: blocked_ips.php  <!-- check: GET /phx/blocked_ips.php 200 auth noerror -->
- [ ] Menu: blocked_phones.php  <!-- check: GET /phx/blocked_phones.php 200 auth noerror -->
- [ ] Menu: cloaker_settings.php  <!-- check: GET /phx/cloaker_settings.php 200 auth noerror -->
- [ ] Menu: cloaker_traffic.php  <!-- check: GET /phx/cloaker_traffic.php 200 auth noerror -->
- [ ] Menu: cache_settings.php  <!-- check: GET /phx/cache_settings.php 200 auth noerror -->
- [ ] Menu: link_cloak/campaigns.php  <!-- check: GET /phx/link_cloak/campaigns.php 200 auth noerror -->
- [ ] Menu: link_cloak/traffic.php  <!-- check: GET /phx/link_cloak/traffic.php 200 auth noerror -->
- [ ] Menu: safe_page_settings.php  <!-- check: GET /phx/safe_page_settings.php 200 auth noerror -->
- [ ] Menu: edit_password.php  <!-- check: GET /phx/edit_password.php 200 auth noerror -->
