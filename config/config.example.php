<?php
/**
 * Konfigurasi Aplikasi — Villa Joglo Masayu
 *
 * Salin file ini menjadi config.php dan isi kredensial yang sesuai.
 * JANGAN commit config.php ke repository.
 */

return [
    // ── Database MySQL ──────────────────────────────────────
    'DB_HOST' => 'localhost',
    'DB_PORT' => 3306,
    'DB_NAME' => 'villa_joglo_masayu',
    'DB_USER' => '',
    'DB_PASS' => '',
    'DB_CHARSET' => 'utf8mb4',

    // ── BMKG API (cuaca) ────────────────────────────────────
    // Kode wilayah ADM4 untuk BMKG API (prakiraan cuaca)
    // Contoh: '34.04.05.1003' = Sukoharjo, Ngaglik, Sleman
    'BMKG_ADM4' => '',

    // ── Aplikasi ────────────────────────────────────────────
    'APP_NAME'  => 'Villa Joglo Masayu',
    'APP_URL'   => 'http://localhost:8000',
    'APP_DEBUG' => true,
];
