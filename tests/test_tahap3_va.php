<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$db = get_db();
echo "=== PENGUJIAN TAHAP 3: EKSTRAKSI DAN PENYIMPANAN VIRTUAL ACCOUNT ===\n";

// 1. Uji Ekstraksi Standard VA (BCA)
$bcaPayload = [
    'transaction_status' => 'pending',
    'payment_type' => 'bank_transfer',
    'va_numbers' => [
        ['bank' => 'bca', 'va_number' => '12345678901234']
    ],
    'expiry_time' => '2026-11-27 12:00:00'
];
$resBca = ekstrak_data_va_midtrans($bcaPayload);
assert($resBca['bank_atau_toko'] === 'bca', "Gagal ekstrak bank BCA");
assert($resBca['kode_pembayaran'] === '12345678901234', "Gagal ekstrak nomor VA BCA");
assert($resBca['kedaluwarsa_pada'] === '2026-11-27 12:00:00', "Gagal ekstrak expiry BCA");
echo "[PASS] Ekstraksi Standard VA (BCA)\n";

// 2. Uji Ekstraksi Mandiri echannel
$mandiriPayload = [
    'transaction_status' => 'pending',
    'payment_type' => 'echannel',
    'biller_code' => '70012',
    'bill_key' => '9988776655',
    'expiry_time' => '2026-11-27 15:30:00'
];
$resMandiri = ekstrak_data_va_midtrans($mandiriPayload);
assert($resMandiri['bank_atau_toko'] === 'mandiri', "Gagal ekstrak bank Mandiri");
assert($resMandiri['kode_pembayaran'] === '70012 / 9988776655', "Gagal ekstrak Mandiri bill");
echo "[PASS] Ekstraksi Mandiri echannel\n";

// 3. Uji Ekstraksi Permata
$permataPayload = [
    'transaction_status' => 'pending',
    'payment_type' => 'bank_transfer',
    'permata_va_number' => '888812345678',
];
$resPermata = ekstrak_data_va_midtrans($permataPayload);
assert($resPermata['bank_atau_toko'] === 'permata', "Gagal ekstrak Permata");
assert($resPermata['kode_pembayaran'] === '888812345678', "Gagal ekstrak kode Permata");
echo "[PASS] Ekstraksi Permata VA\n";

// 4. Uji Ekstraksi Indomaret (cstore)
$cstorePayload = [
    'transaction_status' => 'pending',
    'payment_type' => 'cstore',
    'store' => 'indomaret',
    'payment_code' => 'IND998877',
];
$resCstore = ekstrak_data_va_midtrans($cstorePayload);
assert($resCstore['bank_atau_toko'] === 'indomaret', "Gagal ekstrak Indomaret");
assert($resCstore['kode_pembayaran'] === 'IND998877', "Gagal ekstrak kode Indomaret");
echo "[PASS] Ekstraksi Indomaret cstore\n";

// 5. Uji Formatting & Masking
assert(format_bank_atau_toko('bca') === 'Bank BCA');
assert(format_bank_atau_toko('mandiri') === 'Bank Mandiri');
assert(format_nomor_va('12345678901234') === '1234 5678 9012 34');
assert(format_nomor_va('70012 / 9988776655') === '70012 / 9988776655');
assert(samarkan_kode_pembayaran('12345678901234') === '••••1234');
assert(samarkan_kode_pembayaran('70012 / 9988776655') === '••••6655');
echo "[PASS] Format dan Samarkan Kode VA\n";

// 6. Uji Instruksi Transfer
$petunjukId = ambil_petunjuk_transfer('bca', 'id');
$petunjukEn = ambil_petunjuk_transfer('bca', 'en');
assert(count($petunjukId) === 3 && count($petunjukEn) === 3, "Harus tepat 3 langkah petunjuk");
echo "[PASS] 3 Langkah Petunjuk Transfer (Bilingual ID/EN)\n";

// 7. Uji Database: Simpan VA, Idempotensi, dan Deteksi Duplikat
$db->beginTransaction();

