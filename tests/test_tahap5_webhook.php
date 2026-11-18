<?php
/**
 * Test Gerbang Tahap 5: Webhook Notifikasi Midtrans dan Sinkronisasi
 */

require_once __DIR__ . '/../includes/bootstrap.php';
$pdo = get_db();

echo "=== MEMULAI PENGUJIAN TAHAP 5: WEBHOOK DAN SINKRONISASI ===\n";

$serverKey = $config['MIDTRANS_SERVER_KEY'] ?? '';

// Helper kirim HTTP POST JSON
function post_json($url, $data) {
    $ch = curl_init($url);
    $payload = json_encode($data);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $httpCode, 'body' => $response, 'json' => json_decode($response, true)];
}

// 0. Siapkan Data Uji
$pdo->prepare("DELETE FROM pembayaran WHERE order_id LIKE 'TEST-T5-%' OR order_id LIKE 'TUNAI-T5-%'")->execute();
$pdo->prepare("DELETE FROM reservasi WHERE catatan LIKE 'TEST-T5-%'")->execute();

// Buat reservasi uji
$pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, id_tipe, diproses_oleh, tanggal_checkin, tanggal_checkout, jumlah_tamu, catatan, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total)
    VALUES (4, 1, 2, '2026-12-20', '2026-12-22', 2, 'TEST-T5-RES-1', 'dikonfirmasi', 1500000, 2000000, 350000, 1500000)
")->execute();
$resId1 = (int)$pdo->lastInsertId();

// UJI 1: Signature Salah -> Harus 403
$t1 = post_json('http://localhost:8000/notifikasi-midtrans.php', [
    'order_id' => 'TEST-T5-FAKE',
    'status_code' => '200',
    'gross_amount' => '1500000.00',
    'signature_key' => 'invalid-signature-hash-12345',
    'transaction_status' => 'settlement'
]);
assert($t1['status'] === 403, "Uji 1 Gagal: Status code bukan 403 saat signature salah (Didapat: {$t1['status']})");
echo "UJI 1 LULUS: Signature salah ditolak dengan status 403 Forbidden.\n";

// UJI 2: Order ID Tak Dikenal -> Harus 400
$unknownOrderId = 'TEST-T5-UNKNOWN-' . time();
$unknownSig = hash('sha512', $unknownOrderId . '200' . '1500000.00' . $serverKey);
$t2 = post_json('http://localhost:8000/notifikasi-midtrans.php', [
    'order_id' => $unknownOrderId,
    'status_code' => '200',
    'gross_amount' => '1500000.00',
    'signature_key' => $unknownSig,
    'transaction_status' => 'settlement'
]);
assert($t2['status'] === 400, "Uji 2 Gagal: Status code bukan 400 saat order_id tidak dikenal (Didapat: {$t2['status']})");
echo "UJI 2 LULUS: Order ID tak dikenal ditolak dengan status 400 Bad Request.\n";

// UJI 3: Nominal Tidak Cocok -> Harus 400
$orderNominal = 'TEST-T5-NOMINAL-MISMATCH';
$pdo->prepare("INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status) VALUES (?, ?, 'midtrans', 1500000, 'menunggu')")->execute([$resId1, $orderNominal]);

$mismatchGross = '2500000.00'; // Berbeda dengan nominal di database (1500000)
$mismatchSig = hash('sha512', $orderNominal . '200' . $mismatchGross . $serverKey);
$t3 = post_json('http://localhost:8000/notifikasi-midtrans.php', [
    'order_id' => $orderNominal,
    'status_code' => '200',
    'gross_amount' => $mismatchGross,
    'signature_key' => $mismatchSig,
    'transaction_status' => 'settlement'
]);
assert($t3['status'] === 400, "Uji 3 Gagal: Status code bukan 400 saat nominal tidak cocok (Didapat: {$t3['status']})");
echo "UJI 3 LULUS: Ketidakcocokan nominal ditolak dengan status 400 Bad Request.\n";

