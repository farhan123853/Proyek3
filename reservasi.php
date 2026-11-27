<?php
/**
 * Formulir Pengajuan Reservasi Villa — Villa Joglo Masayu
 * Kebutuhan: F-06, F-07, F-08, F-09, T-04, T-05, T-06
 */

require_once __DIR__ . '/includes/bootstrap.php';

// F-06: Wajib login untuk melakukan reservasi
require_login('login.php');

// Pelanggan wajib memverifikasi email sebelum membuat reservasi.
if (
    current_role() === 'pelanggan'
    && !email_verification_is_complete((int) current_user_id())
) {
    flash_set(
        'warning',
        t('auth.email_verification_required_for_booking')
    );

    redirect('pengaturan-akun.php');
}

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

    // 4. Periksa Ketersediaan Unit dan Simpan Reservasi dalam Transaksi dengan Kunci Mutex (FOR UPDATE)
    if (empty($errors) && $selectedVilla) {
        try {
            $pdo->beginTransaction();

            // Mutex kunci baris tipe_villa untuk mencegah race condition
            $stmtLock = $pdo->prepare("SELECT `jumlah_unit` FROM `tipe_villa` WHERE `id_tipe` = ? FOR UPDATE");
            $stmtLock->execute([$idTipe]);
            $stmtLock->fetch();

            $ketersediaan = hitung_ketersediaan($idTipe, $checkin, $checkout, $pdo);
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
                $errors['ketersediaan'] = "Maaf, seluruh unit untuk {$selectedVilla['nama']} telah terpesan pada " . date('d/m/Y', strtotime($tanggalPenuh)) . ". Silakan pilih tanggal atau tipe villa lain.";
            } else {
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
                    $pdo->rollBack();
                    $errors['umum'] = 'Terjadi kesalahan saat menghitung rincian durasi menginap. Silakan periksa kembali tanggal yang Anda masukkan.';
                } else {
                    // F-09: Simpan status awal "menunggu"
                    $sqlInsert = "INSERT INTO `reservasi` (
                                    `id_pelanggan`, `sumber`, `id_tipe`, `tanggal_checkin`, `tanggal_checkout`,
                                    `jumlah_tamu`, `catatan`, `status`,
                                    `harga_weekday_saat_pesan`, `harga_weekend_saat_pesan`, `harga_extrabed_saat_pesan`,
                                    `estimasi_total`, `dibuat_pada`
                                  ) VALUES (?, 'online', ?, ?, ?, ?, ?, 'menunggu', ?, ?, ?, ?, NOW())";

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
                    catat_reservasi_log($pdo, (int)$idReservasi, null, 'menunggu', current_user_id(), 'Pengajuan reservasi baru online');
                    $pdo->commit();

                    pemicu_oportunistik_kedaluwarsa($pdo);

                    flash_set('success', t('booking.booking_success', ['kode' => "#RSV-{$idReservasi}"]));
                    redirect('reservasi-saya.php');
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['umum'] = 'Terjadi kesalahan sistem saat memproses reservasi: ' . $e->getMessage();
        }
    }
}

$pageTitle = t('booking.title') . ' — Villa Joglo Masayu';
$pageDesc  = t('booking.desc');
$bodyClass = 'page-villa';
$extraScripts = ['assets/js/kalender.js', 'assets/js/reservasi.js'];

require_once __DIR__ . '/includes/layout/head.php';
?>

