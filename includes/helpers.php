<?php
/**
 * Helper Functions — Villa Joglo Masayu
 * Keamanan output, validasi, pemformatan, translasi i18n, dan perhitungan tarif
 */

/**
 * Escape output string untuk mencegah XSS
 */
function e(?string $string): string
{
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * Ambil atau inisialisasi bahasa aktif ('id' atau 'en')
 */
function get_active_lang(): string
{
    static $activeLang = null;
    if ($activeLang !== null) {
        return $activeLang;
    }

    $validLangs = ['id', 'en'];

    // 1. Cek parameter query ?lang=id atau ?lang=en
    if (isset($_GET['lang']) && in_array($_GET['lang'], $validLangs, true)) {
        $selected = $_GET['lang'];
        // Set cookie 1 tahun, SameSite=Lax
        setcookie('masayu_lang', $selected, [
            'expires'  => time() + (365 * 24 * 3600),
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => false, // bisa dibaca script.js
            'samesite' => 'Lax'
        ]);
        $_COOKIE['masayu_lang'] = $selected;

        // Sinkronkan ke database jika login
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION['user']['id_pengguna'])) {
            $_SESSION['user']['bahasa'] = $selected;
            try {
                $db = get_db();
                $stmt = $db->prepare("UPDATE pengguna SET bahasa = ? WHERE id_pengguna = ?");
                $stmt->execute([$selected, $_SESSION['user']['id_pengguna']]);
            } catch (Exception $e) {
                // Abaikan kesalahan DB saat update preferensi
            }
        }

        // Hapus param ?lang= dari query string dan redirect
        $queryParams = $_GET;
        unset($queryParams['lang']);
        $newQuery = http_build_query($queryParams);
        $requestUri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        $redirectUrl = $requestUri . ($newQuery ? '?' . $newQuery : '');
        header('Location: ' . $redirectUrl);
        exit;
    }

    // 2. Cek cookie masayu_lang
    if (!empty($_COOKIE['masayu_lang']) && in_array($_COOKIE['masayu_lang'], $validLangs, true)) {
        $activeLang = $_COOKIE['masayu_lang'];
        return $activeLang;
    }

    // 3. Cek preferensi pengguna yang sedang login
    if (!empty($_SESSION['user']['bahasa']) && in_array($_SESSION['user']['bahasa'], $validLangs, true)) {
        $activeLang = $_SESSION['user']['bahasa'];
        return $activeLang;
    }

    // Default bahasa Indonesia
    $activeLang = 'id';
    return $activeLang;
}

/**
 * Fungsi translasi string berdasar kamus dwibahasa
 */
function t(string $key, array $params = []): string
{
    static $dictionaries = [];

    $lang = get_active_lang();

    // Muat kamus aktif jika belum dimuat
    if (!isset($dictionaries[$lang])) {
        $file = __DIR__ . '/../lang/' . $lang . '.php';
        $dictionaries[$lang] = file_exists($file) ? require $file : [];
    }

    // Muat kamus fallback id jika belum dimuat
    if (!isset($dictionaries['id'])) {
        $fallbackFile = __DIR__ . '/../lang/id.php';
        $dictionaries['id'] = file_exists($fallbackFile) ? require $fallbackFile : [];
    }

    // Cari value berdasarkan dot notation
    $getValue = function($dict, $path) {
        $keys = explode('.', $path);
        $curr = $dict;
        foreach ($keys as $k) {
            if (!is_array($curr) || !isset($curr[$k])) {
                return null;
            }
            $curr = $curr[$k];
        }
        return is_string($curr) ? $curr : null;
    };

    $translation = $getValue($dictionaries[$lang], $key);

    // Fallback ke bahasa Indonesia jika tidak ditemukan
    if ($translation === null && $lang !== 'id') {
        $translation = $getValue($dictionaries['id'], $key);
    }

    // Jika tetap tidak ditemukan, kembalikan kuncinya sendiri
    if ($translation === null) {
        $translation = $key;
    }

    // Substitusi parameter {nama}
    if (!empty($params)) {
        foreach ($params as $paramKey => $paramVal) {
            $translation = str_replace('{' . $paramKey . '}', (string)$paramVal, $translation);
        }
    }

    return $translation;
}

