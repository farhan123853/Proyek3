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
 * Evaluasi status pembayaran efektif dari sekumpulan baris pembayaran (in-memory)
 * Mengutamakan status 'lunas', lalu 'menunggu' terbaru, lalu status percobaan terakhir, atau 'belum_dibayar'
 */
function evaluasi_status_bayar(array $pembayaranList): array
{
    if (empty($pembayaranList)) {
        return ['status' => 'belum_dibayar', 'pembayaran' => null];
    }

    // 1. Cek apakah ada pembayaran 'lunas'
    foreach ($pembayaranList as $p) {
        if (($p['status'] ?? '') === 'lunas') {
            return ['status' => 'lunas', 'pembayaran' => $p];
        }
    }

    // 2. Cek apakah ada pembayaran 'menunggu' (ambil yang terbaru / pertama jika DESC)
    foreach ($pembayaranList as $p) {
        if (($p['status'] ?? '') === 'menunggu') {
            return ['status' => 'menunggu', 'pembayaran' => $p];
        }
    }

    // 3. Status terakhir lainnya (ambil elemen pertama karena list terurut DESC)
    $terakhir = reset($pembayaranList);
    return ['status' => $terakhir['status'] ?? 'belum_dibayar', 'pembayaran' => $terakhir];
}

/**
 * Mengambil semua pembayaran untuk daftar id_reservasi dalam satu query (mencegah N+1)
 *
 * @param PDO $pdo
 * @param array $idReservasiList
 * @return array [id_reservasi => ['status' => ..., 'pembayaran' => ..., 'history' => [...]]]
 */
