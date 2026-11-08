<?php
/**
 * Dashboard Administrator — Villa Joglo Masayu
 * Kebutuhan: F-13, T-10
 * - Manajemen akun staf (Pengelola & Pemilik)
 * - Aktifkan / Nonaktifkan akun
 * - Ubah peran pengguna
 * - Keamanan: Admin TIDAK BOLEH menonaktifkan akunnya sendiri (T-10)
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Hak akses: murni hanya untuk Admin
require_role('admin', 'login.php');

$pdo = get_db();
$currentAdminId = current_user_id();

$formErrors = [];
$namaBaru   = trim($_POST['nama'] ?? '');
$emailBaru  = trim($_POST['email'] ?? '');
$kontakBaru = trim($_POST['kontak'] ?? '');
$peranBaru  = trim($_POST['peran'] ?? 'pengelola');

// Tangani Form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    // 1. Tambah Akun Staf Baru (Pengelola / Pemilik)
    if ($action === 'create_staff') {
        $password = $_POST['password'] ?? '';

        if (empty($namaBaru)) {
            $formErrors['nama'] = 'Nama lengkap wajib diisi.';
        }
        if (empty($emailBaru) || !filter_var($emailBaru, FILTER_VALIDATE_EMAIL)) {
            $formErrors['email'] = 'Alamat email tidak valid.';
        }
        if (empty($kontakBaru)) {
            $formErrors['kontak'] = 'Nomor kontak wajib diisi.';
        }
        if (empty($password) || strlen($password) < 6) {
            $formErrors['password'] = 'Kata sandi minimal 6 karakter.';
        }
        if (!in_array($peranBaru, ['pengelola', 'pemilik'], true)) {
            $formErrors['peran'] = 'Peran yang diizinkan hanya Pengelola atau Pemilik.';
        }

        // Cek email ganda
        if (empty($formErrors)) {
            $stmtCek = $pdo->prepare("SELECT `id_pengguna` FROM `pengguna` WHERE `email` = ? LIMIT 1");
            $stmtCek->execute([$emailBaru]);
            if ($stmtCek->fetch()) {
                $formErrors['email'] = 'Email ini telah digunakan.';
            }
        }

        if (empty($formErrors)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmtInsert = $pdo->prepare("INSERT INTO `pengguna` (`nama`, `email`, `kontak`, `password_hash`, `peran`, `status_akun`, `dibuat_pada`) VALUES (?, ?, ?, ?, ?, 'aktif', NOW())");
            $stmtInsert->execute([$namaBaru, $emailBaru, $kontakBaru, $hash, $peranBaru]);

            flash_set('success', "Akun {$peranBaru} baru atas nama " . e($namaBaru) . " berhasil dibuat!");
            redirect('dashboard/admin.php');
        }
    }

    // 2. Toggle Status Akun (Aktif / Nonaktif)
    if ($action === 'toggle_status') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        $newStatus = trim($_POST['new_status'] ?? '');

        // T-10: Admin tidak boleh menonaktifkan dirinya sendiri!
        if ($targetId === $currentAdminId) {
            flash_set('danger', 'Pelanggaran keamanan: Anda tidak dapat menonaktifkan akun Admin Anda sendiri!');
            redirect('dashboard/admin.php');
        }

        if (in_array($newStatus, ['aktif', 'nonaktif'], true) && $targetId > 0) {
            $stmtToggle = $pdo->prepare("UPDATE `pengguna` SET `status_akun` = ? WHERE `id_pengguna` = ?");
            $stmtToggle->execute([$newStatus, $targetId]);

            flash_set('success', "Status akun #{$targetId} berhasil diubah menjadi: {$newStatus}.");
            redirect('dashboard/admin.php');
        }
    }

    // 3. Ubah Peran Pengguna
    if ($action === 'change_role') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        $targetRole = trim($_POST['target_role'] ?? '');

        // Cegah admin mengubah perannya sendiri menjadi non-admin
        if ($targetId === $currentAdminId && $targetRole !== 'admin') {
            flash_set('danger', 'Anda tidak dapat menghapus hak akses Admin dari akun Anda sendiri.');
            redirect('dashboard/admin.php');
        }

        $allowedRoles = ['pelanggan', 'pengelola', 'pemilik', 'admin'];
        if (in_array($targetRole, $allowedRoles, true) && $targetId > 0) {
            $stmtRole = $pdo->prepare("UPDATE `pengguna` SET `peran` = ? WHERE `id_pengguna` = ?");
            $stmtRole->execute([$targetRole, $targetId]);

            flash_set('success', "Peran akun #{$targetId} berhasil diperbarui menjadi {$targetRole}.");
            redirect('dashboard/admin.php');
        }
    }
}

// Ambil daftar seluruh pengguna
$stmtAll = $pdo->query("SELECT * FROM `pengguna` ORDER BY `id_pengguna` ASC");
$usersList = $stmtAll->fetchAll();

$pageTitle = 'Dashboard Administrator — Villa Joglo Masayu';
$pageDesc  = 'Kelola akun staf, hak akses, dan pengaturan pengguna sistem Villa Joglo Masayu.';
$bodyClass = 'page-villa';

require_once __DIR__ . '/../includes/layout/head.php';
?>

<main class="container" style="padding-top: calc(var(--header-h-scrolled) + 3rem); padding-bottom: 5rem;">
  <div style="margin-bottom: 2rem;">
    <p class="eyebrow">Manajemen Akses Sistem</p>
    <h1 class="section-heading" style="margin-bottom: 0.5rem;">Dashboard Administrator</h1>
    <p class="body-text">
      Buat akun staf (Pengelola dan Pemilik), kelola status keaktifan, dan atur peran hak akses pengguna.
    </p>
  </div>

  <!-- Form Tambah Akun Staf Baru -->
  <div class="form-card">
    <h3 class="form-title" style="font-size: 1.35rem;">+ Tambah Akun Staf Baru</h3>
    <p class="form-desc">Formulir resmi untuk membuat akun staf internal (Pengelola atau Pemilik Villa).</p>

    <form method="POST" action="dashboard/admin.php" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_staff" />

      <div class="form-grid-3">
        <div class="form-group">
          <label class="form-label" for="nama">Nama Lengkap <span class="required">*</span></label>
          <input type="text" id="nama" name="nama" class="form-control" value="<?= e($namaBaru) ?>" required />
          <?php if (isset($formErrors['nama'])): ?><div class="form-error"><?= e($formErrors['nama']) ?></div><?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="email">Alamat Email <span class="required">*</span></label>
          <input type="email" id="email" name="email" class="form-control" value="<?= e($emailBaru) ?>" required />
          <?php if (isset($formErrors['email'])): ?><div class="form-error"><?= e($formErrors['email']) ?></div><?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="kontak">Kontak / No. Telepon <span class="required">*</span></label>
          <input type="tel" id="kontak" name="kontak" class="form-control" value="<?= e($kontakBaru) ?>" required />
          <?php if (isset($formErrors['kontak'])): ?><div class="form-error"><?= e($formErrors['kontak']) ?></div><?php endif; ?>
        </div>
      </div>

      <div class="form-grid-2">
        <div class="form-group">
          <label class="form-label" for="peran">Pilih Peran <span class="required">*</span></label>
          <select id="peran" name="peran" class="form-control" required>
            <option value="pengelola" <?= $peranBaru === 'pengelola' ? 'selected' : '' ?>>Pengelola Villa</option>
            <option value="pemilik" <?= $peranBaru === 'pemilik' ? 'selected' : '' ?>>Pemilik Villa (Owner)</option>
          </select>
          <?php if (isset($formErrors['peran'])): ?><div class="form-error"><?= e($formErrors['peran']) ?></div><?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Kata Sandi Awal <span class="required">*</span></label>
          <input type="password" id="password" name="password" class="form-control" required autocomplete="new-password" />
          <div class="form-hint">Minimal 6 karakter.</div>
          <?php if (isset($formErrors['password'])): ?><div class="form-error"><?= e($formErrors['password']) ?></div><?php endif; ?>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-gold">Buat Akun Staf</button>
      </div>
    </form>
  </div>

  <!-- Tabel Seluruh Pengguna Sistem -->
  <div style="margin-top: 3rem;">
    <h3 class="form-title" style="font-size: 1.35rem; margin-bottom: 0.5rem;">Daftar Seluruh Pengguna</h3>
    <p class="form-desc">Total <?= count($usersList) ?> akun terdaftar pada sistem.</p>

    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nama & Email</th>
            <th>Kontak</th>
            <th>Peran</th>
            <th>Status Akun</th>
            <th>Terdaftar</th>
            <th>Tindakan Administrator</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($usersList as $u): ?>
            <tr>
              <td>#<?= e($u['id_pengguna']) ?></td>
              <td>
                <strong><?= e($u['nama']) ?></strong><br>
                <small style="color: var(--wood);"><?= e($u['email']) ?></small>
                <?php if ((int)$u['id_pengguna'] === $currentAdminId): ?>
                  <span style="font-size: 11px; background: #FEF3C7; color: #92400E; padding: 1px 6px; border-radius: 2px; margin-left: 4px;">Akun Anda</span>
                <?php endif; ?>
              </td>
              <td><?= e($u['kontak']) ?></td>
              <td>
                <span class="user-role-badge badge-<?= e($u['peran']) ?>"><?= strtoupper(e($u['peran'])) ?></span>
              </td>
              <td>
                <span class="badge-status status-<?= e($u['status_akun']) ?>">
                  <?= e($u['status_akun']) ?>
                </span>
              </td>
              <td style="font-size: 12.5px; color: var(--wood);">
                <?= date('d/m/Y', strtotime($u['dibuat_pada'])) ?>
              </td>
              <td>
                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                  <!-- Toggle Status Aktif / Nonaktif -->
                  <?php if ((int)$u['id_pengguna'] !== $currentAdminId): ?>
                    <form method="POST" action="dashboard/admin.php" style="display: inline;" onsubmit="return confirm('Ubah status akun ini menjadi <?= $u['status_akun'] === 'aktif' ? 'NONAKTIF' : 'AKTIF' ?>?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle_status" />
                      <input type="hidden" name="target_id" value="<?= e($u['id_pengguna']) ?>" />
                      <input type="hidden" name="new_status" value="<?= $u['status_akun'] === 'aktif' ? 'nonaktif' : 'aktif' ?>" />
                      <button type="submit" class="btn btn-sm <?= $u['status_akun'] === 'aktif' ? 'btn-danger' : 'btn-success' ?>" style="padding: 4px 10px; font-size: 11px;">
                        <?= $u['status_akun'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>
                      </button>
                    </form>

                    <!-- Ubah Peran -->
                    <form method="POST" action="dashboard/admin.php" style="display: inline-flex; align-items: center; gap: 4px;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="change_role" />
                      <input type="hidden" name="target_id" value="<?= e($u['id_pengguna']) ?>" />
                      <select name="target_role" class="form-control" style="width: auto; padding: 4px 8px; font-size: 11.5px; height: auto;" onchange="if(confirm('Ubah peran akun ini menjadi ' + this.value + '?')) this.form.submit(); else this.value='<?= e($u['peran']) ?>';">
                        <option value="pelanggan" <?= $u['peran'] === 'pelanggan' ? 'selected' : '' ?>>Pelanggan</option>
                        <option value="pengelola" <?= $u['peran'] === 'pengelola' ? 'selected' : '' ?>>Pengelola</option>
                        <option value="pemilik" <?= $u['peran'] === 'pemilik' ? 'selected' : '' ?>>Pemilik</option>
                        <option value="admin" <?= $u['peran'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                      </select>
                    </form>
                  <?php else: ?>
                    <span style="font-size: 12px; color: var(--wood); font-style: italic;">Akun Admin Aktif</span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/layout/foot.php'; ?>

