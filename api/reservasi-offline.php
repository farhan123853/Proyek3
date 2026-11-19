<?php
/**
 * API: Catat Reservasi Offline (Walk-in, WhatsApp, Telepon) — Villa Joglo Masayu
 * Kebutuhan: Tahap 11-G.1
 * Hanya Pengelola/Admin. CSRF. Atomic transaction + FOR UPDATE.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['pengelola', 'admin'], null);
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'pesan' => 'Metode tidak diizinkan.'], 405);
}

csrf_verify();

$pdo    = get_db();
$userId = current_user_id();

// ─── Input ───────────────────────────────────────────────────────────────────
$sumber      = trim($_POST['sumber'] ?? '');
$namaTamu    = trim($_POST['nama_tamu'] ?? '');
$kontakTamu  = preg_replace('/[^0-9]/', '', trim($_POST['kontak_tamu'] ?? ''));
$idPelanggan = (int)($_POST['id_pelanggan'] ?? 0);
$idTipe      = (int)($_POST['id_tipe'] ?? 0);
$checkin     = trim($_POST['tanggal_checkin'] ?? '');
$checkout    = trim($_POST['tanggal_checkout'] ?? '');
$jumlahTamu  = (int)($_POST['jumlah_tamu'] ?? 1);
$catatan     = trim($_POST['catatan'] ?? '');
$catatTunai  = ($_POST['catat_tunai'] ?? '') === '1';
$konfirmasiConflict = ($_POST['konfirmasi_conflict'] ?? '0') === '1';

$errors = [];

// Validasi sumber
$sumberValid = ['walk_in', 'whatsapp', 'telepon'];
if (!in_array($sumber, $sumberValid, true)) {
    $errors['sumber'] = t('offline.error_sumber_invalid');
}

// Validasi nama tamu
if (mb_strlen($namaTamu) < 2 || mb_strlen($namaTamu) > 100) {
    $errors['nama_tamu'] = t('offline.error_nama_tamu');
}

// Validasi kontak tamu (9-15 digit)
if (!preg_match('/^\d{9,15}$/', $kontakTamu)) {
    $errors['kontak_tamu'] = t('offline.error_kontak_tamu');
}

// Validasi id_pelanggan opsional
if ($idPelanggan > 0) {
    $stmtP = $pdo->prepare("SELECT id_pengguna FROM pengguna WHERE id_pengguna = ? AND peran = 'pelanggan' AND aktif = 1 LIMIT 1");
    $stmtP->execute([$idPelanggan]);
    if (!$stmtP->fetch()) {
        $idPelanggan = 0; // abaikan jika tidak valid
    }
}

// Validasi tipe villa
$selectedVilla = null;
if ($idTipe > 0) {
    $stmtV = $pdo->prepare("SELECT * FROM tipe_villa WHERE id_tipe = ? LIMIT 1");
    $stmtV->execute([$idTipe]);
    $selectedVilla = $stmtV->fetch(PDO::FETCH_ASSOC);
}
if (!$selectedVilla) {
    $errors['id_tipe'] = t('offline.error_tipe_invalid');
}

// Validasi tanggal
$today = date('Y-m-d');
if (empty($checkin) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkin)) {
    $errors['tanggal_checkin'] = t('booking.error_checkin_wajib');
} elseif ($checkin < $today) {
    $errors['tanggal_checkin'] = t('booking.error_checkin_lampau');
}
if (empty($checkout) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkout)) {
    $errors['tanggal_checkout'] = t('booking.error_checkout_wajib');
} elseif (!empty($checkin) && $checkout <= $checkin) {
    $errors['tanggal_checkout'] = t('booking.error_checkout_sebelum_checkin');
}

// Validasi jumlah tamu vs kapasitas
if ($selectedVilla) {
    if ($jumlahTamu < 1) {
        $errors['jumlah_tamu'] = t('booking.error_tamu_min');
    } elseif ($jumlahTamu > (int)$selectedVilla['kapasitas_maks']) {
        $errors['jumlah_tamu'] = t('booking.error_tamu_max', ['maks' => $selectedVilla['kapasitas_maks']]);
    }
}

if (!empty($errors)) {
    json_response(['ok' => false, 'pesan' => implode(' ', $errors), 'errors' => $errors], 422);
}

// ─── Transaksi ───────────────────────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    // Kunci baris tipe villa sebagai mutex per tipe (FOR UPDATE)
    $stmtLock = $pdo->prepare("SELECT jumlah_unit FROM tipe_villa WHERE id_tipe = ? FOR UPDATE");
    $stmtLock->execute([$idTipe]);
    $stmtLock->fetch(); // pastikan terkunci

    // Periksa ketersediaan menggunakan hitung_ketersediaan() — satu sumber kebenaran
    $ketersediaan = hitung_ketersediaan($idTipe, $checkin, $checkout, $pdo);

    // Cek apakah ada malam yang PENUH
    $adaPenuh     = false;
    $tanggalPenuh = '';
    foreach ($ketersediaan['malam'] as $tgl => $malam) {
        if ($malam['status'] === 'penuh') {
            $adaPenuh     = true;
            $tanggalPenuh = $tgl;
            break;
        }
    }
    if ($adaPenuh) {
        $pdo->rollBack();
        json_response(['ok' => false, 'pesan' => t('booking.error_penuh', ['tanggal' => $tanggalPenuh])], 409);
    }

    // Cek apakah ada malam dengan permintaan MENUNGGU (peringatan, bukan blokir)
    $adaConflict     = false;
    $tanggalConflict = '';
    foreach ($ketersediaan['malam'] as $tgl => $malam) {
        if ($malam['menunggu'] > 0) {
            $adaConflict     = true;
            $tanggalConflict = $tgl;
            break;
        }
    }
    if ($adaConflict && !$konfirmasiConflict) {
        $pdo->rollBack();
        json_response([
            'ok'              => false,
            'perlu_konfirmasi' => true,
            'pesan'           => t('offline.warning_conflict', ['tanggal' => $tanggalConflict])
        ], 409);
    }

    // Hitung tarif snapshot (sama dengan reservasi online)
    $rincian = hitung_rincian_tarif(
        $checkin,
        $checkout,
        $jumlahTamu,
        (int)$selectedVilla['kapasitas_standar'],
        (int)$selectedVilla['kapasitas_maks'],
        (float)$selectedVilla['harga_weekday'],
        (float)$selectedVilla['harga_weekend'],
        (float)$selectedVilla['harga_extrabed']
    );

    if (!$rincian) {
        $pdo->rollBack();
        json_response(['ok' => false, 'pesan' => t('booking.error_hitung_tarif')], 500);
    }

    // Insert reservasi offline (langsung 'dikonfirmasi')
    $stmtInsert = $pdo->prepare("
        INSERT INTO reservasi (
            id_pelanggan, nama_tamu, kontak_tamu, sumber, dibuat_oleh,
            id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, catatan,
            status, harga_weekday_saat_pesan, harga_weekend_saat_pesan,
            harga_extrabed_saat_pesan, estimasi_total, dibuat_pada, diproses_oleh
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'dikonfirmasi', ?, ?, ?, ?, NOW(), ?)
    ");
    $stmtInsert->execute([
        $idPelanggan ?: null,
        $namaTamu,
        $kontakTamu,
        $sumber,
        $userId,
        $idTipe,
        $checkin,
        $checkout,
        $jumlahTamu,
        $catatan,
        $rincian['tarif_weekday'],
        $rincian['tarif_weekend'],
        $rincian['tarif_extrabed'],
        $rincian['estimasi_total'],
        $userId
    ]);
    $idReservasi = (int)$pdo->lastInsertId();

    // Jika catat_tunai=true, buat record pembayaran tunai ATOMIK
    if ($catatTunai) {
        $orderId    = 'CASH-' . $idReservasi . '-' . date('YmdHis');
        $stmtBayar = $pdo->prepare("
            INSERT INTO pembayaran (
                id_reservasi, order_id, sumber, jumlah, status,
                metode_kelompok, metode_detail, catatan, dicatat_oleh,
                dibuat_pada, dibayar_pada
            ) VALUES (?, ?, 'manual', ?, 'lunas', 'tunai', 'cash',
                      'Dicatat bersamaan saat reservasi offline', ?, NOW(), NOW())
        ");
        $stmtBayar->execute([
            $idReservasi,
            $orderId,
            (int)$rincian['estimasi_total'],
            $userId
        ]);
    }

    $pdo->commit();

    // Siapkan template WhatsApp
    $nomorWa  = preg_replace('/^0/', '62', $kontakTamu);
    $tglIn    = date('d/m/Y', strtotime($checkin));
    $tglOut   = date('d/m/Y', strtotime($checkout));
    $nomFmt   = format_rupiah($rincian['estimasi_total']);
    $waMsg    = "Halo Bapak/Ibu {$namaTamu}, reservasi Anda di Villa Joglo Masayu telah DIKONFIRMASI.\n"
              . "Detail:\n"
              . "- Tipe: {$selectedVilla['nama']}\n"
              . "- Check-in: {$tglIn}\n"
              . "- Check-out: {$tglOut}\n"
              . "- Tamu: {$jumlahTamu} orang\n"
              . "- Estimasi Total: {$nomFmt}\n"
              . "Terima kasih dan sampai jumpa! — Villa Joglo Masayu";
    $waLink   = "https://wa.me/{$nomorWa}?text=" . urlencode($waMsg);

    json_response([
        'ok'             => true,
        'id_reservasi'   => $idReservasi,
        'estimasi_total' => (int)$rincian['estimasi_total'],
        'wa_link'        => $waLink,
        'pesan'          => t('offline.success', ['kode' => "#RSV-{$idReservasi}"])
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("api/reservasi-offline.php error: " . $e->getMessage());
    json_response(['ok' => false, 'pesan' => t('common.error_server')], 500);
}
