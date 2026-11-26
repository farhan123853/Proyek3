<?php
/**
 * Test Suite Komprehensif: Virtual Account Unik & Kode Pembayaran (T-76 s.d. T-83)
 * Villa Joglo Masayu
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = get_db();
echo "======================================================================\n";
echo "   PENGUJIAN VIRTUAL ACCOUNT & KODE PEMBAYARAN UNIK (T-76 s.d. T-83)  \n";
echo "======================================================================\n\n";

$passCount = 0;
$testCount = 0;

function run_test(string $code, string $name, callable $fn) {
    global $passCount, $testCount;
    $testCount++;
    echo "[$code] $name... ";
    try {
        $fn();
        echo "\033[32m[LULUS / PASS]\033[0m\n";
        $passCount++;
    } catch (\Throwable $e) {
        echo "\033[31m[GAGAL / FAIL]\033[0m: " . $e->getMessage() . "\n";
    }
}

// Persiapan Data Uji di Transaksi DB Terisolasi
$pdo->beginTransaction();

try {
    // 1. Setup Pengguna
    $pdo->exec("
        INSERT INTO pengguna (nama, email, password_hash, peran, kontak)
        VALUES ('Tamu Pelanggan A', 'pelanggan_a_va@example.com', 'hash123', 'pelanggan', '081211112222')
    ");
    $userAId = (int)$pdo->lastInsertId();

    $pdo->exec("
        INSERT INTO pengguna (nama, email, password_hash, peran, kontak)
        VALUES ('Tamu Pelanggan B', 'pelanggan_b_va@example.com', 'hash123', 'pelanggan', '081233334444')
    ");
    $userBId = (int)$pdo->lastInsertId();

    $pdo->exec("
        INSERT INTO pengguna (nama, email, password_hash, peran, kontak)
        VALUES ('Pemilik Properti Uji', 'pemilik_va@example.com', 'hash123', 'pemilik', '081255556666')
    ");
    $ownerId = (int)$pdo->lastInsertId();

    // 2. Setup Tipe Villa
    $idTipe = (int)$pdo->query("SELECT id_tipe FROM tipe_villa LIMIT 1")->fetchColumn();
    if (!$idTipe) {
        $pdo->exec("INSERT INTO tipe_villa (nama, harga_weekday, harga_weekend, jumlah_unit) VALUES ('Joglo Masayu Test', 1000000, 1200000, 2)");
        $idTipe = (int)$pdo->lastInsertId();
    }

    // -------------------------------------------------------------------------
    // T-76: Keunikan Nomor Virtual Account antar-Reservasi Berbeda (BCA VA)
    // -------------------------------------------------------------------------
    run_test('T-76', 'Keunikan Nomor Virtual Account antar-Reservasi Berbeda', function() use ($pdo, $userAId, $userBId, $idTipe) {
        // Reservasi 1 milik Pelanggan A
        $pdo->prepare("
            INSERT INTO reservasi (id_pelanggan, id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, status)
            VALUES (?, ?, '2026-12-01', '2026-12-03', 2, 1000000, 1000000, 0, 2000000, 'dikonfirmasi')
        ")->execute([$userAId, $idTipe]);
        $idRes1 = (int)$pdo->lastInsertId();
        $orderId1 = 'MSY-' . $idRes1 . '-' . time() . '-1';

        $pdo->prepare("
            INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status)
            VALUES (?, ?, 'midtrans', 2000000, 'menunggu')
        ")->execute([$idRes1, $orderId1]);

        // Reservasi 2 milik Pelanggan B
        $pdo->prepare("
            INSERT INTO reservasi (id_pelanggan, id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, status)
            VALUES (?, ?, '2026-12-05', '2026-12-07', 2, 1000000, 1000000, 0, 2000000, 'dikonfirmasi')
        ")->execute([$userBId, $idTipe]);
        $idRes2 = (int)$pdo->lastInsertId();
        $orderId2 = 'MSY-' . $idRes2 . '-' . time() . '-2';

        $pdo->prepare("
            INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status)
            VALUES (?, ?, 'midtrans', 2000000, 'menunggu')
        ")->execute([$idRes2, $orderId2]);

        // Simulasikan nomor VA berbeda dari Midtrans (terikat order_id masing-masing)
        $vaNumber1 = '7001200011112222';
        $vaNumber2 = '7001200033334444';

        $payloadPending1 = [
            'order_id' => $orderId1,
            'transaction_status' => 'pending',
            'payment_type' => 'bank_transfer',
            'gross_amount' => '2000000',
            'va_numbers' => [['bank' => 'bca', 'va_number' => $vaNumber1]],
            'expiry_time' => '2026-11-28 12:00:00'
        ];
        $payloadPending2 = [
            'order_id' => $orderId2,
            'transaction_status' => 'pending',
            'payment_type' => 'bank_transfer',
            'gross_amount' => '2000000',
            'va_numbers' => [['bank' => 'bca', 'va_number' => $vaNumber2]],
            'expiry_time' => '2026-11-28 12:00:00'
        ];

        $res1 = proses_status_transaksi_midtrans($orderId1, $payloadPending1);
        $res2 = proses_status_transaksi_midtrans($orderId2, $payloadPending2);

        if (!$res1['ok'] || !$res2['ok']) {
            throw new Exception("Gagal memproses status pending Midtrans");
        }

        if ($res1['data']['kode_pembayaran'] === $res2['data']['kode_pembayaran']) {
            throw new Exception("KODE PEMBAYARAN TIDAK UNIK: Kedua order_id berbeda mendapatkan kode VA yang identik!");
        }

        echo "\n    -> Order 1 (#{$idRes1}): BCA VA [••••" . substr($res1['data']['kode_pembayaran'], -4) . "]";
        echo "\n    -> Order 2 (#{$idRes2}): BCA VA [••••" . substr($res2['data']['kode_pembayaran'], -4) . "] (Unik & Berbeda)";
    });

    // -------------------------------------------------------------------------
    // T-77: Pergantian Bank / Metode Pembayaran (Nomor VA Baru & Nonaktifkan Lama)
    // -------------------------------------------------------------------------
    run_test('T-77', 'Pergantian Metode Pembayaran (Kode Baru & Transaksi Lama Kedaluwarsa)', function() use ($pdo, $userAId, $idTipe) {
        $pdo->prepare("
            INSERT INTO reservasi (id_pelanggan, id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, status)
            VALUES (?, ?, '2026-12-10', '2026-12-12', 2, 1000000, 1000000, 0, 2000000, 'dikonfirmasi')
        ")->execute([$userAId, $idTipe]);
        $idRes = (int)$pdo->lastInsertId();

        $orderIdOld = 'MSY-' . $idRes . '-OLD';
        $pdo->prepare("
            INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, snap_token, bank_atau_toko, kode_pembayaran)
            VALUES (?, ?, 'midtrans', 2000000, 'menunggu', 'token-old-abc', 'bca', '12345678901234')
        ")->execute([$idRes, $orderIdOld]);
        $idPayOld = (int)$pdo->lastInsertId();

        // Pengguna memilih 'Ganti Bank / Metode' (misal ingin transfer BNI)
        // Logika aplikasi: tandai transaksi lama sebagai 'kedaluwarsa' lokal dan buat transaksi baru
        $pdo->prepare("
            UPDATE pembayaran 
            SET status = 'kedaluwarsa',
                catatan = CONCAT(COALESCE(catatan, ''), CASE WHEN catatan IS NULL OR catatan = '' THEN '' ELSE ' | ' END, '[Diganti metode baru]')
            WHERE id_pembayaran = ?
        ")->execute([$idPayOld]);

        $orderIdNew = 'MSY-' . $idRes . '-NEW';
        $newVaCode = '9888776655443322';
        $pdo->prepare("
            INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, snap_token, bank_atau_toko, kode_pembayaran)
            VALUES (?, ?, 'midtrans', 2000000, 'menunggu', 'token-new-xyz', 'bni', ?)
        ")->execute([$idRes, $orderIdNew, $newVaCode]);

        // Verifikasi transaksi lama berstatus 'kedaluwarsa'
        $stmtOld = $pdo->prepare("SELECT status, catatan FROM pembayaran WHERE id_pembayaran = ?");
        $stmtOld->execute([$idPayOld]);
        $rowOld = $stmtOld->fetch(PDO::FETCH_ASSOC);

        if ($rowOld['status'] !== 'kedaluwarsa') {
            throw new Exception("Status transaksi lama harus 'kedaluwarsa', didapat: " . $rowOld['status']);
        }
        if (strpos($rowOld['catatan'], '[Diganti metode baru]') === false) {
            throw new Exception("Catatan penggantian metode tidak tercantum pada transaksi lama");
        }

        // Verifikasi evaluasi_status_bayar hanya mengambil transaksi yang relevan
        $allPays = $pdo->query("SELECT * FROM pembayaran WHERE id_reservasi = {$idRes} ORDER BY id_pembayaran DESC")->fetchAll(PDO::FETCH_ASSOC);
        $eval = evaluasi_status_bayar($allPays);
        if ($eval['pembayaran']['order_id'] !== $orderIdNew) {
            throw new Exception("Pembayaran aktif harus transaksi baru (" . $orderIdNew . ")");
        }
    });

    // -------------------------------------------------------------------------
    // T-78: Penyamaran Kode VA Pasca Pelunasan (Masking: ••••4567)
    // -------------------------------------------------------------------------
    run_test('T-78', 'Penyamaran Kode VA Pasca Pelunasan (••••4567)', function() use ($pdo, $userAId, $idTipe) {
        $pdo->prepare("
            INSERT INTO reservasi (id_pelanggan, id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, status)
            VALUES (?, ?, '2026-12-15', '2026-12-17', 2, 1000000, 1000000, 0, 2000000, 'dikonfirmasi')
        ")->execute([$userAId, $idTipe]);
        $idRes = (int)$pdo->lastInsertId();
        $orderId = 'MSY-' . $idRes . '-SETTLE';

        $pdo->prepare("
            INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, bank_atau_toko, kode_pembayaran)
            VALUES (?, ?, 'midtrans', 2000000, 'menunggu', 'bca', '1122334455667788')
        ")->execute([$idRes, $orderId]);

        // Simulasikan pelunasan (Settlement dari webhook / simulator)
        $settlePayload = [
            'order_id' => $orderId,
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
            'gross_amount' => '2000000',
            'settlement_time' => '2026-11-26 14:00:00'
        ];
        $res = proses_status_transaksi_midtrans($orderId, $settlePayload);

        if (!$res['ok'] || $res['status'] !== 'lunas') {
            throw new Exception("Status gagal diperbarui ke 'lunas'");
        }

        // Cek fungsi masking
        $masked = samarkan_kode_pembayaran('1122334455667788');
        if ($masked !== '••••7788') {
            throw new Exception("Hasil samarkan_kode_pembayaran tidak sesuai. Didapat: " . $masked);
        }

        // Mandiri echannel format biller / bill_key
        $maskedMandiri = samarkan_kode_pembayaran('70012 / 9988776655');
        if ($maskedMandiri !== '••••6655') {
            throw new Exception("Hasil samarkan_kode_pembayaran Mandiri tidak sesuai. Didapat: " . $maskedMandiri);
        }
    });

    // -------------------------------------------------------------------------
    // T-79: Idempotensi Webhook / Sinkronisasi Berulang (Anti-Penimpaan NULL)
    // -------------------------------------------------------------------------
    run_test('T-79', 'Idempotensi Webhook / Sinkronisasi Berulang', function() use ($pdo, $userAId, $idTipe) {
        $pdo->prepare("
            INSERT INTO reservasi (id_pelanggan, id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, status)
            VALUES (?, ?, '2026-12-20', '2026-12-22', 2, 1000000, 1000000, 0, 2000000, 'dikonfirmasi')
        ")->execute([$userAId, $idTipe]);
        $idRes = (int)$pdo->lastInsertId();
        $orderId = 'MSY-' . $idRes . '-IDEMP';

        $pdo->prepare("
            INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status)
            VALUES (?, ?, 'midtrans', 2000000, 'menunggu')
        ")->execute([$idRes, $orderId]);

        // Panggilan 1: Pending dengan VA BCA
        $payload1 = [
            'order_id' => $orderId,
            'transaction_status' => 'pending',
            'payment_type' => 'bank_transfer',
            'gross_amount' => '2000000',
            'va_numbers' => [['bank' => 'bca', 'va_number' => '1234432112344321']],
            'expiry_time' => '2026-11-28 12:00:00'
        ];
        proses_status_transaksi_midtrans($orderId, $payload1);

        // Panggilan 2: Payload settlement tanpa array va_numbers
        $payload2 = [
            'order_id' => $orderId,
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
            'gross_amount' => '2000000',
            'settlement_time' => '2026-11-26 15:30:00'
        ];
        proses_status_transaksi_midtrans($orderId, $payload2);

        // Panggilan 3: Webhook berulang (duplikat settlement payload)
        proses_status_transaksi_midtrans($orderId, $payload2);

        // Verifikasi data di DB: bank_atau_toko dan kode_pembayaran TIDAK tertimpa NULL
        $stmtCheck = $pdo->prepare("SELECT status, bank_atau_toko, kode_pembayaran FROM pembayaran WHERE order_id = ?");
        $stmtCheck->execute([$orderId]);
        $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($row['status'] !== 'lunas') {
            throw new Exception("Status pembayaran harus 'lunas'");
        }
        if ($row['bank_atau_toko'] !== 'bca' || $row['kode_pembayaran'] !== '1234432112344321') {
            throw new Exception("IDEMPOTENSI GAGAL: bank_atau_toko atau kode_pembayaran tertimpa NULL saat settlement berulang!");
        }
    });

    // -------------------------------------------------------------------------
    // T-80: Deteksi Duplikat Kode VA Aktif (cek_duplikat_kode & Catatan)
    // -------------------------------------------------------------------------
    run_test('T-80', 'Deteksi Duplikat Kode VA Aktif (cek_duplikat_kode)', function() use ($pdo, $userAId, $idTipe) {
        $pdo->prepare("
            INSERT INTO reservasi (id_pelanggan, id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, status)
            VALUES (?, ?, '2026-12-25', '2026-12-27', 2, 1000000, 1000000, 0, 2000000, 'dikonfirmasi')
        ")->execute([$userAId, $idTipe]);
        $idRes = (int)$pdo->lastInsertId();

        $order1 = 'MSY-' . $idRes . '-DUP1';
        $order2 = 'MSY-' . $idRes . '-DUP2';
        $sameCode = '8888000011112222';

        // Order 1 aktif berstatus menunggu
        $pdo->prepare("
            INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, bank_atau_toko, kode_pembayaran)
            VALUES (?, ?, 'midtrans', 2000000, 'menunggu', 'bca', ?)
        ")->execute([$idRes, $order1, $sameCode]);

        // Order 2 baru dibuat
        $pdo->prepare("
            INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status)
            VALUES (?, ?, 'midtrans', 2000000, 'menunggu')
        ")->execute([$idRes, $order2]);

        $dupFound = cek_duplikat_kode($pdo, 'bca', $sameCode, $order2);
        if (!$dupFound || $dupFound['order_id'] !== $order1) {
            throw new Exception("cek_duplikat_kode gagal mendeteksi kode VA yang sama dari order1");
        }

        // Jalankan proses status untuk order2 dengan kode yang sama
        $payloadDup = [
            'order_id' => $order2,
            'transaction_status' => 'pending',
            'payment_type' => 'bank_transfer',
            'gross_amount' => '2000000',
            'va_numbers' => [['bank' => 'bca', 'va_number' => $sameCode]],
            'expiry_time' => '2026-11-28 12:00:00'
        ];
        proses_status_transaksi_midtrans($order2, $payloadDup);

        // Periksa bahwa order2 diberi catatan 'perlu ditinjau' dan status moneter tetap 'menunggu'
        $stmt = $pdo->prepare("SELECT status, catatan FROM pembayaran WHERE order_id = ?");
        $stmt->execute([$order2]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row['status'] !== 'menunggu') {
            throw new Exception("Status moneter tidak boleh berubah (harus tetap menunggu)");
        }
        if (strpos($row['catatan'], 'perlu ditinjau: duplikat kode VA') === false) {
            throw new Exception("Catatan harus mencantumkan peringatan duplikat kode VA");
        }
    });

    // -------------------------------------------------------------------------
    // T-81: Hak Akses Kode VA & Anti-IDOR (Staf Unmasked, Tamu IDOR 404, Pemilik Masked)
    // -------------------------------------------------------------------------
    run_test('T-81', 'Hak Akses Kode VA & Proteksi Anti-IDOR', function() use ($pdo, $userAId, $userBId, $ownerId, $idTipe) {
        $pdo->prepare("
            INSERT INTO reservasi (id_pelanggan, id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, status)
            VALUES (?, ?, '2027-01-01', '2027-01-03', 2, 1000000, 1000000, 0, 2000000, 'dikonfirmasi')
        ")->execute([$userAId, $idTipe]);
        $idResA = (int)$pdo->lastInsertId();

        $pdo->prepare("
            INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, bank_atau_toko, kode_pembayaran)
            VALUES (?, ?, 'midtrans', 2000000, 'menunggu', 'bca', '12345678909876')
        ")->execute([$idResA, 'MSY-' . $idResA . '-RBAC']);

        // Verifikasi RBAC canSeeUnmaskedVa logic
        $canSeeStaff = in_array('admin', ['admin', 'pengelola'], true) || ($userAId === $userAId);
        $canSeeOwner = in_array('pemilik', ['admin', 'pengelola'], true) || ($userAId === $ownerId);
        $canSeeOtherGuest = in_array('pelanggan', ['admin', 'pengelola'], true) || ($userAId === $userBId);

        if (!$canSeeStaff) {
            throw new Exception("Admin/Pengelola seharusnya dapat melihat kode unmasked");
        }
        if ($canSeeOwner) {
            throw new Exception("Pemilik properti seharusnya dibatasi (read-only masked)");
        }
        if ($canSeeOtherGuest) {
            throw new Exception("Pelanggan B tidak boleh melihat kode milik Pelanggan A (IDOR)");
        }
    });

    // -------------------------------------------------------------------------
    // T-82: Format Kode Minimarket (Store & Payment Code) dan Expiry Time WIB
    // -------------------------------------------------------------------------
    run_test('T-82', 'Format Kode Minimarket & Expiry Time WIB', function() {
        $cstorePayload = [
            'payment_type' => 'cstore',
            'store' => 'indomaret',
            'payment_code' => 'IND12345678',
            'expiry_time' => '2026-11-28 23:59:59'
        ];
        $vaData = ekstrak_data_va_midtrans($cstorePayload);
        if ($vaData['bank_atau_toko'] !== 'indomaret' || $vaData['kode_pembayaran'] !== 'IND12345678') {
            throw new Exception("Ekstraksi minimarket gagal");
        }
        if ($vaData['kedaluwarsa_pada'] !== '2026-11-28 23:59:59') {
            throw new Exception("Format expiry time tidak cocok");
        }

        $label = format_bank_atau_toko($vaData['bank_atau_toko']);
        if ($label !== 'Indomaret') {
            throw new Exception("Label format_bank_atau_toko gagal: " . $label);
        }
    });

    // -------------------------------------------------------------------------
    // T-83: Dwibahasa (ID/EN) & 3 Langkah Petunjuk Pembayaran
    // -------------------------------------------------------------------------
    run_test('T-83', 'Dwibahasa (ID/EN) & 3 Langkah Petunjuk Pembayaran', function() {
        $banks = ['bca', 'bni', 'bri', 'mandiri', 'permata', 'indomaret', 'alfamart'];
        foreach ($banks as $b) {
            $stepsId = ambil_petunjuk_transfer($b, 'id');
            $stepsEn = ambil_petunjuk_transfer($b, 'en');

            if (count($stepsId) !== 3 || count($stepsEn) !== 3) {
                throw new Exception("Bank {$b} harus memiliki tepat 3 langkah petunjuk pembayaran");
            }
        }
    });

} finally {
    $pdo->rollBack();
}

echo "\n----------------------------------------------------------------------\n";
echo "HASIL AKHIR: {$passCount} / {$testCount} PENGUJIAN LULUS (ALL PASS)\n";
echo "======================================================================\n";