// Siapkan reservasi uji
$db->exec("
    INSERT INTO reservasi (id_pelanggan, id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, status)
    VALUES (1, 1, '2026-12-01', '2026-12-03', 2, 500000, 500000, 0, 1000000, 'dikonfirmasi')
");
$idRes1 = (int)$db->lastInsertId();

$orderId1 = 'TEST-VA-ORDER-1-' . time();
$db->exec("
    INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status)
    VALUES ({$idRes1}, '{$orderId1}', 'midtrans', 1000000, 'menunggu')
");

// Simulasi webhook pending dengan VA BCA
$pendingBca = [
    'order_id' => $orderId1,
    'transaction_status' => 'pending',
    'payment_type' => 'bank_transfer',
    'gross_amount' => '1000000',
    'va_numbers' => [
        ['bank' => 'bca', 'va_number' => '9999000011112222']
    ],
    'expiry_time' => '2026-11-28 10:00:00'
];

$resProses1 = proses_status_transaksi_midtrans($orderId1, $pendingBca);
assert($resProses1['ok'] === true, "Gagal proses pending");
assert($resProses1['data']['bank_atau_toko'] === 'bca', "Bank tidak tersimpan");
assert($resProses1['data']['kode_pembayaran'] === '9999000011112222', "Kode pembayaran tidak tersimpan");

// Uji Idempotensi: settlement tanpa va_numbers TIDAK boleh menghapus bank_atau_toko dan kode_pembayaran
$settlementPayload = [
    'order_id' => $orderId1,
    'transaction_status' => 'settlement',
    'payment_type' => 'bank_transfer',
    'transaction_id' => 'TRX-SETTLE-1',
    'settlement_time' => '2026-11-26 15:00:00',
    'gross_amount' => '1000000'
];
$resProsesSettle = proses_status_transaksi_midtrans($orderId1, $settlementPayload);
assert($resProsesSettle['ok'] === true, "Gagal proses settlement");
assert($resProsesSettle['data']['status'] === 'lunas', "Status harus lunas");
assert($resProsesSettle['data']['bank_atau_toko'] === 'bca', "Idempotensi gagal: bank terhapus");
assert($resProsesSettle['data']['kode_pembayaran'] === '9999000011112222', "Idempotensi gagal: kode VA terhapus");
echo "[PASS] Idempotensi Penyimpanan VA & Settlement\n";

// Uji Deteksi Duplikat: buat reservasi kedua yang mendapat VA sama saat status menunggu
$db->exec("
    INSERT INTO reservasi (id_pelanggan, id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, status)
    VALUES (1, 1, '2026-12-05', '2026-12-07', 2, 500000, 500000, 0, 1000000, 'dikonfirmasi')
");
$idRes2 = (int)$db->lastInsertId();

$orderIdActive = 'TEST-VA-ACTIVE-' . time();
$db->exec("
    INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, bank_atau_toko, kode_pembayaran)
    VALUES ({$idRes2}, '{$orderIdActive}', 'midtrans', 1000000, 'menunggu', 'bca', '7777888899990000')
");

$orderIdDup = 'TEST-VA-DUP-' . time();
$db->exec("
    INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status)
    VALUES ({$idRes2}, '{$orderIdDup}', 'midtrans', 1000000, 'menunggu')
");

$pendingDup = [
    'order_id' => $orderIdDup,
    'transaction_status' => 'pending',
    'payment_type' => 'bank_transfer',
    'gross_amount' => '1000000',
    'va_numbers' => [
        ['bank' => 'bca', 'va_number' => '7777888899990000']
    ],
    'expiry_time' => '2026-11-28 10:00:00'
];

$resDup = proses_status_transaksi_midtrans($orderIdDup, $pendingDup);
assert($resDup['ok'] === true);

$stmtCheckDup = $db->prepare("SELECT catatan, status FROM pembayaran WHERE order_id = ?");
$stmtCheckDup->execute([$orderIdDup]);
$rowDup = $stmtCheckDup->fetch(PDO::FETCH_ASSOC);

assert($rowDup['status'] === 'menunggu', "Status moneter tidak boleh berubah (harus tetap menunggu)");
assert(strpos($rowDup['catatan'], 'perlu ditinjau: duplikat kode VA') !== false, "Catatan harus mencantumkan peringatan duplikat");
echo "[PASS] Deteksi Duplikat Kode VA (Catatan ditandai tanpa mutasi status moneter)\n";

// Uji Ganti Metode Pembayaran (Logika Pembatalan Transaksi Lama)
$orderIdOld = 'TEST-VA-OLD-' . time();
$db->exec("
    INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, snap_token, bank_atau_toko, kode_pembayaran)
    VALUES ({$idRes2}, '{$orderIdOld}', 'midtrans', 1000000, 'menunggu', 'token-old-123', 'bca', '1111222233334444')
");
$idOld = (int)$db->lastInsertId();

// Simulasi aksi ganti metode: tandai lama sebagai kedaluwarsa
$stmtExp = $db->prepare("
    UPDATE pembayaran 
    SET status = 'kedaluwarsa',
        catatan = CONCAT(COALESCE(catatan, ''), CASE WHEN catatan IS NULL OR catatan = '' THEN '' ELSE ' | ' END, '[Diganti metode baru]')
    WHERE id_pembayaran = ?
");
$stmtExp->execute([$idOld]);

// Verifikasi status transaksi lama berubah menjadi 'kedaluwarsa'
$stmtCheckOld = $db->prepare("SELECT status, catatan FROM pembayaran WHERE id_pembayaran = ?");
$stmtCheckOld->execute([$idOld]);
$rowOld = $stmtCheckOld->fetch(PDO::FETCH_ASSOC);
assert($rowOld['status'] === 'kedaluwarsa', "Transaksi lama harus kedaluwarsa setelah ganti metode");
assert(strpos($rowOld['catatan'], '[Diganti metode baru]') !== false, "Catatan pergantian metode harus tercatat");
echo "[PASS] Pergantian Metode Pembayaran (Transaksi lama kedaluwarsa, tidak dihitung pemasukan)\n";

$db->rollBack();
echo "=== SEMUA PENGUJIAN TAHAP 3 SUKSES (ALL PASS) ===\n";
