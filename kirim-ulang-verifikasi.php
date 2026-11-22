
<?php
/**
 * Request / Resend Email Verification
 * Villa Joglo Masayu
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Hanya pelanggan yang boleh meminta verifikasi.
require_role('pelanggan');

// Endpoint hanya menerima POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method Not Allowed');
}

// Validasi CSRF.
csrf_verify();

// Proses pembuatan token dan pengiriman email.
$result = email_verification_request(
    (int) current_user_id()
);

// Tampilkan notifikasi.
if (!empty($result['success'])) {
    flash_set(
        'success',
        $result['message'] ?? 'Permintaan verifikasi berhasil diproses.'
    );
} else {
    flash_set(
        'warning',
        $result['message'] ?? 'Permintaan verifikasi gagal diproses.'
    );
}

// Kembali ke pengaturan akun.
redirect('pengaturan-akun.php');
