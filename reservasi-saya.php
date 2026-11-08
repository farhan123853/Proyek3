<?php
/**
 * Riwayat Reservasi Pelanggan — Villa Joglo Masayu
 * Kebutuhan: F-10, T-07
 * Proteksi IDOR: Hanya menampilkan data reservasi milik pengguna yang sedang login.
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Wajib login sebagai pelanggan (atau staf jika melihat reservasi pribadi)
require_login('login.php');

$userId = current_user_id();
$pdo = get_db();

// F-10: Query dikunci dengan id_pelanggan = current_user_id()
$sql = "SELECT r.*, v.nama AS nama_villa, v.slug AS slug_villa, p.nama AS nama_pengelola
        FROM `reservasi` r
        JOIN `tipe_villa` v ON r.id_tipe = v.id_tipe
        LEFT JOIN `pengguna` p ON r.diproses_oleh = p.id_pengguna
        WHERE r.id_pelanggan = ?
        ORDER BY r.id_reservasi DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$reservasiList = $stmt->fetchAll();

$pageTitle = t('my_bookings.title') . ' — Villa Joglo Masayu';
$pageDesc  = t('my_bookings.desc');
$bodyClass = 'page-villa';

require_once __DIR__ . '/includes/layout/head.php';
?>

<main class="container" style="padding-top: calc(var(--header-h-scrolled) + 3rem); padding-bottom: 5rem;">
  <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
      <p class="eyebrow"><?= t('my_bookings.eyebrow') ?></p>
      <h1 class="section-heading" style="margin-bottom: 0.5rem;"><?= t('my_bookings.title') ?></h1>
      <p class="body-text" style="margin-bottom: 0;"><?= t('my_bookings.desc') ?></p>
    </div>
    <div>
      <a href="reservasi.php" class="btn btn-gold">+ <?= t('booking.step1') ?></a>
    </div>
  </div>

  <?php if (empty($reservasiList)): ?>
    <div class="form-card" style="text-align: center; padding: 4rem 2rem;">
      <h3 style="font-family: 'Playfair Display', serif; color: var(--teak); margin-bottom: 0.75rem;"><?= t('my_bookings.empty') ?></h3>
      <p style="color: var(--wood); margin-bottom: 1.5rem;"><?= t('booking.desc') ?></p>
      <a href="reservasi.php" class="btn btn-gold"><?= t('villa.cta_booking') ?></a>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th><?= t('my_bookings.code') ?></th>
            <th><?= t('booking.villa_label') ?></th>
            <th><?= t('my_bookings.dates') ?></th>
            <th><?= t('booking.guests_label') ?></th>
            <th><?= t('booking.total_estimation') ?></th>
            <th><?= t('common.status') ?></th>
            <th><?= t('common.action') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reservasiList as $r): ?>
            <tr>
              <td><strong>#RSV-<?= e($r['id_reservasi']) ?></strong></td>
              <td>
                <a href="villa.php?slug=<?= e($r['slug_villa']) ?>" style="color: var(--teak); font-weight: 700; text-decoration: underline;">
                  <?= e($r['nama_villa']) ?>
                </a>
              </td>
              <td>
                <?= format_tanggal_id($r['tanggal_checkin']) ?><br>
                <small style="color: var(--wood);"><?= t('common.to') ?> <?= format_tanggal_id($r['tanggal_checkout']) ?></small>
              </td>
              <td><?= e($r['jumlah_tamu']) ?> <?= t('common.guests') ?></td>
              <td>
                <strong><?= format_rupiah($r['estimasi_total']) ?></strong><br>
                <small style="color: rgba(59,36,22,0.6); font-style: italic;">*<?= t('booking.estimation_notice') ?></small>
              </td>
              <td>
                <?php
                  $statusKey = 'my_bookings.status_' . $r['status'];
                  $statusLabel = t($statusKey);
                ?>
                <span class="badge-status status-<?= e($r['status']) ?>">
                  <?= e($statusLabel) ?>
                </span>
                <?php if ($r['status'] === 'dikonfirmasi' && !empty($r['nama_pengelola'])): ?>
                  <div style="font-size: 11px; color: var(--wood); margin-top: 2px;">
                    Oleh: <?= e($r['nama_pengelola']) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <?php
                  $pesanWa = urlencode("Halo Pengelola Joglo Masayu, saya ingin menanyakan status reservasi #RSV-{$r['id_reservasi']} untuk {$r['nama_villa']}. Terima kasih.");
                  $waUrl = "https://wa.me/6281234567890?text={$pesanWa}";
                ?>
                <a href="<?= $waUrl ?>" target="_blank" rel="noopener" class="btn btn-sm btn-ghost" style="padding: 6px 12px; font-size: 11px;">
                  <?= t('my_bookings.btn_whatsapp') ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="background: var(--sand); padding: 1rem 1.25rem; border-radius: var(--radius-btn); font-size: 13px; color: var(--wood); line-height: 1.6;">
      <strong><?= t('booking.price_summary') ?>:</strong>
      <?= t('booking.estimation_notice') ?>
    </div>
  <?php endif; ?>
</main>

<?php require_once __DIR__ . '/includes/layout/foot.php'; ?>

