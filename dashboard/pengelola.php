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

// Daftar tipe villa untuk form reservasi offline dan blokir
$villasList = $pdo->query("SELECT id_tipe, nama, kapasitas_maks FROM tipe_villa ORDER BY id_tipe ASC")->fetchAll();

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

                // 1. Ambil data reservasi target (kunci baris)
                $stmtTarget = $pdo->prepare("SELECT * FROM `reservasi` WHERE `id_reservasi` = ? FOR UPDATE");
                $stmtTarget->execute([$idReservasi]);
                $target = $stmtTarget->fetch();

                if (!$target) {
                    $pdo->rollBack();
                    flash_set('danger', 'Data reservasi tidak ditemukan.');
                    redirect('dashboard/pengelola.php');
                }

                // 2. Kunci baris tipe villa sebagai mutex per tipe
                $stmtVilla = $pdo->prepare("SELECT `id_tipe`, `nama`, `jumlah_unit` FROM `tipe_villa` WHERE `id_tipe` = ? FOR UPDATE");
                $stmtVilla->execute([$target['id_tipe']]);
                $villaInfo = $stmtVilla->fetch();

                // 3. Periksa ketersediaan menggunakan hitung_ketersediaan() — satu sumber kebenaran
                $ketersediaan = hitung_ketersediaan($target['id_tipe'], $target['tanggal_checkin'], $target['tanggal_checkout'], $pdo);

                // Cek apakah ada malam penuh selain reservasi ini sendiri
                // (reservasi ini masih 'menunggu' jadi belum dihitung di terkonfirmasi)
                $adaPenuh = false;
                $tanggalPenuh = '';
                foreach ($ketersediaan['malam'] as $tgl => $malam) {
                    if ($malam['status'] === 'penuh') {
                        $adaPenuh = true;
                        $tanggalPenuh = $tgl;
                        break;
                    }
                }

                if ($adaPenuh) {
                    $pdo->rollBack();
                    flash_set('danger', "Gagal mengonfirmasi #RSV-{$idReservasi}: Seluruh unit {$villaInfo['nama']} sudah terisi pada tanggal {$tanggalPenuh}.");
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
        } elseif ($aksi === 'batalkan_offline') {
            // Batalkan reservasi offline yang belum lunas
            try {
                $alasanBatal = trim($_POST['alasan_batal'] ?? '');
                if (strlen($alasanBatal) < 3) {
                    flash_set('danger', t('offline.error_alasan_batal'));
                    redirect('dashboard/pengelola.php');
                }
                $pdo->beginTransaction();
                $stmtCek = $pdo->prepare("SELECT r.*, p.status AS bayar_status FROM reservasi r LEFT JOIN pembayaran p ON p.id_reservasi = r.id_reservasi AND p.status = 'lunas' WHERE r.id_reservasi = ? FOR UPDATE");
                $stmtCek->execute([$idReservasi]);
                $cekR = $stmtCek->fetch();
                if (!$cekR) {
                    $pdo->rollBack();
                    flash_set('danger', 'Reservasi tidak ditemukan.');
                    redirect('dashboard/pengelola.php');
                }
                if ($cekR['bayar_status'] === 'lunas') {
                    $pdo->rollBack();
                    flash_set('danger', t('offline.error_batal_sudah_lunas'));
                    redirect('dashboard/pengelola.php');
                }
                $stmtBatal = $pdo->prepare("UPDATE reservasi SET status = 'dibatalkan' WHERE id_reservasi = ?");
                $stmtBatal->execute([$idReservasi]);
                $pdo->commit();
                flash_set('success', t('offline.batal_success', ['kode' => "#RSV-{$idReservasi}"]));
                redirect('dashboard/pengelola.php');
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash_set('danger', 'Terjadi kesalahan saat membatalkan reservasi.');
                redirect('dashboard/pengelola.php');
            }
        }
    }
}

// 2. Filter Status Reservasi & Pembayaran
$filterStatus = trim($_GET['status'] ?? '');
$filterBayar  = trim($_GET['bayar'] ?? '');
$filterSumber = trim($_GET['sumber'] ?? '');

$allowedStatus = ['menunggu', 'dikonfirmasi', 'ditolak', 'dibatalkan'];
$allowedSumber = ['online', 'walk_in', 'whatsapp', 'telepon'];
$whereClauses = [];
$params = [];

if (in_array($filterStatus, $allowedStatus, true)) {
    $whereClauses[] = "r.status = ?";
    $params[] = $filterStatus;
}
// Filter sumber reservasi
if (in_array($filterSumber, $allowedSumber, true)) {
    $whereClauses[] = "r.sumber = ?";
    $params[] = $filterSumber;
}

// Filter pencarian teks: nomor reservasi, nama tamu, order ID, nomor VA, atau bank
$searchQ = trim($_GET['q'] ?? '');
if (!empty($searchQ)) {
    $cleanId = ltrim($searchQ, '#rsvRSV- ');
    $whereClauses[] = "(
        r.id_reservasi = ? 
        OR r.nama_tamu LIKE ? 
        OR p.nama LIKE ? 
        OR r.id_reservasi IN (
            SELECT pb.id_reservasi FROM pembayaran pb 
            WHERE pb.order_id LIKE ? OR pb.kode_pembayaran LIKE ? OR pb.bank_atau_toko LIKE ?
        )
    )";
    $params[] = is_numeric($cleanId) ? (int)$cleanId : 0;
    $params[] = '%' . $searchQ . '%';
    $params[] = '%' . $searchQ . '%';
    $params[] = '%' . $searchQ . '%';
    $params[] = '%' . $searchQ . '%';
    $params[] = '%' . $searchQ . '%';
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

$sql = "SELECT r.*, v.nama AS nama_villa, v.slug AS slug_villa, 
               COALESCE(p.nama, r.nama_tamu) AS nama_pelanggan,
               p.email AS email_pelanggan,
               COALESCE(p.kontak, r.kontak_tamu) AS kontak_pelanggan,
               staf.nama AS nama_staf,
               r.sumber AS sumber_reservasi
        FROM `reservasi` r
        JOIN `tipe_villa` v ON r.id_tipe = v.id_tipe
        LEFT JOIN `pengguna` p ON r.id_pelanggan = p.id_pengguna
        LEFT JOIN `pengguna` staf ON r.diproses_oleh = staf.id_pengguna
        {$whereSql}
        ORDER BY 
          CASE WHEN r.status = 'menunggu' THEN 1 ELSE 2 END,
          r.tanggal_checkin ASC, 
          r.id_reservasi DESC";

$stmtList = $pdo->prepare($sql);
$stmtList->execute($params);
$rawList = $stmtList->fetchAll();

