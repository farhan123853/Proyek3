<?php
/**
 * Halaman Struk / Resi Pembayaran & Rincian Tagihan Mandiri
 * Villa Joglo Masayu — Mendukung cetak langsung (A4/A5) & fallback tanpa JavaScript
 */

require_once __DIR__ . '/includes/bootstrap.php';

// 1. Wajib login
if (!is_logged_in()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// 2. Validasi ID
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'id_reservasi', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    http_response_code(400);
    echo '<p style="font-family:sans-serif; text-align:center; padding:2rem;">ID Reservasi tidak valid.</p>';
    exit;
}

$db = get_db();
$userRole = current_role();
$userId = (int)current_user_id();
$isStaff = in_array($userRole, ['admin', 'pengelola', 'pemilik'], true);

// 3. Ambil data reservasi
$stmt = $db->prepare("
    SELECT r.*,
           tk.nama AS nama_villa,
           tk.harga_weekday, tk.harga_weekend,
           p.nama AS nama_pelanggan, p.email AS email_pelanggan, p.kontak AS kontak_pelanggan,
           staf.nama AS nama_staf_proses
    FROM reservasi r
    LEFT JOIN tipe_villa tk ON r.id_tipe = tk.id_tipe
    LEFT JOIN pengguna p ON r.id_pelanggan = p.id_pengguna
    LEFT JOIN pengguna staf ON r.diproses_oleh = staf.id_pengguna
    WHERE r.id_reservasi = ?
    LIMIT 1
");
$stmt->execute([$id]);
$resv = $stmt->fetch(PDO::FETCH_ASSOC);

// 4. Anti-IDOR: Pelanggan HANYA boleh melihat milik sendiri
if (!$resv || (!$isStaff && (int)$resv['id_pelanggan'] !== $userId)) {
    http_response_code(404);
    $pageTitle = 'Data Tidak Ditemukan — Villa Joglo Masayu';
    require_once __DIR__ . '/includes/layout/head.php';
    echo '<main class="container mx-auto px-4 py-16 text-center max-w-lg">
            <div class="bg-white border border-stone-200 rounded-2xl p-8 shadow-sm">
                <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-4 font-bold text-xl">!</div>
                <h1 class="text-xl font-serif font-bold text-stone-900 mb-2">' . t('payment.not_found') . '</h1>
                <p class="text-sm text-stone-500 mb-6">Data struk atau rincian tagihan reservasi ini tidak ditemukan atau Anda tidak memiliki akses.</p>
                <a href="' . ($isStaff ? 'dashboard/pengelola.php' : 'reservasi-saya.php') . '" class="btn btn-gold text-xs py-2 px-5">' . ($isStaff ? t('payment.back_to_dashboard') : t('payment.back_to_reservations')) . '</a>
            </div>
          </main>';
    require_once __DIR__ . '/includes/layout/foot.php';
    exit;
}

// 5. Ambil data pembayaran
$stmtBayar = $db->prepare("
    SELECT pb.*, staf.nama AS pencatat_nama
    FROM pembayaran pb
    LEFT JOIN pengguna staf ON pb.dicatat_oleh = staf.id_pengguna
    WHERE pb.id_reservasi = ?
    ORDER BY pb.id_pembayaran DESC
");
$stmtBayar->execute([$id]);
$allPayments = $stmtBayar->fetchAll(PDO::FETCH_ASSOC);

$eval = evaluasi_status_bayar($allPayments);
$statusBayar = $eval['status'];
$pembayaranUtama = $eval['pembayaran'];
$isLunas = ($statusBayar === 'lunas');

// Durasi & Tanggal
$checkinTs = strtotime($resv['tanggal_checkin']);
$checkoutTs = strtotime($resv['tanggal_checkout']);
$malam = max(1, (int)round(($checkoutTs - $checkinTs) / 86400));

// Tentukan data tamu
$sumberR = $resv['sumber_reservasi'] ?? ($resv['sumber'] ?? 'online');
if (in_array($sumberR, ['walk_in', 'whatsapp', 'telepon'], true)) {
    $guestName = !empty($resv['nama_tamu']) ? $resv['nama_tamu'] : ($resv['nama_pelanggan'] ?? 'Tamu Walk-in');
    $guestContact = !empty($resv['kontak_tamu']) ? $resv['kontak_tamu'] : ($resv['kontak_pelanggan'] ?? '-');
} else {
    $guestName = $resv['nama_pelanggan'] ?? ($resv['nama_tamu'] ?? 'Tamu');
    $guestContact = $resv['kontak_pelanggan'] ?? ($resv['kontak_tamu'] ?? '-');
}

$globalConfig = $config ?? [];
$isSandbox = false;
if ($pembayaranUtama && ($pembayaranUtama['sumber'] ?? '') === 'midtrans') {
    $isSandbox = !($globalConfig['MIDTRANS_IS_PRODUCTION'] ?? false);
}

// Nomor Resi / Order ID
$orderId = $pembayaranUtama['order_id'] ?? ('RSV-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT));
$waktuBayar = !empty($pembayaranUtama['dibayar_pada']) 
    ? date('d/m/Y H:i', strtotime($pembayaranUtama['dibayar_pada'])) . ' WIB' 
    : '-';

// WA Link
$cleanWa = preg_replace('/^0/', '62', preg_replace('/[^\d]/', '', $guestContact));
if ($isLunas) {
    $waText = "Halo Bapak/Ibu {$guestName},\nBerikut bukti pembayaran untuk reservasi *#RSV-{$id}* ({$resv['nama_villa']}):\nStatus: *LUNAS*\nTotal: " . format_rupiah($resv['estimasi_total']) . "\nMetode: " . format_metode_pembayaran($pembayaranUtama['metode_kelompok'] ?? 'tunai') . "\nWaktu: {$waktuBayar}\n\nTerima kasih atas kepercayaannya di Villa Joglo Masayu.";
} else {
    $waText = "Halo Bapak/Ibu {$guestName},\nReservasi Anda *#RSV-{$id}* ({$resv['nama_villa']}) telah dikonfirmasi.\nTotal Tagihan: *" . format_rupiah($resv['estimasi_total']) . "*\nStatus: *MENUNGGU PEMBAYARAN*\nCheck-in: " . date('d/m/Y', $checkinTs) . " s.d. " . date('d/m/Y', $checkoutTs) . ".\n\nSilakan selesaikan pembayaran via transfer/e-wallet atau tunai saat tiba di villa. Terima kasih.";
}
$waLink = "https://wa.me/{$cleanWa}?text=" . rawurlencode($waText);

$autoPrint = isset($_GET['print']) && $_GET['print'] === '1';
$pageTitle = ($isLunas ? t('payment.receipt_title') : t('payment.invoice_title')) . " #RSV-{$id} — Villa Joglo Masayu";

require_once __DIR__ . '/includes/layout/head.php';
?>

<style>
  @media print {
    @page {
      size: A4 portrait;
      margin: 12mm;
    }
    body {
      background: #ffffff !important;
      color: #000000 !important;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
    .no-print, header, nav, footer, #toast-container, .btn-action-area {
      display: none !important;
    }
    .receipt-container {
      max-width: 100% !important;
      margin: 0 !important;
      padding: 0 !important;
      box-shadow: none !important;
      border: 1px solid #292524 !important;
    }
  }
</style>

<main class="container mx-auto px-4 py-8 max-w-3xl" style="padding-top: calc(var(--header-h-scrolled) + 1.5rem); padding-bottom: 5rem;">
  
  <!-- Baris Navigasi & Aksi (Disembunyikan saat cetak) -->
  <div class="no-print flex items-center justify-between mb-6 flex-wrap gap-3">
    <a href="<?= $isStaff ? 'dashboard/pengelola.php' : 'reservasi-saya.php' ?>" class="inline-flex items-center gap-1.5 text-xs text-stone-600 hover:text-stone-900 font-medium">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
      <span><?= $isStaff ? t('payment.back_to_dashboard') : t('payment.back_to_reservations') ?></span>
    </a>

    <div class="flex items-center gap-2 flex-wrap">
      <button type="button" onclick="salinResi('<?= e($orderId) ?>')" class="btn btn-sm btn-ghost border border-stone-300 text-xs py-1.5 px-3 inline-flex items-center gap-1.5">
        <svg class="w-4 h-4 text-stone-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
        <span><?= t('payment.btn_copy_receipt') ?></span>
      </button>

      <a href="<?= $waLink ?>" target="_blank" rel="noopener" class="btn btn-sm btn-ghost border border-stone-300 text-xs py-1.5 px-3 inline-flex items-center gap-1.5">
        <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.007c.106.005.249-.04.39.298.144.347.491 1.2.534 1.288.043.088.072.191.014.306-.058.115-.087.187-.173.289l-.26.302c-.087.087-.179.182-.077.357.101.174.451.744.969 1.205.666.594 1.229.778 1.403.865.174.087.275.072.376-.043.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.043.073.043.419-.101.824z"/></svg>
        <span><?= t('payment.btn_share_wa') ?></span>
      </a>

      <button type="button" onclick="window.print()" class="btn btn-sm btn-gold text-xs py-1.5 px-4 inline-flex items-center gap-1.5">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        <span><?= t('payment.btn_print_pdf') ?></span>
      </button>
    </div>
  </div>

  <!-- KARTU RESI / STRUK UTAMA -->
  <div class="receipt-container bg-white border border-stone-200 rounded-2xl p-6 md:p-8 shadow-sm">
    
    <!-- Header Resi -->
    <div class="flex flex-col sm:flex-row sm:items-start justify-between pb-6 border-b border-stone-200 gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest font-semibold text-amber-800 mb-0.5">Villa Joglo Masayu</p>
        <h2 class="text-2xl font-serif font-bold text-stone-900 mb-1">
          <?= $isLunas ? t('payment.receipt_title') : t('payment.invoice_title') ?>
        </h2>
        <p class="text-xs text-stone-500 mb-0">Jl. Joglo Masayu No. 88, Yogyakarta &bull; +62 812-3456-7890</p>
      </div>

      <div class="sm:text-right">
        <?php if ($isLunas): ?>
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
            <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
            <span>LUNAS / PAID</span>
          </span>
        <?php else: ?>
          <div><?= render_badge_pembayaran($statusBayar, true) ?></div>
        <?php endif; ?>
        
        <div class="text-[11px] font-mono text-stone-500 mt-2">
          <?= t('payment.receipt_no') ?>: <strong class="text-stone-800"><?= e($orderId) ?></strong>
        </div>
        <div class="text-[11px] text-stone-500">
          <?= t('payment.payment_time') ?>: <span class="text-stone-700"><?= e($waktuBayar) ?></span>
        </div>
      </div>
    </div>

    <!-- Banner Sandbox Midtrans jika ada -->
    <?php if ($isSandbox): ?>
      <div class="my-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 flex items-center gap-2">
        <svg class="w-4 h-4 shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span><strong><?= t('payment.sandbox_badge') ?>:</strong> <?= t('payment.sandbox_notice') ?></span>
      </div>
    <?php endif; ?>

    <!-- Grid Data Reservasi & Tamu -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 py-6 border-b border-stone-200 text-xs">
      <div>
        <h4 class="font-serif font-bold text-stone-900 text-sm mb-3 pb-1 border-b border-stone-100"><?= t('payment.guest_info') ?></h4>
        <dl class="space-y-2">
          <div class="flex justify-between">
            <dt class="text-stone-500"><?= t('payment.guest_info') ?>:</dt>
            <dd class="font-semibold text-stone-800 text-right"><?= e($guestName) ?></dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-stone-500"><?= t('payment.guest_contact') ?>:</dt>
            <dd class="text-stone-800 text-right"><?= e($guestContact) ?></dd>
          </div>
          <?php if (!empty($resv['email_pelanggan'])): ?>
            <div class="flex justify-between">
              <dt class="text-stone-500">Email:</dt>
              <dd class="text-stone-800 text-right"><?= e($resv['email_pelanggan']) ?></dd>
            </div>
          <?php endif; ?>
          <div class="flex justify-between">
            <dt class="text-stone-500">Sumber Reservasi:</dt>
            <dd class="capitalize text-stone-800 text-right"><?= e($sumberR) ?></dd>
          </div>
        </dl>
      </div>

      <div>
        <h4 class="font-serif font-bold text-stone-900 text-sm mb-3 pb-1 border-b border-stone-100">Detail Kamar & Jadwal</h4>
        <dl class="space-y-2">
          <div class="flex justify-between">
            <dt class="text-stone-500"><?= t('payment.villa_type') ?>:</dt>
            <dd class="font-semibold text-stone-800 text-right"><?= e($resv['nama_villa']) ?></dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-stone-500"><?= t('payment.stay_period') ?>:</dt>
            <dd class="text-stone-800 text-right"><?= date('d/m/Y', $checkinTs) ?> &ndash; <?= date('d/m/Y', $checkoutTs) ?></dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-stone-500"><?= t('payment.duration_night', ['n' => $malam]) ?>:</dt>
            <dd class="text-stone-800 text-right"><?= $malam ?> Malam (<?= e($resv['jumlah_tamu']) ?> Tamu)</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-stone-500">Kode Reservasi:</dt>
            <dd class="font-mono font-bold text-amber-900 text-right">#RSV-<?= $id ?></dd>
          </div>
        </dl>
      </div>
    </div>

    <!-- Rincian Pembayaran / Tagihan -->
    <div class="py-6 border-b border-stone-200">
      <h4 class="font-serif font-bold text-stone-900 text-sm mb-3">Rincian Finansial</h4>
      
      <table class="w-full text-xs">
        <thead>
          <tr class="border-b border-stone-100 text-stone-500 text-left">
            <th class="py-2">Deskripsi</th>
            <th class="py-2 text-center">Durasi</th>
            <th class="py-2 text-right">Tarif / Malam</th>
            <th class="py-2 text-right">Jumlah</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-stone-100 text-stone-800">
          <tr>
            <td class="py-2.5">
              <span class="font-medium"><?= e($resv['nama_villa']) ?></span>
              <div class="text-[11px] text-stone-500">Check-in: <?= date('d/m/Y', $checkinTs) ?> | Check-out: <?= date('d/m/Y', $checkoutTs) ?></div>
            </td>
            <td class="py-2.5 text-center"><?= $malam ?> Malam</td>
            <td class="py-2.5 text-right"><?= format_rupiah($resv['harga_weekday_saat_pesan'] ?? $resv['harga_weekday'] ?? 0) ?></td>
            <td class="py-2.5 text-right font-medium"><?= format_rupiah($resv['estimasi_total']) ?></td>
          </tr>
        </tbody>
        <tfoot class="border-t-2 border-stone-300 font-semibold text-stone-900 text-xs">
          <tr>
            <td colspan="3" class="pt-3 text-right"><?= t('payment.total_amount') ?>:</td>
            <td class="pt-3 text-right font-bold text-sm text-stone-900"><?= format_rupiah($resv['estimasi_total']) ?></td>
          </tr>
          <?php if ($isLunas): ?>
            <tr>
              <td colspan="3" class="py-1 text-right text-emerald-700"><?= t('payment.amount_paid') ?>:</td>
              <td class="py-1 text-right font-bold text-emerald-700"><?= format_rupiah($pembayaranUtama['jumlah'] ?? $resv['estimasi_total']) ?></td>
            </tr>
            <tr class="text-stone-500">
              <td colspan="3" class="py-1 text-right"><?= t('payment.unpaid_amount') ?>:</td>
              <td class="py-1 text-right font-bold">Rp 0</td>
            </tr>
          <?php else: ?>
            <tr class="text-rose-700">
              <td colspan="3" class="py-1 text-right"><?= t('payment.unpaid_amount') ?>:</td>
              <td class="py-1 text-right font-bold"><?= format_rupiah($resv['estimasi_total']) ?></td>
            </tr>
          <?php endif; ?>
        </tfoot>
      </table>
    </div>

    <!-- Informasi Metode & Petugas Pencatat (Tunai / Midtrans) -->
    <?php if ($pembayaranUtama): ?>
      <div class="py-4 border-b border-stone-200 text-xs grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <span class="text-stone-500"><?= t('payment.payment_method_label') ?>:</span>
          <div class="font-medium text-stone-800 mt-0.5">
            <?= format_metode_pembayaran($pembayaranUtama['metode_kelompok'] ?? '') ?>
            <?php if (!empty($pembayaranUtama['metode_detail'])): ?>
              <span class="text-stone-500 text-[11px]">(<?= e($pembayaranUtama['metode_detail']) ?>)</span>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!empty($pembayaranUtama['transaction_id'])): ?>
          <div>
            <span class="text-stone-500"><?= t('payment.transaction_ref') ?>:</span>
            <div class="font-mono text-stone-800 mt-0.5"><?= e($pembayaranUtama['transaction_id']) ?></div>
          </div>
        <?php endif; ?>

        <?php if (!empty($pembayaranUtama['pencatat_nama'])): ?>
          <div>
            <span class="text-stone-500"><?= t('payment.recorded_by') ?>:</span>
            <div class="font-medium text-stone-800 mt-0.5"><?= e($pembayaranUtama['pencatat_nama']) ?></div>
          </div>
        <?php endif; ?>

        <?php if (!empty($pembayaranUtama['catatan'])): ?>
          <div>
            <span class="text-stone-500"><?= t('payment.cash_note_label') ?>:</span>
            <div class="text-stone-700 mt-0.5 italic">"<?= e($pembayaranUtama['catatan']) ?>"</div>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- Riwayat Transaksi (jika ada lebih dari 1 transaksi / percobaan) -->
    <?php if (count($allPayments) > 1): ?>
      <div class="py-4 border-b border-stone-200 text-xs">
        <h5 class="font-semibold text-stone-800 mb-2"><?= t('payment.payment_history') ?></h5>
        <div class="space-y-1.5">
          <?php foreach ($allPayments as $pHist): ?>
            <div class="flex items-center justify-between p-2 bg-stone-50 rounded text-[11px]">
              <div>
                <span class="font-mono font-medium"><?= e($pHist['order_id']) ?></span>
                <span class="text-stone-500 ml-1.5">&bull; <?= format_metode_pembayaran($pHist['metode_kelompok']) ?></span>
              </div>
              <div class="flex items-center gap-2">
                <span><?= format_rupiah($pHist['jumlah']) ?></span>
                <?= render_badge_pembayaran($pHist['status'], false) ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Footer Resi / Kebijakan -->
    <div class="pt-6 text-center text-xs text-stone-400">
      <p class="mb-1">Terima kasih telah memilih Villa Joglo Masayu sebagai tujuan istirahat Anda.</p>
      <p class="text-[10px] mb-0">Dokumen ini merupakan bukti sah transaksi reservasi akomodasi resmi Villa Joglo Masayu.</p>
    </div>

  </div>

  <div id="toast-struk" class="fixed bottom-6 right-6 z-50 bg-stone-900 text-white text-xs px-4 py-2.5 rounded-lg shadow-lg hidden transition-opacity duration-200">
    <?= t('payment.copied_toast') ?>
  </div>

</main>

<script>
function salinResi(teks) {
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(teks).then(() => tampilkanToast());
  } else {
    const input = document.createElement('input');
    input.value = teks;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);
    tampilkanToast();
  }
}

function tampilkanToast() {
  const toast = document.getElementById('toast-struk');
  if (!toast) return;
  toast.classList.remove('hidden');
  toast.style.opacity = '1';
  setTimeout(() => {
    toast.style.opacity = '0';
    setTimeout(() => toast.classList.add('hidden'), 300);
  }, 2500);
}

<?php if ($autoPrint): ?>
window.addEventListener('DOMContentLoaded', () => {
  setTimeout(() => window.print(), 350);
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/layout/foot.php'; ?>
