<?php
/**
 * Kalender Hunian (Occupancy Calendar) — Villa Joglo Masayu
 * Kebutuhan: Tahap 11-G.8
 *
 * Hak Akses:
 * - Pengelola: Akses penuh (lihat nama tamu, aksi WhatsApp, catat tunai, batalkan offline).
 * - Pemilik & Admin: READ-ONLY tanpa tombol aksi.
 *
 * Tampilan:
 * - >= 640px: Timeline grid per tipe villa untuk bulan terpilih.
 * - < 640px: Daftar agenda per hari (tanpa scroll horizontal pada halaman).
 * - Legenda berpola + teks (tidak bergantung warna saja).
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role(['pengelola', 'pemilik', 'admin'], 'login.php');

$pdo = get_db();
$currentUserRole = current_role();
$isPengelola = ($currentUserRole === 'pengelola');

// ─── Parameter Bulan & Tahun ────────────────────────────────────────────────
$bulanSekarang = (int)date('n');
$tahunSekarang = (int)date('Y');

$bulan = (int)($_GET['bulan'] ?? $bulanSekarang);
$tahun = (int)($_GET['tahun'] ?? $tahunSekarang);

if ($bulan < 1 || $bulan > 12) $bulan = $bulanSekarang;
if ($tahun < 2020 || $tahun > 2100) $tahun = $tahunSekarang;

$totalHari = (int)date('t', strtotime(sprintf('%04d-%02d-01', $tahun, $bulan)));
$tglAwalBulan = sprintf('%04d-%02d-01', $tahun, $bulan);
$tglAkhirBulan = sprintf('%04d-%02d-%02d', $tahun, $bulan, $totalHari);
$tglNextMonth = date('Y-m-d', strtotime("$tglAkhirBulan +1 day"));

// Navigasi prev/next
$prevMonth = $bulan - 1;
$prevYear = $tahun;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }

$nextMonth = $bulan + 1;
$nextYear = $tahun;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

// Nama Bulan
$namaBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
if (get_active_lang() === 'en') {
    $namaBulan = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];
}

// ─── Ambil Master Tipe Villa ────────────────────────────────────────────────
$stmtV = $pdo->query("SELECT * FROM tipe_villa ORDER BY id_tipe ASC");
$villasList = $stmtV->fetchAll(PDO::FETCH_ASSOC);

// ─── Ambil Reservasi yang Tumpang Tindih Bulan Ini ──────────────────────────
$stmtR = $pdo->prepare("
    SELECT r.*, v.nama AS nama_villa,
           COALESCE(p.nama, r.nama_tamu) AS nama_tamu_efektif,
           COALESCE(p.kontak, r.kontak_tamu) AS kontak_tamu_efektif,
           (SELECT status FROM pembayaran WHERE id_reservasi = r.id_reservasi AND status = 'lunas' LIMIT 1) AS bayar_lunas
    FROM reservasi r
    JOIN tipe_villa v ON r.id_tipe = v.id_tipe
    LEFT JOIN pengguna p ON r.id_pelanggan = p.id_pengguna
    WHERE r.status IN ('menunggu', 'dikonfirmasi')
      AND r.tanggal_checkin < ?
      AND r.tanggal_checkout > ?
    ORDER BY r.tanggal_checkin ASC
");
$stmtR->execute([$tglNextMonth, $tglAwalBulan]);
$reservasiList = $stmtR->fetchAll(PDO::FETCH_ASSOC);

// ─── Ambil Blokir Tanggal yang Aktif Bulan Ini ──────────────────────────────
$blokirList = [];
try {
    $stmtB = $pdo->prepare("
        SELECT b.*, v.nama AS nama_villa
        FROM blokir_tanggal b
        JOIN tipe_villa v ON b.id_tipe = v.id_tipe
        WHERE b.dibatalkan_pada IS NULL
          AND b.tanggal_mulai < ?
          AND b.tanggal_selesai > ?
        ORDER BY b.tanggal_mulai ASC
    ");
    $stmtB->execute([$tglNextMonth, $tglAwalBulan]);
    $blokirList = $stmtB->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Tabel blokir belum tersedia
}

// ─── Kelompokkan Data per Tipe Villa ─────────────────────────────────────────
$dataPerTipe = [];
foreach ($villasList as $v) {
    $dataPerTipe[$v['id_tipe']] = [
        'villa' => $v,
        'reservasi' => [],
        'blokir' => []
    ];
}
foreach ($reservasiList as $r) {
    if (isset($dataPerTipe[$r['id_tipe']])) {
        $dataPerTipe[$r['id_tipe']]['reservasi'][] = $r;
    }
}
foreach ($blokirList as $b) {
    if (isset($dataPerTipe[$b['id_tipe']])) {
        $dataPerTipe[$b['id_tipe']]['blokir'][] = $b;
    }
}

// ─── Bangun Data Agenda Harian untuk Tampilan Mobile (< 640px) ───────────────
$agendaHarian = [];
for ($h = 1; $h <= $totalHari; $h++) {
    $tglStr = sprintf('%04d-%02d-%02d', $tahun, $bulan, $h);
    $events = [];

    foreach ($reservasiList as $r) {
        if ($r['tanggal_checkin'] <= $tglStr && $r['tanggal_checkout'] > $tglStr) {
            $events[] = [
                'kategori' => ($r['status'] === 'menunggu') ? 'menunggu' : ($r['sumber'] === 'online' ? 'online' : 'offline'),
                'tipe'     => $r['nama_villa'],
                'kode'     => "#RSV-{$r['id_reservasi']}",
                'nama'     => $isPengelola ? $r['nama_tamu_efektif'] : 'Tamu Reservasi',
                'kontak'   => $isPengelola ? $r['kontak_tamu_efektif'] : '',
                'checkin'  => $r['tanggal_checkin'],
                'checkout' => $r['tanggal_checkout'],
                'lunas'    => ($r['bayar_lunas'] === 'lunas'),
                'id_res'   => $r['id_reservasi'],
                'sumber'   => $r['sumber']
            ];
        }
    }

    foreach ($blokirList as $b) {
        if ($b['tanggal_mulai'] <= $tglStr && $b['tanggal_selesai'] > $tglStr) {
            $events[] = [
                'kategori' => 'blokir',
                'tipe'     => $b['nama_villa'],
                'kode'     => "#BLK-{$b['id_blokir']}",
                'nama'     => $b['alasan'],
                'kontak'   => '',
                'checkin'  => $b['tanggal_mulai'],
                'checkout' => $b['tanggal_selesai'],
                'lunas'    => false,
                'id_res'   => 0,
                'sumber'   => 'blokir'
            ];
        }
    }

    if (!empty($events)) {
        $agendaHarian[$tglStr] = [
            'hari' => $h,
            'nama_hari' => date('D', strtotime($tglStr)),
            'events' => $events
        ];
    }
}

$pageTitle = t('occupancy.title') . ' — Villa Joglo Masayu';
$pageDesc  = 'Pantau jadwal hunian kamar, reservasi online, tamu offline, dan pemblokiran tanggal.';
$bodyClass = 'page-villa';

require_once __DIR__ . '/includes/layout/head.php';
?>

<main class="container mx-auto px-4" style="padding-top: calc(var(--header-h-scrolled) + 2rem); padding-bottom: 5rem; max-width: 1400px;">

  <!-- Header & Kontrol -->
  <div class="flex flex-col md:flex-row md:items-end justify-between mb-6 gap-4">
    <div>
      <div class="flex items-center gap-2 mb-1">
        <a href="<?= $currentUserRole === 'pemilik' ? 'dashboard/pemilik.php' : ($currentUserRole === 'admin' ? 'dashboard/admin.php' : 'dashboard/pengelola.php') ?>" class="text-xs text-amber-800 hover:underline font-semibold flex items-center gap-1">
          &larr; <?= t('occupancy.back_dashboard') ?>
        </a>
        <?php if (!$isPengelola): ?>
          <span class="text-[10px] uppercase font-bold bg-stone-100 text-stone-600 border border-stone-200 px-2 py-0.5 rounded-full">
            <?= t('owner.readonly_badge') ?>
          </span>
        <?php endif; ?>
      </div>
      <h1 class="text-2xl md:text-3xl font-serif text-stone-900 mb-1"><?= t('occupancy.title') ?></h1>
      <p class="text-xs md:text-sm text-stone-600 mb-0"><?= t('occupancy.subtitle') ?></p>
    </div>

    <!-- Navigasi Bulan & Aksi Pengelola -->
    <div class="flex items-center gap-3 flex-wrap">
      <div class="inline-flex items-center bg-white border border-stone-300 rounded-xl shadow-sm p-1">
        <a href="kalender-hunian.php?bulan=<?= $prevMonth ?>&tahun=<?= $prevYear ?>" class="p-2 text-stone-600 hover:text-stone-900 hover:bg-stone-100 rounded-lg min-h-[44px] min-w-[44px] flex items-center justify-center" aria-label="Bulan sebelumnya">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <span class="px-4 text-sm font-serif font-bold text-stone-900 whitespace-nowrap">
          <?= $namaBulan[$bulan] ?> <?= $tahun ?>
        </span>
        <a href="kalender-hunian.php?bulan=<?= $nextMonth ?>&tahun=<?= $nextYear ?>" class="p-2 text-stone-600 hover:text-stone-900 hover:bg-stone-100 rounded-lg min-h-[44px] min-w-[44px] flex items-center justify-center" aria-label="Bulan berikutnya">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
      </div>

      <?php if ($isPengelola): ?>
        <a href="dashboard/pengelola.php" class="btn btn-sm btn-gold text-xs py-2 px-3 shadow-sm min-h-[44px] flex items-center gap-1.5">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          <?= t('offline.btn_tambah') ?>
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Legenda Aksesibel (Bukan hanya warna) -->
  <div class="bg-white border border-stone-200 rounded-xl p-4 mb-6 shadow-sm flex items-center justify-between gap-4 flex-wrap text-xs">
    <span class="font-bold text-stone-700"><?= t('occupancy.legend_label') ?>:</span>
    <div class="flex items-center gap-4 flex-wrap">
      <div class="flex items-center gap-1.5">
        <span class="w-4 h-4 rounded bg-sky-600 inline-block shadow-sm"></span>
        <span class="text-stone-700 font-medium"><?= t('occupancy.legend_online') ?></span>
      </div>
      <div class="flex items-center gap-1.5">
        <span class="w-4 h-4 rounded bg-violet-700 inline-block shadow-sm"></span>
        <span class="text-stone-700 font-medium"><?= t('occupancy.legend_offline') ?></span>
      </div>
      <div class="flex items-center gap-1.5">
        <span class="w-4 h-4 rounded border border-amber-400 bg-amber-100 inline-block shadow-sm" style="background-image: repeating-linear-gradient(45deg, #fef3c7, #fef3c7 4px, #fde68a 4px, #fde68a 8px);"></span>
        <span class="text-stone-700 font-medium"><?= t('occupancy.legend_pending') ?></span>
      </div>
      <div class="flex items-center gap-1.5">
        <span class="w-4 h-4 rounded border border-stone-400 bg-stone-700 text-stone-200 inline-flex items-center justify-center text-[10px] font-bold">✕</span>
        <span class="text-stone-700 font-medium"><?= t('occupancy.legend_blocked') ?></span>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════
       1. TAMPILAN DESKTOP & TABLET (>= 640px): TIMELINE PER TIPE VILLA
  ════════════════════════════════════════════════════════════════════════ -->
  <div class="hidden sm:block bg-white border border-stone-200 rounded-2xl shadow-sm overflow-hidden mb-8">
    <div class="overflow-x-auto">
      <table class="w-full border-collapse text-xs select-none min-w-[900px]">
        <thead>
          <tr class="bg-stone-50 border-b border-stone-200 text-stone-600">
            <th class="p-3 text-left w-44 sticky left-0 bg-stone-50 z-10 border-r border-stone-200 font-serif font-bold text-stone-900">
              <?= t('occupancy.col_villa') ?>
            </th>
            <?php for ($d = 1; $d <= $totalHari; $d++): ?>
              <?php
                $tglCol = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);
                $isWk = (date('N', strtotime($tglCol)) >= 5);
                $isToday = ($tglCol === date('Y-m-d'));
              ?>
              <th class="p-1 text-center border-r border-stone-100 w-8 <?= $isToday ? 'bg-amber-100 text-amber-900 font-bold' : ($isWk ? 'bg-stone-100/70 font-semibold' : '') ?>">
                <div class="text-[10px] leading-tight text-stone-400"><?= substr(date('D', strtotime($tglCol)), 0, 1) ?></div>
                <div class="text-xs"><?= $d ?></div>
              </th>
            <?php endfor; ?>
          </tr>
        </thead>
        <tbody class="divide-y divide-stone-200">
          <?php foreach ($dataPerTipe as $idTipe => $item): ?>
            <?php
              $v = $item['villa'];
              $resListVilla = $item['reservasi'];
              $blkListVilla = $item['blokir'];
            ?>
            <tr class="hover:bg-stone-50/50">
              <!-- Label Villa Sticky -->
              <td class="p-3 font-semibold text-stone-900 sticky left-0 bg-white border-r border-stone-200 z-10 shadow-sm">
                <div class="font-serif font-bold text-sm text-stone-900"><?= e($v['nama']) ?></div>
                <div class="text-[11px] text-stone-400 mt-0.5">Kapasitas maks <?= e($v['kapasitas_maks']) ?> tamu</div>
              </td>

              <!-- Baris Hari-Hari -->
              <?php for ($d = 1; $d <= $totalHari; $d++): ?>
                <?php
                  $tglCell = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);
                  $isWk = (date('N', strtotime($tglCell)) >= 5);
                  $isToday = ($tglCell === date('Y-m-d'));

                  // Cek apakah ada reservasi pada malam ini (checkin <= D < checkout)
                  $activeRes = null;
                  foreach ($resListVilla as $r) {
                      if ($r['tanggal_checkin'] <= $tglCell && $r['tanggal_checkout'] > $tglCell) {
                          $activeRes = $r;
                          break;
                      }
                  }

                  // Cek blokir
                  $activeBlk = null;
                  foreach ($blkListVilla as $b) {
                      if ($b['tanggal_mulai'] <= $tglCell && $b['tanggal_selesai'] > $tglCell) {
                          $activeBlk = $b;
                          break;
                      }
                  }
                ?>
                <td class="p-0 border-r border-stone-100 h-14 text-center align-middle relative <?= $isToday ? 'bg-amber-50/40' : ($isWk ? 'bg-stone-50/40' : '') ?>">
                  <?php if ($activeBlk): ?>
                    <div class="w-full h-10 bg-stone-700 text-stone-100 border-y border-stone-800 flex items-center justify-center text-[10px] font-bold px-1 overflow-hidden" title="Blokir: <?= e($activeBlk['alasan']) ?>">
                      ✕ Blokir
                    </div>
                  <?php elseif ($activeRes): ?>
                    <?php
                      $isMenunggu = ($activeRes['status'] === 'menunggu');
                      $isOffline = ($activeRes['sumber'] !== 'online');
                      $badgeClass = $isMenunggu
                        ? 'bg-amber-100 text-amber-950 border-y border-amber-400 font-semibold'
                        : ($isOffline ? 'bg-violet-700 text-white font-semibold' : 'bg-sky-600 text-white font-semibold');
                      $patternStyle = $isMenunggu ? 'background-image: repeating-linear-gradient(45deg, #fef3c7, #fef3c7 4px, #fde68a 4px, #fde68a 8px);' : '';
                      $tamuLabel = $isPengelola ? e($activeRes['nama_tamu_efektif']) : 'Tamu';
                    ?>
                    <div class="w-full h-10 <?= $badgeClass ?> flex flex-col items-center justify-center text-[10px] px-0.5 overflow-hidden leading-tight cursor-default shadow-sm"
                         style="<?= $patternStyle ?>"
                         title="#RSV-<?= $activeRes['id_reservasi'] ?>: <?= $tamuLabel ?> (<?= date('d/m', strtotime($activeRes['tanggal_checkin'])) ?> - <?= date('d/m', strtotime($activeRes['tanggal_checkout'])) ?>)">
                      <span class="truncate max-w-full font-bold">#<?= $activeRes['id_reservasi'] ?></span>
                      <span class="truncate max-w-full text-[9px] opacity-90"><?= $tamuLabel ?></span>
                    </div>
                  <?php else: ?>
                    <span class="text-[10px] text-stone-200">·</span>
                  <?php endif; ?>
                </td>
              <?php endfor; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════
       2. TAMPILAN MOBILE (< 640px): DAFTAR AGENDA PER HARI (Tanpa horizontal scroll)
  ════════════════════════════════════════════════════════════════════════ -->
  <div class="block sm:hidden space-y-4 mb-8">
    <h2 class="text-base font-serif font-bold text-stone-900 mb-2"><?= t('occupancy.agenda_title') ?> (<?= $namaBulan[$bulan] ?> <?= $tahun ?>)</h2>

    <?php if (empty($agendaHarian)): ?>
      <div class="bg-white border border-stone-200 rounded-2xl p-6 text-center text-stone-500 text-xs">
        <?= t('occupancy.no_events_month') ?>
      </div>
    <?php else: ?>
      <?php foreach ($agendaHarian as $tgl => $agenda): ?>
        <div class="bg-white border border-stone-200 rounded-2xl p-4 shadow-sm">
          <div class="flex items-center justify-between pb-2 mb-3 border-b border-stone-100">
            <div class="flex items-center gap-2">
              <span class="w-8 h-8 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 font-serif font-bold text-sm flex items-center justify-center">
                <?= $agenda['hari'] ?>
              </span>
              <div>
                <span class="font-bold text-xs text-stone-900"><?= date('l, d F Y', strtotime($tgl)) ?></span>
              </div>
            </div>
            <span class="text-[11px] text-stone-500 font-semibold"><?= count($agenda['events']) ?> jadwal</span>
          </div>

          <div class="space-y-2">
            <?php foreach ($agenda['events'] as $ev): ?>
              <?php
                $catClass = 'border-stone-200 bg-stone-50 text-stone-800';
                $badgeName = '';
                if ($ev['kategori'] === 'online') {
                    $catClass = 'border-sky-300 bg-sky-50 text-sky-950';
                    $badgeName = 'Online';
                } elseif ($ev['kategori'] === 'offline') {
                    $catClass = 'border-violet-300 bg-violet-50 text-violet-950';
                    $badgeName = 'Offline';
                } elseif ($ev['kategori'] === 'menunggu') {
                    $catClass = 'border-amber-300 bg-amber-50 text-amber-950';
                    $badgeName = 'Menunggu';
                } elseif ($ev['kategori'] === 'blokir') {
                    $catClass = 'border-stone-400 bg-stone-200 text-stone-900';
                    $badgeName = 'Blokir';
                }
              ?>
              <div class="border rounded-xl p-3 <?= $catClass ?> text-xs">
                <div class="flex items-center justify-between font-bold mb-1">
                  <span><?= e($ev['tipe']) ?></span>
                  <span class="text-[10px] font-semibold border px-1.5 py-0.5 rounded bg-white/70"><?= $badgeName ?></span>
                </div>
                <div class="font-semibold text-sm mb-0.5"><?= e($ev['nama']) ?></div>
                <div class="text-[11px] text-stone-600">
                  <?= date('d/m/Y', strtotime($ev['checkin'])) ?> s.d. <?= date('d/m/Y', strtotime($ev['checkout'])) ?>
                </div>

                <?php if ($isPengelola && !empty($ev['kontak'])): ?>
                  <?php $nomorWa = preg_replace('/^0/', '62', preg_replace('/[^\d]/', '', $ev['kontak'])); ?>
                  <div class="mt-2 pt-2 border-t border-black/10 flex items-center justify-between">
                    <span class="text-[11px] font-mono"><?= e($ev['kontak']) ?></span>
                    <a href="https://wa.me/<?= $nomorWa ?>" target="_blank" rel="noopener" class="btn btn-sm btn-ghost text-[11px] py-1 px-2 border border-stone-300 flex items-center gap-1">
                      WhatsApp &rarr;
                    </a>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</main>

<?php require_once __DIR__ . '/includes/layout/foot.php'; ?>