/**
 * Format angka ke mata uang Rupiah
 */
function format_rupiah($angka, bool $denganPrefix = true): string
{
    $formatted = number_format((float)$angka, 0, ',', '.');
    return $denganPrefix ? 'Rp ' . $formatted : $formatted;
}

/**
 * Format tanggal YYYY-MM-DD ke format bahasa aktif
 */
function format_tanggal_id(?string $dateStr): string
{
    if (empty($dateStr)) {
        return '-';
    }
    $lang = get_active_lang();
    $bulanId = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $bulanEn = [
        1 => 'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    $dt = DateTime::createFromFormat('Y-m-d', substr($dateStr, 0, 10));
    if (!$dt) {
        return $dateStr;
    }
    $b = (int)$dt->format('n');
    $bulanText = ($lang === 'en') ? ($bulanEn[$b] ?? '') : ($bulanId[$b] ?? '');
    return $dt->format('j') . ' ' . $bulanText . ' ' . $dt->format('Y');
}

/**
 * Hitung rincian malam menginap dan estimasi tarif
 */
function hitung_rincian_tarif(
    string $checkin,
    string $checkout,
    int $jumlahTamu,
    int $kapasitasStd,
    int $kapasitasMaks,
    float $hargaWeekday,
    float $hargaWeekend,
    float $hargaExtrabed
): ?array {
    try {
        $dIn = new DateTime($checkin);
        $dOut = new DateTime($checkout);
    } catch (Exception $e) {
        return null;
    }

    if ($dOut <= $dIn) {
        return null;
    }

    if ($jumlahTamu > $kapasitasMaks || $jumlahTamu < 1) {
        return null;
    }

    $period = new DatePeriod($dIn, new DateInterval('P1D'), $dOut);
    $malamWeekday = 0;
    $malamWeekend = 0;

    foreach ($period as $dt) {
        $dayOfWeek = (int)$dt->format('N'); // 1 (Senin) s.d. 7 (Minggu)
        if ($dayOfWeek >= 1 && $dayOfWeek <= 4) {
            $malamWeekday++;
        } else {
            $malamWeekend++;
        }
    }

    $totalMalam = $malamWeekday + $malamWeekend;
    if ($totalMalam <= 0) {
        return null;
    }

    $extraBed = ($jumlahTamu > $kapasitasStd) ? ($jumlahTamu - $kapasitasStd) : 0;
    $biayaExtraBedPerMalam = $extraBed * $hargaExtrabed;
    $totalBiayaExtraBed = $biayaExtraBedPerMalam * $totalMalam;

    $subtotalWeekday = $malamWeekday * $hargaWeekday;
    $subtotalWeekend = $malamWeekend * $hargaWeekend;
    $estimasiTotal = $subtotalWeekday + $subtotalWeekend + $totalBiayaExtraBed;

    return [
        'total_malam'       => $totalMalam,
        'malam_weekday'     => $malamWeekday,
        'malam_weekend'     => $malamWeekend,
        'extra_bed'         => $extraBed,
        'tarif_weekday'     => $hargaWeekday,
        'tarif_weekend'     => $hargaWeekend,
        'tarif_extrabed'    => $hargaExtrabed,
        'subtotal_weekday'  => $subtotalWeekday,
        'subtotal_weekend'  => $subtotalWeekend,
        'total_extra_bed'   => $totalBiayaExtraBed,
        'estimasi_total'    => $estimasiTotal,
    ];
}

/**
 * Simpan pesan flash ke sesi
 */
function flash_set(string $tipe, string $pesan): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'][$tipe][] = $pesan;
}

/**
 * Ambil dan hapus pesan flash dari sesi
 */
function flash_get(?string $tipe = null): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if ($tipe !== null) {
        $messages = $_SESSION['flash'][$tipe] ?? [];
        unset($_SESSION['flash'][$tipe]);
        return $messages;
    }
    $all = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $all;
}

/**
 * Redirect HTTP ke path tertentu
 */
function redirect(string $path): void
{
    if (!preg_match('#^https?://#i', $path)) {
        $path = defined('BASE_URL') ? BASE_URL . ltrim($path, '/') : '/' . ltrim($path, '/');
    }
    header('Location: ' . $path);
    exit;
}

