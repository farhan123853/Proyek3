
<?php
/**
 * Pengaturan Akun Pelanggan
 * Villa Joglo Masayu
 */

require_once __DIR__ . '/includes/bootstrap.php';

require_role('pelanggan');

$pdo = get_db();
$userId = (int) current_user_id();

// Ambil informasi akun langsung dari database.
$stmt = $pdo->prepare(
    "SELECT
        id_pengguna,
        nama,
        email,
        kontak,
        status_akun,
        email_verified_at
     FROM pengguna
     WHERE id_pengguna = ?
     LIMIT 1"
);

$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    logout_user();

    flash_set(
        'warning',
        'Data akun tidak ditemukan. Silakan masuk kembali.'
    );

    redirect('login.php');
}

$isVerified = $user['email_verified_at'] !== null;

$pageTitle = 'Pengaturan Akun — Villa Joglo Masayu';
$pageDesc = 'Kelola informasi akun dan verifikasi alamat email.';
$bodyClass = 'page-villa';

require_once __DIR__ . '/includes/layout/head.php';
?>

<main class="container"
      style="padding-top: calc(var(--header-h-scrolled) + 3rem); padding-bottom: 5rem;">

    <div style="max-width: 700px; margin: 0 auto;">

        <p class="eyebrow">MY ACCOUNT</p>
        <h1 class="section-heading">Pengaturan Akun</h1>

        <p class="body-text">
            Lihat informasi akun dan kelola status verifikasi
            alamat email kamu.
        </p>

        <div class="form-card" style="margin-top:2rem;">

            <h2 class="form-title">Informasi Profil</h2>

            <div class="form-group">
                <label class="form-label">Nama Lengkap</label>
                <div class="form-control"
                     style="display:flex;align-items:center;">
                    <?= e($user['nama']) ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Alamat Email</label>

                <div class="form-control"
                     style="display:flex;align-items:center;">
                    <?= e($user['email']) ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Nomor Kontak</label>

                <div class="form-control"
                     style="display:flex;align-items:center;">
                    <?= e($user['kontak']) ?>
                </div>
            </div>

            <div style="
                margin-top:2rem;
                padding:1.25rem;
                border:1px solid <?= $isVerified ? '#86c79a' : '#e6c477' ?>;
                background:<?= $isVerified ? '#f0faf2' : '#fff9e9' ?>;
                border-radius:10px;
            ">

                <h3 style="margin-bottom:0.75rem;">
                    Verifikasi Alamat Email
                </h3>

                <?php if ($isVerified): ?>

                    <p style="color:#276b3b;">
                        <strong>Email Terverifikasi</strong>
                    </p>

                    <p style="margin-top:0.5rem;">
                        Alamat email kamu sudah berhasil diverifikasi
                        pada
                        <?= e(date(
                            'd-m-Y H:i',
                            strtotime($user['email_verified_at'])
                        )) ?>.
                    </p>

                <?php else: ?>

                    <p style="color:#8a6200;">
                        <strong>Email Belum Terverifikasi</strong>
                    </p>

                    <p style="margin-top:0.5rem;">
                        Verifikasi alamat email untuk membuka akses
                        ke fitur reservasi Villa Joglo Masayu.
                    </p>

                    <form method="POST"
                          action="kirim-ulang-verifikasi.php"
                          style="margin-top:1rem;">

                        <?= csrf_field() ?>

                        <button type="submit"
                                class="btn btn-gold"
                                style="width:100%;justify-content:center;">
                            Request Verifikasi
                        </button>

                    </form>

                    <p style="
                        margin-top:0.75rem;
                        font-size:13px;
                        color:var(--wood);
                    ">
                        Setelah mengirim permintaan, silakan periksa
                        Inbox atau folder Spam pada email kamu.
                    </p>

                <?php endif; ?>

            </div>

            <div style="margin-top:2rem;">
                <a href="reservasi-saya.php"
                   style="color:var(--gold-dark);font-weight:700;">
                    Kembali ke Reservasi Saya
                </a>
            </div>

        </div>

    </div>

</main>

<?php require_once __DIR__ . '/includes/layout/foot.php'; ?>
