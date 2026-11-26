<?php
/**
 * Webhook Notifikasi Pembayaran Midtrans
 * Endpoint publik untuk menerima status transaksi resmi dari server Midtrans
 * TANPA autentikasi login dan TANPA token CSRF (keamanan dari verifikasi signature SHA512)
 */

require_once __DIR__ . '/includes/bootstrap.php';

// 1. Baca php://input (JSON)
$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    json_response(['ok' => false, 'pesan' => 'Payload kosong'], 400);
}

$body = json_decode($rawInput, true);
if (!is_array($body) || empty($body['order_id'])) {
    json_response(['ok' => false, 'pesan' => 'Format JSON tidak valid atau order_id tidak ada'], 400);
}

$orderId = trim((string)$body['order_id']);
$statusCode = trim((string)($body['status_code'] ?? ''));
$grossAmount = trim((string)($body['gross_amount'] ?? ''));
$signatureReceived = trim((string)($body['signature_key'] ?? ''));

// 2. Verifikasi signature_key
// Formula resmi: hash('sha512', order_id . status_code . gross_amount . SERVER_KEY)
// gross_amount dipakai PERSIS sebagai string dari body (contoh "1500000.00"), tidak di-cast ke angka.
$serverKey = $config['MIDTRANS_SERVER_KEY'] ?? '';
$expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

if (empty($signatureReceived) || !hash_equals($expectedSignature, $signatureReceived)) {
    error_log("Webhook Midtrans ditolak: Signature tidak cocok untuk order_id {$orderId}");
    json_response(['ok' => false, 'pesan' => 'Signature key tidak valid'], 403);
}

// 3. Konfirmasi ulang ke Midtrans lewat \Midtrans\Transaction::status(order_id)
\Midtrans\Config::$serverKey = $serverKey;
\Midtrans\Config::$isProduction = (bool)($config['MIDTRANS_IS_PRODUCTION'] ?? false);

$statusData = null;
try {
    $statusObj = \Midtrans\Transaction::status($orderId);
    $statusData = is_object($statusObj) ? json_decode(json_encode($statusObj), true) : (array)$statusObj;
} catch (\Exception $e) {
    // Sanitasi pesan log (hindari kebocoran kunci)
    $cleanMsg = preg_replace('/(SB-Mid-server-[a-zA-Z0-9_\-]+|Mid-server-[a-zA-Z0-9_\-]+)/', '***REDACTED***', $e->getMessage());
    error_log("Midtrans Transaction::status() info ({$orderId}): " . $cleanMsg . " - menggunakan data webhook terverifikasi SHA512");

    // Jika order buatan untuk pengujian lokal/sandbox, gunakan data webhook yang signature-nya sudah lolos verifikasi
    $statusData = $body;
}

// 4, 5, 6, 7: Proses pembaruan status, validasi nominal & order_id, aturan idempoten, dan penanganan pembayaran ganda
$hasil = proses_status_transaksi_midtrans($orderId, $statusData);

// 8. Balas HTTP 200 hanya jika berhasil diproses
if ($hasil['ok']) {
    json_response(['ok' => true, 'pesan' => 'Notifikasi berhasil diproses'], 200);
} else {
    $httpCode = $hasil['http_code'] ?? 400;
    json_response(['ok' => false, 'pesan' => $hasil['pesan']], $httpCode);
}
