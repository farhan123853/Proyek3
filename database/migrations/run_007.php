<?php
/**
 * Jalankan Migration 007 (Perpanjangan Batas Bayar Reservasi) — Villa Joglo Masayu
 * Menambahkan kolom perpanjang_kali TINYINT NOT NULL DEFAULT 0 pada tabel reservasi.
 * Usage: php database/migrations/run_007.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Akses ditolak: Skrip migrasi hanya boleh dijalankan melalui CLI.\n";
    exit;
}

require_once __DIR__ . '/../../includes/bootstrap.php';
$pdo = get_db();

echo "=== Menjalankan Migrasi 007: Perpanjangan Batas Bayar Reservasi ===\n";

// Periksa dan tambahkan kolom perpanjang_kali secara idempoten
$cols = $pdo->query("
    SELECT COLUMN_NAME 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservasi'
")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('perpanjang_kali', $cols, true)) {
    $pdo->exec("ALTER TABLE `reservasi` ADD COLUMN `perpanjang_kali` TINYINT NOT NULL DEFAULT 0 AFTER `kedaluwarsa_pada`");
    echo " [+] Kolom `perpanjang_kali` TINYINT NOT NULL DEFAULT 0 berhasil ditambahkan ke tabel `reservasi`.\n";
} else {
    echo " [=] Kolom `perpanjang_kali` sudah ada pada tabel `reservasi` (dilewati).\n";
}

echo "=== Migrasi 007 Selesai ===\n";
