<?php
/**
 * Logout Pengguna — Villa Joglo Masayu
 * Kebutuhan: F-05, T-03
 */

require_once __DIR__ . '/includes/bootstrap.php';

logout_user();
flash_set('info', 'Anda telah berhasil keluar dari akun.');
redirect('login.php');
