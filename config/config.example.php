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

    // ── Midtrans Payment Gateway (Mode Sandbox) ──────────────
    // Kunci sandbox diperoleh dari Dashboard Midtrans (Settings > Access Keys)
    // Server Key HANYA disimpan di server (jangan bocorkan ke repo atau JavaScript)
    'MIDTRANS_SERVER_KEY'    => 'SB-Mid-server-ISI_DI_SINI',
    'MIDTRANS_CLIENT_KEY'    => 'SB-Mid-client-ISI_DI_SINI',
    'MIDTRANS_IS_PRODUCTION' => false,
    'PEMBAYARAN_BATAS_JAM'   => 24,

    // ── Aplikasi & URL ───────────────────────────────────────
    // Digunakan sebagai <base href="..."> untuk konsistensi tautan dan path aset.
    // Sesuaikan bila menggunakan subfolder di XAMPP, misal: 'http://localhost/website'
    'APP_NAME'   => 'Villa Joglo Masayu',
    'APP_URL'    => 'http://localhost:8000',
    'APP_DEBUG'  => true,

    // External Email Verification API
    'EMAIL_API_URL'    => '',
    'EMAIL_API_SECRET' => '',

    // Google Sign-In
    'GOOGLE_CLIENT_ID' => '',
];
