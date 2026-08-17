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
        'order.form_title' => ['order', 'Sipariş Bilgileriniz', 'Your Order Details', 'بيانات طلبك'],
        'order.required' => ['order', '(Zorunlu)', '(Required)', '(مطلوب)'],
        'order.select' => ['order', 'Seçiniz', 'Select', 'اختر'],
        'order.note_optional' => ['order', 'İsteğe bağlı', 'Optional', 'اختياري'],
        'order.note_placeholder' => ['order', 'Varsa notunuzu yazın', 'Add a note if needed', 'أضف ملاحظة إن وجدت'],
        'order.otp_wait' => ['order', 'Telefonunuza SMS gönderildi. Doğrulama penceresinden kodu girerek siparişinizi onaylayın.', 'An SMS was sent to your phone. Enter the code in the verification window to confirm your order.', 'تم إرسال رسالة إلى هاتفك. أدخل الرمز في نافذة التحقق لتأكيد طلبك.'],
        'order.otp_title' => ['order', 'SMS Doğrulama', 'SMS Verification', 'التحقق عبر الرسائل'],
        'order.otp_lead' => ['order', 'Telefonunuza gönderilen 6 haneli kodu girin.', 'Enter the 6-digit code sent to your phone.', 'أدخل الرمز المكوّن من 6 أرقام المُرسل إلى هاتفك.'],
        'order.otp_hint' => ['order', 'Kod 5 dakika geçerlidir. Doğrulama sonrası siparişiniz onaylanır.', 'The code is valid for 5 minutes. Your order is confirmed after verification.', 'الرمز صالح لمدة 5 دقائق. يتم تأكيد طلبك بعد التحقق.'],
        'order.otp_code' => ['order', 'Doğrulama kodu', 'Verification code', 'رمز التحقق'],
        'order.otp_placeholder' => ['order', '6 haneli kod', '6-digit code', 'رمز من 6 أرقام'],
        'order.otp_verify' => ['order', 'Kodu doğrula ve siparişi onayla', 'Verify code and confirm order', 'تحقق من الرمز وأكّد الطلب'],
        'order.otp_resend' => ['order', 'Kodu yeniden gönder', 'Resend code', 'إعادة إرسال الرمز'],
        'order.otp_expired' => ['order', 'Kodun süresi doldu. Yeni kod gönderin.', 'The code expired. Request a new one.', 'انتهت صلاحية الرمز. اطلب رمزاً جديداً.'],
        'order.otp_invalid' => ['order', 'Kod hatalı. Lütfen tekrar deneyin.', 'Invalid code. Please try again.', 'الرمز غير صحيح. حاول مرة أخرى.'],
        'order.invoice_section' => ['order', 'Kurumsal fatura bilgileri', 'Corporate invoice details', 'بيانات الفاتورة للشركات'],
        'order.invoice_optional' => ['order', 'İsteğe bağlı · tıklayın', 'Optional · click', 'اختياري · اضغط'],
        'order.invoice_vkn' => ['order', 'Vergi numarası', 'Tax number', 'الرقم الضريبي'],
        'order.invoice_tax_office' => ['order', 'Vergi dairesi', 'Tax office', 'مكتب الضرائب'],
        'order.invoice_company' => ['order', 'Firma ünvanı', 'Company name', 'اسم الشركة'],
        'order.invoice_address' => ['order', 'Fatura adresi', 'Invoice address', 'عنوان الفاتورة'],

        // Teşekkür
        'thankyou.title' => ['thankyou', 'Siparişiniz Alındı', 'Order Received', 'تم استلام طلبك'],
        'thankyou.message' => ['thankyou', 'Teşekkür ederiz! Siparişiniz en kısa sürede hazırlanacak.', 'Thank you! Your order will be prepared shortly.', 'شكراً لك! سيتم تجهيز طلبك قريباً.'],
        'thankyou.thanks' => ['thankyou', 'Teşekkürler!', 'Thank you!', 'شكراً لك!'],
        'thankyou.received' => ['thankyou', 'Siparişiniz başarıyla alındı.', 'Your order was received successfully.', 'تم استلام طلبك بنجاح.'],
        'thankyou.sms_pending' => ['thankyou', 'Siparişiniz alındı. Lütfen telefonunuza gelen kod ile doğrulayın.', 'Your order was received. Please verify with the code sent to your phone.', 'تم استلام طلبك. يرجى التحقق بالرمز المُرسل إلى هاتفك.'],
        'thankyou.order_no' => ['thankyou', 'Sipariş', 'Order', 'الطلب'],
        'thankyou.not_found' => ['thankyou', 'Sipariş bulunamadı', 'Order not found', 'الطلب غير موجود'],
        'thankyou.info' => ['thankyou', 'Sipariş Bilgileri', 'Order Information', 'معلومات الطلب'],
        'thankyou.details' => ['thankyou', 'Sipariş Detayları', 'Order Details', 'تفاصيل الطلب'],
        'thankyou.sms_title' => ['thankyou', 'SMS Doğrulama', 'SMS Verification', 'التحقق عبر الرسائل'],
        'thankyou.sms_help' => ['thankyou', 'Telefonunuza gönderilen 6 haneli kodu girin veya SMS’teki linke tıklayın. Kod 20 dakika geçerlidir.', 'Enter the 6-digit code sent to your phone or tap the link in the SMS. The code is valid for 20 minutes.', 'أدخل الرمز المكوّن من 6 أرقام أو اضغط الرابط في الرسالة. الرمز صالح لمدة 20 دقيقة.'],
        'thankyou.verify' => ['thankyou', 'Doğrula', 'Verify', 'تحقق'],
        'thankyou.resend' => ['thankyou', 'Kodu yeniden gönder', 'Resend code', 'إعادة إرسال الرمز'],
        'thankyou.payment' => ['thankyou', 'Ödeme', 'Payment', 'الدفع'],
        'thankyou.date' => ['thankyou', 'Tarih', 'Date', 'التاريخ'],
        'thankyou.variants' => ['thankyou', 'Varyantlar', 'Options', 'الخيارات'],
        'thankyou.query' => ['thankyou', 'Sipariş sorgula', 'Track order', 'تتبع الطلب'],
        'thankyou.iban' => ['thankyou', 'IBAN', 'IBAN', 'IBAN'],
        'thankyou.holder' => ['thankyou', 'Alıcı adı soyadı', 'Account holder', 'اسم المستفيد'],
        'thankyou.branch' => ['thankyou', 'Şube', 'Branch', 'الفرع'],
        'thankyou.copy_name' => ['thankyou', 'Ad soyadı kopyala', 'Copy name', 'نسخ الاسم'],
        'thankyou.copy_iban' => ['thankyou', 'IBAN kopyala', 'Copy IBAN', 'نسخ IBAN'],
        'thankyou.trust' => ['thankyou', 'Siparişiniz güvenle kaydedildi. En kısa sürede sizinle iletişime geçilecektir.', 'Your order was saved securely. We will contact you shortly.', 'تم حفظ طلبك بأمان. سنتواصل معك قريباً.'],
        'thankyou.where' => ['thankyou', 'Siparişim Nerede?', 'Where is my order?', 'أين طلبي؟'],
        'thankyou.continue' => ['thankyou', 'Alışverişe Devam Et', 'Continue shopping', 'متابعة التسوق'],
        'thankyou.total' => ['thankyou', 'Toplam Tutar', 'Total', 'الإجمالي'],

        // Vitrin / sipariş CTA
        'shop.order_now' => ['shop', 'Hemen Sipariş Ver', 'Order Now', 'اطلب الآن'],
        'shop.order_short' => ['shop', 'Sipariş Ver', 'Order', 'اطلب'],
        'shop.discount' => ['shop', 'indirim', 'off', 'خصم'],
        'shop.default_heading_main' => ['shop', 'Ürünlerimiz', 'Our Products', 'منتجاتنا'],
        'shop.default_heading_sub' => ['shop', 'Güvenli alışveriş', 'Secure shopping', 'تسوق آمن'],
        'shop.empty_products' => ['shop', 'Şu an listelenecek ürün bulunmuyor.', 'No products to display at the moment.', 'لا توجد منتجات للعرض حالياً.'],
        'shop.empty_products_hint' => ['shop', 'Kısa süre içinde tekrar kontrol edebilirsiniz.', 'Please check back soon.', 'يرجى المحاولة لاحقاً.'],
        'shop.payment_options' => ['shop', 'Ödeme seçenekleri', 'Payment options', 'خيارات الدفع'],
        'shop.sale_price' => ['shop', 'Kampanyalı fiyat', 'Sale price', 'سعر العرض'],
        'shop.offer_hint' => ['shop', 'Kapıda ödeme · Ücretsiz kargo · Kampanyalı fiyat', 'Cash on delivery · Free shipping · Sale price', 'الدفع عند الاستلام · شحن مجاني · سعر العرض'],
        'trust.cod_cash' => ['trust', 'Kapıda Nakit Ödeme', 'Cash on Delivery', 'الدفع نقداً عند الاستلام'],
        'trust.cod_card' => ['trust', 'Kapıda Kart ile Ödeme', 'Card on Delivery', 'الدفع بالبطاقة عند الاستلام'],
        'trust.online_card' => ['trust', 'Online Kredi Kartı', 'Online Credit Card', 'بطاقة ائتمان أونلاين'],
        'trust.bank' => ['trust', 'Havale / EFT', 'Bank Transfer', 'تحويل بنكي'],
        'trust.cod' => ['trust', 'Kapıda Ödeme', 'Cash on Delivery', 'الدفع عند الاستلام'],
        'trust.installment_3' => ['trust', '3 taksit imkânı (kartla online)', '3 installments (online card)', '3 أقساط (بطاقة أونلاين)'],
        'trust.installment_n' => ['trust', '{n} taksit imkânı (kartla online)', '{n} installments (online card)', '{n} أقساط (بطاقة أونلاين)'],
        'trust.installment' => ['trust', 'Taksitli ödeme (kartla online)', 'Installments (online card)', 'دفع بالأقساط (بطاقة أونلاين)'],

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

/** Eksik katalog anahtarlarını her istekte bir kez tamamla (INSERT IGNORE). */
function i18n_ensure_catalog(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (!($pdo instanceof PDO)) {
        return;
    }
    i18n_seed_default_translations($pdo);
}