function ambil_pembayaran_batch(PDO $pdo, array $idReservasiList): array
{
    $cleanIds = array_values(array_unique(array_filter(array_map('intval', $idReservasiList))));
    if (empty($cleanIds)) {
        return [];
    }

    $inPlaceholders = implode(',', array_fill(0, count($cleanIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM pembayaran WHERE id_reservasi IN ($inPlaceholders) ORDER BY id_pembayaran DESC");
    $stmt->execute($cleanIds);
    $allPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $grouped = [];
    foreach ($cleanIds as $id) {
        $grouped[$id] = [];
    }
    foreach ($allPayments as $p) {
        $idRes = (int)$p['id_reservasi'];
        $grouped[$idRes][] = $p;
    }

    $result = [];
    foreach ($grouped as $idRes => $list) {
        $eval = evaluasi_status_bayar($list);
        $result[$idRes] = [
            'status' => $eval['status'],
            'pembayaran' => $eval['pembayaran'],
            'history' => $list,
        ];
    }
    return $result;
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
 * Ekstrak data Virtual Account (VA) atau kode pembayaran dari respons Midtrans
 * Menangani:
 * - standard VA: va_numbers (BCA, BNI, BRI, CIMB, dll.)
 * - Mandiri Bill: payment_type = 'echannel' (biller_code / bill_key)
 * - Permata VA: permata_va_number
 * - Minimarket: payment_type = 'cstore' (store, payment_code)
 * - Expiry: expiry_time
 */
function ekstrak_data_va_midtrans($statusData): array
{
    if (is_object($statusData)) {
        $statusData = json_decode(json_encode($statusData), true);
    }
    if (!is_array($statusData)) {
        return [
            'bank_atau_toko'   => null,
            'kode_pembayaran'  => null,
            'kedaluwarsa_pada' => null,
        ];
    }

    $bankAtauToko = null;
    $kodePembayaran = null;
    $kedaluwarsaPada = null;

    $paymentType = strtolower((string)($statusData['payment_type'] ?? ''));

    // 1. Standard VA (va_numbers)
    if (!empty($statusData['va_numbers']) && is_array($statusData['va_numbers'])) {
        $firstVa = reset($statusData['va_numbers']);
        if (is_array($firstVa)) {
            $bankAtauToko = strtolower((string)($firstVa['bank'] ?? ''));
            $kodePembayaran = trim((string)($firstVa['va_number'] ?? ''));
        }
    }

    // 2. Mandiri Bill (echannel)
    if ((empty($kodePembayaran) || $paymentType === 'echannel') && (!empty($statusData['bill_key']) || !empty($statusData['biller_code']))) {
        $bankAtauToko = 'mandiri';
        $billerCode = trim((string)($statusData['biller_code'] ?? ''));
        $billKey = trim((string)($statusData['bill_key'] ?? ''));
        if ($billerCode !== '' && $billKey !== '') {
            $kodePembayaran = $billerCode . ' / ' . $billKey;
        } elseif ($billKey !== '') {
            $kodePembayaran = $billKey;
        }
    }

    // 3. Permata VA
    if (empty($kodePembayaran) && !empty($statusData['permata_va_number'])) {
        $bankAtauToko = 'permata';
        $kodePembayaran = trim((string)$statusData['permata_va_number']);
    }

    // 4. Minimarket / Convenience Store (cstore)
    if ((empty($kodePembayaran) || $paymentType === 'cstore') && !empty($statusData['payment_code'])) {
        $bankAtauToko = strtolower((string)($statusData['store'] ?? 'cstore'));
        $kodePembayaran = trim((string)$statusData['payment_code']);
    }

    // 5. Expiry time
    if (!empty($statusData['expiry_time'])) {
        $ts = strtotime((string)$statusData['expiry_time']);
        if ($ts !== false) {
            $kedaluwarsaPada = date('Y-m-d H:i:s', $ts);
        }
    }

    return [
        'bank_atau_toko'   => $bankAtauToko ?: null,
        'kode_pembayaran'  => $kodePembayaran ?: null,
        'kedaluwarsa_pada' => $kedaluwarsaPada ?: null,
    ];
}

/**
 * Cek apakah ada pembayaran 'menunggu' lain dengan bank_atau_toko dan kode_pembayaran yang sama
 */
function cek_duplikat_kode(PDO $db, string $bankAtauToko, string $kodePembayaran, string $orderIdSaatIni): ?array
{
    if (empty($bankAtauToko) || empty($kodePembayaran)) {
        return null;
    }
    $stmt = $db->prepare("
        SELECT id_pembayaran, order_id, id_reservasi, status, dibuat_pada
        FROM pembayaran
        WHERE bank_atau_toko = ?
          AND kode_pembayaran = ?
          AND status = 'menunggu'
          AND order_id != ?
        LIMIT 1
    ");
    $stmt->execute([$bankAtauToko, $kodePembayaran, $orderIdSaatIni]);
    $dup = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($dup) {
        error_log("PERINGATAN DUPLIKAT KODE VA: {$bankAtauToko} {$kodePembayaran} sudah aktif di order {$dup['order_id']} saat diproses untuk {$orderIdSaatIni}");
        return $dup;
    }
    return null;
}

/**
 * Format nama bank atau toko untuk tampilan manusia
 */
function format_bank_atau_toko(?string $bankAtauToko): string
{
    if (empty($bankAtauToko)) {
        return '-';
    }
    $key = strtolower(trim($bankAtauToko));
    $map = [
        'bca'        => 'Bank BCA',
        'bni'        => 'Bank BNI',
        'bri'        => 'Bank BRI',
        'mandiri'    => 'Bank Mandiri',
        'permata'    => 'Bank Permata',
        'cimb'       => 'Bank CIMB Niaga',
        'indomaret'  => 'Indomaret',
        'alfamart'   => 'Alfamart',
        'gopay'      => 'GoPay',
        'shopeepay'  => 'ShopeePay',
        'qris'       => 'QRIS',
    ];
    return $map[$key] ?? strtoupper($bankAtauToko);
}

/**
 * Format nomor VA dengan spasi per 4 digit agar mudah dibaca pengguna
 * Khusus echannel Mandiri (biller_code / bill_key) tetap diformat terpisah.
 */
function format_nomor_va(?string $kode, ?string $bankAtauToko = null): string
{
    if (empty($kode)) {
        return '-';
    }
    $kode = trim($kode);
    if (strpos($kode, '/') !== false) {
        return $kode;
    }
    if (preg_match('/^[0-9A-Za-z]+$/', $kode)) {
        return trim(chunk_split($kode, 4, ' '));
    }
    return $kode;
}

/**
 * Samarkan kode pembayaran: tampilkan hanya 4 karakter terakhir (••••4567)
 */
function samarkan_kode_pembayaran(?string $kode): string
{
    if (empty($kode)) {
        return '-';
    }
    $clean = trim((string)$kode);
    if (strpos($clean, '/') !== false) {
        $parts = explode('/', $clean);
        $lastPart = trim(end($parts));
        $tail = mb_substr($lastPart, -4);
        return '••••' . $tail;
    }
    if (mb_strlen($clean) <= 4) {
        return $clean;
    }
    return '••••' . mb_substr($clean, -4);
}

/**
 * Mengambil 3 langkah ringkas instruksi pembayaran per bank/toko dalam bahasa ID/EN
 */
function ambil_petunjuk_transfer(string $bankAtauToko, string $lang = 'id'): array
{
    $bank = strtolower(trim($bankAtauToko));
    $isEn = ($lang === 'en');

    $petunjuk = [
        'bca' => $isEn ? [
            'Open BCA mobile, KlikBCA, or BCA ATM, and choose Transfer > BCA Virtual Account.',
            'Enter the Virtual Account number above and verify the recipient name & total amount.',
            'Confirm the payment and keep your transaction receipt.',
        ] : [
            'Buka BCA mobile, KlikBCA, atau ATM BCA, lalu pilih Transfer > BCA Virtual Account.',
            'Masukkan nomor Virtual Account di atas dan pastikan nama penerima & nominal sesuai.',
            'Konfirmasi transaksi dan simpan bukti pembayaran Anda.',
        ],
        'bni' => $isEn ? [
            'Open BNI Mobile Banking or BNI ATM, select Transfer > Virtual Account Billing.',
            'Enter the BNI Virtual Account number shown above.',
            'Review the billing details and enter your PIN to finish the transaction.',
        ] : [
            'Buka BNI Mobile Banking atau ATM BNI, pilih Transfer > Virtual Account Billing.',
            'Masukkan nomor Virtual Account BNI yang tertera di atas.',
            'Periksa rincian tagihan lalu masukkan PIN untuk menyelesaikan transaksi.',
        ],
        'bri' => $isEn ? [
            'Open BRImo or BRI ATM, then choose Payment > BRIVA.',
            'Enter the BRIVA number and verify that customer name and bill match.',
            'Confirm the payment and input your PIN.',
        ] : [
            'Buka BRImo atau ATM BRI, lalu pilih menu Pembayaran > BRIVA.',
            'Masukkan nomor BRIVA dan periksa apakah nama pelanggan dan tagihan sesuai.',
            'Konfirmasi pembayaran dan masukkan PIN Anda.',
        ],
        'mandiri' => $isEn ? [
            'Open Livin\' by Mandiri or ATM, choose Pay > Multipayment or Virtual Account.',
            'Enter Company/Biller Code and Payment Number / Bill Key above.',
            'Check the billing details and complete the transaction.',
        ] : [
            'Buka Livin\' by Mandiri atau ATM, pilih Bayar > Multipayment atau Virtual Account.',
            'Masukkan Kode Perusahaan/Biller Code dan Nomor Pembayaran / Bill Key di atas.',
            'Periksa rincian tagihan dan selesaikan transaksi.',
        ],
        'permata' => $isEn ? [
            'Open PermataMobile X or Permata ATM, select Payment > Virtual Account.',
            'Enter the Permata Virtual Account number shown above.',
            'Verify the total amount and complete the payment.',
        ] : [
            'Buka PermataMobile X atau ATM Permata, pilih Pembayaran > Virtual Account.',
            'Masukkan nomor Permata Virtual Account yang tertera di atas.',
            'Periksa nominal tagihan dan selesaikan pembayaran.',
        ],
        'indomaret' => $isEn ? [
            'Visit the nearest Indomaret store and tell the cashier you want to pay a booking bill.',
            'Show the payment code above to the cashier.',
            'Pay the exact amount and keep the official receipt from the cashier.',
        ] : [
            'Datangi gerai Indomaret terdekat dan sampaikan ke kasir pembayaran pemesanan.',
            'Tunjukkan kode pembayaran di atas kepada kasir.',
            'Bayar sesuai nominal pas dan simpan struk resmi dari kasir.',
        ],
        'alfamart' => $isEn ? [
            'Visit the nearest Alfamart / Alfamidi store and go to the cashier counter.',
            'Provide the payment code above to the cashier.',
            'Pay the required amount and keep your official payment receipt.',
        ] : [
            'Datangi kasir gerai Alfamart / Alfamidi terdekat.',
            'Berikan kode pembayaran yang tertera di atas kepada kasir.',
            'Bayar sesuai nominal tagihan dan simpan bukti transaksi resmi.',
        ],
    ];

    if (isset($petunjuk[$bank])) {
        return $petunjuk[$bank];
    }

    return $isEn ? [
        'Open your mobile banking or e-wallet application.',
        'Choose transfer or payment to the Virtual Account / Code shown above.',
        'Verify the booking details, pay the exact amount, and save your receipt.',
    ] : [
        'Buka aplikasi mobile banking atau e-wallet pilihan Anda.',
        'Pilih menu transfer atau pembayaran ke Virtual Account / Kode di atas.',
        'Pastikan detail pemesanan sesuai, selesaikan pembayaran, dan simpan buktinya.',
    ];
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

    $vaData = ekstrak_data_va_midtrans($statusData);
    $bankAtauToko = $vaData['bank_atau_toko'];
    $kodePembayaran = $vaData['kode_pembayaran'];
    $kedaluwarsaPada = $vaData['kedaluwarsa_pada'];

    $statusInternal = petakan_status_midtrans($transactionStatus, $fraudStatus);

    $startedTx = false;
    if (!$db->inTransaction()) {
        $db->beginTransaction();
        $startedTx = true;
    }
    try {
        // 1. Kunci dan validasi baris pembayaran
        $stmtP = $db->prepare("SELECT id_pembayaran, id_reservasi, order_id, sumber, jumlah, status, bank_atau_toko, kode_pembayaran FROM pembayaran WHERE order_id = ? FOR UPDATE");
        $stmtP->execute([$orderId]);
        $currP = $stmtP->fetch(PDO::FETCH_ASSOC);

        if (!$currP) {
            if ($startedTx && $db->inTransaction()) {
                $db->rollBack();
            }
            return ['ok' => false, 'pesan' => 'Order ID tidak ditemukan di basis data', 'http_code' => 400];
        }

        // 2. Validasi kesesuaian gross_amount jika tersedia
        if ($grossAmount !== null && $grossAmount !== '') {
            $nominalExpected = (int)$currP['jumlah'];
            $nominalReceived = (int)round((float)$grossAmount);
            if ($nominalExpected !== $nominalReceived) {
                if ($startedTx && $db->inTransaction()) {
                    $db->rollBack();
                }
                error_log("Validasi nominal gagal pada {$orderId}: diharapkan {$nominalExpected}, diterima {$nominalReceived}");
                return ['ok' => false, 'pesan' => 'Nominal transaksi tidak cocok dengan database', 'http_code' => 400];
            }
        }

        // 3. Kunci baris reservasi terkait
        $stmtR = $db->prepare("SELECT id_reservasi, status FROM reservasi WHERE id_reservasi = ? FOR UPDATE");
        $stmtR->execute([$currP['id_reservasi']]);
        $reservasi = $stmtR->fetch(PDO::FETCH_ASSOC);

        // Cek duplikasi kode VA aktif
        $catatanTambahan = null;
        if ($statusInternal === 'menunggu' && !empty($bankAtauToko) && !empty($kodePembayaran)) {
            $dup = cek_duplikat_kode($db, $bankAtauToko, $kodePembayaran, $orderId);
            if ($dup) {
                $catatanTambahan = "perlu ditinjau: duplikat kode VA dengan " . $dup['order_id'];
            }
        }

        // 4. Idempotensi: jika baris pembayaran ini SUDAH 'lunas', jangan turunkan statusnya
        if ($currP['status'] === 'lunas') {
            if (!empty($bankAtauToko) || !empty($kodePembayaran) || !empty($kedaluwarsaPada)) {
                $stmtUpdateVa = $db->prepare("
                    UPDATE pembayaran
                    SET bank_atau_toko = COALESCE(?, bank_atau_toko),
                        kode_pembayaran = COALESCE(?, kode_pembayaran),
                        kedaluwarsa_pada = COALESCE(?, kedaluwarsa_pada),
                        kode_dibuat_pada = CASE WHEN kode_dibuat_pada IS NOT NULL THEN kode_dibuat_pada WHEN ? IS NOT NULL THEN NOW() ELSE NULL END
                    WHERE id_pembayaran = ?
                ");
                $stmtUpdateVa->execute([$bankAtauToko, $kodePembayaran, $kedaluwarsaPada, $kodePembayaran, $currP['id_pembayaran']]);
            }

            $stmtFresh = $db->prepare("SELECT * FROM pembayaran WHERE id_pembayaran = ? LIMIT 1");
            $stmtFresh->execute([$currP['id_pembayaran']]);
            $fresh = $stmtFresh->fetch(PDO::FETCH_ASSOC);

            if ($startedTx && $db->inTransaction()) {
                $db->commit();
            }
            return [
                'ok' => true,
                'status' => 'lunas',
                'pesan' => 'Pembayaran sudah lunas (idempoten)',
                'http_code' => 200,
                'data' => [
                    'id_pembayaran'        => (int)$fresh['id_pembayaran'],
                    'id_reservasi'         => (int)$fresh['id_reservasi'],
                    'order_id'             => $fresh['order_id'],
                    'status'               => 'lunas',
                    'bank_atau_toko'       => $fresh['bank_atau_toko'] ?? null,
                    'bank_atau_toko_label' => format_bank_atau_toko($fresh['bank_atau_toko'] ?? null),
                    'kode_pembayaran'      => $fresh['kode_pembayaran'] ?? null,
                    'kode_pembayaran_fmt'  => format_nomor_va($fresh['kode_pembayaran'] ?? null, $fresh['bank_atau_toko'] ?? null),
                    'kode_pembayaran_mask' => samarkan_kode_pembayaran($fresh['kode_pembayaran'] ?? null),
                    'kedaluwarsa_pada'     => $fresh['kedaluwarsa_pada'] ?? null,
                    'kedaluwarsa_pada_fmt' => !empty($fresh['kedaluwarsa_pada']) ? date('d/m/Y H:i', strtotime($fresh['kedaluwarsa_pada'])) . ' WIB' : null,
                    'is_lunas'             => true,
                    'is_menunggu'          => false,
                ]
            ];
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
            $lunasLain = $stmtLunasLain->fetch(PDO::FETCH_ASSOC);

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
                        bank_atau_toko = COALESCE(?, bank_atau_toko),
                        kode_pembayaran = COALESCE(?, kode_pembayaran),
                        kedaluwarsa_pada = COALESCE(?, kedaluwarsa_pada),
                        kode_dibuat_pada = CASE WHEN kode_dibuat_pada IS NOT NULL THEN kode_dibuat_pada WHEN ? IS NOT NULL THEN NOW() ELSE NULL END,
                        catatan = ?,
                        dibayar_pada = ?
                    WHERE id_pembayaran = ?
                ");
                $stmtUpdate->execute([$metodeKelompok, $paymentType, $transactionId, $bankAtauToko, $kodePembayaran, $kedaluwarsaPada, $kodePembayaran, $catatanGanda, $dibayarPada, $currP['id_pembayaran']]);
                error_log("PERINGATAN PEMBAYARAN GANDA: order_id {$orderId} tiba saat reservasi {$currP['id_reservasi']} sudah lunas.");
            } else {
                // Pembayaran lunas pertama yang sah
                $stmtUpdate = $db->prepare("
                    UPDATE pembayaran 
                    SET status = 'lunas',
                        metode_kelompok = ?,
                        metode_detail = ?,
                        transaction_id = ?,
                        bank_atau_toko = COALESCE(?, bank_atau_toko),
                        kode_pembayaran = COALESCE(?, kode_pembayaran),
                        kedaluwarsa_pada = COALESCE(?, kedaluwarsa_pada),
                        kode_dibuat_pada = CASE WHEN kode_dibuat_pada IS NOT NULL THEN kode_dibuat_pada WHEN ? IS NOT NULL THEN NOW() ELSE NULL END,
                        dibayar_pada = ?
                    WHERE id_pembayaran = ?
                ");
                $stmtUpdate->execute([$metodeKelompok, $paymentType, $transactionId, $bankAtauToko, $kodePembayaran, $kedaluwarsaPada, $kodePembayaran, $dibayarPada, $currP['id_pembayaran']]);
            }
        } else {
            // Status non-lunas ('menunggu', 'kedaluwarsa', 'gagal')
            // Jangan mengubah status jika sudah 'lunas' atau 'ganda'
            if (!in_array($currP['status'], ['lunas', 'ganda'], true)) {
                $metodeKelompok = !empty($paymentType) ? kelompok_metode($paymentType) : null;
                $sqlCatatan = "catatan";
                $paramsUpdate = [
                    $statusInternal,
                    $metodeKelompok,
                    $paymentType,
                    $transactionId,
                    $bankAtauToko,
                    $kodePembayaran,
                    $kedaluwarsaPada,
                    $kodePembayaran,
                ];
                if ($catatanTambahan) {
                    $sqlCatatan = "CASE WHEN catatan IS NULL OR catatan = '' THEN ? ELSE CONCAT(catatan, ' | ', ?) END";
                    $paramsUpdate[] = $catatanTambahan;
                    $paramsUpdate[] = $catatanTambahan;
                }
                $paramsUpdate[] = $currP['id_pembayaran'];

                $stmtUpdate = $db->prepare("
                    UPDATE pembayaran 
                    SET status = ?,
                        metode_kelompok = COALESCE(?, metode_kelompok),
                        metode_detail = COALESCE(?, metode_detail),
                        transaction_id = COALESCE(?, transaction_id),
                        bank_atau_toko = COALESCE(?, bank_atau_toko),
                        kode_pembayaran = COALESCE(?, kode_pembayaran),
                        kedaluwarsa_pada = COALESCE(?, kedaluwarsa_pada),
                        kode_dibuat_pada = CASE WHEN kode_dibuat_pada IS NOT NULL THEN kode_dibuat_pada WHEN ? IS NOT NULL THEN NOW() ELSE NULL END,
                        catatan = {$sqlCatatan}
                    WHERE id_pembayaran = ?
                ");
                $stmtUpdate->execute($paramsUpdate);
            }
        }

        $stmtFresh = $db->prepare("SELECT * FROM pembayaran WHERE id_pembayaran = ? LIMIT 1");
        $stmtFresh->execute([$currP['id_pembayaran']]);
        $fresh = $stmtFresh->fetch(PDO::FETCH_ASSOC);

        if ($startedTx && $db->inTransaction()) {
            $db->commit();
        }

        $curStatus = $fresh['status'] ?? $statusInternal;
        return [
            'ok' => true,
            'status' => $curStatus,
            'pesan' => 'Status pembayaran: ' . $curStatus,
            'http_code' => 200,
            'data' => [
                'id_pembayaran'        => (int)($fresh['id_pembayaran'] ?? $currP['id_pembayaran']),
                'id_reservasi'         => (int)($fresh['id_reservasi'] ?? $currP['id_reservasi']),
                'order_id'             => $fresh['order_id'] ?? $orderId,
                'status'               => $curStatus,
                'bank_atau_toko'       => $fresh['bank_atau_toko'] ?? null,
                'bank_atau_toko_label' => format_bank_atau_toko($fresh['bank_atau_toko'] ?? null),
                'kode_pembayaran'      => $fresh['kode_pembayaran'] ?? null,
                'kode_pembayaran_fmt'  => format_nomor_va($fresh['kode_pembayaran'] ?? null, $fresh['bank_atau_toko'] ?? null),
                'kode_pembayaran_mask' => samarkan_kode_pembayaran($fresh['kode_pembayaran'] ?? null),
                'kedaluwarsa_pada'     => $fresh['kedaluwarsa_pada'] ?? null,
                'kedaluwarsa_pada_fmt' => !empty($fresh['kedaluwarsa_pada']) ? date('d/m/Y H:i', strtotime($fresh['kedaluwarsa_pada'])) . ' WIB' : null,
                'is_lunas'             => $curStatus === 'lunas',
                'is_menunggu'          => $curStatus === 'menunggu',
            ]
        ];
    } catch (\Exception $e) {
        if ($startedTx && $db->inTransaction()) {
            $db->rollBack();
        }
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
        $stmtFull = $db->prepare("SELECT * FROM pembayaran WHERE id_pembayaran = ? LIMIT 1");
        $stmtFull->execute([$pembayaran['id_pembayaran']]);
        $fresh = $stmtFull->fetch(PDO::FETCH_ASSOC);
        return [
            'ok' => true,
            'status' => $pembayaran['status'],
            'pesan' => 'Bukan transaksi Midtrans (manual)',
            'http_code' => 200,
            'data' => [
                'id_pembayaran'        => (int)$fresh['id_pembayaran'],
                'id_reservasi'         => (int)$fresh['id_reservasi'],
                'order_id'             => $fresh['order_id'],
                'status'               => $fresh['status'],
                'bank_atau_toko'       => $fresh['bank_atau_toko'] ?? null,
                'bank_atau_toko_label' => format_bank_atau_toko($fresh['bank_atau_toko'] ?? null),
                'kode_pembayaran'      => $fresh['kode_pembayaran'] ?? null,
                'kode_pembayaran_fmt'  => format_nomor_va($fresh['kode_pembayaran'] ?? null, $fresh['bank_atau_toko'] ?? null),
                'kode_pembayaran_mask' => samarkan_kode_pembayaran($fresh['kode_pembayaran'] ?? null),
                'kedaluwarsa_pada'     => $fresh['kedaluwarsa_pada'] ?? null,
                'kedaluwarsa_pada_fmt' => !empty($fresh['kedaluwarsa_pada']) ? date('d/m/Y H:i', strtotime($fresh['kedaluwarsa_pada'])) . ' WIB' : null,
                'is_lunas'             => $fresh['status'] === 'lunas',
                'is_menunggu'          => $fresh['status'] === 'menunggu',
            ]
        ];
    }

    // Jika sudah lunas, tidak perlu melakukan panggilan jaringan ulang (idempoten & hemat kuota)
    if ($pembayaran['status'] === 'lunas') {
        $stmtFull = $db->prepare("SELECT * FROM pembayaran WHERE id_pembayaran = ? LIMIT 1");
        $stmtFull->execute([$pembayaran['id_pembayaran']]);
        $fresh = $stmtFull->fetch(PDO::FETCH_ASSOC);
        return [
            'ok' => true,
            'status' => 'lunas',
            'pesan' => 'Pembayaran sudah berstatus lunas',
            'http_code' => 200,
            'data' => [
                'id_pembayaran'        => (int)$fresh['id_pembayaran'],
                'id_reservasi'         => (int)$fresh['id_reservasi'],
                'order_id'             => $fresh['order_id'],
                'status'               => 'lunas',
                'bank_atau_toko'       => $fresh['bank_atau_toko'] ?? null,
                'bank_atau_toko_label' => format_bank_atau_toko($fresh['bank_atau_toko'] ?? null),
                'kode_pembayaran'      => $fresh['kode_pembayaran'] ?? null,
                'kode_pembayaran_fmt'  => format_nomor_va($fresh['kode_pembayaran'] ?? null, $fresh['bank_atau_toko'] ?? null),
                'kode_pembayaran_mask' => samarkan_kode_pembayaran($fresh['kode_pembayaran'] ?? null),
                'kedaluwarsa_pada'     => $fresh['kedaluwarsa_pada'] ?? null,
                'kedaluwarsa_pada_fmt' => !empty($fresh['kedaluwarsa_pada']) ? date('d/m/Y H:i', strtotime($fresh['kedaluwarsa_pada'])) . ' WIB' : null,
                'is_lunas'             => true,
                'is_menunggu'          => false,
            ]
        ];
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

    $statusData = is_object($statusObj) ? json_decode(json_encode($statusObj), true) : (array)$statusObj;

    return proses_status_transaksi_midtrans($orderId, $statusData);
}

/**
 * Hitung ketersediaan unit villa per malam untuk rentang tanggal tertentu.
 * Dipakai di: api/cek-ketersediaan.php (kalender), reservasi.php (submit),
 *             dashboard/pengelola.php (konfirmasi), api/reservasi-offline.php,
 *             api/blokir-tanggal.php — SATU sumber kebenaran.
 *
 * Aturan (B — proposal Tahap 11):
 *   Unit terpakai malam D = reservasi 'dikonfirmasi' (checkin<=D<checkout)
 *                         + blokir_tanggal aktif yang mencakup D.
 *   PENUH    = terpakai >= jumlah_unit.
 *   MENUNGGU = tidak penuh TAPI ada reservasi 'menunggu' pada malam itu
 *              (peringatan saja, boleh tetap dipesan sesuai F-08).
 *   TERSEDIA = selain itu.
 *
 * @param int      $id_tipe  ID tipe villa
 * @param string   $dari     Tanggal awal inklusif  (YYYY-MM-DD)
 * @param string   $sampai   Tanggal akhir eksklusif (YYYY-MM-DD, seperti checkout)
 * @param PDO|null $db       Koneksi; jika null ambil get_db()
 * @return array{unit_total:int, malam:array<string,array{terkonfirmasi:int,menunggu:int,diblokir:int,status:string}>}
 */
function hitung_ketersediaan(int $id_tipe, string $dari, string $sampai, ?PDO $db = null): array
{
    if ($db === null) {
        $db = get_db();
    }

    // Ambil jumlah unit villa
    $stmtVilla = $db->prepare("SELECT jumlah_unit FROM tipe_villa WHERE id_tipe = ? LIMIT 1");
    $stmtVilla->execute([$id_tipe]);
    $villa     = $stmtVilla->fetch(PDO::FETCH_ASSOC);
    $unitTotal = $villa ? (int)$villa['jumlah_unit'] : 1;

    // Bangun daftar malam (dari s.d. sebelum sampai)
    $result = [];
    try {
        $cur = new DateTimeImmutable($dari);
        $end = new DateTimeImmutable($sampai);
    } catch (\Exception $e) {
        return ['unit_total' => $unitTotal, 'malam' => []];
    }
    while ($cur < $end) {
        $key          = $cur->format('Y-m-d');
        $result[$key] = ['terkonfirmasi' => 0, 'menunggu' => 0, 'diblokir' => 0, 'status' => 'tersedia'];
        $cur          = $cur->modify('+1 day');
    }
    if (empty($result)) {
        return ['unit_total' => $unitTotal, 'malam' => []];
    }

    // Reservasi 'dikonfirmasi' yang tumpang tindih
    $stmtConf = $db->prepare("
        SELECT tanggal_checkin, tanggal_checkout
        FROM reservasi
        WHERE id_tipe    = ?
          AND status     = 'dikonfirmasi'
          AND tanggal_checkin  < ?
          AND tanggal_checkout > ?
    ");
    $stmtConf->execute([$id_tipe, $sampai, $dari]);
    foreach ($stmtConf->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rCur = new DateTimeImmutable($row['tanggal_checkin']);
        $rEnd = new DateTimeImmutable($row['tanggal_checkout']);
        while ($rCur < $rEnd) {
            $k = $rCur->format('Y-m-d');
            if (isset($result[$k])) $result[$k]['terkonfirmasi']++;
            $rCur = $rCur->modify('+1 day');
        }
    }

    // Reservasi 'menunggu' yang tumpang tindih
    $stmtPend = $db->prepare("
        SELECT tanggal_checkin, tanggal_checkout
        FROM reservasi
        WHERE id_tipe    = ?
          AND status     = 'menunggu'
          AND tanggal_checkin  < ?
          AND tanggal_checkout > ?
    ");
    $stmtPend->execute([$id_tipe, $sampai, $dari]);
    foreach ($stmtPend->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rCur = new DateTimeImmutable($row['tanggal_checkin']);
        $rEnd = new DateTimeImmutable($row['tanggal_checkout']);
        while ($rCur < $rEnd) {
            $k = $rCur->format('Y-m-d');
            if (isset($result[$k])) $result[$k]['menunggu']++;
            $rCur = $rCur->modify('+1 day');
        }
    }

    // blokir_tanggal aktif (dibatalkan_pada IS NULL)
    try {
        $stmtBlokir = $db->prepare("
            SELECT tanggal_mulai, tanggal_selesai
            FROM blokir_tanggal
            WHERE id_tipe           = ?
              AND dibatalkan_pada   IS NULL
              AND tanggal_mulai     < ?
              AND tanggal_selesai   > ?
        ");
        $stmtBlokir->execute([$id_tipe, $sampai, $dari]);
        foreach ($stmtBlokir->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rCur = new DateTimeImmutable($row['tanggal_mulai']);
            $rEnd = new DateTimeImmutable($row['tanggal_selesai']);
            while ($rCur < $rEnd) {
                $k = $rCur->format('Y-m-d');
                if (isset($result[$k])) $result[$k]['diblokir']++;
                $rCur = $rCur->modify('+1 day');
            }
        }
    } catch (\PDOException $e) {
        // Tabel belum ada (sebelum migration 004), abaikan blokir
        error_log("hitung_ketersediaan: blokir_tanggal belum tersedia – " . $e->getMessage());
    }

    // Tentukan status per malam
    foreach ($result as $date => &$malam) {
        $totalTerpakai = $malam['terkonfirmasi'] + $malam['diblokir'];
        if ($totalTerpakai >= $unitTotal) {
            $malam['status'] = 'penuh';
        } elseif ($malam['menunggu'] > 0) {
            $malam['status'] = 'menunggu';
        } else {
            $malam['status'] = 'tersedia';
        }
    }
    unset($malam);

    return ['unit_total' => $unitTotal, 'malam' => $result];
}

/**
 * Pencatatan log khusus Midtrans ke tmp/midtrans-error.log
 * Menyensor kunci sensitif sebelum menulis ke file.
 */
function catat_midtrans_log(string $kategori, string $lokasi, string $pesan, ?string $exceptionClass = null): void {
    $dir = defined('ROOT_PATH') ? ROOT_PATH . 'tmp' : __DIR__ . '/../tmp';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $logFile = $dir . DIRECTORY_SEPARATOR . 'midtrans-error.log';

    // Sensor nilai kunci jika sempat muncul dalam pesan
    $cleanPesan = preg_replace('/(SB-Mid-[a-zA-Z0-9_\-]+|Mid-server-[a-zA-Z0-9_\-]+|Mid-client-[a-zA-Z0-9_\-]+)/', '***REDACTED***', $pesan);

    $waktu = date('Y-m-d H:i:s');
    $exPart = $exceptionClass ? " [Exception: {$exceptionClass}]" : "";
    $entry = sprintf("[%s] [%s] [%s]%s %s\n", $waktu, $kategori, $lokasi, $exPart, $cleanPesan);

    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

/**
 * Validasi kesiapan Server Key & Client Key Midtrans
 * Mengembalikan status terperinci (apakah kosong, placeholder, atau awalan tidak dikenal).
 * JANGAN menolak kunci jika format awalan berbeda dari standar; cukup beri peringatan.
 */
function midtrans_kunci_siap(): array {
    global $config;
    $serverKey = trim((string)($config['MIDTRANS_SERVER_KEY'] ?? (defined('MIDTRANS_SERVER_KEY') ? MIDTRANS_SERVER_KEY : '')));
    $clientKey = trim((string)($config['MIDTRANS_CLIENT_KEY'] ?? (defined('MIDTRANS_CLIENT_KEY') ? MIDTRANS_CLIENT_KEY : '')));
    $isProduction = (bool)($config['MIDTRANS_IS_PRODUCTION'] ?? (defined('MIDTRANS_IS_PRODUCTION') ? MIDTRANS_IS_PRODUCTION : false));

    $placeholders = ['YOUR_MIDTRANS', 'ISI_DI_SINI', 'DUMMY', 'CHANGE_ME', 'EXAMPLE', 'PLACEHOLDER'];

    $serverSiap = true;
    $serverAlasan = '';
    $serverPeringatan = '';

    if ($serverKey === '') {
        $serverSiap = false;
        $serverAlasan = 'Server Key Midtrans kosong.';
    } else {
        foreach ($placeholders as $ph) {
            if (stripos($serverKey, $ph) !== false) {
                $serverSiap = false;
                $serverAlasan = 'Server Key Midtrans masih menggunakan nilai placeholder.';
                break;
            }
        }
        if ($serverSiap) {
            // Awalan tak dikenal: peringatan saja, BUKAN penolakan
            $expectedPrefix = $isProduction ? 'Mid-server-' : 'SB-Mid-server-';
            if (strpos($serverKey, $expectedPrefix) !== 0) {
                $serverPeringatan = "Awalan Server Key tidak biasa (standar: '$expectedPrefix').";
            }
        }
    }

    $clientSiap = true;
    $clientAlasan = '';
    $clientPeringatan = '';

    if ($clientKey === '') {
        $clientSiap = false;
        $clientAlasan = 'Client Key Midtrans kosong.';
    } else {
        foreach ($placeholders as $ph) {
            if (stripos($clientKey, $ph) !== false) {
                $clientSiap = false;
                $clientAlasan = 'Client Key Midtrans masih menggunakan nilai placeholder.';
                break;
            }
        }
        if ($clientSiap) {
            $expectedPrefix = $isProduction ? 'Mid-client-' : 'SB-Mid-client-';
            if (strpos($clientKey, $expectedPrefix) !== 0) {
                $clientPeringatan = "Awalan Client Key tidak biasa (standar: '$expectedPrefix').";
            }
        }
    }

    $siap = $serverSiap && $clientSiap;

    return [
        'siap'              => $siap,
        'server_siap'       => $serverSiap,
        'client_siap'       => $clientSiap,
        'server_alasan'     => $serverAlasan,
        'client_alasan'     => $clientAlasan,
        'server_peringatan' => $serverPeringatan,
        'client_peringatan' => $clientPeringatan,
        'server_len'        => strlen($serverKey),
        'client_len'        => strlen($clientKey),
        'server_prefix'     => substr($serverKey, 0, 14),
        'client_prefix'     => substr($clientKey, 0, 14),
        'is_production'     => $isProduction,
    ];
}
