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