// Ambil semua pembayaran sekaligus dengan SATU query batch untuk semua reservasi yang tampil (mencegah N+1)
$allIds = array_map(fn($item) => (int)$item['id_reservasi'], $rawList);
$pembayaranBatch = ambil_pembayaran_batch($pdo, $allIds);

// Olah status bayar efektif untuk setiap reservasi
$reservasiList = [];
foreach ($rawList as $r) {
    $idRes = (int)$r['id_reservasi'];
    $bayarInfo = $pembayaranBatch[$idRes] ?? ['status' => 'belum_dibayar', 'pembayaran' => null, 'history' => []];
    $statusBayar = $bayarInfo['status'];

    // Filter pembayaran client-side bila diisi
    if (!empty($filterBayar)) {
        if ($filterBayar === 'belum_dibayar' && $statusBayar !== 'belum_dibayar') continue;
        if ($filterBayar === 'menunggu' && $statusBayar !== 'menunggu') continue;
        if ($filterBayar === 'lunas' && $statusBayar !== 'lunas') continue;
        if ($filterBayar === 'gagal_kedaluwarsa' && !in_array($statusBayar, ['gagal', 'kedaluwarsa'], true)) continue;
    }

    $history = $bayarInfo['history'] ?? [];

    $r['bayar_status'] = $statusBayar;
    $r['bayar_pembayaran'] = $bayarInfo['pembayaran'];
    $r['bayar_history'] = $history;

    // Evaluasi penanda lewat batas bayar (24 jam)
    $tsDibuat = strtotime($r['dibuat_pada']);
    $tsBatas = $tsDibuat + (24 * 3600);
    $r['is_lewat_batas'] = ($r['status'] === 'dikonfirmasi' && $statusBayar !== 'lunas' && time() > $tsBatas);
    $r['batas_waktu_str'] = date('d/m/Y H:i', $tsBatas) . ' WIB';

    // Deteksi "bentrok": reservasi menunggu yang bertabrakan dengan yang sudah dikonfirmasi
    $r['is_bentrok'] = false;
    if ($r['status'] === 'menunggu') {
        $stmtBentrok = $pdo->prepare("
            SELECT COUNT(*) FROM reservasi
            WHERE id_tipe = ? AND status = 'dikonfirmasi'
              AND tanggal_checkin < ? AND tanggal_checkout > ?
        ");
        $stmtBentrok->execute([$r['id_tipe'], $r['tanggal_checkout'], $r['tanggal_checkin']]);
        $jumlahKonfirmasi = (int)$stmtBentrok->fetchColumn();
        $r['is_bentrok'] = ($jumlahKonfirmasi >= (int)($r['jumlah_unit'] ?? 999));
    }

    // Sumber reservasi (default 'online' jika kolom belum ada)
    $r['sumber_reservasi'] = $r['sumber_reservasi'] ?? ($r['sumber'] ?? 'online');

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
    <div class="flex items-center gap-2 flex-wrap">
      <a href="kalender-hunian.php" class="btn btn-sm btn-outline-gold text-xs py-2 px-4">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        <?= t('occupancy.title') ?>
      </a>
      <button type="button" id="btnTambahOffline" class="btn btn-sm btn-gold text-xs py-2 px-4" onclick="document.getElementById('modalOffline').classList.remove('hidden')">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        <?= t('offline.btn_tambah') ?>
      </button>
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

      <!-- Filter Sumber -->
      <select name="sumber" class="form-control text-xs py-1.5 px-3 rounded-lg border-stone-300 w-auto" onchange="this.form.submit()">
        <option value=""><?= t('offline.filter_all_sumber') ?></option>
        <option value="online" <?= $filterSumber === 'online' ? 'selected' : '' ?>><?= t('offline.sumber_online') ?></option>
        <option value="walk_in" <?= $filterSumber === 'walk_in' ? 'selected' : '' ?>><?= t('offline.sumber_walk_in') ?></option>
        <option value="whatsapp" <?= $filterSumber === 'whatsapp' ? 'selected' : '' ?>><?= t('offline.sumber_whatsapp') ?></option>
        <option value="telepon" <?= $filterSumber === 'telepon' ? 'selected' : '' ?>><?= t('offline.sumber_telepon') ?></option>
      </select>

      <!-- Input Pencarian (No. Reservasi, Nama, Order ID, VA) -->
      <div class="inline-flex items-center gap-1">
        <input type="text" name="q" value="<?= e($searchQ) ?>" placeholder="<?= t('payment.filter_va_placeholder') ?>" class="form-control text-xs py-1.5 px-3 rounded-lg border-stone-300 w-44 md:w-56" />
        <button type="submit" class="btn btn-sm btn-ghost border border-stone-300 text-xs py-1.5 px-2.5">
          <svg class="w-3.5 h-3.5 text-stone-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </button>
      </div>

      <?php if ($filterStatus || $filterBayar || $filterSumber || $searchQ): ?>
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
                  <?php
                    $sumberR = $r['sumber_reservasi'] ?? ($r['sumber'] ?? 'online');
                    $sumberLabels = ['online'=>'Online','walk_in'=>'Langsung','whatsapp'=>'WhatsApp','telepon'=>'Telepon'];
                    $sumberColors = ['online'=>'bg-sky-50 text-sky-700 border-sky-200','walk_in'=>'bg-violet-50 text-violet-700 border-violet-200','whatsapp'=>'bg-emerald-50 text-emerald-700 border-emerald-200','telepon'=>'bg-orange-50 text-orange-700 border-orange-200'];
                    $sLabel = $sumberLabels[$sumberR] ?? $sumberR;
                    $sColor = $sumberColors[$sumberR] ?? 'bg-stone-50 text-stone-600 border-stone-200';
                  ?>
                  <span class="inline-block mt-1 text-[10px] font-semibold border px-1.5 py-0.5 rounded <?= $sColor ?>"><?= e($sLabel) ?></span>
                  <?php if (!empty($r['is_bentrok'])): ?>
                    <span class="inline-block mt-1 text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300 px-1.5 py-0.5 rounded">⚠ <?= t('offline.badge_bentrok') ?></span>
                  <?php endif; ?>
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
                  <?php if ($r['status'] === 'dikonfirmasi' || !empty($r['bayar_pembayaran'])): ?>
                    <div><?= render_badge_pembayaran($r['bayar_status'], true) ?></div>
                    <?php if ($isLunas): ?>
                      <?php if (!empty($r['bayar_pembayaran']['metode_kelompok'])): ?>
                        <div class="text-[11px] text-stone-600 font-medium mt-1">
                          <?= format_metode_pembayaran($r['bayar_pembayaran']['metode_kelompok']) ?>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($r['bayar_pembayaran']['dibayar_pada'])): ?>
                        <div class="text-[10px] text-stone-500 mt-0.5">
                          <?= date('d/m/Y H:i', strtotime($r['bayar_pembayaran']['dibayar_pada'])) ?> WIB
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($r['bayar_pembayaran']['kode_pembayaran'])): ?>
                        <div class="text-[10px] text-stone-500 font-mono mt-0.5">
                          <span class="text-stone-400 font-sans"><?= t('payment.va_code_label') ?>:</span> <?= e(samarkan_kode_pembayaran($r['bayar_pembayaran']['kode_pembayaran'])) ?>
                        </div>
                      <?php endif; ?>
                      <a href="struk-pembayaran.php?id=<?= $idRes ?>" role="button" class="btn-open-struk inline-flex items-center gap-1 text-[11px] text-amber-900 hover:text-amber-700 font-semibold underline mt-1.5 focus:outline-none focus:ring-1 focus:ring-amber-500 rounded" data-id="<?= $idRes ?>" data-type="lunas" aria-haspopup="dialog">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span><?= t('payment.btn_view_receipt') ?></span>
                      </a>
                    <?php else: ?>
                      <?php if (!empty($r['bayar_pembayaran']['kode_pembayaran'])): ?>
                        <div class="text-[10px] text-stone-700 font-mono mt-1 font-semibold">
                          <span class="text-stone-400 font-sans"><?= t('payment.va_code_label') ?>:</span> <?= e(samarkan_kode_pembayaran($r['bayar_pembayaran']['kode_pembayaran'])) ?>
                        </div>
                      <?php endif; ?>
                      <a href="struk-pembayaran.php?id=<?= $idRes ?>" role="button" class="btn-open-struk inline-flex items-center gap-1 text-[11px] text-stone-600 hover:text-stone-900 font-medium underline mt-1.5 focus:outline-none focus:ring-1 focus:ring-stone-400 rounded" data-id="<?= $idRes ?>" data-type="tagihan" aria-haspopup="dialog">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span><?= t('payment.btn_invoice_details') ?></span>
                      </a>
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
              <?php if ($r['status'] === 'dikonfirmasi' || !empty($r['bayar_pembayaran'])): ?>
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
            <?php if ($isLunas && !empty($r['bayar_pembayaran']['dibayar_pada'])): ?>
              <div class="text-[11px] text-stone-500">
                <strong>Dibayar:</strong> <?= date('d/m/Y H:i', strtotime($r['bayar_pembayaran']['dibayar_pada'])) ?> WIB
                <?php if (!empty($r['bayar_pembayaran']['metode_kelompok'])): ?>
                  (<?= format_metode_pembayaran($r['bayar_pembayaran']['metode_kelompok']) ?>)
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($r['bayar_pembayaran']['kode_pembayaran'])): ?>
              <div class="text-[11px] text-stone-600 font-mono">
                <strong><?= t('payment.va_code_label') ?>:</strong> <?= e(format_bank_atau_toko($r['bayar_pembayaran']['bank_atau_toko'] ?? null)) ?> <?= e(samarkan_kode_pembayaran($r['bayar_pembayaran']['kode_pembayaran'])) ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="pt-3 flex items-center justify-end gap-2 flex-wrap text-xs">
            <?php if ($r['status'] === 'dikonfirmasi' || !empty($r['bayar_pembayaran'])): ?>
              <a href="struk-pembayaran.php?id=<?= $idRes ?>" role="button" class="btn-open-struk btn btn-sm btn-ghost border border-stone-300 text-xs py-1.5 px-2.5 inline-flex items-center gap-1 min-h-[44px]" data-id="<?= $idRes ?>" data-type="<?= $isLunas ? 'lunas' : 'tagihan' ?>" aria-haspopup="dialog">
                <svg class="w-3.5 h-3.5 text-stone-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span><?= $isLunas ? t('payment.btn_view_receipt') : t('payment.btn_invoice_details') ?></span>
              </a>
            <?php endif; ?>
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

