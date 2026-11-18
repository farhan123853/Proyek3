<?php
/**
 * API: Data Laporan Pemasukan untuk Dashboard Pemilik & Admin
 * Hanya membaca data (Read-Only)
 * Hak Akses: Pemilik & Admin
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/laporan.php';

// 1. Hanya menerima GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'pesan' => 'Method not allowed'], 405);
}

// 2. Proteksi hak akses
if (!is_logged_in() || !in_array(current_role(), ['pemilik', 'admin'], true)) {
    json_response(['ok' => false, 'pesan' => t('payment.unauthorized')], 403);
}

$tahunSekarang = (int)date('Y');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : $tahunSekarang;
if ($tahun < 2020 || $tahun > 2100) {
    $tahun = $tahunSekarang;
}

$allowedMetode = ['all', 'transfer_bank', 'e_wallet', 'tunai', 'lainnya'];
$metode = trim($_GET['metode'] ?? 'all');
if (!in_array($metode, $allowedMetode, true)) {
    $metode = 'all';
}

$pdo = get_db();

// 1. Ringkasan 4 Kartu Eksekutif
$ringkasan = ringkasan_pemasukan($tahun, $metode);

// 2. Data Bulanan 12 Bulan (untuk Grafik Batang & Tabel)
$bulanan = pemasukan_bulanan($tahun, $metode);

// 3. Data Per Tipe Villa
$perTipe = pemasukan_per_tipe($tahun);

// 4. Daftar Tahun Laporan
$daftarTahun = daftar_tahun_laporan();

// 5. Estimasi Nilai Reservasi Terkonfirmasi (Terpisah dari uang masuk aktual)
$stmtEst = $pdo->query("
    SELECT 
        COUNT(*) AS total_terkonfirmasi,
        COALESCE(SUM(estimasi_total), 0) AS total_estimasi
    FROM reservasi 
    WHERE status = 'dikonfirmasi'
");
$estimasi = $stmtEst->fetch();

// Format label nama bulan untuk mempermudah render klien
$namaBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$namaBulanEn = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

$bulananFormatted = [];
foreach ($bulanan as $m => $b) {
    $b['nama_bulan_id'] = $namaBulan[$m];
    $b['nama_bulan_en'] = $namaBulanEn[$m];
    $bulananFormatted[] = $b;
}

json_response([
    'ok' => true,
    'tahun' => $tahun,
    'metode' => $metode,
    'ringkasan' => $ringkasan,
    'bulanan' => $bulananFormatted,
    'per_tipe' => $perTipe,
    'daftar_tahun' => $daftarTahun,
    'estimasi_terkonfirmasi' => [
        'total_terkonfirmasi' => (int)($estimasi['total_terkonfirmasi'] ?? 0),
        'total_estimasi'      => (int)($estimasi['total_estimasi'] ?? 0),
    ]
]);
