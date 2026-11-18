<?php
/**
 * Dashboard Pengelola — Villa Joglo Masayu
 * Kebutuhan: F-11, T-08, Tahap 8
 * - Kelola dan proses permintaan reservasi (Konfirmasi / Tolak)
 * - Transaksi basis data (BEGIN ... SELECT FOR UPDATE) untuk mencegah race condition / double-booking
 * - Kolom & Filter Pembayaran (semua, belum_dibayar, menunggu, lunas, gagal/kedaluwarsa)
 * - Catat Pembayaran Tunai (dialog 2 langkah, nominal otomatis, pengakhiran Midtrans pending)
 * - Penegakan aturan bisnis: Reservasi lunas tidak dapat ditolak/dibatalkan
 * - Tombol Cek status pembayaran dan template WhatsApp cerdas
 * - Tabel responsif yang bertransformasi menjadi kartu bertumpuk di layar mobile (<768px)
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Hak akses: hanya untuk Pengelola atau Admin
require_role(['pengelola', 'admin'], 'login.php');

$pdo = get_db();
$currentUserId = current_user_id();

// 1. Tangani Aksi Konfirmasi / Tolak via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    csrf_verify();

    $idReservasi = (int)($_POST['id_reservasi'] ?? 0);
    $aksi        = trim($_POST['aksi'] ?? '');

    if ($idReservasi && in_array($aksi, ['konfirmasi', 'tolak'], true)) {
        if ($aksi === 'tolak') {
            // PENEGAKAN ATURAN BISNIS DI SERVER:
            // Reservasi dengan pembayaran 'lunas' tidak boleh ditolak atau dibatalkan oleh Pengelola
            $stmtCekLunas = $pdo->prepare("SELECT id_pembayaran FROM pembayaran WHERE id_reservasi = ? AND status = 'lunas' LIMIT 1");
            $stmtCekLunas->execute([$idReservasi]);
            if ($stmtCekLunas->fetch()) {
                flash_set('danger', 'Reservasi ini telah berstatus lunas dan tidak dapat ditolak atau dibatalkan.');
                redirect('dashboard/pengelola.php');
            }

            // Update status menjadi ditolak
            $stmtTolak = $pdo->prepare("UPDATE `reservasi` SET `status` = 'ditolak', `diproses_oleh` = ? WHERE `id_reservasi` = ?");
            $stmtTolak->execute([$currentUserId, $idReservasi]);
            flash_set('warning', "Reservasi #RSV-{$idReservasi} telah ditolak.");
            redirect('dashboard/pengelola.php');
        } elseif ($aksi === 'konfirmasi') {
            // Konfirmasi dalam transaksi dengan kunci baris (FOR UPDATE)
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
                    $pdo->rollBack();
                    flash_set('danger', "Gagal mengonfirmasi #RSV-{$idReservasi}: Seluruh unit ({$jumlahUnit} unit) {$villaInfo['nama']} sudah terisi pada tanggal tersebut.");
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

// 2. Filter Status Reservasi & Pembayaran
$filterStatus = trim($_GET['status'] ?? '');
$filterBayar  = trim($_GET['bayar'] ?? '');

$allowedStatus = ['menunggu', 'dikonfirmasi', 'ditolak'];
$whereClauses = [];
$params = [];

if (in_array($filterStatus, $allowedStatus, true)) {
    $whereClauses[] = "r.status = ?";
    $params[] = $filterStatus;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

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
$rawList = $stmtList->fetchAll();

// Olah status bayar efektif untuk setiap reservasi
$reservasiList = [];
foreach ($rawList as $r) {
    $idRes = (int)$r['id_reservasi'];
    $bayarInfo = status_bayar_efektif($idRes);
    $statusBayar = $bayarInfo['status'];

    // Filter pembayaran client-side bila diisi
    if (!empty($filterBayar)) {
        if ($filterBayar === 'belum_dibayar' && $statusBayar !== 'belum_dibayar') continue;
        if ($filterBayar === 'menunggu' && $statusBayar !== 'menunggu') continue;
        if ($filterBayar === 'lunas' && $statusBayar !== 'lunas') continue;
        if ($filterBayar === 'gagal_kedaluwarsa' && !in_array($statusBayar, ['gagal', 'kedaluwarsa'], true)) continue;
    }

    // Ambil riwayat pembayaran untuk modal rincian
    $stmtH = $pdo->prepare("SELECT * FROM pembayaran WHERE id_reservasi = ? ORDER BY id_pembayaran DESC");
    $stmtH->execute([$idRes]);
    $history = $stmtH->fetchAll();

    $r['bayar_status'] = $statusBayar;
    $r['bayar_pembayaran'] = $bayarInfo['pembayaran'];
    $r['bayar_history'] = $history;

    // Evaluasi penanda lewat batas bayar (24 jam)
    $tsDibuat = strtotime($r['dibuat_pada']);
    $tsBatas = $tsDibuat + (24 * 3600);
    $r['is_lewat_batas'] = ($r['status'] === 'dikonfirmasi' && $statusBayar !== 'lunas' && time() > $tsBatas);
    $r['batas_waktu_str'] = date('d/m/Y H:i', $tsBatas) . ' WIB';

    $reservasiList[] = $r;
}

// Statistik ringkas status
$statMenunggu = (int)$pdo->query("SELECT COUNT(*) FROM `reservasi` WHERE `status` = 'menunggu'")->fetchColumn();
$statKonfirmasi = (int)$pdo->query("SELECT COUNT(*) FROM `reservasi` WHERE `status` = 'dikonfirmasi'")->fetchColumn();
$statDitolak = (int)$pdo->query("SELECT COUNT(*) FROM `reservasi` WHERE `status` = 'ditolak'")->fetchColumn();

// Statistik pembayaran (pada reservasi dikonfirmasi)
$statLunas = (int)$pdo->query("SELECT COUNT(DISTINCT id_reservasi) FROM `pembayaran` WHERE `status` = 'lunas'")->fetchColumn();

$pageTitle = t('dashboard.pengelola_title') . ' — Villa Joglo Masayu';
$pageDesc  = 'Kelola permohonan reservasi, verifikasi pembayaran, dan pantau ketersediaan kamar.';
$bodyClass = 'page-villa';

require_once __DIR__ . '/../includes/layout/head.php';
?>

<main class="container mx-auto px-4" style="padding-top: calc(var(--header-h-scrolled) + 2rem); padding-bottom: 5rem;">
  <!-- Header Dashboard -->
  <div class="flex flex-col md:flex-row md:items-end justify-between mb-6 gap-3">
    <div>
      <p class="text-xs uppercase tracking-widest font-semibold text-amber-800 mb-1"><?= t('dashboard.pengelola_title') ?></p>
      <h1 class="text-2xl md:text-3xl font-serif text-stone-900 mb-1"><?= t('dashboard.pengelola_title') ?></h1>
      <p class="text-sm text-stone-600 mb-0">Kelola reservasi tamu, verifikasi pembayaran, dan pencatatan kas masuk.</p>
    </div>
  </div>

  <!-- Kartu Statistik Cepat -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white border <?= $statMenunggu > 0 ? 'border-amber-300 ring-2 ring-amber-100' : 'border-stone-200' ?> rounded-xl p-4 shadow-sm">
      <span class="text-xs text-stone-500 font-semibold block uppercase tracking-wider mb-1"><?= t('dashboard.menunggu_konfirmasi') ?></span>
      <div class="text-2xl font-bold text-amber-800"><?= $statMenunggu ?></div>
      <div class="text-[11px] text-amber-700 font-medium mt-1">Perlu diverifikasi</div>
    </div>
    <div class="bg-white border border-stone-200 rounded-xl p-4 shadow-sm">
      <span class="text-xs text-stone-500 font-semibold block uppercase tracking-wider mb-1"><?= t('dashboard.terkonfirmasi') ?></span>
      <div class="text-2xl font-bold text-stone-800"><?= $statKonfirmasi ?></div>
      <div class="text-[11px] text-stone-500 mt-1">Jadwal tamu disetujui</div>
    </div>
    <div class="bg-white border border-stone-200 rounded-xl p-4 shadow-sm">
      <span class="text-xs text-stone-500 font-semibold block uppercase tracking-wider mb-1">Pembayaran Lunas</span>
      <div class="text-2xl font-bold text-emerald-700"><?= $statLunas ?></div>
      <div class="text-[11px] text-emerald-600 mt-1">Online & Tunai</div>
    </div>
    <div class="bg-white border border-stone-200 rounded-xl p-4 shadow-sm">
      <span class="text-xs text-stone-500 font-semibold block uppercase tracking-wider mb-1">Permintaan Ditolak</span>
      <div class="text-2xl font-bold text-rose-700"><?= $statDitolak ?></div>
      <div class="text-[11px] text-rose-500 mt-1">Kapasitas/jadwal bentrok</div>
    </div>
  </div>

  <!-- Bar Penyaring Ganda: Status Reservasi & Status Pembayaran -->
  <div class="bg-white border border-stone-200 rounded-xl p-4 mb-6 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
    <form method="GET" action="dashboard/pengelola.php" class="flex items-center gap-3 flex-wrap text-sm">
      <span class="font-semibold text-stone-700 shrink-0"><?= t('common.filter') ?>:</span>
      
      <!-- Filter Reservasi -->
      <select name="status" class="form-control text-xs py-1.5 px-3 rounded-lg border-stone-300 w-auto" onchange="this.form.submit()">
        <option value=""><?= t('common.all') ?> Reservasi</option>
        <option value="menunggu" <?= $filterStatus === 'menunggu' ? 'selected' : '' ?>><?= t('my_bookings.status_menunggu') ?> (<?= $statMenunggu ?>)</option>
        <option value="dikonfirmasi" <?= $filterStatus === 'dikonfirmasi' ? 'selected' : '' ?>><?= t('my_bookings.status_dikonfirmasi') ?> (<?= $statKonfirmasi ?>)</option>
        <option value="ditolak" <?= $filterStatus === 'ditolak' ? 'selected' : '' ?>><?= t('common.cancel') ?> (<?= $statDitolak ?>)</option>
      </select>

      <!-- Filter Pembayaran -->
      <select name="bayar" class="form-control text-xs py-1.5 px-3 rounded-lg border-stone-300 w-auto" onchange="this.form.submit()">
        <option value="">Semua Pembayaran</option>
        <option value="belum_dibayar" <?= $filterBayar === 'belum_dibayar' ? 'selected' : '' ?>><?= t('payment.badge_belum_dibayar') ?></option>
        <option value="menunggu" <?= $filterBayar === 'menunggu' ? 'selected' : '' ?>><?= t('payment.badge_menunggu') ?></option>
        <option value="lunas" <?= $filterBayar === 'lunas' ? 'selected' : '' ?>><?= t('payment.badge_lunas') ?></option>
        <option value="gagal_kedaluwarsa" <?= $filterBayar === 'gagal_kedaluwarsa' ? 'selected' : '' ?>>Gagal / Kedaluwarsa</option>
      </select>

      <?php if ($filterStatus || $filterBayar): ?>
        <a href="dashboard/pengelola.php" class="text-xs text-amber-800 hover:underline font-semibold ml-1">Reset Filter</a>
      <?php endif; ?>
    </form>

    <div class="text-xs text-stone-500">
      Menampilkan <strong><?= count($reservasiList) ?></strong> permintaan
    </div>
  </div>

  <!-- Kontainer Reservasi: Tabel di Desktop, Kartu Bertumpuk di Mobile -->
  <?php if (empty($reservasiList)): ?>
    <div class="bg-white border border-stone-200 rounded-xl p-10 text-center shadow-sm">
      <p class="text-sm text-stone-500 mb-0">Tidak ada data reservasi dengan penyaring yang dipilih.</p>
    </div>
  <?php else: ?>

    <!-- 1. Tampilan Tabel Desktop (>= 768px) -->
    <div class="hidden md:block bg-white border border-stone-200 rounded-xl shadow-sm overflow-hidden mb-6">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm divide-y divide-stone-200">
          <thead class="bg-stone-50 text-stone-700 font-semibold text-xs tracking-wider uppercase">
            <tr>
              <th class="p-3.5">Kode & Tamu</th>
              <th class="p-3.5">Tipe Villa</th>
              <th class="p-3.5">Jadwal & Tamu</th>
              <th class="p-3.5">Estimasi Tagihan</th>
              <th class="p-3.5">Status Reservasi</th>
              <th class="p-3.5">Pembayaran</th>
              <th class="p-3.5 text-right">Tindakan</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100 text-stone-800">
            <?php foreach ($reservasiList as $r): ?>
              <?php
                $idRes = (int)$r['id_reservasi'];
                $isLunas = ($r['bayar_status'] === 'lunas');
                $orderIdAktif = $r['bayar_pembayaran']['order_id'] ?? '';
              ?>
              <tr class="hover:bg-stone-50/70 transition-colors">
                <td class="p-3.5">
                  <div class="font-mono font-bold text-amber-900 text-xs">#RSV-<?= $idRes ?></div>
                  <div class="font-semibold text-stone-900 text-sm mt-0.5"><?= e($r['nama_pelanggan']) ?></div>
                  <div class="text-xs text-stone-500"><?= e($r['kontak_pelanggan']) ?></div>
                </td>
                <td class="p-3.5 font-medium text-stone-800 text-xs">
                  <?= e($r['nama_villa']) ?>
                </td>
                <td class="p-3.5 text-xs">
                  <div class="font-semibold text-stone-900"><?= date('d/m/Y', strtotime($r['tanggal_checkin'])) ?></div>
                  <div class="text-stone-500">s.d. <?= date('d/m/Y', strtotime($r['tanggal_checkout'])) ?></div>
                  <div class="text-[11px] text-stone-400 mt-0.5"><?= e($r['jumlah_tamu']) ?> orang</div>
                </td>
                <td class="p-3.5 text-xs">
                  <div class="font-bold text-stone-900"><?= format_rupiah($r['estimasi_total']) ?></div>
                  <?php if ($r['is_lewat_batas']): ?>
                    <span class="inline-block mt-1 text-[10px] font-bold text-rose-700 bg-rose-50 border border-rose-200 px-1.5 py-0.5 rounded">
                      <?= t('payment.overdue_badge') ?>
                    </span>
                  <?php endif; ?>
                </td>
                <td class="p-3.5 text-xs">
                  <span class="badge-status status-<?= e($r['status']) ?> text-xs px-2.5 py-0.5 rounded-full font-semibold">
                    <?= e(format_status_reservasi($r['status'])) ?>
                  </span>
                  <?php if (!empty($r['nama_staf'])): ?>
                    <div class="text-[10px] text-stone-400 mt-1">Oleh: <?= e($r['nama_staf']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="p-3.5 text-xs">
                  <?php if ($r['status'] === 'dikonfirmasi'): ?>
                    <div><?= render_badge_pembayaran($r['bayar_status'], true) ?></div>
                    <?php if (!empty($r['bayar_pembayaran']['metode_kelompok'])): ?>
                      <div class="text-[11px] text-stone-500 mt-1">
                        <?= format_metode_pembayaran($r['bayar_pembayaran']['metode_kelompok']) ?>
                      </div>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-stone-400 text-xs">-</span>
                  <?php endif; ?>
                </td>
                <td class="p-3.5 text-right text-xs">
                  <div class="flex items-center justify-end gap-1.5 flex-wrap">
                    <?php if ($r['status'] === 'menunggu'): ?>
                      <!-- Konfirmasi -->
                      <form method="POST" action="dashboard/pengelola.php" onsubmit="return confirm('Konfirmasi reservasi #RSV-<?= $idRes ?>?');" class="inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id_reservasi" value="<?= $idRes ?>" />
                        <input type="hidden" name="aksi" value="konfirmasi" />
                        <button type="submit" class="btn btn-sm btn-success text-xs py-1 px-2.5">Setujui</button>
                      </form>
                      <!-- Tolak -->
                      <form method="POST" action="dashboard/pengelola.php" onsubmit="return confirm('Tolak reservasi #RSV-<?= $idRes ?>?');" class="inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id_reservasi" value="<?= $idRes ?>" />
                        <input type="hidden" name="aksi" value="tolak" />
                        <button type="submit" class="btn btn-sm btn-danger text-xs py-1 px-2.5">Tolak</button>
                      </form>

                    <?php elseif ($r['status'] === 'dikonfirmasi'): ?>
                      <!-- Tombol Catat Pembayaran Tunai jika belum lunas -->
                      <?php if (!$isLunas): ?>
                        <button type="button" class="btn btn-sm btn-gold btn-open-cash-modal text-xs py-1 px-2" data-id="<?= $idRes ?>" data-nama="<?= e($r['nama_pelanggan']) ?>" data-nominal="<?= (int)$r['estimasi_total'] ?>" data-nominal-fmt="<?= format_rupiah($r['estimasi_total']) ?>">
                          <?= t('payment.btn_record_cash') ?>
                        </button>
                      <?php endif; ?>

                      <!-- Tombol Cek Status jika ada order_id aktif -->
                      <?php if (!empty($orderIdAktif) && !$isLunas): ?>
                        <button type="button" class="btn btn-sm btn-ghost btn-check-status-admin text-xs py-1 px-2 border border-stone-300" data-order-id="<?= e($orderIdAktif) ?>" title="Cek Status Online">
                          <svg class="w-3.5 h-3.5 text-stone-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        </button>
                      <?php endif; ?>

                      <!-- Tombol Kabari via WhatsApp -->
                      <?php
                        $nomorHp = preg_replace('/^0/', '62', preg_replace('/[^\d]/', '', $r['kontak_pelanggan']));
                        if ($isLunas) {
                            $waText = urlencode("Halo Bapak/Ibu {$r['nama_pelanggan']}, kami konfirmasi bahwa pembayaran untuk reservasi #RSV-{$idRes} ({$r['nama_villa']}) sebesar " . format_rupiah($r['estimasi_total']) . " telah LUNAS. Kami menantikan kedatangan Anda pada " . date('d/m/Y', strtotime($r['tanggal_checkin'])) . ". Terima kasih. - Villa Joglo Masayu");
                        } else {
                            $waText = urlencode("Halo Bapak/Ibu {$r['nama_pelanggan']}, reservasi Anda #RSV-{$idRes} ({$r['nama_villa']}) telah DIKONFIRMASI. Silakan selesaikan pembayaran sebesar " . format_rupiah($r['estimasi_total']) . " sebelum {$r['batas_waktu_str']} via online (VA/E-wallet) di halaman Reservasi Saya atau tunai saat tiba di villa. Terima kasih. - Villa Joglo Masayu");
                        }
                      ?>
                      <a href="https://wa.me/<?= $nomorHp ?>?text=<?= $waText ?>" target="_blank" rel="noopener" class="btn btn-sm btn-ghost text-xs py-1 px-2 border border-stone-300" title="Hubungi via WhatsApp">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.007c.106.005.249-.04.39.298.144.347.491 1.2.534 1.288.043.088.072.191.014.306-.058.115-.087.187-.173.289l-.26.302c-.087.087-.179.182-.077.357.101.174.451.744.969 1.205.666.594 1.229.778 1.403.865.174.087.275.072.376-.043.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.043.073.043.419-.101.824z"/></svg>
                      </a>

                      <!-- Tombol Tolak Disabled jika sudah Lunas -->
                      <?php if ($isLunas): ?>
                        <button type="button" class="btn btn-sm btn-ghost text-xs py-1 px-2 opacity-50 cursor-not-allowed" disabled title="Reservasi sudah lunas (tidak dapat ditolak/dibatalkan)">
                          Tolak
                        </button>
                      <?php endif; ?>

                    <?php else: ?>
                      <span class="text-stone-400 text-xs">Selesai</span>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 2. Tampilan Kartu Bertumpuk Mobile (< 768px) -->
    <div class="block md:hidden space-y-4 mb-6">
      <?php foreach ($reservasiList as $r): ?>
        <?php
          $idRes = (int)$r['id_reservasi'];
          $isLunas = ($r['bayar_status'] === 'lunas');
          $orderIdAktif = $r['bayar_pembayaran']['order_id'] ?? '';
        ?>
        <div class="bg-white border border-stone-200 rounded-xl p-4 shadow-sm">
          <div class="flex items-center justify-between pb-3 border-b border-stone-100 gap-2">
            <div>
              <span class="font-mono font-bold text-amber-900 text-xs">#RSV-<?= $idRes ?></span>
              <h4 class="font-serif font-bold text-stone-900 text-base leading-tight mt-0.5"><?= e($r['nama_villa']) ?></h4>
            </div>
            <div class="flex flex-col items-end gap-1">
              <span class="badge-status status-<?= e($r['status']) ?> text-[10px] px-2 py-0.5 rounded-full font-semibold">
                <?= e(format_status_reservasi($r['status'])) ?>
              </span>
              <?php if ($r['status'] === 'dikonfirmasi'): ?>
                <?= render_badge_pembayaran($r['bayar_status'], true) ?>
              <?php endif; ?>
            </div>
          </div>

          <div class="py-3 text-xs space-y-1.5 border-b border-stone-100 text-stone-600">
            <div><strong>Tamu:</strong> <?= e($r['nama_pelanggan']) ?> (<?= e($r['kontak_pelanggan']) ?>)</div>
            <div><strong>Periode:</strong> <?= date('d/m/Y', strtotime($r['tanggal_checkin'])) ?> s.d. <?= date('d/m/Y', strtotime($r['tanggal_checkout'])) ?> (<?= e($r['jumlah_tamu']) ?> orang)</div>
            <div>
              <strong>Estimasi Tagihan:</strong> <span class="font-bold text-amber-900"><?= format_rupiah($r['estimasi_total']) ?></span>
              <?php if ($r['is_lewat_batas']): ?>
                <span class="text-rose-700 bg-rose-50 border border-rose-200 px-1 py-0.2 rounded font-bold ml-1"><?= t('payment.overdue_badge') ?></span>
              <?php endif; ?>
            </div>
          </div>

          <div class="pt-3 flex items-center justify-end gap-2 flex-wrap text-xs">
            <?php if ($r['status'] === 'menunggu'): ?>
              <form method="POST" action="dashboard/pengelola.php" onsubmit="return confirm('Setujui reservasi #RSV-<?= $idRes ?>?');" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id_reservasi" value="<?= $idRes ?>" />
                <input type="hidden" name="aksi" value="konfirmasi" />
                <button type="submit" class="btn btn-sm btn-success text-xs py-1.5 px-3">Setujui</button>
              </form>
              <form method="POST" action="dashboard/pengelola.php" onsubmit="return confirm('Tolak reservasi #RSV-<?= $idRes ?>?');" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id_reservasi" value="<?= $idRes ?>" />
                <input type="hidden" name="aksi" value="tolak" />
                <button type="submit" class="btn btn-sm btn-danger text-xs py-1.5 px-3">Tolak</button>
              </form>
            <?php elseif ($r['status'] === 'dikonfirmasi'): ?>
              <?php if (!$isLunas): ?>
                <button type="button" class="btn btn-sm btn-gold btn-open-cash-modal text-xs py-1.5 px-3" data-id="<?= $idRes ?>" data-nama="<?= e($r['nama_pelanggan']) ?>" data-nominal="<?= (int)$r['estimasi_total'] ?>" data-nominal-fmt="<?= format_rupiah($r['estimasi_total']) ?>">
                  <?= t('payment.btn_record_cash') ?>
                </button>
              <?php endif; ?>
              <a href="https://wa.me/<?= preg_replace('/^0/', '62', preg_replace('/[^\d]/', '', $r['kontak_pelanggan'])) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-ghost text-xs py-1.5 px-2.5 border border-stone-300">
                WhatsApp
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>
</main>

<!-- Modal Pencatatan Pembayaran Tunai (Konfirmasi 2 Langkah) -->
<div id="modal-catat-tunai" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm hidden" role="dialog" aria-modal="true" aria-labelledby="modal-title-tunai">
  <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-stone-200 transform transition-all duration-200">
    <div class="flex items-center justify-between pb-3 border-b border-stone-100 mb-4">
      <h3 class="font-serif text-lg font-bold text-stone-900" id="modal-title-tunai"><?= t('payment.cash_confirm_dialog') ?></h3>
      <button type="button" class="text-stone-400 hover:text-stone-700 text-xl font-bold p-1 close-modal-tunai" aria-label="Tutup">&times;</button>
    </div>

    <form id="form-catat-tunai" novalidate>
      <input type="hidden" id="modal-tunai-id" name="id_reservasi" value="" />
      
      <!-- Rincian Tamu & Tagihan -->
      <div class="bg-amber-50/60 border border-amber-200/80 rounded-xl p-3 mb-4 text-xs space-y-1">
        <div><strong>Pemesan:</strong> <span id="modal-tunai-nama">-</span></div>
        <div><strong>Total Tagihan:</strong> <span id="modal-tunai-nominal-fmt" class="font-bold text-amber-900 text-sm">-</span></div>
        <div class="text-stone-500 italic">* Pembayaran harus diterima secara penuh (tanpa DP).</div>
      </div>

      <!-- Tanggal Bayar -->
      <div class="mb-4">
        <label for="modal-tunai-tanggal" class="block text-xs font-semibold text-stone-700 mb-1">Tanggal & Waktu Diterima (WIB)</label>
        <input type="datetime-local" id="modal-tunai-tanggal" name="tanggal_bayar" class="form-control text-xs w-full rounded-lg border-stone-300" required />
      </div>

      <!-- Catatan Opsional -->
      <div class="mb-5">
        <label for="modal-tunai-catatan" class="block text-xs font-semibold text-stone-700 mb-1">Catatan Staf (Maks 200 Karakter)</label>
        <textarea id="modal-tunai-catatan" name="catatan" rows="2" maxlength="200" placeholder="Contoh: Diterima tunai di resepsionis villa..." class="form-control text-xs w-full rounded-lg border-stone-300"></textarea>
      </div>

      <!-- Konfirmasi 2 Langkah: Peringatan Fisik Uang -->
      <div class="bg-stone-50 border border-stone-200 rounded-lg p-3 mb-5 text-xs text-stone-700 flex items-start gap-2">
        <input type="checkbox" id="modal-tunai-check" class="mt-0.5 rounded border-stone-300 text-amber-800 focus:ring-amber-800" required />
        <label for="modal-tunai-check" class="cursor-pointer">
          Saya mengonfirmasi bahwa uang fisik sejumlah tersebut telah diterima lengkap di kasir villa.
        </label>
      </div>

      <div class="flex items-center justify-end gap-2">
        <button type="button" class="btn btn-sm btn-ghost text-xs py-2 px-4 close-modal-tunai">Batal</button>
        <button type="submit" id="btn-submit-tunai" class="btn btn-sm btn-gold text-xs py-2 px-5 font-bold shadow" disabled>
          Simpan Lunas
        </button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const modalTunai = document.getElementById('modal-catat-tunai');
  const formTunai = document.getElementById('form-catat-tunai');
  const checkKonfirmasi = document.getElementById('modal-tunai-check');
  const btnSubmitTunai = document.getElementById('btn-submit-tunai');

  // Format tanggal saat ini ke format input datetime-local lokal
  function getCurrentLocalDateTime() {
    const now = new Date();
    const offset = now.getTimezoneOffset() * 60000;
    const localISOTime = (new Date(now.getTime() - offset)).toISOString().slice(0, 16);
    return localISOTime;
  }

  // Buka Modal Tunai
  document.querySelectorAll('.btn-open-cash-modal').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const id = btn.getAttribute('data-id');
      const nama = btn.getAttribute('data-nama');
      const nominalFmt = btn.getAttribute('data-nominal-fmt');

      document.getElementById('modal-tunai-id').value = id;
      document.getElementById('modal-tunai-nama').textContent = nama;
      document.getElementById('modal-tunai-nominal-fmt').textContent = nominalFmt;
      document.getElementById('modal-tunai-tanggal').value = getCurrentLocalDateTime();
      document.getElementById('modal-tunai-catatan').value = '';
      checkKonfirmasi.checked = false;
      btnSubmitTunai.disabled = true;

      modalTunai.classList.remove('hidden');
    });
  });

  // Checkbox Konfirmasi 2 Langkah
  checkKonfirmasi.addEventListener('change', function() {
    btnSubmitTunai.disabled = !this.checked;
  });

  // Tutup Modal
  document.querySelectorAll('.close-modal-tunai').forEach(function(btn) {
    btn.addEventListener('click', function() {
      modalTunai.classList.add('hidden');
    });
  });

  // Submit Catat Tunai via AJAX
  formTunai.addEventListener('submit', function(e) {
    e.preventDefault();
    if (!checkKonfirmasi.checked) return;

    btnSubmitTunai.disabled = true;
    const origText = btnSubmitTunai.innerHTML;
    btnSubmitTunai.innerHTML = 'Menyimpan...';

    const idRes = document.getElementById('modal-tunai-id').value;
    const tgl = document.getElementById('modal-tunai-tanggal').value;
    const catatan = document.getElementById('modal-tunai-catatan').value;
    const csrfToken = window.MASAYU_AUTH ? window.MASAYU_AUTH.csrf : '';

    fetch('api/catat-tunai.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'id_reservasi=' + encodeURIComponent(idRes) + 
            '&tanggal_bayar=' + encodeURIComponent(tgl) + 
            '&catatan=' + encodeURIComponent(catatan) + 
            '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
      if (data && data.ok) {
        if (typeof showToast === 'function') {
          showToast(data.pesan || 'Pembayaran tunai berhasil dicatat.', 'success');
        }
        modalTunai.classList.add('hidden');
        setTimeout(function() { window.location.reload(); }, 1000);
      } else {
        if (typeof showToast === 'function') {
          showToast((data && data.pesan) ? data.pesan : 'Gagal mencatat pembayaran tunai', 'error');
        }
        btnSubmitTunai.disabled = false;
        btnSubmitTunai.innerHTML = origText;
      }
    })
    .catch(function(err) {
      if (typeof showToast === 'function') {
        showToast('Kesalahan jaringan saat menyimpan.', 'error');
      }
      btnSubmitTunai.disabled = false;
      btnSubmitTunai.innerHTML = origText;
    });
  });

  // Cek Status Pembayaran Admin
  document.querySelectorAll('.btn-check-status-admin').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const orderId = btn.getAttribute('data-order-id');
      if (!orderId) return;

      btn.disabled = true;
      const csrfToken = window.MASAYU_AUTH ? window.MASAYU_AUTH.csrf : '';

      fetch('api/cek-status-pembayaran.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'order_id=' + encodeURIComponent(orderId) + '&csrf_token=' + encodeURIComponent(csrfToken)
      })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data && data.ok) {
          if (typeof showToast === 'function') {
            showToast(data.pesan || 'Status: ' + data.status, 'success');
          }
          setTimeout(function() { window.location.reload(); }, 1000);
        } else {
          if (typeof showToast === 'function') {
            showToast((data && data.pesan) ? data.pesan : 'Gagal sinkron status', 'warning');
          }
          btn.disabled = false;
        }
      })
      .catch(function() {
        if (typeof showToast === 'function') {
          showToast('Kesalahan jaringan.', 'error');
        }
        btn.disabled = false;
      });
    });
  });
});
</script>

<?php require_once __DIR__ . '/../includes/layout/foot.php'; ?>