<!-- Drawer Slide-Over: Detail Pembayaran (Struk & Tagihan) -->
<div id="drawer-struk-backdrop" class="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-sm hidden transition-opacity duration-300 opacity-0" aria-hidden="true"></div>

<div id="drawer-struk" class="fixed inset-y-0 right-0 z-50 w-full md:max-w-xl bg-white shadow-2xl flex flex-col transform translate-x-full transition-transform duration-300 ease-in-out hidden" role="dialog" aria-modal="true" aria-labelledby="drawer-struk-title">
  
  <!-- Header Drawer -->
  <div class="px-6 py-4 border-b border-stone-200 flex items-center justify-between bg-stone-50/90">
    <div class="flex items-center gap-2.5">
      <span class="w-2.5 h-2.5 rounded-full bg-amber-600"></span>
      <div>
        <h3 id="drawer-struk-title" class="font-serif font-bold text-stone-900 text-base md:text-lg leading-tight">
          <?= t('payment.receipt_title') ?>
        </h3>
        <p id="drawer-struk-subtitle" class="text-[11px] text-stone-500 mb-0 font-mono">Villa Joglo Masayu</p>
      </div>
    </div>
    <button type="button" id="btn-close-drawer-icon" class="text-stone-400 hover:text-stone-700 p-2 rounded-lg hover:bg-stone-200/60 transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500" aria-label="<?= t('payment.btn_close_drawer') ?>">
      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  </div>

  <!-- Body Drawer (Scrollable) -->
  <div class="flex-1 overflow-y-auto p-5 md:p-6 space-y-5 text-xs text-stone-700">
    
    <!-- State Loading -->
    <div id="drawer-loading" class="py-20 text-center text-stone-400">
      <svg class="animate-spin w-8 h-8 text-amber-700 mx-auto mb-3" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
      </svg>
      <p class="text-xs"><?= t('payment.loading_receipt') ?></p>
    </div>

    <!-- State Error -->
    <div id="drawer-error" class="hidden py-16 text-center text-stone-500">
      <div class="w-10 h-10 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-3 font-bold">!</div>
      <p id="drawer-error-msg" class="text-xs text-rose-700"><?= t('payment.error_load_receipt') ?></p>
    </div>

    <!-- Data Struk Loaded -->
    <div id="drawer-data" class="hidden space-y-5">
      
      <!-- Status Badge & Order ID Header -->
      <div class="bg-stone-50 border border-stone-200 rounded-xl p-4 flex items-center justify-between gap-3">
        <div>
          <span class="text-[10px] uppercase tracking-wider text-stone-500 font-semibold block"><?= t('payment.receipt_no') ?></span>
          <span id="drawer-order-id" class="font-mono font-bold text-stone-900 text-sm"></span>
          <div class="text-[11px] text-stone-500 mt-0.5" id="drawer-waktu-bayar"></div>
        </div>
        <div id="drawer-status-badge" class="shrink-0 text-right"></div>
      </div>

      <!-- Sandbox Notice jika Midtrans Sandbox -->
      <div id="drawer-sandbox-notice" class="hidden p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 flex items-start gap-2">
        <svg class="w-4 h-4 shrink-0 text-amber-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span><strong><?= t('payment.sandbox_badge') ?>:</strong> <?= t('payment.sandbox_notice') ?></span>
      </div>

      <!-- Detail Reservasi & Tamu -->
      <div class="border border-stone-200 rounded-xl p-4 bg-white space-y-2.5">
        <h4 class="font-serif font-bold text-stone-900 text-xs uppercase tracking-wider pb-1.5 border-b border-stone-100"><?= t('payment.guest_info') ?></h4>
        <div class="grid grid-cols-2 gap-2">
          <div>
            <span class="text-stone-400 block"><?= t('payment.guest_info') ?></span>
            <span id="drawer-guest-name" class="font-semibold text-stone-800"></span>
          </div>
          <div>
            <span class="text-stone-400 block"><?= t('payment.guest_contact') ?></span>
            <span id="drawer-guest-contact" class="text-stone-800"></span>
          </div>
          <div>
            <span class="text-stone-400 block"><?= t('payment.villa_type') ?></span>
            <span id="drawer-villa-name" class="font-semibold text-stone-800"></span>
          </div>
          <div>
            <span class="text-stone-400 block"><?= t('payment.stay_period') ?></span>
            <span id="drawer-period" class="text-stone-800"></span>
          </div>
        </div>
      </div>

      <!-- Detail Finansial -->
      <div class="border border-stone-200 rounded-xl p-4 bg-white space-y-2.5">
        <h4 class="font-serif font-bold text-stone-900 text-xs uppercase tracking-wider pb-1.5 border-b border-stone-100">Rincian Finansial</h4>
        <dl class="space-y-1.5">
          <div class="flex justify-between">
            <dt class="text-stone-500">Tarif / Malam:</dt>
            <dd id="drawer-rate" class="text-stone-800 text-right"></dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-stone-500">Durasi Menginap:</dt>
            <dd id="drawer-duration" class="text-stone-800 text-right"></dd>
          </div>
          <div class="flex justify-between font-semibold pt-1 border-t border-stone-100">
            <dt class="text-stone-900"><?= t('payment.total_amount') ?>:</dt>
            <dd id="drawer-total" class="text-stone-900 text-right"></dd>
          </div>
          <div id="drawer-paid-row" class="flex justify-between text-emerald-700">
            <dt><?= t('payment.amount_paid') ?>:</dt>
            <dd id="drawer-paid" class="font-bold text-right"></dd>
          </div>
          <div id="drawer-unpaid-row" class="flex justify-between text-rose-700">
            <dt><?= t('payment.unpaid_amount') ?>:</dt>
            <dd id="drawer-unpaid" class="font-bold text-right"></dd>
          </div>
        </dl>
      </div>

      <!-- Detail Metode Pembayaran -->
      <div id="drawer-payment-details-card" class="border border-stone-200 rounded-xl p-4 bg-white space-y-2">
        <h4 class="font-serif font-bold text-stone-900 text-xs uppercase tracking-wider pb-1.5 border-b border-stone-100"><?= t('payment.payment_method_label') ?></h4>
        <div class="space-y-1.5">
          <div class="flex justify-between">
            <span class="text-stone-500"><?= t('payment.payment_method_label') ?>:</span>
            <span id="drawer-method-label" class="font-semibold text-stone-800 text-right"></span>
          </div>
          <div id="drawer-tx-ref-row" class="flex justify-between">
            <span class="text-stone-500"><?= t('payment.transaction_ref') ?>:</span>
            <span id="drawer-tx-ref" class="font-mono text-stone-800 text-right"></span>
          </div>
          <div id="drawer-va-row" class="hidden flex justify-between items-center py-1 border-t border-stone-100">
            <span class="text-stone-500"><?= t('payment.va_card_title') ?>:</span>
            <div class="flex items-center gap-1.5">
              <span id="drawer-va-bank" class="text-stone-600 font-medium text-xs"></span>
              <span id="drawer-va-code" class="font-mono font-bold text-stone-900 text-xs"></span>
              <button type="button" id="btn-drawer-copy-va" class="text-amber-800 hover:text-amber-600 p-0.5" title="<?= t('payment.btn_copy_va') ?>">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
              </button>
            </div>
          </div>
          <div id="drawer-expiry-row" class="hidden flex justify-between items-center text-[11px] text-amber-800">
            <span><?= t('payment.expiry_time_label') ?>:</span>
            <span id="drawer-expiry-time" class="font-medium"></span>
          </div>
          <div id="drawer-recorder-row" class="flex justify-between">
            <span class="text-stone-500"><?= t('payment.recorded_by') ?>:</span>
            <span id="drawer-recorder" class="text-stone-800 text-right"></span>
          </div>
          <div id="drawer-cash-note-row" class="pt-1 text-stone-600 italic">
            <span class="text-stone-400 not-italic block text-[11px]"><?= t('payment.cash_note_label') ?>:</span>
            <span id="drawer-cash-note"></span>
          </div>
        </div>
      </div>

      <!-- Quick Action jika Belum Lunas (Catat Tunai) -->
      <div id="drawer-unpaid-action-box" class="hidden p-3.5 bg-amber-50/80 border border-amber-200 rounded-xl flex items-center justify-between gap-3">
        <div class="text-xs text-amber-900">
          <span class="font-semibold block">Tamu ingin membayar tunai?</span>
          <span class="text-[11px] text-amber-700">Catat penerimaan fisik langsung di sini.</span>
        </div>
        <button type="button" id="btn-drawer-quick-cash" class="btn btn-sm btn-gold text-xs py-1.5 px-3 font-semibold shrink-0">
          <?= t('payment.btn_record_cash') ?>
        </button>
      </div>

      <!-- Riwayat Transaksi -->
      <div id="drawer-history-section" class="hidden border border-stone-200 rounded-xl p-4 bg-white space-y-2">
        <h4 class="font-serif font-bold text-stone-900 text-xs uppercase tracking-wider pb-1.5 border-b border-stone-100"><?= t('payment.payment_history') ?></h4>
        <div id="drawer-history-list" class="space-y-1.5"></div>
      </div>

    </div>

  </div>

  <!-- Footer Drawer (Tombol Aksi) -->
  <div class="px-5 py-3.5 border-t border-stone-200 bg-stone-50 flex items-center justify-between gap-2 flex-wrap">
    <div class="flex items-center gap-2 flex-wrap">
      <button type="button" id="btn-drawer-copy" class="btn btn-sm btn-ghost border border-stone-300 text-xs py-2 px-3 inline-flex items-center gap-1.5 min-h-[44px]">
        <svg class="w-4 h-4 text-stone-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
        <span><?= t('payment.btn_copy_receipt') ?></span>
      </button>

      <a id="btn-drawer-wa" href="#" target="_blank" rel="noopener" class="btn btn-sm btn-ghost border border-stone-300 text-xs py-2 px-3 inline-flex items-center gap-1.5 min-h-[44px]">
        <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.007c.106.005.249-.04.39.298.144.347.491 1.2.534 1.288.043.088.072.191.014.306-.058.115-.087.187-.173.289l-.26.302c-.087.087-.179.182-.077.357.101.174.451.744.969 1.205.666.594 1.229.778 1.403.865.174.087.275.072.376-.043.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.043.073.043.419-.101.824z"/></svg>
        <span><?= t('payment.btn_share_wa') ?></span>
      </a>

      <a id="btn-drawer-print" href="#" target="_blank" rel="noopener" class="btn btn-sm btn-gold text-xs py-2 px-3.5 inline-flex items-center gap-1.5 font-semibold min-h-[44px]">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        <span><?= t('payment.btn_print_pdf') ?></span>
      </a>
    </div>

    <button type="button" id="btn-close-drawer-bottom" class="btn btn-sm btn-ghost border border-stone-200 text-xs py-2 px-3.5 text-stone-600 min-h-[44px]">
      <?= t('payment.btn_close_drawer') ?>
    </button>
  </div>

