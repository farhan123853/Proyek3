<?php
/**
 * Formulir Pengajuan Reservasi Villa — Villa Joglo Masayu
 * Kebutuhan: F-06, F-07, F-08, F-09, T-04, T-05, T-06
 */

require_once __DIR__ . '/includes/bootstrap.php';

// F-06: Wajib login untuk melakukan reservasi
require_login('login.php');

$pdo = get_db();

// Ambil daftar tipe villa untuk dropdown
$stmtVillas = $pdo->query("SELECT * FROM `tipe_villa` ORDER BY `id_tipe` ASC");
$villasList = $stmtVillas->fetchAll();

// Tangkap pre-selected villa dari query param (misal: ?villa=joglo-saren)
$preselectSlug = trim($_GET['villa'] ?? '');
$defaultIdTipe = 0;
if ($preselectSlug) {
    foreach ($villasList as $v) {
        if ($v['slug'] === $preselectSlug) {
            $defaultIdTipe = (int)$v['id_tipe'];
            break;
        }
    }
}
if (!$defaultIdTipe && !empty($villasList)) {
    $defaultIdTipe = (int)$villasList[0]['id_tipe'];
}

$errors = [];
$idTipe     = (int)($_POST['id_tipe'] ?? $defaultIdTipe);
$checkin    = trim($_POST['tanggal_checkin'] ?? '');
$checkout   = trim($_POST['tanggal_checkout'] ?? '');
$jumlahTamu = (int)($_POST['jumlah_tamu'] ?? 2);
$catatan    = trim($_POST['catatan'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // 1. Validasi Keberadaan Tipe Villa
    $selectedVilla = null;
    foreach ($villasList as $v) {
        if ((int)$v['id_tipe'] === $idTipe) {
            $selectedVilla = $v;
            break;
        }
    }

    if (!$selectedVilla) {
        $errors['id_tipe'] = 'Silakan pilih tipe villa yang valid.';
    }

    // 2. Validasi Tanggal
    $today = date('Y-m-d');
    if (empty($checkin)) {
        $errors['tanggal_checkin'] = 'Tanggal check-in wajib diisi.';
    } elseif ($checkin < $today) {
        $errors['tanggal_checkin'] = 'Tanggal check-in tidak boleh di masa lampau.';
    }

    if (empty($checkout)) {
        $errors['tanggal_checkout'] = 'Tanggal check-out wajib diisi.';
    } elseif (!empty($checkin) && $checkout <= $checkin) {
        $errors['tanggal_checkout'] = 'Tanggal check-out harus setelah tanggal check-in (minimal 1 malam menginap).';
    }

    // 3. Validasi Jumlah Tamu
    if ($selectedVilla) {
        $kapasitasMaks = (int)$selectedVilla['kapasitas_maks'];
        $kapasitasStd  = (int)$selectedVilla['kapasitas_standar'];

        if ($jumlahTamu < 1) {
            $errors['jumlah_tamu'] = 'Jumlah tamu minimal 1 orang.';
        } elseif ($jumlahTamu > $kapasitasMaks) {
            $errors['jumlah_tamu'] = "Jumlah tamu melebihi kapasitas maksimum ({$kapasitasMaks} tamu) untuk {$selectedVilla['nama']}.";
        }
    }

    // 4. Periksa Ketersediaan Unit Berdasarkan Reservasi "dikonfirmasi"
    if (empty($errors) && $selectedVilla) {
        $sqlOverlap = "SELECT COUNT(*) FROM `reservasi` 
                       WHERE `id_tipe` = :id_tipe 
                         AND `status` = 'dikonfirmasi' 
                         AND `tanggal_checkin` < :checkout 
                         AND `tanggal_checkout` > :checkin";
        $stmtCek = $pdo->prepare($sqlOverlap);
        $stmtCek->execute([
            'id_tipe'  => $idTipe,
            'checkout' => $checkout,
            'checkin'  => $checkin
        ]);
        $terpesan = (int)$stmtCek->fetchColumn();
        $unitTersedia = (int)$selectedVilla['jumlah_unit'];

        if ($terpesan >= $unitTersedia) {
            $errors['ketersediaan'] = "Maaf, seluruh unit untuk {$selectedVilla['nama']} telah terpesan pada rentang tanggal tersebut. Silakan pilih tanggal atau tipe villa lain.";
        }
    }

    // 5. Hitung Snapshot Tarif dan Simpan Reservasi
    if (empty($errors) && $selectedVilla) {
        $rincian = hitung_rincian_tarif(
            $checkin,
            $checkout,
            $jumlahTamu,
            (int)$selectedVilla['kapasitas_standar'],
            (int)$selectedVilla['kapasitas_maks'],
            (float)$selectedVilla['harga_weekday'],
            (float)$selectedVilla['harga_weekend'],
            (float)$selectedVilla['harga_extrabed']
        );

        if (!$rincian) {
            $errors['umum'] = 'Terjadi kesalahan saat menghitung rincian durasi menginap. Silakan periksa kembali tanggal yang Anda masukkan.';
        } else {
            // F-09: Simpan status awal "menunggu"
            $sqlInsert = "INSERT INTO `reservasi` (
                            `id_pelanggan`, `id_tipe`, `tanggal_checkin`, `tanggal_checkout`,
                            `jumlah_tamu`, `catatan`, `status`,
                            `harga_weekday_saat_pesan`, `harga_weekend_saat_pesan`, `harga_extrabed_saat_pesan`,
                            `estimasi_total`, `dibuat_pada`
                          ) VALUES (?, ?, ?, ?, ?, ?, 'menunggu', ?, ?, ?, ?, NOW())";

            $stmtInsert = $pdo->prepare($sqlInsert);
            $stmtInsert->execute([
                current_user_id(),
                $idTipe,
                $checkin,
                $checkout,
                $jumlahTamu,
                $catatan,
                $rincian['tarif_weekday'],
                $rincian['tarif_weekend'],
                $rincian['tarif_extrabed'],
                $rincian['estimasi_total']
            ]);

            $idReservasi = $pdo->lastInsertId();

            flash_set('success', "Permintaan reservasi berhasil diajukan! Kode Reservasi: #RSV-{$idReservasi}. Status pesanan Anda saat ini sedang menunggu konfirmasi pengelola.");
            redirect('reservasi-saya.php');
        }
    }
}

