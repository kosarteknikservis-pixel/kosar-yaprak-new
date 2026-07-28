<?php
declare(strict_types=1);

/**
 * Site bazlı olmayan genel KVKK / yasal metin şablonları.
 * İletişim satırları admin ayarlarından veya CMS'den gelmez; standart metin kullanılır.
 */

function legal_kvkk_html(): string
{
    return <<<'HTML'
<h2>Kişisel Verilerin Korunması Hakkında Aydınlatma Metni</h2>
<p>6698 sayılı Kişisel Verilerin Korunması Kanunu (“KVKK”) kapsamında, veri sorumlusu sıfatıyla kişisel verileriniz aşağıda açıklanan çerçevede işlenmektedir.</p>

<h3>1. Veri Sorumlusu</h3>
<p>Bu internet sitesi üzerinden gerçekleştirilen işlemlerde veri sorumlusu, siteyi işleten tüzel/gerçek kişidir. Sipariş ve iletişim formlarında paylaştığınız bilgiler yalnızca hizmet sunumu amacıyla kullanılır.</p>

<h3>2. İşlenen Kişisel Veriler</h3>
<ul>
<li>Kimlik ve iletişim bilgileri (ad, soyad, telefon, e-posta, adres)</li>
<li>Sipariş ve ödeme bilgileri (ürün, tutar, teslimat adresi)</li>
<li>İşlem güvenliği verileri (IP adresi, oturum/cookie kayıtları, tarayıcı bilgisi)</li>
<li>Pazarlama tercihleri ve kampanya etkileşimleri (açık rıza halinde)</li>
</ul>

<h3>3. İşleme Amaçları</h3>
<ul>
<li>Siparişin alınması, ödemenin alınması ve teslimatın yapılması</li>
<li>Müşteri hizmetleri ve destek taleplerinin yanıtlanması</li>
<li>Yasal yükümlülüklerin yerine getirilmesi</li>
<li>Site güvenliği, dolandırıcılık önleme ve hizmet kalitesinin artırılması</li>
</ul>

<h3>4. Aktarım</h3>
<p>Kişisel verileriniz; kargo firmaları, ödeme kuruluşları, muhasebe/fatura hizmet sağlayıcıları ve yasal mercilerle, yalnızca gerekli olduğu ölçüde ve mevzuata uygun şekilde paylaşılabilir.</p>

<h3>5. Saklama Süresi</h3>
<p>Veriler, ilgili mevzuatta öngörülen süreler boyunca veya işleme amacının gerektirdiği süre kadar saklanır; süre sonunda silinir, yok edilir veya anonim hale getirilir.</p>

<h3>6. Haklarınız</h3>
<p>KVKK’nın 11. maddesi kapsamında; verilerinizin işlenip işlenmediğini öğrenme, düzeltme, silme, itiraz etme ve zararın giderilmesini talep etme haklarına sahipsiniz. Taleplerinizi sitedeki iletişim kanalları üzerinden iletebilirsiniz.</p>

<h3>7. Güvenlik</h3>
<p>Kişisel verilerinizin güvenliği için teknik ve idari tedbirler alınmaktadır. Ödeme bilgileri, lisanslı ödeme altyapısı sağlayıcıları aracılığıyla işlenir; kart bilgileri site sunucularında saklanmaz.</p>
HTML;
}

function legal_mesafeli_satis_html(): string
{
    return <<<'HTML'
<h2>Mesafeli Satış Sözleşmesi</h2>
<p>İşbu sözleşme, 6502 sayılı Tüketicinin Korunması Hakkında Kanun ve Mesafeli Sözleşmeler Yönetmeliği hükümleri uyarınca düzenlenmiştir.</p>

<h3>1. Taraflar</h3>
<p><strong>Satıcı:</strong> Bu internet sitesini işleten satıcı.<br>
<strong>Alıcı:</strong> Siteden sipariş veren tüketici.</p>

<h3>2. Konu</h3>
<p>Alıcı’nın, sitede belirtilen niteliklere sahip ürün/ürünlerin satışı ve teslimine ilişkin tarafların hak ve yükümlülüklerini kapsar.</p>

<h3>3. Ürün ve Bedel</h3>
<p>Ürünün türü, adedi, satış bedeli, vergiler ve kargo ücreti sipariş ekranında açıkça gösterilir. Alıcı, siparişi onayladığında sözleşmeyi kabul etmiş sayılır.</p>

<h3>4. Teslimat</h3>
<p>Ürün, Alıcı’nın bildirdiği adrese, stok ve lojistik koşullarına bağlı olarak makul süre içinde teslim edilir. Teslimat süresi ve koşulları “Kargo Süreci” sayfasında açıklanır.</p>

<h3>5. Cayma Hakkı</h3>
<p>Alıcı, teslimden itibaren 14 gün içinde herhangi bir gerekçe göstermeksizin cayma hakkını kullanabilir. Cayma hakkının kullanımına ilişkin usul “İade ve Değişim” sayfasında yer alır. Hijyen, hızlı bozulan veya kişiye özel üretilen ürünlerde mevzuatın izin vermediği hallerde cayma hakkı kullanılamayabilir.</p>

<h3>6. Ödeme</h3>
<p>Ödeme, sipariş ekranında size sunulan yöntemlerden biriyle tamamlanır. Kart bilgileriniz güvenli ödeme altyapısı üzerinden işlenir; site sunucularında saklanmaz.</p>

<h3>7. Uyuşmazlık</h3>
<p>Uyuşmazlıklarda Alıcı, Tüketici Hakem Heyetlerine ve Tüketici Mahkemelerine başvurabilir.</p>
HTML;
}

