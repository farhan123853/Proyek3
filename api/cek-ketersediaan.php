<?php
/**
 * API: Cek Ketersediaan Tanggal & Hitung Tarif — Villa Joglo Masayu
 * Kebutuhan: F-06, F-07, F-08, F-16
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

$idTipe     = (int)($_REQUEST['id_tipe'] ?? 0);
$checkin    = trim($_REQUEST['checkin'] ?? '');
$checkout   = trim($_REQUEST['checkout'] ?? '');
$jumlahTamu = (int)($_REQUEST['jumlah_tamu'] ?? 1);

if (!$idTipe || empty($checkin) || empty($checkout)) {
    json_response([
        'tersedia' => false,
        'pesan'    => 'Parameter id_tipe, checkin, dan checkout wajib diisi.'
    ], 400);
}

// 1. Validasi Tanggal
$today = date('Y-m-d');
if ($checkin < $today) {
    json_response([
        'tersedia' => false,
        'pesan'    => 'Tanggal check-in tidak boleh di masa lampau.'
    ], 422);
}

if ($checkout <= $checkin) {
    json_response([
        'tersedia' => false,
        'pesan'    => 'Tanggal check-out harus lebih besar dari tanggal check-in (minimal 1 malam).'
    ], 422);
}

$pdo = get_db();

// 2. Ambil data tipe villa
$stmtVilla = $pdo->prepare("SELECT * FROM `tipe_villa` WHERE `id_tipe` = ? LIMIT 1");
$stmtVilla->execute([$idTipe]);
$villa = $stmtVilla->fetch();

if (!$villa) {
    json_response([
        'tersedia' => false,
        'pesan'    => 'Tipe villa yang dipilih tidak ditemukan.'
    ], 404);
}

// 3. Validasi Kapasitas
if ($jumlahTamu > (int)$villa['kapasitas_maks']) {
    json_response([
        'tersedia' => false,
        'pesan'    => "Jumlah tamu melebihi kapasitas maksimum villa {$villa['nama']} (maks. {$villa['kapasitas_maks']} tamu)."
    ], 422);
}

if ($jumlahTamu < 1) {
    json_response([
        'tersedia' => false,
        'pesan'    => 'Jumlah tamu minimal 1 orang.'
    ], 422);
}

// 4. Periksa Ketersediaan Unit Berdasarkan Reservasi Berstatus "dikonfirmasi"
// Rumus tumpang tindih: (checkin_baru < checkout_lama) AND (checkout_baru > checkin_lama)
$sqlOverlap = "SELECT COUNT(*) as `total_terpesan` 
               FROM `reservasi` 
               WHERE `id_tipe` = :id_tipe 
                 AND `status` = 'dikonfirmasi' 
                 AND `tanggal_checkin` < :checkout 
                 AND `tanggal_checkout` > :checkin";

$stmtCheck = $pdo->prepare($sqlOverlap);
$stmtCheck->execute([
    'id_tipe'  => $idTipe,
    'checkout' => $checkout,
    'checkin'  => $checkin
]);
$overlap = (int)$stmtCheck->fetchColumn();
$jumlahUnit = (int)$villa['jumlah_unit'];

$tersedia = ($overlap < $jumlahUnit);

// 5. Hitung Rincian Tarif & Estimasi Nilai
$rincian = hitung_rincian_tarif(
    $checkin,
    $checkout,
    $jumlahTamu,
    (int)$villa['kapasitas_standar'],
    (int)$villa['kapasitas_maks'],
    (float)$villa['harga_weekday'],
    (float)$villa['harga_weekend'],
    (float)$villa['harga_extrabed']
);

if (!$tersedia) {
    json_response([
        'tersedia' => false,
        'pesan'    => "Maaf, seluruh unit untuk {$villa['nama']} sudah terpesan pada rentang tanggal tersebut.",
        'terpesan' => $overlap,
        'unit'     => $jumlahUnit,
        'rincian'  => $rincian
    ]);
}

json_response([
    'tersedia' => true,
    'pesan'    => "Unit {$villa['nama']} tersedia pada tanggal tersebut.",
    'terpesan' => $overlap,
    'unit'     => $jumlahUnit,
    'rincian'  => $rincian
]);

