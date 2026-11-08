<?php
/**
 * Dashboard Pemilik (Owner) — Villa Joglo Masayu
 * Kebutuhan: F-12, T-09, T-18
 * - Laporan ringkas statistik & performa reservasi (READ-ONLY)
 * - TIDAK ADA tombol atau form pengubah status reservasi (T-09)
 * - Menampilkan total estimasi nilai reservasi terkonfirmasi dengan label wajib:
 *   "estimasi, bukan uang masuk aktual" (karena pembayaran online tidak termasuk ruang lingkup)
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Hak akses: hanya untuk Pemilik atau Admin
require_role(['pemilik', 'admin'], 'login.php');

$pdo = get_db();

// Filter Periode & Villa
$filterTipe  = (int)($_GET['id_tipe'] ?? 0);
$filterBulan = trim($_GET['bulan'] ?? ''); // format YYYY-MM

$whereClauses = [];
$params = [];

if ($filterTipe > 0) {
    $whereClauses[] = "r.id_tipe = ?";
    $params[] = $filterTipe;
}

if ($filterBulan && preg_match('/^\d{4}-\d{2}$/', $filterBulan)) {
    $whereClauses[] = "DATE_FORMAT(r.tanggal_checkin, '%Y-%m') = ?";
    $params[] = $filterBulan;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// 1. Ringkasan Keseluruhan Berdasarkan Filter
$sqlSummary = "SELECT 
                COUNT(*) AS total_reservasi,
                SUM(CASE WHEN r.status = 'menunggu' THEN 1 ELSE 0 END) AS total_menunggu,
                SUM(CASE WHEN r.status = 'dikonfirmasi' THEN 1 ELSE 0 END) AS total_konfirmasi,
                SUM(CASE WHEN r.status = 'ditolak' THEN 1 ELSE 0 END) AS total_ditolak,
                SUM(CASE WHEN r.status = 'dikonfirmasi' THEN r.estimasi_total ELSE 0 END) AS total_estimasi_konfirmasi
              FROM `reservasi` r
              {$whereSql}";

$stmtSum = $pdo->prepare($sqlSummary);
$stmtSum->execute($params);
$summary = $stmtSum->fetch();

// 2. Ringkasan Per Tipe Villa
$sqlPerTipe = "SELECT 
                v.nama AS nama_villa,
                COUNT(r.id_reservasi) AS total_pesanan,
                SUM(CASE WHEN r.status = 'dikonfirmasi' THEN 1 ELSE 0 END) AS total_terkonfirmasi,
                SUM(CASE WHEN r.status = 'dikonfirmasi' THEN r.estimasi_total ELSE 0 END) AS estimasi_nilai
               FROM `tipe_villa` v
               LEFT JOIN `reservasi` r ON v.id_tipe = r.id_tipe
               GROUP BY v.id_tipe, v.nama
               ORDER BY v.id_tipe ASC";
$perTipeList = $pdo->query($sqlPerTipe)->fetchAll();

// 3. Daftar Detail Reservasi (READ-ONLY, tidak ada form tindakan)
$sqlDetail = "SELECT r.*, v.nama AS nama_villa, p.nama AS nama_pelanggan, p.email AS email_pelanggan, staf.nama AS nama_staf
              FROM `reservasi` r
              JOIN `tipe_villa` v ON r.id_tipe = v.id_tipe
              JOIN `pengguna` p ON r.id_pelanggan = p.id_pengguna
              LEFT JOIN `pengguna` staf ON r.diproses_oleh = staf.id_pengguna
              {$whereSql}
              ORDER BY r.tanggal_checkin DESC, r.id_reservasi DESC";

$stmtDetail = $pdo->prepare($sqlDetail);
$stmtDetail->execute($params);
$detailList = $stmtDetail->fetchAll();

// Ambil list villa untuk dropdown filter
$villasDropdown = $pdo->query("SELECT `id_tipe`, `nama` FROM `tipe_villa` ORDER BY `id_tipe` ASC")->fetchAll();

$pageTitle = t('dashboard.pemilik_title') . ' — Villa Joglo Masayu';
$pageDesc  = 'Pantau statistik permohonan reservasi dan estimasi nilai transaksi terkonfirmasi.';
$bodyClass = 'page-villa';

require_once __DIR__ . '/../includes/layout/head.php';
?>

<main class="container" style="padding-top: calc(var(--header-h-scrolled) + 3rem); padding-bottom: 5rem;">
  <div style="margin-bottom: 2rem;">
    <p class="eyebrow"><?= t('dashboard.pemilik_title') ?></p>
    <h1 class="section-heading" style="margin-bottom: 0.5rem;"><?= t('dashboard.pemilik_title') ?></h1>
    <p class="body-text">
      Ringkasan performa pemesanan dan estimasi nilai reservasi. Seluruh data disajikan secara read-only untuk kebutuhan evaluasi bisnis.
    </p>
  </div>

  <!-- Kartu Highlight Estimasi Nilai Reservasi -->
  <div style="background: linear-gradient(135deg, #FFFAF0 0%, #FAF3E3 100%); border: 2px solid var(--gold); border-radius: var(--radius-img); padding: clamp(1.5rem, 3vw, 2.25rem); margin-bottom: 2rem; box-shadow: 0 4px 20px rgba(59,36,22,0.06);">
    <span style="font-size: 13px; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase; color: var(--gold-dark); display: block; margin-bottom: 0.35rem;">
      <?= t('dashboard.total_omzet') ?>
    </span>
    <div style="font-family: 'Playfair Display', serif; font-size: clamp(2.2rem, 4vw, 3.2rem); color: var(--teak); line-height: 1.15;">
      <?= format_rupiah($summary['total_estimasi_konfirmasi'] ?? 0) ?>
    </div>
    
    <!-- LABEL WAJIB F-12 -->
    <div style="margin-top: 0.75rem; padding: 0.6rem 0.9rem; background: rgba(227, 166, 0, 0.12); border-left: 3px solid var(--gold); border-radius: 2px; font-size: 13px; color: var(--teak);">
      <strong>Catatan Kepatuhan:</strong> <?= t('booking.estimation_notice') ?>
    </div>
  </div>

  <!-- Kartu Statistik Jumlah -->
  <div class="stats-grid">
    <div class="stat-card">
      <span class="stat-card-label"><?= t('dashboard.total_reservasi') ?></span>
      <div class="stat-card-value"><?= (int)($summary['total_reservasi'] ?? 0) ?></div>
      <div class="stat-card-note">Seluruh status pemesanan</div>
    </div>
    <div class="stat-card">
      <span class="stat-card-label"><?= t('dashboard.terkonfirmasi') ?></span>
      <div class="stat-card-value" style="color: #065F46;"><?= (int)($summary['total_konfirmasi'] ?? 0) ?></div>
      <div class="stat-card-note">Reservasi telah disetujui</div>
    </div>
    <div class="stat-card">
      <span class="stat-card-label">Menunggu</span>
      <div class="stat-card-value" style="color: #92400E;"><?= (int)($summary['total_menunggu'] ?? 0) ?></div>
      <div class="stat-card-note">Menunggu respon pengelola</div>
    </div>
    <div class="stat-card">
      <span class="stat-card-label">Ditolak</span>
      <div class="stat-card-value" style="color: #991B1B;"><?= (int)($summary['total_ditolak'] ?? 0) ?></div>
      <div class="stat-card-note">Jadwal/kapasitas bentrok</div>
    </div>
  </div>

  <!-- Ringkasan Per Tipe Villa -->
  <div class="form-card" style="margin-bottom: 2rem;">
    <h3 class="form-title" style="font-size: 1.35rem; margin-bottom: 1rem;">Performa Berdasarkan Tipe Villa</h3>
    <div class="table-responsive" style="margin-bottom: 0;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Tipe Villa</th>
            <th>Total Pemesanan</th>
            <th>Terkonfirmasi</th>
            <th>Estimasi Nilai Terkonfirmasi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($perTipeList as $pt): ?>
            <tr>
              <td><strong><?= e($pt['nama_villa']) ?></strong></td>
              <td><?= (int)$pt['total_pesanan'] ?> reservasi</td>
              <td><span class="badge-status status-dikonfirmasi"><?= (int)$pt['total_terkonfirmasi'] ?></span></td>
              <td>
                <strong><?= format_rupiah($pt['estimasi_nilai'] ?? 0) ?></strong><br>
                <small style="color: rgba(59,36,22,0.6); font-style: italic;">*estimasi, bukan uang masuk aktual</small>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Bar Filter Laporan Detail -->
  <div class="filter-bar">
    <form method="GET" action="dashboard/pemilik.php" class="filter-form">
      <span class="filter-label">Filter Periode & Tipe:</span>
      
      <input type="month" name="bulan" value="<?= e($filterBulan) ?>" class="form-control" style="width: auto; padding: 6px 12px; font-size: 13.5px;" />

      <select name="id_tipe" class="form-control" style="width: auto; padding: 6px 12px; font-size: 13.5px;">
        <option value="">Semua Tipe Villa</option>
        <?php foreach ($villasDropdown as $vd): ?>
          <option value="<?= e($vd['id_tipe']) ?>" <?= $filterTipe === (int)$vd['id_tipe'] ? 'selected' : '' ?>>
            <?= e($vd['nama']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <button type="submit" class="btn btn-sm btn-gold">Terapkan</button>
      <?php if ($filterBulan || $filterTipe): ?>
        <a href="dashboard/pemilik.php" class="btn btn-sm btn-ghost">Reset Filter</a>
      <?php endif; ?>
    </form>
    <div style="font-size: 13px; color: var(--wood);">
      Menampilkan <strong><?= count($detailList) ?></strong> baris data
    </div>
  </div>

  <!-- Tabel Rincian Data Reservasi (READ-ONLY) -->
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Kode</th>
          <th>Tamu & Email</th>
          <th>Tipe Villa</th>
          <th>Tanggal Menginap</th>
          <th>Tamu</th>
          <th>Estimasi Nilai</th>
          <th>Status</th>
          <th>Petugas Pemroses</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($detailList)): ?>
          <tr>
            <td colspan="8" style="text-align: center; padding: 2rem; color: var(--wood);">
              Tidak ada data reservasi pada filter yang dipilih.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($detailList as $r): ?>
            <tr>
              <td><strong>#RSV-<?= e($r['id_reservasi']) ?></strong></td>
              <td>
                <?= e($r['nama_pelanggan']) ?><br>
                <small style="color: var(--wood);"><?= e($r['email_pelanggan']) ?></small>
              </td>
              <td><?= e($r['nama_villa']) ?></td>
              <td>
                <?= date('d/m/Y', strtotime($r['tanggal_checkin'])) ?> s.d. <?= date('d/m/Y', strtotime($r['tanggal_checkout'])) ?>
              </td>
              <td><?= e($r['jumlah_tamu']) ?> org</td>
              <td>
                <strong><?= format_rupiah($r['estimasi_total']) ?></strong><br>
                <small style="color: rgba(59,36,22,0.6); font-style: italic;">*Estimasi</small>
              </td>
              <td>
                <span class="badge-status status-<?= e($r['status']) ?>">
                  <?= e($r['status']) ?>
                </span>
              </td>
              <td style="font-size: 13px; color: var(--wood);">
                <?= !empty($r['nama_staf']) ? e($r['nama_staf']) : '<span style="color:#aaa;">-</span>' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</main>

<?php require_once __DIR__ . '/../includes/layout/foot.php'; ?>