function legal_iade_degisim_html(): string
{
    return <<<'HTML'
<h2>İade ve Değişim Koşulları</h2>

<h3>1. Cayma Hakkı (14 Gün)</h3>
<p>Teslim aldığınız ürünü, ambalajı açılmamış ve kullanılmamış olması kaydıyla 14 gün içinde iade edebilirsiniz. İade talebinizi iletişim veya destek formu üzerinden iletmeniz yeterlidir.</p>

<h3>2. İade Süreci</h3>
<ol>
<li>Destek veya iletişim kanalından iade talebi oluşturun.</li>
<li>Ürünü orijinal ambalajı ile birlikte kargoya verin.</li>
<li>Ürün tarafımıza ulaştıktan sonra inceleme yapılır.</li>
<li>Onay sonrası ödemeniz, kullandığınız yönteme uygun şekilde iade edilir.</li>
</ol>

<h3>3. Değişim</h3>
<p>Stok durumuna bağlı olarak aynı ürünün farklı varyantı ile değişim talep edebilirsiniz. Değişim talepleri iade süreci ile aynı kanallardan alınır.</p>

<h3>4. İade Edilemeyen Ürünler</h3>
<ul>
<li>Hijyen ve sağlık açısından iadesi uygun olmayan ürünler</li>
<li>Ambalajı açılmış ve kullanılmış ürünler</li>
<li>Kişiye özel üretilmiş ürünler</li>
<li>Mevzuat gereği iadesi mümkün olmayan ürünler</li>
</ul>

<h3>5. Kargo Ücreti</h3>
<p>Cayma hakkı kapsamındaki iadelerde, yasal düzenlemelere uygun şekilde kargo masrafları uygulanır. Ayıplı/hatalı ürünlerde kargo masrafı satıcıya aittir.</p>
HTML;
}

function legal_kargo_sureci_html(): string
{
    return <<<'HTML'
<h2>Kargo ve teslimat</h2>
<h3>Sipariş onayı</h3>
<p>Siparişiniz bize ulaştıktan sonra kontrol edilir ve işleme alınır.</p>
<h3>Kargoya veriliş</h3>
<p>Onaylanan siparişleriniz genellikle <strong>1–3 iş günü</strong> içinde kargoya teslim edilir.</p>
<h3>Teslimat süresi</h3>
<p>Kargo firması paketinizi adresinize ulaştırır. Bulunduğunuz ile göre teslimat birkaç iş günü sürebilir.</p>
<h3>Sipariş takibi</h3>
<p><a href="sorgula.php">Sipariş sorgulama</a> sayfasından telefon numaranızla güncel durumu görebilirsiniz.</p>
HTML;
}

function legal_hakkimizda_html(): string
{
    return <<<'HTML'
<h2>Mağazamız hakkında</h2>
<h3>Biz kimiz?</h3>
<p>Size kaliteli ürünleri hızlı ve güvenilir şekilde ulaştırmak için çalışıyoruz. Müşteri memnuniyeti önceliğimizdir.</p>
<h3>Neden bizi tercih etmelisiniz?</h3>
<p>Şeffaf fiyatlandırma, hızlı kargo ve kolay destek hattı ile alışverişinizi sorunsuz tamamlamanız için yanınızdayız.</p>
<h3>Teslimat</h3>
<p>Siparişleriniz onay sonrası genellikle <strong>1–3 iş günü</strong> içinde kargoya verilir.</p>
HTML;
}

function legal_iletisim_html(): string
{
    return <<<'HTML'
<h2>Bize ulaşın</h2>
<h3>Destek talebi</h3>
<p>Sipariş, iade veya genel sorularınız için <a href="destek_talebi.php">destek talebi</a> formunu doldurabilirsiniz.</p>
<h3>Sipariş sorgulama</h3>
<p>Mevcut siparişinizin durumunu <a href="sorgula.php">sipariş sorgulama</a> sayfasından telefon numaranızla kontrol edin.</p>
<h3>Çalışma saatleri</h3>
<p>Talepleriniz en kısa sürede yanıtlanır; yoğun dönemlerde dönüş süresi uzayabilir.</p>
HTML;
}

function legal_sss_html(): string
{
    require_once __DIR__ . '/sss_faq.php';

    return sss_faq_build_html(sss_faq_default_items());
}
