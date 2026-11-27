<?php
/**
 * Dashboard Pemilik (Owner) — Villa Joglo Masayu
 * Kebutuhan: Tahap 8 (Bagian 2)
 * - Murni BACA (Read-Only), tanpa satu pun tombol atau formulir pengubah data
 * - Pemasukan dihitung berdasarkan tanggal pembayaran (dibayar_pada), bukan check-in
 * - Banner label dua bahasa (Sandbox / Production)
 * - Filter chip metode & pemilih tahun (asinkronus tanpa reload penuh, fallback server-rendered)
 * - 4 Kartu ringkasan: Bulan ini, Bulan lalu, Total tahun terpilih, Rata-rata per bulan
 * - Grafik batang bertumpuk 12 bulan (HTML/SVG vanilla) dengan aksesibilitas aria-label
 * - Tabel rincian per bulan (desktop) yang bertransformasi menjadi kartu bertumpuk di mobile (<640px)
 * - Kartu kontribusi pemasukan per tipe villa
 * - Kartu terpisah estimasi nilai reservasi terkonfirmasi (tidak digabung dengan pemasukan)
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/laporan.php';

// Hak akses: hanya untuk Pemilik atau Admin
require_role(['pemilik', 'admin'], 'login.php');

$pdo = get_db();
pemicu_oportunistik_kedaluwarsa($pdo);

// 1. Parameter Filter
$tahunSekarang = (int)date('Y');
$bulanSekarang = (int)date('n');
$daftarTahun   = daftar_tahun_laporan();

$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : $tahunSekarang;
if (!in_array($tahun, $daftarTahun, true) && ($tahun < 2020 || $tahun > 2100)) {
    $tahun = $tahunSekarang;
}

$allowedMetode = ['all', 'transfer_bank', 'e_wallet', 'tunai', 'lainnya'];
$metode = trim($_GET['metode'] ?? 'all');
if (!in_array($metode, $allowedMetode, true)) {
    $metode = 'all';
}

// 2. Data Laporan (Server-side rendering awal)
$ringkasan = ringkasan_pemasukan($tahun, $metode);
$bulanan   = pemasukan_bulanan($tahun, $metode);
$perTipe   = pemasukan_per_tipe($tahun);

// 3. Estimasi Nilai Reservasi Terkonfirmasi (Terpisah dari uang masuk aktual)
$stmtEst = $pdo->query("
    SELECT 
        COUNT(*) AS total_terkonfirmasi,
        COALESCE(SUM(estimasi_total), 0) AS total_estimasi
    FROM reservasi 
    WHERE status = 'dikonfirmasi'
");
$estimasi = $stmtEst->fetch();

// 3b. Statistik Kedaluwarsa (Hanya-baca, tidak masuk pemasukan & tidak memengaruhi estimasi terkonfirmasi)
$stmtKedaluwarsa = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_kedaluwarsa,
        COALESCE(SUM(estimasi_total), 0) AS total_nominal
    FROM reservasi 
    WHERE status = 'kedaluwarsa' AND YEAR(tanggal_checkin) = ?
");
$stmtKedaluwarsa->execute([$tahun]);
$statKedaluwarsaTahun = $stmtKedaluwarsa->fetch() ?: ['total_kedaluwarsa' => 0, 'total_nominal' => 0];

// 4. Sumber Reservasi (Online vs Offline pada tahun terpilih)
$stmtSumber = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN sumber = 'online' THEN 1 ELSE 0 END) AS total_online,
        SUM(CASE WHEN sumber IN ('walk_in', 'whatsapp', 'telepon') THEN 1 ELSE 0 END) AS total_offline,
        COUNT(*) AS total_semua
    FROM reservasi
    WHERE YEAR(tanggal_checkin) = ?
");
$stmtSumber->execute([$tahun]);
$statSumber = $stmtSumber->fetch() ?: ['total_online' => 0, 'total_offline' => 0, 'total_semua' => 0];

$isProduction = (bool)($config['MIDTRANS_IS_PRODUCTION'] ?? false);

$pageTitle = t('owner.title') . ' — Villa Joglo Masayu';
$pageDesc  = t('owner.subtitle');
$bodyClass = 'page-villa';

require_once __DIR__ . '/../includes/layout/head.php';

// Helper nama bulan
$namaBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$namaBulanSingkat = [
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
    5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
    9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
];
if (get_active_lang() === 'en') {
    $namaBulan = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];
    $namaBulanSingkat = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
    ];
}

// Cek apakah ada kolom lainnya yang bernilai > 0
$adaLainnya = false;
$maxBulanNominal = 0;
foreach ($bulanan as $b) {
    if (($b['lainnya'] ?? 0) > 0) $adaLainnya = true;
    if ($b['total'] > $maxBulanNominal) $maxBulanNominal = $b['total'];
}
if ($maxBulanNominal === 0) $maxBulanNominal = 1;
?>

<main class="container mx-auto px-4" style="padding-top: calc(var(--header-h-scrolled) + 2rem); padding-bottom: 5rem; max-width: 1200px;">

  <!-- 1. Header & Filter Kontrol -->
  <div class="flex flex-col md:flex-row md:items-end justify-between mb-6 gap-4">
    <div>
      <p class="text-xs uppercase tracking-widest font-semibold text-amber-800 mb-1"><?= t('dashboard.pemilik_title') ?></p>
      <h1 class="text-2xl md:text-3xl font-serif text-stone-900 mb-1"><?= t('owner.title') ?></h1>
      <p class="text-sm text-stone-600 mb-0"><?= t('owner.subtitle') ?></p>
    </div>

    <!-- Filter Bar: Chip Metode & Pemilih Tahun -->
    <div class="flex flex-wrap items-center gap-3">
      <!-- Pemilih Tahun -->
      <div class="flex items-center gap-1.5 bg-white border border-stone-300 rounded-lg px-2.5 py-1 shadow-sm">
        <label for="tahunSelect" class="text-xs font-semibold text-stone-600 shrink-0">Tahun:</label>
        <select id="tahunSelect" class="bg-transparent text-xs font-bold text-stone-800 border-none focus:ring-0 cursor-pointer">
          <?php foreach ($daftarTahun as $y): ?>
            <option value="<?= $y ?>" <?= $y === $tahun ? 'selected' : '' ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Chip Metode Pembayaran -->
      <div class="inline-flex rounded-lg border border-stone-200 bg-stone-100 p-0.5 text-xs shadow-sm flex-wrap" id="metodeChips">
        <button type="button" data-metode="all" class="chip-metode px-3 py-1.5 font-medium rounded-md transition-colors <?= $metode === 'all' ? 'bg-white text-stone-900 font-bold shadow-sm' : 'text-stone-600 hover:text-stone-900' ?>">
          <?= t('owner.method_all') ?>
        </button>
        <button type="button" data-metode="transfer_bank" class="chip-metode px-3 py-1.5 font-medium rounded-md transition-colors <?= $metode === 'transfer_bank' ? 'bg-white text-stone-900 font-bold shadow-sm' : 'text-stone-600 hover:text-stone-900' ?>">
          <?= t('payment.method_transfer_bank') ?>
        </button>
        <button type="button" data-metode="e_wallet" class="chip-metode px-3 py-1.5 font-medium rounded-md transition-colors <?= $metode === 'e_wallet' ? 'bg-white text-stone-900 font-bold shadow-sm' : 'text-stone-600 hover:text-stone-900' ?>">
          <?= t('payment.method_e_wallet') ?>
        </button>
        <button type="button" data-metode="tunai" class="chip-metode px-3 py-1.5 font-medium rounded-md transition-colors <?= $metode === 'tunai' ? 'bg-white text-stone-900 font-bold shadow-sm' : 'text-stone-600 hover:text-stone-900' ?>">
          <?= t('payment.method_tunai') ?>
        </button>
      </div>
    </div>
  </div>

  <!-- 2. Banner Label Lingkungan (Sandbox vs Production) -->
  <div class="mb-6">
    <?php if (!$isProduction): ?>
      <div class="flex items-center gap-3 p-3.5 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-900 shadow-sm" role="note">
        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-200 text-amber-900 tracking-wider uppercase shrink-0">Sandbox</span>
        <span><?= t('owner.banner_sandbox') ?></span>
      </div>
    <?php else: ?>
      <div class="flex items-center gap-3 p-3.5 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-900 shadow-sm" role="note">
        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-200 text-emerald-900 tracking-wider uppercase shrink-0">Live</span>
        <span><?= t('owner.banner_production') ?></span>
      </div>
    <?php endif; ?>
  </div>

  <!-- Kontainer Konten Dinamis (diperbarui via Fetch atau render awal) -->
  <div id="dashboardContent" class="relative">

    <!-- Loading Skeleton Overlay (tersembunyi secara default) -->
    <div id="loadingOverlay" class="absolute inset-0 bg-white/70 backdrop-blur-[1px] rounded-2xl z-20 flex items-center justify-center hidden">
      <div class="flex items-center gap-2 bg-stone-900 text-white text-xs px-4 py-2 rounded-lg shadow-lg">
        <svg class="animate-spin w-4 h-4 text-amber-400" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
        <span>Memperbarui data...</span>
      </div>
    </div>

    <!-- 3. Empat Kartu Ringkasan Eksekutif (Grid: 1 -> 2 -> 4 kolom) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <!-- Kartu 1: Bulan Ini -->
      <div class="bg-white border border-stone-200 rounded-2xl p-5 shadow-sm hover:shadow transition-shadow">
        <span class="text-xs uppercase tracking-wider font-semibold text-stone-500 block mb-1"><?= t('owner.card_this_month') ?></span>
        <div class="text-2xl font-serif font-bold text-amber-900 mb-1" id="cardBulanIni">
          <?= format_rupiah($ringkasan['bulan_ini']) ?>
        </div>
        <p class="text-[11px] text-stone-500 mb-0" id="cardBulanIniSub">
          <?= t('owner.as_of_date', ['tanggal' => date('d M Y')]) ?>
        </p>
      </div>

      <!-- Kartu 2: Bulan Lalu -->
      <div class="bg-white border border-stone-200 rounded-2xl p-5 shadow-sm hover:shadow transition-shadow">
        <span class="text-xs uppercase tracking-wider font-semibold text-stone-500 block mb-1"><?= t('owner.card_last_month') ?></span>
        <div class="text-2xl font-serif font-bold text-stone-800 mb-1" id="cardBulanLalu">
          <?= format_rupiah($ringkasan['bulan_lalu']) ?>
        </div>
        <p class="text-[11px] text-stone-500 mb-0" id="cardBulanLaluSub">
          <?php if ($ringkasan['bulan_lalu'] > 0 && $ringkasan['persen_bulan_ini'] !== null): ?>
            <?= t('owner.pct_of_last_month', ['persen' => $ringkasan['persen_bulan_ini']]) ?>
          <?php else: ?>
            <?= t('owner.no_last_month') ?>
          <?php endif; ?>
        </p>
      </div>

      <!-- Kartu 3: Total Tahun Terpilih -->
      <div class="bg-white border border-stone-200 rounded-2xl p-5 shadow-sm hover:shadow transition-shadow">
        <span class="text-xs uppercase tracking-wider font-semibold text-stone-500 block mb-1" id="cardTotalTahunLabel">
          <?= t('owner.card_total_year') ?> (<?= $tahun ?>)
        </span>
        <div class="text-2xl font-serif font-bold text-emerald-800 mb-1" id="cardTotalTahun">
          <?= format_rupiah($ringkasan['total_tahun']) ?>
        </div>
        <p class="text-[11px] text-emerald-700 font-medium mb-0" id="cardTotalTahunSub">
          <?= $ringkasan['total_transaksi'] ?> transaksi lunas
        </p>
      </div>

      <!-- Kartu 4: Rata-Rata Per Bulan -->
      <div class="bg-white border border-stone-200 rounded-2xl p-5 shadow-sm hover:shadow transition-shadow">
        <span class="text-xs uppercase tracking-wider font-semibold text-stone-500 block mb-1"><?= t('owner.card_avg_month') ?></span>
        <div class="text-2xl font-serif font-bold text-stone-800 mb-1" id="cardRataRata">
          <?= format_rupiah($ringkasan['rata_rata_bulanan']) ?>
        </div>
        <p class="text-[11px] text-stone-500 mb-0" id="cardRataRataSub">
          <?= t('owner.avg_note', ['bulan' => $ringkasan['pembagi_bulan']]) ?>
        </p>
      </div>
    </div>

    <!-- 4. Grafik Batang Bertumpuk Per Bulan (12 Bulan) -->
    <div class="bg-white border border-stone-200 rounded-2xl p-5 md:p-6 mb-8 shadow-sm">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-stone-100 gap-3 mb-6">
        <div>
          <h2 class="text-lg font-serif font-bold text-stone-900 mb-0.5"><?= t('owner.chart_title') ?> (<?= $tahun ?>)</h2>
          <p class="text-xs text-stone-500 mb-0">Total pemasukan per metode per bulan</p>
        </div>

        <!-- Legenda Grafik (Warna + Teks) -->
        <div class="flex items-center gap-3 text-xs flex-wrap">
          <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded bg-amber-600 inline-block"></span>
            <span class="text-stone-700 font-medium"><?= t('payment.method_transfer_bank') ?></span>
          </div>
          <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded bg-emerald-600 inline-block"></span>
            <span class="text-stone-700 font-medium"><?= t('payment.method_e_wallet') ?></span>
          </div>
          <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded bg-sky-600 inline-block"></span>
            <span class="text-stone-700 font-medium"><?= t('payment.method_tunai') ?></span>
          </div>
          <?php if ($adaLainnya): ?>
            <div class="flex items-center gap-1.5" id="legendLainnya">
              <span class="w-3 h-3 rounded bg-purple-600 inline-block"></span>
              <span class="text-stone-700 font-medium"><?= t('owner.col_other') ?></span>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Area Batang Grafik (SVG / HTML Bar) -->
      <div role="img" aria-label="<?= t('owner.chart_aria_label', ['tahun' => $tahun]) ?>" class="overflow-x-auto pb-2">
        <div class="min-w-[600px] flex items-end justify-between gap-2 md:gap-3 h-64 pt-8 px-2 border-b border-stone-200" id="chartBarsContainer">
          <?php foreach ($bulanan as $m => $b): ?>
            <?php
              $isBulanIni = !empty($b['is_bulan_ini']);
              $isMasaDepan = !empty($b['is_masa_depan']);
              $totalNominal = $b['total'];
              $tinggiPersen = $maxBulanNominal > 0 ? ($totalNominal / $maxBulanNominal) * 100 : 0;
              if ($tinggiPersen > 0 && $tinggiPersen < 4) $tinggiPersen = 4; // Minimal bar agar terlihat

              // Sub-persentase dari total bulan tersebut
              $pBank   = $totalNominal > 0 ? ($b['transfer_bank'] / $totalNominal) * 100 : 0;
              $pWallet = $totalNominal > 0 ? ($b['e_wallet'] / $totalNominal) * 100 : 0;
              $pTunai  = $totalNominal > 0 ? ($b['tunai'] / $totalNominal) * 100 : 0;
              $pLain   = $totalNominal > 0 ? ($b['lainnya'] / $totalNominal) * 100 : 0;

              // Format teks total dalam juta rupiah (cth: 12.5 jt)
              $totalJuta = $totalNominal > 0 ? number_format($totalNominal / 1000000, 1, ',', '.') . ' jt' : '0';
              $namaBln = $namaBulanSingkat[$m];
            ?>

            <div class="flex-1 flex flex-col items-center h-full justify-end group relative" tabindex="0"
                 aria-label="<?= $namaBulan[$m] ?>: Total <?= format_rupiah($totalNominal) ?> (Bank: <?= format_rupiah($b['transfer_bank']) ?>, E-Wallet: <?= format_rupiah($b['e_wallet']) ?>, Tunai: <?= format_rupiah($b['tunai']) ?>)">
              
              <!-- Nilai di atas batang -->
              <span class="text-[10px] font-bold text-stone-700 mb-1 whitespace-nowrap opacity-90 group-hover:scale-110 transition-transform">
                <?= $totalNominal > 0 ? $totalJuta : '-' ?>
              </span>

              <!-- Batang Bertumpuk -->
              <div class="w-full max-w-[38px] rounded-t-md overflow-hidden flex flex-col-reverse transition-all duration-300 <?= $isBulanIni ? 'ring-2 ring-amber-500 ring-offset-1' : '' ?> <?= $isMasaDepan ? 'bg-stone-100 opacity-40' : 'bg-stone-50' ?>"
                   style="height: <?= $isMasaDepan ? '4px' : max(4, $tinggiPersen) . '%' ?>;">
                
                <?php if ($totalNominal > 0): ?>
                  <!-- Segment Tunai (Sky) -->
                  <?php if ($b['tunai'] > 0): ?>
                    <div style="height: <?= $pTunai ?>%;" class="bg-sky-600 hover:bg-sky-500 transition-colors" title="Tunai: <?= format_rupiah($b['tunai']) ?>"></div>
                  <?php endif; ?>

                  <!-- Segment E-Wallet (Emerald) -->
                  <?php if ($b['e_wallet'] > 0): ?>
                    <div style="height: <?= $pWallet ?>%;" class="bg-emerald-600 hover:bg-emerald-500 transition-colors" title="E-Wallet: <?= format_rupiah($b['e_wallet']) ?>"></div>
                  <?php endif; ?>

                  <!-- Segment Bank (Amber) -->
                  <?php if ($b['transfer_bank'] > 0): ?>
                    <div style="height: <?= $pBank ?>%;" class="bg-amber-600 hover:bg-amber-500 transition-colors" title="Transfer Bank: <?= format_rupiah($b['transfer_bank']) ?>"></div>
                  <?php endif; ?>

                  <!-- Segment Lainnya (Purple) -->
                  <?php if ($b['lainnya'] > 0): ?>
                    <div style="height: <?= $pLain ?>%;" class="bg-purple-600 hover:bg-purple-500 transition-colors" title="Lainnya: <?= format_rupiah($b['lainnya']) ?>"></div>
                  <?php endif; ?>
                <?php endif; ?>

              </div>

              <!-- Label Bulan di Bawah Batang -->
              <div class="mt-2 text-center">
                <span class="text-xs font-semibold block <?= $isBulanIni ? 'text-amber-800 font-bold' : 'text-stone-600' ?>">
                  <?= $namaBln ?>
                </span>
                <?php if ($isBulanIni): ?>
                  <span class="inline-block text-[9px] font-bold text-amber-700 bg-amber-100 px-1 rounded-sm leading-tight mt-0.5 whitespace-nowrap">
                    berjalan
                  </span>
                <?php endif; ?>
              </div>

              <!-- Tooltip Hover Popover -->
              <div class="absolute bottom-full mb-8 hidden group-hover:block group-focus:block bg-stone-900 text-white text-[11px] rounded-lg p-2.5 shadow-xl z-30 pointer-events-none min-w-[150px]">
                <div class="font-bold border-b border-stone-700 pb-1 mb-1 text-amber-400"><?= $namaBulan[$m] ?> <?= $tahun ?></div>
                <div class="flex justify-between py-0.5"><span>Transfer:</span><span class="font-mono"><?= format_rupiah($b['transfer_bank']) ?></span></div>
                <div class="flex justify-between py-0.5"><span>E-Wallet:</span><span class="font-mono"><?= format_rupiah($b['e_wallet']) ?></span></div>
                <div class="flex justify-between py-0.5"><span>Tunai:</span><span class="font-mono"><?= format_rupiah($b['tunai']) ?></span></div>
                <div class="flex justify-between py-0.5 border-t border-stone-700 pt-1 font-bold text-white"><span>Total:</span><span class="font-mono"><?= format_rupiah($totalNominal) ?></span></div>
              </div>

            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- 5. Tabel "Rincian per Bulan" (Desktop >=640px) & Kartu Bulan (Mobile <640px) -->
    <div class="bg-white border border-stone-200 rounded-2xl p-5 md:p-6 mb-8 shadow-sm">
      <div class="flex items-center justify-between pb-4 border-b border-stone-100 mb-4">
        <div>
          <h2 class="text-lg font-serif font-bold text-stone-900 mb-0.5"><?= t('owner.table_title') ?> (<?= $tahun ?>)</h2>
          <p class="text-xs text-stone-500 mb-0">Rincian tabel terstruktur urut bulan terbaru</p>
        </div>
      </div>

      <!-- Versi Desktop: Tabel Terstruktur (hidden sm:block) -->
      <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-left text-xs border border-stone-200 rounded-lg overflow-hidden" id="desktopTable">
          <thead class="bg-stone-50 text-stone-700 font-semibold border-b border-stone-200">
            <tr>
              <th class="p-3"><?= t('owner.col_month') ?></th>
              <th class="p-3 text-center"><?= t('owner.col_trx') ?></th>
              <th class="p-3 text-right"><?= t('owner.col_bank') ?></th>
              <th class="p-3 text-right"><?= t('owner.col_ewallet') ?></th>
              <th class="p-3 text-right"><?= t('owner.col_cash') ?></th>
              <?php if ($adaLainnya): ?>
                <th class="p-3 text-right" id="thLainnya"><?= t('owner.col_other') ?></th>
              <?php endif; ?>
              <th class="p-3 text-right"><?= t('owner.col_total') ?></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100 text-stone-700" id="desktopTableBody">
            <?php
              // Tampilkan urutan bulan terbaru di atas (12 -> 1)
              $reversedMonths = array_reverse($bulanan, true);
              foreach ($reversedMonths as $m => $b):
                $isBulanIni = !empty($b['is_bulan_ini']);
            ?>
              <tr class="<?= $isBulanIni ? 'bg-amber-50/70 font-semibold text-amber-950' : 'hover:bg-stone-50/50' ?>">
                <td class="p-3 font-medium">
                  <?= $namaBulan[$m] ?>
                  <?php if ($isBulanIni): ?>
                    <span class="inline-block text-[10px] bg-amber-200 text-amber-900 px-1.5 py-0.2 rounded font-bold ml-1.5"><?= t('owner.current_month') ?></span>
                  <?php endif; ?>
                </td>
                <td class="p-3 text-center font-mono"><?= $b['jumlah_transaksi'] ?></td>
                <td class="p-3 text-right font-mono"><?= format_rupiah($b['transfer_bank']) ?></td>
                <td class="p-3 text-right font-mono"><?= format_rupiah($b['e_wallet']) ?></td>
                <td class="p-3 text-right font-mono"><?= format_rupiah($b['tunai']) ?></td>
                <?php if ($adaLainnya): ?>
                  <td class="p-3 text-right font-mono"><?= format_rupiah($b['lainnya']) ?></td>
                <?php endif; ?>
                <td class="p-3 text-right font-bold text-stone-900 font-mono"><?= format_rupiah($b['total']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="bg-stone-100 text-stone-900 font-bold border-t-2 border-stone-300" id="desktopTableFoot">
            <tr>
              <td class="p-3"><?= t('owner.total_year_label', ['tahun' => $tahun]) ?></td>
              <td class="p-3 text-center font-mono"><?= $ringkasan['total_transaksi'] ?></td>
              <td class="p-3 text-right font-mono"><?= format_rupiah($ringkasan['total_transfer']) ?></td>
              <td class="p-3 text-right font-mono"><?= format_rupiah($ringkasan['total_ewallet']) ?></td>
              <td class="p-3 text-right font-mono"><?= format_rupiah($ringkasan['total_tunai']) ?></td>
              <?php if ($adaLainnya): ?>
                <td class="p-3 text-right font-mono"><?= format_rupiah($ringkasan['total_lainnya']) ?></td>
              <?php endif; ?>
              <td class="p-3 text-right font-bold text-amber-900 font-mono text-sm"><?= format_rupiah($ringkasan['total_tahun']) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Versi Mobile: Kartu per Bulan (block sm:hidden) -->
      <div class="block sm:hidden space-y-3" id="mobileCardsContainer">
        <?php foreach ($reversedMonths as $m => $b): ?>
          <?php
            $isBulanIni = !empty($b['is_bulan_ini']);
            $totBulan = $b['total'];
            $pBank   = $totBulan > 0 ? ($b['transfer_bank'] / $totBulan) * 100 : 0;
            $pWallet = $totBulan > 0 ? ($b['e_wallet'] / $totBulan) * 100 : 0;
            $pTunai  = $totBulan > 0 ? ($b['tunai'] / $totBulan) * 100 : 0;
            $pLain   = $totBulan > 0 ? ($b['lainnya'] / $totBulan) * 100 : 0;
          ?>
          <div class="border <?= $isBulanIni ? 'border-amber-400 bg-amber-50/40' : 'border-stone-200 bg-white' ?> rounded-xl p-3.5 shadow-sm text-xs">
            <div class="flex items-center justify-between mb-2">
              <div class="font-bold text-stone-800 text-sm flex items-center gap-1.5">
                <span><?= $namaBulan[$m] ?></span>
                <?php if ($isBulanIni): ?>
                  <span class="text-[10px] bg-amber-200 text-amber-900 px-1.5 py-0.2 rounded font-bold"><?= t('owner.current_month') ?></span>
                <?php endif; ?>
              </div>
              <div class="font-bold text-amber-900 font-mono text-sm"><?= format_rupiah($totBulan) ?></div>
            </div>

            <!-- Bar Horizontal Bertumpuk -->
            <div class="w-full h-2.5 bg-stone-100 rounded-full overflow-hidden flex mb-2.5">
              <?php if ($totBulan > 0): ?>
                <?php if ($b['transfer_bank'] > 0): ?><div style="width: <?= $pBank ?>%;" class="bg-amber-600"></div><?php endif; ?>
                <?php if ($b['e_wallet'] > 0): ?><div style="width: <?= $pWallet ?>%;" class="bg-emerald-600"></div><?php endif; ?>
                <?php if ($b['tunai'] > 0): ?><div style="width: <?= $pTunai ?>%;" class="bg-sky-600"></div><?php endif; ?>
                <?php if ($b['lainnya'] > 0): ?><div style="width: <?= $pLain ?>%;" class="bg-purple-600"></div><?php endif; ?>
              <?php endif; ?>
            </div>

            <!-- Rincian Nilai -->
            <div class="text-[11px] text-stone-600 flex flex-wrap gap-x-2 gap-y-1">
              <span>Transfer: <strong class="text-stone-800 font-mono"><?= format_rupiah($b['transfer_bank']) ?></strong></span>
              <span>·</span>
              <span>E-Wallet: <strong class="text-stone-800 font-mono"><?= format_rupiah($b['e_wallet']) ?></strong></span>
              <span>·</span>
              <span>Tunai: <strong class="text-stone-800 font-mono"><?= format_rupiah($b['tunai']) ?></strong></span>
              <span>·</span>
              <span class="font-medium"><?= $b['jumlah_transaksi'] ?> transaksi</span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

    </div>

    <!-- 6. Kartu "Per Tipe Villa" & 7. Kartu Terpisah "Estimasi Nilai Reservasi Terkonfirmasi" -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

      <!-- Kartu Per Tipe Villa -->
      <div class="bg-white border border-stone-200 rounded-2xl p-5 md:p-6 shadow-sm">
        <h2 class="text-lg font-serif font-bold text-stone-900 mb-1"><?= t('owner.villa_breakdown_title') ?> (<?= $tahun ?>)</h2>
        <p class="text-xs text-stone-500 mb-4">Kontribusi transaksi lunas berdasarkan unit villa</p>

        <div class="space-y-4" id="villaBreakdownContainer">
          <?php foreach ($perTipe['data'] as $v): ?>
            <div>
              <div class="flex items-center justify-between text-xs font-semibold mb-1">
                <span class="text-stone-800"><?= e($v['nama_tipe']) ?></span>
                <span class="text-stone-600 font-mono"><?= format_rupiah($v['total_nominal']) ?> (<?= $v['persentase'] ?>%)</span>
              </div>
              <div class="w-full h-2.5 bg-stone-100 rounded-full overflow-hidden">
                <div class="h-full bg-amber-700 rounded-full transition-all duration-500" style="width: <?= $v['persentase'] ?>%;"></div>
              </div>
              <div class="text-[10px] text-stone-400 mt-0.5"><?= $v['jumlah_transaksi'] ?> transaksi lunas</div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Kartu Terpisah: Estimasi Nilai Reservasi Terkonfirmasi (Label Wajib: Estimasi, Belum Tentu Dibayar) -->
      <div class="bg-gradient-to-br from-amber-50 to-orange-50 border-2 border-amber-300 rounded-2xl p-5 md:p-6 shadow-sm flex flex-col justify-between">
        <div>
          <div class="flex items-center gap-2 mb-2">
            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
            <h2 class="text-sm uppercase tracking-wider font-bold text-amber-900 mb-0"><?= t('owner.card_estimation_title') ?></h2>
          </div>
          <div class="text-3xl font-serif font-bold text-stone-900 mb-2" id="valEstimasi">
            <?= format_rupiah($estimasi['total_estimasi'] ?? 0) ?>
          </div>
          <p class="text-xs text-stone-600 mb-4">
            Total estimasi nilai dari <strong><?= (int)($estimasi['total_terkonfirmasi'] ?? 0) ?> reservasi</strong> yang telah dikonfirmasi pengelola.
          </p>
        </div>

        <!-- Peringatan Kepatuhan Bisnis (Jangan digabung dengan uang masuk aktual) -->
        <div class="bg-white/80 border border-amber-300 rounded-xl p-3.5 text-xs text-amber-900 flex items-start gap-2.5">
          <svg class="w-4 h-4 text-amber-700 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <div>
            <strong>Catatan:</strong> <?= t('owner.estimation_notice') ?>. Angka ini mencakup reservasi yang masih menunggu pelunasan tamu di villa atau transfer online.
          </div>
        </div>
      </div>

    </div>

    <!-- 8. Kartu Sumber Reservasi (Online vs Offline - Read-only Tahap 11) -->
    <div class="bg-white border border-stone-200 rounded-2xl p-5 mb-8 shadow-sm">
      <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm uppercase tracking-wider font-bold text-stone-700 mb-0"><?= t('owner.source_card_title') ?> (<?= $tahun ?>)</h3>
        <span class="text-xs text-stone-500 font-medium"><?= (int)$statSumber['total_semua'] ?> total reservasi</span>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div class="bg-sky-50 border border-sky-200 rounded-xl p-4 text-center">
          <span class="text-xs text-sky-800 font-semibold block uppercase tracking-wider mb-1"><?= t('offline.sumber_online') ?></span>
          <div class="text-2xl font-serif font-bold text-sky-900"><?= (int)$statSumber['total_online'] ?></div>
          <p class="text-[11px] text-sky-700 mt-1 mb-0">Website / Pelanggan</p>
        </div>
        <div class="bg-violet-50 border border-violet-200 rounded-xl p-4 text-center">
          <span class="text-xs text-violet-800 font-semibold block uppercase tracking-wider mb-1"><?= t('owner.source_offline') ?></span>
          <div class="text-2xl font-serif font-bold text-violet-900"><?= (int)$statSumber['total_offline'] ?></div>
          <p class="text-[11px] text-violet-700 mt-1 mb-0">Walk-in, WhatsApp, Telepon</p>
        </div>
      </div>
    </div>

    <!-- 9. Kategori Reservasi Kedaluwarsa (Read-only Pemilik) -->
    <div class="bg-stone-50 border border-stone-200 rounded-2xl p-5 mb-8 shadow-sm">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
        <div class="flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full bg-stone-400"></span>
          <h3 class="text-sm uppercase tracking-wider font-bold text-stone-700 mb-0">Reservasi Kedaluwarsa (<?= $tahun ?>)</h3>
        </div>
        <span class="text-xs text-stone-500 font-medium"><?= (int)$statKedaluwarsaTahun['total_kedaluwarsa'] ?> reservasi dibatalkan otomatis</span>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-white border border-stone-200 rounded-xl p-4">
          <span class="text-xs text-stone-500 font-semibold block uppercase tracking-wider mb-1">Jumlah Reservasi Kedaluwarsa</span>
          <div class="text-2xl font-serif font-bold text-stone-700"><?= (int)$statKedaluwarsaTahun['total_kedaluwarsa'] ?></div>
          <p class="text-[11px] text-stone-500 mt-1 mb-0">Unit kamar otomatis dilepas kembali ke kalender</p>
        </div>
        <div class="bg-white border border-stone-200 rounded-xl p-4">
          <span class="text-xs text-stone-500 font-semibold block uppercase tracking-wider mb-1">Estimasi Nilai Terlepas</span>
          <div class="text-2xl font-serif font-bold text-stone-700"><?= format_rupiah($statKedaluwarsaTahun['total_nominal']) ?></div>
          <p class="text-[11px] text-stone-500 mt-1 mb-0">Tidak masuk dalam perhitungan pemasukan aktual</p>
        </div>
      </div>
    </div>

  </div>

</main>

<!-- Skrip Interaktif: Update Asinkronus Filter Tahun & Metode -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  const tahunSelect = document.getElementById('tahunSelect');
  const chipButtons = document.querySelectorAll('.chip-metode');
  const loadingOverlay = document.getElementById('loadingOverlay');

  let currentTahun = parseInt(tahunSelect.value) || new Date().getFullYear();
  let currentMetode = '<?= e($metode) ?>';

  const namaBulanMap = <?= json_encode($namaBulan) ?>;
  const isEn = (window.MASAYU_LANG === 'en');

  function formatRp(num) {
    num = Math.round(Number(num) || 0);
    return 'Rp ' + num.toLocaleString('id-ID');
  }

  function formatJt(num) {
    num = Number(num) || 0;
    if (num <= 0) return '-';
    return (num / 1000000).toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' jt';
  }

  function fetchDashboardData() {
    if (loadingOverlay) loadingOverlay.classList.remove('hidden');

    const url = 'api/laporan-pemilik.php?tahun=' + encodeURIComponent(currentTahun) + '&metode=' + encodeURIComponent(currentMetode);

    fetch(url)
      .then(function(res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function(res) {
        if (!res || !res.ok) {
          throw new Error((res && res.pesan) ? res.pesan : 'Galat');
        }
        renderData(res);
      })
      .catch(function(err) {
        if (typeof showToast === 'function') {
          showToast(isEn ? 'Failed to update report data.' : 'Gagal memperbarui data laporan.', 'error');
        }
      })
      .finally(function() {
        if (loadingOverlay) loadingOverlay.classList.add('hidden');
      });
  }

  function renderData(data) {
    const ringkasan = data.ringkasan;
    const bulanan   = data.bulanan;
    const perTipe   = data.per_tipe;
    const tahun     = data.tahun;

    // 1. Kartu Ringkasan
    document.getElementById('cardBulanIni').textContent = formatRp(ringkasan.bulan_ini);
    document.getElementById('cardBulanLalu').textContent = formatRp(ringkasan.bulan_lalu);
    if (ringkasan.bulan_lalu > 0 && ringkasan.persen_bulan_ini !== null) {
      document.getElementById('cardBulanLaluSub').textContent = isEn 
        ? 'this month is currently ' + ringkasan.persen_bulan_ini + '% of last month'
        : 'bulan ini baru ' + ringkasan.persen_bulan_ini + '% dari bulan lalu';
    } else {
      document.getElementById('cardBulanLaluSub').textContent = isEn ? 'no revenue last month' : 'tidak ada pemasukan bulan lalu';
    }

    document.getElementById('cardTotalTahunLabel').textContent = (isEn ? 'Selected Year Total (' : 'Total Tahun Terpilih (') + tahun + ')';
    document.getElementById('cardTotalTahun').textContent = formatRp(ringkasan.total_tahun);
    document.getElementById('cardTotalTahunSub').textContent = ringkasan.total_transaksi + (isEn ? ' paid transactions' : ' transaksi lunas');

    document.getElementById('cardRataRata').textContent = formatRp(ringkasan.rata_rata_bulanan);
    document.getElementById('cardRataRataSub').textContent = (isEn ? 'divided by ' : 'dibagi ') + ringkasan.pembagi_bulan + (isEn ? ' elapsed months' : ' bulan berjalan');

    // 2. Batang Grafik
    let maxNominal = 0;
    bulanan.forEach(function(b) {
      if (b.total > maxNominal) maxNominal = b.total;
    });
    if (maxNominal === 0) maxNominal = 1;

    const chartContainer = document.getElementById('chartBarsContainer');
    if (chartContainer) {
      let barsHtml = '';
      bulanan.forEach(function(b) {
        const m = b.bulan;
        const total = b.total;
        let pct = (total / maxNominal) * 100;
        if (pct > 0 && pct < 4) pct = 4;

        const pBank   = total > 0 ? (b.transfer_bank / total) * 100 : 0;
        const pWallet = total > 0 ? (b.e_wallet / total) * 100 : 0;
        const pTunai  = total > 0 ? (b.tunai / total) * 100 : 0;
        const pLain   = total > 0 ? (b.lainnya / total) * 100 : 0;

        const isBlnIni = b.is_bulan_ini;
        const isDepan  = b.is_masa_depan;
        const namaBln  = isEn ? b.nama_bulan_en.substr(0, 3) : b.nama_bulan_id.substr(0, 3);
        const namaFull = isEn ? b.nama_bulan_en : b.nama_bulan_id;

        barsHtml += `
          <div class="flex-1 flex flex-col items-center h-full justify-end group relative" tabindex="0"
               aria-label="${namaFull}: Total ${formatRp(total)} (Bank: ${formatRp(b.transfer_bank)}, E-Wallet: ${formatRp(b.e_wallet)}, Tunai: ${formatRp(b.tunai)})">
            <span class="text-[10px] font-bold text-stone-700 mb-1 whitespace-nowrap opacity-90 group-hover:scale-110 transition-transform">
              ${formatJt(total)}
            </span>
            <div class="w-full max-w-[38px] rounded-t-md overflow-hidden flex flex-col-reverse transition-all duration-300 ${isBlnIni ? 'ring-2 ring-amber-500 ring-offset-1' : ''} ${isDepan ? 'bg-stone-100 opacity-40' : 'bg-stone-50'}"
                 style="height: ${isDepan ? '4px' : Math.max(4, pct) + '%'};">
              ${total > 0 ? `
                ${b.tunai > 0 ? `<div style="height: ${pTunai}%;" class="bg-sky-600 hover:bg-sky-500 transition-colors" title="Tunai: ${formatRp(b.tunai)}"></div>` : ''}
                ${b.e_wallet > 0 ? `<div style="height: ${pWallet}%;" class="bg-emerald-600 hover:bg-emerald-500 transition-colors" title="E-Wallet: ${formatRp(b.e_wallet)}"></div>` : ''}
                ${b.transfer_bank > 0 ? `<div style="height: ${pBank}%;" class="bg-amber-600 hover:bg-amber-500 transition-colors" title="Transfer Bank: ${formatRp(b.transfer_bank)}"></div>` : ''}
                ${b.lainnya > 0 ? `<div style="height: ${pLain}%;" class="bg-purple-600 hover:bg-purple-500 transition-colors" title="Lainnya: ${formatRp(b.lainnya)}"></div>` : ''}
              ` : ''}
            </div>
            <div class="mt-2 text-center">
              <span class="text-xs font-semibold block ${isBlnIni ? 'text-amber-800 font-bold' : 'text-stone-600'}">
                ${namaBln}
              </span>
              ${isBlnIni ? `<span class="inline-block text-[9px] font-bold text-amber-700 bg-amber-100 px-1 rounded-sm leading-tight mt-0.5 whitespace-nowrap">${isEn ? 'current' : 'berjalan'}</span>` : ''}
            </div>
            <div class="absolute bottom-full mb-8 hidden group-hover:block group-focus:block bg-stone-900 text-white text-[11px] rounded-lg p-2.5 shadow-xl z-30 pointer-events-none min-w-[150px]">
              <div class="font-bold border-b border-stone-700 pb-1 mb-1 text-amber-400">${namaFull} ${tahun}</div>
              <div class="flex justify-between py-0.5"><span>Transfer:</span><span class="font-mono">${formatRp(b.transfer_bank)}</span></div>
              <div class="flex justify-between py-0.5"><span>E-Wallet:</span><span class="font-mono">${formatRp(b.e_wallet)}</span></div>
              <div class="flex justify-between py-0.5"><span>Tunai:</span><span class="font-mono">${formatRp(b.tunai)}</span></div>
              <div class="flex justify-between py-0.5 border-t border-stone-700 pt-1 font-bold text-white"><span>Total:</span><span class="font-mono">${formatRp(total)}</span></div>
            </div>
          </div>
        `;
      });
      chartContainer.innerHTML = barsHtml;
    }

    // 3. Tabel Desktop (Terbaru di atas: Bulan 12 -> 1)
    const reversedBulanan = [...bulanan].reverse();
    const tableBody = document.getElementById('desktopTableBody');
    if (tableBody) {
      let tbodyHtml = '';
      reversedBulanan.forEach(function(b) {
        const isBlnIni = b.is_bulan_ini;
        const namaFull = isEn ? b.nama_bulan_en : b.nama_bulan_id;
        tbodyHtml += `
          <tr class="${isBlnIni ? 'bg-amber-50/70 font-semibold text-amber-950' : 'hover:bg-stone-50/50'}">
            <td class="p-3 font-medium">
              ${namaFull}
              ${isBlnIni ? `<span class="inline-block text-[10px] bg-amber-200 text-amber-900 px-1.5 py-0.2 rounded font-bold ml-1.5">${isEn ? 'current' : 'berjalan'}</span>` : ''}
            </td>
            <td class="p-3 text-center font-mono">${b.jumlah_transaksi}</td>
            <td class="p-3 text-right font-mono">${formatRp(b.transfer_bank)}</td>
            <td class="p-3 text-right font-mono">${formatRp(b.e_wallet)}</td>
            <td class="p-3 text-right font-mono">${formatRp(b.tunai)}</td>
            <td class="p-3 text-right font-bold text-stone-900 font-mono">${formatRp(b.total)}</td>
          </tr>
        `;
      });
      tableBody.innerHTML = tbodyHtml;
    }

    // Update tfoot desktop
    const tableFoot = document.getElementById('desktopTableFoot');
    if (tableFoot) {
      tableFoot.innerHTML = `
        <tr>
          <td class="p-3">${isEn ? 'Total Year ' + tahun : 'Total Tahun ' + tahun}</td>
          <td class="p-3 text-center font-mono">${ringkasan.total_transaksi}</td>
          <td class="p-3 text-right font-mono">${formatRp(ringkasan.total_transfer)}</td>
          <td class="p-3 text-right font-mono">${formatRp(ringkasan.total_ewallet)}</td>
          <td class="p-3 text-right font-mono">${formatRp(ringkasan.total_tunai)}</td>
          <td class="p-3 text-right font-bold text-amber-900 font-mono text-sm">${formatRp(ringkasan.total_tahun)}</td>
        </tr>
      `;
    }

    // 4. Kartu Mobile (<640px)
    const mobileContainer = document.getElementById('mobileCardsContainer');
    if (mobileContainer) {
      let mobHtml = '';
      reversedBulanan.forEach(function(b) {
        const isBlnIni = b.is_bulan_ini;
        const tot = b.total;
        const pBank   = tot > 0 ? (b.transfer_bank / tot) * 100 : 0;
        const pWallet = tot > 0 ? (b.e_wallet / tot) * 100 : 0;
        const pTunai  = tot > 0 ? (b.tunai / tot) * 100 : 0;
        const pLain   = tot > 0 ? (b.lainnya / tot) * 100 : 0;
        const namaFull = isEn ? b.nama_bulan_en : b.nama_bulan_id;

        mobHtml += `
          <div class="border ${isBlnIni ? 'border-amber-400 bg-amber-50/40' : 'border-stone-200 bg-white'} rounded-xl p-3.5 shadow-sm text-xs">
            <div class="flex items-center justify-between mb-2">
              <div class="font-bold text-stone-800 text-sm flex items-center gap-1.5">
                <span>${namaFull}</span>
                ${isBlnIni ? `<span class="text-[10px] bg-amber-200 text-amber-900 px-1.5 py-0.2 rounded font-bold">${isEn ? 'current' : 'berjalan'}</span>` : ''}
              </div>
              <div class="font-bold text-amber-900 font-mono text-sm">${formatRp(tot)}</div>
            </div>
            <div class="w-full h-2.5 bg-stone-100 rounded-full overflow-hidden flex mb-2.5">
              ${tot > 0 ? `
                ${b.transfer_bank > 0 ? `<div style="width: ${pBank}%;" class="bg-amber-600"></div>` : ''}
                ${b.e_wallet > 0 ? `<div style="width: ${pWallet}%;" class="bg-emerald-600"></div>` : ''}
                ${b.tunai > 0 ? `<div style="width: ${pTunai}%;" class="bg-sky-600"></div>` : ''}
                ${b.lainnya > 0 ? `<div style="width: ${pLain}%;" class="bg-purple-600"></div>` : ''}
              ` : ''}
            </div>
            <div class="text-[11px] text-stone-600 flex flex-wrap gap-x-2 gap-y-1">
              <span>Transfer: <strong class="text-stone-800 font-mono">${formatRp(b.transfer_bank)}</strong></span>
              <span>·</span>
              <span>E-Wallet: <strong class="text-stone-800 font-mono">${formatRp(b.e_wallet)}</strong></span>
              <span>·</span>
              <span>Tunai: <strong class="text-stone-800 font-mono">${formatRp(b.tunai)}</strong></span>
              <span>·</span>
              <span class="font-medium">${b.jumlah_transaksi} transaksi</span>
            </div>
          </div>
        `;
      });
      mobileContainer.innerHTML = mobHtml;
    }

    // 5. Villa Breakdown
    const villaContainer = document.getElementById('villaBreakdownContainer');
    if (villaContainer && perTipe && perTipe.data) {
      let vHtml = '';
      perTipe.data.forEach(function(v) {
        vHtml += `
          <div>
            <div class="flex items-center justify-between text-xs font-semibold mb-1">
              <span class="text-stone-800">${v.nama_tipe}</span>
              <span class="text-stone-600 font-mono">${formatRp(v.total_nominal)} (${v.persentase}%)</span>
            </div>
            <div class="w-full h-2.5 bg-stone-100 rounded-full overflow-hidden">
              <div class="h-full bg-amber-700 rounded-full transition-all duration-500" style="width: ${v.persentase}%;"></div>
            </div>
            <div class="text-[10px] text-stone-400 mt-0.5">${v.jumlah_transaksi} ${isEn ? 'paid transactions' : 'transaksi lunas'}</div>
          </div>
        `;
      });
      villaContainer.innerHTML = vHtml;
    }
  }

  // Event Listener: Ubah Tahun
  tahunSelect.addEventListener('change', function() {
    currentTahun = parseInt(this.value);
    fetchDashboardData();
  });

  // Event Listener: Klik Chip Metode
  chipButtons.forEach(function(btn) {
    btn.addEventListener('click', function() {
      const selectedMetode = this.getAttribute('data-metode');
      if (selectedMetode === currentMetode) return;

      currentMetode = selectedMetode;

      chipButtons.forEach(function(b) {
        b.className = 'chip-metode px-3 py-1.5 font-medium rounded-md transition-colors text-stone-600 hover:text-stone-900';
      });
      this.className = 'chip-metode px-3 py-1.5 font-medium rounded-md transition-colors bg-white text-stone-900 font-bold shadow-sm';

      fetchDashboardData();
    });
  });
});
</script>

<?php require_once __DIR__ . '/../includes/layout/foot.php'; ?>
