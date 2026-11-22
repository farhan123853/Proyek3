<?php
/**
 * Bootstrap Aplikasi — Villa Joglo Masayu
 * Memuat konfigurasi, mengatur sesi aman, mendefinisikan konstanta, dan memuat modul inti
 */

// 0. Pengaturan Zona Waktu
date_default_timezone_set('Asia/Jakarta');

// 1. Konfigurasi Sesi Aman
if (session_status() === PHP_SESSION_NONE) {
    // Pengaturan cookie sesi aman
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// 2. Tentukan Direktori Root
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
}

// 3. Muat Konfigurasi
$configFile = ROOT_PATH . 'config' . DIRECTORY_SEPARATOR . 'config.php';
if (!file_exists($configFile)) {
    $configFile = ROOT_PATH . 'config' . DIRECTORY_SEPARATOR . 'config.example.php';
}
$config = file_exists($configFile) ? require $configFile : [];

// 3b. Definisikan konstanta Midtrans jika belum didefinisikan
if (!defined('MIDTRANS_SERVER_KEY')) {
    define('MIDTRANS_SERVER_KEY', (string)($config['MIDTRANS_SERVER_KEY'] ?? ''));
}
if (!defined('MIDTRANS_CLIENT_KEY')) {
    define('MIDTRANS_CLIENT_KEY', (string)($config['MIDTRANS_CLIENT_KEY'] ?? ''));
}
if (!defined('MIDTRANS_IS_PRODUCTION')) {
    define('MIDTRANS_IS_PRODUCTION', (bool)($config['MIDTRANS_IS_PRODUCTION'] ?? false));
}

// 4. Tentukan BASE_URL untuk <base href="...">
if (!defined('BASE_URL')) {
    $appUrl = $config['APP_URL'] ?? 'http://localhost:8000';
    define('BASE_URL', rtrim($appUrl, '/') . '/');
}

// 5. Muat Modul Inti
if (file_exists(ROOT_PATH . 'vendor/autoload.php')) {
    require_once ROOT_PATH . 'vendor/autoload.php';
}
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

