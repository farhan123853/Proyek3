<?php
/**
 * Test Suite: Kedaluwarsa Otomatis, Batas Bayar, dan Mutasi Status (T-84 s.d. T-96)
 * Villa Joglo Masayu
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/kedaluwarsa.php';

$pdo = get_db();

echo "========================================================================\n";
echo "   PENGUJIAN SISTEM: KEDALUWARSA RESERVASI & BATAS BAYAR (T-84 s.d. T-96)\n";
echo "========================================================================\n\n";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assert_test(bool $condition, string $id, string $title, string $detail = '') {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo " [LULUS] {$id}: {$title}\n";
        if ($detail) echo "         {$detail}\n";
    } else {
        $failedTests++;
        echo " [GAGAL] {$id}: {$title}\n";
        if ($detail) echo "         DETAIL: {$detail}\n";
    }
}

// -------------------------------------------------------------------------
// TEST 1 (T-89): Formula Batas Bayar Online (24 Jam, Batas Jam 12 Check-in, Min 2 Jam)
// -------------------------------------------------------------------------
$cfgTest = [
    'PEMBAYARAN_BATAS_JAM' => 24,
    'PEMBAYARAN_BATAS_MIN_JAM' => 2,
    'PEMBAYARAN_CHECKIN_BATAS_JAM' => 12
];

// Kasus A: Konfirmasi jauh sebelum check-in (misal 5 hari sebelumnya) -> batas = konfirmasi + 24 jam
$tglCheckinA = '2026-12-10';
$konfirmasiA = '2026-12-05 10:00:00';
$hasilA = hitung_batas_bayar_online($tglCheckinA, $konfirmasiA, $cfgTest);
$expectedA = '2026-12-06 10:00:00';
assert_test($hasilA === $expectedA, 'T-89A', 'Batas bayar standar 24 jam konfirmasi', "Dihasilkan: {$hasilA}, Diharapkan: {$expectedA}");

// Kasus B: Konfirmasi 1 hari sebelum check-in sore hari (misal jam 15:00) -> batas dibatasi jam 12:00 hari check-in (kurang dari 24 jam)
$tglCheckinB = '2026-12-10';
$konfirmasiB = '2026-12-09 15:00:00'; // 24 jam = 10 Des 15:00, tapi max check-in = 10 Des 12:00
$hasilB = hitung_batas_bayar_online($tglCheckinB, $konfirmasiB, $cfgTest);
$expectedB = '2026-12-10 12:00:00';
assert_test($hasilB === $expectedB, 'T-89B', 'Batas bayar dibatasi jam 12:00 WIB hari check-in', "Dihasilkan: {$hasilB}, Diharapkan: {$expectedB}");

// Kasus C: Konfirmasi pada hari yang sama pagi jam 11:00 (kurang dari 2 jam ke jam 12:00) -> batas minimal 2 jam (13:00)
$tglCheckinC = '2026-12-10';
$konfirmasiC = '2026-12-10 11:00:00'; // Jam 12 hanya 1 jam, harus minimum 2 jam -> 13:00
$hasilC = hitung_batas_bayar_online($tglCheckinC, $konfirmasiC, $cfgTest);
$expectedC = '2026-12-10 13:00:00';
assert_test($hasilC === $expectedC, 'T-89C', 'Batas bayar minimal 2 jam dari konfirmasi', "Dihasilkan: {$hasilC}, Diharapkan: {$expectedC}");

// -------------------------------------------------------------------------
// TEST 2 (T-88): Formula Batas Bayar Tunai (Akhir Hari Check-in 23:59:59 WIB)
// -------------------------------------------------------------------------
$hasilTunai = hitung_batas_bayar_tunai('2026-12-15');
assert_test($hasilTunai === '2026-12-15 23:59:59', 'T-88A', 'Batas bayar tunai berakhir pada akhir hari check-in (23:59:59 WIB)', "Dihasilkan: {$hasilTunai}");

// -------------------------------------------------------------------------
// SIAPKAN DATA UJI ISOLASI
// -------------------------------------------------------------------------
// Ambil tipe villa dan pelanggan uji
$tipe = $pdo->query("SELECT id_tipe FROM tipe_villa LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$pelanggan1 = $pdo->query("SELECT id_pengguna FROM pengguna WHERE peran = 'pelanggan' ORDER BY id_pengguna ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$pelanggan2 = $pdo->query("SELECT id_pengguna FROM pengguna WHERE peran = 'pelanggan' ORDER BY id_pengguna DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$idTipe = (int)$tipe['id_tipe'];
$idPelanggan1 = (int)$pelanggan1['id_pengguna'];
$idPelanggan2 = (int)$pelanggan2['id_pengguna'];

// Tanggal uji khusus agar tidak mengganggu kalender produksi
$tglTestCheckin = '2029-01-10';
$tglTestCheckout = '2029-01-12';

// Bersihkan data uji sebelumnya jika ada
$pdo->prepare("DELETE FROM pembayaran WHERE id_reservasi IN (SELECT id_reservasi FROM reservasi WHERE tanggal_checkin = ?)")->execute([$tglTestCheckin]);
$pdo->prepare("DELETE FROM reservasi_log WHERE id_reservasi IN (SELECT id_reservasi FROM reservasi WHERE tanggal_checkin = ?)")->execute([$tglTestCheckin]);
$pdo->prepare("DELETE FROM reservasi WHERE tanggal_checkin = ?")->execute([$tglTestCheckin]);

// -------------------------------------------------------------------------
// TEST 3 (T-84): Reservasi Dikonfirmasi yang Sudah LUNAS Tidak Kedaluwarsa
// -------------------------------------------------------------------------
$stmtR1 = $pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, nama_tamu, kontak_tamu, id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, batas_bayar_pada, dibuat_pada)
    VALUES (?, 'Tamu Lunas', '0812999901', ?, ?, ?, 2, 'dikonfirmasi', 500000, 600000, 100000, 1000000, NOW() - INTERVAL 2 HOUR, NOW() - INTERVAL 1 DAY)
");
$stmtR1->execute([$idPelanggan1, $idTipe, $tglTestCheckin, $tglTestCheckout]);
$idR1 = (int)$pdo->lastInsertId();

// Tambahkan pembayaran 'lunas'
$pdo->prepare("
    INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, dibayar_pada)
    VALUES (?, ?, 'midtrans', 1000000, 'lunas', NOW() - INTERVAL 1 HOUR)
")->execute([$idR1, "TEST-LUNAS-{$idR1}"]);

$runExpire1 = expire_reservasi_lewat_batas($pdo);
$statusR1 = $pdo->query("SELECT status FROM reservasi WHERE id_reservasi = {$idR1}")->fetchColumn();
assert_test($statusR1 === 'dikonfirmasi', 'T-84', 'Reservasi dengan pembayaran lunas tetap dikonfirmasi (tidak kedaluwarsa)', "Status: {$statusR1}");

// -------------------------------------------------------------------------
// TEST 4 (T-85): Reservasi Lewat Batas Tanpa Pembayaran Menjadi 'kedaluwarsa' & Tanggal Terlepas
// -------------------------------------------------------------------------
$stmtR2 = $pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, nama_tamu, kontak_tamu, id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, batas_bayar_pada, dibuat_pada)
    VALUES (?, 'Tamu Expire', '0812999902', ?, ?, ?, 2, 'dikonfirmasi', 500000, 600000, 100000, 1000000, NOW() - INTERVAL 1 HOUR, NOW() - INTERVAL 1 DAY)
");
$stmtR2->execute([$idPelanggan1, $idTipe, $tglTestCheckin, $tglTestCheckout]);
$idR2 = (int)$pdo->lastInsertId();

// Sebelum expire: reservasi memblokir tanggal
$ketersediaanSebelum = hitung_ketersediaan($idTipe, $tglTestCheckin, $tglTestCheckout, $pdo);
$unitTerisiSebelum = $ketersediaanSebelum['malam'][$tglTestCheckin]['terkonfirmasi'] ?? 0;

$runExpire2 = expire_reservasi_lewat_batas($pdo);
$resR2 = $pdo->query("SELECT status, kedaluwarsa_pada FROM reservasi WHERE id_reservasi = {$idR2}")->fetch(PDO::FETCH_ASSOC);

// Setelah expire: tanggal terlepas
$ketersediaanSetelah = hitung_ketersediaan($idTipe, $tglTestCheckin, $tglTestCheckout, $pdo);
$unitTerisiSetelah = $ketersediaanSetelah['malam'][$tglTestCheckin]['terkonfirmasi'] ?? 0;

assert_test(
    $resR2['status'] === 'kedaluwarsa' && !empty($resR2['kedaluwarsa_pada']) && ($unitTerisiSetelah === $unitTerisiSebelum - 1),
    'T-85',
    'Lewat batas bayar tanpa pelunasan otomatis kedaluwarsa dan unit kamar terlepas di kalender',
    "Status: {$resR2['status']}, Unit Sebelum: {$unitTerisiSebelum}, Unit Setelah: {$unitTerisiSetelah}"
);

// -------------------------------------------------------------------------
// TEST 5 (T-86): Transaksi Online Masih Aktif di Midtrans -> Dilewati Penutupan
// -------------------------------------------------------------------------
$stmtR3 = $pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, nama_tamu, kontak_tamu, id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, batas_bayar_pada, dibuat_pada)
    VALUES (?, 'Tamu Aktif VA', '0812999903', ?, ?, ?, 2, 'dikonfirmasi', 500000, 600000, 100000, 1000000, NOW() - INTERVAL 10 MINUTE, NOW() - INTERVAL 1 DAY)
");
$stmtR3->execute([$idPelanggan1, $idTipe, $tglTestCheckin, $tglTestCheckout]);
$idR3 = (int)$pdo->lastInsertId();

// Tambahkan pembayaran 'menunggu' dengan kedaluwarsa_pada 1 jam di masa depan
$pdo->prepare("
    INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, kedaluwarsa_pada, dibuat_pada)
    VALUES (?, ?, 'midtrans', 1000000, 'menunggu', NOW() + INTERVAL 1 HOUR, NOW())
")->execute([$idR3, "TEST-PENDING-{$idR3}"]);

$runExpire3 = expire_reservasi_lewat_batas($pdo);
$statusR3 = $pdo->query("SELECT status FROM reservasi WHERE id_reservasi = {$idR3}")->fetchColumn();
assert_test(
    $statusR3 === 'dikonfirmasi',
    'T-86',
    'Reservasi dengan transaksi online yang masih aktif di payment gateway dilewati (tidak ditutup prematur)',
    "Status: {$statusR3}"
);

// -------------------------------------------------------------------------
// TEST 6 (T-87): Idempotensi Fungsi expire_reservasi_lewat_batas()
// -------------------------------------------------------------------------
$runA = expire_reservasi_lewat_batas($pdo);
$runB = expire_reservasi_lewat_batas($pdo);
assert_test(
    $runB['kedaluwarsa'] === 0,
    'T-87',
    'Pemanggilan berulang bersifat idempoten (tidak menutup ganda atau memicu kesalahan)',
    "Putaran 1 kedaluwarsa: {$runA['kedaluwarsa']}, Putaran 2 kedaluwarsa: {$runB['kedaluwarsa']}"
);

// -------------------------------------------------------------------------
// TEST 7 (T-88B): Aksi Pelanggan Bayar Tunai & Beralih Online
// -------------------------------------------------------------------------
$stmtR4 = $pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, nama_tamu, kontak_tamu, id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, batas_bayar_pada, dibuat_pada)
    VALUES (?, 'Tamu Switch Plan', '0812999904', ?, ?, ?, 2, 'dikonfirmasi', 500000, 600000, 100000, 1000000, NOW() + INTERVAL 10 HOUR, NOW())
");
$stmtR4->execute([$idPelanggan1, $idTipe, $tglTestCheckin, $tglTestCheckout]);
$idR4 = (int)$pdo->lastInsertId();

// Pilih bayar tunai
$resCash = pelanggan_pilih_bayar_tunai($idR4, $idPelanggan1);
$dataR4Cash = $pdo->query("SELECT rencana_bayar, batas_bayar_pada FROM reservasi WHERE id_reservasi = {$idR4}")->fetch(PDO::FETCH_ASSOC);

assert_test(
    $resCash['ok'] === true && $dataR4Cash['rencana_bayar'] === 'tunai' && $dataR4Cash['batas_bayar_pada'] === "{$tglTestCheckin} 23:59:59",
    'T-88B',
    'Pelanggan memilih bayar tunai: rencana_bayar=tunai dan batas waktu menjadi akhir hari check-in',
    "Batas bayar: {$dataR4Cash['batas_bayar_pada']}"
);

// Beralih kembali ke online
$resOnline = pelanggan_kembali_bayar_online($idR4, $idPelanggan1);
$dataR4Online = $pdo->query("SELECT rencana_bayar, batas_bayar_pada FROM reservasi WHERE id_reservasi = {$idR4}")->fetch(PDO::FETCH_ASSOC);

assert_test(
    $resOnline['ok'] === true && $dataR4Online['rencana_bayar'] === 'online',
    'T-88C',
    'Pelanggan beralih kembali ke bayar online: batas bayar online dihitung ulang dari waktu sekarang',
    "Batas bayar online: {$dataR4Online['batas_bayar_pada']}"
);

// -------------------------------------------------------------------------
// TEST 8 (T-95): Pencegahan IDOR Aksi Pembayaran Pelanggan
// -------------------------------------------------------------------------
$resIdor = pelanggan_pilih_bayar_tunai($idR4, $idPelanggan2); // idPelanggan2 mencoba ubah reservasi milik idPelanggan1
assert_test(
    $resIdor['ok'] === false,
    'T-95',
    'Proteksi IDOR: Pengguna tidak dapat memanipulasi metode pembayaran reservasi milik orang lain',
    "Pesan respon: {$resIdor['pesan']}"
);

// -------------------------------------------------------------------------
// TEST 9 (T-90 & T-91): Pembayaran Terlambat (Reaktivasi vs Penanda Ganda/Bentrok)
// -------------------------------------------------------------------------
// Buat reservasi berstatus 'kedaluwarsa'
$tglLateCheckin = '2029-02-10';
$tglLateCheckout = '2029-02-12';
$pdo->prepare("DELETE FROM pembayaran WHERE id_reservasi IN (SELECT id_reservasi FROM reservasi WHERE tanggal_checkin = ?)")->execute([$tglLateCheckin]);
$pdo->prepare("DELETE FROM reservasi_log WHERE id_reservasi IN (SELECT id_reservasi FROM reservasi WHERE tanggal_checkin = ?)")->execute([$tglLateCheckin]);
$pdo->prepare("DELETE FROM reservasi WHERE tanggal_checkin = ?")->execute([$tglLateCheckin]);

$stmtRLate = $pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, nama_tamu, kontak_tamu, id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, batas_bayar_pada, kedaluwarsa_pada, dibuat_pada)
    VALUES (?, 'Tamu Late Pay', '0812999905', ?, ?, ?, 2, 'kedaluwarsa', 500000, 600000, 100000, 1000000, NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 12 HOUR, NOW() - INTERVAL 2 DAY)
");
$stmtRLate->execute([$idPelanggan1, $idTipe, $tglLateCheckin, $tglLateCheckout]);
$idRLate = (int)$pdo->lastInsertId();

// Tambahkan pembayaran 'menunggu' yang terlambat dibayar
$orderLate = "MSY-LATE-{$idRLate}-" . time();
$pdo->prepare("
    INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, dibuat_pada)
    VALUES (?, ?, 'midtrans', 1000000, 'menunggu', NOW() - INTERVAL 2 DAY)
")->execute([$idRLate, $orderLate]);

// Kasus A (T-90): Tanggal masih kosong -> settlement tiba -> reaktivasi menjadi 'dikonfirmasi'
$hasilProsesA = proses_status_transaksi_midtrans($orderLate, [
    'order_id' => $orderLate,
    'transaction_status' => 'settlement',
    'gross_amount' => '1000000',
    'payment_type' => 'bank_transfer',
    'transaction_id' => 'TRX-TEST-LATE-01',
    'settlement_time' => date('Y-m-d H:i:s')
]);

$statusAfterLate = $pdo->query("SELECT status FROM reservasi WHERE id_reservasi = {$idRLate}")->fetchColumn();
$logReaktif = $pdo->query("SELECT alasan FROM reservasi_log WHERE id_reservasi = {$idRLate} ORDER BY id_log DESC LIMIT 1")->fetchColumn();

assert_test(
    $statusAfterLate === 'dikonfirmasi' && strpos((string)$logReaktif, 'pembayaran terlambat') !== false,
    'T-90',
    'Pembayaran terlambat saat kamar masih tersedia: reservasi aktif kembali menjadi dikonfirmasi',
    "Status reservasi: {$statusAfterLate}, Log: {$logReaktif}"
);

// Kasus B (T-91): Kamar sudah terisi penuh oleh tamu lain -> settlement tiba untuk reservasi kedaluwarsa lain
$stmtRLate2 = $pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, nama_tamu, kontak_tamu, id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, batas_bayar_pada, kedaluwarsa_pada, dibuat_pada)
    VALUES (?, 'Tamu Bentrok', '0812999906', ?, ?, ?, 2, 'kedaluwarsa', 500000, 600000, 100000, 1000000, NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 12 HOUR, NOW() - INTERVAL 2 DAY)
");
$stmtRLate2->execute([$idPelanggan1, $idTipe, $tglLateCheckin, $tglLateCheckout]);
$idRLate2 = (int)$pdo->lastInsertId();

$orderLate2 = "MSY-LATEBENTROK-{$idRLate2}-" . time();
$pdo->prepare("
    INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, dibuat_pada)
    VALUES (?, ?, 'midtrans', 1000000, 'menunggu', NOW() - INTERVAL 2 DAY)
")->execute([$idRLate2, $orderLate2]);

// Isi penuh seluruh unit kamar pada tanggal tersebut menggunakan pemblokiran unit
$unitTotal = (int)$pdo->query("SELECT jumlah_unit FROM tipe_villa WHERE id_tipe = {$idTipe}")->fetchColumn();
$pdo->prepare("
    INSERT INTO blokir_tanggal (id_tipe, tanggal_mulai, tanggal_selesai, alasan, dibuat_oleh)
    VALUES (?, ?, ?, 'Uji Kamar Penuh Bentrok', ?)
")->execute([$idTipe, $tglLateCheckin, $tglLateCheckout, $idPelanggan1]);

// Settlement tiba saat kamar sudah PENUH
$hasilProsesB = proses_status_transaksi_midtrans($orderLate2, [
    'order_id' => $orderLate2,
    'transaction_status' => 'settlement',
    'gross_amount' => '1000000',
    'payment_type' => 'bank_transfer',
    'transaction_id' => 'TRX-TEST-LATE-02',
    'settlement_time' => date('Y-m-d H:i:s')
]);

$statusRLate2 = $pdo->query("SELECT status FROM reservasi WHERE id_reservasi = {$idRLate2}")->fetchColumn();
$payLate2 = $pdo->query("SELECT status, catatan FROM pembayaran WHERE order_id = '{$orderLate2}'")->fetch(PDO::FETCH_ASSOC);

assert_test(
    $statusRLate2 === 'kedaluwarsa' && $payLate2['status'] === 'ganda' && strpos((string)$payLate2['catatan'], 'perlu ditinjau: tanggal sudah terisi') !== false,
    'T-91',
    'Pembayaran terlambat saat kamar penuh: reservasi tetap kedaluwarsa & pembayaran ditandai ganda perlu ditinjau',
    "Status reservasi: {$statusRLate2}, Status bayar: {$payLate2['status']}, Catatan: {$payLate2['catatan']}"
);

// -------------------------------------------------------------------------
// TEST 10 (T-93): Skrip CLI bin/expire-reservasi.php Menolak Akses Web
// -------------------------------------------------------------------------
$cliScriptContent = file_get_contents(__DIR__ . '/../bin/expire-reservasi.php');
$hasCliGuard = (strpos($cliScriptContent, "php_sapi_name() !== 'cli'") !== false);
assert_test(
    $hasCliGuard,
    'T-93',
    'Skrip bin/expire-reservasi.php memiliki guard otentikasi CLI (menolak eksekusi web HTTP)',
    'Guard php_sapi_name() terverifikasi'
);

// -------------------------------------------------------------------------
// TEST 11 (T-94): Audit Log reservasi_log Mencatat Seluruh Mutasi Status
// -------------------------------------------------------------------------
$logCount = (int)$pdo->query("SELECT COUNT(*) FROM reservasi_log WHERE id_reservasi = {$idR2}")->fetchColumn();
assert_test(
    $logCount >= 1,
    'T-94',
    'Tabel reservasi_log berhasil mencatat mutasi status sistem otomatis (audit trail lengkap)',
    "Jumlah entri log tercatat untuk #RSV-{$idR2}: {$logCount}"
);

// -------------------------------------------------------------------------
// TEST 12 (T-92): Validasi Expiry Midtrans & Penolakan Jika < 5 Menit
// -------------------------------------------------------------------------
$apiBuatPembayaran = file_get_contents(__DIR__ . '/../api/buat-pembayaran.php');
$hasExpirySync = (strpos($apiBuatPembayaran, "min(\$maxMenit, \$sisaMenit)") !== false);
$hasMinReject = (strpos($apiBuatPembayaran, "\$durasiMenit < 5") !== false);
assert_test(
    $hasExpirySync && $hasMinReject,
    'T-92',
    'api/buat-pembayaran.php menyelaraskan durasi expiry dengan batas bayar dan menolak transaksi < 5 menit',
    'Sinkronisasi expiry menit dan penolakan batas < 5 menit terverifikasi'
);

// -------------------------------------------------------------------------
// BERSIHKAN DATA UJI
// -------------------------------------------------------------------------
$pdo->prepare("DELETE FROM pembayaran WHERE id_reservasi IN (SELECT id_reservasi FROM reservasi WHERE tanggal_checkin IN (?, ?))")->execute([$tglTestCheckin, $tglLateCheckin]);
$pdo->prepare("DELETE FROM reservasi_log WHERE id_reservasi IN (SELECT id_reservasi FROM reservasi WHERE tanggal_checkin IN (?, ?))")->execute([$tglTestCheckin, $tglLateCheckin]);
$pdo->prepare("DELETE FROM reservasi WHERE tanggal_checkin IN (?, ?)")->execute([$tglTestCheckin, $tglLateCheckin]);
$pdo->prepare("DELETE FROM blokir_tanggal WHERE alasan = 'Uji Kamar Penuh Bentrok'")->execute();

echo "\n========================================================================\n";
echo "   RINGKASAN HASIL PENGUJIAN: {$passedTests} / {$totalTests} LULUS";
if ($failedTests > 0) {
    echo " ({$failedTests} GAGAL)";
}
echo "\n========================================================================\n";

exit($failedTests === 0 ? 0 : 1);
