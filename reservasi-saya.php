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

// Pemicu oportunistik pemeriksaan kedaluwarsa reservasi
pemicu_oportunistik_kedaluwarsa($pdo);

// Tangani Aksi POST Pelanggan: Pilih Bayar Tunai & Kembali Bayar Online
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    csrf_verify();
    $aksi = trim($_POST['aksi'] ?? '');
    $idRes = (int)($_POST['id_reservasi'] ?? 0);

    if ($aksi === 'pilih_bayar_tunai') {
        $res = pelanggan_pilih_bayar_tunai($idRes, $userId);
        flash_set($res['ok'] ? 'success' : 'danger', $res['pesan']);
        redirect('reservasi-saya.php#card-rsv-' . $idRes);
    } elseif ($aksi === 'kembali_bayar_online') {
        $res = pelanggan_kembali_bayar_online($idRes, $userId);
        flash_set($res['ok'] ? 'success' : 'danger', $res['pesan']);
        redirect('reservasi-saya.php#card-rsv-' . $idRes);
    }
}

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
        SELECT id_pembayaran, order_id, sumber, jumlah, status, metode_kelompok, metode_detail, snap_token, bank_atau_toko, kode_pembayaran, kedaluwarsa_pada, dibuat_pada, dibayar_pada
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

          // Hitung batas waktu bayar dari kolom batas_bayar_pada di database
          if (!empty($r['batas_bayar_pada'])) {
              $batasWaktuTs = strtotime($r['batas_bayar_pada']);
          } else {
              // Fallback untuk reservasi lama yang belum punya batas_bayar_pada
              $waktuDibuat = strtotime($r['dibuat_pada']);
              $batasWaktuTs = $waktuDibuat + (PEMBAYARAN_BATAS_JAM * 3600);
          }
          $isLewatBatas = (time() > $batasWaktuTs && $statusBayar !== 'lunas');
          $batasWaktuStr = date('d M Y, H:i', $batasWaktuTs) . ' WIB';
          $sisaDetikBayar = max(0, $batasWaktuTs - time());
          $rencanaBayar = $r['rencana_bayar'] ?? 'online';

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
                  <?php if (!empty($pembayaranAktif['kode_pembayaran'])): ?>
                    <div class="text-[11px] font-mono text-stone-500 mb-0.5">
                      <?= e(format_bank_atau_toko($pembayaranAktif['bank_atau_toko'] ?? null)) ?>: <?= e(samarkan_kode_pembayaran($pembayaranAktif['kode_pembayaran'])) ?>
                    </div>
                  <?php endif; ?>
                  <span><?= t('payment.contact_wa_change') ?>.</span>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                  <a href="struk-pembayaran.php?id=<?= $idRes ?>" target="_blank" rel="noopener" class="btn btn-sm btn-ghost border border-stone-300 text-xs inline-flex items-center gap-1.5 py-1.5 px-3 min-h-[38px]">
                    <svg class="w-4 h-4 text-stone-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span><?= t('payment.btn_pay_proof') ?></span>
                  </a>
                  <?php
                    $pesanWaLunas = urlencode("Halo Pengelola Joglo Masayu, reservasi saya #RSV-{$idRes} untuk {$r['nama_villa']} sudah lunas. Saya ingin konfirmasi rincian kedatangan. Terima kasih.");
                  ?>
                  <a href="https://wa.me/6281234567890?text=<?= $pesanWaLunas ?>" target="_blank" rel="noopener" class="btn btn-sm btn-ghost text-xs inline-flex items-center gap-1.5 text-emerald-800 border-emerald-200 hover:bg-emerald-50 min-h-[38px]">
                    <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.007c.106.005.249-.04.39.298.144.347.491 1.2.534 1.288.043.088.072.191.014.306-.058.115-.087.187-.173.289l-.26.302c-.087.087-.179.182-.077.357.101.174.451.744.969 1.205.666.594 1.229.778 1.403.865.174.087.275.072.376-.043.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.043.073.043.419-.101.824z"/></svg>
                    <span><?= t('my_bookings.btn_whatsapp') ?></span>
                  </a>
                </div>

              <?php elseif ($statusBayar === 'menunggu'): ?>
                <!-- Sub-Kondisi 2B: Transaksi Menunggu -->
                <?php if (!empty($pembayaranAktif['kode_pembayaran'])): ?>
                  <!-- KARTU KODE PEMBAYARAN VIRTUAL ACCOUNT -->
                  <div class="w-full bg-gradient-to-br from-amber-50/90 via-stone-50 to-amber-100/30 border-2 border-amber-300 rounded-xl p-4 my-2 text-stone-900 shadow-sm">
                    <div class="flex items-center justify-between pb-2.5 border-b border-amber-200/70 mb-3 flex-wrap gap-2">
                      <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-600 animate-pulse"></span>
                        <span class="text-xs font-bold text-amber-950 uppercase tracking-wider"><?= t('payment.va_card_title') ?></span>
                        <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-200/80 text-amber-950">
                          <?= e(format_bank_atau_toko($pembayaranAktif['bank_atau_toko'] ?? null)) ?>
                        </span>
                      </div>
                      <?php if (!empty($pembayaranAktif['kedaluwarsa_pada'])): ?>
                        <div class="text-[11px] text-amber-900 font-medium flex items-center gap-1.5">
                          <svg class="w-3.5 h-3.5 text-amber-700" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                          <span><?= t('payment.countdown_label') ?>:</span>
                          <span class="va-countdown font-mono font-bold text-rose-700" data-expiry="<?= e($pembayaranAktif['kedaluwarsa_pada']) ?>" data-expired-text="<?= t('payment.countdown_expired') ?>">--:--:--</span>
                        </div>
                      <?php endif; ?>
                    </div>

                    <div class="mb-3">
                      <div class="text-[11px] text-stone-600 mb-1 font-medium"><?= t('payment.va_code_label') ?> / Nomor Pembayaran:</div>
                      <div class="flex items-center gap-2 flex-wrap">
                        <div class="font-mono text-xl md:text-2xl font-bold tracking-wider text-stone-950 bg-white px-3.5 py-1.5 rounded-lg border border-amber-200 shadow-inner select-all">
                          <?= e(format_nomor_va($pembayaranAktif['kode_pembayaran'], $pembayaranAktif['bank_atau_toko'])) ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-ghost border border-amber-300 bg-white hover:bg-amber-100/60 text-xs py-1.5 px-3 inline-flex items-center gap-1.5 btn-copy-clipboard" data-copy="<?= e(str_replace(' ', '', $pembayaranAktif['kode_pembayaran'])) ?>" data-toast="<?= t('payment.va_copied_toast') ?>">
                          <svg class="w-3.5 h-3.5 text-amber-800" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                          <span><?= t('payment.btn_copy_va') ?></span>
                        </button>
                      </div>
                    </div>

                    <div class="flex items-center justify-between text-xs py-2 px-3 bg-white/80 rounded-lg border border-amber-100 mb-3 flex-wrap gap-2">
                      <div>
                        <span class="text-stone-500"><?= t('payment.total_amount') ?>:</span>
                        <span class="font-bold text-stone-900 ml-1 font-mono text-sm"><?= format_rupiah($pembayaranAktif['jumlah']) ?></span>
                        <span class="text-[11px] text-stone-400 ml-2">No. Resi: <span class="font-mono"><?= e($pembayaranAktif['order_id']) ?></span></span>
                      </div>
                      <button type="button" class="text-xs text-amber-900 hover:text-amber-700 font-semibold underline inline-flex items-center gap-1 btn-copy-clipboard" data-copy="<?= (int)$pembayaranAktif['jumlah'] ?>" data-toast="<?= t('payment.amount_copied_toast') ?>">
                        <?= t('payment.btn_copy_amount') ?>
                      </button>
                    </div>

                    <!-- Panduan 3 Langkah Transfer -->
                    <?php
                      $steps = ambil_petunjuk_transfer($pembayaranAktif['bank_atau_toko'] ?? '', get_active_lang());
                    ?>
                    <div class="mt-3 pt-2.5 border-t border-amber-200/60">
                      <div class="text-[11px] font-bold uppercase tracking-wider text-stone-700 mb-1.5"><?= t('payment.how_to_pay') ?>:</div>
                      <ol class="space-y-1.5 text-xs text-stone-700">
                        <?php foreach ($steps as $sIdx => $stepText): ?>
                          <li class="flex items-start gap-2">
                            <span class="w-4 h-4 rounded-full bg-amber-200 text-amber-900 font-bold text-[10px] flex items-center justify-center shrink-0 mt-0.5"><?= $sIdx + 1 ?></span>
                            <span><?= e($stepText) ?></span>
                          </li>
                        <?php endforeach; ?>
                      </ol>
                    </div>

                    <!-- Tombol Aksi di Kartu VA -->
                    <div class="mt-4 pt-3 border-t border-amber-200/70 flex items-center gap-2 flex-wrap">
                      <button type="button" class="btn btn-sm btn-gold btn-check-status text-xs py-1.5 px-3" data-order-id="<?= e($pembayaranAktif['order_id']) ?>">
                        <?= t('payment.btn_check_status') ?>
                      </button>

                      <button type="button" class="btn btn-sm btn-ghost bg-white border border-stone-300 text-xs py-1.5 px-3 btn-ganti-metode text-stone-700 hover:bg-stone-50" data-id="<?= $idRes ?>">
                        <?= t('payment.btn_change_method') ?>
                      </button>

                      <?php
                        $bankFmt = format_bank_atau_toko($pembayaranAktif['bank_atau_toko']);
                        $kodeVa = $pembayaranAktif['kode_pembayaran'];
                        $nomFmt = format_rupiah($pembayaranAktif['jumlah']);
                        $batasStr = !empty($pembayaranAktif['kedaluwarsa_pada']) ? date('d/m/Y H:i', strtotime($pembayaranAktif['kedaluwarsa_pada'])) . ' WIB' : '-';
                        $pesanWaVa = urlencode(get_active_lang() === 'en' 
                          ? "Hello Joglo Masayu, I am confirming payment for reservation #RSV-{$idRes} ({$r['nama_villa']}). Method: {$bankFmt}, VA: {$kodeVa}, Amount: {$nomFmt}, Deadline: {$batasStr}."
                          : "Halo Pengelola Joglo Masayu, saya konfirmasi pembayaran reservasi #RSV-{$idRes} ({$r['nama_villa']}). Metode: {$bankFmt}, VA: {$kodeVa}, Nominal: {$nomFmt}, Batas: {$batasStr}."
                        );
                      ?>
                      <a href="https://wa.me/6281234567890?text=<?= $pesanWaVa ?>" target="_blank" rel="noopener" class="btn btn-sm btn-ghost text-xs inline-flex items-center gap-1 text-emerald-800 border-emerald-200 hover:bg-emerald-50 py-1.5 px-2.5">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.007c.106.005.249-.04.39.298.144.347.491 1.2.534 1.288.043.088.072.191.014.306-.058.115-.087.187-.173.289l-.26.302c-.087.087-.179.182-.077.357.101.174.451.744.969 1.205.666.594 1.229.778 1.403.865.174.087.275.072.376-.043.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.043.073.043.419-.101.824z"/></svg>
                        <span>WhatsApp</span>
                      </a>
                    </div>
                  </div>
                <?php else: ?>
                  <!-- Belum ada kode pembayaran terdaftar -->
                  <div class="text-xs text-stone-600 mb-2">
                    <div class="text-amber-800 font-semibold mb-0.5">
                      <?= t('payment.no_va_notice') ?>
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
                <?php endif; ?>

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
                <?php
                  $startTs = !empty($r['dibuat_pada']) ? strtotime($r['dibuat_pada']) : ($batasWaktuTs - (PEMBAYARAN_BATAS_JAM * 3600));
                  $totalWindow = max(1, $batasWaktuTs - $startTs);
                  $elapsedWindow = max(0, time() - $startTs);
                  $progressPct = (int)min(100, max(0, round(($elapsedWindow / $totalWindow) * 100)));
                  $isUrgent = ($sisaDetikBayar <= 10800);
                  $barColorClass = ($isLewatBatas || $isUrgent) ? 'bg-rose-600' : 'bg-amber-600';
                  $initHours = floor($sisaDetikBayar / 3600);
                  $initMins = floor(($sisaDetikBayar % 3600) / 60);
                  $initSecs = $sisaDetikBayar % 60;
                  $initCountdownStr = $sisaDetikBayar > 0
                    ? sprintf('%02dj %02dm %02dd', $initHours, $initMins, $initSecs)
                    : t('payment.countdown_expired');
                ?>
                <div class="w-full space-y-3">
                  <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                    <div class="text-xs text-stone-600 flex-1 space-y-2">
                      <div class="flex items-center justify-between flex-wrap gap-2">
                        <div class="text-stone-800 font-semibold flex items-center gap-1.5 flex-wrap">
                          <svg class="w-4 h-4 <?= $isUrgent ? 'text-rose-600' : 'text-amber-600' ?> shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                          <span><?= e(t('payment.time_remaining')) ?>:</span>
                          <span class="deadline-countdown font-mono font-bold <?= $isUrgent ? 'text-rose-700' : 'text-amber-900' ?>"
                                data-deadline-ts="<?= (int)$batasWaktuTs ?>"
                                data-server-now="<?= time() ?>"
                                data-expired-text="<?= e(t('payment.countdown_expired')) ?>"><?= e($initCountdownStr) ?></span>
                          <?php if ($isLewatBatas): ?>
                            <span class="inline-block text-rose-700 bg-rose-50 border border-rose-200 px-1.5 py-0.5 rounded text-[10px] font-bold"><?= e(t('payment.overdue_badge')) ?></span>
                          <?php endif; ?>
                        </div>
                        <span class="text-[11px] text-stone-500"><?= e(t('payment.expiry_notice', ['waktu' => $batasWaktuStr])) ?></span>
                      </div>

                      <!-- Progress Bar Waktu Berjalan -->
                      <div class="w-full bg-stone-200 rounded-full h-2 overflow-hidden" role="progressbar" aria-valuenow="<?= $progressPct ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="h-full <?= $barColorClass ?> rounded-full transition-all duration-500" style="width: <?= $progressPct ?>%"></div>
                      </div>

                      <?php if ($isUrgent): ?>
                        <div class="p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-start gap-2">
                          <svg class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                          <span><?= e(t('payment.urgent_expiry_warning', ['sisa' => t('payment.three_hours')])) ?></span>
                        </div>
                      <?php endif; ?>

                      <p class="text-[11px] text-stone-500 mb-0">
                        <?= e(t('payment.cash_plan_desc')) ?>
                      </p>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap shrink-0">
                      <button type="button" class="btn btn-sm btn-gold btn-pay-snap text-xs py-2 px-4 shadow font-bold" data-id="<?= $idRes ?>">
                        <?= e(t('payment.btn_pay_now')) ?>
                      </button>

                      <?php if ($rencanaBayar === 'tunai'): ?>
                        <form method="POST" action="reservasi-saya.php" class="inline-block">
                          <?= csrf_field() ?>
                          <input type="hidden" name="aksi" value="kembali_bayar_online">
                          <input type="hidden" name="id_reservasi" value="<?= $idRes ?>">
                          <button type="submit" class="btn btn-sm btn-ghost bg-white border border-stone-300 text-stone-700 hover:bg-stone-50 text-xs py-2 px-3">
                            <?= e(t('payment.btn_switch_online')) ?>
                          </button>
                        </form>
                      <?php else: ?>
                        <form method="POST" action="reservasi-saya.php" class="inline-block">
                          <?= csrf_field() ?>
                          <input type="hidden" name="aksi" value="pilih_bayar_tunai">
                          <input type="hidden" name="id_reservasi" value="<?= $idRes ?>">
                          <button type="submit" class="btn btn-sm btn-ghost bg-white border border-stone-300 text-stone-700 hover:bg-stone-50 text-xs py-2 px-3">
                            <?= e(t('payment.btn_pay_cash')) ?>
                          </button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endif; ?>

            <?php elseif ($r['status'] === 'kedaluwarsa'): ?>
              <!-- KONDISI 3: Kedaluwarsa -->
              <?php
                $waktuKedaluwarsaStr = !empty($r['kedaluwarsa_pada'])
                    ? date('d M Y, H:i', strtotime($r['kedaluwarsa_pada'])) . ' WIB'
                    : $batasWaktuStr;

                $slotMasihTersedia = false;
                if ($r['tanggal_checkin'] >= date('Y-m-d')) {
                    $ketersediaan = hitung_ketersediaan((int)$r['id_tipe'], $r['tanggal_checkin'], $r['tanggal_checkout'], $pdo);
                    if (!empty($ketersediaan['malam'])) {
                        $adaPenuh = false;
                        foreach ($ketersediaan['malam'] as $malam) {
                            if ($malam['status'] === 'penuh') {
                                $adaPenuh = true;
                                break;
                            }
                        }
                        $slotMasihTersedia = !$adaPenuh;
                    }
                }

                $pesanWaExpired = urlencode(get_active_lang() === 'en'
                    ? "Hello Joglo Masayu, my reservation #RSV-{$idRes} ({$r['nama_villa']}) has expired. I would like to inquire about rebooking."
                    : "Halo Pengelola Joglo Masayu, reservasi saya #RSV-{$idRes} ({$r['nama_villa']}) telah kedaluwarsa. Saya ingin menanyakan pemesanan ulang. Terima kasih."
                );
              ?>
              <div class="w-full flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="text-xs text-stone-600 space-y-1.5 flex-1">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="badge-status status-kedaluwarsa text-xs px-2.5 py-0.5 rounded-full font-semibold">
                      <?= e(t('my_bookings.status_kedaluwarsa')) ?>
                    </span>
                    <?php if ($slotMasihTersedia): ?>
                      <span class="inline-flex items-center gap-1 text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full text-[11px] font-semibold">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span><?= e(t('my_bookings.slot_still_available')) ?></span>
                      </span>
                    <?php else: ?>
                      <span class="inline-flex items-center gap-1 text-stone-600 bg-stone-100 border border-stone-200 px-2 py-0.5 rounded-full text-[11px] font-medium">
                        <span><?= e(t('my_bookings.slot_not_available')) ?></span>
                      </span>
                    <?php endif; ?>
                  </div>
                  <p class="text-stone-600 mb-0">
                    <?= e(t('my_bookings.expired_desc', ['waktu' => $waktuKedaluwarsaStr])) ?>
                  </p>
                </div>
                <div class="flex items-center gap-2 flex-wrap shrink-0">
                  <a href="reservasi.php?id_tipe=<?= (int)$r['id_tipe'] ?>&checkin=<?= e($r['tanggal_checkin']) ?>&checkout=<?= e($r['tanggal_checkout']) ?>&tamu=<?= e($r['jumlah_tamu']) ?>" class="btn btn-sm btn-gold text-xs py-1.5 px-3 inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span><?= e(t('my_bookings.rebook_same_date')) ?></span>
                  </a>
                  <a href="https://wa.me/6281234567890?text=<?= $pesanWaExpired ?>" target="_blank" rel="noopener" class="btn btn-sm btn-ghost text-xs inline-flex items-center gap-1.5 text-emerald-800 border-emerald-200 hover:bg-emerald-50 py-1.5 px-3">
                    <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.007c.106.005.249-.04.39.298.144.347.491 1.2.534 1.288.043.088.072.191.014.306-.058.115-.087.187-.173.289l-.26.302c-.087.087-.179.182-.077.357.101.174.451.744.969 1.205.666.594 1.229.778 1.403.865.174.087.275.072.376-.043.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.043.073.043.419-.101.824z"/></svg>
                    <span><?= e(t('my_bookings.btn_whatsapp')) ?></span>
                  </a>
                </div>
              </div>

            <?php else: ?>
              <!-- KONDISI 4: Ditolak, Dibatalkan, atau Kedaluwarsa (fallback) -->
              <div class="text-xs text-stone-500">
                <span><?= e(t('my_bookings.status_no_action', ['status' => $resStatusLabel])) ?></span>
              </div>
            <?php endif; ?>
          </div>

          <!-- Riwayat Percobaan Pembayaran (Collapsible) -->
          <?php if (!empty($history)): ?>
            <div class="mt-4 pt-3 border-t border-stone-100 text-xs">
              <details class="group cursor-pointer">
                <summary class="flex items-center justify-between text-stone-500 hover:text-stone-800 select-none">
                  <span class="font-semibold"><?= t('payment.attempt_history_title') ?> (<?= count($history) ?>)</span>
                  <span class="text-stone-400 group-open:rotate-180 transition-transform duration-200">▾</span>
                </summary>
                <div class="mt-3 overflow-x-auto">
                  <table class="w-full text-left text-xs border border-stone-200 rounded">
                    <thead class="bg-stone-50 text-stone-600 border-b border-stone-200">
                      <tr>
                        <th class="p-2">Waktu</th>
                        <th class="p-2">Order ID</th>
                        <th class="p-2"><?= t('payment.bank_or_store') ?></th>
                        <th class="p-2"><?= t('payment.va_code_label') ?></th>
                        <th class="p-2"><?= t('payment.expiry_time_label') ?></th>
                        <th class="p-2">Nominal</th>
                        <th class="p-2">Status</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100 text-stone-700">
                      <?php foreach ($history as $h): ?>
                        <tr>
                          <td class="p-2 whitespace-nowrap"><?= date('d/m/Y H:i', strtotime($h['dibuat_pada'])) ?></td>
                          <td class="p-2 font-mono"><?= e($h['order_id']) ?></td>
                          <td class="p-2 font-medium"><?= e(format_bank_atau_toko($h['bank_atau_toko'] ?? null)) ?></td>
                          <td class="p-2 font-mono"><?= e(format_nomor_va($h['kode_pembayaran'] ?? null, $h['bank_atau_toko'] ?? null)) ?></td>
                          <td class="p-2 whitespace-nowrap text-stone-500"><?= !empty($h['kedaluwarsa_pada']) ? date('d/m/Y H:i', strtotime($h['kedaluwarsa_pada'])) : '-' ?></td>
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
  <?php
  $kunciStatus = midtrans_kunci_siap();
  if (!$kunciStatus['client_siap']):
    catat_midtrans_log('CONFIG_WARNING', 'reservasi-saya.php', 'Client Key belum dikonfigurasi: ' . $kunciStatus['client_alasan']);
  ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
      if (typeof showToast === 'function') {
        showToast(<?= json_encode(t('payment.not_configured')) ?>, 'error');
      }
      document.querySelectorAll('.btn-pay-snap').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          if (typeof showToast === 'function') {
            showToast(<?= json_encode(t('payment.not_configured')) ?>, 'error');
          }
        });
      });
    });
    </script>
  <?php else: ?>
    <?php
    $snapJsUrl = (!empty($config['MIDTRANS_IS_PRODUCTION']))
        ? 'https://app.midtrans.com/snap/snap.js'
        : 'https://app.sandbox.midtrans.com/snap/snap.js';
    ?>
    <!-- Script Midtrans Snap (Sandbox / Production) sesuai dokumentasi resmi -->
    <script src="<?= $snapJsUrl ?>" data-client-key="<?= e($config['MIDTRANS_CLIENT_KEY'] ?? '') ?>"></script>

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
            setTimeout(function() { window.location.reload(); }, 800);
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

    // 3. Handler Ganti Bank / Metode Pembayaran
    document.querySelectorAll('.btn-ganti-metode').forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        const idReservasi = btn.getAttribute('data-id');
        if (!idReservasi) return;

        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="inline-flex items-center gap-1.5"><svg class="animate-spin w-3 h-3" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>Memuat...</span>';

        const csrfToken = window.MASAYU_AUTH ? window.MASAYU_AUTH.csrf : '';
        fetch('api/buat-pembayaran.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'id_reservasi=' + encodeURIComponent(idReservasi) + '&ganti_metode=1&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
          btn.disabled = false;
          btn.innerHTML = origHtml;
          if (data && data.ok && data.token) {
            if (window.snap) {
              window.snap.pay(data.token, {
                onSuccess: function() {
                  if (typeof showToast === 'function') showToast(window.MASAYU_LANG === 'en' ? 'Payment successful!' : 'Pembayaran berhasil!', 'success');
                  setTimeout(function() { window.location.reload(); }, 800);
                },
                onPending: function(result) {
                  fetch('api/cek-status-pembayaran.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'order_id=' + encodeURIComponent(result.order_id) + '&csrf_token=' + encodeURIComponent(csrfToken)
                  }).finally(function() {
                    setTimeout(function() { window.location.reload(); }, 600);
                  });
                },
                onError: function() {
                  if (typeof showToast === 'function') showToast(window.MASAYU_LANG === 'en' ? 'Payment failed.' : 'Pembayaran gagal.', 'error');
                }
              });
            } else {
              window.location.reload();
            }
          } else {
            if (typeof showToast === 'function') {
              showToast((data && data.pesan) ? data.pesan : 'Gagal mengganti metode pembayaran', 'error');
            }
          }
        })
        .catch(function() {
          btn.disabled = false;
          btn.innerHTML = origHtml;
          if (typeof showToast === 'function') showToast('Terjadi kesalahan jaringan.', 'error');
        });
      });
    });

    // 4. Handler Salin Clipboard (Nomor VA & Nominal)
    document.querySelectorAll('.btn-copy-clipboard').forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        const text = this.getAttribute('data-copy');
        const toastMsg = this.getAttribute('data-toast') || 'Berhasil disalin!';
        if (!text) return;

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(function() {
            if (typeof showToast === 'function') showToast(toastMsg, 'success');
          });
        } else {
          const temp = document.createElement('textarea');
          temp.value = text;
          document.body.appendChild(temp);
          temp.select();
          document.execCommand('copy');
          document.body.removeChild(temp);
          if (typeof showToast === 'function') showToast(toastMsg, 'success');
        }
      });
    });

  });
  </script>
  <?php endif; ?>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // 5. Timer Hitung Mundur Sisa Waktu Pembayaran (Countdown)
  var serverClockOffsetMs = 0;
  var firstDeadlineEl = document.querySelector('.deadline-countdown[data-server-now]');
  if (firstDeadlineEl) {
    var serverNowSec = parseInt(firstDeadlineEl.getAttribute('data-server-now'), 10);
    if (!isNaN(serverNowSec)) {
      serverClockOffsetMs = (serverNowSec * 1000) - Date.now();
    }
  }

  var deadlineReloadScheduled = false;

  function formatCountdown(diffMs) {
    var totalSecs = Math.max(0, Math.floor(diffMs / 1000));
    var hours = Math.floor(totalSecs / 3600);
    var mins = Math.floor((totalSecs % 3600) / 60);
    var secs = totalSecs % 60;
    return (hours < 10 ? '0' : '') + hours + 'j ' + (mins < 10 ? '0' : '') + mins + 'm ' + (secs < 10 ? '0' : '') + secs + 'd';
  }

  function updateCountdowns() {
    var now = Date.now() + serverClockOffsetMs;

    var vaElements = document.querySelectorAll('.va-countdown[data-expiry]');
    vaElements.forEach(function(el) {
      var expiryStr = el.getAttribute('data-expiry');
      if (!expiryStr) return;
      var expiryTime = new Date(expiryStr.replace(/-/g, '/')).getTime();
      var diff = expiryTime - Date.now();
      if (diff <= 0) {
        el.textContent = el.getAttribute('data-expired-text') || 'Kedaluwarsa';
        el.classList.add('text-rose-600', 'font-bold');
      } else {
        el.textContent = formatCountdown(diff);
      }
    });

    var deadlineElements = document.querySelectorAll('.deadline-countdown[data-deadline-ts]');
    deadlineElements.forEach(function(el) {
      var tsSec = parseInt(el.getAttribute('data-deadline-ts'), 10);
      if (isNaN(tsSec)) return;
      var diff = (tsSec * 1000) - now;
      if (diff <= 0) {
        el.textContent = el.getAttribute('data-expired-text') || 'Kedaluwarsa';
        el.classList.add('text-rose-600', 'font-bold');
        if (el.getAttribute('data-was-active') === '1' && !deadlineReloadScheduled) {
          deadlineReloadScheduled = true;
          setTimeout(function() { window.location.reload(); }, 1500);
        }
      } else {
        el.setAttribute('data-was-active', '1');
        el.textContent = formatCountdown(diff);
        if (diff <= 10800 * 1000) {
          el.classList.add('text-rose-700');
        }
      }
    });
  }

  updateCountdowns();
  setInterval(updateCountdowns, 1000);
});
</script>

<?php require_once __DIR__ . '/includes/layout/foot.php'; ?>