/**
 * Kirim response JSON lalu exit
 */
function json_response($data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Pemetaan kelompok metode pembayaran dari payment_type Midtrans atau manual
 * Sesuai dokumentasi resmi Midtrans (Snap & Core API)
 */
function kelompok_metode(?string $paymentType): string
{
    if (empty($paymentType)) {
        return 'lainnya';
    }

    $type = strtolower(trim($paymentType));

    // 1. Transfer Bank (Virtual Account / Mandiri Bill)
    $vaTypes = [
        'bank_transfer',
        'echannel',
        'permata',
        'permata_va',
        'bca_va',
        'bni_va',
        'bri_va',
        'cimb_va',
        'other_va'
    ];
    if (in_array($type, $vaTypes, true) || (strlen($type) > 3 && substr($type, -3) === '_va')) {
        return 'transfer_bank';
    }

    // 2. E-Wallet & QRIS
    $ewalletTypes = [
        'gopay',
        'shopeepay',
        'qris',
        'dana',
        'ovo',
        'linkaja'
    ];
    if (in_array($type, $ewalletTypes, true)) {
        return 'e_wallet';
    }

    // 3. Tunai / Manual Cash
    if (in_array($type, ['cash', 'tunai', 'manual'], true)) {
        return 'tunai';
    }

    // 4. Metode lainnya
    error_log("Peringatan: payment_type Midtrans tidak dikenal: " . $paymentType);
    return 'lainnya';
}

/**
 * Format status reservasi mengikuti bahasa aktif
 */
function format_status_reservasi(string $status): string
{
    switch ($status) {
        case 'menunggu':
            return t('my_bookings.status_menunggu');
        case 'dikonfirmasi':
            return t('my_bookings.status_dikonfirmasi');
        case 'ditolak':
            return t('my_bookings.status_ditolak');
        case 'selesai':
            return t('my_bookings.status_selesai');
        case 'dibatalkan':
            return t('my_bookings.status_dibatalkan');
        default:
            return ucfirst($status);
    }
}

/**
 * Format nama metode pembayaran mengikuti bahasa aktif
 */
function format_metode_pembayaran(?string $metodeKelompok): string
{
    switch ($metodeKelompok) {
        case 'transfer_bank':
            return t('payment.method_transfer_bank');
        case 'e_wallet':
            return t('payment.method_e_wallet');
        case 'tunai':
            return t('payment.method_tunai');
        case 'lainnya':
        default:
            return t('payment.method_lainnya');
    }
}

/**
 * Render komponen badge status pembayaran dengan ikon + teks (aksesibel, tidak bergantung warna saja)
 */
function render_badge_pembayaran(string $status, bool $isStaff = false): string
{
    switch ($status) {
        case 'lunas':
            $label = t('payment.badge_lunas');
            $icon = '<svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
            return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200" data-payment-status="lunas">' . $icon . '<span>' . e($label) . '</span></span>';

        case 'menunggu':
            $label = t('payment.badge_menunggu');
            $icon = '<svg class="w-3.5 h-3.5 text-amber-600 shrink-0 animate-spin-slow" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
            return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200" data-payment-status="menunggu">' . $icon . '<span>' . e($label) . '</span></span>';

        case 'gagal':
            $label = t('payment.badge_gagal');
            $icon = '<svg class="w-3.5 h-3.5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>';
            return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-800 border border-rose-200" data-payment-status="gagal">' . $icon . '<span>' . e($label) . '</span></span>';

        case 'kedaluwarsa':
            $label = t('payment.badge_kedaluwarsa');
            $icon = '<svg class="w-3.5 h-3.5 text-slate-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
            return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-300" data-payment-status="kedaluwarsa">' . $icon . '<span>' . e($label) . '</span></span>';

        case 'ganda':
            if (!$isStaff) return '';
            $label = t('payment.badge_ganda');
            $icon = '<svg class="w-3.5 h-3.5 text-purple-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>';
            return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-50 text-purple-800 border border-purple-300" data-payment-status="ganda">' . $icon . '<span>' . e($label) . '</span></span>';

        case 'dibatalkan':
            if (!$isStaff) return '';
            $label = t('payment.badge_dibatalkan');
            $icon = '<svg class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>';
            return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 border border-gray-300" data-payment-status="dibatalkan">' . $icon . '<span>' . e($label) . '</span></span>';

        case 'belum_dibayar':
        default:
            $label = t('payment.badge_belum_dibayar');
            $icon = '<svg class="w-3.5 h-3.5 text-stone-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h8"/></svg>';
            return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-stone-100 text-stone-700 border border-stone-300" data-payment-status="belum_dibayar">' . $icon . '<span>' . e($label) . '</span></span>';
    }
}
function status_bayar_efektif(int $idReservasi): array
{
    $db = get_db();
    
    // 1. Cek apakah ada pembayaran 'lunas'
    $stmt = $db->prepare("SELECT * FROM pembayaran WHERE id_reservasi = ? AND status = 'lunas' LIMIT 1");
    $stmt->execute([$idReservasi]);
    $lunas = $stmt->fetch();
    if ($lunas) {
        return ['status' => 'lunas', 'pembayaran' => $lunas];
    }

    // 2. Cek apakah ada pembayaran 'menunggu'
    $stmt = $db->prepare("SELECT * FROM pembayaran WHERE id_reservasi = ? AND status = 'menunggu' ORDER BY id_pembayaran DESC LIMIT 1");
    $stmt->execute([$idReservasi]);
    $menunggu = $stmt->fetch();
    if ($menunggu) {
        return ['status' => 'menunggu', 'pembayaran' => $menunggu];
    }

    // 3. Cek pembayaran terakhir lainnya (gagal, kedaluwarsa, dsb)
    $stmt = $db->prepare("SELECT * FROM pembayaran WHERE id_reservasi = ? ORDER BY id_pembayaran DESC LIMIT 1");
    $stmt->execute([$idReservasi]);
    $terakhir = $stmt->fetch();
    if ($terakhir) {
        return ['status' => $terakhir['status'], 'pembayaran' => $terakhir];
    }

    return ['status' => 'belum_dibayar', 'pembayaran' => null];
}

/**
 * Pemetaan status transaksi Midtrans ke status internal sistem
 */
function petakan_status_midtrans(string $transactionStatus, ?string $fraudStatus = null): string
{
    $txStatus = strtolower(trim($transactionStatus));
    $fraud = strtolower(trim((string)$fraudStatus));

    if ($txStatus === 'settlement' || ($txStatus === 'capture' && $fraud === 'accept')) {
        return 'lunas';
    }
    if ($txStatus === 'pending') {
        return 'menunggu';
    }
    if ($txStatus === 'expire') {
        return 'kedaluwarsa';
    }
    if (in_array($txStatus, ['deny', 'cancel', 'failure', 'failed'], true)) {
        return 'gagal';
    }

    error_log("Status Midtrans tidak dikenal: " . $transactionStatus);
    return 'menunggu';
}

/**
 * Proses pembaruan status pembayaran dari payload/objek Midtrans secara idempoten dan aman
 */
function proses_status_transaksi_midtrans(string $orderId, array $statusData): array
{
    global $config;
    $db = get_db();

    $transactionStatus = $statusData['transaction_status'] ?? '';
    $fraudStatus = $statusData['fraud_status'] ?? null;
    $paymentType = $statusData['payment_type'] ?? '';
    $transactionId = $statusData['transaction_id'] ?? null;
    $settlementTime = $statusData['settlement_time'] ?? null;
    $grossAmount = $statusData['gross_amount'] ?? null;

    $statusInternal = petakan_status_midtrans($transactionStatus, $fraudStatus);

    $db->beginTransaction();
    try {
        // 1. Kunci dan validasi baris pembayaran
        $stmtP = $db->prepare("SELECT id_pembayaran, id_reservasi, order_id, sumber, jumlah, status FROM pembayaran WHERE order_id = ? FOR UPDATE");
        $stmtP->execute([$orderId]);
        $currP = $stmtP->fetch();

        if (!$currP) {
            $db->rollBack();
            return ['ok' => false, 'pesan' => 'Order ID tidak ditemukan di basis data', 'http_code' => 400];
        }

        // 2. Validasi kesesuaian gross_amount jika tersedia
        if ($grossAmount !== null && $grossAmount !== '') {
            $nominalExpected = (int)$currP['jumlah'];
            $nominalReceived = (int)round((float)$grossAmount);
            if ($nominalExpected !== $nominalReceived) {
                $db->rollBack();
                error_log("Validasi nominal gagal pada {$orderId}: diharapkan {$nominalExpected}, diterima {$nominalReceived}");
                return ['ok' => false, 'pesan' => 'Nominal transaksi tidak cocok dengan database', 'http_code' => 400];
            }
        }

        // 3. Kunci baris reservasi terkait
        $stmtR = $db->prepare("SELECT id_reservasi, status FROM reservasi WHERE id_reservasi = ? FOR UPDATE");
        $stmtR->execute([$currP['id_reservasi']]);
        $reservasi = $stmtR->fetch();

        // 4. Idempotensi: jika baris pembayaran ini SUDAH 'lunas', jangan turunkan statusnya
        if ($currP['status'] === 'lunas') {
            $db->commit();
            return ['ok' => true, 'status' => 'lunas', 'pesan' => 'Pembayaran sudah lunas (idempoten)', 'http_code' => 200];
        }

        // 5. Kasus status baru adalah 'lunas'
        if ($statusInternal === 'lunas') {
            // Cek apakah ada pembayaran 'lunas' LAIN pada reservasi yang sama (misal tunai yang dicatat Pengelola)
            $stmtLunasLain = $db->prepare("
                SELECT id_pembayaran, order_id, sumber, metode_kelompok, jumlah 
                FROM pembayaran 
                WHERE id_reservasi = ? AND status = 'lunas' AND id_pembayaran != ?
                LIMIT 1
            ");
            $stmtLunasLain->execute([$currP['id_reservasi'], $currP['id_pembayaran']]);
            $lunasLain = $stmtLunasLain->fetch();

            $metodeKelompok = kelompok_metode($paymentType);
            $dibayarPada = $settlementTime ? date('Y-m-d H:i:s', strtotime($settlementTime)) : date('Y-m-d H:i:s');

            if ($lunasLain) {
                // KASUS PEMBAYARAN GANDA:
                // Ditandai 'ganda' agar tidak dihitung dua kali sebagai pemasukan & tampil 'perlu ditinjau' di admin
                $catatanGanda = "Pembayaran ganda: reservasi sudah lunas via " . $lunasLain['sumber'] . " (" . $lunasLain['order_id'] . "). Perlu ditinjau.";
                $stmtUpdate = $db->prepare("
                    UPDATE pembayaran 
                    SET status = 'ganda',
                        metode_kelompok = ?,
                        metode_detail = ?,
                        transaction_id = ?,
                        catatan = ?,
                        dibayar_pada = ?
                    WHERE id_pembayaran = ?
                ");
                $stmtUpdate->execute([$metodeKelompok, $paymentType, $transactionId, $catatanGanda, $dibayarPada, $currP['id_pembayaran']]);
                error_log("PERINGATAN PEMBAYARAN GANDA: order_id {$orderId} tiba saat reservasi {$currP['id_reservasi']} sudah lunas.");
                $db->commit();
                return ['ok' => true, 'status' => 'ganda', 'pesan' => 'Pembayaran ganda terdeteksi (perlu ditinjau)', 'http_code' => 200];
            } else {
                // Pembayaran lunas pertama yang sah
                $stmtUpdate = $db->prepare("
                    UPDATE pembayaran 
                    SET status = 'lunas',
                        metode_kelompok = ?,
                        metode_detail = ?,
                        transaction_id = ?,
                        dibayar_pada = ?
                    WHERE id_pembayaran = ?
                ");
                $stmtUpdate->execute([$metodeKelompok, $paymentType, $transactionId, $dibayarPada, $currP['id_pembayaran']]);
                $db->commit();
                return ['ok' => true, 'status' => 'lunas', 'pesan' => 'Pembayaran berhasil dikonfirmasi lunas', 'http_code' => 200];
            }
        } else {
            // Status non-lunas ('menunggu', 'kedaluwarsa', 'gagal')
            // Jangan mengubah status jika sudah 'lunas' atau 'ganda'
            if (!in_array($currP['status'], ['lunas', 'ganda'], true)) {
                $metodeKelompok = !empty($paymentType) ? kelompok_metode($paymentType) : null;
                $stmtUpdate = $db->prepare("
                    UPDATE pembayaran 
                    SET status = ?,
                        metode_kelompok = COALESCE(?, metode_kelompok),
                        metode_detail = COALESCE(?, metode_detail),
                        transaction_id = COALESCE(?, transaction_id)
                    WHERE id_pembayaran = ?
                ");
                $stmtUpdate->execute([$statusInternal, $metodeKelompok, $paymentType, $transactionId, $currP['id_pembayaran']]);
            }
            $db->commit();
            return ['ok' => true, 'status' => $statusInternal, 'pesan' => 'Status pembayaran: ' . $statusInternal, 'http_code' => 200];
        }
    } catch (\Exception $e) {
        $db->rollBack();
        error_log("Kesalahan basis data saat update status pembayaran {$orderId}: " . $e->getMessage());
        return ['ok' => false, 'pesan' => 'Terjadi kesalahan basis data internal', 'http_code' => 500];
    }
}

/**
 * Helper Sinkron Pembayaran (Plan B jika webhook belum/tidak tembus)
 * Memanggil Midtrans \Midtrans\Transaction::status() lalu menerapkan pemetaan & validasi
 */
function sinkron_pembayaran(string $orderId): array
{
    global $config;
    $db = get_db();

    // 1. Cek keberadaan order di DB
    $stmt = $db->prepare("SELECT id_pembayaran, status, sumber, jumlah FROM pembayaran WHERE order_id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $pembayaran = $stmt->fetch();
    if (!$pembayaran) {
        return ['ok' => false, 'pesan' => 'Order ID tidak ditemukan', 'http_code' => 404];
    }

    if ($pembayaran['sumber'] !== 'midtrans') {
        return ['ok' => true, 'status' => $pembayaran['status'], 'pesan' => 'Bukan transaksi Midtrans (manual)', 'http_code' => 200];
    }

    // Jika sudah lunas, tidak perlu melakukan panggilan jaringan ulang (idempoten & hemat kuota)
    if ($pembayaran['status'] === 'lunas') {
        return ['ok' => true, 'status' => 'lunas', 'pesan' => 'Pembayaran sudah berstatus lunas', 'http_code' => 200];
    }

    // 2. Hubungi API Midtrans
    \Midtrans\Config::$serverKey = $config['MIDTRANS_SERVER_KEY'] ?? '';
    \Midtrans\Config::$isProduction = (bool)($config['MIDTRANS_IS_PRODUCTION'] ?? false);

    try {
        $statusObj = \Midtrans\Transaction::status($orderId);
    } catch (\Exception $e) {
        $cleanMsg = preg_replace('/(SB-Mid-server-[a-zA-Z0-9_\-]+|Mid-server-[a-zA-Z0-9_\-]+)/', '***REDACTED***', $e->getMessage());
        error_log("Midtrans Status Check Error ({$orderId}): " . $cleanMsg);
        return ['ok' => false, 'pesan' => 'Gagal menghubungi server Midtrans: ' . $cleanMsg, 'http_code' => 502];
    }

    $statusData = [
        'transaction_status' => is_object($statusObj) ? ($statusObj->transaction_status ?? '') : ($statusObj['transaction_status'] ?? ''),
        'fraud_status'       => is_object($statusObj) ? ($statusObj->fraud_status ?? null) : ($statusObj['fraud_status'] ?? null),
        'payment_type'       => is_object($statusObj) ? ($statusObj->payment_type ?? '') : ($statusObj['payment_type'] ?? ''),
        'transaction_id'     => is_object($statusObj) ? ($statusObj->transaction_id ?? null) : ($statusObj['transaction_id'] ?? null),
        'settlement_time'    => is_object($statusObj) ? ($statusObj->settlement_time ?? null) : ($statusObj['settlement_time'] ?? null),
        'gross_amount'       => is_object($statusObj) ? ($statusObj->gross_amount ?? null) : ($statusObj['gross_amount'] ?? null),
    ];

    return proses_status_transaksi_midtrans($orderId, $statusData);
}
