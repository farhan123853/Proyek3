<?php
/**
 * Jalankan Migration 006 (Status Kedaluwarsa, Batas Bayar & Log Reservasi) — Villa Joglo Masayu
 * Usage: php database/migrations/run_006.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Akses ditolak: Skrip migrasi hanya boleh dijalankan melalui CLI.\n";
    exit;
}

require_once __DIR__ . '/../../includes/bootstrap.php';
$pdo = get_db();

echo "=== Menjalankan Migrasi 006: Status Kedaluwarsa, Batas Bayar & Log Reservasi ===\n";

// 1. Perluas ENUM status
$pdo->exec("
    ALTER TABLE `reservasi` 
    MODIFY COLUMN `status` ENUM('menunggu','dikonfirmasi','ditolak','dibatalkan','kedaluwarsa') NOT NULL DEFAULT 'menunggu'
");
echo " [+] ENUM kolom `status` pada tabel `reservasi` berhasil diperluas dengan 'kedaluwarsa'.\n";

// 2. Periksa dan tambahkan kolom secara idempoten
$cols = $pdo->query("
    SELECT COLUMN_NAME 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservasi'
")->fetchAll(PDO::FETCH_COLUMN);

$columnsToAdd = [
    'batas_bayar_pada' => "ALTER TABLE `reservasi` ADD COLUMN `batas_bayar_pada` DATETIME NULL AFTER `status`",
    'rencana_bayar'    => "ALTER TABLE `reservasi` ADD COLUMN `rencana_bayar` ENUM('online','tunai') NOT NULL DEFAULT 'online' AFTER `batas_bayar_pada`",
    'kedaluwarsa_pada' => "ALTER TABLE `reservasi` ADD COLUMN `kedaluwarsa_pada` DATETIME NULL AFTER `rencana_bayar`",
];

foreach ($columnsToAdd as $colName => $alterSql) {
    if (!in_array($colName, $cols, true)) {
        $pdo->exec($alterSql);
        echo " [+] Kolom `{$colName}` berhasil ditambahkan ke tabel `reservasi`.\n";
    } else {
        echo " [=] Kolom `{$colName}` sudah ada (dilewati).\n";
    }
}

// 3. Periksa dan tambahkan indeks idx_reservasi_status_batas
$idxCount = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservasi' AND INDEX_NAME = 'idx_reservasi_status_batas'
")->fetchColumn();

if ($idxCount === 0) {
    $pdo->exec("ALTER TABLE `reservasi` ADD INDEX `idx_reservasi_status_batas` (`status`, `batas_bayar_pada`)");
    echo " [+] Indeks `idx_reservasi_status_batas` berhasil ditambahkan.\n";
} else {
    echo " [=] Indeks `idx_reservasi_status_batas` sudah ada (dilewati).\n";
}

// 4. Buat tabel reservasi_log
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `reservasi_log` (
        `id_log` INT AUTO_INCREMENT PRIMARY KEY,
        `id_reservasi` INT NOT NULL,
        `dari_status` ENUM('menunggu','dikonfirmasi','ditolak','dibatalkan','kedaluwarsa') NULL,
        `ke_status` ENUM('menunggu','dikonfirmasi','ditolak','dibatalkan','kedaluwarsa') NOT NULL,
        `oleh_pengguna` INT NULL,
        `alasan` VARCHAR(200) NULL,
        `dibuat_pada` DATETIME NOT NULL,
        INDEX `idx_reservasi_log_id` (`id_reservasi`),
        INDEX `idx_reservasi_log_dibuat` (`dibuat_pada`),
        CONSTRAINT `fk_reservasi_log_reservasi` FOREIGN KEY (`id_reservasi`) REFERENCES `reservasi` (`id_reservasi`) ON DELETE CASCADE,
        CONSTRAINT `fk_reservasi_log_pengguna` FOREIGN KEY (`oleh_pengguna`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo " [+] Tabel `reservasi_log` dipastikan ada.\n";

// 5. Isi batas_bayar_pada untuk reservasi dikonfirmasi yang sudah ada
$updatedRows = $pdo->exec("
    UPDATE `reservasi`
    SET `batas_bayar_pada` = DATE_ADD(`dibuat_pada`, INTERVAL 24 HOUR)
    WHERE `status` = 'dikonfirmasi' AND `batas_bayar_pada` IS NULL
");
echo " [+] Batas bayar diisi untuk {$updatedRows} reservasi terkonfirmasi lama.\n";

// 6. Inisialisasi entri log awal jika belum ada
$insertedLogs = $pdo->exec("
    INSERT INTO `reservasi_log` (`id_reservasi`, `dari_status`, `ke_status`, `oleh_pengguna`, `alasan`, `dibuat_pada`)
    SELECT r.`id_reservasi`, NULL, r.`status`, r.`diproses_oleh`, 'Inisialisasi migrasi data status awal', r.`dibuat_pada`
    FROM `reservasi` r
    WHERE NOT EXISTS (
        SELECT 1 FROM `reservasi_log` rl WHERE rl.`id_reservasi` = r.`id_reservasi`
    )
");
echo " [+] Sebanyak {$insertedLogs} entri status awal dicatat ke `reservasi_log`.\n";

echo "=== Migrasi 006 Selesai ===\n";
