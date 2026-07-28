<?php
require '../db.php';
require 'auth.php';
require_once dirname(__DIR__) . '/includes/app_url.php';

$page_title = 'Yorum Üretici';
$message = '';
if (isset($_GET['saved']) && (int) ($_GET['edit'] ?? 0) > 0) {
    $message = 'success:Yorum kaydedildi.';
}

// Ürünler
$products = $pdo->query('SELECT product_id, product_name FROM products ORDER BY product_name')->fetchAll(PDO::FETCH_ASSOC);

// AI ayarları (tek satır)
$pdo->exec("CREATE TABLE IF NOT EXISTS ai_settings (
  id TINYINT PRIMARY KEY,
  default_provider VARCHAR(20) DEFAULT 'local',
  openai_key VARCHAR(256) DEFAULT NULL,
  gemini_key VARCHAR(256) DEFAULT NULL
)");
$aiRowCount = $pdo->query('SELECT COUNT(*) FROM ai_settings')->fetchColumn();
if ((int)$aiRowCount === 0) {
    $pdo->exec("INSERT INTO ai_settings (id, default_provider) VALUES (1, 'local')");
}

// AI ayarları tablosunu güncelle (128'den 256'ya)
try {
    $pdo->exec("ALTER TABLE ai_settings MODIFY COLUMN openai_key VARCHAR(256) DEFAULT NULL");
    $pdo->exec("ALTER TABLE ai_settings MODIFY COLUMN gemini_key VARCHAR(256) DEFAULT NULL");
} catch (PDOException $e) {
    // Sütun zaten güncellenmiş olabilir
}
$ai = $pdo->query('SELECT default_provider, openai_key, gemini_key FROM ai_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC) ?: [];

// Global review visibility (create table if not exists)
$pdo->exec("CREATE TABLE IF NOT EXISTS reviews_settings (id TINYINT PRIMARY KEY, show_reviews TINYINT(1) NOT NULL DEFAULT 1)");
$check = $pdo->query("SELECT COUNT(*) FROM reviews_settings")->fetchColumn();
if ((int)$check === 0) { $pdo->exec("INSERT INTO reviews_settings (id, show_reviews) VALUES (1,1)"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_reviews'])) {
    $show = isset($_POST['show_reviews']) ? 1 : 0;
    $sm = isset($_POST['show_manual_front']) ? 1 : 0;
    $sa = isset($_POST['show_ai_front']) ? 1 : 0;
    try {
        $pdo->exec('ALTER TABLE reviews_settings ADD COLUMN show_manual_front TINYINT(1) NOT NULL DEFAULT 1');
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec('ALTER TABLE reviews_settings ADD COLUMN show_ai_front TINYINT(1) NOT NULL DEFAULT 1');
    } catch (Throwable $e) {
    }
    $stmt = $pdo->prepare('UPDATE reviews_settings SET show_reviews=?, show_manual_front=?, show_ai_front=? WHERE id=1');
    $stmt->execute([$show, $sm, $sa]);
    $message = 'success:Yorum gösterme ayarı güncellendi.';
}
$show_row = $pdo->query('SELECT show_reviews, COALESCE(show_manual_front,1) AS smf, COALESCE(show_ai_front,1) AS saf FROM reviews_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC);
$show_reviews = (int)($show_row['show_reviews'] ?? 1);
$show_manual_front = (int)($show_row['smf'] ?? 1);
$show_ai_front = (int)($show_row['saf'] ?? 1);

function review_image_pk_column(PDO $pdo): string
{
    static $cache = [];

    $key = spl_object_id($pdo);
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    try {
        if ($pdo->query("SHOW COLUMNS FROM product_review_images LIKE 'id'")->fetch()) {
            return $cache[$key] = 'id';
        }
        if ($pdo->query("SHOW COLUMNS FROM product_review_images LIKE 'image_id'")->fetch()) {
            return $cache[$key] = 'image_id';
        }
    } catch (Throwable $e) {
    }

    return $cache[$key] = 'id';
}

function review_image_web_src(string $path, ?PDO $pdo = null): string
{
    $p = str_replace('\\', '/', trim($path));
    $p = preg_replace('#^(\.\./)+#', '', $p);
    $p = ltrim($p, '/');
    if ($p === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $p)) {
        return $p;
    }

    return app_url($p, [], $pdo);
}

function review_image_abs_path(string $path): string
{
    $p = str_replace('\\', '/', trim($path));
    $p = preg_replace('#^(\.\./)+#', '', $p);
    $p = ltrim($p, '/');

    return dirname(__DIR__) . '/' . $p;
}

function generateName($gender = 'mix') {
    $male = ['Ahmet','Mehmet','Emre','Mert','Burak','Cem','Hakan','Berk','Can','Onur','Kerem','Eren','Tolga','Serkan','Kaan','Umut','Gökhan','Barış','Yusuf','İbrahim','Ömer','Halil','Furkan','Musa','Samet','Enes','Metin','Ali','Mustafa'];
    $female = ['Ayşe','Fatma','Zeynep','Selin','Elif','Deniz','Ece','Derya','Melis','İrem','Tuğçe','Buse','Sena','Gizem','Cansu','Naz','Hülya','Gül','Şeyma','Merve','Burcu','Esra','Sibel','Yasemin','Dilan','Nisa','Aylin','Derin','Su'];
    if ($gender === 'male') { $pool = $male; }
    elseif ($gender === 'female') { $pool = $female; }
    else { $pool = (mt_rand(0,1)===1) ? $male : $female; } // mix
    $surnames = ['Yılmaz','Kaya','Demir','Şahin','Çelik','Yıldız','Yıldırım','Aydın','Arslan','Doğan','Kurt','Koç','Polat','Taş','Er','Aksoy','Öztürk','Çetin','Korkmaz','Avcı','Aslan','Bozkurt','Kara','Türkmen','Kaplan','Erdoğan','Özdemir','Sezer','Kuş'];
    return $pool[array_rand($pool)] . ' ' . $surnames[array_rand($surnames)];
}

function generateTextLocal($seed, $length = 'orta') {
    $positiveWords = ['harika', 'mükemmel', 'süper', 'muhteşem', 'olağanüstü', 'fantastik', 'müthiş', 'çok iyi', 'başarılı', 'kaliteli', 'güzel', 'hoş', 'tatmin edici', 'beklentileri aştı', 'çok beğendim', 'çok memnun kaldım'];
    $qualityWords = ['kalite', 'işçilik', 'malzeme', 'dokuma', 'kesim', 'tasarım', 'renk', 'kumaş', 'boya', 'yapı', 'doku', 'görünüm', 'hassasiyet', 'detay'];
    $experienceWords = ['kullanım', 'deneyim', 'rahatlık', 'konfor', 'pratiklik', 'kolaylık', 'uygunluk', 'uyum', 'performans', 'dayanıklılık', 'sağlamlık'];
    $deliveryWords = ['teslimat', 'paketleme', 'hız', 'güvenlik', 'özen', 'dikkat', 'hizmet', 'müşteri hizmetleri', 'iletişim', 'destek'];
    $recommendWords = ['öneririm', 'tavsiye ederim', 'gönül rahatlığıyla alabilirsiniz', 'kesinlikle alın', 'pişman olmazsınız', 'değer', 'fiyat/performans', 'uygun fiyat'];

    $sentences = [
        // Kalite cümleleri
        ucfirst($seed) . ' ' . $qualityWords[array_rand($qualityWords)] . ' ' . $positiveWords[array_rand($positiveWords)] . '.',
        $qualityWords[array_rand($qualityWords)] . ' açısından ' . $positiveWords[array_rand($positiveWords)] . ' bir ürün.',
        'Beklediğimden çok daha ' . $positiveWords[array_rand($positiveWords)] . ' çıktı.',

        // Deneyim cümleleri
        $experienceWords[array_rand($experienceWords)] . ' ' . $positiveWords[array_rand($positiveWords)] . '.',
        'Günlük kullanımda çok ' . $positiveWords[array_rand($positiveWords)] . '.',
        'Kullanım ' . $experienceWords[array_rand($experienceWords)] . ' açısından ' . $positiveWords[array_rand($positiveWords)] . '.',

        // Teslimat cümleleri
        $deliveryWords[array_rand($deliveryWords)] . ' ' . $positiveWords[array_rand($positiveWords)] . 'ydı.',
        'Hızlı ' . $deliveryWords[array_rand($deliveryWords)] . ' ve ' . $positiveWords[array_rand($positiveWords)] . ' paketleme.',
        'Sipariş süreci ' . $positiveWords[array_rand($positiveWords)] . 'ydı.',

        // Tavsiye cümleleri
        'Tereddüt etmiştim ama ' . $recommendWords[array_rand($recommendWords)] . '.',
        'Arkadaşlarıma da ' . $recommendWords[array_rand($recommendWords)] . '.',
        'Fiyat/performans olarak ' . $positiveWords[array_rand($positiveWords)] . '.',
        'Kesinlikle ' . $recommendWords[array_rand($recommendWords)] . '.',

        // Genel pozitif cümleler
        'Çok ' . $positiveWords[array_rand($positiveWords)] . ' bir deneyim yaşadım.',
        'Ürün ' . $positiveWords[array_rand($positiveWords)] . ' ve ' . $positiveWords[array_rand($positiveWords)] . '.',
        'Memnun kaldım, ' . $recommendWords[array_rand($recommendWords)] . '.',
        'Kalite beklentilerimi aştı.',
        'Çok ' . $positiveWords[array_rand($positiveWords)] . ' bir alışveriş deneyimi.',
    ];

    $count = ($length === 'uzun') ? 3 : (($length === 'kisa') ? 1 : 2);
    $selectedSentences = [];

    // İlk cümle her zaman seed ile başlasın
    $firstSentence = $sentences[array_rand($sentences)];
    $selectedSentences[] = $firstSentence;

    // Diğer cümleleri ekle
    for ($i = 1; $i < $count; $i++) {
        $selectedSentences[] = $sentences[array_rand($sentences)];
    }

    return implode(' ', $selectedSentences);
}

function aiGenerate($provider, $apiKey, $model, $prompt, $length = 'orta') {
    $lenAllowed = ['kisa', 'orta', 'uzun'];
    $lengthNorm = in_array($length, $lenAllowed, true) ? $length : 'orta';
    $tokensOpenAi = ['kisa' => 120, 'orta' => 220, 'uzun' => 400];
    $tokensGemini = ['kisa' => 128, 'orta' => 256, 'uzun' => 512];
    $maxTok = $tokensOpenAi[$lengthNorm];
    $maxOutGem = $tokensGemini[$lengthNorm];
    try {
        if ($provider === 'local') {
            return generateTextLocal($prompt, $lengthNorm);
        }

        if ($provider === 'openai') {
                        if (empty($apiKey)) {
                            return ['error' => 'OpenAI API anahtarı gerekli'];
                        }

            // sk-... ve sk-proj-... gibi yaygın biçimler
            if (!preg_match('/^sk-[A-Za-z0-9\-_]{10,}$/', $apiKey)) {
                return ['error' => 'OpenAI API anahtarı biçimi geçersiz. "sk-" ile başlamalı.'];
            }
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            $payload = [
                'model' => ($model ?: 'gpt-3.5-turbo'),
                'messages' => [ ['role'=>'user','content'=>$prompt] ],
                'temperature' => 0.7,
                'max_tokens' => $maxTok
            ];
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_POSTFIELDS => json_encode($payload)
            ]);
            $res = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($res === false) {
                return ['error' => 'OpenAI API bağlantı hatası'];
            }

            $json = @json_decode($res, true);
            if ($httpCode !== 200) {
                $errorMsg = isset($json['error']['message']) ? $json['error']['message'] : 'HTTP ' . $httpCode;
                return ['error' => 'OpenAI API hatası: ' . $errorMsg];
            }

            if (isset($json['choices'][0]['message']['content'])) {
                return trim($json['choices'][0]['message']['content']);
            }
            return ['error' => 'OpenAI API geçersiz yanıt'];
        }

        if ($provider === 'gemini') {
            if (empty($apiKey)) {
                return ['error' => 'Gemini API anahtarı gerekli'];
            }

            if (!preg_match('/^AIza[0-9A-Za-z\-_]{35,}$/', $apiKey)) {
                return ['error' => 'Gemini API anahtarı geçersiz görünüyor (Google AI Studio anahtarı: AIza...).'];
            }
            $useModel = ($model ?: 'gemini-2.5-flash');
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $useModel . ':generateContent?key=' . urlencode($apiKey);
            $payload = [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'maxOutputTokens' => $maxOutGem,
                    'temperature' => 0.75,
                ],
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_POSTFIELDS => json_encode($payload)
            ]);
            $res = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($res === false) {
                return ['error' => 'Gemini API bağlantı hatası'];
            }

            $json = @json_decode($res, true);
            if ($httpCode !== 200) {
                $errorMsg = isset($json['error']['message']) ? $json['error']['message'] : 'HTTP ' . $httpCode;
                return ['error' => 'Gemini API hatası: ' . $errorMsg];
            }

            if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                return trim($json['candidates'][0]['content']['parts'][0]['text']);
            }
            return ['error' => 'Gemini API geçersiz yanıt'];
        }

        return ['error' => 'Bilinmeyen sağlayıcı: ' . $provider];

    } catch (Exception $e) {
        return ['error' => 'API hatası: ' . $e->getMessage()];
    }
}

