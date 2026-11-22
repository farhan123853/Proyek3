<?php
/**
 * Google Sign-In - Complete Profile
 * Villa Joglo Masayu
 *
 * Menyelesaikan registrasi pelanggan melalui Google.
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Pengguna yang sudah login tidak perlu melengkapi profil.
if (is_logged_in()) {
    redirect(get_role_redirect_url(current_role()));
}

// Ambil data registrasi sementara dari session.
$pending = $_SESSION['google_signup_pending'] ?? null;

// Pastikan data registrasi masih valid.
if (
    !is_array($pending) ||
    empty($pending['google_id']) ||
    empty($pending['email']) ||
    empty($pending['nama']) ||
    !isset($pending['email_verified']) ||
    empty($pending['expires_at']) ||
    time() > (int) $pending['expires_at']
) {
    unset($_SESSION['google_signup_pending']);

    flash_set(
        'warning',
        'Sesi registrasi Google berakhir. Silakan ulangi proses masuk dengan Google.'
    );

    redirect('login.php');
}

$errors = [];
$kontak = trim($_POST['kontak'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    // Validasi nomor kontak.
    if ($kontak === '') {
        $errors['kontak'] = 'Nomor WhatsApp wajib diisi.';
    } elseif (strlen($kontak) > 30) {
        $errors['kontak'] = 'Nomor kontak maksimal 30 karakter.';
    }

    if (empty($errors)) {
        $pdo = get_db();

        try {
            $pdo->beginTransaction();

            // Periksa kembali identitas Google untuk mencegah duplikasi.
            $stmtGoogle = $pdo->prepare(
                "SELECT id_pengguna
                 FROM pengguna
                 WHERE google_id = ?
                 LIMIT 1
                 FOR UPDATE"
            );

            $stmtGoogle->execute([$pending['google_id']]);

            if ($stmtGoogle->fetch()) {
                $pdo->rollBack();

                unset($_SESSION['google_signup_pending']);

                flash_set(
                    'warning',
                    'Akun Google sudah terdaftar. Silakan masuk kembali.'
                );

                redirect('login.php');
            }

            // Jangan otomatis menautkan akun lokal berdasarkan email.
            $stmtEmail = $pdo->prepare(
                "SELECT id_pengguna
                 FROM pengguna
                 WHERE email = ?
                 LIMIT 1
                 FOR UPDATE"
            );

            $stmtEmail->execute([$pending['email']]);

            if ($stmtEmail->fetch()) {
                $pdo->rollBack();

                $errors['email'] =
                    'Email ini sudah memiliki akun. Silakan gunakan metode login sebelumnya.';
            } else {

                // Password acak: tidak diketahui dan tidak digunakan
                // oleh pengguna Google Sign-In.
                $randomPassword = bin2hex(random_bytes(32));

                $passwordHash = password_hash(
                    $randomPassword,
                    PASSWORD_DEFAULT
                );

                // Akun baru selalu menjadi pelanggan.
                // Email hanya langsung diverifikasi apabila Google
                // merupakan pihak yang berwenang memverifikasinya.
                $sql = "
                    INSERT INTO pengguna (
                        nama,
                        email,
                        google_id,
                        kontak,
                        password_hash,
                        peran,
                        status_akun,
                        email_verified_at,
                        dibuat_pada
                    )
                    VALUES (
                        ?, ?, ?, ?, ?,
                        'pelanggan',
                        'aktif',
                        IF(? = 1, NOW(), NULL),
                        NOW()
                    )
                ";

                $stmtInsert = $pdo->prepare($sql);

                $stmtInsert->execute([
                    $pending['nama'],
                    $pending['email'],
                    $pending['google_id'],
                    $kontak,
                    $passwordHash,
                    $pending['email_verified'] ? 1 : 0
                ]);

                $newId = (int) $pdo->lastInsertId();

                $pdo->commit();

                // Hapus informasi registrasi sementara.
                unset($_SESSION['google_signup_pending']);

                // Buat session menggunakan sistem autentikasi lama.
                login_user([
                    'id_pengguna' => $newId,
                    'nama' => $pending['nama'],
                    'email' => $pending['email'],
                    'kontak' => $kontak,
                    'peran' => 'pelanggan'
                ]);

                flash_set(
                    'success',
                    t('auth.registration_success', [
                        'nama' => $pending['nama']
                    ])
                );

                // Konsisten dengan registrasi email-password.
                redirect('pengaturan-akun.php');
            }

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Google profile completion failed: ' . $e->getMessage()
            );

            $errors['general'] =
                'Terjadi kesalahan saat membuat akun. Silakan coba kembali.';
        }
    }
}

$pageTitle = 'Lengkapi Profil — Villa Joglo Masayu';
$pageDesc  = 'Lengkapi informasi akun pelanggan Anda.';
$bodyClass = 'page-villa';

require_once __DIR__ . '/includes/layout/head.php';
?>

<main class="container" style="padding-top: calc(var(--header-h-scrolled) + 3rem); padding-bottom: 5rem;">
    <div style="max-width: 540px; margin: 0 auto;">

        <div class="form-card">

            <p class="eyebrow">Registrasi Google</p>

            <h1 class="form-title">Lengkapi Profil Anda</h1>

            <p class="form-desc">
                Satu langkah lagi sebelum Anda dapat menggunakan layanan
                reservasi Villa Joglo Masayu.
            </p>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger" role="alert">
                    <?= e($errors['general']) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors['email'])): ?>
                <div class="alert alert-danger" role="alert">
                    <?= e($errors['email']) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="google-complete-profile.php" novalidate>

                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label">Nama Lengkap</label>

                    <input
                        type="text"
                        class="form-control"
                        value="<?= e($pending['nama']) ?>"
                        readonly
                    />
                </div>

                <div class="form-group">
                    <label class="form-label">Alamat Email</label>

                    <input
                        type="email"
                        class="form-control"
                        value="<?= e($pending['email']) ?>"
                        readonly
                    />
                </div>

                <div class="form-group">
                    <label class="form-label" for="kontak">
                        Nomor Kontak / WhatsApp
                        <span class="required">*</span>
                    </label>

                    <input
                        type="tel"
                        id="kontak"
                        name="kontak"
                        class="form-control"
                        value="<?= e($kontak) ?>"
                        placeholder="Contoh: 081234567890"
                        maxlength="30"
                        required
                        autocomplete="tel"
                    />

                    <?php if (isset($errors['kontak'])): ?>
                        <div class="form-error">
                            <?= e($errors['kontak']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button
                        type="submit"
                        class="btn btn-gold"
                        style="width: 100%; justify-content: center;"
                    >
                        Selesaikan Registrasi
                    </button>
                </div>

            </form>

            <div style="margin-top: 1.5rem; text-align: center; font-size: 13px; color: var(--wood);">
                Akun ini akan didaftarkan sebagai pelanggan Villa Joglo Masayu.
            </div>

        </div>

    </div>
</main>

<?php require_once __DIR__ . '/includes/layout/foot.php'; ?>