<main class="container" style="padding-top: calc(var(--header-h-scrolled) + 3rem); padding-bottom: 5rem;">
  <div style="max-width: 760px; margin: 0 auto;">

    <div class="form-card">
      <p class="eyebrow"><?= t('booking.eyebrow') ?></p>
      <h1 class="form-title"><?= t('booking.title') ?></h1>
      <p class="form-desc"><?= t('booking.desc') ?></p>

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
          <label class="form-label" for="id_tipe"><?= t('booking.villa_label') ?> <span class="required">*</span></label>
          <select id="id_tipe" name="id_tipe" class="form-control" required>
            <?php foreach ($villasList as $v): ?>
              <option value="<?= e($v['id_tipe']) ?>"
                      data-std="<?= e($v['kapasitas_standar']) ?>"
                      data-max="<?= e($v['kapasitas_maks']) ?>"
                      data-weekday="<?= e($v['harga_weekday']) ?>"
                      data-weekend="<?= e($v['harga_weekend']) ?>"
                      <?= ($idTipe === (int)$v['id_tipe']) ? 'selected' : '' ?>>
                <?= e($v['nama']) ?> — <?= format_rupiah($v['harga_weekday']) ?> / <?= t('common.night') ?> (<?= t('common.guests') ?>: <?= e($v['kapasitas_standar']) ?>-<?= e($v['kapasitas_maks']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['id_tipe'])): ?>
            <div class="form-error"><?= e($errors['id_tipe']) ?></div>
          <?php endif; ?>
        </div>

        <!-- Kalender Pemilihan Tanggal (Tahap 11) -->
        <div id="reservasiCalendarBox" class="mb-5"></div>

        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label" for="tanggal_checkin"><?= t('booking.checkin_label') ?> <span class="required">*</span></label>
            <input type="date" id="tanggal_checkin" name="tanggal_checkin" class="form-control" value="<?= e($checkin) ?>" required />
            <div class="form-hint">Waktu check-in mulai 14.00 WIB.</div>
            <?php if (isset($errors['tanggal_checkin'])): ?>
              <div class="form-error"><?= e($errors['tanggal_checkin']) ?></div>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label class="form-label" for="tanggal_checkout"><?= t('booking.checkout_label') ?> <span class="required">*</span></label>
            <input type="date" id="tanggal_checkout" name="tanggal_checkout" class="form-control" value="<?= e($checkout) ?>" required />
            <div class="form-hint">Waktu check-out maksimal 12.00 WIB.</div>
            <?php if (isset($errors['tanggal_checkout'])): ?>
              <div class="form-error"><?= e($errors['tanggal_checkout']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="jumlah_tamu"><?= t('booking.guests_label') ?> <span class="required">*</span></label>
          <input type="number" id="jumlah_tamu" name="jumlah_tamu" class="form-control" value="<?= e($jumlahTamu) ?>" min="1" max="10" required />
          <div class="form-hint" id="tamuHint">Tamu melebihi kapasitas standar akan dikenakan biaya extra bed per malam.</div>
          <?php if (isset($errors['jumlah_tamu'])): ?>
            <div class="form-error"><?= e($errors['jumlah_tamu']) ?></div>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="catatan"><?= t('booking.special_requests') ?></label>
          <textarea id="catatan" name="catatan" class="form-control" rows="3" placeholder="Contoh: Perkiraan waktu tiba pukul 15.00 WIB, mohon disiapkan extra bed."><?= e($catatan) ?></textarea>
        </div>

        <!-- Box Rincian Estimasi Biaya Real-time -->
        <div id="reservasiPreviewBox" class="estimate-box" style="display: none;">
          <span class="estimate-box-label"><?= t('booking.price_summary') ?></span>
          
          <div style="font-size: 14px; margin-bottom: 0.5rem; color: var(--wood);">
            Durasi: <strong id="previewTotalMalam" style="color: var(--teak);">-</strong>
          </div>

          <div style="display: flex; justify-content: space-between; font-size: 13.5px; padding: 4px 0; border-bottom: 1px dashed rgba(59,36,22,0.1);">
            <span>Subtotal Malam Weekday:</span>
            <strong id="previewSubtotalWeekday">-</strong>
          </div>

          <div style="display: flex; justify-content: space-between; font-size: 13.5px; padding: 4px 0; border-bottom: 1px dashed rgba(59,36,22,0.1);">
            <span>Subtotal Malam Weekend:</span>
            <strong id="previewSubtotalWeekend">-</strong>
          </div>

          <div id="previewExtraBedRow" style="display: none; justify-content: space-between; font-size: 13.5px; padding: 4px 0; border-bottom: 1px dashed rgba(59,36,22,0.1);">
            <span id="previewExtraBedInfo"><?= t('booking.extrabed_total') ?>:</span>
            <strong id="previewSubtotalExtra">-</strong>
          </div>

          <div style="display: flex; justify-content: space-between; align-items: baseline; margin-top: 0.75rem;">
            <span style="font-size: 15px; font-weight: 700; color: var(--teak);"><?= t('booking.total_estimation') ?>:</span>
            <span class="estimate-box-value" id="previewEstimasiTotal">-</span>
          </div>

          <small class="estimate-disclaimer">
            * <?= t('booking.estimation_notice') ?>
          </small>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-gold" style="width: 100%; justify-content: center;"><?= t('booking.btn_submit') ?></button>
        </div>
      </form>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/layout/foot.php'; ?>

