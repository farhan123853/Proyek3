<?php
/**
 * Logout Pengguna — Villa Joglo Masayu
 * Kebutuhan: F-05, T-03
 * Logout hanya diizinkan via POST + verifikasi token CSRF
 */

require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    logout_user();
    flash_set('info', 'Anda sudah keluar.');
    redirect('index.php');
}

// Jika diakses lewat GET, tolak dan kembalikan ke beranda untuk mencegah CSRF
redirect('index.php');
