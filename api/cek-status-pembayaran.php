<?php
/**
 * API: Cek Status Pembayaran (Sinkronisasi Manual / Plan B)
 * Memanggil status resmi Midtrans dari server ke server
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// 1. Hanya menerima metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'pesan' => 'Method not allowed'], 405);
}

// 2. Wajib login
if (!is_logged_in()) {
    json_response(['ok' => false, 'pesan' => t('payment.unauthorized')], 403);
}

// 3. Verifikasi CSRF token
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!$csrfToken) {
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);
    $csrfToken = $jsonData['csrf_token'] ?? null;
    if ($jsonData && isset($jsonData['order_id'])) {
        $_POST['order_id'] = $jsonData['order_id'];
    }
}

if (!csrf_token_valid($csrfToken)) {
    json_response(['ok' => false, 'pesan' => t('payment.csrf_invalid')], 403);
}

// 4. Ambil parameter order_id
$orderId = trim((string)($_POST['order_id'] ?? ''));
if (empty($orderId)) {
    json_response(['ok' => false, 'pesan' => 'Order ID wajib disertakan'], 400);
}

$db = get_db();
$userRole = current_role();
$userId = (int)current_user_id();

// 5. Cek kepemilikan pembayaran: Pelanggan hanya boleh mengecek transaksinya sendiri
$stmt = $db->prepare("
    SELECT p.id_pembayaran, p.id_reservasi, p.order_id, p.sumber, p.status, r.id_pelanggan
    FROM pembayaran p
    JOIN reservasi r ON p.id_reservasi = r.id_reservasi
    WHERE p.order_id = ?
    LIMIT 1
");
$stmt->execute([$orderId]);
$dataP = $stmt->fetch();

if (!$dataP) {
    json_response(['ok' => false, 'pesan' => t('payment.not_found')], 404);
}

if ($userRole === 'pelanggan' && (int)$dataP['id_pelanggan'] !== $userId) {
    // IDOR protection: respons generik 404
    json_response(['ok' => false, 'pesan' => t('payment.not_found')], 404);
}

// 6. Jalankan helper sinkron_pembayaran
$hasil = sinkron_pembayaran($orderId);
$httpCode = $hasil['http_code'] ?? ($hasil['ok'] ? 200 : 400);

json_response($hasil, $httpCode);
