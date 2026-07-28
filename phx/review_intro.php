<?php
require '../db.php';
require 'auth.php';

$page_title = 'Yorum Açıklaması Yönetimi';
$message = '';

// URL'den mesaj al (success:/error: beklenir; çıktıda htmlspecialchars kullanılıyor)
if (isset($_GET['msg'])) {
    $raw = (string) $_GET['msg'];
    if (preg_match('/^(success|error):(.+)/si', $raw, $m) && isset($m[2])) {
        $message = strtolower($m[1]) . ':' . $m[2];
    } elseif ($raw !== '' && mb_strlen($raw) <= 1800) {
        $message = 'success:' . $raw;
    }
}


// Açıklama ayarları tablosu
$pdo->exec("CREATE TABLE IF NOT EXISTS review_intro_settings (
  id TINYINT PRIMARY KEY,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  title VARCHAR(255) DEFAULT 'Müşteri Deneyimleri',
  content TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// css_code sütununu kaldır (eğer varsa)
try {
    $pdo->exec("ALTER TABLE review_intro_settings DROP COLUMN css_code");
} catch (PDOException $e) {
    // Sütun zaten yoksa hata vermez
}

// Çoklu açıklama tablosu
$pdo->exec("CREATE TABLE IF NOT EXISTS review_intro_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  content TEXT NOT NULL,
  image_path VARCHAR(500) DEFAULT NULL,
  sort_order INT DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// css_code sütunu kaldırıldı

// AI ayarları tablosunu güncelle (128'den 256'ya)
try {
    $pdo->exec("ALTER TABLE ai_settings MODIFY COLUMN openai_key VARCHAR(256) DEFAULT NULL");
    $pdo->exec("ALTER TABLE ai_settings MODIFY COLUMN gemini_key VARCHAR(256) DEFAULT NULL");
} catch (PDOException $e) {
    // Sütun zaten güncellenmiş olabilir
}

$introRowCount = $pdo->query('SELECT COUNT(*) FROM review_intro_settings')->fetchColumn();
if ((int)$introRowCount === 0) {
    $pdo->exec("INSERT INTO review_intro_settings (id, is_active, title) VALUES (1, 0, 'Müşteri Deneyimleri')");
}

// AI ayarları (reviews.php'den aynı)
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
$ai = $pdo->query('SELECT default_provider, openai_key, gemini_key FROM ai_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC) ?: [];

// Açıklama listesini çek
$introItems = $pdo->query('SELECT * FROM review_intro_items ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);

// Açıklama ayarlarını çek
$introSettings = $pdo->query('SELECT is_active, title, content FROM review_intro_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC) ?: [
    'is_active' => 0,
    'title' => 'Müşteri Deneyimleri',
    'content' => ''
];

// AI üretim fonksiyonu (reviews.php'den aynı)
function aiGenerate($provider, $apiKey, $model, $prompt, $maxTokens = 2000, $temperature = 0.9) {
    try {

        if ($provider === 'openai') {
            if (empty($apiKey)) {
                return ['error' => 'OpenAI API anahtarı gerekli'];
            }
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            $payload = [
                'model' => ($model ?: 'gpt-3.5-turbo'),
                'messages' => [ ['role'=>'user','content'=>$prompt] ],
                'temperature' => $temperature,
                'max_tokens' => $maxTokens
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
            $useModel = ($model ?: 'gemini-2.5-flash');
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $useModel . ':generateContent?key=' . urlencode($apiKey);
            $payload = [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'maxOutputTokens' => max(128, min(8192, (int) $maxTokens)),
                    'temperature' => $temperature
                ]
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

// Açıklama üretimi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_intro'])) {
    $provider = $_POST['provider'] ?? ($ai['default_provider'] ?? 'local');
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
        elseif ($provider==='gemini') $model='gemini-2.5-flash';
    }

    $prompt = trim($_POST['prompt'] ?? 'Ürün hakkında detaylı, uzun ve etkileyici bir açıklama yaz. Ürünün özelliklerini, kalitesini, kullanım alanlarını ve müşteri memnuniyetini vurgula. En az 3-4 paragraf olsun.');
    $maxTokens = (int)($_POST['max_tokens'] ?? 2000);
    $temperature = (float)($_POST['temperature'] ?? 0.9);

    $result = aiGenerate($provider, $apiKey, $model, $prompt, $maxTokens, $temperature);

    if (is_array($result) && isset($result['error'])) {
        $message = 'error:' . $result['error'];
    } else {
        // Başarılı, açıklamayı kaydet
        $stmt = $pdo->prepare('UPDATE review_intro_settings SET content = ? WHERE id = 1');
        $stmt->execute([$result]);
        $message = 'success:Açıklama başarıyla üretildi ve kaydedildi.';

        // Sayfayı yenile
        header('Location: review_intro.php?msg=' . urlencode($message));
        exit;
    }
}

    // Açıklama ayarlarını güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $title = trim($_POST['title'] ?? 'Müşteri Deneyimleri');
    $content = trim($_POST['content'] ?? '');

    $stmt = $pdo->prepare('UPDATE review_intro_settings SET is_active = ?, title = ?, content = ? WHERE id = 1');
    $stmt->execute([$is_active, $title, $content]);
    $message = 'success:Açıklama ayarları güncellendi.';

    // Sayfayı yenile ve "Açıklama Ayarları" bölümünde kal
    header('Location: review_intro.php?msg=' . urlencode($message) . '#settings');
    exit;
}

// Açıklamayı sil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_intro'])) {
    $stmt = $pdo->prepare('UPDATE review_intro_settings SET content = NULL WHERE id = 1');
    $stmt->execute();
    $message = 'success:Açıklama silindi.';

    // Sayfayı yenile
    header('Location: review_intro.php?msg=' . urlencode($message));
    exit;
}

// Yeni açıklama ekle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $title = trim($_POST['item_title'] ?? '');
    $content = trim($_POST['item_content'] ?? '');
    $sort_order = (int)($_POST['item_sort_order'] ?? 0);

    // En az bir alan dolu olmalı (başlık, içerik veya görsel)
    $has_image = isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK && !empty($_FILES['item_image']['name']);

    if ($title || $content || $has_image) {
        // Görsel yükleme
        $image_path = null;
        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($file_extension, $allowed_extensions)) {
                $filename = 'intro_' . time() . '_' . uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $filename;

                if (move_uploaded_file($_FILES['item_image']['tmp_name'], $upload_path)) {
                    $image_path = 'uploads/' . $filename;
                }
            }
        }

        $stmt = $pdo->prepare('INSERT INTO review_intro_items (title, content, image_path, sort_order) VALUES (?, ?, ?, ?)');
        $stmt->execute([$title, $content, $image_path, $sort_order]);
        $message = 'success:Açıklama başarıyla eklendi.';
        // Sayfayı yenile
        header('Location: review_intro.php?msg=' . urlencode($message));
        exit;
    } else {
        $message = 'error:En az bir alan (başlık, içerik veya görsel) dolu olmalıdır.';
    }
}

// Açıklama düzenle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_item'])) {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $title = trim($_POST['item_title'] ?? '');
    $content = trim($_POST['item_content'] ?? '');
    $sort_order = (int)($_POST['item_sort_order'] ?? 0);
    $is_active = isset($_POST['item_is_active']) ? 1 : 0;

    // En az bir alan dolu olmalı (başlık, içerik veya görsel)
    $has_image = isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK && !empty($_FILES['item_image']['name']);

    if ($item_id && ($title || $content || $has_image)) {
        // Görsel yükleme
        $image_path = null;
        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK && !empty($_FILES['item_image']['name'])) {
            $upload_dir = '../uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($file_extension, $allowed_extensions)) {
                $filename = 'intro_' . time() . '_' . uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $filename;

                if (move_uploaded_file($_FILES['item_image']['tmp_name'], $upload_path)) {
                    $image_path = 'uploads/' . $filename;
                }
            }
        }

        // Mevcut görseli al
        $current_stmt = $pdo->prepare('SELECT image_path FROM review_intro_items WHERE id = ?');
        $current_stmt->execute([$item_id]);
        $current_item = $current_stmt->fetch(PDO::FETCH_ASSOC);
        $final_image_path = $current_item['image_path']; // Mevcut görseli koru

        if ($image_path) {
            $final_image_path = $image_path; // Yeni görsel varsa onu kullan
        }

        $stmt = $pdo->prepare('UPDATE review_intro_items SET title = ?, content = ?, image_path = ?, sort_order = ?, is_active = ? WHERE id = ?');
        $stmt->execute([$title, $content, $final_image_path, $sort_order, $is_active, $item_id]);

        $message = 'success:Açıklama başarıyla güncellendi.';
        // Sayfayı yenile ve düzenleme formuna git
        header('Location: review_intro.php?msg=' . urlencode($message) . '#edit-form');
        exit;
    } else {
        $message = 'error:En az bir alan (başlık, içerik veya görsel) dolu olmalıdır.';
    }
}

