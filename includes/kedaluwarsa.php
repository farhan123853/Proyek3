<?php
/**
 * Modul Kedaluwarsa Reservasi Otomatis & Log Riwayat Status — Villa Joglo Masayu
 * Menangani batas bayar, penutupan reservasi lewat batas, pemicu oportunistik, dan reaktivasi pembayaran terlambat.
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * Mencatat riwayat mutasi status reservasi ke tabel reservasi_log
 */
function catat_reservasi_log(
    PDO $pdo,
    int $idReservasi,
    ?string $dariStatus,
    string $keStatus,
    ?int $olehPengguna = null,
    ?string $alasan = null
): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO `reservasi_log` (
                `id_reservasi`, `dari_status`, `ke_status`, `oleh_pengguna`, `alasan`, `dibuat_pada`
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $idReservasi,
            $dariStatus,
            $keStatus,
            $olehPengguna ?: null,
            $alasan ? mb_substr($alasan, 0, 200) : null
        ]);
    } catch (\Exception $e) {
        error_log("Gagal mencatat reservasi_log #{$idReservasi}: " . $e->getMessage());
    }
}

/**
 * Menghitung batas waktu pembayaran online resmi:
 * Batas bayar online = waktu konfirmasi + PEMBAYARAN_BATAS_JAM (24 jam),
 * dibatasi paling lambat jam 12.00 WIB pada hari check-in,
 * tetapi tidak kurang dari 2 jam sejak konfirmasi.
 *
 * @param string      $tanggalCheckin  Format YYYY-MM-DD
 * @param string|null $waktuKonfirmasi Format datetime (default: waktu sekarang)
 * @param array|null  $cfg             Array config (default: global $config)
 * @return string Datetime YYYY-MM-DD HH:MM:SS
 */
function hitung_batas_bayar_online(string $tanggalCheckin, ?string $waktuKonfirmasi = null, ?array $cfg = null): string
{
    global $config;
    $c = $cfg ?? ($config ?? []);

    $baseTime = $waktuKonfirmasi ? strtotime($waktuKonfirmasi) : time();
    $batasJam = (int)($c['PEMBAYARAN_BATAS_JAM'] ?? 24);
    $minJam = (int)($c['PEMBAYARAN_BATAS_MIN_JAM'] ?? 2);
    $checkinBatasJam = (int)($c['PEMBAYARAN_CHECKIN_BATAS_JAM'] ?? 12);

    $tsStandard = $baseTime + ($batasJam * 3600);
    $tsCheckinMax = strtotime($tanggalCheckin . ' ' . sprintf('%02d:00:00', $checkinBatasJam));
    $tsMin = $baseTime + ($minJam * 3600);

    $tsDeadline = min($tsStandard, $tsCheckinMax);
    if ($tsDeadline < $tsMin) {
        $tsDeadline = $tsMin;
    }

    return date('Y-m-d H:i:s', $tsDeadline);
}

/**
 * Menghitung batas waktu pembayaran tunai di villa:
 * Batas bayar = akhir hari check-in (23.59.59 WIB)
 *
 * @param string $tanggalCheckin Format YYYY-MM-DD
 * @return string Datetime YYYY-MM-DD 23:59:59
 */
function hitung_batas_bayar_tunai(string $tanggalCheckin): string
{
    return $tanggalCheckin . ' 23:59:59';
}

/**
 * Menutup reservasi berstatus 'dikonfirmasi' yang telah melewati batas bayar
 * dan belum memiliki pembayaran 'lunas'.
 *
 * @param PDO|null $pdo
 * @param int      $limit Maksimal reservasi yang diproses per pemanggilan
 * @return array{diperiksa:int, kedaluwarsa:int, dilewati:int, ids:int[]}
 */