// UJI 4: Webhook Dikirim Dua Kali -> Tetap Satu 'Lunas' Tanpa Error (Idempoten)
$orderIdem = 'TEST-T5-IDEMPOTEN';
$pdo->prepare("INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status) VALUES (?, ?, 'midtrans', 1500000, 'menunggu')")->execute([$resId1, $orderIdem]);

$idemGross = '1500000.00';
$idemSig = hash('sha512', $orderIdem . '200' . $idemGross . $serverKey);

// Kiriman pertama
$t4a = post_json('http://localhost:8000/notifikasi-midtrans.php', [
    'order_id' => $orderIdem,
    'status_code' => '200',
    'gross_amount' => $idemGross,
    'signature_key' => $idemSig,
    'transaction_status' => 'settlement',
    'payment_type' => 'bank_transfer',
    'transaction_id' => 'TRX-IDEM-001'
]);
assert($t4a['status'] === 200, "Uji 4A Gagal: Kiriman pertama bukan 200 (Didapat: {$t4a['status']})");

// Periksa status di DB
$cek1 = $pdo->query("SELECT status, metode_kelompok FROM pembayaran WHERE order_id = '{$orderIdem}'")->fetch();
assert($cek1['status'] === 'lunas', "Uji 4A Gagal: Status di DB bukan lunas");
assert($cek1['metode_kelompok'] === 'transfer_bank', "Uji 4A Gagal: Kelompok metode bukan transfer_bank");

// Kiriman kedua (duplikasi webhook)
$t4b = post_json('http://localhost:8000/notifikasi-midtrans.php', [
    'order_id' => $orderIdem,
    'status_code' => '200',
    'gross_amount' => $idemGross,
    'signature_key' => $idemSig,
    'transaction_status' => 'settlement',
    'payment_type' => 'bank_transfer',
    'transaction_id' => 'TRX-IDEM-001'
]);
assert($t4b['status'] === 200, "Uji 4B Gagal: Kiriman kedua gagal (Didapat: {$t4b['status']})");

$cek2 = $pdo->query("SELECT COUNT(*) AS total_lunas FROM pembayaran WHERE id_reservasi = {$resId1} AND status = 'lunas'")->fetch();
assert((int)$cek2['total_lunas'] === 1, "Uji 4B Gagal: Jumlah pembayaran lunas bertambah!");
echo "UJI 4 LULUS: Pengiriman ganda idempoten, status tetap satu 'lunas' tanpa error.\n";

// UJI 5: Settlement Terlambat Setelah Tunai -> 'ganda' dan Tidak Dihitung
$pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, id_tipe, diproses_oleh, tanggal_checkin, tanggal_checkout, jumlah_tamu, catatan, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total)
    VALUES (4, 1, 2, '2026-12-25', '2026-12-27', 2, 'TEST-T5-RES-2', 'dikonfirmasi', 1500000, 2000000, 350000, 1500000)
")->execute();
$resId2 = (int)$pdo->lastInsertId();

// 1. Kasus: Pengelola sudah mencatat pembayaran tunai 'lunas' duluan
$orderTunai = 'TUNAI-T5-' . $resId2;
$pdo->prepare("
    INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, metode_kelompok, metode_detail, dibayar_pada, dicatat_oleh)
    VALUES (?, ?, 'manual', 1500000, 'lunas', 'tunai', 'cash', NOW(), 2)
")->execute([$resId2, $orderTunai]);

// 2. Transaksi Midtrans yang sebelumnya masih 'menunggu' tiba-tiba menerima settlement terlambat
$orderMidtransLate = 'TEST-T5-LATE-' . $resId2;
$pdo->prepare("
    INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status)
    VALUES (?, ?, 'midtrans', 1500000, 'menunggu')
")->execute([$resId2, $orderMidtransLate]);

