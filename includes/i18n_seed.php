<?php
declare(strict_types=1);

/**
 * Varsayılan arayüz çevirileri (tr kaynak + en + ar).
 * INSERT IGNORE ile eklenir; admin panelinden yapılan elle düzenlemeler KORUNUR.
 *
 * Anahtar biçimi: grup.alt_anahtar
 */
function i18n_translation_catalog(): array
{
    // key => [group, tr, en, ar]
    return [
        // Menü
        'menu.title' => ['menu', 'Menü', 'Menu', 'القائمة'],
        'menu.home' => ['menu', 'Ana Sayfa', 'Home', 'الرئيسية'],
        'menu.go_products' => ['menu', 'Ürünlere Git', 'Go to Products', 'إلى المنتجات'],
        'menu.order_query' => ['menu', 'Sipariş Sorgula', 'Track Order', 'تتبع الطلب'],
        'menu.faq' => ['menu', 'Sıkça Sorulan Sorular', 'FAQ', 'الأسئلة الشائعة'],
        'menu.support' => ['menu', 'Destek Talebi', 'Support Request', 'طلب الدعم'],
        'menu.dealership' => ['menu', 'Bayilik Başvurusu', 'Become a Dealer', 'طلب الوكالة'],
        'menu.about' => ['menu', 'Hakkımızda', 'About Us', 'من نحن'],
        'menu.shipping' => ['menu', 'Kargo Süreci', 'Shipping', 'الشحن'],
        'menu.contact' => ['menu', 'Bize Ulaşın', 'Contact Us', 'اتصل بنا'],
        'menu.order_cta_title' => ['menu', 'Tıkla Sipariş Ver', 'Click to Order', 'اطلب الآن'],
        'menu.order_cta_sub' => ['menu', 'Ürünleri incele ve hemen sipariş oluştur', 'Browse products and order now', 'تصفّح المنتجات واطلب الآن'],
        'menu.scroll_down' => ['menu', 'Aşağı kaydır', 'Scroll down', 'مرّر للأسفل'],

        // Avantaj kartları
        'perk.section' => ['perk', 'Avantajlarınız', 'Your Benefits', 'مزاياك'],
        'perk.discount' => ['perk', 'Özel İndirim', 'Special Discount', 'خصم خاص'],
        'perk.discount_sub' => ['perk', "%20'ye varan", 'Up to 20%', 'حتى 20%'],
        'perk.free_shipping' => ['perk', 'Ücretsiz Kargo', 'Free Shipping', 'شحن مجاني'],
        'perk.free_shipping_sub' => ['perk', '500 TL üzeri', 'Over 500 TL', 'أكثر من 500 ليرة'],
        'perk.fast_delivery' => ['perk', 'Hızlı Teslimat', 'Fast Delivery', 'توصيل سريع'],
        'perk.fast_delivery_sub' => ['perk', 'Aynı gün kargo', 'Same-day shipping', 'شحن بنفس اليوم'],

        // Genel butonlar / etiketler
        'common.order_now' => ['common', 'Hemen Sipariş Ver', 'Order Now', 'اطلب الآن'],
        'common.add_to_cart' => ['common', 'Sepete Ekle', 'Add to Cart', 'أضف إلى السلة'],
        'common.buy' => ['common', 'Satın Al', 'Buy', 'اشترِ'],
        'common.submit' => ['common', 'Gönder', 'Submit', 'إرسال'],
        'common.save' => ['common', 'Kaydet', 'Save', 'حفظ'],
        'common.send' => ['common', 'Gönder', 'Send', 'إرسال'],
        'common.search' => ['common', 'Ara', 'Search', 'بحث'],
        'common.back_home' => ['common', 'Ana sayfaya dön', 'Back to home', 'العودة للرئيسية'],
        'common.loading' => ['common', 'Yükleniyor…', 'Loading…', 'جارٍ التحميل…'],
        'common.price' => ['common', 'Fiyat', 'Price', 'السعر'],
        'common.quantity' => ['common', 'Adet', 'Quantity', 'الكمية'],
        'common.total' => ['common', 'Toplam', 'Total', 'الإجمالي'],
        'common.free' => ['common', 'Ücretsiz', 'Free', 'مجاني'],
        'common.name' => ['common', 'Ad Soyad', 'Full Name', 'الاسم الكامل'],
        'common.phone' => ['common', 'Telefon', 'Phone', 'الهاتف'],
        'common.address' => ['common', 'Adres', 'Address', 'العنوان'],
        'common.city' => ['common', 'İl', 'City', 'المدينة'],
        'common.district' => ['common', 'İlçe', 'District', 'المنطقة'],
        'common.email' => ['common', 'E-posta', 'Email', 'البريد الإلكتروني'],
        'common.note' => ['common', 'Not', 'Note', 'ملاحظة'],
        'common.required' => ['common', 'Zorunlu alan', 'Required field', 'حقل مطلوب'],

        // Sipariş / ödeme
        'order.title' => ['order', 'Sipariş Oluştur', 'Create Order', 'إنشاء طلب'],
        'order.customer_info' => ['order', 'Müşteri Bilgileri', 'Customer Information', 'معلومات العميل'],
        'order.delivery_address' => ['order', 'Teslimat Adresi', 'Delivery Address', 'عنوان التوصيل'],
        'order.payment_method' => ['order', 'Ödeme Yöntemi', 'Payment Method', 'طريقة الدفع'],
        'order.cod' => ['order', 'Kapıda Ödeme', 'Cash on Delivery', 'الدفع عند الاستلام'],
        'order.summary' => ['order', 'Sipariş Özeti', 'Order Summary', 'ملخص الطلب'],
        'order.complete' => ['order', 'Siparişi Tamamla', 'Complete Order', 'إتمام الطلب'],
        'order.shipping_fee' => ['order', 'Kargo Ücreti', 'Shipping Fee', 'رسوم الشحن'],
        'order.grand_total' => ['order', 'Genel Toplam', 'Grand Total', 'المجموع الكلي'],

        // Sipariş formu alanları
        'order.name_label' => ['order', 'Adınız ve Soyadınız:', 'Your Full Name:', 'اسمك الكامل:'],
        'order.phone_label' => ['order', 'Telefon Numaranız:', 'Your Phone Number:', 'رقم هاتفك:'],
        'order.city_label' => ['order', 'İl:', 'City:', 'المحافظة:'],
        'order.city_select' => ['order', 'İl Seçiniz', 'Select City', 'اختر المحافظة'],
        'order.district_label' => ['order', 'İlçe:', 'District:', 'المنطقة:'],
        'order.district_first' => ['order', 'Önce İl Seçiniz', 'Select City First', 'اختر المحافظة أولاً'],
        'order.district_select' => ['order', 'İlçe Seçiniz', 'Select District', 'اختر المنطقة'],
        'order.district_loading' => ['order', 'İlçeler yükleniyor...', 'Loading districts...', 'جارٍ تحميل المناطق...'],
        'order.district_error' => ['order', 'Hata: İlçeler yüklenemedi', 'Error: districts could not load', 'خطأ: تعذّر تحميل المناطق'],
        'order.district_none' => ['order', 'Bu il için ilçe bulunamadı', 'No districts for this city', 'لا توجد مناطق لهذه المحافظة'],
        'order.conn_error' => ['order', 'Bağlantı hatası', 'Connection error', 'خطأ في الاتصال'],
        'order.address_label' => ['order', 'Teslimat Yapılacak Adres:', 'Delivery Address:', 'عنوان التوصيل:'],
        'order.payment_title' => ['order', 'Ödeme Yöntemi Seçiniz', 'Select Payment Method', 'اختر طريقة الدفع'],
        'order.payment_select' => ['order', 'Seçiniz...', 'Select...', 'اختر...'],
        'order.submit_btn' => ['order', 'SİPARİŞİ TAMAMLA', 'COMPLETE ORDER', 'إتمام الطلب'],
        'order.invoice_section' => ['order', 'Kurumsal fatura bilgileri', 'Corporate invoice details', 'بيانات الفاتورة للشركات'],
        'order.invoice_optional' => ['order', 'İsteğe bağlı · tıklayın', 'Optional · click', 'اختياري · اضغط'],
        'order.invoice_vkn' => ['order', 'Vergi numarası', 'Tax number', 'الرقم الضريبي'],
        'order.invoice_tax_office' => ['order', 'Vergi dairesi', 'Tax office', 'مكتب الضرائب'],
        'order.invoice_company' => ['order', 'Firma ünvanı', 'Company name', 'اسم الشركة'],
        'order.invoice_address' => ['order', 'Fatura adresi', 'Invoice address', 'عنوان الفاتورة'],

        // Teşekkür
        'thankyou.title' => ['thankyou', 'Siparişiniz Alındı', 'Order Received', 'تم استلام طلبك'],
        'thankyou.message' => ['thankyou', 'Teşekkür ederiz! Siparişiniz en kısa sürede hazırlanacak.', 'Thank you! Your order will be prepared shortly.', 'شكراً لك! سيتم تجهيز طلبك قريباً.'],

        // Sorgulama
        'query.title' => ['query', 'Sipariş Sorgulama', 'Order Tracking', 'تتبع الطلب'],
        'query.enter_phone' => ['query', 'Telefon numaranızı girin', 'Enter your phone number', 'أدخل رقم هاتفك'],

        // Footer
        'footer.rights' => ['footer', 'Tüm hakları saklıdır.', 'All rights reserved.', 'جميع الحقوق محفوظة.'],
        'footer.quick_links' => ['footer', 'Hızlı Bağlantılar', 'Quick Links', 'روابط سريعة'],
        'footer.contact' => ['footer', 'İletişim', 'Contact', 'اتصل'],

        // Dil / para birimi seçici
        'switcher.language' => ['switcher', 'Dil', 'Language', 'اللغة'],
        'switcher.currency' => ['switcher', 'Para Birimi', 'Currency', 'العملة'],
    ];
}

function i18n_seed_default_translations(PDO $pdo): void
{
    try {
        $catalog = i18n_translation_catalog();
        $ins = $pdo->prepare(
            'INSERT IGNORE INTO site_translations (lang_code, t_key, t_value, t_group) VALUES (?,?,?,?)'
        );
        foreach ($catalog as $key => $row) {
            [$group, $tr, $en, $ar] = $row;
            $ins->execute(['tr', $key, $tr, $group]);
            $ins->execute(['en', $key, $en, $group]);
            $ins->execute(['ar', $key, $ar, $group]);
        }
    } catch (Throwable $e) {
        if (isset($_SERVER['HTTP_HOST'])) {
            error_log('i18n_seed: ' . $e->getMessage());
        }
    }
}
