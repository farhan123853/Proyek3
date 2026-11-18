<?php
/**
 * Riwayat Reservasi Pelanggan — Villa Joglo Masayu
 * Kebutuhan: F-10, T-07, Tahap 8
 * Proteksi IDOR: Hanya menampilkan data reservasi milik pengguna yang sedang login.
 * Menampilkan kartu reservasi dengan alur konfirmasi -> bayar -> selesai,
 * integrasi Midtrans Snap JS (Sandbox), tombol cek status, dan riwayat pembayaran.
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

// Cek apakah ada reservasi yang membutuhkan Snap JS (dikonfirmasi dan belum lunas)
$needSnapJs = false;
$reservasiDetailMap = [];

foreach ($reservasiList as $r) {
    $idRes = (int)$r['id_reservasi'];
    $bayarInfo = status_bayar_efektif($idRes);

    // Ambil riwayat percobaan pembayaran untuk reservasi ini
    $stmtHistory = $pdo->prepare("
        SELECT id_pembayaran, order_id, sumber, jumlah, status, metode_kelompok, metode_detail, snap_token, dibuat_pada, dibayar_pada
        FROM pembayaran
        WHERE id_reservasi = ?
        ORDER BY id_pembayaran DESC
    ");
    $stmtHistory->execute([$idRes]);
    $history = $stmtHistory->fetchAll();

    $reservasiDetailMap[$idRes] = [
        'bayarInfo' => $bayarInfo,
        'history'   => $history
    ];

    if ($r['status'] === 'dikonfirmasi' && $bayarInfo['status'] !== 'lunas') {
        $needSnapJs = true;
    }
}

$pageTitle = t('my_bookings.title') . ' — Villa Joglo Masayu';
$pageDesc  = t('my_bookings.desc');
$bodyClass = 'page-villa';

require_once __DIR__ . '/includes/layout/head.php';
?>

<main class="container mx-auto px-4" style="padding-top: calc(var(--header-h-scrolled) + 2.5rem); padding-bottom: 5rem; max-width: 960px;">
  <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
      <p class="text-xs uppercase tracking-widest font-semibold text-amber-800 mb-1"><?= t('my_bookings.eyebrow') ?></p>
      <h1 class="text-3xl font-serif text-stone-900 mb-1"><?= t('my_bookings.title') ?></h1>
      <p class="text-sm text-stone-600 mb-0"><?= t('my_bookings.desc') ?></p>
    </div>
    <div>
      <a href="reservasi.php" class="btn btn-gold text-sm py-2 px-4 shadow-sm inline-flex items-center gap-1.5">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        <span><?= t('booking.title') ?></span>
      </a>
    </div>
  </div>

  <?php if (empty($reservasiList)): ?>
    <div class="bg-white border border-stone-200 rounded-xl p-10 text-center shadow-sm">
      <div class="w-16 h-16 bg-amber-50 text-amber-700 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      </div>
      <h3 class="font-serif text-xl text-stone-800 mb-2"><?= t('my_bookings.empty') ?></h3>
      <p class="text-sm text-stone-600 max-w-md mx-auto mb-6"><?= t('booking.desc') ?></p>
      <a href="reservasi.php" class="btn btn-gold text-sm py-2 px-6"><?= t('villa.cta_booking') ?></a>
    </div>
  <?php else: ?>

    <div class="space-y-6">
      <?php foreach ($reservasiList as $r): ?>
        <?php
          $idRes = (int)$r['id_reservasi'];
          $detail = $reservasiDetailMap[$idRes];
          $bayarInfo = $detail['bayarInfo'];
          $history = $detail['history'];
          $statusBayar = $bayarInfo['status'];
          $pembayaranAktif = $bayarInfo['pembayaran'];

          // Hitung batas waktu bayar (24 jam dari waktu reservasi dibuat / dikonfirmasi)
          $waktuDibuat = strtotime($r['dibuat_pada']);
          $batasWaktuTs = $waktuDibuat + (24 * 3600);
          $isLewatBatas = (time() > $batasWaktuTs && $statusBayar !== 'lunas');
          $batasWaktuStr = date('d M Y, H:i', $batasWaktuTs) . ' WIB';

          // Status label reservasi
          $resStatusLabel = format_status_reservasi($r['status']);
        ?>

        <div class="bg-white border border-stone-200 rounded-2xl p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200" id="card-rsv-<?= $idRes ?>">
          <!-- Header Kartu -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-stone-100 gap-3">
            <div>
              <div class="flex items-center gap-2 mb-1 flex-wrap">
                <span class="text-xs font-mono font-bold px-2 py-0.5 bg-stone-100 text-stone-800 rounded">#RSV-<?= $idRes ?></span>
                <span class="text-xs text-stone-400">·</span>
                <span class="text-xs text-stone-500"><?= format_tanggal_id($r['dibuat_pada']) ?></span>
              </div>
              <h3 class="text-lg font-serif font-bold text-stone-900">
                <a href="villa.php?slug=<?= e($r['slug_villa']) ?>" class="hover:text-amber-800 transition-colors">
                  <?= e($r['nama_villa']) ?>
                </a>
              </h3>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
              <!-- Badge Status Reservasi -->
              <span class="badge-status status-<?= e($r['status']) ?> text-xs px-2.5 py-1 rounded-full font-semibold">
                <?= e($resStatusLabel) ?>
              </span>
              <!-- Badge Status Pembayaran -->
              <?php if ($r['status'] === 'dikonfirmasi'): ?>
                <?= render_badge_pembayaran($statusBayar, false) ?>
              <?php endif; ?>
            </div>
          </div>

          <!-- Rincian Periode & Tamu -->
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 py-4 text-sm border-b border-stone-100">
            <div>
              <span class="text-xs text-stone-500 block mb-0.5"><?= t('my_bookings.dates') ?></span>
              <span class="font-medium text-stone-800"><?= format_tanggal_id($r['tanggal_checkin']) ?></span>
              <span class="text-xs text-stone-500 block"><?= t('common.to') ?> <?= format_tanggal_id($r['tanggal_checkout']) ?></span>
            </div>
            <div>
              <span class="text-xs text-stone-500 block mb-0.5"><?= t('booking.guests_label') ?></span>
              <span class="font-medium text-stone-800"><?= e($r['jumlah_tamu']) ?> <?= t('common.guests') ?></span>
            </div>
            <div class="col-span-2 sm:col-span-1">
              <span class="text-xs text-stone-500 block mb-0.5"><?= t('booking.total_estimation') ?></span>
              <span class="text-base font-bold text-amber-900"><?= format_rupiah($r['estimasi_total']) ?></span>
            </div>
          </div>

          <!-- Indikator 3 Langkah Alur Reservasi -->
          <div class="py-4 border-b border-stone-100">
            <div class="flex items-center justify-between max-w-md mx-auto text-xs font-semibold text-center">
              <!-- Langkah 1: Konfirmasi -->
              <div class="flex-1 flex flex-col items-center">
                <div class="w-7 h-7 rounded-full flex items-center justify-center mb-1.5 <?= ($r['status'] === 'menunggu') ? 'bg-amber-600 text-white ring-4 ring-amber-100' : 'bg-emerald-600 text-white' ?>">
                  <?php if ($r['status'] === 'menunggu'): ?>
                    <span>1</span>
                  <?php else: ?>
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                  <?php endif; ?>
                </div>
                <span class="<?= ($r['status'] === 'menunggu') ? 'text-amber-800 font-bold' : 'text-stone-700' ?>"><?= t('payment.step_1_confirm') ?></span>
              </div>

              <div class="w-10 sm:w-16 h-0.5 <?= ($r['status'] === 'dikonfirmasi') ? 'bg-emerald-500' : 'bg-stone-200' ?> mb-4"></div>

              <!-- Langkah 2: Bayar -->
              <div class="flex-1 flex flex-col items-center">
                <div class="w-7 h-7 rounded-full flex items-center justify-center mb-1.5 <?= ($r['status'] === 'dikonfirmasi' && $statusBayar !== 'lunas') ? 'bg-amber-600 text-white ring-4 ring-amber-100' : (($statusBayar === 'lunas') ? 'bg-emerald-600 text-white' : 'bg-stone-200 text-stone-500') ?>">
                  <?php if ($statusBayar === 'lunas'): ?>
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                  <?php else: ?>
                    <span>2</span>
                  <?php endif; ?>
                </div>
                <span class="<?= ($r['status'] === 'dikonfirmasi' && $statusBayar !== 'lunas') ? 'text-amber-800 font-bold' : (($statusBayar === 'lunas') ? 'text-stone-700' : 'text-stone-400') ?>"><?= t('payment.step_2_pay') ?></span>
              </div>

              <div class="w-10 sm:w-16 h-0.5 <?= ($statusBayar === 'lunas') ? 'bg-emerald-500' : 'bg-stone-200' ?> mb-4"></div>

              <!-- Langkah 3: Selesai -->
              <div class="flex-1 flex flex-col items-center">
                <div class="w-7 h-7 rounded-full flex items-center justify-center mb-1.5 <?= ($statusBayar === 'lunas') ? 'bg-emerald-600 text-white ring-4 ring-emerald-100' : 'bg-stone-200 text-stone-500' ?>">
                  <span>3</span>
                </div>
                <span class="<?= ($statusBayar === 'lunas') ? 'text-emerald-800 font-bold' : 'text-stone-400' ?>"><?= t('payment.step_3_finish') ?></span>
              </div>
            </div>
          </div>

          <!-- Bagian Aksi Sesuai Kondisi -->
          <div class="pt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <?php if ($r['status'] === 'menunggu'): ?>
              <!-- KONDISI 1: Menunggu Konfirmasi Pengelola -->
              <div class="text-xs text-stone-600 flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Menunggu konfirmasi ketersediaan oleh Pengelola Villa. Anda akan dapat melakukan pembayaran setelah reservasi disetujui.</span>
              </div>
              <div>
                <?php
                  $pesanWa = urlencode("Halo Pengelola Joglo Masayu, saya ingin menanyakan status reservasi #RSV-{$idRes} untuk {$r['nama_villa']}. Terima kasih.");
                ?>
                <a href="https://wa.me/6281234567890?text=<?= $pesanWa ?>" target="_blank" rel="noopener" class="btn btn-sm btn-ghost text-xs inline-flex items-center gap-1.5">
                  <?= t('my_bookings.btn_whatsapp') ?>
                </a>
              </div>

            <?php elseif ($r['status'] === 'dikonfirmasi'): ?>
              <!-- KONDISI 2: Reservasi Terkonfirmasi -->

              <?php if ($statusBayar === 'lunas'): ?>
                <!-- Sub-Kondisi 2A: Sudah LUNAS -->
                <div class="text-xs text-stone-600">
                  <div class="flex items-center gap-1.5 text-emerald-700 font-semibold mb-0.5">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    <span><?= t('payment.badge_lunas') ?> (<?= format_metode_pembayaran($pembayaranAktif['metode_kelompok'] ?? 'tunai') ?>)</span>
                  </div>
                  <span><?= t('payment.contact_wa_change') ?>.</span>
                </div>
                <div>
                  <?php
                    $pesanWaLunas = urlencode("Halo Pengelola Joglo Masayu, reservasi saya #RSV-{$idRes} untuk {$r['nama_villa']} sudah lunas. Saya ingin konfirmasi rincian kedatangan. Terima kasih.");
                  ?>
                  <a href="https://wa.me/6281234567890?text=<?= $pesanWaLunas ?>" target="_blank" rel="noopener" class="btn btn-sm btn-ghost text-xs inline-flex items-center gap-1.5 text-emerald-800 border-emerald-200 hover:bg-emerald-50">
                    <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.007c.106.005.249-.04.39.298.144.347.491 1.2.534 1.288.043.088.072.191.014.306-.058.115-.087.187-.173.289l-.26.302c-.087.087-.179.182-.077.357.101.174.451.744.969 1.205.666.594 1.229.778 1.403.865.174.087.275.072.376-.043.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.043.073.043.419-.101.824z"/></svg>
                    <span><?= t('my_bookings.btn_whatsapp') ?></span>
                  </a>
                </div>

              <?php elseif ($statusBayar === 'menunggu'): ?>
                <!-- Sub-Kondisi 2B: Transaksi Menunggu / Snap Token Aktif -->
                <div class="text-xs text-stone-600">
                  <div class="text-amber-800 font-semibold mb-0.5">
                    <?= t('payment.expiry_notice', ['waktu' => $batasWaktuStr]) ?>
                  </div>
                  <span><?= t('payment.pay_cash_note') ?></span>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                  <?php if (!empty($pembayaranAktif['snap_token'])): ?>
                    <button type="button" class="btn btn-sm btn-gold btn-pay-snap text-xs py-1.5 px-3" data-id="<?= $idRes ?>" data-token="<?= e($pembayaranAktif['snap_token']) ?>">
                      <?= t('payment.btn_continue') ?>
                    </button>
                  <?php else: ?>
                    <button type="button" class="btn btn-sm btn-gold btn-pay-snap text-xs py-1.5 px-3" data-id="<?= $idRes ?>">
                      <?= t('payment.btn_pay_now') ?>
                    </button>
                  <?php endif; ?>

                  <button type="button" class="btn btn-sm btn-ghost btn-check-status text-xs py-1.5 px-3 border border-stone-300" data-order-id="<?= e($pembayaranAktif['order_id'] ?? '') ?>">
                    <?= t('payment.btn_check_status') ?>
                  </button>
                </div>

              <?php elseif ($statusBayar === 'gagal' || $statusBayar === 'kedaluwarsa'): ?>
                <!-- Sub-Kondisi 2C: Gagal atau Kedaluwarsa -->
                <div class="text-xs text-rose-700">
                  <span>Pembayaran sebelumnya <?= $statusBayar === 'gagal' ? 'gagal diproses' : 'telah kedaluwarsa' ?>. Silakan lakukan pembayaran ulang.</span>
                </div>
                <div>
                  <button type="button" class="btn btn-sm btn-gold btn-pay-snap text-xs py-1.5 px-3" data-id="<?= $idRes ?>">
                    <?= t('payment.btn_pay_again') ?>
                  </button>
                </div>

              <?php else: ?>
                <!-- Sub-Kondisi 2D: Belum Dibayar -->
                <div class="text-xs text-stone-600 max-w-sm">
                  <div class="text-stone-800 font-semibold mb-0.5">
                    <?= t('payment.expiry_notice', ['waktu' => $batasWaktuStr]) ?>
                    <?php if ($isLewatBatas): ?>
                      <span class="inline-block ml-1 text-rose-700 bg-rose-50 border border-rose-200 px-1.5 py-0.2 rounded text-[10px] font-bold"><?= t('payment.overdue_badge') ?></span>
                    <?php endif; ?>
                  </div>
                  <span class="text-stone-500"><?= t('payment.pay_cash_note') ?></span>
                </div>
                <div>
                  <button type="button" class="btn btn-sm btn-gold btn-pay-snap text-xs py-2 px-4 shadow font-bold" data-id="<?= $idRes ?>">
                    <?= t('payment.btn_pay_now') ?>
                  </button>
                </div>
              <?php endif; ?>

            <?php else: ?>
              <!-- KONDISI 3: Ditolak atau Dibatalkan -->
              <div class="text-xs text-stone-500">
                <span>Reservasi ini berstatus <?= e($r['status']) ?>. Tidak ada tindakan pembayaran yang diperlukan.</span>
              </div>
            <?php endif; ?>
          </div>

          <!-- Riwayat Percobaan Pembayaran (Collapsible) -->
          <?php if (!empty($history)): ?>
            <div class="mt-4 pt-3 border-t border-stone-100 text-xs">
              <details class="group cursor-pointer">
                <summary class="flex items-center justify-between text-stone-500 hover:text-stone-800 select-none">
                  <span class="font-semibold">Riwayat Percobaan Pembayaran (<?= count($history) ?>)</span>
                  <span class="text-stone-400 group-open:rotate-180 transition-transform duration-200">▾</span>
                </summary>
                <div class="mt-3 overflow-x-auto">
                  <table class="w-full text-left text-xs border border-stone-200 rounded">
                    <thead class="bg-stone-50 text-stone-600 border-b border-stone-200">
                      <tr>
                        <th class="p-2">Waktu</th>
                        <th class="p-2">Order ID</th>
                        <th class="p-2">Metode</th>
                        <th class="p-2">Nominal</th>
                        <th class="p-2">Status</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100 text-stone-700">
                      <?php foreach ($history as $h): ?>
                        <tr>
                          <td class="p-2 whitespace-nowrap"><?= date('d/m/Y H:i', strtotime($h['dibuat_pada'])) ?></td>
                          <td class="p-2 font-mono"><?= e($h['order_id']) ?></td>
                          <td class="p-2"><?= format_metode_pembayaran($h['metode_kelompok']) ?></td>
                          <td class="p-2 font-semibold"><?= format_rupiah($h['jumlah']) ?></td>
                          <td class="p-2"><?= render_badge_pembayaran($h['status'], false) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </details>
            </div>
          <?php endif; ?>

        </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>
</main>

<?php if ($needSnapJs): ?>
  <!-- Script Midtrans Snap Sandbox sesuai dokumentasi resmi -->
  <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="<?= e($config['MIDTRANS_CLIENT_KEY'] ?? '') ?>"></script>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    // 1. Handler Klik Tombol Bayar (Snap)
    document.querySelectorAll('.btn-pay-snap').forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        const idReservasi = btn.getAttribute('data-id');
        const existingToken = btn.getAttribute('data-token');

        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="inline-flex items-center gap-1.5"><svg class="animate-spin w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>Memuat...</span>';

        function launchSnap(token) {
          if (!window.snap) {
            if (typeof showToast === 'function') {
              showToast('Midtrans Snap JS belum siap. Silakan periksa koneksi internet.', 'error');
            }
            btn.disabled = false;
            btn.innerHTML = originalText;
            return;
          }

          window.snap.pay(token, {
            onSuccess: function(result) {
              if (typeof showToast === 'function') {
                showToast(window.MASAYU_LANG === 'en' ? 'Payment successful! Updating status...' : 'Pembayaran berhasil! Memperbarui status...', 'success');
              }
              // Konfirmasi ke server via cek-status-pembayaran
              fetch('api/cek-status-pembayaran.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'order_id=' + encodeURIComponent(result.order_id) + '&csrf_token=' + encodeURIComponent(window.MASAYU_AUTH ? window.MASAYU_AUTH.csrf : '')
              }).finally(function() {
                setTimeout(function() { window.location.reload(); }, 1200);
              });
            },
            onPending: function(result) {
              if (typeof showToast === 'function') {
                showToast(window.MASAYU_LANG === 'en' ? 'Payment pending. Please complete transaction.' : 'Pembayaran menunggu penyelesaian transaksi.', 'info');
              }
              fetch('api/cek-status-pembayaran.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'order_id=' + encodeURIComponent(result.order_id) + '&csrf_token=' + encodeURIComponent(window.MASAYU_AUTH ? window.MASAYU_AUTH.csrf : '')
              }).finally(function() {
                setTimeout(function() { window.location.reload(); }, 1200);
              });
            },
            onError: function(result) {
              if (typeof showToast === 'function') {
                showToast(window.MASAYU_LANG === 'en' ? 'Payment failed.' : 'Pembayaran gagal diproses.', 'error');
              }
              btn.disabled = false;
              btn.innerHTML = originalText;
            },
            onClose: function() {
              btn.disabled = false;
              btn.innerHTML = originalText;
            }
          });
        }

        // Jika token sudah ada di cache klien, pakai ulang
        if (existingToken) {
          launchSnap(existingToken);
          return;
        }

        // Minta snap token dari server
        const csrfToken = window.MASAYU_AUTH ? window.MASAYU_AUTH.csrf : '';
        fetch('api/buat-pembayaran.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'id_reservasi=' + encodeURIComponent(idReservasi) + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
          if (data && data.ok && data.token) {
            btn.setAttribute('data-token', data.token);
            launchSnap(data.token);
          } else {
            if (typeof showToast === 'function') {
              showToast((data && data.pesan) ? data.pesan : 'Gagal menghasilkan token pembayaran.', 'error');
            }
            btn.disabled = false;
            btn.innerHTML = originalText;
          }
        })
        .catch(function(err) {
          if (typeof showToast === 'function') {
            showToast('Terjadi kesalahan jaringan.', 'error');
          }
          btn.disabled = false;
          btn.innerHTML = originalText;
        });
      });
    });

    // 2. Handler Cek Status Pembayaran
    document.querySelectorAll('.btn-check-status').forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        const orderId = btn.getAttribute('data-order-id');
        if (!orderId) return;

        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="inline-flex items-center gap-1"><svg class="animate-spin w-3 h-3" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>...</span>';

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
              showToast((data && data.pesan) ? data.pesan : 'Gagal mengecek status', 'warning');
            }
            btn.disabled = false;
            btn.innerHTML = origHtml;
          }
        })
        .catch(function() {
          if (typeof showToast === 'function') {
            showToast('Kesalahan jaringan saat sinkronisasi status.', 'error');
          }
          btn.disabled = false;
          btn.innerHTML = origHtml;
        });
      });
    });
  });
  </script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/layout/foot.php'; ?>