$lateGross = '1500000.00';
$lateSig = hash('sha512', $orderMidtransLate . '200' . $lateGross . $serverKey);

$t5 = post_json('http://localhost:8000/notifikasi-midtrans.php', [
    'order_id' => $orderMidtransLate,
    'status_code' => '200',
    'gross_amount' => $lateGross,
    'signature_key' => $lateSig,
    'transaction_status' => 'settlement',
    'payment_type' => 'gopay',
    'transaction_id' => 'TRX-LATE-GOPAY'
]);
assert($t5['status'] === 200, "Uji 5 Gagal: Webhook settlement terlambat bukan 200 (Didapat: {$t5['status']})");

// Verifikasi di DB: baris Midtrans terlambat harus berstatus 'ganda', bukan 'lunas'
$cekLate = $pdo->query("SELECT status, catatan, metode_kelompok FROM pembayaran WHERE order_id = '{$orderMidtransLate}'")->fetch();
assert($cekLate['status'] === 'ganda', "Uji 5 Gagal: Status bukan 'ganda', didapat: " . $cekLate['status']);
assert(strpos($cekLate['catatan'], 'Pembayaran ganda') !== false, "Uji 5 Gagal: Catatan tidak memuat 'Pembayaran ganda'");
assert($cekLate['metode_kelompok'] === 'e_wallet', "Uji 5 Gagal: Metode kelompok bukan e_wallet");

// Verifikasi: pembayaran 'lunas' pada reservasi tetap HANYA SATU (pembayaran tunai)
$cekLunasRes2 = $pdo->query("SELECT COUNT(*) AS total_lunas FROM pembayaran WHERE id_reservasi = {$resId2} AND status = 'lunas'")->fetch();
assert((int)$cekLunasRes2['total_lunas'] === 1, "Uji 5 Gagal: Total lunas bukan 1 pada reservasi 2!");
echo "UJI 5 LULUS: Settlement terlambat setelah tunai berhasil ditandai 'ganda' dan tidak dihitung ganda.\n";

// UJI 6: Verifikasi Helper sinkron_pembayaran
$hasilSinkron = sinkron_pembayaran($orderIdem);
assert($hasilSinkron['ok'] === true, "Uji 6 Gagal: sinkron_pembayaran gagal");
assert($hasilSinkron['status'] === 'lunas', "Uji 6 Gagal: status bukan lunas");
echo "UJI 6 LULUS: Helper sinkron_pembayaran berfungsi normal dan idempoten.\n";

// UJI 7: Verifikasi Endpoint api/cek-status-pembayaran.php
// GET -> 405 Method not allowed
$chGet = curl_init('http://localhost:8000/api/cek-status-pembayaran.php');
curl_setopt($chGet, CURLOPT_RETURNTRANSFER, true);
curl_exec($chGet);
$getCode = curl_getinfo($chGet, CURLINFO_HTTP_CODE);
curl_close($chGet);
assert($getCode === 405, "Uji 7A Gagal: Endpoint menerima GET padahal hanya POST (Didapat: {$getCode})");

// POST tanpa login -> 403 Forbidden
$t7b = post_json('http://localhost:8000/api/cek-status-pembayaran.php', ['order_id' => $orderIdem]);
assert($t7b['status'] === 403, "Uji 7B Gagal: Endpoint tidak menolak tanpa login (Didapat: {$t7b['status']})");
echo "UJI 7 LULUS: Proteksi endpoint api/cek-status-pembayaran.php (POST only + wajib login) bekerja.\n";

// Bersihkan data uji
$pdo->prepare("DELETE FROM pembayaran WHERE order_id LIKE 'TEST-T5-%' OR order_id LIKE 'TUNAI-T5-%'")->execute();
$pdo->prepare("DELETE FROM reservasi WHERE catatan LIKE 'TEST-T5-%'")->execute();

echo "=== GERBANG TAHAP 5: SEMUA PENGUJIAN LOLOS DENGAN HASIL VALID ===\n";
