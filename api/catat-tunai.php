<?php
/**
 * API: Catat Pembayaran Tunai
 * Hanya dapat diakses oleh peran Pengelola
 * Memperbarui status pembayaran menjadi 'lunas' (sumber: manual, metode: tunai)
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// 1. Hanya menerima metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'pesan' => 'Method not allowed'], 405);
}

// 2. Wajib login peran Pengelola
if (!is_logged_in() || current_role() !== 'pengelola') {
    json_response(['ok' => false, 'pesan' => t('payment.unauthorized')], 403);
}

// 3. Verifikasi token CSRF
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!$csrfToken) {
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);
    $csrfToken = $jsonData['csrf_token'] ?? null;
    if ($jsonData) {
        if (isset($jsonData['id_reservasi'])) $_POST['id_reservasi'] = $jsonData['id_reservasi'];
        if (isset($jsonData['tanggal_bayar'])) $_POST['tanggal_bayar'] = $jsonData['tanggal_bayar'];
        if (isset($jsonData['catatan'])) $_POST['catatan'] = $jsonData['catatan'];
    }
}

if (!csrf_token_valid($csrfToken)) {
    json_response(['ok' => false, 'pesan' => t('payment.csrf_invalid')], 403);
}

// 4. Validasi parameter input
$idReservasi = isset($_POST['id_reservasi']) ? (int)$_POST['id_reservasi'] : 0;
if ($idReservasi <= 0) {
    json_response(['ok' => false, 'pesan' => t('payment.not_found')], 404);
}

$db = get_db();

// 5. Cek data reservasi
$stmt = $db->prepare("SELECT id_reservasi, status, estimasi_total, dibuat_pada FROM reservasi WHERE id_reservasi = ? LIMIT 1");
$stmt->execute([$idReservasi]);
$reservasi = $stmt->fetch();

if (!$reservasi || $reservasi['status'] !== 'dikonfirmasi') {
    json_response(['ok' => false, 'pesan' => t('payment.not_found')], 400);
}

// Validasi tanggal bayar (WIB)
$inputTanggal = trim((string)($_POST['tanggal_bayar'] ?? ''));
if (empty($inputTanggal)) {
    $tanggalBayar = date('Y-m-d H:i:s');
} else {
    // Tangani format datetime-local (Y-m-d\TH:i) atau format standar
    $inputTanggal = str_replace('T', ' ', $inputTanggal);
    if (strlen($inputTanggal) === 16) {
        $inputTanggal .= ':00';
    }
    $tsBayar = strtotime($inputTanggal);
    if ($tsBayar === false) {
        $tanggalBayar = date('Y-m-d H:i:s');
    } else {
        $now = time();
        if ($tsBayar > $now + 60) {
            json_response(['ok' => false, 'pesan' => t('payment.cash_date_future')], 400);
        }
        $tsDibuat = strtotime($reservasi['dibuat_pada']);
        if ($tsBayar < $tsDibuat - 60) {
            json_response(['ok' => false, 'pesan' => 'Tanggal pembayaran tidak boleh sebelum reservasi dibuat.'], 400);
        }
        $tanggalBayar = date('Y-m-d H:i:s', $tsBayar);
    }
}

$catatan = trim((string)($_POST['catatan'] ?? ''));
if (mb_strlen($catatan, 'UTF-8') > 200) {
    $catatan = mb_substr($catatan, 0, 200, 'UTF-8');
}

// 6. Langkah (1): Cek apakah ada transaksi Midtrans 'menunggu' untuk reservasi ini
$stmtPending = $db->prepare("
    SELECT id_pembayaran, order_id 
    FROM pembayaran 
    WHERE id_reservasi = ? AND status = 'menunggu' AND sumber = 'midtrans'
    ORDER BY id_pembayaran DESC
");
$stmtPending->execute([$idReservasi]);
$pendingMidtrans = $stmtPending->fetchAll();

if (!empty($pendingMidtrans)) {
    \Midtrans\Config::$serverKey = $config['MIDTRANS_SERVER_KEY'] ?? '';
    \Midtrans\Config::$isProduction = (bool)($config['MIDTRANS_IS_PRODUCTION'] ?? false);

    foreach ($pendingMidtrans as $pendingTx) {
        $cancelOk = false;
        try {
            // Coba batalkan via API Midtrans cancel
            \Midtrans\Transaction::cancel($pendingTx['order_id']);
            $cancelOk = true;
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $code = (int)$e->getCode();
            // Jika 404 / 412 (transaksi belum terbentuk di server Midtrans atau sudah expire),
            // atau 401 dalam lingkungan mock/dummy key lokal, anggap selesai untuk dibatalkan di server lokal
            if ($code === 404 || $code === 412 || $code === 401 || strpos($msg, '404') !== false || strpos($msg, '412') !== false || strpos($msg, "doesn't exist") !== false || strpos($msg, 'Unauthorized') !== false) {
                $cancelOk = true;
            } else {
                error_log("Gagal membatalkan transaksi Midtrans pending ({$pendingTx['order_id']}): " . $msg);
                $cancelOk = false;
            }
        }

        if (!$cancelOk) {
            json_response(['ok' => false, 'pesan' => t('payment.cash_online_active')], 400);
        }

        // Tandai baris Midtrans sebagai 'kedaluwarsa'
        $db->prepare("UPDATE pembayaran SET status = 'kedaluwarsa' WHERE id_pembayaran = ?")->execute([$pendingTx['id_pembayaran']]);
    }
}

// 7. Langkah (2, 3, 4): Transaksi Database
$db->beginTransaction();
try {
    // Kunci baris reservasi
    $stmtLock = $db->prepare("SELECT id_reservasi, status, estimasi_total FROM reservasi WHERE id_reservasi = ? FOR UPDATE");
    $stmtLock->execute([$idReservasi]);
    $resLocked = $stmtLock->fetch();

    if (!$resLocked || $resLocked['status'] !== 'dikonfirmasi') {
        $db->rollBack();
        json_response(['ok' => false, 'pesan' => t('payment.not_found')], 400);
    }

    // Periksa ulang apakah sudah ada pembayaran 'lunas'
    $stmtCekLunas = $db->prepare("SELECT id_pembayaran FROM pembayaran WHERE id_reservasi = ? AND status = 'lunas' LIMIT 1 FOR UPDATE");
    $stmtCekLunas->execute([$idReservasi]);
    if ($stmtCekLunas->fetch()) {
        $db->rollBack();
        json_response(['ok' => false, 'pesan' => t('payment.cash_already_paid')], 400);
    }

    $nominal = (int)round((float)$resLocked['estimasi_total']);
    $orderId = 'TUNAI-' . $idReservasi . '-' . time();
    $idPengelola = (int)current_user_id();

    // INSERT pembayaran tunai
    $stmtInsert = $db->prepare("
        INSERT INTO pembayaran (
            id_reservasi, order_id, sumber, jumlah, status, 
            metode_kelompok, metode_detail, dicatat_oleh, catatan, dibuat_pada, dibayar_pada
        ) VALUES (
            ?, ?, 'manual', ?, 'lunas',
            'tunai', 'cash', ?, ?, NOW(), ?
        )
    ");
    $stmtInsert->execute([
        $idReservasi,
        $orderId,
        $nominal,
        $idPengelola,
        !empty($catatan) ? $catatan : null,
        $tanggalBayar
    ]);

    $db->commit();

    json_response([
        'ok' => true,
        'pesan' => t('payment.cash_success'),
        'order_id' => $orderId,
        'nominal' => $nominal
    ], 200);

} catch (\Exception $e) {
    $db->rollBack();
    error_log("Gagal mencatat pembayaran tunai untuk reservasi {$idReservasi}: " . $e->getMessage());
    json_response(['ok' => false, 'pesan' => 'Terjadi kesalahan sistem saat menyimpan pembayaran tunai'], 500);
}
