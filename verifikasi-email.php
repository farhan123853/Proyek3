<?php
/**
 * Email Verification Confirmation
 * Villa Joglo Masayu
 */

require_once __DIR__ . '/includes/bootstrap.php';

$token = trim((string) (
    $_POST['token'] ?? $_GET['token'] ?? ''
));

$status = 'invalid';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $result = email_verification_confirm($token);

    if (!empty($result['success'])) {
        $status = (
            $result['status'] === 'already_verified'
        ) ? 'already_verified' : 'verified';
    } else {
        $status = $result['status'] ?? 'error';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = email_verification_lookup($token);
    $status = $result['status'];
} else {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Method Not Allowed');
}

$pageTitle = 'Verifikasi Email — Villa Joglo Masayu';
$pageDesc = 'Konfirmasi verifikasi alamat email pelanggan.';
$bodyClass = 'page-villa';

require_once __DIR__ . '/includes/layout/head.php';
?>

<main class="container"
      style="padding-top: calc(var(--header-h-scrolled) + 3rem); padding-bottom: 5rem;">

    <div style="max-width: 560px; margin: 0 auto;">
        <div class="form-card">

            <p class="eyebrow">EMAIL VERIFICATION</p>

            <?php if ($status === 'valid'): ?>

                <h1 class="form-title">Konfirmasi Email</h1>

                <p class="form-desc">
                    Tautan verifikasi kamu valid.
                    Tekan tombol di bawah untuk menyelesaikan
                    verifikasi alamat email.
                </p>

                <form method="POST" action="verifikasi-email.php">
                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="token"
                        value="<?= e($token) ?>"
                    >

                    <div class="form-actions">
                        <button type="submit"
                                class="btn btn-gold"
                                style="width:100%;justify-content:center;">
                            Konfirmasi Verifikasi
                        </button>
                    </div>
                </form>

            <?php elseif (
                $status === 'verified' ||
                $status === 'already_verified'
            ): ?>

                <h1 class="form-title">Email Terverifikasi!</h1>

                <p class="form-desc">
                    Alamat email kamu sudah berhasil diverifikasi.
                    Sekarang kamu dapat menggunakan fitur reservasi
                    Villa Joglo Masayu.
                </p>

                <div class="form-actions">
                    <a href="login.php"
                       class="btn btn-gold"
                       style="width:100%;justify-content:center;">
                        Masuk ke Akun
                    </a>
                </div>

            <?php elseif ($status === 'expired'): ?>

                <h1 class="form-title">Tautan Kedaluwarsa</h1>

                <p class="form-desc">
                    Tautan verifikasi sudah melewati masa berlakunya.
                    Silakan masuk dan meminta tautan verifikasi baru
                    melalui Pengaturan Akun.
                </p>

                <a href="pengaturan-akun.php" class="btn btn-gold">
                    Pengaturan Akun
                </a>

            <?php elseif ($status === 'used'): ?>

                <h1 class="form-title">Tautan Tidak Berlaku</h1>

                <p class="form-desc">
                    Tautan ini sudah pernah digunakan atau dibatalkan.
                    Silakan meminta tautan verifikasi baru.
                </p>

                <a href="pengaturan-akun.php" class="btn btn-gold">
                    Pengaturan Akun
                </a>

            <?php else: ?>

                <h1 class="form-title">Verifikasi Tidak Berhasil</h1>

                <p class="form-desc">
                    Tautan verifikasi tidak ditemukan atau tidak valid.
                    Silakan periksa kembali email kamu atau minta
                    tautan verifikasi baru.
                </p>

                <a href="index.php" class="btn btn-gold">
                    Kembali ke Beranda
                </a>

            <?php endif; ?>

        </div>
    </div>

</main>

<?php require_once __DIR__ . '/includes/layout/foot.php'; ?>
