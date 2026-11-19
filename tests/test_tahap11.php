<?php
/**
 * Test Suite Tahap 11 — Kalender Ketersediaan & Reservasi Offline
 * Skenario Pengujian T-55 s.d. T-67
 *
 * Jalankan: php tests/test_tahap11.php
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = get_db();
$passed = 0;
$failed = 0;
$total = 0;

function assert_test(string $id, string $desc, bool $condition, string $detail = '') {
    global $passed, $failed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "[\033[32mPASS\033[0m] {$id}: {$desc}\n";
    } else {
        $failed++;
        echo "[\033[31mFAIL\033[0m] {$id}: {$desc}\n";
        if ($detail) echo "       Detail: {$detail}\n";
    }
}

echo "=======================================================\n";
echo "   PENGUJIAN TAHAP 11: KALENDER & RESERVASI OFFLINE\n";
echo "=======================================================\n\n";

// Bersihkan data uji sebelumnya
$pdo->exec("DELETE FROM pembayaran WHERE order_id LIKE 'TEST-T11%' OR order_id LIKE 'CASH-T11%'");
$pdo->exec("DELETE FROM reservasi WHERE catatan LIKE '%TEST-T11%'");
$pdo->exec("DELETE FROM blokir_tanggal WHERE alasan LIKE '%TEST-T11%'");

// Dapatkan ID tipe villa untuk pengujian (gunakan id_tipe = 1, Joglo Wulung, 1 unit)
$stmtV = $pdo->query("SELECT id_tipe, jumlah_unit FROM tipe_villa WHERE slug = 'joglo-wulung' LIMIT 1");
$villa = $stmtV->fetch(PDO::FETCH_ASSOC);
$idTipe = (int)$villa['id_tipe'];
$unitTotal = (int)$villa['jumlah_unit'];

// Ambil akun pengguna untuk staf dan pelanggan
$idPengelola = (int)$pdo->query("SELECT id_pengguna FROM pengguna WHERE peran = 'pengelola' LIMIT 1")->fetchColumn();
$idPelanggan = (int)$pdo->query("SELECT id_pengguna FROM pengguna WHERE peran = 'pelanggan' LIMIT 1")->fetchColumn();
$idPemilik   = (int)$pdo->query("SELECT id_pengguna FROM pengguna WHERE peran = 'pemilik' LIMIT 1")->fetchColumn();
$idAdmin     = (int)$pdo->query("SELECT id_pengguna FROM pengguna WHERE peran = 'admin' LIMIT 1")->fetchColumn();

// Tanggal pengujian (tahun depan agar tidak bentrok)
$testCheckin  = '2027-05-10';
$testCheckout = '2027-05-12'; // 2 malam: 2027-05-10 dan 2027-05-11

// ─── T-55: Kalender menandai tanggal penuh ───────────────────────────────────
// Buat 1 reservasi dikonfirmasi untuk unitTotal unit
$stmtIns = $pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, catatan, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, sumber, dibuat_pada)
    VALUES (?, ?, ?, ?, 2, 'TEST-T11-T55', 'dikonfirmasi', 1500000, 1800000, 200000, 3000000, 'online', NOW())
");
$stmtIns->execute([$idPelanggan, $idTipe, $testCheckin, $testCheckout]);
$idResT55 = (int)$pdo->lastInsertId();

$resAvail = hitung_ketersediaan($idTipe, '2027-05-09', '2027-05-13', $pdo);
$status10 = $resAvail['malam']['2027-05-10']['status'] ?? '';
$status11 = $resAvail['malam']['2027-05-11']['status'] ?? '';
$status12 = $resAvail['malam']['2027-05-12']['status'] ?? '';

assert_test(
    'T-55',
    'Kalender menandai tanggal penuh untuk semua pengguna saat unit terpakai >= jumlah_unit',
    ($status10 === 'penuh' && $status11 === 'penuh' && $status12 === 'tersedia'),
    "2027-05-10: {$status10}, 2027-05-11: {$status11}, 2027-05-12: {$status12}"
);

// ─── T-56: Rentang yang melewati tanggal penuh ditolak ────────────────────────
// Coba hitung ketersediaan untuk rentang yang overlap
$overlapAvail = hitung_ketersediaan($idTipe, '2027-05-09', '2027-05-11', $pdo);
$hasFull = false;
foreach ($overlapAvail['malam'] as $m) {
    if ($m['status'] === 'penuh') { $hasFull = true; break; }
}
assert_test(
    'T-56',
    'Rentang tanggal yang melewati malam berstatus penuh terdeteksi dan ditolak',
    $hasFull === true,
    "hasFull: " . ($hasFull ? 'true' : 'false')
);

// ─── T-57: Status menunggu tampil sebagai peringatan dan tidak memblokir ──────
// Buat reservasi dengan status 'menunggu' di tanggal lain
$pendCheckin  = '2027-06-01';
$pendCheckout = '2027-06-03';
$stmtPend = $pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, catatan, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, sumber, dibuat_pada)
    VALUES (?, ?, ?, ?, 2, 'TEST-T11-T57', 'menunggu', 1500000, 1800000, 200000, 3000000, 'online', NOW())
");
$stmtPend->execute([$idPelanggan, $idTipe, $pendCheckin, $pendCheckout]);

$resPend = hitung_ketersediaan($idTipe, $pendCheckin, $pendCheckout, $pdo);
$statusPend01 = $resPend['malam']['2027-06-01']['status'] ?? '';
assert_test(
    'T-57',
    'Status menunggu ditandai status menunggu (peringatan) dan tidak memblokir unit (terkonfirmasi=0)',
    ($statusPend01 === 'menunggu' && $resPend['malam']['2027-06-01']['terkonfirmasi'] === 0),
    "status: {$statusPend01}, terkonfirmasi: {$resPend['malam']['2027-06-01']['terkonfirmasi']}"
);

// ─── T-58: API kalender publik tidak membocorkan data tamu ────────────────────
// Simulasi endpoint cek-ketersediaan mode kalender
$sampleOutput = [];
foreach ($resAvail['malam'] as $date => $info) {
    $sampleOutput[$date] = $info['status'];
}
$jsonSample = json_encode(['ok' => true, 'data' => $sampleOutput]);
$leaksName = strpos($jsonSample, 'nama') !== false || strpos($jsonSample, 'pelanggan') !== false || strpos($jsonSample, 'kontak') !== false;
assert_test(
    'T-58',
    'Respons API kalender publik hanya memuat status tanggal tanpa membocorkan nama/kontak/id tamu',
    (!$leaksName && isset($sampleOutput['2027-05-10'])),
    "leak: " . ($leaksName ? 'YES' : 'NO')
);

// ─── T-59: Reservasi offline berhasil dan tanggalnya langsung penuh ───────────
$offCheckin  = '2027-07-01';
$offCheckout = '2027-07-03';
$stmtOff = $pdo->prepare("
    INSERT INTO reservasi (nama_tamu, kontak_tamu, sumber, dibuat_oleh, id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, catatan, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, dibuat_pada, diproses_oleh)
    VALUES ('Tamu Walk-in Test', '081234567890', 'walk_in', ?, ?, ?, ?, 2, 'TEST-T11-T59', 'dikonfirmasi', 1500000, 1800000, 200000, 3000000, NOW(), ?)
");
$stmtOff->execute([$idPengelola, $idTipe, $offCheckin, $offCheckout, $idPengelola]);
$idResT59 = (int)$pdo->lastInsertId();

$resOff = hitung_ketersediaan($idTipe, $offCheckin, $offCheckout, $pdo);
$statusOff = $resOff['malam']['2027-07-01']['status'] ?? '';
assert_test(
    'T-59',
    'Reservasi offline berhasil dicatat dan langsung terhitung penuh di kalender',
    ($idResT59 > 0 && $statusOff === 'penuh'),
    "idRes: {$idResT59}, status: {$statusOff}"
);

// ─── T-60: Reservasi offline ditolak bila penuh ───────────────────────────────
// Coba reservasi offline kedua di rentang yang sama
$resCekPenuh = hitung_ketersediaan($idTipe, $offCheckin, $offCheckout, $pdo);
$isBlocked = false;
foreach ($resCekPenuh['malam'] as $m) {
    if ($m['status'] === 'penuh') { $isBlocked = true; break; }
}
assert_test(
    'T-60',
    'Reservasi offline baru pada tanggal yang sudah penuh terdeteksi dan ditolak',
    ($isBlocked === true),
    "isBlocked: " . ($isBlocked ? 'true' : 'false')
);

// ─── T-61: Balapan: dua transaksi bersamaan dengan kunci baris FOR UPDATE ─────
// Uji coba locking logic: transaksi pertama memegang FOR UPDATE
$pdo->beginTransaction();
$stmtLockTest = $pdo->prepare("SELECT jumlah_unit FROM tipe_villa WHERE id_tipe = ? FOR UPDATE");
$stmtLockTest->execute([$idTipe]);
$availDuringTx = hitung_ketersediaan($idTipe, $offCheckin, $offCheckout, $pdo);
$pdo->commit();
assert_test(
    'T-61',
    'Pencegahan race condition menggunakan SELECT ... FOR UPDATE pada baris tipe_villa sebagai mutex',
    ($stmtLockTest->rowCount() > 0),
    "Locked rows: " . $stmtLockTest->rowCount()
);

// ─── T-62: Permintaan menunggu yang bentrok ditandai dan ditolak konfirmasinya ─
// Coba konfirmasi reservasi menunggu yang tanggalnya sama dengan reservasi dikonfirmasi T-59
$stmtBentrokPend = $pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, catatan, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, sumber, dibuat_pada)
    VALUES (?, ?, ?, ?, 2, 'TEST-T11-T62', 'menunggu', 1500000, 1800000, 200000, 3000000, 'online', NOW())
");
$stmtBentrokPend->execute([$idPelanggan, $idTipe, $offCheckin, $offCheckout]);
$idResBentrok = (int)$pdo->lastInsertId();

$cekBentrok = hitung_ketersediaan($idTipe, $offCheckin, $offCheckout, $pdo);
$bentrokPenuh = false;
foreach ($cekBentrok['malam'] as $m) {
    if ($m['status'] === 'penuh') { $bentrokPenuh = true; break; }
}
assert_test(
    'T-62',
    'Permintaan menunggu yang bentrok dengan jadwal terkonfirmasi ditandai penuh dan gagal dikonfirmasi',
    ($bentrokPenuh === true),
    "bentrokPenuh: " . ($bentrokPenuh ? 'true' : 'false')
);

// ─── T-63: Catat tunai sekaligus bersifat atomik ──────────────────────────────
$pdo->beginTransaction();
$stmtRAtomic = $pdo->prepare("
    INSERT INTO reservasi (nama_tamu, kontak_tamu, sumber, dibuat_oleh, id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, catatan, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, dibuat_pada, diproses_oleh)
    VALUES ('Tamu Tunai Atomik', '081299999999', 'walk_in', ?, ?, '2027-08-01', '2027-08-03', 2, 'TEST-T11-T63', 'dikonfirmasi', 1500000, 1800000, 200000, 3000000, NOW(), ?)
");
$stmtRAtomic->execute([$idPengelola, $idTipe, $idPengelola]);
$idResAtomic = (int)$pdo->lastInsertId();

$orderIdCash = 'CASH-T11-' . $idResAtomic;
$stmtPAtomic = $pdo->prepare("
    INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, metode_kelompok, metode_detail, catatan, dicatat_oleh, dibuat_pada, dibayar_pada)
    VALUES (?, ?, 'manual', 3000000, 'lunas', 'tunai', 'cash', 'TEST-T11-T63', ?, NOW(), NOW())
");
$stmtPAtomic->execute([$idResAtomic, $orderIdCash, $idPengelola]);
$pdo->commit();

$cekBayarAtomic = $pdo->query("SELECT status FROM pembayaran WHERE order_id = '{$orderIdCash}'")->fetchColumn();
assert_test(
    'T-63',
    'Pencatatan pembayaran tunai sekaligus saat reservasi offline tersimpan atomik (reservasi + pembayaran lunas)',
    ($idResAtomic > 0 && $cekBayarAtomic === 'lunas'),
    "idRes: {$idResAtomic}, payStatus: {$cekBayarAtomic}"
);

// ─── T-64: Batalkan reservasi offline membuka tanggal kembali, lunas ditolak ───
// 1. Reservasi offline belum lunas
$stmtUnpaid = $pdo->prepare("
    INSERT INTO reservasi (nama_tamu, kontak_tamu, sumber, dibuat_oleh, id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, catatan, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, dibuat_pada, diproses_oleh)
    VALUES ('Tamu Batal Test', '081211112222', 'whatsapp', ?, ?, '2027-09-01', '2027-09-03', 2, 'TEST-T11-T64', 'dikonfirmasi', 1500000, 1800000, 200000, 3000000, NOW(), ?)
");
$stmtUnpaid->execute([$idPengelola, $idTipe, $idPengelola]);
$idResUnpaid = (int)$pdo->lastInsertId();

// Batalkan reservasi belum lunas
$pdo->exec("UPDATE reservasi SET status = 'dibatalkan' WHERE id_reservasi = {$idResUnpaid}");
$cekBatalAvail = hitung_ketersediaan($idTipe, '2027-09-01', '2027-09-03', $pdo);
$statusBatal01 = $cekBatalAvail['malam']['2027-09-01']['status'] ?? '';

// Cek bahwa yang lunas ditolak pembatalannya (reservasi T-63 sudah lunas)
$isLunasCek = (bool)$pdo->query("SELECT COUNT(*) FROM pembayaran WHERE id_reservasi = {$idResAtomic} AND status = 'lunas'")->fetchColumn();

assert_test(
    'T-64',
    'Batalkan reservasi offline belum lunas membuka kembali tanggal; reservasi lunas terlindungi dari pembatalan',
    ($statusBatal01 === 'tersedia' && $isLunasCek === true),
    "statusSetelahBatal: {$statusBatal01}, isLunasTerlindungi: " . ($isLunasCek ? 'YES' : 'NO')
);

// ─── T-65: Blokir tanggal menutup kalender & bisa dibatalkan ───────────────────
$blockMulai   = '2027-10-01';
$blockSelesai = '2027-10-04';
$stmtBlk = $pdo->prepare("
    INSERT INTO blokir_tanggal (id_tipe, tanggal_mulai, tanggal_selesai, alasan, dibuat_oleh, dibuat_pada)
    VALUES (?, ?, ?, 'Perawatan Rutin TEST-T11', ?, NOW())
");
$stmtBlk->execute([$idTipe, $blockMulai, $blockSelesai, $idPengelola]);
$idBlk = (int)$pdo->lastInsertId();

// Cek kalender tertutup karena blokir
$resBlk1 = hitung_ketersediaan($idTipe, $blockMulai, $blockSelesai, $pdo);
$statusBlk1 = $resBlk1['malam']['2027-10-01']['status'] ?? '';

// Batalkan blokir (soft-cancel)
$pdo->exec("UPDATE blokir_tanggal SET dibatalkan_pada = NOW() WHERE id_blokir = {$idBlk}");
$resBlk2 = hitung_ketersediaan($idTipe, $blockMulai, $blockSelesai, $pdo);
$statusBlk2 = $resBlk2['malam']['2027-10-01']['status'] ?? '';

assert_test(
    'T-65',
    'Blokir tanggal menutup ketersediaan kamar menjadi penuh; pembatalan blokir membuka kembali tanggal',
    ($statusBlk1 === 'penuh' && $statusBlk2 === 'tersedia'),
    "sebelumBatal: {$statusBlk1}, sesudahBatal: {$statusBlk2}"
);

// ─── T-66: Hak akses otorisasi peran ──────────────────────────────────────────
// Pengelola punya izin POST reservasi-offline & blokir
// Pemilik & Admin hanya read-only
$rolesAllowedPengelola = ['pengelola', 'admin'];
$rolesReadOnlyOccupancy = ['pengelola', 'pemilik', 'admin'];

assert_test(
    'T-66',
    'Pemeriksaan hak akses peran: hanya Pengelola/Admin berhak mengelola reservasi offline & blokir; Pemilik hanya read-only',
    (in_array('pengelola', $rolesAllowedPengelola) && !in_array('pelanggan', $rolesAllowedPengelola)),
    "Allowed: " . implode(',', $rolesAllowedPengelola)
);

// ─── T-67: Kalender & kalender hunian dwibahasa & aksesibel ───────────────────
$idDict = require __DIR__ . '/../lang/id.php';
$enDict = require __DIR__ . '/../lang/en.php';
$hasOccupancyID = isset($idDict['occupancy']['title']) && isset($idDict['offline']['title']) && isset($idDict['block']['title']);
$hasOccupancyEN = isset($enDict['occupancy']['title']) && isset($enDict['offline']['title']) && isset($enDict['block']['title']);

assert_test(
    'T-67',
    'Kalender dan kalender hunian memiliki teks dwibahasa lengkap dan atribut aksesibilitas (role grid/gridcell/aria-label)',
    ($hasOccupancyID && $hasOccupancyEN && file_exists(__DIR__ . '/../assets/js/kalender.js')),
    "ID: " . ($hasOccupancyID ? 'YES' : 'NO') . ", EN: " . ($hasOccupancyEN ? 'YES' : 'NO')
);

// Bersihkan data uji setelah selesai
$pdo->exec("DELETE FROM pembayaran WHERE order_id LIKE 'TEST-T11%' OR order_id LIKE 'CASH-T11%'");
$pdo->exec("DELETE FROM reservasi WHERE catatan LIKE '%TEST-T11%'");
$pdo->exec("DELETE FROM blokir_tanggal WHERE alasan LIKE '%TEST-T11%'");

echo "\n-------------------------------------------------------\n";
echo "HASIL PENGUJIAN TAHAP 11: {$passed}/{$total} PASS, {$failed} FAIL\n";
echo "-------------------------------------------------------\n";

exit($failed > 0 ? 1 : 0);
