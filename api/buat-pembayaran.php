<?php
/**
 * API: Buat Transaksi Pembayaran Midtrans Snap
 * Endpoint aman untuk menghasilkan Snap Token bagi reservasi terkonfirmasi
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// 1. Hanya menerima metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'pesan' => 'Method not allowed'], 405);
}

// 2. Wajib login peran Pelanggan
if (!is_logged_in() || current_user()['peran'] !== 'pelanggan') {
    json_response(['ok' => false, 'pesan' => t('payment.unauthorized')], 403);
}

// 3. Verifikasi CSRF token
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
$jsonData = null;
if (!$csrfToken) {
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);
    $csrfToken = $jsonData['csrf_token'] ?? null;
    if ($jsonData && isset($jsonData['id_reservasi'])) {
        $_POST['id_reservasi'] = $jsonData['id_reservasi'];
    }
    if ($jsonData && isset($jsonData['ganti_metode'])) {
        $_POST['ganti_metode'] = $jsonData['ganti_metode'];
    }
}

if (!csrf_token_valid($csrfToken)) {
    json_response(['ok' => false, 'pesan' => t('payment.csrf_invalid')], 403);
}

// 4. Validasi ID Reservasi
$idReservasi = isset($_POST['id_reservasi']) ? (int)$_POST['id_reservasi'] : 0;
if ($idReservasi <= 0) {
    json_response(['ok' => false, 'pesan' => t('payment.not_found')], 404);
}

$idPelanggan = (int)current_user()['id_pengguna'];
$db = get_db();

// 5. Query prepared: reservasi harus milik pelanggan yang login DAN status='dikonfirmasi'
// Menggunakan respons generik 404 bila tidak cocok untuk mencegah IDOR dan enumerasi ID
$stmt = $db->prepare("
    SELECT r.*, v.nama AS nama_villa, u.nama AS nama_tamu, u.email AS email_tamu, u.kontak AS kontak_tamu
    FROM reservasi r
    JOIN tipe_villa v ON r.id_tipe = v.id_tipe
    JOIN pengguna u ON r.id_pelanggan = u.id_pengguna
    WHERE r.id_reservasi = ? AND r.id_pelanggan = ? AND r.status = 'dikonfirmasi'
    LIMIT 1
");
$stmt->execute([$idReservasi, $idPelanggan]);
$reservasi = $stmt->fetch();

if (!$reservasi) {
    json_response(['ok' => false, 'pesan' => t('payment.not_found')], 404);
}

// 6. Tolak jika sudah ada pembayaran berstatus 'lunas'
$stmt = $db->prepare("SELECT id_pembayaran FROM pembayaran WHERE id_reservasi = ? AND status = 'lunas' LIMIT 1");
$stmt->execute([$idReservasi]);
if ($stmt->fetch()) {
    json_response(['ok' => false, 'pesan' => t('payment.already_paid')], 400);
}

$nominal = (int)round((float)$reservasi['estimasi_total']);
$batasJam = (int)($config['PEMBAYARAN_BATAS_JAM'] ?? 24);
$gantiMetode = !empty($_POST['ganti_metode']);

// Selaraskan sisa waktu pembayaran dengan batas_bayar_pada
$batasBayarPada = $reservasi['batas_bayar_pada'] ?? null;
if (!empty($batasBayarPada)) {
    $tsBatas = strtotime($batasBayarPada);
    $sisaDetik = $tsBatas - time();
} else {
    $sisaDetik = $batasJam * 3600;
}

$sisaMenit = (int)floor($sisaDetik / 60);
$maxMenit = $batasJam * 60;
$durasiMenit = min($maxMenit, $sisaMenit);

// Midtrans menetapkan durasi minimal dalam satuan menit (minimal 5 menit)
// Tolak pembuatan transaksi jika sisa waktu kurang dari 5 menit atau sudah lewat batas
if ($durasiMenit < 5 || $sisaDetik <= 0) {
    json_response(['ok' => false, 'pesan' => t('payment.deadline_passed_error')], 400);
}

// 7. Cek apakah ada transaksi Midtrans 'menunggu' yang masih aktif dalam rentang batas menit
$stmt = $db->prepare("
    SELECT id_pembayaran, order_id, snap_token, dibuat_pada
    FROM pembayaran
    WHERE id_reservasi = ? AND status = 'menunggu' AND sumber = 'midtrans' AND snap_token IS NOT NULL
      AND dibuat_pada >= (NOW() - INTERVAL ? MINUTE)
    ORDER BY id_pembayaran DESC
    LIMIT 1
");
$stmt->execute([$idReservasi, $durasiMenit]);
$existing = $stmt->fetch();

if ($existing && !empty($existing['snap_token'])) {
    if (!$gantiMetode) {
        json_response([
            'ok' => true,
            'token' => $existing['snap_token'],
            'order_id' => $existing['order_id'],
            'reused' => true
        ]);
    } else {
        // Pengguna meminta ganti metode/bank:
        // Batalkan/expire transaksi lama di server Midtrans jika memungkinkan
        try {
            \Midtrans\Config::$serverKey = $config['MIDTRANS_SERVER_KEY'] ?? '';
            \Midtrans\Config::$isProduction = (bool)($config['MIDTRANS_IS_PRODUCTION'] ?? false);
            \Midtrans\Transaction::cancel($existing['order_id']);
        } catch (\Exception $e) {
            try {
                \Midtrans\Transaction::expire($existing['order_id']);
            } catch (\Exception $ex) {
                // Abaikan jika order belum terdaftar di Core API Midtrans
            }
        }
        // Tandai transaksi lama sebagai 'kedaluwarsa' di database lokal
        $stmtExpire = $db->prepare("
            UPDATE pembayaran 
            SET status = 'kedaluwarsa',
                catatan = CONCAT(COALESCE(catatan, ''), CASE WHEN catatan IS NULL OR catatan = '' THEN '' ELSE ' | ' END, '[Diganti metode baru]')
            WHERE id_pembayaran = ?
        ");
        $stmtExpire->execute([$existing['id_pembayaran']]);
    }
}

// 8. Buat order_id baru: 'MSY-{id_reservasi}-{timestamp}' (panjang <= 50 karakter)
$orderId = 'MSY-' . $idReservasi . '-' . time();

// 9. Konfigurasi Midtrans SDK
$statusKunci = midtrans_kunci_siap();
if (!$statusKunci['server_siap']) {
    catat_midtrans_log('CONFIG_ERROR', 'api/buat-pembayaran.php', "Server Key belum siap: " . $statusKunci['server_alasan']);
    json_response(['ok' => false, 'pesan' => t('payment.snap_error')], 502);
}

\Midtrans\Config::$serverKey = $config['MIDTRANS_SERVER_KEY'] ?? (defined('MIDTRANS_SERVER_KEY') ? MIDTRANS_SERVER_KEY : '');
\Midtrans\Config::$isProduction = (bool)($config['MIDTRANS_IS_PRODUCTION'] ?? (defined('MIDTRANS_IS_PRODUCTION') ? MIDTRANS_IS_PRODUCTION : false));
\Midtrans\Config::$isSanitized = true;
\Midtrans\Config::$is3ds = true;

// Parameter Snap sesuai dokumentasi resmi
$customerDetails = [
    'first_name' => mb_substr($reservasi['nama_tamu'] ?: 'Tamu', 0, 50, 'UTF-8'),
    'email'      => mb_substr($reservasi['email_tamu'] ?: '', 0, 50, 'UTF-8'),
];
$cleanPhone = preg_replace('/[^0-9+]/', '', (string)($reservasi['kontak_tamu'] ?? ''));
if (!empty($cleanPhone)) {
    $customerDetails['phone'] = mb_substr($cleanPhone, 0, 19, 'UTF-8');
}

$params = [
    'transaction_details' => [
        'order_id' => $orderId,
        'gross_amount' => $nominal,
    ],
    'item_details' => [
        [
            'id' => 'VILLA-' . $reservasi['id_tipe'],
            'price' => $nominal,
            'quantity' => 1,
            'name' => mb_substr('Sewa ' . $reservasi['nama_villa'], 0, 50, 'UTF-8'),
        ]
    ],
    'customer_details' => $customerDetails,
    'expiry' => [
        'unit' => 'minute',
        'duration' => $durasiMenit,
    ],
    // Dibatasi hanya VA bank dan e-wallet (tanpa kartu kredit)
    'enabled_payments' => [
        'bca_va', 'echannel', 'bni_va', 'bri_va', 'permata_va', 'cimb_va', 'other_va',
        'gopay', 'shopeepay', 'qris'
    ],
];

try {
    $snapToken = \Midtrans\Snap::getSnapToken($params);
} catch (Exception $e) {
    $rawMsg = $e->getMessage();
    $code = $e->getCode();
    $exClass = get_class($e);

    // Sanitasi pesan error: pastikan tidak ada kebocoran Server Key maupun Client Key ke log server
    $cleanMsg = preg_replace('/(SB-Mid-[a-zA-Z0-9_\-]+|Mid-server-[a-zA-Z0-9_\-]+|Mid-client-[a-zA-Z0-9_\-]+)/', '***REDACTED***', $rawMsg);

    // Kategori diagnosa untuk mempermudah pelacakan tanpa mengekspos rahasia
    $diagnosa = 'Kesalahan API Midtrans';
    if ($code === 401 || strpos($rawMsg, '401') !== false) {
        $diagnosa = 'Autentikasi Gagal (401 Unauthorized) - Server Key di config/config.php tidak valid atau belum diganti dengan kunci Sandbox/Production resmi Midtrans';
    } elseif ($code === 400 || strpos($rawMsg, '400') !== false) {
        $diagnosa = 'Validasi Parameter Gagal (400 Bad Request) - Periksa skema customer_details, item_details, atau gross_amount';
    } elseif (stripos($rawMsg, 'ssl') !== false || stripos($rawMsg, 'certificate') !== false) {
        $diagnosa = 'Koneksi SSL Gagal - Periksa sertifikat CA cURL di php.ini (curl.cainfo)';
    }

    $logDetail = sprintf(
        "OrderID: %s | ID Reservasi: %d | Nominal: %d | Code: %s | Diagnosa: %s | Detail: %s",
        $orderId,
        $idReservasi,
        $nominal,
        $code ?: 'N/A',
        $diagnosa,
        $cleanMsg
    );

    // Tulis ke tmp/midtrans-error.log
    catat_midtrans_log('SNAP_ERROR', 'api/buat-pembayaran.php', $logDetail, $exClass);
    error_log("[Midtrans Snap Error] " . $logDetail);

    json_response(['ok' => false, 'pesan' => t('payment.snap_error')], 502);
}

// 10. Simpan baris pembayaran baru ke database
$stmt = $db->prepare("
    INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, snap_token)
    VALUES (?, ?, 'midtrans', ?, 'menunggu', ?)
");
$stmt->execute([$idReservasi, $orderId, $nominal, $snapToken]);

json_response([
    'ok' => true,
    'token' => $snapToken,
    'order_id' => $orderId
]);

