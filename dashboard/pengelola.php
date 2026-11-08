<?php
/**
 * Dashboard Pengelola — Villa Joglo Masayu
 * Kebutuhan: F-11, T-08
 * - Daftar dan penyaring status reservasi
 * - Konfirmasi dan tolak permintaan (POST + CSRF)
 * - Transaksi basis data (BEGIN ... SELECT FOR UPDATE) untuk mencegah race condition / double-booking
 * - Mencatat diproses_oleh
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Hak akses: hanya untuk Pengelola atau Admin
require_role(['pengelola', 'admin'], 'login.php');

$pdo = get_db();
$currentUserId = current_user_id();

// Tangani Aksi Konfirmasi / Tolak via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $idReservasi = (int)($_POST['id_reservasi'] ?? 0);
    $aksi        = trim($_POST['aksi'] ?? '');

    if ($idReservasi && in_array($aksi, ['konfirmasi', 'tolak'], true)) {
        if ($aksi === 'tolak') {
            // Update status menjadi ditolak
            $stmtTolak = $pdo->prepare("UPDATE `reservasi` SET `status` = 'ditolak', `diproses_oleh` = ? WHERE `id_reservasi` = ?");
            $stmtTolak->execute([$currentUserId, $idReservasi]);
            flash_set('warning', "Reservasi #RSV-{$idReservasi} telah ditolak.");
            redirect('dashboard/pengelola.php');
        } elseif ($aksi === 'konfirmasi') {
            // F-11 & T-08: Konfirmasi dalam transaksi dengan kunci baris (FOR UPDATE)
            try {
                $pdo->beginTransaction();

                // 1. Ambil data reservasi target
                $stmtTarget = $pdo->prepare("SELECT * FROM `reservasi` WHERE `id_reservasi` = ? FOR UPDATE");
                $stmtTarget->execute([$idReservasi]);
                $target = $stmtTarget->fetch();

                if (!$target) {
                    $pdo->rollBack();
                    flash_set('danger', 'Data reservasi tidak ditemukan.');
                    redirect('dashboard/pengelola.php');
                }

                // 2. Ambil data tipe villa dan jumlah unit
                $stmtVilla = $pdo->prepare("SELECT `id_tipe`, `nama`, `jumlah_unit` FROM `tipe_villa` WHERE `id_tipe` = ? FOR UPDATE");
                $stmtVilla->execute([$target['id_tipe']]);
                $villaInfo = $stmtVilla->fetch();

                $jumlahUnit = (int)($villaInfo['jumlah_unit'] ?? 1);

                // 3. Periksa ketersediaan: hitung reservasi lain yang telah dikonfirmasi dan tumpang tindih
                $sqlCheck = "SELECT COUNT(*) FROM `reservasi` 
                             WHERE `id_tipe` = :id_tipe 
                               AND `status` = 'dikonfirmasi' 
                               AND `id_reservasi` != :id_target
                               AND `tanggal_checkin` < :checkout 
                               AND `tanggal_checkout` > :checkin 
                             FOR UPDATE";

                $stmtCheck = $pdo->prepare($sqlCheck);
                $stmtCheck->execute([
                    'id_tipe'    => $target['id_tipe'],
                    'id_target'  => $idReservasi,
                    'checkout'   => $target['tanggal_checkout'],
                    'checkin'    => $target['tanggal_checkin']
                ]);
                $bentrok = (int)$stmtCheck->fetchColumn();

                if ($bentrok >= $jumlahUnit) {
                    // Batalkan transaksi, status tetap menunggu
                    $pdo->rollBack();
                    flash_set('danger', "Gagal mengonfirmasi #RSV-{$idReservasi}: Seluruh unit ({$jumlahUnit} unit) {$villaInfo['nama']} sudah terisi pada tanggal tersebut. Status reservasi tetap menunggu.");
                    redirect('dashboard/pengelola.php');
                }

                // 4. Update status ke dikonfirmasi
                $stmtKonfirmasi = $pdo->prepare("UPDATE `reservasi` SET `status` = 'dikonfirmasi', `diproses_oleh` = ? WHERE `id_reservasi` = ?");
                $stmtKonfirmasi->execute([$currentUserId, $idReservasi]);

                $pdo->commit();
                flash_set('success', "Reservasi #RSV-{$idReservasi} berhasil dikonfirmasi!");
                redirect('dashboard/pengelola.php');
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                flash_set('danger', 'Terjadi kesalahan sistem saat memproses konfirmasi: ' . $e->getMessage());
                redirect('dashboard/pengelola.php');
            }
        }
    }
}

// Filter Status
$filterStatus = trim($_GET['status'] ?? '');
$allowedStatus = ['menunggu', 'dikonfirmasi', 'ditolak'];

$whereSql = '';
$params = [];
if (in_array($filterStatus, $allowedStatus, true)) {
    $whereSql = "WHERE r.status = ?";
    $params[] = $filterStatus;
}

$sql = "SELECT r.*, v.nama AS nama_villa, v.slug AS slug_villa, 
               p.nama AS nama_pelanggan, p.email AS email_pelanggan, p.kontak AS kontak_pelanggan,
               staf.nama AS nama_staf
        FROM `reservasi` r
        JOIN `tipe_villa` v ON r.id_tipe = v.id_tipe
        JOIN `pengguna` p ON r.id_pelanggan = p.id_pengguna
        LEFT JOIN `pengguna` staf ON r.diproses_oleh = staf.id_pengguna
        {$whereSql}
        ORDER BY 
          CASE WHEN r.status = 'menunggu' THEN 1 ELSE 2 END,
          r.tanggal_checkin ASC, 
          r.id_reservasi DESC";

$stmtList = $pdo->prepare($sql);
$stmtList->execute($params);
$reservasiList = $stmtList->fetchAll();

// Statistik ringkas status
$statMenunggu = (int)$pdo->query("SELECT COUNT(*) FROM `reservasi` WHERE `status` = 'menunggu'")->fetchColumn();
$statKonfirmasi = (int)$pdo->query("SELECT COUNT(*) FROM `reservasi` WHERE `status` = 'dikonfirmasi'")->fetchColumn();
$statDitolak = (int)$pdo->query("SELECT COUNT(*) FROM `reservasi` WHERE `status` = 'ditolak'")->fetchColumn();

$pageTitle = t('dashboard.pengelola_title') . ' — Villa Joglo Masayu';
$pageDesc  = 'Kelola dan proses permintaan reservasi tamu Villa Joglo Masayu.';
$bodyClass = 'page-villa';

require_once __DIR__ . '/../includes/layout/head.php';
?>

<main class="container" style="padding-top: calc(var(--header-h-scrolled) + 3rem); padding-bottom: 5rem;">
  <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
      <p class="eyebrow"><?= t('dashboard.pengelola_title') ?></p>
      <h1 class="section-heading" style="margin-bottom: 0.5rem;"><?= t('dashboard.pengelola_title') ?></h1>
      <p class="body-text" style="margin-bottom: 0;">Kelola permohonan reservasi tamu dan pantau ketersediaan kamar.</p>
    </div>
  </div>

  <!-- Kartu Statistik -->
  <div class="stats-grid">
    <div class="stat-card <?= $statMenunggu > 0 ? 'stat-card--highlight' : '' ?>">
      <span class="stat-card-label"><?= t('dashboard.menunggu_konfirmasi') ?></span>
      <div class="stat-card-value" style="color: #92400E;"><?= $statMenunggu ?></div>
      <div class="stat-card-note">Perlu segera diproses</div>
    </div>
    <div class="stat-card">
      <span class="stat-card-label"><?= t('dashboard.terkonfirmasi') ?></span>
      <div class="stat-card-value" style="color: #065F46;"><?= $statKonfirmasi ?></div>
      <div class="stat-card-note">Jadwal tamu aktif</div>
    </div>
    <div class="stat-card">
      <span class="stat-card-label">Permintaan Ditolak</span>
      <div class="stat-card-value" style="color: #991B1B;"><?= $statDitolak ?></div>
      <div class="stat-card-note">Kapasitas/jadwal tidak sesuai</div>
    </div>
  </div>

  <!-- Bar Penyaring Status -->
  <div class="filter-bar">
    <form method="GET" action="dashboard/pengelola.php" class="filter-form">
      <span class="filter-label"><?= t('common.filter') ?>:</span>
      <select name="status" class="form-control" style="width: auto; padding: 6px 12px; font-size: 13.5px;" onchange="this.form.submit()">
        <option value=""><?= t('common.all') ?> (<?= $statMenunggu + $statKonfirmasi + $statDitolak ?>)</option>
        <option value="menunggu" <?= $filterStatus === 'menunggu' ? 'selected' : '' ?>><?= t('my_bookings.status_menunggu') ?> (<?= $statMenunggu ?>)</option>
        <option value="dikonfirmasi" <?= $filterStatus === 'dikonfirmasi' ? 'selected' : '' ?>><?= t('my_bookings.status_dikonfirmasi') ?> (<?= $statKonfirmasi ?>)</option>
        <option value="ditolak" <?= $filterStatus === 'ditolak' ? 'selected' : '' ?>><?= t('common.cancel') ?> (<?= $statDitolak ?>)</option>
      </select>
      <?php if ($filterStatus): ?>
        <a href="dashboard/pengelola.php" class="btn btn-sm btn-ghost">Reset</a>
      <?php endif; ?>
    </form>
    <div style="font-size: 13px; color: var(--wood);">
      Menampilkan <strong><?= count($reservasiList) ?></strong> permintaan
    </div>
  </div>

  <!-- Tabel Reservasi -->
  <?php if (empty($reservasiList)): ?>
    <div class="form-card" style="text-align: center; padding: 3rem;">
      <p style="color: var(--wood); margin: 0;">Tidak ada data reservasi dengan penyaring status yang dipilih.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Kode</th>
            <th>Pemesan & Kontak</th>
            <th>Tipe Villa</th>
            <th>Check-In & Out</th>
            <th>Tamu</th>
            <th>Estimasi Nilai</th>
            <th>Status</th>
            <th>Catatan</th>
            <th>Tindakan Pengelola</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reservasiList as $r): ?>
            <tr>
              <td><strong>#RSV-<?= e($r['id_reservasi']) ?></strong></td>
              <td>
                <strong><?= e($r['nama_pelanggan']) ?></strong><br>
                <small style="color: var(--wood);"><?= e($r['kontak_pelanggan']) ?></small><br>
                <small style="color: var(--wood);"><?= e($r['email_pelanggan']) ?></small>
              </td>
              <td><?= e($r['nama_villa']) ?></td>
              <td>
                <strong><?= date('d/m/Y', strtotime($r['tanggal_checkin'])) ?></strong><br>
                s.d. <?= date('d/m/Y', strtotime($r['tanggal_checkout'])) ?>
              </td>
              <td><?= e($r['jumlah_tamu']) ?> orang</td>
              <td>
                <strong><?= format_rupiah($r['estimasi_total']) ?></strong><br>
                <small style="color: rgba(59,36,22,0.6); font-style: italic;">*Estimasi</small>
              </td>
              <td>
                <span class="badge-status status-<?= e($r['status']) ?>">
                  <?= e($r['status']) ?>
                </span>
                <?php if (!empty($r['nama_staf'])): ?>
                  <div style="font-size: 11px; color: var(--wood); margin-top: 2px;">
                    Oleh: <?= e($r['nama_staf']) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td style="max-width: 180px; font-size: 12.5px; color: var(--wood);">
                <?= !empty($r['catatan']) ? e($r['catatan']) : '<span style="color:#aaa;">-</span>' ?>
              </td>
              <td>
                <?php if ($r['status'] === 'menunggu'): ?>
                  <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                    <form method="POST" action="dashboard/pengelola.php" onsubmit="return confirm('Apakah Anda yakin ingin MENGONFIRMASI reservasi #RSV-<?= e($r['id_reservasi']) ?>?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id_reservasi" value="<?= e($r['id_reservasi']) ?>" />
                      <input type="hidden" name="aksi" value="konfirmasi" />
                      <button type="submit" class="btn btn-sm btn-success">Konfirmasi</button>
                    </form>

                    <form method="POST" action="dashboard/pengelola.php" onsubmit="return confirm('Apakah Anda yakin ingin MENOLAK reservasi #RSV-<?= e($r['id_reservasi']) ?>?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id_reservasi" value="<?= e($r['id_reservasi']) ?>" />
                      <input type="hidden" name="aksi" value="tolak" />
                      <button type="submit" class="btn btn-sm btn-danger">Tolak</button>
                    </form>
                  </div>
                <?php else: ?>
                  <span style="font-size: 12px; color: var(--wood);">Selesai diproses</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../includes/layout/foot.php'; ?>