// Üretilen/gelmiş metni sadeleştir (markdown, gereksiz semboller, çoklu boşluklar)
function cleanReviewText($text){
    if (is_array($text)) return $text; // hata objesi ise dokunma
    $text = preg_replace('/[#*_`~>-]+/u',' ',$text); // markdown işaretleri
    $text = preg_replace('/\s{2,}/u',' ', $text);   // çoklu boşluk
    $text = trim($text);
    return $text;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $product_id = (int)($_POST['product_id'] ?? 0); // 0 = Genel
    $count = max(1, min(20, (int)($_POST['count'] ?? 10)));
    $seed = trim($_POST['seed'] ?? 'ürün');
    $lengthRaw = $_POST['length'] ?? 'orta';
    $allowedLen = ['kisa', 'orta', 'uzun'];
    $length = in_array($lengthRaw, $allowedLen, true) ? $lengthRaw : 'orta';
    $genderRaw = $_POST['gender'] ?? 'mix';
    $gender = in_array($genderRaw, ['male', 'female', 'mix'], true) ? $genderRaw : 'mix';
$provider = $_POST['provider'] ?? ($ai['default_provider'] ?? 'local');
if (!in_array($provider, ['local', 'openai', 'gemini'], true)) {
    $provider = 'local';
}
$openaiKey = trim($_POST['openai_key'] ?? '');
$geminiKey = trim($_POST['gemini_key'] ?? '');
// Boş bırakılırsa kayıtlı anahtarları kullan
if ($openaiKey === '' && !empty($ai['openai_key'])) { $openaiKey = $ai['openai_key']; }
if ($geminiKey === '' && !empty($ai['gemini_key'])) { $geminiKey = $ai['gemini_key']; }
$apiKey = ($provider==='openai') ? $openaiKey : (($provider==='gemini') ? $geminiKey : '');
$model = trim($_POST['model'] ?? '');
    // Her sağlayıcı için doğru varsayılan model
    if ($model==='') {
        if ($provider==='openai') $model='gpt-3.5-turbo';
        elseif ($provider==='gemini') $model='gemini-2.5-flash'; // hızlı ve ucuz
// alternatif: 'gemini-1.5-pro' (daha yetenekli, biraz daha pahalı)

        elseif ($provider==='ollama') $model='mistral';
        elseif ($provider==='huggingface') $model='gpt2';
    }

    // Girilen anahtarları ve seçimi kaydet (persist) - model kaydetme, her seferinde doğru model kullanılacak
    $save = $pdo->prepare('UPDATE ai_settings SET default_provider=?, openai_key=?, gemini_key=? WHERE id=1');
    $save->execute([$provider, ($openaiKey?:NULL), ($geminiKey?:NULL)]);

    // İlk yorumu test et
    // Prompt tamamen kullanıcıya ait: seed alanını direkt kullan
    $prompt = $seed; // kullanıcı istediği promptu serbestçe yazabilir
    if ($provider==='local' && trim($prompt)==='') { $prompt = 'ürün'; }

    $testResult = aiGenerate($provider, $apiKey, $model, $prompt, $length);

    // Hata kontrolü
    if (is_array($testResult) && isset($testResult['error'])) {
        $message = 'error:' . $testResult['error'];
    } else {
        // Başarılı, tüm yorumları oluştur
        $ins = $pdo->prepare('INSERT INTO product_reviews (product_id, reviewer_name, rating, review_text, is_verified, is_ai, created_at) VALUES (?, ?, ?, ?, 1, 1, ?)');
        for ($i=0; $i<$count; $i++) {
            // Yıldız dağılımı: %60 5⭐, %30 4⭐, %10 3⭐; ilk yorum 5⭐ sabit
            if ($i === 0) { $rating = 5; }
            else {
                $r = mt_rand(1,100);
                $rating = ($r<=60) ? 5 : (($r<=90) ? 4 : 3);
            }
            $name = generateName($gender);

            // Metin üretimi: İlk yorum da dahil kısalık/tekrar kontrolü
            $text = ($i===0) ? $testResult : aiGenerate($provider, $apiKey, $model, $prompt, $length);
            if (is_array($text) && isset($text['error'])) { $text = $text['error']; }
            $text = cleanReviewText($text);
            if (mb_strlen($text) > 450) { $text = mb_substr($text, 0, 450) . '...'; }

            // Son 2 ay içinde rastgele tarih
            $ts = strtotime('-' . mt_rand(0, 60) . ' day');
            $date = date('Y-m-d H:i:s', $ts);
            $ins->execute([$product_id, $name, $rating, $text, $date]);
        }
        $message = 'success:' . $count . ' yorum oluşturuldu.';
    }
}