// Açıklama sil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    $item_id = (int)($_POST['item_id'] ?? 0);
    if ($item_id) {
        // Görseli de sil
        $stmt = $pdo->prepare('SELECT image_path FROM review_intro_items WHERE id = ?');
        $stmt->execute([$item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($item && $item['image_path'] && file_exists('../' . $item['image_path'])) {
            unlink('../' . $item['image_path']);
        }

        $stmt = $pdo->prepare('DELETE FROM review_intro_items WHERE id = ?');
        $stmt->execute([$item_id]);
        $message = 'success:Açıklama başarıyla silindi.';
        // Sayfayı yenile
        header('Location: review_intro.php?msg=' . urlencode($message));
        exit;
    }
}

// Tek sıralama güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_single_sort'])) {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $new_sort_order = (int)($_POST['new_sort_order'] ?? 0);

    if ($item_id) {
        $stmt = $pdo->prepare('UPDATE review_intro_items SET sort_order = ? WHERE id = ?');
        $stmt->execute([$new_sort_order, $item_id]);
        $message = 'success:Sıralama güncellendi.';
        // Sayfayı yenile
        header('Location: review_intro.php?msg=' . urlencode($message));
        exit;
    }
}

// Sıralamayı oklarla taşı (yukarı)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_up'])) {
    $item_id = (int)($_POST['item_id'] ?? 0);
    if ($item_id) {
        try {
            $pdo->beginTransaction();
            // Mevcut öğe
            $stmt = $pdo->prepare('SELECT id, sort_order FROM review_intro_items WHERE id = ? FOR UPDATE');
            $stmt->execute([$item_id]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($current) {
                // Bir üst komşu (daha küçük sort_order'a sahip en yakın kayıt)
                $stmt = $pdo->prepare('SELECT id, sort_order FROM review_intro_items WHERE (sort_order < ?) ORDER BY sort_order DESC, id DESC LIMIT 1');
                $stmt->execute([$current['sort_order']]);
                $prev = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($prev) {
                    // Çakışmayı önlemek için geçici bir değer kullanarak swap
                    $pdo->prepare('UPDATE review_intro_items SET sort_order = -1 WHERE id = ?')->execute([$prev['id']]);
                    $pdo->prepare('UPDATE review_intro_items SET sort_order = ? WHERE id = ?')->execute([$prev['sort_order'], $current['id']]);
                    $pdo->prepare('UPDATE review_intro_items SET sort_order = ? WHERE id = ?')->execute([$current['sort_order'], $prev['id']]);
                }
            }
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
        }
        header('Location: review_intro.php');
        exit;
    }
}

