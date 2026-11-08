<?php
/**
 * Halaman Masuk (Login) — Villa Joglo Masayu
 * Kebutuhan: F-05, T-03
 * Login untuk 4 peran: Pelanggan, Pengelola, Pemilik, Admin
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Jika sudah login, redirect sesuai peran
if (is_logged_in()) {
    redirect(get_role_redirect_url(current_role()));
}

$error = '';
$email = trim($_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Silakan masukkan alamat email dan kata sandi Anda.';
    } else {
        $pdo = get_db();
        $stmt = $pdo->prepare("SELECT * FROM `pengguna` WHERE `email` = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Pesan error generik untuk melindungi keamanan akun
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = t('auth.invalid_credentials');
        } elseif ($user['status_akun'] !== 'aktif') {
            $error = t('auth.role_unauthorized');
        } else {
            // Berhasil login
            login_user($user);

            flash_set('success', t('auth.login_success', ['nama' => $user['nama']]));
            redirect(get_role_redirect_url($user['peran']));
        }
    }
}

$pageTitle = t('auth.login_title') . ' — Villa Joglo Masayu';
$pageDesc  = t('auth.login_desc');
$bodyClass = 'page-villa';

require_once __DIR__ . '/includes/layout/head.php';
?>

<main class="container" style="padding-top: calc(var(--header-h-scrolled) + 3rem); padding-bottom: 5rem;">
  <div style="max-width: 480px; margin: 0 auto;">

    <div class="form-card">
      <p class="eyebrow"><?= t('auth.login_eyebrow') ?></p>
      <h1 class="form-title"><?= t('auth.login_title') ?></h1>
      <p class="form-desc"><?= t('auth.login_desc') ?></p>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger" role="alert" style="margin-bottom: 1.5rem;">
          <span><?= e($error) ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php" novalidate>
        <?= csrf_field() ?>

        <div class="form-group">
          <label class="form-label" for="email"><?= t('auth.email_label') ?></label>
          <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" required autocomplete="email" autofocus />
        </div>

        <div class="form-group">
          <label class="form-label" for="password"><?= t('auth.password_label') ?></label>
          <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password" />
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-gold" style="width: 100%; justify-content: center;"><?= t('auth.btn_login') ?></button>
        </div>
      </form>

      <div style="margin-top: 1.5rem; text-align: center; font-size: 14px; color: var(--wood);">
        <?= t('auth.no_account') ?> <a href="daftar.php" style="color: var(--gold-dark); font-weight: 700; text-decoration: underline;"><?= t('auth.register_link') ?></a>
      </div>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/layout/foot.php'; ?>

