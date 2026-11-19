<?php
/**
 * API: Blokir / Batalkan Blokir Tanggal Villa — Villa Joglo Masayu
 * Kebutuhan: Tahap 11-G.6
 * POST: buat blokir baru.
 * POST dengan _method=DELETE: batalkan blokir (soft cancel via dibatalkan_pada).
 * Hanya Pengelola/Admin. CSRF.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['pengelola', 'admin'], null);
header('Content-Type: application/json; charset=UTF-8');

csrf_verify();

$pdo    = get_db();
$userId = current_user_id();
$method = strtoupper(trim($_POST['_method'] ?? $_SERVER['REQUEST_METHOD']));

// ─── DELETE (batalkan blokir — soft cancel) ───────────────────────────────
if ($method === 'DELETE') {
    $idBlokir = (int)($_POST['id_blokir'] ?? 0);
    if (!$idBlokir) {
        json_response(['ok' => false, 'pesan' => t('block.error_id_wajib')], 400);
    }
    $stmt = $pdo->prepare("UPDATE blokir_tanggal SET dibatalkan_pada = NOW() WHERE id_blokir = ? AND dibatalkan_pada IS NULL");
    $stmt->execute([$idBlokir]);
    if ($stmt->rowCount() === 0) {
        json_response(['ok' => false, 'pesan' => t('block.error_not_found')], 404);
    }
    json_response(['ok' => true, 'pesan' => t('block.cancel_success')]);
}

// ─── POST (buat blokir baru) ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'pesan' => 'Metode tidak diizinkan.'], 405);
}

$idTipe  = (int)($_POST['id_tipe'] ?? 0);
$mulai   = trim($_POST['tanggal_mulai'] ?? '');
$selesai = trim($_POST['tanggal_selesai'] ?? '');
$alasan  = trim($_POST['alasan'] ?? '');

$errors = [];

if (!$idTipe) {
    $errors['id_tipe'] = t('block.error_tipe_wajib');
}
if (!$mulai || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $mulai)) {
    $errors['tanggal_mulai'] = t('block.error_mulai_wajib');
}
if (!$selesai || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selesai)) {
    $errors['tanggal_selesai'] = t('block.error_selesai_wajib');
}
if (!empty($mulai) && !empty($selesai) && $selesai <= $mulai) {
    $errors['tanggal_selesai'] = t('block.error_selesai_sebelum_mulai');
}
if (mb_strlen($alasan) < 2 || mb_strlen($alasan) > 200) {
    $errors['alasan'] = t('block.error_alasan');
}

if (!empty($errors)) {
    json_response(['ok' => false, 'pesan' => implode(' ', $errors), 'errors' => $errors], 422);
}

// Pastikan tipe villa ada
$stmtV = $pdo->prepare("SELECT nama FROM tipe_villa WHERE id_tipe = ? LIMIT 1");
$stmtV->execute([$idTipe]);
$villa = $stmtV->fetch(PDO::FETCH_ASSOC);
if (!$villa) {
    json_response(['ok' => false, 'pesan' => t('offline.error_tipe_invalid')], 404);
}

// Cek apakah rentang memotong reservasi 'dikonfirmasi' (tolak jika ya)
$stmtKonflik = $pdo->prepare("
    SELECT id_reservasi, tanggal_checkin, tanggal_checkout
    FROM reservasi
    WHERE id_tipe = ?
      AND status  = 'dikonfirmasi'
      AND tanggal_checkin  < ?
      AND tanggal_checkout > ?
    LIMIT 5
");
$stmtKonflik->execute([$idTipe, $selesai, $mulai]);
$konflik = $stmtKonflik->fetchAll(PDO::FETCH_ASSOC);

if (!empty($konflik)) {
    $listStr = implode(', ', array_map(
        fn($r) => "#RSV-{$r['id_reservasi']} ({$r['tanggal_checkin']} s.d. {$r['tanggal_checkout']})",
        $konflik
    ));
    json_response([
        'ok'    => false,
        'pesan' => t('block.error_overlap_reservasi', ['list' => $listStr])
    ], 409);
}

// Insert blokir
$stmtInsert = $pdo->prepare("
    INSERT INTO blokir_tanggal (id_tipe, tanggal_mulai, tanggal_selesai, alasan, dibuat_oleh, dibuat_pada)
    VALUES (?, ?, ?, ?, ?, NOW())
");
$stmtInsert->execute([$idTipe, $mulai, $selesai, $alasan, $userId]);
$idBlokir = (int)$pdo->lastInsertId();

json_response([
    'ok'       => true,
    'id_blokir' => $idBlokir,
    'pesan'    => t('block.success', [
        'villa'  => $villa['nama'],
        'mulai'  => $mulai,
        'selesai' => $selesai
    ])
]);
