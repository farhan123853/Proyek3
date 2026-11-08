<?php
/**
 * Registrasi Akun Pelanggan — Villa Joglo Masayu
 * Kebutuhan: F-04, T-02
 * Registrasi publik HANYA membuat peran 'pelanggan' (mengabaikan input peran apapun).
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Jika sudah login, redirect sesuai peran
if (is_logged_in()) {
    redirect(get_role_redirect_url(current_role()));
}

$errors = [];
$nama   = trim($_POST['nama'] ?? '');
$email  = trim($_POST['email'] ?? '');
$kontak = trim($_POST['kontak'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $password   = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi_password'] ?? '';

    // Validasi input
    if (empty($nama)) {
        $errors['nama'] = 'Nama lengkap wajib diisi.';
    }

    if (empty($email)) {
        $errors['email'] = 'Alamat email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format alamat email tidak valid.';
    }

    if (empty($kontak)) {
        $errors['kontak'] = 'Nomor kontak/WhatsApp wajib diisi.';
    }

    if (empty($password)) {
        $errors['password'] = 'Kata sandi wajib diisi.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Kata sandi minimal 6 karakter.';
    }

    if ($password !== $konfirmasi) {
        $errors['konfirmasi_password'] = 'Konfirmasi kata sandi tidak cocok.';
    }

    // Periksa apakah email sudah terdaftar
    if (empty($errors)) {
        $pdo = get_db();
        $stmtCek = $pdo->prepare("SELECT `id_pengguna` FROM `pengguna` WHERE `email` = ? LIMIT 1");
        $stmtCek->execute([$email]);
        if ($stmtCek->fetch()) {
            $errors['email'] = 'Alamat email sudah terdaftar. Silakan gunakan email lain atau masuk ke akun Anda.';
        }
    }

    // Simpan ke database jika valid
    if (empty($errors)) {
        $pdo = get_db();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        // F-04: Paksa peran selalu 'pelanggan' tanpa memperdulikan input apapun
        $peran = 'pelanggan';
        $statusAkun = 'aktif';

        $sql = "INSERT INTO `pengguna` (`nama`, `email`, `kontak`, `password_hash`, `peran`, `status_akun`, `dibuat_pada`)
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nama, $email, $kontak, $hash, $peran, $statusAkun]);

        $newId = (int)$pdo->lastInsertId();

        // Login otomatis setelah registrasi berhasil
        login_user([
            'id_pengguna' => $newId,
            'nama'        => $nama,
            'email'       => $email,
            'kontak'      => $kontak,
            'peran'       => $peran
        ]);

        flash_set('success', t('auth.registration_success', ['nama' => $nama]));
        redirect('reservasi.php');
    }
}

$pageTitle = t('auth.register_title') . ' — Villa Joglo Masayu';
$pageDesc  = t('auth.register_desc');
$bodyClass = 'page-villa';

require_once __DIR__ . '/includes/layout/head.php';
?>

<main class="container" style="padding-top: calc(var(--header-h-scrolled) + 3rem); padding-bottom: 5rem;">
  <div style="max-width: 540px; margin: 0 auto;">

    <div class="form-card">
      <p class="eyebrow"><?= t('auth.register_eyebrow') ?></p>
      <h1 class="form-title"><?= t('auth.register_title') ?></h1>
      <p class="form-desc"><?= t('auth.register_desc') ?></p>

      <form method="POST" action="daftar.php" novalidate>
        <?= csrf_field() ?>

        <div class="form-group">
          <label class="form-label" for="nama"><?= t('auth.name_label') ?> <span class="required">*</span></label>
          <input type="text" id="nama" name="nama" class="form-control" value="<?= e($nama) ?>" required autocomplete="name" />
          <?php if (isset($errors['nama'])): ?>
            <div class="form-error"><?= e($errors['nama']) ?></div>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="email"><?= t('auth.email_label') ?> <span class="required">*</span></label>
          <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" required autocomplete="email" />
          <?php if (isset($errors['email'])): ?>
            <div class="form-error"><?= e($errors['email']) ?></div>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="kontak"><?= t('auth.phone_label') ?> <span class="required">*</span></label>
          <input type="tel" id="kontak" name="kontak" class="form-control" value="<?= e($kontak) ?>" placeholder="Contoh: 081234567890" required autocomplete="tel" />
          <?php if (isset($errors['kontak'])): ?>
            <div class="form-error"><?= e($errors['kontak']) ?></div>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="password"><?= t('auth.password_label') ?> <span class="required">*</span></label>
          <input type="password" id="password" name="password" class="form-control" required autocomplete="new-password" />
          <div class="form-hint">Minimal 6 karakter.</div>
          <?php if (isset($errors['password'])): ?>
            <div class="form-error"><?= e($errors['password']) ?></div>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="konfirmasi_password"><?= t('account.confirm_new_password') ?> <span class="required">*</span></label>
          <input type="password" id="konfirmasi_password" name="konfirmasi_password" class="form-control" required autocomplete="new-password" />
          <?php if (isset($errors['konfirmasi_password'])): ?>
            <div class="form-error"><?= e($errors['konfirmasi_password']) ?></div>
          <?php endif; ?>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-gold" style="width: 100%; justify-content: center;"><?= t('auth.btn_register') ?></button>
        </div>
      </form>

      <div style="margin-top: 1.5rem; text-align: center; font-size: 14px; color: var(--wood);">
        <?= t('auth.have_account') ?> <a href="login.php" style="color: var(--gold-dark); font-weight: 700; text-decoration: underline;"><?= t('auth.login_link') ?></a>
      </div>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/layout/foot.php'; ?>

