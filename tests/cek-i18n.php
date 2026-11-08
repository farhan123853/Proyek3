<?php
/**
 * Skrip Verifikasi Kelengkapan Dwibahasa (ID/EN) — Villa Joglo Masayu
 * Memindai seluruh kode sumber PHP untuk panggilan t('...'),
 * memeriksa ketersediaan kunci di lang/id.php dan lang/en.php,
 * serta memastikan tidak ada kunci yang hilang atau bernilai kosong.
 */

$rootDir = dirname(__DIR__);
$idFile = $rootDir . '/lang/id.php';
$enFile = $rootDir . '/lang/en.php';

if (!file_exists($idFile) || !file_exists($enFile)) {
    echo "ERROR: File kamus lang/id.php atau lang/en.php tidak ditemukan!\n";
    exit(1);
}

$idDict = require $idFile;
$enDict = require $enFile;

function flattenDict($dict, $prefix = '') {
    $out = [];
    foreach ($dict as $k => $v) {
        $path = $prefix ? $prefix . '.' . $k : $k;
        if (is_array($v)) {
            $out = array_merge($out, flattenDict($v, $path));
        } else {
            $out[$path] = (string)$v;
        }
    }
    return $out;
}

$idKeys = flattenDict($idDict);
$enKeys = flattenDict($enDict);

echo "Total kunci terdaftar:\n";
echo " - ID: " . count($idKeys) . " kunci\n";
echo " - EN: " . count($enKeys) . " kunci\n\n";

// 1. Periksa simetri antara ID dan EN
$missingInEn = array_diff_key($idKeys, $enKeys);
$missingInId = array_diff_key($enKeys, $idKeys);

$hasError = false;

if (!empty($missingInEn)) {
    echo "PERINGATAN: Kunci ada di ID tapi tidak ada di EN:\n";
    foreach ($missingInEn as $k => $v) {
        echo "  - $k\n";
    }
    $hasError = true;
}

if (!empty($missingInId)) {
    echo "PERINGATAN: Kunci ada di EN tapi tidak ada di ID:\n";
    foreach ($missingInId as $k => $v) {
        echo "  - $k\n";
    }
    $hasError = true;
}

// 2. Pindai seluruh file PHP
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootDir));
$phpFiles = new RegexIterator($files, '/\.php$/');

$usedKeys = [];
foreach ($phpFiles as $file) {
    $path = $file->getRealPath();
    // Abaikan folder vendor/ atau node_modules/ atau tests/
    if (strpos($path, 'node_modules') !== false || strpos($path, 'tests') !== false || strpos($path, 'lang') !== false) {
        continue;
    }

    $content = file_get_contents($path);
    if (preg_match_all("/\bt\(\s*['\"]([a-zA-Z0-9_\.]+)['\"]/m", $content, $matches)) {
        foreach ($matches[1] as $key) {
            $usedKeys[$key][] = str_replace($rootDir . DIRECTORY_SEPARATOR, '', $path);
        }
    }
}

echo "Total kunci t('...') unik yang dipanggil di kode PHP: " . count($usedKeys) . "\n\n";

$missingInBoth = [];
foreach ($usedKeys as $k => $sources) {
    $inId = isset($idKeys[$k]) && trim($idKeys[$k]) !== '';
    $inEn = isset($enKeys[$k]) && trim($enKeys[$k]) !== '';

    if (!$inId || !$inEn) {
        $missingInBoth[$k] = [
            'sources' => array_unique($sources),
            'in_id' => $inId,
            'in_en' => $inEn
        ];
    }
}

if (!empty($missingInBoth)) {
    echo "GAGAL: Ditemukan " . count($missingInBoth) . " kunci yang tidak lengkap di kamus:\n";
    foreach ($missingInBoth as $k => $info) {
        echo "  - '$k' (ID: " . ($info['in_id'] ? 'OK' : 'MISSING') . ", EN: " . ($info['in_en'] ? 'OK' : 'MISSING') . ")\n";
        echo "    Dipakai di: " . implode(', ', $info['sources']) . "\n";
    }
    exit(1);
}

echo "HASIL VERIFIKASI: 0 missing keys! Seluruh kunci t() lengkap di ID dan EN.\n";
exit(0);