// Yorum görselleri tablosu
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_review_images (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        review_id INT UNSIGNED NOT NULL,
        image_path VARCHAR(512) NOT NULL,
        display_order INT NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_pri_review (review_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {
}

// Manuel yorum + görsel ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_manual_review'])) {
    $reviewer_name = trim((string) ($_POST['manual_name'] ?? ''));
    $rating = max(1, min(5, (int) ($_POST['manual_rating'] ?? 5)));
    $review_text = trim((string) ($_POST['manual_text'] ?? ''));
    $product_id = max(0, (int) ($_POST['manual_product_id'] ?? 0));

    if ($reviewer_name === '' || $review_text === '') {
        $message = 'error:Manuel yorum için isim ve metin zorunludur.';
    } else {
        try {
            $ins = $pdo->prepare(
                'INSERT INTO product_reviews (product_id, reviewer_name, rating, review_text, is_verified, is_ai, is_active, created_at)
                 VALUES (?, ?, ?, ?, 1, 0, 1, NOW())'
            );
            $ins->execute([$product_id, $reviewer_name, $rating, $review_text]);
            $reviewId = (int) $pdo->lastInsertId();

            $uploaded = 0;
            if (isset($_FILES['manual_images']) && is_array($_FILES['manual_images']['name'])) {
                $uploadDir = dirname(__DIR__) . '/uploads/reviews/';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0775, true);
                }
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $imgIns = $pdo->prepare(
                    'INSERT INTO product_review_images (review_id, image_path, display_order) VALUES (?, ?, ?)'
                );
                foreach ($_FILES['manual_images']['name'] as $i => $origName) {
                    if (($_FILES['manual_images']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        continue;
                    }
                    $ext = strtolower(pathinfo((string) $origName, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed, true)) {
                        continue;
                    }
                    $fname = 'review_' . $reviewId . '_' . time() . '_' . $i . '.' . $ext;
                    $dest = $uploadDir . $fname;
                    if (move_uploaded_file($_FILES['manual_images']['tmp_name'][$i], $dest)) {
                        $imgIns->execute([$reviewId, 'uploads/reviews/' . $fname, $uploaded]);
                        $uploaded++;
                    }
                }
            }
            $message = 'success:Manuel yorum eklendi' . ($uploaded > 0 ? " ($uploaded görsel)." : '.');
        } catch (Throwable $e) {
            $message = 'error:Yorum eklenemedi: ' . $e->getMessage();
        }
    }
}