</div>

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

<!-- ═══════════════════════════════════════════════════════════
     MODAL: RESERVASI OFFLINE
═══════════════════════════════════════════════════════════ -->
<div id="modalOffline" class="hidden fixed inset-0 z-[200] flex items-start justify-center p-4 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="modalOfflineTitle">
  <div class="absolute inset-0 bg-black/50" onclick="document.getElementById('modalOffline').classList.add('hidden')"></div>
  <div class="relative bg-white rounded-2xl shadow-2xl max-w-lg w-full my-8 z-10">
    <div class="flex items-center justify-between p-5 border-b border-stone-200">
      <h2 id="modalOfflineTitle" class="text-lg font-serif font-bold text-stone-900"><?= t('offline.title') ?></h2>
      <button type="button" class="text-stone-400 hover:text-stone-700 p-1 rounded" onclick="document.getElementById('modalOffline').classList.add('hidden')" aria-label="Tutup">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="formOffline" class="p-5 space-y-4">
      <?= csrf_field() ?>
      <div id="offlineAlertBox" class="hidden"></div>

      <!-- Sumber -->
      <div class="form-group">
        <label class="form-label text-sm" for="offline_sumber"><?= t('offline.sumber_label') ?> <span class="required">*</span></label>
        <select id="offline_sumber" name="sumber" class="form-control text-sm" required>
          <option value="walk_in"><?= t('offline.sumber_walk_in') ?></option>
          <option value="whatsapp"><?= t('offline.sumber_whatsapp') ?></option>
          <option value="telepon"><?= t('offline.sumber_telepon') ?></option>
        </select>
      </div>

      <!-- Nama & Kontak Tamu -->
      <div class="grid grid-cols-2 gap-3">
        <div class="form-group">
          <label class="form-label text-sm" for="offline_nama"><?= t('offline.nama_tamu') ?> <span class="required">*</span></label>
          <input type="text" id="offline_nama" name="nama_tamu" class="form-control text-sm" placeholder="Min. 2 karakter" required minlength="2" maxlength="100" />
        </div>
        <div class="form-group">
          <label class="form-label text-sm" for="offline_kontak"><?= t('offline.kontak_tamu') ?> <span class="required">*</span></label>
          <input type="tel" id="offline_kontak" name="kontak_tamu" class="form-control text-sm" placeholder="08xx / 628xx (9-15 digit)" required />
        </div>
      </div>

      <!-- Tipe Villa -->
      <div class="form-group">
        <label class="form-label text-sm" for="offline_tipe"><?= t('booking.villa_label') ?> <span class="required">*</span></label>
        <select id="offline_tipe" name="id_tipe" class="form-control text-sm" required>
          <option value="">-- Pilih Tipe Villa --</option>
          <?php foreach ($villasList ?? [] as $v): ?>
            <option value="<?= e($v['id_tipe']) ?>" data-max="<?= e($v['kapasitas_maks']) ?>"><?= e($v['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Tanggal -->
      <div class="grid grid-cols-2 gap-3">
        <div class="form-group">
          <label class="form-label text-sm" for="offline_checkin"><?= t('booking.checkin_label') ?> <span class="required">*</span></label>
          <input type="date" id="offline_checkin" name="tanggal_checkin" class="form-control text-sm" min="<?= date('Y-m-d') ?>" required />
        </div>
        <div class="form-group">
          <label class="form-label text-sm" for="offline_checkout"><?= t('booking.checkout_label') ?> <span class="required">*</span></label>
          <input type="date" id="offline_checkout" name="tanggal_checkout" class="form-control text-sm" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required />
        </div>
      </div>

      <!-- Jumlah Tamu -->
      <div class="form-group">
        <label class="form-label text-sm" for="offline_tamu"><?= t('booking.guests_label') ?> <span class="required">*</span></label>
        <input type="number" id="offline_tamu" name="jumlah_tamu" class="form-control text-sm" value="2" min="1" max="20" required />
        <div id="offline_tamu_hint" class="form-hint text-xs"></div>
      </div>

      <!-- Catatan -->
      <div class="form-group">
        <label class="form-label text-sm" for="offline_catatan"><?= t('booking.special_requests') ?></label>
        <textarea id="offline_catatan" name="catatan" class="form-control text-sm" rows="2" maxlength="500" placeholder="Opsional..."></textarea>
      </div>

      <!-- Catat Tunai Sekarang -->
      <div class="flex items-center gap-2">
        <input type="checkbox" id="offline_catat_tunai" name="catat_tunai" value="1" class="w-4 h-4 rounded text-amber-600 border-stone-300" />
        <label for="offline_catat_tunai" class="text-sm text-stone-700 cursor-pointer"><?= t('offline.catat_tunai_label') ?></label>
      </div>

      <input type="hidden" name="konfirmasi_conflict" id="offline_konfirmasi_conflict" value="0" />

      <div class="flex justify-end gap-3 pt-2 border-t border-stone-100">
        <button type="button" class="btn btn-ghost text-sm" onclick="document.getElementById('modalOffline').classList.add('hidden')"><?= t('common.cancel') ?></button>
        <button type="submit" id="btnOfflineSubmit" class="btn btn-gold text-sm"><?= t('offline.btn_simpan') ?></button>
      </div>
    </form>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: BLOKIR TANGGAL
═══════════════════════════════════════════════════════════ -->
<div id="modalBlokir" class="hidden fixed inset-0 z-[200] flex items-start justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="modalBlokirTitle">
  <div class="absolute inset-0 bg-black/50" onclick="document.getElementById('modalBlokir').classList.add('hidden')"></div>
  <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full my-8 z-10">
    <div class="flex items-center justify-between p-5 border-b border-stone-200">
      <h2 id="modalBlokirTitle" class="text-lg font-serif font-bold text-stone-900"><?= t('block.title') ?></h2>
      <button type="button" class="text-stone-400 hover:text-stone-700 p-1 rounded" onclick="document.getElementById('modalBlokir').classList.add('hidden')" aria-label="Tutup">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="formBlokir" class="p-5 space-y-4">
      <?= csrf_field() ?>
      <div id="blokirAlertBox" class="hidden"></div>

      <div class="form-group">
        <label class="form-label text-sm" for="blokir_tipe"><?= t('booking.villa_label') ?> <span class="required">*</span></label>
        <select id="blokir_tipe" name="id_tipe" class="form-control text-sm" required>
          <option value="">-- Pilih Tipe Villa --</option>
          <?php foreach ($villasList ?? [] as $v): ?>
            <option value="<?= e($v['id_tipe']) ?>"><?= e($v['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div class="form-group">
          <label class="form-label text-sm" for="blokir_mulai"><?= t('block.mulai_label') ?> <span class="required">*</span></label>
          <input type="date" id="blokir_mulai" name="tanggal_mulai" class="form-control text-sm" min="<?= date('Y-m-d') ?>" required />
        </div>
        <div class="form-group">
          <label class="form-label text-sm" for="blokir_selesai"><?= t('block.selesai_label') ?> <span class="required">*</span></label>
          <input type="date" id="blokir_selesai" name="tanggal_selesai" class="form-control text-sm" required />
        </div>
      </div>
      <div class="form-group">
        <label class="form-label text-sm" for="blokir_alasan"><?= t('block.alasan_label') ?> <span class="required">*</span></label>
        <select id="blokir_alasan_preset" class="form-control text-sm mb-2" onchange="document.getElementById('blokir_alasan').value=this.value">
          <option value=""><?= t('block.pilih_alasan') ?></option>
          <option value="<?= t('block.reason_maintenance') ?>"><?= t('block.reason_maintenance') ?></option>
          <option value="<?= t('block.reason_owner') ?>"><?= t('block.reason_owner') ?></option>
        </select>
        <input type="text" id="blokir_alasan" name="alasan" class="form-control text-sm" placeholder="<?= t('block.alasan_placeholder') ?>" maxlength="200" required minlength="2" />
      </div>
      <div class="flex justify-end gap-3 pt-2 border-t border-stone-100">
        <button type="button" class="btn btn-ghost text-sm" onclick="document.getElementById('modalBlokir').classList.add('hidden')"><?= t('common.cancel') ?></button>
        <button type="submit" id="btnBlokirSubmit" class="btn btn-danger text-sm"><?= t('block.btn_blokir') ?></button>
      </div>
    </form>
  </div>
</div>

<!-- Tombol aksi bawah (blokir + reservasi offline) untuk mobile -->
<div class="fixed bottom-5 right-5 flex flex-col gap-2 z-50">
  <button type="button" onclick="document.getElementById('modalBlokir').classList.remove('hidden')"
    class="inline-flex items-center gap-2 bg-rose-700 hover:bg-rose-800 text-white text-sm font-semibold px-4 py-2.5 rounded-full shadow-lg min-h-[44px]"
    aria-label="<?= t('block.title') ?>">
    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-12.728 12.728M5.636 5.636l12.728 12.728"/></svg>
    <?= t('block.btn_blokir') ?>
  </button>
  <button type="button" onclick="document.getElementById('modalOffline').classList.remove('hidden')"
    class="inline-flex items-center gap-2 bg-amber-700 hover:bg-amber-800 text-white text-sm font-semibold px-4 py-2.5 rounded-full shadow-lg min-h-[44px]"
    aria-label="<?= t('offline.btn_tambah') ?>">
    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
    <?= t('offline.btn_tambah') ?>
  </button>
</div>

<script>
(function(){
  // ─── Form Reservasi Offline ─────────────────────────────────────────────
  var formOffline = document.getElementById('formOffline');
  if (formOffline) {
    // Update max tamu jika tipe villa berubah
    document.getElementById('offline_tipe').addEventListener('change', function(){
      var opt = this.options[this.selectedIndex];
      var max = opt.getAttribute('data-max') || '';
      var hint = document.getElementById('offline_tamu_hint');
      if (max) {
        hint.textContent = 'Kapasitas maksimal: ' + max + ' orang';
        document.getElementById('offline_tamu').max = max;
      } else {
        hint.textContent = '';
      }
    });

    formOffline.addEventListener('submit', function(e){
      e.preventDefault();
      var btn = document.getElementById('btnOfflineSubmit');
      var alertBox = document.getElementById('offlineAlertBox');
      btn.disabled = true;
      var orig = btn.textContent;
      btn.textContent = '...';

      var fd = new FormData(formOffline);

      fetch('api/reservasi-offline.php', {
        method: 'POST',
        body: fd
      })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.ok) {
          document.getElementById('modalOffline').classList.add('hidden');
          if (typeof showToast === 'function') showToast(data.pesan || 'Reservasi berhasil!', 'success');
          setTimeout(function(){ window.location.reload(); }, 1200);
        } else if (data.perlu_konfirmasi) {
          // Tampilkan peringatan conflict
          alertBox.className = 'bg-amber-50 border border-amber-300 rounded-lg p-3 text-sm text-amber-900';
          alertBox.textContent = data.pesan;
          alertBox.classList.remove('hidden');
          document.getElementById('offline_konfirmasi_conflict').value = '1';
          btn.textContent = 'Simpan Meski Ada Peringatan';
          btn.disabled = false;
        } else {
          alertBox.className = 'bg-rose-50 border border-rose-300 rounded-lg p-3 text-sm text-rose-900';
          alertBox.textContent = data.pesan || 'Terjadi kesalahan.';
          alertBox.classList.remove('hidden');
          btn.disabled = false;
          btn.textContent = orig;
        }
      })
      .catch(function(){
        alertBox.className = 'bg-rose-50 border border-rose-300 rounded-lg p-3 text-sm text-rose-900';
        alertBox.textContent = 'Kesalahan jaringan.';
        alertBox.classList.remove('hidden');
        btn.disabled = false;
        btn.textContent = orig;
      });
    });
  }

  // ─── Form Blokir Tanggal ─────────────────────────────────────────────────
  var formBlokir = document.getElementById('formBlokir');
  if (formBlokir) {
    document.getElementById('blokir_mulai').addEventListener('change', function(){
      var v = this.value;
      if (v) {
        var next = new Date(v);
        next.setDate(next.getDate() + 1);
        document.getElementById('blokir_selesai').min = next.toISOString().split('T')[0];
      }
    });

    formBlokir.addEventListener('submit', function(e){
      e.preventDefault();
      var btn = document.getElementById('btnBlokirSubmit');
      var alertBox = document.getElementById('blokirAlertBox');
      btn.disabled = true;
      var orig = btn.textContent;
      btn.textContent = '...';

      var fd = new FormData(formBlokir);

      fetch('api/blokir-tanggal.php', {
        method: 'POST',
        body: fd
      })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.ok) {
          document.getElementById('modalBlokir').classList.add('hidden');
          if (typeof showToast === 'function') showToast(data.pesan || 'Blokir berhasil!', 'success');
          setTimeout(function(){ window.location.reload(); }, 1200);
        } else {
          alertBox.className = 'bg-rose-50 border border-rose-300 rounded-lg p-3 text-sm text-rose-900';
          alertBox.textContent = data.pesan || 'Terjadi kesalahan.';
          alertBox.classList.remove('hidden');
          btn.disabled = false;
          btn.textContent = orig;
        }
      })
      .catch(function(){
        alertBox.className = 'bg-rose-50 border border-rose-300 rounded-lg p-3 text-sm text-rose-900';
        alertBox.textContent = 'Kesalahan jaringan.';
        alertBox.classList.remove('hidden');
        btn.disabled = false;
        btn.textContent = orig;
      });
    });
  }

  // ─── SLIDE-OVER DRAWER STRUK / RINCIAN TAGIHAN ───────────────────────────
  let drawerTriggerBtn = null;
  let currentDrawerOrderId = '';
  let currentDrawerResvData = null;

  const drawer = document.getElementById('drawer-struk');
  const backdrop = document.getElementById('drawer-struk-backdrop');
  const drawerLoading = document.getElementById('drawer-loading');
  const drawerData = document.getElementById('drawer-data');
  const drawerError = document.getElementById('drawer-error');
  const btnCloseIcon = document.getElementById('btn-close-drawer-icon');
  const btnCloseBottom = document.getElementById('btn-close-drawer-bottom');
  const btnCopy = document.getElementById('btn-drawer-copy');
  const btnPrint = document.getElementById('btn-drawer-print');
  const btnWa = document.getElementById('btn-drawer-wa');
  const btnQuickCash = document.getElementById('btn-drawer-quick-cash');

  function openDrawer(id, triggerElement) {
    drawerTriggerBtn = triggerElement || null;
    currentDrawerOrderId = '';
    currentDrawerResvData = null;

    backdrop.classList.remove('hidden');
    drawer.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    requestAnimationFrame(function() {
      backdrop.classList.add('opacity-100');
      backdrop.classList.remove('opacity-0');
      drawer.classList.remove('translate-x-full');
    });

    drawerLoading.classList.remove('hidden');
    drawerData.classList.add('hidden');
    drawerError.classList.add('hidden');

    fetch('api/struk.php?id=' + encodeURIComponent(id))
      .then(function(res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function(resp) {
        if (!resp.ok || !resp.data) {
          throw new Error(resp.pesan || 'Data tidak ditemukan.');
        }
        renderDrawerContent(resp.data);
      })
      .catch(function(err) {
        drawerLoading.classList.add('hidden');
        drawerError.classList.remove('hidden');
        document.getElementById('drawer-error-msg').textContent = err.message || 'Gagal memuat data struk.';
      });

    setTimeout(function() {
      if (btnCloseIcon) btnCloseIcon.focus();
    }, 100);
  }

  function closeDrawer() {
    backdrop.classList.remove('opacity-100');
    backdrop.classList.add('opacity-0');
    drawer.classList.add('translate-x-full');

    setTimeout(function() {
      backdrop.classList.add('hidden');
      drawer.classList.add('hidden');
      document.body.style.overflow = '';
      if (drawerTriggerBtn && typeof drawerTriggerBtn.focus === 'function') {
        drawerTriggerBtn.focus();
      }
    }, 300);
  }

  function renderDrawerContent(d) {
    currentDrawerResvData = d;
    const isLunas = d.is_lunas;
    const pay = d.pembayaran || {};

    currentDrawerOrderId = pay.order_id || d.kode_reservasi;

    document.getElementById('drawer-struk-title').textContent = isLunas 
      ? '<?= t('payment.receipt_title') ?> #' + d.kode_reservasi 
      : '<?= t('payment.invoice_title') ?> #' + d.kode_reservasi;
    
    document.getElementById('drawer-order-id').textContent = currentDrawerOrderId;
    document.getElementById('drawer-waktu-bayar').textContent = pay.dibayar_pada_fmt 
      ? 'Waktu: ' + pay.dibayar_pada_fmt 
      : 'Batas: Check-in villa';

    const badgeContainer = document.getElementById('drawer-status-badge');
    if (isLunas) {
      badgeContainer.innerHTML = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300"><svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg><span>LUNAS</span></span>';
    } else {
      badgeContainer.innerHTML = '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-300">MENUNGGU</span>';
    }

    const sandboxNotice = document.getElementById('drawer-sandbox-notice');
    if (pay.is_sandbox) {
      sandboxNotice.classList.remove('hidden');
    } else {
      sandboxNotice.classList.add('hidden');
    }

    document.getElementById('drawer-guest-name').textContent = d.tamu.nama;
    document.getElementById('drawer-guest-contact').textContent = d.tamu.kontak;
    document.getElementById('drawer-villa-name').textContent = d.villa.nama;
    document.getElementById('drawer-period').textContent = d.jadwal.checkin_fmt + ' - ' + d.jadwal.checkout_fmt + ' (' + d.jadwal.malam + ' malam)';

    document.getElementById('drawer-rate').textContent = d.villa.harga_per_malam_fmt;
    document.getElementById('drawer-duration').textContent = d.jadwal.malam + ' Malam (' + d.jadwal.jumlah_tamu + ' Tamu)';
    document.getElementById('drawer-total').textContent = d.keuangan.estimasi_total_fmt;
    document.getElementById('drawer-paid').textContent = d.keuangan.total_dibayar_fmt;
    document.getElementById('drawer-unpaid').textContent = d.keuangan.sisa_tagihan_fmt;

    const paidRow = document.getElementById('drawer-paid-row');
    const unpaidRow = document.getElementById('drawer-unpaid-row');
    if (isLunas) {
      paidRow.classList.remove('hidden');
      unpaidRow.classList.add('hidden');
    } else {
      paidRow.classList.add('hidden');
      unpaidRow.classList.remove('hidden');
    }

    const methodCard = document.getElementById('drawer-payment-details-card');
    if (pay.metode_label) {
      methodCard.classList.remove('hidden');
      document.getElementById('drawer-method-label').textContent = pay.metode_label + (pay.metode_detail ? ' (' + pay.metode_detail + ')' : '');

      const txRefRow = document.getElementById('drawer-tx-ref-row');
      if (pay.transaction_ref) {
        txRefRow.classList.remove('hidden');
        document.getElementById('drawer-tx-ref').textContent = pay.transaction_ref;
      } else {
        txRefRow.classList.add('hidden');
      }

      const vaRow = document.getElementById('drawer-va-row');
      const expiryRow = document.getElementById('drawer-expiry-row');
      if (pay.kode_pembayaran) {
        vaRow.classList.remove('hidden');
        document.getElementById('drawer-va-bank').textContent = (pay.bank_atau_toko_label && pay.bank_atau_toko_label !== '-') ? pay.bank_atau_toko_label + ' · ' : '';
        document.getElementById('drawer-va-code').textContent = pay.kode_pembayaran_fmt || pay.kode_pembayaran;
        if (pay.kedaluwarsa_pada_fmt) {
          expiryRow.classList.remove('hidden');
          document.getElementById('drawer-expiry-time').textContent = pay.kedaluwarsa_pada_fmt;
        } else {
          expiryRow.classList.add('hidden');
        }
      } else {
        vaRow.classList.add('hidden');
        expiryRow.classList.add('hidden');
      }

      const recRow = document.getElementById('drawer-recorder-row');
      if (pay.dicatat_oleh) {
        recRow.classList.remove('hidden');
        document.getElementById('drawer-recorder').textContent = pay.dicatat_oleh;
      } else {
        recRow.classList.add('hidden');
      }

      const noteRow = document.getElementById('drawer-cash-note-row');
      if (pay.catatan) {
        noteRow.classList.remove('hidden');
        document.getElementById('drawer-cash-note').textContent = '"' + pay.catatan + '"';
      } else {
        noteRow.classList.add('hidden');
      }
    } else {
      methodCard.classList.add('hidden');
    }

    const unpaidBox = document.getElementById('drawer-unpaid-action-box');
    if (!isLunas && d.status_reservasi === 'dikonfirmasi') {
      unpaidBox.classList.remove('hidden');
    } else {
      unpaidBox.classList.add('hidden');
    }

    const historySec = document.getElementById('drawer-history-section');
    const historyList = document.getElementById('drawer-history-list');
    historyList.innerHTML = '';
    if (Array.isArray(d.riwayat) && d.riwayat.length > 1) {
      historySec.classList.remove('hidden');
      d.riwayat.forEach(function(h) {
        const item = document.createElement('div');
        item.className = 'p-2.5 bg-stone-50 rounded text-[11px] border border-stone-100 space-y-1';
        let vaInfo = '';
        if (h.kode_pembayaran) {
          vaInfo = '<div class="text-[10px] text-stone-600 font-mono font-medium">VA: ' + escapeHtml(h.kode_pembayaran_fmt || h.kode_pembayaran) + (h.bank_atau_toko_label ? ' (' + escapeHtml(h.bank_atau_toko_label) + ')' : '') + '</div>';
        }
        item.innerHTML = '<div class="flex items-center justify-between"><span class="font-mono font-semibold text-stone-800">' + escapeHtml(h.order_id) + '</span><span class="text-stone-400 text-[10px]">' + escapeHtml(h.dibuat_pada_fmt || '') + '</span></div>' +
          '<div class="flex items-center justify-between"><span>' + escapeHtml(h.metode_label) + ' &bull; <strong>' + escapeHtml(h.jumlah_fmt) + '</strong></span>' + h.status_badge + '</div>' + vaInfo;
        historyList.appendChild(item);
      });
    } else {
      historySec.classList.add('hidden');
    }

    btnPrint.href = 'struk-pembayaran.php?id=' + encodeURIComponent(d.id_reservasi) + '&print=1';
    btnWa.href = d.wa_link || '#';

    drawerLoading.classList.add('hidden');
    drawerData.classList.remove('hidden');
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, function(m) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];
    });
  }

  document.querySelectorAll('.btn-open-struk').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      if (e.ctrlKey || e.metaKey || e.shiftKey) return;
      e.preventDefault();
      const id = this.getAttribute('data-id');
      openDrawer(id, this);
    });
  });

  if (btnCloseIcon) btnCloseIcon.addEventListener('click', closeDrawer);
  if (btnCloseBottom) btnCloseBottom.addEventListener('click', closeDrawer);
  if (backdrop) backdrop.addEventListener('click', closeDrawer);

  if (btnCopy) {
    btnCopy.addEventListener('click', function() {
      if (!currentDrawerOrderId) return;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(currentDrawerOrderId).then(function() {
          if (typeof showToast === 'function') {
            showToast('<?= t('payment.copied_toast') ?>', 'success');
          }
        });
      } else {
        const temp = document.createElement('input');
        temp.value = currentDrawerOrderId;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        document.body.removeChild(temp);
        if (typeof showToast === 'function') {
          showToast('<?= t('payment.copied_toast') ?>', 'success');
        }
      }
    });
  }

  const btnDrawerCopyVa = document.getElementById('btn-drawer-copy-va');
  if (btnDrawerCopyVa) {
    btnDrawerCopyVa.addEventListener('click', function() {
      if (!currentDrawerResvData || !currentDrawerResvData.pembayaran || !currentDrawerResvData.pembayaran.kode_pembayaran) return;
      const cleanCode = String(currentDrawerResvData.pembayaran.kode_pembayaran).replace(/\s+/g, '');
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(cleanCode).then(function() {
          if (typeof showToast === 'function') showToast('<?= t('payment.va_copied_toast') ?>', 'success');
        });
      } else {
        const temp = document.createElement('input');
        temp.value = cleanCode;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        document.body.removeChild(temp);
        if (typeof showToast === 'function') showToast('<?= t('payment.va_copied_toast') ?>', 'success');
      }
    });
  }

  if (btnQuickCash) {
    btnQuickCash.addEventListener('click', function() {
      if (!currentDrawerResvData) return;
      const d = currentDrawerResvData;
      closeDrawer();
      setTimeout(function() {
        document.getElementById('modal-tunai-id').value = d.id_reservasi;
        document.getElementById('modal-tunai-nama').textContent = d.tamu.nama;
        document.getElementById('modal-tunai-nominal-fmt').textContent = d.keuangan.estimasi_total_fmt;
        document.getElementById('modal-tunai-tanggal').value = getCurrentLocalDateTime();
        document.getElementById('modal-tunai-catatan').value = '';
        checkKonfirmasi.checked = false;
        btnSubmitTunai.disabled = true;
        modalTunai.classList.remove('hidden');
      }, 350);
    });
  }

  // Keyboard ESC menutup modal dan drawer
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
      if (drawer && !drawer.classList.contains('hidden')) {
        closeDrawer();
        return;
      }
      document.getElementById('modalOffline')?.classList.add('hidden');
      document.getElementById('modalBlokir')?.classList.add('hidden');
      document.getElementById('modal-catat-tunai')?.classList.add('hidden');
    }
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/layout/foot.php'; ?>