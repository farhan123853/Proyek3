<?php
/**
 * Jalankan Migration 005 (Kolom Virtual Account & Kode Pembayaran) — Villa Joglo Masayu
 * Usage: php database/migrations/run_005.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Akses ditolak: Skrip migrasi hanya boleh dijalankan melalui CLI.\n";
    exit;
}

require_once __DIR__ . '/../../includes/bootstrap.php';
$pdo = get_db();

echo "=== Menjalankan Migrasi 005: Virtual Account & Kode Pembayaran ===\n";

// 1. Periksa dan tambahkan kolom secara idempoten
$cols = $pdo->query("
    SELECT COLUMN_NAME 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pembayaran'
")->fetchAll(PDO::FETCH_COLUMN);

$columnsToAdd = [
    'bank_atau_toko'  => "ALTER TABLE `pembayaran` ADD COLUMN `bank_atau_toko` VARCHAR(30) NULL AFTER `metode_detail`",
    'kode_pembayaran' => "ALTER TABLE `pembayaran` ADD COLUMN `kode_pembayaran` VARCHAR(64) NULL AFTER `bank_atau_toko`",
    'kedaluwarsa_pada'=> "ALTER TABLE `pembayaran` ADD COLUMN `kedaluwarsa_pada` DATETIME NULL AFTER `kode_pembayaran`",
    'kode_dibuat_pada'=> "ALTER TABLE `pembayaran` ADD COLUMN `kode_dibuat_pada` DATETIME NULL AFTER `kedaluwarsa_pada`",
];

foreach ($columnsToAdd as $colName => $alterSql) {
    if (!in_array($colName, $cols, true)) {
        $pdo->exec($alterSql);
        echo " [+] Kolom `{$colName}` berhasil ditambahkan ke tabel `pembayaran`.\n";
    } else {
        echo " [=] Kolom `{$colName}` sudah ada (dilewati).\n";
    }
}

// 2. Periksa dan tambahkan indeks non-unik idx_pembayaran_kode_va
$idxCount = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pembayaran' AND INDEX_NAME = 'idx_pembayaran_kode_va'
")->fetchColumn();

if ($idxCount === 0) {
    $pdo->exec("ALTER TABLE `pembayaran` ADD INDEX `idx_pembayaran_kode_va` (`bank_atau_toko`, `kode_pembayaran`)");
    echo " [+] Indeks non-unik `idx_pembayaran_kode_va` berhasil ditambahkan.\n";
} else {
    echo " [=] Indeks `idx_pembayaran_kode_va` sudah ada (dilewati).\n";
}

echo "=== Migrasi 005 Selesai ===\n";
