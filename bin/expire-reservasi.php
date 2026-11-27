<?php
/**
 * CLI Skrip Penutup Reservasi Lewat Batas (Otomatisasi Cron / Task Scheduler)
 * Menutup reservasi dikonfirmasi yang belum dibayar dan telah melewati batas waktu.
 *
 * Usage: php bin/expire-reservasi.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Akses ditolak: Skrip ini hanya boleh dijalankan melalui CLI atau penjadwal sistem.\n";
    exit(1);
}

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = get_db();
echo "[" . date('Y-m-d H:i:s') . " WIB] Memulai pengecekan reservasi kedaluwarsa...\n";

$hasil = expire_reservasi_lewat_batas($pdo, 100);

echo "Hasil pengecekan:\n";
echo " - Reservasi diperiksa : {$hasil['diperiksa']}\n";
echo " - Ditutup kedaluwarsa : {$hasil['kedaluwarsa']}\n";
echo " - Dilewati / aktif    : {$hasil['dilewati']}\n";
if (!empty($hasil['ids'])) {
    echo " - ID yang ditutup     : " . implode(', ', $hasil['ids']) . "\n";
}
echo "[" . date('Y-m-d H:i:s') . " WIB] Selesai.\n";