// Sıralamayı oklarla taşı (aşağı)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_down'])) {
    $item_id = (int)($_POST['item_id'] ?? 0);
    if ($item_id) {
        try {
            $pdo->beginTransaction();
            // Mevcut öğe
            $stmt = $pdo->prepare('SELECT id, sort_order FROM review_intro_items WHERE id = ? FOR UPDATE');
            $stmt->execute([$item_id]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($current) {
                // Bir alt komşu (daha büyük sort_order'a sahip en yakın kayıt)
                $stmt = $pdo->prepare('SELECT id, sort_order FROM review_intro_items WHERE (sort_order > ?) ORDER BY sort_order ASC, id ASC LIMIT 1');
                $stmt->execute([$current['sort_order']]);
                $next = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($next) {
                    // Çakışmayı önlemek için geçici bir değer kullanarak swap
                    $pdo->prepare('UPDATE review_intro_items SET sort_order = -1 WHERE id = ?')->execute([$next['id']]);
                    $pdo->prepare('UPDATE review_intro_items SET sort_order = ? WHERE id = ?')->execute([$next['sort_order'], $current['id']]);
                    $pdo->prepare('UPDATE review_intro_items SET sort_order = ? WHERE id = ?')->execute([$current['sort_order'], $next['id']]);
                }
            }
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
        }
        header('Location: review_intro.php');
        exit;
    }
}

