<?php
/**
 * API: Detail Pembayaran (Struk / Resi & Rincian Tagihan)
 * Endpoint read-only JSON dengan proteksi autentikasi dan anti-IDOR
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// 1. Hanya menerima metode GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'pesan' => 'Method not allowed'], 405);
}

// 2. Wajib login
if (!is_logged_in()) {
    json_response(['ok' => false, 'pesan' => t('payment.unauthorized')], 401);
}

// 3. Validasi ID reservasi (integer positif)
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'id_reservasi', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    json_response(['ok' => false, 'pesan' => 'ID reservasi tidak valid.'], 400);
}

$db = get_db();
$userRole = current_role();
$userId = (int)current_user_id();
$isStaff = in_array($userRole, ['admin', 'pengelola', 'pemilik'], true);
// Full unmasked VA code is visible to admin, pengelola, and the reservation owner.
// Pemilik (owner) is read-only and sees masked code.
$canSeeUnmaskedVa = in_array($userRole, ['admin', 'pengelola'], true) || ((int)($resv['id_pelanggan'] ?? 0) === $userId);

// 4. Ambil data reservasi
$stmtResv = $db->prepare("
    SELECT r.*,
           tk.nama AS nama_villa,
           tk.harga_weekday, tk.harga_weekend,
           p.nama AS nama_pelanggan, p.email AS email_pelanggan, p.kontak AS kontak_pelanggan,
           staf.nama AS nama_staf_proses
    FROM reservasi r
    LEFT JOIN tipe_villa tk ON r.id_tipe = tk.id_tipe
    LEFT JOIN pengguna p ON r.id_pelanggan = p.id_pengguna
    LEFT JOIN pengguna staf ON r.diproses_oleh = staf.id_pengguna
    WHERE r.id_reservasi = ?
    LIMIT 1
");
$stmtResv->execute([$id]);
$resv = $stmtResv->fetch(PDO::FETCH_ASSOC);

if (!$resv) {
    json_response(['ok' => false, 'pesan' => t('payment.not_found')], 404);
}

// 5. Anti-IDOR Protection: Pelanggan HANYA boleh melihat reservasi miliknya sendiri
if (!$isStaff && (int)$resv['id_pelanggan'] !== $userId) {
    json_response(['ok' => false, 'pesan' => t('payment.not_found')], 404);
}

// 6. Ambil semua baris pembayaran terkait
$stmtBayar = $db->prepare("
    SELECT pb.*, staf.nama AS pencatat_nama
    FROM pembayaran pb
    LEFT JOIN pengguna staf ON pb.dicatat_oleh = staf.id_pengguna
    WHERE pb.id_reservasi = ?
    ORDER BY pb.id_pembayaran DESC
");
$stmtBayar->execute([$id]);
$allPayments = $stmtBayar->fetchAll(PDO::FETCH_ASSOC);

$eval = evaluasi_status_bayar($allPayments);
$statusBayar = $eval['status'];
$pembayaranUtama = $eval['pembayaran'];
$isLunas = ($statusBayar === 'lunas');

// Durasi & Tanggal
$checkinTs = strtotime($resv['tanggal_checkin']);
$checkoutTs = strtotime($resv['tanggal_checkout']);
$malam = max(1, (int)round(($checkoutTs - $checkinTs) / 86400));

// Tentukan data tamu (Online vs Offline)
$sumberR = $resv['sumber_reservasi'] ?? ($resv['sumber'] ?? 'online');
if (in_array($sumberR, ['walk_in', 'whatsapp', 'telepon'], true)) {
    $guestName = !empty($resv['nama_tamu']) ? $resv['nama_tamu'] : ($resv['nama_pelanggan'] ?? 'Tamu Walk-in');
    $guestContact = !empty($resv['kontak_tamu']) ? $resv['kontak_tamu'] : ($resv['kontak_pelanggan'] ?? '-');
} else {
    $guestName = $resv['nama_pelanggan'] ?? ($resv['nama_tamu'] ?? 'Tamu');
    $guestContact = $resv['kontak_pelanggan'] ?? ($resv['kontak_tamu'] ?? '-');
}

// Rincian pembayaran
$globalConfig = $config ?? [];
$isSandbox = false;
$payData = null;

if ($pembayaranUtama) {
    if (($pembayaranUtama['sumber'] ?? '') === 'midtrans') {
        $isSandbox = !($globalConfig['MIDTRANS_IS_PRODUCTION'] ?? false);
    }

    // Nomor referensi / transaksi (disamarkan jika panjang)
    $txId = $pembayaranUtama['transaction_id'] ?? '';
    $refRingkas = $txId;
    if (strlen($txId) > 16) {
        $refRingkas = substr($txId, 0, 8) . '...' . substr($txId, -4);
    }

    $waktuBayarFmt = !empty($pembayaranUtama['dibayar_pada']) 
        ? date('d/m/Y H:i', strtotime($pembayaranUtama['dibayar_pada'])) . ' WIB' 
        : '-';

    $rawKode = $pembayaranUtama['kode_pembayaran'] ?? null;
    $displayedKode = $canSeeUnmaskedVa ? $rawKode : samarkan_kode_pembayaran($rawKode);

    $payData = [
        'id_pembayaran' => (int)$pembayaranUtama['id_pembayaran'],
        'order_id' => $pembayaranUtama['order_id'] ?? '',
        'sumber' => $pembayaranUtama['sumber'] ?? 'manual',
        'status' => $pembayaranUtama['status'] ?? 'menunggu',
        'metode_kelompok' => $pembayaranUtama['metode_kelompok'] ?? '',
        'metode_label' => format_metode_pembayaran($pembayaranUtama['metode_kelompok'] ?? ''),
        'metode_detail' => $pembayaranUtama['metode_detail'] ?? '',
        'bank_atau_toko' => $pembayaranUtama['bank_atau_toko'] ?? null,
        'bank_atau_toko_label' => format_bank_atau_toko($pembayaranUtama['bank_atau_toko'] ?? null),
        'kode_pembayaran' => $displayedKode,
        'kode_pembayaran_fmt' => format_nomor_va($displayedKode, $pembayaranUtama['bank_atau_toko'] ?? null),
        'kode_pembayaran_mask' => samarkan_kode_pembayaran($rawKode),
        'kedaluwarsa_pada' => $pembayaranUtama['kedaluwarsa_pada'] ?? null,
        'kedaluwarsa_pada_fmt' => !empty($pembayaranUtama['kedaluwarsa_pada']) ? date('d/m/Y H:i', strtotime($pembayaranUtama['kedaluwarsa_pada'])) . ' WIB' : null,
        'petunjuk_transfer' => !empty($pembayaranUtama['bank_atau_toko']) ? ambil_petunjuk_transfer($pembayaranUtama['bank_atau_toko'], current_lang()) : [],
        'jumlah' => (int)($pembayaranUtama['jumlah'] ?? 0),
        'jumlah_fmt' => format_rupiah($pembayaranUtama['jumlah'] ?? 0),
        'dibayar_pada' => $pembayaranUtama['dibayar_pada'],
        'dibayar_pada_fmt' => $waktuBayarFmt,
        'transaction_id' => $txId,
        'transaction_ref' => $refRingkas,
        'dicatat_oleh' => $pembayaranUtama['pencatat_nama'] ?? null,
        'catatan' => $pembayaranUtama['catatan'] ?? null,
        'is_sandbox' => $isSandbox,
    ];
}

// Riwayat percobaan pembayaran (tanpa membocorkan token / kunci rahasia)
$riwayat = [];
$attemptNo = count($allPayments);
foreach ($allPayments as $pb) {
    $rawHistKode = $pb['kode_pembayaran'] ?? null;
    $displayedHistKode = $canSeeUnmaskedVa ? $rawHistKode : samarkan_kode_pembayaran($rawHistKode);
    $riwayat[] = [
        'attempt_no' => $attemptNo--,
        'id_pembayaran' => (int)$pb['id_pembayaran'],
        'order_id' => $pb['order_id'],
        'sumber' => $pb['sumber'],
        'metode_kelompok' => $pb['metode_kelompok'],
        'metode_label' => format_metode_pembayaran($pb['metode_kelompok'] ?? ''),
        'bank_atau_toko' => $pb['bank_atau_toko'] ?? null,
        'bank_atau_toko_label' => format_bank_atau_toko($pb['bank_atau_toko'] ?? null),
        'kode_pembayaran' => $displayedHistKode,
        'kode_pembayaran_fmt' => format_nomor_va($displayedHistKode, $pb['bank_atau_toko'] ?? null),
        'kode_pembayaran_mask' => samarkan_kode_pembayaran($rawHistKode),
        'kedaluwarsa_pada' => $pb['kedaluwarsa_pada'] ?? null,
        'kedaluwarsa_pada_fmt' => !empty($pb['kedaluwarsa_pada']) ? date('d/m/Y H:i', strtotime($pb['kedaluwarsa_pada'])) . ' WIB' : '-',
        'jumlah' => (int)$pb['jumlah'],
        'jumlah_fmt' => format_rupiah($pb['jumlah']),
        'status' => $pb['status'],
        'status_badge' => render_badge_pembayaran($pb['status'], false),
        'dibuat_pada' => $pb['dibuat_pada'],
        'dibuat_pada_fmt' => date('d/m/Y H:i', strtotime($pb['dibuat_pada'])) . ' WIB',
        'dibayar_pada' => $pb['dibayar_pada'],
        'dibayar_pada_fmt' => !empty($pb['dibayar_pada']) ? date('d/m/Y H:i', strtotime($pb['dibayar_pada'])) . ' WIB' : '-',
        'dicatat_oleh' => $pb['pencatat_nama'] ?? null,
    ];
}

// WhatsApp link generator (bilingual berdasarkan preferensi bahasa atau sistem)
$cleanWa = preg_replace('/^0/', '62', preg_replace('/[^\d]/', '', $guestContact));
$tamuLang = $resv['bahasa'] ?? current_lang();
$isTamuEn = ($tamuLang === 'en');

if ($isLunas) {
    if ($isTamuEn) {
        $waText = "Hello {$guestName},\nHere is your official payment proof for reservation *#RSV-{$id}* ({$resv['nama_villa']}):\nStatus: *PAID (LUNAS)*\nTotal: " . format_rupiah($resv['estimasi_total']) . "\nMethod: " . ($payData['metode_label'] ?? 'Cash') . "\nTime: " . ($payData['dibayar_pada_fmt'] ?? '-') . "\n\nThank you for choosing Villa Joglo Masayu.";
    } else {
        $waText = "Halo Bapak/Ibu {$guestName},\nBerikut bukti pembayaran resmi untuk reservasi *#RSV-{$id}* ({$resv['nama_villa']}):\nStatus: *LUNAS*\nTotal: " . format_rupiah($resv['estimasi_total']) . "\nMetode: " . ($payData['metode_label'] ?? 'Tunai') . "\nWaktu: " . ($payData['dibayar_pada_fmt'] ?? '-') . "\n\nTerima kasih atas kepercayaannya di Villa Joglo Masayu.";
    }
} else {
    $bankInfo = !empty($payData['bank_atau_toko_label']) && $payData['bank_atau_toko_label'] !== '-' ? $payData['bank_atau_toko_label'] : 'Transfer Bank';
    $kodeVaInfo = !empty($payData['kode_pembayaran']) ? $payData['kode_pembayaran'] : '-';
    $deadlineInfo = !empty($payData['kedaluwarsa_pada_fmt']) ? $payData['kedaluwarsa_pada_fmt'] : date('d/m/Y H:i', strtotime('+24 hours')) . ' WIB';

    if ($isTamuEn) {
        $waText = "Hello {$guestName},\nYour reservation *#RSV-{$id}* ({$resv['nama_villa']}) has been confirmed and is awaiting payment.\nTotal Amount: *" . format_rupiah($resv['estimasi_total']) . "*\nBank/Method: {$bankInfo}\nVA Code: *{$kodeVaInfo}*\nPayment Deadline: {$deadlineInfo}\n\nPlease complete your transfer before the deadline. Thank you.";
    } else {
        $waText = "Halo Bapak/Ibu {$guestName},\nReservasi Anda *#RSV-{$id}* ({$resv['nama_villa']}) telah dikonfirmasi dan menunggu pembayaran.\nTotal Tagihan: *" . format_rupiah($resv['estimasi_total']) . "*\nBank/Metode: {$bankInfo}\nKode VA: *{$kodeVaInfo}*\nBatas Waktu: {$deadlineInfo}\n\nSilakan selesaikan pembayaran sebelum batas waktu berakhir. Terima kasih.";
    }
}
$waLink = "https://wa.me/{$cleanWa}?text=" . rawurlencode($waText);

    $hargaPerMalam = (int)($resv['harga_weekday_saat_pesan'] ?? $resv['harga_weekday'] ?? 0);
    $payload = [
    'id_reservasi' => $id,
    'kode_reservasi' => 'RSV-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT),
    'status_reservasi' => $resv['status'],
    'status_bayar' => $statusBayar,
    'is_lunas' => $isLunas,
    'sumber_reservasi' => $sumberR,
    'tamu' => [
        'nama' => $guestName,
        'kontak' => $guestContact,
        'email' => $resv['email_pelanggan'] ?? '-',
    ],
    'villa' => [
        'nama' => $resv['nama_villa'] ?? 'Villa Joglo Masayu',
        'harga_per_malam' => $hargaPerMalam,
        'harga_per_malam_fmt' => format_rupiah($hargaPerMalam),
    ],
    'jadwal' => [
        'checkin' => $resv['tanggal_checkin'],
        'checkout' => $resv['tanggal_checkout'],
        'checkin_fmt' => date('d/m/Y', $checkinTs),
        'checkout_fmt' => date('d/m/Y', $checkoutTs),
        'malam' => $malam,
        'jumlah_tamu' => (int)$resv['jumlah_tamu'],
    ],
    'keuangan' => [
        'estimasi_total' => (int)$resv['estimasi_total'],
        'estimasi_total_fmt' => format_rupiah($resv['estimasi_total']),
        'total_dibayar' => $isLunas ? (int)($payData['jumlah'] ?? $resv['estimasi_total']) : 0,
        'total_dibayar_fmt' => format_rupiah($isLunas ? (int)($payData['jumlah'] ?? $resv['estimasi_total']) : 0),
        'sisa_tagihan' => $isLunas ? 0 : (int)$resv['estimasi_total'],
        'sisa_tagihan_fmt' => format_rupiah($isLunas ? 0 : (int)$resv['estimasi_total']),
    ],
    'pembayaran' => $payData,
    'riwayat' => $riwayat,
    'wa_link' => $waLink,
    'url_struk' => 'struk-pembayaran.php?id=' . $id,
];

json_response(['ok' => true, 'data' => $payload], 200);