// Tek silme
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $pdo->prepare('DELETE FROM product_review_images WHERE review_id=?')->execute([$did]);
    $pdo->prepare('DELETE FROM product_reviews WHERE review_id=?')->execute([$did]);
    header('Location: reviews.php');
    exit;
}

// Toplu silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    if (!empty($_POST['selected']) && is_array($_POST['selected'])) {
        $ids = array_map('intval', $_POST['selected']);
        if (!empty($ids)) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM product_review_images WHERE review_id IN ($in)")->execute($ids);
            $pdo->prepare("DELETE FROM product_reviews WHERE review_id IN ($in)")->execute($ids);
        }
    }
    header('Location: reviews.php?deleted=1');
    exit;
}
// Düzenleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_review'])) {
    $review_id = (int)($_POST['review_id'] ?? 0);
    $reviewer_name = trim($_POST['reviewer_name'] ?? '');
    $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $review_text = trim($_POST['review_text'] ?? '');
    $product_id = max(0, (int)($_POST['product_id'] ?? 0));
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($review_id && $reviewer_name && $review_text) {
        try {
            $upd = $pdo->prepare('UPDATE product_reviews SET product_id=?, reviewer_name=?, rating=?, review_text=?, is_active=? WHERE review_id=?');
            $upd->execute([$product_id, $reviewer_name, $rating, $review_text, $is_active, $review_id]);

            if (!empty($_POST['delete_image_ids']) && is_array($_POST['delete_image_ids'])) {
                $imgPk = review_image_pk_column($pdo);
                $imgSel = $pdo->prepare("SELECT {$imgPk} AS id, image_path FROM product_review_images WHERE {$imgPk} = ? AND review_id = ?");
                $imgDel = $pdo->prepare("DELETE FROM product_review_images WHERE {$imgPk} = ? AND review_id = ?");
                foreach ($_POST['delete_image_ids'] as $imgIdRaw) {
                    $imgId = (int) $imgIdRaw;
                    if ($imgId <= 0) {
                        continue;
                    }
                    $imgSel->execute([$imgId, $review_id]);
                    $img = $imgSel->fetch(PDO::FETCH_ASSOC);
                    if (!$img) {
                        continue;
                    }
                    $path = (string) ($img['image_path'] ?? '');
                    $abs = review_image_abs_path($path);
                    if ($path !== '' && is_file($abs)) {
                        @unlink($abs);
                    }
                    $imgDel->execute([$imgId, $review_id]);
                }
            }

            if (isset($_FILES['review_images']) && is_array($_FILES['review_images']['name'])) {
                $uploadDir = dirname(__DIR__) . '/uploads/reviews/';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0775, true);
                }
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $ordStmt = $pdo->prepare('SELECT COALESCE(MAX(display_order), -1) FROM product_review_images WHERE review_id = ?');
                $ordStmt->execute([$review_id]);
                $maxOrder = (int) $ordStmt->fetchColumn();
                $imgIns = $pdo->prepare('INSERT INTO product_review_images (review_id, image_path, display_order) VALUES (?, ?, ?)');
                foreach ($_FILES['review_images']['name'] as $i => $origName) {
                    if (($_FILES['review_images']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        continue;
                    }
                    $ext = strtolower(pathinfo((string) $origName, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed, true)) {
                        continue;
                    }
                    $fname = 'review_' . $review_id . '_' . time() . '_' . $i . '.' . $ext;
                    $dest = $uploadDir . $fname;
                    if (move_uploaded_file($_FILES['review_images']['tmp_name'][$i], $dest)) {
                        $maxOrder++;
                        $imgIns->execute([$review_id, 'uploads/reviews/' . $fname, $maxOrder]);
                    }
                }
            }

            header('Location: reviews.php?edit=' . $review_id . '&saved=1#r' . $review_id);
            exit;
        } catch (Throwable $e) {
            $message = 'error:Düzenleme kaydedilemedi: ' . $e->getMessage();
        }
    } else {
        $message = 'error:Eksik bilgi.';
    }
}

