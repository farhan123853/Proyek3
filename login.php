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
            $error = 'Email atau kata sandi yang Anda masukkan salah.';
        } elseif ($user['status_akun'] !== 'aktif') {
            $error = 'Akun Anda sedang dinonaktifkan oleh administrator. Silakan hubungi pengelola.';
        } else {
            // Berhasil login
            login_user($user);

            flash_set('success', 'Selamat datang kembali, ' . e($user['nama']) . '!');
            redirect(get_role_redirect_url($user['peran']));
        }
    }
}

$pageTitle = 'Masuk ke Akun — Villa Joglo Masayu';
$pageDesc  = 'Masuk ke akun Villa Joglo Masayu untuk mengelola reservasi dan informasi penginapan.';
$bodyClass = 'page-villa';

require_once __DIR__ . '/includes/layout/head.php';
?>

<main class="container" style="padding-top: calc(var(--header-h-scrolled) + 3rem); padding-bottom: 5rem;">
  <div style="max-width: 480px; margin: 0 auto;">

    <div class="form-card">
      <p class="eyebrow">Akses Akun</p>
      <h1 class="form-title">Masuk ke Akun</h1>
      <p class="form-desc">Masuk untuk mengelola reservasi atau mengakses dashboard sistem.</p>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger" role="alert" style="margin-bottom: 1.5rem;">
          <span><?= e($error) ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php" novalidate>
        <?= csrf_field() ?>

        <div class="form-group">
          <label class="form-label" for="email">Alamat Email</label>
          <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" required autocomplete="email" autofocus />
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Kata Sandi</label>
          <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password" />
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-gold" style="width: 100%; justify-content: center;">Masuk</button>
        </div>
      </form>

      <div style="margin-top: 1.5rem; text-align: center; font-size: 14px; color: var(--wood);">
        Belum memiliki akun? <a href="daftar.php" style="color: var(--gold-dark); font-weight: 700; text-decoration: underline;">Daftar akun pelanggan</a>
      </div>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/layout/foot.php'; ?>

