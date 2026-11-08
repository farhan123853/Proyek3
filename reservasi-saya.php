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

$pageTitle = 'Riwayat Reservasi Saya — Villa Joglo Masayu';
$pageDesc  = 'Pantau status dan riwayat pengajuan reservasi Villa Joglo Masayu Anda.';
$bodyClass = 'page-villa';

require_once __DIR__ . '/includes/layout/head.php';
?>

<main class="container" style="padding-top: calc(var(--header-h-scrolled) + 3rem); padding-bottom: 5rem;">
  <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
      <p class="eyebrow">Akun Pelanggan</p>
      <h1 class="section-heading" style="margin-bottom: 0.5rem;">Riwayat Reservasi Saya</h1>
      <p class="body-text" style="margin-bottom: 0;">Daftar seluruh permintaan reservasi yang Anda ajukan.</p>
    </div>
    <div>
      <a href="reservasi.php" class="btn btn-gold">+ Buat Reservasi Baru</a>
    </div>
  </div>

  <?php if (empty($reservasiList)): ?>
    <div class="form-card" style="text-align: center; padding: 4rem 2rem;">
      <h3 style="font-family: 'Playfair Display', serif; color: var(--teak); margin-bottom: 0.75rem;">Belum Ada Riwayat Reservasi</h3>
      <p style="color: var(--wood); margin-bottom: 1.5rem;">Anda belum pernah mengajukan pemesanan villa. Mulai liburan tenang Anda di Joglo Masayu.</p>
      <a href="reservasi.php" class="btn btn-gold">Pesan Villa Sekarang</a>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Kode</th>
            <th>Tipe Villa</th>
            <th>Check-In & Check-Out</th>
            <th>Tamu</th>
            <th>Estimasi Total</th>
            <th>Status</th>
            <th>Diajukan</th>
            <th>Tindakan</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reservasiList as $r): ?>
            <tr>
              <td><strong>#RSV-<?= e($r['id_reservasi']) ?></strong></td>
              <td>
                <a href="villa.php?id=<?= e($r['slug_villa']) ?>" style="color: var(--teak); font-weight: 700; text-decoration: underline;">
                  <?= e($r['nama_villa']) ?>
                </a>
              </td>
              <td>
                <?= format_tanggal_id($r['tanggal_checkin']) ?><br>
                <small style="color: var(--wood);">s.d. <?= format_tanggal_id($r['tanggal_checkout']) ?></small>
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
                <?php if ($r['status'] === 'dikonfirmasi' && !empty($r['nama_pengelola'])): ?>
                  <div style="font-size: 11px; color: var(--wood); margin-top: 2px;">
                    Oleh: <?= e($r['nama_pengelola']) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td style="font-size: 12.5px; color: var(--wood);">
                <?= date('d/m/Y H:i', strtotime($r['dibuat_pada'])) ?>
              </td>
              <td>
                <?php
                  $pesanWa = urlencode("Halo Pengelola Joglo Masayu, saya ingin menanyakan status reservasi #RSV-{$r['id_reservasi']} untuk {$r['nama_villa']}. Terima kasih.");
                  $waUrl = "https://wa.me/6281234567890?text={$pesanWa}";
                ?>
                <a href="<?= $waUrl ?>" target="_blank" rel="noopener" class="btn btn-sm btn-ghost" style="padding: 6px 12px; font-size: 11px;">
                  Tanya WA
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="background: var(--sand); padding: 1rem 1.25rem; border-radius: var(--radius-btn); font-size: 13px; color: var(--wood); line-height: 1.6;">
      <strong>Catatan Penting:</strong>
      Status <em>menunggu</em> berarti permintaan Anda sedang ditinjau ketersediaannya oleh pengelola. Status <em>dikonfirmasi</em> menandakan jadwal menginap Anda telah tercatat secara resmi. Untuk pertanyaan lanjutan, Anda dapat menghubungi pihak pengelola via WhatsApp.
    </div>
  <?php endif; ?>
</main>

<?php require_once __DIR__ . '/includes/layout/foot.php'; ?>