// Durum değiştir (Aktif/Pasif)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $item_id = (int)($_POST['item_id'] ?? 0);

    if ($item_id) {
        // Mevcut durumu al
        $stmt = $pdo->prepare('SELECT is_active FROM review_intro_items WHERE id = ?');
        $stmt->execute([$item_id]);
        $current_status = $stmt->fetchColumn();

        // Durumu tersine çevir
        $new_status = $current_status == 1 ? 0 : 1;

        // Güncelle
        $stmt = $pdo->prepare('UPDATE review_intro_items SET is_active = ? WHERE id = ?');
        $stmt->execute([$new_status, $item_id]);

        $status_text = $new_status == 1 ? 'aktif' : 'pasif';
        $message = 'success:Açıklama durumu ' . $status_text . ' olarak güncellendi.';

        // Sayfayı yenile
        header('Location: review_intro.php?msg=' . urlencode($message));
        exit;
    }
}

// Düzenleme modu
$edit_item = null;
if (isset($_GET['edit_item'])) {
    $item_id = (int)$_GET['edit_item'];
    $stmt = $pdo->prepare('SELECT * FROM review_intro_items WHERE id = ?');
    $stmt->execute([$item_id]);
    $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<?php include 'admin_header.php'; ?>

<div class="container mt-4">
    <div class="top-bar mb-3 d-flex justify-content-between align-items-center">
        <h2 class="mb-0"><i class="fas fa-comment-dots me-2"></i>Yorum Açıklaması Yönetimi</h2>
        <a href="reviews.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Yorumlara Dön</a>
    </div>

    <?php if ($message): $p = explode(':',$message,2); $type = $p[0]; $txt = $p[1] ?? $message; ?>
        <div class="alert alert-<?= $type==='success'?'success':'danger' ?>"><?= htmlspecialchars($txt) ?></div>
    <?php endif; ?>

    <!-- Açıklama Üretimi -->
    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-magic me-1"></i>Açıklama Üret</div>
        <div class="card-body">
            <form method="POST" class="row g-3">
                <div class="col-12">
                    <label class="form-label">Prompt (Açıklama İsteği)</label>
                    <textarea name="prompt" class="form-control" rows="4" placeholder="Ürün hakkında detaylı açıklama isteği yazın...

Örnek uzun prompt:
'Termal boya ürünü için HTML formatında çok detaylı ve uzun banner açıklaması yaz. En az 5-6 paragraf olsun. Ana başlık, alt başlık, ürün özellikleri, kullanım alanları, avantajları, teknik detaylar, müşteri yorumları ve sonuç bölümü olsun. Her bölümü HTML etiketleri ile (p, strong, em, ul, li, h3, h4) detaylı şekilde anlat. Çok uzun ve kapsamlı olsun.'"></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Yapay Zeka Sağlayıcısı</label>
                    <select name="provider" id="provider" class="form-select">
                        <option value="openai" <?= (($ai['default_provider'] ?? 'openai')==='openai'?'selected':'') ?>>OpenAI Chat Completions</option>
                        <option value="gemini" <?= (($ai['default_provider'] ?? '')==='gemini'?'selected':'') ?>>Google Gemini</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Model</label>
                    <select name="model" id="model" class="form-select">
                        <option value="">Otomatik Seçim</option>
                        <optgroup label="OpenAI Modelleri" id="openai-models" style="display:none;">
                            <option value="gpt-4o">GPT-4o (En Gelişmiş)</option>
                            <option value="gpt-4o-mini">GPT-4o Mini (Hızlı)</option>
                            <option value="gpt-4-turbo">GPT-4 Turbo</option>
                            <option value="gpt-3.5-turbo">GPT-3.5 Turbo (Ekonomik)</option>
                        </optgroup>
                        <optgroup label="Gemini Modelleri" id="gemini-models" style="display:none;">
                            <option value="gemini-2.0-flash-exp">Gemini 2.0 Flash (Deneysel)</option>
                            <option value="gemini-1.5-pro">Gemini 1.5 Pro (Gelişmiş)</option>
                            <option value="gemini-1.5-flash">Gemini 1.5 Flash (Hızlı)</option>
                            <option value="gemini-pro">Gemini Pro (Klasik)</option>
                        </optgroup>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Token Limiti</label>
                    <select name="max_tokens" id="max_tokens" class="form-select">
                        <option value="1000">1000 (Kısa)</option>
                        <option value="2000" selected>2000 (Orta)</option>
                        <option value="3000">3000 (Uzun)</option>
                        <option value="4000">4000 (Çok Uzun)</option>
                        <option value="8000">8000 (Maksimum)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Yaratıcılık (Temperature)</label>
                    <select name="temperature" id="temperature" class="form-select">
                        <option value="0.5">0.5 (Çok Düşük)</option>
                        <option value="0.7">0.7 (Düşük)</option>
                        <option value="0.8">0.8 (Orta)</option>
                        <option value="0.9" selected>0.9 (Yüksek)</option>
                        <option value="1.0">1.0 (Maksimum)</option>
                    </select>
                </div>
                <div class="col-md-6" id="openaiKeyWrap">
                    <label class="form-label">OpenAI API Key (opsiyonel)</label>
                    <input type="text" name="openai_key" class="form-control" placeholder="OpenAI anahtarı" value="<?= htmlspecialchars($ai['openai_key'] ?? '') ?>">
                </div>
                <div class="col-md-6" id="geminiKeyWrap">
                    <label class="form-label">Gemini API Key (opsiyonel)</label>
                    <input type="text" name="gemini_key" class="form-control" placeholder="Google AI Studio anahtarı" value="<?= htmlspecialchars($ai['gemini_key'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary" name="generate_intro" value="1"><i class="fas fa-wand-magic-sparkles"></i> Açıklama Üret</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Açıklama Ayarları -->
    <div class="card mb-4" id="settings">
        <div class="card-header"><i class="fas fa-cog me-1"></i>Açıklama Ayarları</div>
        <div class="card-body">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?= $introSettings['is_active'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_active">Açıklamayı anasayfada göster</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Başlık <small class="text-muted">(Her zaman gizli)</small></label>
                        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($introSettings['title'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Açıklama Metni (HTML Destekli)</label>
                        <textarea name="content" class="form-control" rows="6" placeholder="HTML etiketleri kullanabilirsiniz: <p>, <strong>, <br>, <span>, vb."><?= htmlspecialchars($introSettings['content'] ?? '') ?></textarea>
                        <small class="text-muted">HTML etiketleri desteklenir: &lt;p&gt;, &lt;strong&gt;, &lt;br&gt;, &lt;span&gt;, &lt;div&gt; vb.</small>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-success" name="update_settings" value="1"><i class="fas fa-save"></i> Ayarları Kaydet</button>
                        <?php if (!empty($introSettings['content'])): ?>
                            <button class="btn btn-danger ms-2" name="delete_intro" value="1" onclick="return confirm('Açıklamayı silmek istediğinizden emin misiniz?')"><i class="fas fa-trash"></i> Açıklamayı Sil</button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <!-- Çoklu Açıklama Yönetimi -->
    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-list me-1"></i>Çoklu Açıklama Yönetimi</div>
        <div class="card-body">
            <!-- Açıklama Ekleme/Düzenleme Formu -->
            <div class="card mb-3 <?= $edit_item ? 'border-primary shadow-lg' : '' ?>" id="edit-form" <?= $edit_item ? 'style="border-width: 2px !important;"' : '' ?>>
                <div class="card-header <?= $edit_item ? 'bg-primary text-white' : '' ?>">
                    <i class="fas fa-<?= $edit_item ? 'edit' : 'plus' ?> me-1"></i>
                    <?= $edit_item ? 'Açıklama Düzenle' : 'Yeni Açıklama Ekle' ?>
                    <?php if ($edit_item): ?>
                        <a href="review_intro.php" class="btn btn-sm btn-light float-end">İptal</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <?php if ($edit_item): ?>
                            <input type="hidden" name="item_id" value="<?= $edit_item['id'] ?>">
                        <?php endif; ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Başlık (Opsiyonel)</label>
                                <input type="text" name="item_title" class="form-control" value="<?= htmlspecialchars($edit_item['title'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Sıralama</label>
                                <input type="number" name="item_sort_order" class="form-control" value="<?= $edit_item['sort_order'] ?? 0 ?>" min="0">
                            </div>
                            <div class="col-md-3">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" name="item_is_active" id="item_is_active" <?= ($edit_item['is_active'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="item_is_active">Aktif</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Görsel</label>
                                <input type="file" name="item_image" class="form-control" accept="image/*">
                                <?php if ($edit_item && $edit_item['image_path']): ?>
                                    <small class="text-muted">Mevcut görsel: <?= htmlspecialchars($edit_item['image_path']) ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label">İçerik (HTML Destekli - Opsiyonel)</label>
                                <textarea name="item_content" class="form-control" rows="4"><?= htmlspecialchars($edit_item['content'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12">
                                <?php if ($edit_item): ?>
                                    <button class="btn btn-success" name="edit_item" value="1"><i class="fas fa-save"></i> Güncelle</button>
                                <?php else: ?>
                                    <button class="btn btn-primary" name="add_item" value="1"><i class="fas fa-plus"></i> Açıklama Ekle</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Mevcut Açıklamalar -->
            <div class="card">
                <div class="card-header">
                    <span><i class="fas fa-list me-1"></i>Mevcut Açıklamalar</span>
                </div>
                <div class="card-body">
                    <?php if (empty($introItems)): ?>
                        <p class="text-muted text-center">Henüz açıklama eklenmemiş.</p>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($introItems as $item): ?>
                                <li class="list-group-item d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <form method="POST" class="me-1">
                                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                            <button type="submit" name="move_up" value="1" class="btn btn-sm btn-outline-secondary" title="Yukarı Taşı">
                                                <i class="fas fa-arrow-up"></i>
                                            </button>
                                        </form>
                                        <form method="POST" class="me-3">
                                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                            <button type="submit" name="move_down" value="1" class="btn btn-sm btn-outline-secondary" title="Aşağı Taşı">
                                                <i class="fas fa-arrow-down"></i>
                                            </button>
                                        </form>
                                        <?php if ($item['image_path']): ?>
                                            <img src="../<?= htmlspecialchars($item['image_path']) ?>" alt="görsel" style="width:80px;height:60px;object-fit:cover;border-radius:6px;margin-right:12px;">
                                        <?php endif; ?>
                                        <div>
                                            <div class="d-flex align-items-center mb-1">
                                                <strong class="me-2"><?= $item['title'] ? htmlspecialchars($item['title']) : 'Başlıksız Açıklama' ?></strong>
                                                <form method="POST" class="m-0 p-0">
                                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                    <button type="submit" name="toggle_status" value="1" class="badge <?= $item['is_active'] ? 'bg-success' : 'bg-warning' ?> status-badge" title="Durumu değiştir">
                                                        <?= $item['is_active'] ? 'Aktif' : 'Pasif' ?>
                                                    </button>
                                                </form>
                                            </div>
                                            <?php if ($item['content']): ?>
                                                <div style="max-height:60px;overflow:hidden;"><?= $item['content'] ?></div>
                                            <?php else: ?>
                                                <div class="text-muted"><em>İçerik yok</em></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="btn-group">
                                        <a href="?edit_item=<?= $item['id'] ?>#edit-form" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Düzenle</a>
                                        <form method="POST" onsubmit="return confirm('Bu açıklamayı silmek istediğinizden emin misiniz?')">
                                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" name="delete_item" value="1"><i class="fas fa-trash"></i> Sil</button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>



<script>

// Sayfa yüklendiğinde düzenleme modundaysa forma scroll yap
document.addEventListener('DOMContentLoaded', function() {
    // Düzenleme modundaysa forma scroll yap
    <?php if ($edit_item): ?>
    setTimeout(function() {
        document.getElementById('edit-form').scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }, 100);
    <?php endif; ?>
});
</script>

<style>
/* Modern Card Styles */
.modern-card {
    border: none;
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.12);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    overflow: hidden;
    background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
    border: 1px solid rgba(0,0,0,0.05);
}

.modern-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    border-color: rgba(13, 110, 253, 0.2);
}

.card-image {
    position: relative;
    overflow: hidden;
}

.card-image img {
    width: 100%;
    height: 200px;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.modern-card:hover .card-image img {
    transform: scale(1.05);
}

.card-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modern-card:hover .card-overlay {
    opacity: 1;
}

.card-actions {
    display: flex;
    gap: 10px;
}

.card-actions .btn {
    border-radius: 12px;
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    font-weight: 600;
    transition: all 0.3s ease;
}

.card-actions .btn:hover {
    transform: scale(1.15) translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.4);
}

.card-actions .btn-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
}

.card-actions .btn-danger {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
}

.content-preview {
    max-height: 80px;
    overflow: hidden;
    position: relative;
    font-size: 14px;
    line-height: 1.4;
}

.content-preview::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 20px;
    background: linear-gradient(transparent, white);
}

.card-title {
    font-weight: 700;
    color: #1a202c;
    margin-bottom: 12px;
    font-size: 16px;
    line-height: 1.3;
}

.card-text {
    color: #4a5568;
    font-size: 14px;
    line-height: 1.5;
}

.badge {
    font-size: 11px;
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 600;
}

/* Status badge özel stilleri */
.status-badge {
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    font-size: 10px;
    padding: 4px 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge:hover {
    transform: scale(1.1);
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.status-badge.bg-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
}

.status-badge.bg-warning {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%) !important;
    color: white !important;
}

/* Alt butonlar için özel stiller */
.btn-group .btn {
    border-radius: 12px !important;
    font-weight: 600;
    padding: 8px 16px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.btn-group .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.25);
}

.btn-group .btn-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border: none;
}

