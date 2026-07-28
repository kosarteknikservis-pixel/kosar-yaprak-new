<?php
require 'db.php';

if (isset($_GET['city_id'])) {
    $city_id = (int) $_GET['city_id'];

    // Seçilen ile ait ilçeleri al
    $stmt = $pdo->prepare("SELECT * FROM districts WHERE city_id = ? ORDER BY district_name ASC");
    $stmt->execute([$city_id]);
    $districts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // JSON formatında döndür
    echo json_encode($districts);
}
?>
