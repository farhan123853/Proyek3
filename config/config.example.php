<?php
/**
 * Konfigurasi Aplikasi — Villa Joglo Masayu
 * Salin file ini menjadi config.php dan sesuaikan kredensial lingkungan Anda.
 * JANGAN commit config.php ke version control.
 */

return [
    // ── Basis Data (MySQL / MariaDB via PDO) ─────────────────
    'DB_HOST'    => 'localhost',
    'DB_PORT'    => 3306,
    'DB_NAME'    => 'villa_joglo_masayu',
    'DB_USER'    => 'root',
    'DB_PASS'    => '',
    'DB_CHARSET' => 'utf8mb4',

    // ── BMKG API (Prakiraan Cuaca Terbuka) ───────────────────
    // Kode wilayah tingkat IV (Desa/Kelurahan) Sukoharjo, Ngaglik, Sleman
    // Berdasarkan Kepmendagri 100.1.1-6117 Tahun 2022:
    // 34 (D.I. Yogyakarta) . 04 (Kab. Sleman) . 05 (Kec. Ngaglik) . 2003 (Kalurahan Sukoharjo)
    // TODO: Verifikasi kembali kode wilayah adm4 pada sistem BMKG bila ada pembaruan data wilayah.
    'BMKG_ADM4'  => '34.04.05.2003',

    // ── Aplikasi & URL ───────────────────────────────────────
    // Digunakan sebagai <base href="..."> untuk konsistensi tautan dan path aset.
    // Sesuaikan bila menggunakan subfolder di XAMPP, misal: 'http://localhost/website'
    'APP_NAME'   => 'Villa Joglo Masayu',
    'APP_URL'    => 'http://localhost:8000',
    'APP_DEBUG'  => true,
];
