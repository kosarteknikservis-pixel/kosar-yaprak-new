<?php
require '../db.php'; // Veritabanı bağlantısını dahil edin
require 'auth.php';

// Filtreleme parametrelerini al
$status_name = isset($_GET['status_name']) ? $_GET['status_name'] : null;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;

// Sorgu için SQL ve parametreler dizisini hazırlayın
$query = "
    SELECT DISTINCT o.customer_phone
    FROM orders o
    JOIN order_status s ON o.order_status_id = s.order_status_id
    WHERE 1=1
";

$params = [];

// Durum filtresi uygulama
if ($status_name) {
    $query .= " AND s.status_name = ?";
    $params[] = $status_name;
}

// Tarih aralığı filtresi uygulama
if ($start_date && $end_date) {
    $query .= " AND DATE(o.order_date) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

// Sorguyu çalıştır ve sonuçları al
$number_stmt = $pdo->prepare($query);
$number_stmt->execute($params);

$phone_numbers = $number_stmt->fetchAll(PDO::FETCH_COLUMN);

// CSV dosyasını oluştur ve indir
header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename=telefon_numaralari.csv');
$csv_file = fopen('php://output', 'w');

// CSV dosyasına başlık ekle
fputcsv($csv_file, ['Phone Number']);

// Telefon numaralarını CSV'ye yaz
foreach ($phone_numbers as $number) {
    // Numarayı temizle
    $number = preg_replace('/\D/', '', $number);  // Sadece rakamları tut
    $number = ltrim($number, '0'); // Başındaki sıfırları temizle
    fputcsv($csv_file, [$number]);
}

fclose($csv_file);
exit();
?>
