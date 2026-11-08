<?php
/**
 * API: Layanan Prakiraan Cuaca BMKG — Villa Joglo Masayu
 * Kebutuhan: F-02, F-03, T-13, T-14
 * - cURL dari server, timeout 5 detik, verifikasi SSL
 * - adm4 hanya diambil dari config (bukan dari parameter pengguna)
 * - Cache di cache/cuaca.json (TTL 1 jam, sajikan stale cache jika API gagal)
 * - Kirim field yang dibutuhkan + "sumber": "BMKG"
 * - Pesan alternatif jika API eksternal gagal
 * - Hanya menerima metode GET
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

// Hanya menerima metode GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['status' => 'error', 'pesan' => 'Metode permintaan tidak diizinkan.'], 405);
}

$cacheDir = ROOT_PATH . 'cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'cuaca.json';
$cacheTtl  = 3600; // 1 jam

// 1. Periksa apakah cache masih segar
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
    $cachedContent = @file_get_contents($cacheFile);
    if (!empty($cachedContent)) {
        $cachedData = json_decode($cachedContent, true);
        if ($cachedData && !empty($cachedData['data'])) {
            $cachedData['cached'] = true;
            echo json_encode($cachedData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
}

// 2. Ambil adm4 strictly dari config
$adm4 = $config['BMKG_ADM4'] ?? '34.04.05.2003';
$apiUrl = "https://api.bmkg.go.id/publik/prakiraan-cuaca?adm4=" . urlencode($adm4);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 5,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_USERAGENT      => 'Villa-Joglo-Masayu-App/1.0 (Proyek 3 ULBI)',
    CURLOPT_HTTPHEADER     => ['Accept: application/json']
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$cuacaData = null;

if ($httpCode === 200 && !empty($response)) {
    $raw = json_decode($response, true);
    if (!empty($raw) && (isset($raw['data']) || isset($raw['lokasi']))) {
        // Ekstraksi data lokasi dan prakiraan
        $lokasiNama = $raw['lokasi']['desa'] ?? ($raw['lokasi']['kecamatan'] ?? 'Sukoharjo, Ngaglik, Sleman');
        $kota       = $raw['lokasi']['kotkab'] ?? 'Sleman';
        $provinsi   = $raw['lokasi']['provinsi'] ?? 'D.I. Yogyakarta';

        // Parse slot cuaca 3 harian
        $slots = [];
        $cuacaList = $raw['data'][0]['cuaca'] ?? [];

        // Cuaca bisa berstruktur 2D array [hari][slot] atau 1D array
        foreach ($cuacaList as $item) {
            if (is_array($item)) {
                // Jika nested per hari
                if (isset($item[0])) {
                    foreach ($item as $subItem) {
                        $slots[] = format_cuaca_item($subItem);
                    }
                } else {
                    $slots[] = format_cuaca_item($item);
                }
            }
        }

        // Ambil maksimal 8 slot (rentang 24 jam ke depan)
        $slots = array_slice(array_filter($slots), 0, 8);

        $cuacaData = [
            'status'     => 'success',
            'sumber'     => 'BMKG',
            'lokasi'     => "{$lokasiNama}, {$kota}, {$provinsi}",
            'diperbarui' => date('Y-m-d H:i:s'),
            'data'       => $slots,
            'cached'     => false
        ];

        // Simpan ke cache
        @file_put_contents($cacheFile, json_encode($cuacaData, JSON_UNESCAPED_UNICODE));
    }
}

// 3. Fallback jika permintaan baru gagal: gunakan cache lama jika ada
if (!$cuacaData && file_exists($cacheFile)) {
    $cachedContent = @file_get_contents($cacheFile);
    if (!empty($cachedContent)) {
        $staleData = json_decode($cachedContent, true);
        if ($staleData && !empty($staleData['data'])) {
            $staleData['cached'] = true;
            $staleData['stale']  = true;
            echo json_encode($staleData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
}

// 4. Fallback jika benar-benar tidak ada data
if (!$cuacaData) {
    json_response([
        'status'  => 'error',
        'sumber'  => 'BMKG',
        'pesan'   => 'Layanan perkiraan cuaca sementara tidak dapat dihubungi. Informasi dan reservasi villa tetap dapat digunakan dengan lancar.',
        'lokasi'  => 'Sukoharjo, Ngaglik, Sleman, D.I. Yogyakarta',
        'data'    => []
    ], 200); // 200 OK agar frontend dapat menampilkan pesan alternatif dengan tenang
}

echo json_encode($cuacaData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

/**
 * Format satu slot data cuaca BMKG
 */
function format_cuaca_item(array $raw): ?array
{
    if (empty($raw['datetime']) && empty($raw['local_datetime'])) {
        return null;
    }
    return [
        'waktu'       => $raw['local_datetime'] ?? ($raw['datetime'] ?? ''),
        'suhu'        => isset($raw['t']) ? (string)$raw['t'] : '',
        'kelembapan'  => isset($raw['hu']) ? (string)$raw['hu'] : '',
        'cuaca'       => $raw['weather_desc'] ?? ($raw['weather_desc_en'] ?? 'Berawan'),
        'cuaca_en'    => $raw['weather_desc_en'] ?? '',
        'kecepatan_angin' => isset($raw['ws']) ? (string)$raw['ws'] : '',
        'arah_angin'  => $raw['wd'] ?? '',
        'tutupan_awan'=> isset($raw['tcc']) ? (string)$raw['tcc'] : '',
        'jarak_pandang'=> $raw['vs_text'] ?? ''
    ];
}

