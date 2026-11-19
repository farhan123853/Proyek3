<?php
/**
 * API: Cek Ketersediaan Tanggal & Hitung Tarif — Villa Joglo Masayu
 * Kebutuhan: F-06, F-07, F-08, F-16, Tahap 11 (mode kalender)
 *
 * Mode Kalender (GET ?mode=kalender&tipe=N&dari=YYYY-MM-DD&sampai=YYYY-MM-DD):
 *   - Publik (tanpa login), hanya GET, cache 30 detik.
 *   - Respons hanya berisi status per tanggal — TANPA data pribadi tamu.
 *   - Rate-limit 60 req/menit per sesi (sederhana, cukup untuk publik).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

// ─── MODE KALENDER ───────────────────────────────────────────────────────────
if (($_GET['mode'] ?? '') === 'kalender') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'pesan' => 'Hanya GET yang diizinkan.']);
        exit;
    }

    // Rate limiting per sesi (60 req / 60 detik)
    $rlKey = 'rl_kalender';
    $now   = time();
    if (!isset($_SESSION[$rlKey])) {
        $_SESSION[$rlKey] = ['count' => 0, 'window_start' => $now];
    }
    if ($now - $_SESSION[$rlKey]['window_start'] > 60) {
        $_SESSION[$rlKey] = ['count' => 0, 'window_start' => $now];
    }
    $_SESSION[$rlKey]['count']++;
    if ($_SESSION[$rlKey]['count'] > 60) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'pesan' => 'Terlalu banyak permintaan. Coba lagi dalam satu menit.']);
        exit;
    }

    // Cache pendek — dapat di-cache oleh browser/CDN 30 detik
    header('Cache-Control: public, max-age=30');

    $idTipe = (int)($_GET['tipe'] ?? 0);
    $dari   = trim($_GET['dari'] ?? '');
    $sampai = trim($_GET['sampai'] ?? '');

    // Validasi parameter
    if (!$idTipe || !$dari || !$sampai) {
        json_response(['ok' => false, 'pesan' => 'Parameter tipe, dari, dan sampai wajib diisi.'], 400);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) {
        json_response(['ok' => false, 'pesan' => 'Format tanggal harus YYYY-MM-DD.'], 400);
    }
    if ($sampai <= $dari) {
        json_response(['ok' => false, 'pesan' => 'Tanggal sampai harus setelah tanggal dari.'], 400);
    }
    // Maksimal 400 hari
    $diffDays = (strtotime($sampai) - strtotime($dari)) / 86400;
    if ($diffDays > 400) {
        json_response(['ok' => false, 'pesan' => 'Rentang tanggal maksimal 400 hari.'], 400);
    }

    $pdo = get_db();

    // Pastikan tipe villa ada
    $stmtTipe = $pdo->prepare("SELECT id_tipe FROM tipe_villa WHERE id_tipe = ? LIMIT 1");
    $stmtTipe->execute([$idTipe]);
    if (!$stmtTipe->fetch()) {
        json_response(['ok' => false, 'pesan' => 'Tipe villa tidak ditemukan.'], 404);
    }

    $ketersediaan = hitung_ketersediaan($idTipe, $dari, $sampai, $pdo);

    // Hanya status per tanggal — TANPA nama, id reservasi, kontak, atau data tamu
    $data = [];
    foreach ($ketersediaan['malam'] as $date => $info) {
        $data[$date] = $info['status']; // 'tersedia' | 'menunggu' | 'penuh'
    }

    json_response(['ok' => true, 'unit_total' => $ketersediaan['unit_total'], 'data' => $data]);
}
// ─── END MODE KALENDER ───────────────────────────────────────────────────────

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

// 4. Periksa Ketersediaan menggunakan hitung_ketersediaan() — satu sumber kebenaran
$ketersediaanLegacy = hitung_ketersediaan($idTipe, $checkin, $checkout, $pdo);
$jumlahUnit = $ketersediaanLegacy['unit_total'];

// Ambil jumlah terpakai maksimum dalam rentang (untuk kompatibilitas respons lama)
$overlap  = 0;
$tersedia = true;
foreach ($ketersediaanLegacy['malam'] as $malam) {
    $terpakai = $malam['terkonfirmasi'] + $malam['diblokir'];
    if ($terpakai > $overlap) $overlap = $terpakai;
    if ($malam['status'] === 'penuh') {
        $tersedia = false;
    }
}

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

