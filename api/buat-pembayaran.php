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
if (!$csrfToken) {
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);
    $csrfToken = $jsonData['csrf_token'] ?? null;
    if ($jsonData && isset($jsonData['id_reservasi'])) {
        $_POST['id_reservasi'] = $jsonData['id_reservasi'];
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

// 7. Cek apakah ada transaksi Midtrans 'menunggu' yang masih aktif dalam rentang batas jam
// Keputusan: jika ada snap_token aktif yang masih dalam rentang waktu berlaku, pakai ulang token
// untuk efisiensi dan menghindari pembuatan order ganda di Midtrans
$stmt = $db->prepare("
    SELECT id_pembayaran, order_id, snap_token, dibuat_pada
    FROM pembayaran
    WHERE id_reservasi = ? AND status = 'menunggu' AND sumber = 'midtrans' AND snap_token IS NOT NULL
      AND dibuat_pada >= (NOW() - INTERVAL ? HOUR)
    ORDER BY id_pembayaran DESC
    LIMIT 1
");
$stmt->execute([$idReservasi, $batasJam]);
$existing = $stmt->fetch();

if ($existing && !empty($existing['snap_token'])) {
    json_response([
        'ok' => true,
        'token' => $existing['snap_token'],
        'order_id' => $existing['order_id'],
        'reused' => true
    ]);
}

// 8. Buat order_id baru: 'MSY-{id_reservasi}-{timestamp}' (panjang <= 50 karakter)
$orderId = 'MSY-' . $idReservasi . '-' . time();

// 9. Konfigurasi Midtrans SDK
\Midtrans\Config::$serverKey = $config['MIDTRANS_SERVER_KEY'] ?? '';
\Midtrans\Config::$isProduction = (bool)($config['MIDTRANS_IS_PRODUCTION'] ?? false);
\Midtrans\Config::$isSanitized = true;
\Midtrans\Config::$is3ds = true;

// Parameter Snap sesuai dokumentasi resmi
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
    'customer_details' => [
        'first_name' => $reservasi['nama_tamu'],
        'email' => $reservasi['email_tamu'],
        'phone' => $reservasi['kontak_tamu'],
    ],
    'expiry' => [
        'unit' => 'hour',
        'duration' => $batasJam,
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
    // Sanitasi pesan log: pastikan tidak ada kebocoran Server Key
    $errMsg = preg_replace('/(SB-Mid-server-[a-zA-Z0-9_\-]+|Mid-server-[a-zA-Z0-9_\-]+)/', '***REDACTED***', $e->getMessage());
    error_log("Midtrans Snap Error: " . $errMsg);
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

