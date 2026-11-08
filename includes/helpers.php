<?php
/**
 * Helper Functions — Villa Joglo Masayu
 * Keamanan output, validasi, pemformatan, dan perhitungan tarif
 */

/**
 * Escape output string untuk mencegah XSS
 */
function e(?string $string): string
{
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
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
 * Format tanggal YYYY-MM-DD ke format bahasa Indonesia (contoh: 16 November 2026)
 */
function format_tanggal_id(?string $dateStr): string
{
    if (empty($dateStr)) {
        return '-';
    }
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $dt = DateTime::createFromFormat('Y-m-d', substr($dateStr, 0, 10));
    if (!$dt) {
        return $dateStr;
    }
    $b = (int)$dt->format('n');
    return $dt->format('j') . ' ' . ($bulan[$b] ?? '') . ' ' . $dt->format('Y');
}

/**
 * Hitung rincian malam menginap dan estimasi tarif
 * Sesuai aturan:
 * - Weekday : Senin s.d. Kamis (ISO-8601 day of week 1..4)
 * - Weekend : Jumat s.d. Minggu (ISO-8601 day of week 5..7)
 * - Extra bed: jika tamu > kapasitas_standar, extra bed = tamu - kapasitas_standar
 * - Biaya extra bed per malam = extra_bed * harga_extrabed
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
    // Jika path tidak diawali http/https, buat URL lengkap berdasarkan BASE_URL
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

