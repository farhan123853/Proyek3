<?php
/**
 * Bootstrap Aplikasi — Villa Joglo Masayu
 * Memuat konfigurasi, mengatur sesi aman, mendefinisikan konstanta, dan memuat modul inti
 */

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

// 4. Tentukan BASE_URL untuk <base href="...">
if (!defined('BASE_URL')) {
    $appUrl = $config['APP_URL'] ?? 'http://localhost:8000';
    define('BASE_URL', rtrim($appUrl, '/') . '/');
}

// 5. Muat Modul Inti
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