function expire_reservasi_lewat_batas(?PDO $pdo = null, int $limit = 50): array
{
    global $config;
    if ($pdo === null) {
        $pdo = get_db();
    }

    $hasil = [
        'diperiksa'   => 0,
        'kedaluwarsa' => 0,
        'dilewati'    => 0,
        'ids'         => []
    ];

    // Ambil kandidat reservasi dikonfirmasi yang telah lewat batas dan belum lunas
    $stmtCandidates = $pdo->prepare("
        SELECT r.id_reservasi, r.id_tipe, r.batas_bayar_pada, r.tanggal_checkin, r.tanggal_checkout, r.rencana_bayar
        FROM `reservasi` r
        WHERE r.`status` = 'dikonfirmasi'
          AND r.`batas_bayar_pada` IS NOT NULL
          AND r.`batas_bayar_pada` < NOW()
          AND NOT EXISTS (
              SELECT 1 FROM `pembayaran` p 
              WHERE p.`id_reservasi` = r.`id_reservasi` AND p.`status` = 'lunas'
          )
        ORDER BY r.`batas_bayar_pada` ASC
        LIMIT :limit
    ");
    $stmtCandidates->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtCandidates->execute();
    $candidates = $stmtCandidates->fetchAll(PDO::FETCH_ASSOC);

    $hasil['diperiksa'] = count($candidates);

    foreach ($candidates as $cand) {
        $idRes = (int)$cand['id_reservasi'];

        $startedTx = false;
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTx = true;
        }

        try {
            // 1. Kunci baris reservasi target
            $stmtLock = $pdo->prepare("SELECT * FROM `reservasi` WHERE `id_reservasi` = ? FOR UPDATE");
            $stmtLock->execute([$idRes]);
            $resCurrent = $stmtLock->fetch(PDO::FETCH_ASSOC);

            if (!$resCurrent || $resCurrent['status'] !== 'dikonfirmasi') {
                if ($startedTx && $pdo->inTransaction()) $pdo->commit();
                $hasil['dilewati']++;
                continue;
            }

            // Cek ulang pembayaran lunas di bawah row lock
            $stmtCekLunas = $pdo->prepare("SELECT 1 FROM `pembayaran` WHERE `id_reservasi` = ? AND `status` = 'lunas' LIMIT 1");
            $stmtCekLunas->execute([$idRes]);
            if ($stmtCekLunas->fetch()) {
                if ($startedTx && $pdo->inTransaction()) $pdo->commit();
                $hasil['dilewati']++;
                continue;
            }

            // 2. Kunci baris tipe villa sebagai mutex per tipe
            $stmtVillaLock = $pdo->prepare("SELECT `id_tipe`, `jumlah_unit` FROM `tipe_villa` WHERE `id_tipe` = ? FOR UPDATE");
            $stmtVillaLock->execute([$resCurrent['id_tipe']]);
            $stmtVillaLock->fetch();

            // 3. Periksa apakah ada pembayaran Midtrans berstatus 'menunggu' yang belum kedaluwarsa
            $stmtPending = $pdo->prepare("
                SELECT * FROM `pembayaran` 
                WHERE `id_reservasi` = ? AND `status` = 'menunggu'
                ORDER BY `id_pembayaran` DESC LIMIT 1
            ");
            $stmtPending->execute([$idRes]);
            $pendingPay = $stmtPending->fetch(PDO::FETCH_ASSOC);

            if ($pendingPay) {
                $nowTs = time();
                $payExpiryTs = !empty($pendingPay['kedaluwarsa_pada']) ? strtotime($pendingPay['kedaluwarsa_pada']) : 0;

                // Jika pembayaran online masih dalam masa berlaku di Midtrans
                if ($payExpiryTs > $nowTs) {
                    // Panggil sinkron_pembayaran terlebih dahulu untuk cek settlement terakhir
                    $sinkron = sinkron_pembayaran($pendingPay['order_id']);
                    if ($sinkron['ok'] && ($sinkron['status'] ?? '') === 'lunas') {
                        // Telah lunas di payment gateway! Batalkan penutupan
                        if ($startedTx && $pdo->inTransaction()) $pdo->commit();
                        $hasil['dilewati']++;
                        continue;
                    }

                    // Jika masih menunggu dan transaksi payment gateway belum habis waktunya:
                    // LEWATI penutupan agar pelanggan tidak kehilangan hak bayar yang sedang berlangsung di gateway
                    if ($startedTx && $pdo->inTransaction()) $pdo->commit();
                    $hasil['dilewati']++;
                    continue;
                }
            }

            // 4. Tutup reservasi menjadi 'kedaluwarsa'
            $stmtExpire = $pdo->prepare("
                UPDATE `reservasi`
                SET `status` = 'kedaluwarsa',
                    `kedaluwarsa_pada` = NOW()
                WHERE `id_reservasi` = ? AND `status` = 'dikonfirmasi'
            ");
            $stmtExpire->execute([$idRes]);

            if ($stmtExpire->rowCount() === 1) {
                // Tandai pembayaran 'menunggu' yang tersisa sebagai 'kedaluwarsa'
                $pdo->prepare("
                    UPDATE `pembayaran`
                    SET `status` = 'kedaluwarsa',
                        `catatan` = CONCAT(COALESCE(`catatan`, ''), CASE WHEN `catatan` IS NULL OR `catatan` = '' THEN '' ELSE ' | ' END, '[Kedaluwarsa otomatis: lewat batas bayar]')
                    WHERE `id_reservasi` = ? AND `status` = 'menunggu'
                ")->execute([$idRes]);

                // Upayakan pembatalan/expire pada server Midtrans jika ada order aktif
                if ($pendingPay && !empty($pendingPay['order_id']) && ($pendingPay['sumber'] ?? '') === 'midtrans') {
                    try {
                        \Midtrans\Config::$serverKey = $config['MIDTRANS_SERVER_KEY'] ?? '';
                        \Midtrans\Config::$isProduction = (bool)($config['MIDTRANS_IS_PRODUCTION'] ?? false);
                        \Midtrans\Transaction::expire($pendingPay['order_id']);
                    } catch (\Exception $e) {
                        try {
                            \Midtrans\Transaction::cancel($pendingPay['order_id']);
                        } catch (\Exception $ex) {
                            // Abaikan kegagalan jaringan Midtrans agar tidak menggagalkan penutupan lokal
                        }
                    }
                }

                // Catat log audit sistem
                catat_reservasi_log(
                    $pdo,
                    $idRes,
                    'dikonfirmasi',
                    'kedaluwarsa',
                    null,
                    'Lewat batas waktu pembayaran (sistem otomatis)'
                );

                $hasil['kedaluwarsa']++;
                $hasil['ids'][] = $idRes;
            } else {
                $hasil['dilewati']++;
            }

            if ($startedTx && $pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (\Exception $e) {
            if ($startedTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error saat memproses expire reservasi #{$idRes}: " . $e->getMessage());
            $hasil['dilewati']++;
        }
    }

    return $hasil;
}

/**
 * Pemicu oportunistik (non-cron) yang dibatasi maksimal sekali per interval detik (default 60 detik)
 * Dipanggil di dashboard dan halaman reservasi pelanggan untuk memastikan kedaluwarsa berjalan tanpa cron.
 *
 * @param PDO|null $pdo
 * @param int      $throttleSeconds
 * @return bool True jika fungsi eksekusi berjalan, false jika di-throttle
 */
function pemicu_oportunistik_kedaluwarsa(?PDO $pdo = null, int $throttleSeconds = 60): bool
{
    $lockDir = __DIR__ . '/../tmp';
    if (!is_dir($lockDir)) {
        @mkdir($lockDir, 0755, true);
    }
    $lockFile = $lockDir . '/expire_reservasi.lock';

    $fp = @fopen($lockFile, 'c+');
    if (!$fp) {
        return false;
    }

    if (!flock($fp, LOCK_EX | LOCK_NB)) {
        fclose($fp);
        return false;
    }

    $lastRun = (int)trim((string)stream_get_contents($fp));
    $now = time();

    if ($lastRun > 0 && ($now - $lastRun) < $throttleSeconds) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }

    // Perbarui stempel waktu jalankan
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, (string)$now);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    // Jalankan pemeriksaan kedaluwarsa
    expire_reservasi_lewat_batas($pdo);
    return true;
}

/**
 * Aksi Pelanggan: Memilih metode bayar tunai di villa (memperpanjang batas bayar ke akhir hari check-in)
 *
 * @param int $idReservasi
 * @param int $idPelanggan
 * @return array{ok:bool, pesan:string}
 */
function pelanggan_pilih_bayar_tunai(int $idReservasi, int $idPelanggan): array
{
    $pdo = get_db();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM `reservasi` WHERE `id_reservasi` = ? FOR UPDATE");
        $stmt->execute([$idReservasi]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$r || (int)$r['id_pelanggan'] !== $idPelanggan) {
            $pdo->rollBack();
            return ['ok' => false, 'pesan' => t('payment.not_found')];
        }

        if ($r['status'] !== 'dikonfirmasi') {
            $pdo->rollBack();
            return ['ok' => false, 'pesan' => 'Hanya reservasi berstatus dikonfirmasi yang dapat mengubah metode pembayaran.'];
        }

        // Cek apakah sudah lunas
        $stmtLunas = $pdo->prepare("SELECT 1 FROM `pembayaran` WHERE `id_reservasi` = ? AND `status` = 'lunas' LIMIT 1");
        $stmtLunas->execute([$idReservasi]);
        if ($stmtLunas->fetch()) {
            $pdo->rollBack();
            return ['ok' => false, 'pesan' => t('payment.already_paid')];
        }

        // Hitung batas bayar tunai: akhir hari check-in 23:59:59 WIB
        $batasTunai = hitung_batas_bayar_tunai($r['tanggal_checkin']);

        $stmtUpdate = $pdo->prepare("
            UPDATE `reservasi`
            SET `rencana_bayar` = 'tunai',
                `batas_bayar_pada` = ?
            WHERE `id_reservasi` = ?
        ");
        $stmtUpdate->execute([$batasTunai, $idReservasi]);

        catat_reservasi_log(
            $pdo,
            $idReservasi,
            'dikonfirmasi',
            'dikonfirmasi',
            $idPelanggan,
            'Pelanggan memilih rencana bayar tunai di villa (batas: ' . date('d/m/Y 23:59', strtotime($batasTunai)) . ' WIB)'
        );

        $pdo->commit();
        return [
            'ok' => true,
            'pesan' => t('payment.cash_selected_success', ['waktu' => date('d/m/Y 23:59', strtotime($batasTunai)) . ' WIB'])
        ];
    } catch (\Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'pesan' => 'Terjadi kesalahan sistem saat memilih bayar tunai: ' . $e->getMessage()];
    }
}

/**
 * Aksi Pelanggan: Beralih kembali ke pembayaran online
 *
 * @param int $idReservasi
 * @param int $idPelanggan
 * @return array{ok:bool, pesan:string}
 */
function pelanggan_kembali_bayar_online(int $idReservasi, int $idPelanggan): array
{
    $pdo = get_db();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM `reservasi` WHERE `id_reservasi` = ? FOR UPDATE");
        $stmt->execute([$idReservasi]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$r || (int)$r['id_pelanggan'] !== $idPelanggan) {
            $pdo->rollBack();
            return ['ok' => false, 'pesan' => t('payment.not_found')];
        }

        if ($r['status'] !== 'dikonfirmasi') {
            $pdo->rollBack();
            return ['ok' => false, 'pesan' => 'Hanya reservasi berstatus dikonfirmasi yang dapat mengubah metode pembayaran.'];
        }

        $stmtLunas = $pdo->prepare("SELECT 1 FROM `pembayaran` WHERE `id_reservasi` = ? AND `status` = 'lunas' LIMIT 1");
        $stmtLunas->execute([$idReservasi]);
        if ($stmtLunas->fetch()) {
            $pdo->rollBack();
            return ['ok' => false, 'pesan' => t('payment.already_paid')];
        }

        // Hitung ulang batas bayar online dari sekarang (bukan memperpanjang melebihi aturan yang sama)
        $batasOnline = hitung_batas_bayar_online($r['tanggal_checkin'], 'now');

        $stmtUpdate = $pdo->prepare("
            UPDATE `reservasi`
            SET `rencana_bayar` = 'online',
                `batas_bayar_pada` = ?
            WHERE `id_reservasi` = ?
        ");
        $stmtUpdate->execute([$batasOnline, $idReservasi]);

        catat_reservasi_log(
            $pdo,
            $idReservasi,
            'dikonfirmasi',
            'dikonfirmasi',
            $idPelanggan,
            'Pelanggan beralih kembali ke rencana bayar online (batas: ' . date('d/m/Y H:i', strtotime($batasOnline)) . ' WIB)'
        );

        $pdo->commit();
        return [
            'ok' => true,
            'pesan' => t('payment.online_selected_success', ['waktu' => date('d/m/Y H:i', strtotime($batasOnline)) . ' WIB'])
        ];
    } catch (\Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'pesan' => 'Terjadi kesalahan sistem saat memilih bayar online: ' . $e->getMessage()];
    }
}
