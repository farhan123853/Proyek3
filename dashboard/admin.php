<?php
/**
 * Dashboard Administrator — Villa Joglo Masayu
 * Kebutuhan: Tahap 8 (Bagian 2)
 * - Tab 1: Manajemen Akun Staf (Pengelola & Pemilik) [Fungsi akun tidak berubah]
 * - Tab 2: Transaksi Pembayaran (Daftar pembayaran, filter status & sumber, pencarian, paginasi,
 *   penanda 'perlu ditinjau' untuk status ganda, tombol 'Sinkronkan status' per transaksi Midtrans,
 *   dan tombol 'Batalkan catatan tunai' dengan dialog alasan wajib).
 * - Badge Mode: Sandbox / Production dan peringatan jika Server Key belum dikonfigurasi.
 * - Tanpa pernah menampilkan Server Key atau data sensitif kartu.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/laporan.php';

// Hak akses: murni hanya untuk Admin
require_role('admin', 'login.php');

$pdo = get_db();
$currentAdminId = current_user_id();

$formErrors = [];
$namaBaru   = trim($_POST['nama'] ?? '');
$emailBaru  = trim($_POST['email'] ?? '');
$kontakBaru = trim($_POST['kontak'] ?? '');
$peranBaru  = trim($_POST['peran'] ?? 'pengelola');

// Tab aktif: 'transaksi' (default jika ada filter) atau 'staff'
$activeTab = trim($_GET['tab'] ?? 'transaksi');
if (!in_array($activeTab, ['transaksi', 'staff'], true)) {
    $activeTab = 'transaksi';
}

// ----------------------------------------------------
// 1. TANGANI FORM POST MANAJEMEN STAF (TIDAK BERUBAH)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    // A. Tambah Akun Staf Baru (Pengelola / Pemilik)
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
            redirect('dashboard/admin.php?tab=staff');
        } else {
            $activeTab = 'staff';
        }
    }

    // B. Toggle Status Akun (Aktif / Nonaktif)
    if ($action === 'toggle_status') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        $newStatus = trim($_POST['new_status'] ?? '');

        if ($targetId === $currentAdminId) {
            flash_set('danger', 'Pelanggaran keamanan: Anda tidak dapat menonaktifkan akun Admin Anda sendiri!');
            redirect('dashboard/admin.php?tab=staff');
        }

        if (in_array($newStatus, ['aktif', 'nonaktif'], true) && $targetId > 0) {
            $stmtToggle = $pdo->prepare("UPDATE `pengguna` SET `status_akun` = ? WHERE `id_pengguna` = ?");
            $stmtToggle->execute([$newStatus, $targetId]);

            flash_set('success', "Status akun #{$targetId} berhasil diubah menjadi: {$newStatus}.");
            redirect('dashboard/admin.php?tab=staff');
        }
    }

    // C. Ubah Peran Pengguna
    if ($action === 'change_role') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        $targetRole = trim($_POST['target_role'] ?? '');

        if ($targetId === $currentAdminId && $targetRole !== 'admin') {
            flash_set('danger', 'Anda tidak dapat menghapus hak akses Admin dari akun Anda sendiri.');
            redirect('dashboard/admin.php?tab=staff');
        }

        $allowedRoles = ['pelanggan', 'pengelola', 'pemilik', 'admin'];
        if (in_array($targetRole, $allowedRoles, true) && $targetId > 0) {
            $stmtRole = $pdo->prepare("UPDATE `pengguna` SET `peran` = ? WHERE `id_pengguna` = ?");
            $stmtRole->execute([$targetRole, $targetId]);

            flash_set('success', "Peran akun #{$targetId} berhasil diperbarui menjadi {$targetRole}.");
            redirect('dashboard/admin.php?tab=staff');
        }
    }
}

// ----------------------------------------------------
// 2. QUERY TAB TRANSAKSI PEMBAYARAN
// ----------------------------------------------------
$filterStatus = trim($_GET['status'] ?? '');
$filterSumber = trim($_GET['sumber'] ?? '');
$searchQuery  = trim($_GET['q'] ?? '');

$allowedStatusList = ['menunggu', 'lunas', 'gagal', 'kedaluwarsa', 'ganda', 'dibatalkan'];
$allowedSumberList = ['midtrans', 'manual'];

$whereTrx = [];
$paramsTrx = [];

if (in_array($filterStatus, $allowedStatusList, true)) {
    $whereTrx[] = "p.status = ?";
    $paramsTrx[] = $filterStatus;
}

if (in_array($filterSumber, $allowedSumberList, true)) {
    $whereTrx[] = "p.sumber = ?";
    $paramsTrx[] = $filterSumber;
}

if (!empty($searchQuery)) {
    $cleanQ = ltrim($searchQuery, '#rsvRSV- ');
    $whereTrx[] = "(p.order_id LIKE ? OR p.id_reservasi = ? OR p.kode_pembayaran LIKE ? OR p.bank_atau_toko LIKE ? OR pel.nama LIKE ? OR r.nama_tamu LIKE ?)";
    $paramsTrx[] = '%' . $searchQuery . '%';
    $paramsTrx[] = is_numeric($cleanQ) ? (int)$cleanQ : 0;
    $paramsTrx[] = '%' . $searchQuery . '%';
    $paramsTrx[] = '%' . $searchQuery . '%';
    $paramsTrx[] = '%' . $searchQuery . '%';
    $paramsTrx[] = '%' . $searchQuery . '%';
}

$whereTrxSql = !empty($whereTrx) ? 'WHERE ' . implode(' AND ', $whereTrx) : '';

// Hitung total transaksi untuk paginasi
$sqlCount = "SELECT COUNT(*) FROM pembayaran p {$whereTrxSql}";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($paramsTrx);
$totalRecords = (int)$stmtCount->fetchColumn();

// Paginasi: 12 baris per halaman
$perPage = 12;
$totalPages = max(1, (int)ceil($totalRecords / $perPage));
$page = max(1, min((int)($_GET['p'] ?? 1), $totalPages));
$offset = ($page - 1) * $perPage;

$sqlTrx = "
    SELECT 
        p.*,
        r.id_tipe, r.tanggal_checkin, r.tanggal_checkout, r.status AS status_reservasi,
        v.nama AS nama_villa,
        COALESCE(pel.nama, r.nama_tamu) AS nama_pelanggan,
        COALESCE(pel.email, '-') AS email_pelanggan,
        staf.nama AS nama_staf,
        batal_staf.nama AS nama_pembatal
    FROM pembayaran p
    JOIN reservasi r ON p.id_reservasi = r.id_reservasi
    JOIN tipe_villa v ON r.id_tipe = v.id_tipe
    LEFT JOIN pengguna pel ON r.id_pelanggan = pel.id_pengguna
    LEFT JOIN pengguna staf ON p.dicatat_oleh = staf.id_pengguna
    LEFT JOIN pengguna batal_staf ON p.dibatalkan_oleh = batal_staf.id_pengguna
    {$whereTrxSql}
    ORDER BY p.id_pembayaran DESC
    LIMIT {$perPage} OFFSET {$offset}
";

$stmtTrx = $pdo->prepare($sqlTrx);
$stmtTrx->execute($paramsTrx);
$transaksiList = $stmtTrx->fetchAll();

// Ambil seluruh daftar pengguna untuk Tab Staf
$stmtAllUsers = $pdo->query("SELECT * FROM `pengguna` ORDER BY `id_pengguna` ASC");
$usersList = $stmtAllUsers->fetchAll();

// Cek Konfigurasi Server Key & Client Key Midtrans
$kunciMidtrans = midtrans_kunci_siap();
$isKeyEmpty = !$kunciMidtrans['siap'];
$isProduction = $kunciMidtrans['is_production'];

$pageTitle = t('dashboard.admin_title') . ' — Villa Joglo Masayu';
$pageDesc  = 'Manajemen akun staf internal, pengawasan transaksi pembayaran, dan audit sistem.';
$bodyClass = 'page-villa';

require_once __DIR__ . '/../includes/layout/head.php';
?>

<main class="container mx-auto px-4" style="padding-top: calc(var(--header-h-scrolled) + 2rem); padding-bottom: 5rem; max-width: 1200px;">

  <!-- Header Dashboard Administrator -->
  <div class="flex flex-col md:flex-row md:items-end justify-between mb-6 gap-3">
    <div>
      <div class="flex items-center gap-2 mb-1">
        <p class="text-xs uppercase tracking-widest font-semibold text-amber-800 mb-0"><?= t('dashboard.admin_title') ?></p>
        <span class="text-stone-300">·</span>
        <!-- Badge Mode Midtrans -->
        <?php if ($isProduction): ?>
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            <?= t('admin.mode_production') ?>
          </span>
        <?php else: ?>
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
            <?= t('admin.mode_sandbox') ?>
          </span>
        <?php endif; ?>
      </div>
      <h1 class="text-2xl md:text-3xl font-serif text-stone-900 mb-1"><?= t('dashboard.admin_title') ?></h1>
      <p class="text-sm text-stone-600 mb-0">Kelola akun pengguna, pantau seluruh transaksi pembayaran, dan kendalikan status sistem.</p>
    </div>
  </div>

  <!-- Peringatan Bila Kunci Midtrans Belum Lengkap -->
  <?php if ($isKeyEmpty): ?>
    <div class="mb-6 p-4 bg-rose-50 border border-rose-300 rounded-xl text-xs text-rose-900 flex items-start gap-3 shadow-sm" role="alert">
      <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
      <div>
        <strong class="font-bold block mb-0.5"><?= t('admin.warning_keys_incomplete') ?>: <?= t('admin.warning_no_server_key') ?></strong>
        <span>Pastikan konstanta <code>MIDTRANS_SERVER_KEY</code> dan <code>MIDTRANS_CLIENT_KEY</code> telah diisi dengan nilai yang valid pada berkas <code>config.php</code>.</span>
        <?php if (!$kunciMidtrans['server_siap']): ?>
          <p class="mt-1 mb-0 text-rose-700">· Server Key: <?= e($kunciMidtrans['server_alasan']) ?></p>
        <?php endif; ?>
        <?php if (!$kunciMidtrans['client_siap']): ?>
          <p class="mt-0.5 mb-0 text-rose-700">· Client Key: <?= e($kunciMidtrans['client_alasan']) ?></p>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Tab Navigasi: Transaksi Pembayaran & Manajemen Akun Staf -->
  <div class="border-b border-stone-200 mb-6 flex items-center gap-2">
    <a href="dashboard/admin.php?tab=transaksi" class="py-2.5 px-4 text-sm font-semibold border-b-2 transition-colors inline-flex items-center gap-2 <?= $activeTab === 'transaksi' ? 'border-amber-800 text-amber-900' : 'border-transparent text-stone-500 hover:text-stone-800' ?>">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
      <span><?= t('admin.tab_transactions') ?></span>
      <span class="text-xs font-mono px-2 py-0.5 bg-stone-100 rounded-full text-stone-700"><?= $totalRecords ?></span>
    </a>
    <a href="dashboard/admin.php?tab=staff" class="py-2.5 px-4 text-sm font-semibold border-b-2 transition-colors inline-flex items-center gap-2 <?= $activeTab === 'staff' ? 'border-amber-800 text-amber-900' : 'border-transparent text-stone-500 hover:text-stone-800' ?>">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
      <span><?= t('admin.tab_staff') ?></span>
      <span class="text-xs font-mono px-2 py-0.5 bg-stone-100 rounded-full text-stone-700"><?= count($usersList) ?></span>
    </a>
  </div>

  <?php if ($activeTab === 'transaksi'): ?>
    <!-- ========================================== -->
    <!-- TAB 1: DAFTAR TRANSAKSI PEMBAYARAN        -->
    <!-- ========================================== -->

    <!-- Bar Filter & Pencarian Transaksi -->
    <div class="bg-white border border-stone-200 rounded-xl p-4 mb-6 shadow-sm">
      <form method="GET" action="dashboard/admin.php" class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 text-xs">
        <input type="hidden" name="tab" value="transaksi" />

        <div class="flex items-center gap-3 flex-wrap">
          <!-- Filter Status -->
          <div class="flex items-center gap-1.5">
            <label class="font-semibold text-stone-600"><?= t('admin.filter_status') ?>:</label>
            <select name="status" class="form-control text-xs py-1.5 px-3 rounded-lg border-stone-300 w-auto" onchange="this.form.submit()">
              <option value="">Semua Status</option>
              <?php foreach ($allowedStatusList as $st): ?>
                <option value="<?= $st ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Filter Sumber -->
          <div class="flex items-center gap-1.5">
            <label class="font-semibold text-stone-600"><?= t('admin.filter_source') ?>:</label>
            <select name="sumber" class="form-control text-xs py-1.5 px-3 rounded-lg border-stone-300 w-auto" onchange="this.form.submit()">
              <option value=""><?= t('admin.source_all') ?></option>
              <option value="midtrans" <?= $filterSumber === 'midtrans' ? 'selected' : '' ?>><?= t('admin.source_midtrans') ?></option>
              <option value="manual" <?= $filterSumber === 'manual' ? 'selected' : '' ?>><?= t('admin.source_manual') ?></option>
            </select>
          </div>

          <?php if ($filterStatus || $filterSumber || $searchQuery): ?>
            <a href="dashboard/admin.php?tab=transaksi" class="text-amber-800 hover:underline font-semibold ml-1">Reset</a>
          <?php endif; ?>
        </div>

        <!-- Input Pencarian -->
        <div class="flex items-center gap-2">
          <input type="text" name="q" value="<?= e($searchQuery) ?>" placeholder="<?= t('admin.search_placeholder') ?>"
                 class="form-control text-xs py-1.5 px-3 rounded-lg border-stone-300 w-full sm:w-64" />
          <button type="submit" class="btn btn-sm btn-gold text-xs py-1.5 px-3">Cari</button>
        </div>
      </form>
    </div>

    <!-- Tabel Transaksi Desktop (hidden md:block) -->
    <div class="hidden md:block bg-white border border-stone-200 rounded-xl overflow-hidden shadow-sm mb-6">
      <table class="w-full text-left text-xs border-collapse">
        <thead class="bg-stone-50 text-stone-700 font-semibold border-b border-stone-200">
          <tr>
            <th class="p-3"><?= t('admin.col_order_id') ?></th>
            <th class="p-3"><?= t('admin.col_reservation') ?></th>
            <th class="p-3"><?= t('payment.bank_or_store') ?></th>
            <th class="p-3"><?= t('payment.va_code_label') ?></th>
            <th class="p-3"><?= t('admin.col_amount') ?></th>
            <th class="p-3"><?= t('admin.col_source') ?></th>
            <th class="p-3"><?= t('admin.col_method') ?></th>
            <th class="p-3"><?= t('admin.col_status') ?></th>
            <th class="p-3"><?= t('admin.col_time') ?></th>
            <th class="p-3"><?= t('admin.col_recorded_by') ?></th>
            <th class="p-3 text-right"><?= t('admin.col_actions') ?></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-stone-100 text-stone-700">
          <?php if (empty($transaksiList)): ?>
            <tr>
              <td colspan="11" class="p-8 text-center text-stone-500">
                <?= t('admin.empty_transactions') ?>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($transaksiList as $p): ?>
              <?php
                $isGanda = ($p['status'] === 'ganda');
                $isManualLunas = ($p['sumber'] === 'manual' && $p['status'] === 'lunas');
                $isMidtrans = ($p['sumber'] === 'midtrans');
                $waktu = $p['dibayar_pada'] ?: $p['dibuat_pada'];
              ?>
              <tr class="<?= $isGanda ? 'bg-purple-50/60' : 'hover:bg-stone-50/50' ?>">
                <!-- Order ID -->
                <td class="p-3 font-mono font-bold text-stone-800">
                  <span><?= e($p['order_id']) ?></span>
                  <?php if ($isGanda): ?>
                    <div class="mt-0.5">
                      <span class="inline-block bg-purple-100 text-purple-800 border border-purple-300 text-[10px] font-bold px-1.5 py-0.2 rounded">
                        <?= t('admin.badge_review') ?>
                      </span>
                    </div>
                  <?php endif; ?>
                </td>

                <!-- Reservasi -->
                <td class="p-3">
                  <span class="font-mono font-bold text-amber-900 block">#RSV-<?= $p['id_reservasi'] ?></span>
                  <span class="text-stone-800 font-medium block"><?= e($p['nama_pelanggan']) ?></span>
                  <span class="text-[11px] text-stone-400"><?= e($p['nama_villa']) ?></span>
                </td>

                <!-- Bank / Toko -->
                <td class="p-3 text-stone-800 font-medium whitespace-nowrap">
                  <?= e(format_bank_atau_toko($p['bank_atau_toko'] ?? null)) ?>
                </td>

                <!-- Kode VA (Unmasked untuk Admin) -->
                <td class="p-3 font-mono whitespace-nowrap">
                  <?php if (!empty($p['kode_pembayaran'])): ?>
                    <span class="font-bold text-stone-900 select-all block"><?= e(format_nomor_va($p['kode_pembayaran'], $p['bank_atau_toko'])) ?></span>
                    <?php if (strpos((string)$p['catatan'], 'duplikat kode VA') !== false): ?>
                      <span class="inline-block mt-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
                        <?= t('payment.badge_review_dup_va') ?>
                      </span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-stone-400">-</span>
                  <?php endif; ?>
                </td>

                <!-- Nominal -->
                <td class="p-3 font-semibold font-mono text-stone-900">
                  <?= format_rupiah($p['jumlah']) ?>
                </td>

                <!-- Sumber -->
                <td class="p-3">
                  <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold <?= $p['sumber'] === 'midtrans' ? 'bg-blue-50 text-blue-800 border border-blue-200' : 'bg-emerald-50 text-emerald-800 border border-emerald-200' ?>">
                    <?= $p['sumber'] === 'midtrans' ? 'Midtrans' : 'Manual' ?>
                  </span>
                </td>

                <!-- Metode -->
                <td class="p-3">
                  <span class="block font-medium"><?= format_metode_pembayaran($p['metode_kelompok']) ?></span>
                  <?php if (!empty($p['metode_detail']) && $p['metode_detail'] !== 'cash'): ?>
                    <span class="text-[10px] text-stone-400 font-mono"><?= e($p['metode_detail']) ?></span>
                  <?php endif; ?>
                </td>

                <!-- Status Badge (Mendukung ganda & dibatalkan untuk staf) -->
                <td class="p-3">
                  <?= render_badge_pembayaran($p['status'], true) ?>
                  <?php if ($p['status'] === 'dibatalkan' && !empty($p['alasan_batal'])): ?>
                    <div class="text-[10px] text-stone-400 mt-1 max-w-[160px] truncate" title="<?= e($p['alasan_batal']) ?>">
                      Alasan: <?= e($p['alasan_batal']) ?>
                    </div>
                  <?php endif; ?>
                </td>

                <!-- Waktu -->
                <td class="p-3 whitespace-nowrap text-stone-600">
                  <?= date('d/m/Y H:i', strtotime($waktu)) ?>
                </td>

                <!-- Dicatat Oleh -->
                <td class="p-3 text-[11px] text-stone-600">
                  <?php if (!empty($p['nama_staf'])): ?>
                    <span><?= e($p['nama_staf']) ?></span>
                  <?php elseif ($p['sumber'] === 'midtrans'): ?>
                    <span class="text-stone-400">Sistem (Webhook)</span>
                  <?php else: ?>
                    <span class="text-stone-400">-</span>
                  <?php endif; ?>
                </td>

                <!-- Aksi -->
                <td class="p-3 text-right whitespace-nowrap">
                  <div class="inline-flex items-center gap-1.5 justify-end">
                    <!-- Tombol Lihat Struk -->
                    <a href="struk-pembayaran.php?id=<?= (int)$p['id_reservasi'] ?>" target="_blank" rel="noopener" class="btn btn-sm btn-ghost border border-stone-300 text-[11px] py-1 px-2.5 rounded inline-flex items-center gap-1" title="<?= t('payment.btn_view_receipt') ?>">
                      <svg class="w-3.5 h-3.5 text-stone-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                      <span><?= t('payment.btn_view_receipt') ?></span>
                    </a>

                    <?php if ($isMidtrans): ?>
                      <button type="button" class="btn btn-sm btn-ghost border border-stone-300 text-[11px] py-1 px-2.5 rounded btn-sync-midtrans"
                              data-order-id="<?= e($p['order_id']) ?>">
                        <?= t('admin.btn_sync') ?>
                      </button>
                    <?php endif; ?>

                    <?php if ($isManualLunas): ?>
                      <button type="button" class="btn btn-sm btn-danger text-[11px] py-1 px-2.5 rounded btn-open-cancel-modal"
                              data-id="<?= $p['id_pembayaran'] ?>"
                              data-order="<?= e($p['order_id']) ?>"
                              data-nominal="<?= format_rupiah($p['jumlah']) ?>"
                              data-rsv="<?= $p['id_reservasi'] ?>">
                        <?= t('admin.btn_cancel_cash') ?>
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Tampilan Kartu Mobile (<768px: block md:hidden) -->
    <div class="block md:hidden space-y-3 mb-6">
      <?php if (empty($transaksiList)): ?>
        <div class="bg-white border border-stone-200 rounded-xl p-8 text-center text-stone-500 text-xs">
          <?= t('admin.empty_transactions') ?>
        </div>
      <?php else: ?>
        <?php foreach ($transaksiList as $p): ?>
          <?php
            $isGanda = ($p['status'] === 'ganda');
            $isManualLunas = ($p['sumber'] === 'manual' && $p['status'] === 'lunas');
            $isMidtrans = ($p['sumber'] === 'midtrans');
            $waktu = $p['dibayar_pada'] ?: $p['dibuat_pada'];
          ?>
          <div class="bg-white border <?= $isGanda ? 'border-purple-300 ring-2 ring-purple-100' : 'border-stone-200' ?> rounded-xl p-4 shadow-sm text-xs">
            <div class="flex items-start justify-between gap-2 mb-2 pb-2 border-b border-stone-100">
              <div>
                <span class="font-mono font-bold text-stone-900 block"><?= e($p['order_id']) ?></span>
                <span class="text-stone-500 text-[11px]">#RSV-<?= $p['id_reservasi'] ?> · <?= e($p['nama_pelanggan']) ?></span>
              </div>
              <div class="text-right">
                <span class="font-bold text-stone-900 block font-mono"><?= format_rupiah($p['jumlah']) ?></span>
                <span class="text-[10px] text-stone-400"><?= date('d/m/Y H:i', strtotime($waktu)) ?></span>
              </div>
            </div>

            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-1.5">
                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold <?= $p['sumber'] === 'midtrans' ? 'bg-blue-50 text-blue-800 border border-blue-200' : 'bg-emerald-50 text-emerald-800 border border-emerald-200' ?>">
                  <?= $p['sumber'] === 'midtrans' ? 'Midtrans' : 'Manual' ?>
                </span>
                <span class="text-stone-700"><?= format_metode_pembayaran($p['metode_kelompok']) ?></span>
              </div>
              <div>
                <?= render_badge_pembayaran($p['status'], true) ?>
              </div>
            </div>

            <?php if (!empty($p['kode_pembayaran'])): ?>
              <div class="mb-2 p-2 bg-stone-50 border border-stone-200 rounded font-mono text-xs flex items-center justify-between">
                <div>
                  <span class="text-stone-500 font-sans block text-[10px]"><?= e(format_bank_atau_toko($p['bank_atau_toko'] ?? null)) ?>:</span>
                  <span class="font-bold text-stone-900"><?= e(format_nomor_va($p['kode_pembayaran'], $p['bank_atau_toko'])) ?></span>
                </div>
                <?php if (strpos((string)$p['catatan'], 'duplikat kode VA') !== false): ?>
                  <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300"><?= t('payment.badge_review_dup_va') ?></span>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if ($isGanda): ?>
              <div class="mb-2 p-2 bg-purple-50 text-purple-900 border border-purple-200 rounded text-[11px]">
                <strong><?= t('admin.badge_review') ?>:</strong> Terjadi pembayaran ganda pada reservasi ini. Silakan periksa catatan manual atau rincian transaksi Midtrans.
              </div>
            <?php endif; ?>

            <?php if ($p['status'] === 'dibatalkan' && !empty($p['alasan_batal'])): ?>
              <div class="mb-2 text-[11px] text-stone-500 bg-stone-50 p-2 rounded">
                Dibatalkan oleh <?= e($p['nama_pembatal'] ?: 'Admin') ?>: "<?= e($p['alasan_batal']) ?>"
              </div>
            <?php endif; ?>

            <!-- Aksi Mobile -->
            <div class="flex items-center justify-end gap-2 pt-2 border-t border-stone-100 flex-wrap">
              <a href="struk-pembayaran.php?id=<?= (int)$p['id_reservasi'] ?>" target="_blank" rel="noopener" class="btn btn-sm btn-ghost border border-stone-300 text-xs py-2 px-3 rounded inline-flex items-center gap-1.5 min-h-[44px]">
                <svg class="w-4 h-4 text-stone-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span><?= t('payment.btn_view_receipt') ?></span>
              </a>

              <?php if ($isMidtrans): ?>
                <button type="button" class="btn btn-sm btn-ghost border border-stone-300 text-xs py-1 px-3 rounded btn-sync-midtrans"
                        data-order-id="<?= e($p['order_id']) ?>">
                  <?= t('admin.btn_sync') ?>
                </button>
              <?php endif; ?>

              <?php if ($isManualLunas): ?>
                <button type="button" class="btn btn-sm btn-danger text-xs py-1 px-3 rounded btn-open-cancel-modal"
                        data-id="<?= $p['id_pembayaran'] ?>"
                        data-order="<?= e($p['order_id']) ?>"
                        data-nominal="<?= format_rupiah($p['jumlah']) ?>"
                        data-rsv="<?= $p['id_reservasi'] ?>">
                  <?= t('admin.btn_cancel_cash') ?>
                </button>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Paginasi Transaksi -->
    <?php if ($totalPages > 1): ?>
      <div class="flex items-center justify-between text-xs text-stone-600 bg-white border border-stone-200 rounded-xl p-3 shadow-sm">
        <div>
          Menampilkan halaman <strong><?= $page ?></strong> dari <strong><?= $totalPages ?></strong> (Total <?= $totalRecords ?> transaksi)
        </div>
        <div class="flex items-center gap-1.5">
          <?php if ($page > 1): ?>
            <a href="dashboard/admin.php?tab=transaksi&p=<?= $page - 1 ?>&status=<?= urlencode($filterStatus) ?>&sumber=<?= urlencode($filterSumber) ?>&q=<?= urlencode($searchQuery) ?>" class="btn btn-sm btn-ghost border border-stone-300 py-1 px-2.5">
              &larr; Sebelumnya
            </a>
          <?php endif; ?>

          <?php if ($page < $totalPages): ?>
            <a href="dashboard/admin.php?tab=transaksi&p=<?= $page + 1 ?>&status=<?= urlencode($filterStatus) ?>&sumber=<?= urlencode($filterSumber) ?>&q=<?= urlencode($searchQuery) ?>" class="btn btn-sm btn-ghost border border-stone-300 py-1 px-2.5">
              Berikutnya &rarr;
            </a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

  <?php else: ?>
    <!-- ========================================== -->
    <!-- TAB 2: MANAJEMEN AKUN STAF (TIDAK BERUBAH) -->
    <!-- ========================================== -->

    <!-- Form Tambah Akun Staf Baru -->
    <div class="bg-white border border-stone-200 rounded-2xl p-6 shadow-sm mb-8">
      <h2 class="text-lg font-serif font-bold text-stone-900 mb-1">+ Tambah Akun Staf Baru</h2>
      <p class="text-xs text-stone-500 mb-6">Formulir resmi untuk membuat akun staf internal (Pengelola atau Pemilik Villa).</p>

      <form method="POST" action="dashboard/admin.php?tab=staff" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_staff" />

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
          <div>
            <label class="block text-xs font-semibold text-stone-700 mb-1" for="nama">Nama Lengkap <span class="text-rose-600">*</span></label>
            <input type="text" id="nama" name="nama" class="form-control text-xs rounded-lg border-stone-300 w-full" value="<?= e($namaBaru) ?>" required />
            <?php if (isset($formErrors['nama'])): ?><div class="text-[11px] text-rose-600 mt-1"><?= e($formErrors['nama']) ?></div><?php endif; ?>
          </div>

          <div>
            <label class="block text-xs font-semibold text-stone-700 mb-1" for="email">Alamat Email <span class="text-rose-600">*</span></label>
            <input type="email" id="email" name="email" class="form-control text-xs rounded-lg border-stone-300 w-full" value="<?= e($emailBaru) ?>" required />
            <?php if (isset($formErrors['email'])): ?><div class="text-[11px] text-rose-600 mt-1"><?= e($formErrors['email']) ?></div><?php endif; ?>
          </div>

          <div>
            <label class="block text-xs font-semibold text-stone-700 mb-1" for="kontak">Kontak / No. Telepon <span class="text-rose-600">*</span></label>
            <input type="tel" id="kontak" name="kontak" class="form-control text-xs rounded-lg border-stone-300 w-full" value="<?= e($kontakBaru) ?>" required />
            <?php if (isset($formErrors['kontak'])): ?><div class="text-[11px] text-rose-600 mt-1"><?= e($formErrors['kontak']) ?></div><?php endif; ?>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
          <div>
            <label class="block text-xs font-semibold text-stone-700 mb-1" for="peran">Pilih Peran <span class="text-rose-600">*</span></label>
            <select id="peran" name="peran" class="form-control text-xs rounded-lg border-stone-300 w-full" required>
              <option value="pengelola" <?= $peranBaru === 'pengelola' ? 'selected' : '' ?>>Pengelola Villa</option>
              <option value="pemilik" <?= $peranBaru === 'pemilik' ? 'selected' : '' ?>>Pemilik Villa (Owner)</option>
            </select>
            <?php if (isset($formErrors['peran'])): ?><div class="text-[11px] text-rose-600 mt-1"><?= e($formErrors['peran']) ?></div><?php endif; ?>
          </div>

          <div>
            <label class="block text-xs font-semibold text-stone-700 mb-1" for="password">Kata Sandi Awal <span class="text-rose-600">*</span></label>
            <input type="password" id="password" name="password" class="form-control text-xs rounded-lg border-stone-300 w-full" required autocomplete="new-password" />
            <div class="text-[11px] text-stone-400 mt-1">Minimal 6 karakter.</div>
            <?php if (isset($formErrors['password'])): ?><div class="text-[11px] text-rose-600 mt-1"><?= e($formErrors['password']) ?></div><?php endif; ?>
          </div>
        </div>

        <div>
          <button type="submit" class="btn btn-gold text-xs py-2 px-5 font-bold shadow-sm">Buat Akun Staf</button>
        </div>
      </form>
    </div>

    <!-- Tabel Seluruh Pengguna Sistem -->
    <div class="bg-white border border-stone-200 rounded-2xl overflow-hidden shadow-sm">
      <div class="p-5 border-b border-stone-100 flex items-center justify-between">
        <div>
          <h2 class="text-lg font-serif font-bold text-stone-900 mb-0.5">Daftar Seluruh Pengguna Sistem</h2>
          <p class="text-xs text-stone-500 mb-0">Total <?= count($usersList) ?> akun terdaftar pada sistem.</p>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-stone-50 text-stone-700 font-semibold border-b border-stone-200">
            <tr>
              <th class="p-3">ID</th>
              <th class="p-3">Nama & Email</th>
              <th class="p-3">Kontak</th>
              <th class="p-3">Peran</th>
              <th class="p-3">Status Akun</th>
              <th class="p-3">Terdaftar</th>
              <th class="p-3 text-right">Tindakan Administrator</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100 text-stone-700">
            <?php foreach ($usersList as $u): ?>
              <tr class="hover:bg-stone-50/50">
                <td class="p-3 font-mono font-bold">#<?= e($u['id_pengguna']) ?></td>
                <td class="p-3">
                  <span class="font-bold text-stone-900 block"><?= e($u['nama']) ?></span>
                  <span class="text-stone-400 text-[11px]"><?= e($u['email']) ?></span>
                  <?php if ((int)$u['id_pengguna'] === $currentAdminId): ?>
                    <span class="inline-block text-[10px] bg-amber-100 text-amber-800 px-1.5 py-0.2 rounded font-bold ml-1">Akun Anda</span>
                  <?php endif; ?>
                </td>
                <td class="p-3 text-stone-600"><?= e($u['kontak']) ?></td>
                <td class="p-3">
                  <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider <?= $u['peran'] === 'admin' ? 'bg-purple-100 text-purple-800' : ($u['peran'] === 'pemilik' ? 'bg-amber-100 text-amber-800' : ($u['peran'] === 'pengelola' ? 'bg-blue-100 text-blue-800' : 'bg-stone-100 text-stone-700')) ?>">
                    <?= e($u['peran']) ?>
                  </span>
                </td>
                <td class="p-3">
                  <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold <?= $u['status_akun'] === 'aktif' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200' ?>">
                    <?= e($u['status_akun']) ?>
                  </span>
                </td>
                <td class="p-3 text-stone-500 whitespace-nowrap">
                  <?= date('d/m/Y', strtotime($u['dibuat_pada'])) ?>
                </td>
                <td class="p-3 text-right whitespace-nowrap">
                  <?php if ((int)$u['id_pengguna'] !== $currentAdminId): ?>
                    <div class="inline-flex items-center gap-2 justify-end">
                      <!-- Toggle Status Aktif / Nonaktif -->
                      <form method="POST" action="dashboard/admin.php?tab=staff" style="display: inline;" onsubmit="return confirm('Ubah status akun ini menjadi <?= $u['status_akun'] === 'aktif' ? 'NONAKTIF' : 'AKTIF' ?>?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle_status" />
                        <input type="hidden" name="target_id" value="<?= e($u['id_pengguna']) ?>" />
                        <input type="hidden" name="new_status" value="<?= $u['status_akun'] === 'aktif' ? 'nonaktif' : 'aktif' ?>" />
                        <button type="submit" class="btn btn-sm <?= $u['status_akun'] === 'aktif' ? 'btn-danger' : 'btn-success' ?> text-[11px] py-1 px-2.5 rounded">
                          <?= $u['status_akun'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>
                        </button>
                      </form>

                      <!-- Ubah Peran -->
                      <form method="POST" action="dashboard/admin.php?tab=staff" style="display: inline-flex; align-items: center; gap: 4px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="change_role" />
                        <input type="hidden" name="target_id" value="<?= e($u['id_pengguna']) ?>" />
                        <select name="target_role" class="form-control text-[11px] py-1 px-2 rounded border-stone-300 w-auto" onchange="if(confirm('Ubah peran akun ini menjadi ' + this.value + '?')) this.form.submit(); else this.value='<?= e($u['peran']) ?>';">
                          <option value="pelanggan" <?= $u['peran'] === 'pelanggan' ? 'selected' : '' ?>>Pelanggan</option>
                          <option value="pengelola" <?= $u['peran'] === 'pengelola' ? 'selected' : '' ?>>Pengelola</option>
                          <option value="pemilik" <?= $u['peran'] === 'pemilik' ? 'selected' : '' ?>>Pemilik</option>
                          <option value="admin" <?= $u['peran'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                      </form>
                    </div>
                  <?php else: ?>
                    <span class="text-[11px] text-stone-400 italic">Akun Admin Aktif</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

</main>

<!-- Dialog Modal: Batalkan Catatan Pembayaran Tunai -->
<div id="modalBatalTunai" class="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4" role="dialog" aria-modal="true">
  <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-stone-200">
    <div class="flex items-center justify-between pb-3 border-b border-stone-100 mb-4">
      <div class="flex items-center gap-2">
        <span class="w-2.5 h-2.5 rounded-full bg-rose-600"></span>
        <h3 class="text-base font-serif font-bold text-stone-900"><?= t('admin.cancel_modal_title') ?></h3>
      </div>
      <button type="button" id="btnTutupModalBatal" class="text-stone-400 hover:text-stone-700 text-lg">&times;</button>
    </div>

    <p class="text-xs text-stone-600 mb-4">
      <?= t('admin.cancel_modal_desc') ?>
    </p>

    <div class="bg-stone-50 border border-stone-200 rounded-xl p-3 mb-4 text-xs">
      <div class="flex justify-between mb-1">
        <span class="text-stone-500">Order ID:</span>
        <span class="font-mono font-bold text-stone-800" id="batalModalOrderId">-</span>
      </div>
      <div class="flex justify-between mb-1">
        <span class="text-stone-500">Reservasi:</span>
        <span class="font-mono font-bold text-amber-900" id="batalModalRsv">-</span>
      </div>
      <div class="flex justify-between">
        <span class="text-stone-500">Nominal:</span>
        <span class="font-mono font-bold text-emerald-800" id="batalModalNominal">-</span>
      </div>
    </div>

    <form id="formBatalTunai">
      <input type="hidden" id="batalIdPembayaran" value="" />

      <div class="mb-4">
        <label for="alasanBatalInput" class="block text-xs font-semibold text-stone-700 mb-1">
          <?= t('admin.cancel_reason_label') ?> <span class="text-rose-600">*</span>
        </label>
        <textarea id="alasanBatalInput" rows="3" maxlength="200" required
                  placeholder="<?= t('admin.cancel_reason_placeholder') ?>"
                  class="form-control text-xs rounded-lg border-stone-300 w-full p-2.5 focus:border-rose-500 focus:ring-rose-500"></textarea>
        <div class="text-[10px] text-stone-400 text-right mt-1" id="alasanCharCount">0 / 200 karakter</div>
      </div>

      <div class="flex items-center justify-end gap-2 pt-2 border-t border-stone-100">
        <button type="button" id="btnBatalTutup" class="btn btn-sm btn-ghost text-xs py-1.5 px-3 border border-stone-300 rounded">
          Tutup
        </button>
        <button type="submit" id="btnSubmitBatal" class="btn btn-sm btn-danger text-xs py-1.5 px-4 font-bold shadow-sm rounded">
          <?= t('admin.btn_confirm_cancel') ?>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- JavaScript Interaksi Admin Dashboard -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  const isEn = (window.MASAYU_LANG === 'en');
  const csrfToken = window.MASAYU_AUTH ? window.MASAYU_AUTH.csrf : '';

  // 1. Handler Sinkronkan Status Midtrans
  document.querySelectorAll('.btn-sync-midtrans').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const orderId = btn.getAttribute('data-order-id');
      if (!orderId) return;

      const origHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="inline-flex items-center gap-1"><svg class="animate-spin w-3 h-3" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>...</span>';

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
            showToast((data && data.pesan) ? data.pesan : 'Gagal menyinkronkan status', 'warning');
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

  // 2. Modal Batalkan Catatan Tunai
  const modalBatal = document.getElementById('modalBatalTunai');
  const btnTutupModal = document.getElementById('btnTutupModalBatal');
  const btnBatalTutup = document.getElementById('btnBatalTutup');
  const formBatal = document.getElementById('formBatalTunai');
  const alasanInput = document.getElementById('alasanBatalInput');
  const charCount = document.getElementById('alasanCharCount');
  const btnSubmitBatal = document.getElementById('btnSubmitBatal');

  function closeModal() {
    if (modalBatal) modalBatal.classList.add('hidden');
    if (alasanInput) alasanInput.value = '';
    if (charCount) charCount.textContent = '0 / 200 karakter';
  }

  if (btnTutupModal) btnTutupModal.addEventListener('click', closeModal);
  if (btnBatalTutup) btnBatalTutup.addEventListener('click', closeModal);

  if (alasanInput && charCount) {
    alasanInput.addEventListener('input', function() {
      charCount.textContent = this.value.length + ' / 200 karakter';
    });
  }

  document.querySelectorAll('.btn-open-cancel-modal').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const idPembayaran = btn.getAttribute('data-id');
      const orderId = btn.getAttribute('data-order');
      const nominal = btn.getAttribute('data-nominal');
      const rsvId = btn.getAttribute('data-rsv');

      document.getElementById('batalIdPembayaran').value = idPembayaran;
      document.getElementById('batalModalOrderId').textContent = orderId;
      document.getElementById('batalModalRsv').textContent = '#RSV-' + rsvId;
      document.getElementById('batalModalNominal').textContent = nominal;

      if (modalBatal) modalBatal.classList.remove('hidden');
      if (alasanInput) alasanInput.focus();
    });
  });

  if (formBatal) {
    formBatal.addEventListener('submit', function(e) {
      e.preventDefault();
      const idPembayaran = document.getElementById('batalIdPembayaran').value;
      const alasan = alasanInput.value.trim();

      if (!alasan) {
        if (typeof showToast === 'function') {
          showToast(isEn ? 'Cancellation reason is required.' : 'Alasan pembatalan wajib diisi.', 'warning');
        }
        alasanInput.focus();
        return;
      }

      btnSubmitBatal.disabled = true;
      btnSubmitBatal.textContent = isEn ? 'Cancelling...' : 'Membatalkan...';

      fetch('api/batal-tunai.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id_pembayaran=' + encodeURIComponent(idPembayaran) + 
              '&alasan_batal=' + encodeURIComponent(alasan) + 
              '&csrf_token=' + encodeURIComponent(csrfToken)
      })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data && data.ok) {
          if (typeof showToast === 'function') {
            showToast(data.pesan || 'Catatan tunai berhasil dibatalkan.', 'success');
          }
          closeModal();
          setTimeout(function() { window.location.reload(); }, 1000);
        } else {
          if (typeof showToast === 'function') {
            showToast((data && data.pesan) ? data.pesan : 'Gagal membatalkan catatan tunai', 'error');
          }
          btnSubmitBatal.disabled = false;
          btnSubmitBatal.textContent = isEn ? 'Cancel Now' : 'Batalkan Sekarang';
        }
      })
      .catch(function() {
        if (typeof showToast === 'function') {
          showToast('Terjadi kesalahan jaringan.', 'error');
        }
        btnSubmitBatal.disabled = false;
        btnSubmitBatal.textContent = isEn ? 'Cancel Now' : 'Batalkan Sekarang';
      });
    });
  }
});
</script>

<?php require_once __DIR__ . '/../includes/layout/foot.php'; ?>