$pageTitle = 'Formulir Reservasi — Villa Joglo Masayu';
$pageDesc  = 'Ajukan pemesanan villa joglo premium di Joglo Masayu Sleman secara online.';
$bodyClass = 'page-villa';
$extraScripts = ['assets/js/reservasi.js'];

require_once __DIR__ . '/includes/layout/head.php';
?>

<main class="container" style="padding-top: calc(var(--header-h-scrolled) + 3rem); padding-bottom: 5rem;">
  <div style="max-width: 760px; margin: 0 auto;">

    <div class="form-card">
      <p class="eyebrow">Reservasi Online</p>
      <h1 class="form-title">Pemesanan Villa Joglo</h1>
      <p class="form-desc">
        Pilih tipe villa, tanggal menginap, dan jumlah tamu. Ketersediaan unit akan diperiksa secara otomatis.
      </p>

      <?php if (isset($errors['ketersediaan'])): ?>
        <div class="alert alert-danger" role="alert">
          <span><?= e($errors['ketersediaan']) ?></span>
        </div>
      <?php endif; ?>

      <?php if (isset($errors['umum'])): ?>
        <div class="alert alert-danger" role="alert">
          <span><?= e($errors['umum']) ?></span>
        </div>
      <?php endif; ?>

      <!-- Wadah status ketersediaan via AJAX -->
      <div id="ketersediaanStatusBox"></div>

      <form method="POST" action="reservasi.php" novalidate id="formReservasi">
        <?= csrf_field() ?>

        <div class="form-group">
          <label class="form-label" for="id_tipe">Pilih Tipe Villa <span class="required">*</span></label>
          <select id="id_tipe" name="id_tipe" class="form-control" required>
            <?php foreach ($villasList as $v): ?>
              <option value="<?= e($v['id_tipe']) ?>"
                      data-std="<?= e($v['kapasitas_standar']) ?>"
                      data-max="<?= e($v['kapasitas_maks']) ?>"
                      data-weekday="<?= e($v['harga_weekday']) ?>"
                      data-weekend="<?= e($v['harga_weekend']) ?>"
                      <?= ($idTipe === (int)$v['id_tipe']) ? 'selected' : '' ?>>
                <?= e($v['nama']) ?> — Mulai <?= format_rupiah($v['harga_weekday']) ?> / malam (Kapasitas: <?= e($v['kapasitas_standar']) ?>-<?= e($v['kapasitas_maks']) ?> tamu)
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['id_tipe'])): ?>
            <div class="form-error"><?= e($errors['id_tipe']) ?></div>
          <?php endif; ?>
        </div>

        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label" for="tanggal_checkin">Tanggal Check-In <span class="required">*</span></label>
            <input type="date" id="tanggal_checkin" name="tanggal_checkin" class="form-control" value="<?= e($checkin) ?>" required />
            <div class="form-hint">Waktu check-in mulai 14.00 WIB.</div>
            <?php if (isset($errors['tanggal_checkin'])): ?>
              <div class="form-error"><?= e($errors['tanggal_checkin']) ?></div>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label class="form-label" for="tanggal_checkout">Tanggal Check-Out <span class="required">*</span></label>
            <input type="date" id="tanggal_checkout" name="tanggal_checkout" class="form-control" value="<?= e($checkout) ?>" required />
            <div class="form-hint">Waktu check-out maksimal 12.00 WIB.</div>
            <?php if (isset($errors['tanggal_checkout'])): ?>
              <div class="form-error"><?= e($errors['tanggal_checkout']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="jumlah_tamu">Jumlah Tamu <span class="required">*</span></label>
          <input type="number" id="jumlah_tamu" name="jumlah_tamu" class="form-control" value="<?= e($jumlahTamu) ?>" min="1" max="10" required />
          <div class="form-hint" id="tamuHint">Tamu melebihi kapasitas standar akan dikenakan biaya extra bed per malam.</div>
          <?php if (isset($errors['jumlah_tamu'])): ?>
            <div class="form-error"><?= e($errors['jumlah_tamu']) ?></div>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="catatan">Catatan / Permintaan Khusus (Opsional)</label>
          <textarea id="catatan" name="catatan" class="form-control" rows="3" placeholder="Contoh: Perkiraan waktu tiba pukul 15.00 WIB, mohon disiapkan extra bed."><?= e($catatan) ?></textarea>
        </div>

        <!-- Box Rincian Estimasi Biaya Real-time -->
        <div id="reservasiPreviewBox" class="estimate-box" style="display: none;">
          <span class="estimate-box-label">Rincian Durasi & Estimasi Biaya</span>
          
          <div style="font-size: 14px; margin-bottom: 0.5rem; color: var(--wood);">
            Durasi: <strong id="previewTotalMalam" style="color: var(--teak);">-</strong>
          </div>

          <div style="display: flex; justify-content: space-between; font-size: 13.5px; padding: 4px 0; border-bottom: 1px dashed rgba(59,36,22,0.1);">
            <span>Subtotal Malam Weekday (Senin-Kamis):</span>
            <strong id="previewSubtotalWeekday">-</strong>
          </div>

          <div style="display: flex; justify-content: space-between; font-size: 13.5px; padding: 4px 0; border-bottom: 1px dashed rgba(59,36,22,0.1);">
            <span>Subtotal Malam Weekend (Jumat-Minggu):</span>
            <strong id="previewSubtotalWeekend">-</strong>
          </div>

          <div id="previewExtraBedRow" style="display: none; justify-content: space-between; font-size: 13.5px; padding: 4px 0; border-bottom: 1px dashed rgba(59,36,22,0.1);">
            <span id="previewExtraBedInfo">Biaya Extra Bed:</span>
            <strong id="previewSubtotalExtra">-</strong>
          </div>

          <div style="display: flex; justify-content: space-between; align-items: baseline; margin-top: 0.75rem;">
            <span style="font-size: 15px; font-weight: 700; color: var(--teak);">Estimasi Total:</span>
            <span class="estimate-box-value" id="previewEstimasiTotal">-</span>
          </div>

          <small class="estimate-disclaimer">
            * Catatan: Nilai ini merupakan estimasi perhitungan tarif menginap, bukan tanda bukti penerimaan uang masuk aktual. Pembayaran dan konfirmasi akhir dikoordinasikan langsung bersama pihak pengelola villa.
          </small>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-gold" style="width: 100%; justify-content: center;">Ajukan Permintaan Reservasi</button>
        </div>
      </form>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/layout/foot.php'; ?>