.btn-group .btn-danger {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    border: none;
}

/* Sıralama butonu */
.btn-success {
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
    border: none;
    border-radius: 8px;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
    transition: all 0.3s ease;
}

.btn-success:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
}

/* Sıralama input */
.sort-input {
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-weight: 600;
    text-align: center;
    transition: all 0.3s ease;
}

.sort-input:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    outline: none;
}

/* Responsive */
@media (max-width: 768px) {
    .modern-card {
        margin-bottom: 20px;
    }

    .card-image img {
        height: 150px;
    }
}
</style>

<script>
// Sağlayıcı seçimine göre anahtar alanlarını ve modelleri dinamik göster
function toggleProviderFields(){
  var p = document.getElementById('provider');
  if(!p) return;
  var val = p.value;
  var openai = document.getElementById('openaiKeyWrap');
  var gemini = document.getElementById('geminiKeyWrap');
  var openaiModels = document.getElementById('openai-models');
  var geminiModels = document.getElementById('gemini-models');

  if(val === 'openai'){
    if(openai) openai.style.display = '';
    if(gemini) gemini.style.display = 'none';
    if(openaiModels) openaiModels.style.display = '';
    if(geminiModels) geminiModels.style.display = 'none';
  } else if(val === 'gemini'){
    if(openai) openai.style.display = 'none';
    if(gemini) gemini.style.display = '';
    if(openaiModels) openaiModels.style.display = 'none';
    if(geminiModels) geminiModels.style.display = '';
  }
}

