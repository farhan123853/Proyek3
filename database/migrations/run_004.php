<?php
/**
 * Jalankan Migration 004 — Villa Joglo Masayu
 * Usage: php database/migrations/run_004.php
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
try {
    $pdo = get_db();
} catch (Exception $e) {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=villa_joglo_masayu;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
}

$sql = file_get_contents(__DIR__ . '/004_reservasi_offline.sql');

// Pisahkan dan jalankan statement satu per satu
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    fn($s) => $s !== '' && !preg_match('/^--/', $s)
);

foreach ($statements as $stmt) {
    if (empty(trim($stmt))) continue;
    try {
        $pdo->exec($stmt);
        echo "OK: " . substr(trim($stmt), 0, 80) . "...\n";
    } catch (PDOException $e) {
        // Abaikan "Duplicate column name" (migration sudah pernah jalan)
        if (strpos($e->getMessage(), 'Duplicate column') !== false
            || strpos($e->getMessage(), 'already exists') !== false
            || strpos($e->getMessage(), "Can't DROP") !== false) {
            echo "SKIP (already applied): " . substr(trim($stmt), 0, 60) . "\n";
        } else {
            echo "ERROR: " . $e->getMessage() . "\nSQL: " . substr($stmt, 0, 200) . "\n";
        }
    }
}

echo "\nMigration 004 selesai.\n";

