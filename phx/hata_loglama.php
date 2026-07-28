<?php
require 'auth.php';
// require 'hata_loglama.php';
// Hataları ekranda gösterme
ini_set('display_errors', 0); // Ekrana hata göstermeyi kapat

// Hataları loglamayı aç
ini_set('log_errors', 1);

// Hataların kaydedileceği dosya yolu
ini_set('error_log', dirname(__DIR__) . '/hata_loglari.log');

// Tüm hata raporlamalarını aç
error_reporting(E_ALL);

?>