// Liste (ürün adı opsiyonel)
$reviews = $pdo->query('SELECT r.*, COALESCE(p.product_name, "Genel") AS product_name FROM product_reviews r LEFT JOIN products p ON p.product_id = r.product_id ORDER BY r.created_at DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
$reviewImages = [];
try {
    $imgPk = review_image_pk_column($pdo);
    $imgRows = $pdo->query("SELECT {$imgPk} AS id, review_id, image_path, display_order FROM product_review_images ORDER BY review_id, display_order, {$imgPk}")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($imgRows as $imgRow) {
        $rid = (int) ($imgRow['review_id'] ?? 0);
        if ($rid <= 0) {
            continue;
        }
        if (!isset($reviewImages[$rid])) {
            $reviewImages[$rid] = [];
        }
        $reviewImages[$rid][] = $imgRow;
    }
} catch (Throwable $e) {
    $reviewImages = [];
}

// Inline edit id
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
?>
<?php include 'admin_header.php'; ?>

<div class="container mt-4">
    <div class="top-bar mb-3 d-flex justify-content-between align-items-center">
        <h2 class="mb-0"><i class="fas fa-comments me-2"></i>Yorum Üretici</h2>
        <div>
            <a href="review_intro.php" class="btn btn-outline-primary btn-sm me-2"><i class="fas fa-comment-dots"></i> Açıklama Yönetimi</a>
            <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Geri</a>
        </div>
    </div>

    <?php if ($message): $p = explode(':',$message,2); $type = $p[0]; $txt = $p[1] ?? $message; ?>
        <div class="alert alert-<?= $type==='success'?'success':'danger' ?>"><?= htmlspecialchars($txt) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-user-edit me-1"></i> Manuel yorum ekle (görselli)</div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">İsim *</label>
                    <input type="text" name="manual_name" class="form-control" required placeholder="Örn: Ayşe Y.">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Yıldız</label>
                    <select name="manual_rating" class="form-select">
                        <?php for ($s = 5; $s >= 1; $s--): ?>
                            <option value="<?= $s ?>" <?= $s === 5 ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ürün (opsiyonel)</label>
                    <select name="manual_product_id" class="form-select">
                        <option value="0">Genel</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= (int) $p['product_id'] ?>"><?= htmlspecialchars((string) $p['product_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Görseller (JPG, PNG, WebP)</label>
                    <input type="file" name="manual_images[]" class="form-control" accept="image/*" multiple>
                </div>
                <div class="col-12">
                    <label class="form-label">Yorum metni *</label>
                    <textarea name="manual_text" class="form-control" rows="2" required placeholder="Müşteri yorumu"></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" name="add_manual_review" value="1" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Manuel yorum kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-magic me-1"></i>Oluştur</div>
        <div class="card-body">
            <form method="POST" class="row g-3">
                <input type="hidden" name="product_id" value="0">
                <div class="col-md-4">
                    <label class="form-label">Prompt (tam serbest)</label>
                    <input type="text" name="seed" class="form-control" placeholder="Örn: Ürün hakkında 2 cümlelik olumlu Türkçe yorum yaz" required>
                    <small class="text-muted">Yerel şablon seçiliyse boş bırakırsanız sadece ürün adı/kelimesi girmeniz yeterli.</small>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Adet</label>
                    <input type="number" name="count" class="form-control" min="1" max="20" value="10" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Cinsiyet</label>
                    <select name="gender" class="form-select">
                        <option value="mix" selected>Karışık</option>
                        <option value="male">Erkek</option>
                        <option value="female">Kadın</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Uzunluk</label>
                    <select name="length" class="form-select">
                        <option value="kisa">Kısa</option>
                        <option value="orta" selected>Orta</option>
                        <option value="uzun">Uzun</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Yapay Zeka Sağlayıcısı</label>
                    <select name="provider" id="provider" class="form-select">
                        <option value="local" <?= (($ai['default_provider'] ?? 'local')==='local'?'selected':'') ?>>Yerel Şablon (ücretsiz)</option>
                        <option value="openai" <?= (($ai['default_provider'] ?? '')==='openai'?'selected':'') ?>>OpenAI Chat Completions</option>
                        <option value="gemini" <?= (($ai['default_provider'] ?? '')==='gemini'?'selected':'') ?>>Google Gemini</option>
                    </select>
                </div>
                <div class="col-md-4" id="openaiKeyWrap">
                    <label class="form-label">OpenAI API Key (opsiyonel)</label>
                    <input type="text" name="openai_key" class="form-control" placeholder="OpenAI anahtarı" value="<?= htmlspecialchars($ai['openai_key'] ?? '') ?>">
                </div>
                <div class="col-md-4" id="geminiKeyWrap">
                    <label class="form-label">Gemini API Key (opsiyonel)</label>
                    <input type="text" name="gemini_key" class="form-control" placeholder="Google AI Studio anahtarı" value="<?= htmlspecialchars($ai['gemini_key'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Model (opsiyonel)</label>
                    <input type="text" id="model" name="model" class="form-control" placeholder="Boş bırakılırsa otomatik">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" name="generate" value="1"><i class="fas fa-wand-magic-sparkles"></i> Oluştur</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-eye me-1"></i> Görüntüleme</div>
        <div class="card-body">
            <form method="POST" class="row g-3 align-items-center">
                <input type="hidden" name="toggle_reviews" value="1">
                <div class="col-12 col-lg-4">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="show_reviews" name="show_reviews" <?= $show_reviews ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_reviews">Yorum bölümü (ana sayfa)</label>
                    </div>
                </div>
                <div class="col-12 col-lg-3">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="show_manual_front" name="show_manual_front" <?= $show_manual_front ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_manual_front">Manuel / normal yorumlar</label>
                    </div>
                </div>
                <div class="col-12 col-lg-3">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="show_ai_front" name="show_ai_front" <?= $show_ai_front ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_ai_front">Yapay zeka ile üretilenler</label>
                    </div>
                </div>
                <div class="col-12 col-lg-2">
                    <button class="btn btn-primary btn-sm w-100" type="submit"><i class="fas fa-save"></i> Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <?php /* Inline edit form satır içinde gösterilecek */ ?>

    <div class="card">
        <div class="card-header"><i class="fas fa-list me-1"></i>Son Yorumlar</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <form method="POST" id="bulk-delete-form" aria-label="Toplu silme"></form>
                <div class="d-flex p-2 justify-content-between align-items-center">
                    <div>
                        <button type="submit" id="bulkDeleteBtn" name="bulk_delete" value="1" form="bulk-delete-form" class="btn btn-danger btn-sm" disabled onclick="return confirm('Seçilen yorumlar silinsin mi?')"><i class="fas fa-trash"></i> Toplu Sil</button>
                    </div>
                    <?php if (isset($_GET['deleted'])): ?>
                        <div class="alert alert-success py-1 px-2 mb-0">Seçilen yorumlar silindi.</div>
                    <?php endif; ?>
                </div>
                <table class="table mb-0 align-middle" style="table-layout: fixed;">
                    <colgroup>
                        <col style="width:36px">
                        <col style="width:22%">
                        <col style="width:12%">
                        <col style="width:50%">
                        <col style="width:12%">
                        <col style="width:8%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th style="width:36px; padding-left:12px; padding-right:12px;"><input type="checkbox" id="checkAll"></th>
                            <th>İsim</th>
                            <th>Yıldız</th>
                            <th>Metin</th>
                            <th>Tarih</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody style="display: table-row-group;">
                        <?php foreach ($reviews as $r): ?>
                        <?php if ($edit_id === (int)$r['review_id']): ?>
                        <tr class="table-warning" id="r<?= (int)$r['review_id'] ?>">
                            <td colspan="6" class="p-3">
                                <form method="POST" enctype="multipart/form-data" class="row g-2 align-items-end">
                                    <input type="hidden" name="review_id" value="<?= (int)$r['review_id'] ?>">
                                    <div class="col-md-2">
                                        <label class="form-label small mb-0">Ürün</label>
                                        <select class="form-select form-select-sm" name="product_id">
                                            <option value="0">Genel</option>
                                            <?php foreach ($products as $p): ?>
                                                <option value="<?= (int) $p['product_id'] ?>" <?= ((int)($r['product_id'] ?? 0) === (int)$p['product_id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars((string) $p['product_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-0">İsim</label>
                                        <input type="text" class="form-control form-control-sm" name="reviewer_name" value="<?= htmlspecialchars($r['reviewer_name']) ?>" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-0">Yıldız</label>
                                        <select class="form-select form-select-sm" name="rating">
                                            <?php for($i=5;$i>=1;$i--): ?>
                                                <option value="<?= $i ?>" <?= ((int)$r['rating']===$i?'selected':'') ?>><?= $i ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-0">Metin</label>
                                        <textarea class="form-control form-control-sm" name="review_text" rows="2" style="min-height:70px;resize:vertical" required><?= htmlspecialchars($r['review_text']) ?></textarea>
                                    </div>
                                    <div class="col-md-1">
                                        <small class="text-muted d-block"><?= date('d.m.Y', strtotime($r['created_at'])) ?></small>
                                        <div class="form-check form-switch mt-1">
                                            <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= ((int)($r['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">Aktif</label>
                                        </div>
                                    </div>
                                    <div class="col-md-1 text-end">
                                        <button class="btn btn-sm btn-primary" type="submit" name="update_review" value="1" title="Kaydet"><i class="fas fa-save"></i></button>
                                        <a href="reviews.php#r<?= (int)$r['review_id'] ?>" class="btn btn-sm btn-secondary ms-1" title="İptal"><i class="fas fa-times"></i></a>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small mb-0">Yeni görsel ekle</label>
                                        <input type="file" name="review_images[]" class="form-control form-control-sm" accept="image/*" multiple>
                                    </div>
                                    <?php $editImages = $reviewImages[(int)$r['review_id']] ?? []; ?>
                                    <div class="col-12">
                                        <label class="form-label small mb-1">Mevcut görseller (silmek için işaretle)</label>
                                        <?php if ($editImages === []): ?>
                                            <p class="small text-muted mb-0">Henüz görsel yok. Yukarıdan yükleyip kaydedin.</p>
                                        <?php else: ?>
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php foreach ($editImages as $img): ?>
                                                    <?php $imgPath = (string) ($img['image_path'] ?? ''); ?>
                                                    <label class="border rounded p-1 text-center bg-white" style="width:120px;">
                                                        <img src="<?= htmlspecialchars(review_image_web_src($imgPath, $pdo)) ?>" alt="" style="width:100%;height:80px;object-fit:cover;border-radius:4px;" onerror="this.style.opacity='0.3'">
                                                        <div class="form-check mt-1 mb-0">
                                                            <input class="form-check-input" type="checkbox" name="delete_image_ids[]" value="<?= (int) ($img['id'] ?? 0) ?>">
                                                            <span class="small">Sil</span>
                                                        </div>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        <?php else: ?>
                        <tr id="r<?= (int)$r['review_id'] ?>">
                            <td><input type="checkbox" class="revchk" name="selected[]" value="<?= (int)$r['review_id'] ?>" form="bulk-delete-form"></td>
                            <td><?php
                                $cities = ['Adana','Adıyaman','Afyonkarahisar','Ağrı','Amasya','Ankara','Antalya','Artvin','Aydın','Balıkesir','Bilecik','Bingöl','Bitlis','Bolu','Burdur','Bursa','Çanakkale','Çankırı','Çorum','Denizli','Diyarbakır','Edirne','Elazığ','Erzincan','Erzurum','Eskişehir','Gaziantep','Giresun','Gümüşhane','Hakkari','Hatay','Isparta','Mersin','İstanbul','İzmir','Kars','Kastamonu','Kayseri','Kırklareli','Kırşehir','Kocaeli','Konya','Kütahya','Malatya','Manisa','Kahramanmaraş','Mardin','Muğla','Muş','Nevşehir','Niğde','Ordu','Rize','Sakarya','Samsun','Siirt','Sinop','Sivas','Tekirdağ','Tokat','Trabzon','Tunceli','Şanlıurfa','Uşak','Van','Yozgat','Zonguldak','Aksaray','Bayburt','Karaman','Kırıkkale','Batman','Şırnak','Bartın','Ardahan','Iğdır','Yalova','Karabük','Kilis','Osmaniye','Düzce'];
                                $city = $cities[(int)$r['review_id'] % count($cities)];
                            ?>
                            <?= htmlspecialchars($r['reviewer_name']) ?> <span class="badge bg-success" style="font-weight:700;color:#fff;">Satın aldı - <?= $city ?></span></td>
                            <td><?php for($i=1;$i<=5;$i++): ?><i class="fa<?= $i <= (int)$r['rating'] ? 's' : 'r' ?> fa-star" style="color:#f1c40f"></i><?php endfor; ?></td>
                            <td>
                                <a href="#r<?= (int)$r['review_id'] ?>" style="text-decoration: none; color: inherit;"><?= htmlspecialchars($r['review_text']) ?></a>
                                <?php $rowImages = $reviewImages[(int)$r['review_id']] ?? []; ?>
                                <?php if ($rowImages !== []): ?>
                                    <div class="mt-2 d-flex flex-wrap gap-1">
                                        <?php foreach (array_slice($rowImages, 0, 4) as $img): ?>
                                            <img src="<?= htmlspecialchars(review_image_web_src((string) ($img['image_path'] ?? ''), $pdo)) ?>" alt="" style="width:46px;height:46px;object-fit:cover;border-radius:6px;border:1px solid #ddd;">
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d.m.Y', strtotime($r['created_at'])) ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="reviews.php?edit=<?= (int)$r['review_id'] ?>#r<?= (int)$r['review_id'] ?>" title="Düzenle"><i class="fas fa-edit"></i></a>
                                <a class="btn btn-sm btn-outline-danger ms-1" href="reviews.php?delete=<?= (int)$r['review_id'] ?>" onclick="return confirm('Silinsin mi?')" title="Sil"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
// Sağlayıcı seçimine göre anahtar/model alanlarını dinamik göster
function toggleProviderFields(){
  var p = document.getElementById('provider');
  if(!p) return;
  var val = p.value;
  var openai = document.getElementById('openaiKeyWrap');
  var gemini = document.getElementById('geminiKeyWrap');
  var model = document.getElementById('model');
  if(val === 'local'){
    if(openai) openai.style.display = 'none';
    if(gemini) gemini.style.display = 'none';
    if(model) model.placeholder = 'Yerel şablon kullanılır';
  } else if(val === 'openai'){
    if(openai) openai.style.display = '';
    if(gemini) gemini.style.display = 'none';
    if(model) model.placeholder = 'Boş: gpt-3.5-turbo';
  } else     if(val === 'gemini'){
    if(openai) openai.style.display = 'none';
    if(gemini) gemini.style.display = '';
    if(model) model.placeholder = 'Boş: gemini-2.5-flash';
  }
}
document.addEventListener('DOMContentLoaded', function(){
  var p = document.getElementById('provider');
  if(p){ p.addEventListener('change', toggleProviderFields); toggleProviderFields(); }
});
const chkAll = document.getElementById('checkAll');
const bulkBtn = document.getElementById('bulkDeleteBtn');
function refreshBulk(){
  const anySel = Array.from(document.querySelectorAll('.revchk')).some(x=>x.checked);
  if (bulkBtn) bulkBtn.disabled = !anySel;
}
if (chkAll){
  chkAll.addEventListener('click', function(){
    document.querySelectorAll('.revchk').forEach(function(c){ c.checked = chkAll.checked; });
    refreshBulk();
  });
}
document.querySelectorAll('.revchk').forEach(function(c){ c.addEventListener('change', refreshBulk); });

// Smooth scroll için
document.documentElement.style.scrollBehavior = 'smooth';

// Hash scroll sistemi (index.php'den alındı)
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            var offset = 90; // Admin panel için sabit offset
            window.scrollTo({
                top: target.offsetTop - offset,
                behavior: 'smooth'
            });
        }
    });
});
</script>
<style>
.table thead th:first-child,
.table tbody td:first-child{ width:36px; }
.table tbody td{ vertical-align: middle; word-wrap: break-word; white-space: normal; }
.table tbody td:nth-child(4){ line-height:1.4; }

/* Smooth scroll */
html {
    scroll-behavior: smooth;
}
</style>
<?php include 'admin_footer_common.php'; ?>

