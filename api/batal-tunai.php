<?php
/**
 * API: Batalkan Catatan Pembayaran Tunai
 * HANYA peran Admin
 * Mengubah status pembayaran menjadi 'dibatalkan' dengan alasan wajib (<= 200 karakter)
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// 1. Hanya menerima metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'pesan' => 'Method not allowed'], 405);
}

// 2. Wajib login peran Admin
if (!is_logged_in() || current_role() !== 'admin') {
    json_response(['ok' => false, 'pesan' => t('payment.unauthorized')], 403);
}

// 3. Verifikasi token CSRF
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!$csrfToken) {
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);
    $csrfToken = $jsonData['csrf_token'] ?? null;
    if ($jsonData) {
        if (isset($jsonData['id_pembayaran'])) $_POST['id_pembayaran'] = $jsonData['id_pembayaran'];
        if (isset($jsonData['alasan_batal'])) $_POST['alasan_batal'] = $jsonData['alasan_batal'];
    }
}

if (!csrf_token_valid($csrfToken)) {
    json_response(['ok' => false, 'pesan' => t('payment.csrf_invalid')], 403);
}

// 4. Validasi ID Pembayaran
$idPembayaran = isset($_POST['id_pembayaran']) ? (int)$_POST['id_pembayaran'] : 0;
if ($idPembayaran <= 0) {
    json_response(['ok' => false, 'pesan' => 'ID pembayaran tidak valid'], 400);
}

// 5. Validasi Alasan Batal (Wajib, <= 200 karakter)
$alasanBatal = trim((string)($_POST['alasan_batal'] ?? ''));
if (empty($alasanBatal)) {
    json_response(['ok' => false, 'pesan' => t('payment.reason_required')], 400);
}
if (mb_strlen($alasanBatal, 'UTF-8') > 200) {
    $alasanBatal = mb_substr($alasanBatal, 0, 200, 'UTF-8');
}

$db = get_db();

$db->beginTransaction();
try {
    // Kunci baris pembayaran yang akan dibatalkan
    $stmt = $db->prepare("SELECT * FROM pembayaran WHERE id_pembayaran = ? FOR UPDATE");
    $stmt->execute([$idPembayaran]);
    $pembayaran = $stmt->fetch();

    if (!$pembayaran) {
        $db->rollBack();
        json_response(['ok' => false, 'pesan' => 'Data pembayaran tidak ditemukan'], 404);
    }

    // Hanya pembayaran tunai (manual) berstatus 'lunas' yang dapat dibatalkan melalui fitur ini
    if ($pembayaran['sumber'] !== 'manual' || $pembayaran['status'] !== 'lunas') {
        $db->rollBack();
        json_response(['ok' => false, 'pesan' => 'Hanya pembayaran tunai lunas yang dapat dibatalkan.'], 400);
    }

    $idAdmin = (int)current_user_id();

    // Perbarui status menjadi 'dibatalkan', catat dibatalkan_oleh dan alasan_batal
    // Kolom lunas_key (generated column) otomatis menjadi NULL sehingga reservasi dapat dibayar kembali
    $stmtUpdate = $db->prepare("
        UPDATE pembayaran 
        SET status = 'dibatalkan',
            dibatalkan_oleh = ?,
            alasan_batal = ?
        WHERE id_pembayaran = ?
    ");
    $stmtUpdate->execute([$idAdmin, $alasanBatal, $idPembayaran]);

    $db->commit();

    json_response([
        'ok' => true,
        'pesan' => t('payment.cancel_cash_success'),
        'id_pembayaran' => $idPembayaran
    ], 200);

} catch (\Exception $e) {
    $db->rollBack();
    error_log("Gagal membatalkan catatan tunai ID {$idPembayaran}: " . $e->getMessage());
    json_response(['ok' => false, 'pesan' => 'Terjadi kesalahan sistem saat membatalkan catatan tunai'], 500);
}