// Model seçimine göre önerilen ayarları otomatik yap
function updateRecommendedSettings(){
  var model = document.getElementById('model');
  var maxTokens = document.getElementById('max_tokens');
  var temperature = document.getElementById('temperature');

  if(!model || !maxTokens || !temperature) return;

  var modelValue = model.value;

  // Model bazlı önerilen ayarlar
  if(modelValue.includes('gpt-4o')){
    maxTokens.value = '4000';
    temperature.value = '0.9';
  } else if(modelValue.includes('gpt-4')){
    maxTokens.value = '3000';
    temperature.value = '0.8';
  } else if(modelValue.includes('gpt-3.5')){
    maxTokens.value = '2000';
    temperature.value = '0.9';
  } else if(modelValue.includes('gemini-2.0')){
    maxTokens.value = '4000';
    temperature.value = '0.9';
  } else if(modelValue.includes('gemini-1.5-pro')){
    maxTokens.value = '3000';
    temperature.value = '0.8';
  } else if(modelValue.includes('gemini-1.5-flash')){
    maxTokens.value = '2000';
    temperature.value = '0.9';
  } else if(modelValue.includes('gemini-pro')){
    maxTokens.value = '2000';
    temperature.value = '0.8';
  }
}
document.addEventListener('DOMContentLoaded', function(){
  var p = document.getElementById('provider');
  var m = document.getElementById('model');

  if(p){
    p.addEventListener('change', toggleProviderFields);
    toggleProviderFields();
  }

  if(m){
    m.addEventListener('change', updateRecommendedSettings);
  }
});
</script>

<?php include 'admin_footer_common.php'; ?>
