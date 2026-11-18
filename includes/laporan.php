<?php
/**
 * Layanan Data Laporan Pemasukan — Villa Joglo Masayu
 * Dipakai oleh Dashboard Pemilik dan Dashboard Admin
 * Hanya membaca data (Read-Only) tanpa mengubah data
 * 
 * Definisi:
 * PEMASUKAN = total pembayaran berstatus 'lunas', dikelompokkan menurut dibayar_pada (WIB).
 * Status gagal, kedaluwarsa, menunggu, ganda, dan dibatalkan TIDAK dihitung.
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * Mendapatkan daftar tahun laporan dari tahun terkecil yang memiliki transaksi lunas sampai tahun berjalan WIB
 * @return int[]
 */
function daftar_tahun_laporan(): array
{
    $db = get_db();
    $tahunSekarang = (int)date('Y');

    $stmt = $db->query("
        SELECT MIN(YEAR(dibayar_pada)) AS min_tahun
        FROM pembayaran
        WHERE status = 'lunas' AND dibayar_pada IS NOT NULL
    ");
    $row = $stmt->fetch();

    $minTahun = (!empty($row['min_tahun'])) ? (int)$row['min_tahun'] : $tahunSekarang;
    if ($minTahun > $tahunSekarang) {
        $minTahun = $tahunSekarang;
    }

    $daftarTahun = [];
    for ($y = $minTahun; $y <= $tahunSekarang; $y++) {
        $daftarTahun[] = $y;
    }

    return $daftarTahun;
}

/**
 * Mendapatkan laporan pemasukan bulanan (12 bulan) untuk tahun tertentu
 * Memakai rentang tanggal >= awal tahun AND < awal tahun berikutnya untuk menjaga pemanfaatan indeks basis data
 * 
 * @param int $tahun
 * @param string $metode 'all' | 'transfer_bank' | 'e_wallet' | 'tunai' | 'lainnya'
 * @return array
 */
function pemasukan_bulanan(int $tahun, string $metode = 'all'): array
{
    $db = get_db();

    $tahunSekarang = (int)date('Y');
    $bulanSekarang = (int)date('n');

    // 1. Inisialisasi struktur 12 bulan
    $bulanData = [];
    for ($m = 1; $m <= 12; $m++) {
        $isMasaDepan = ($tahun > $tahunSekarang) || ($tahun === $tahunSekarang && $m > $bulanSekarang);
        $isBulanIni   = ($tahun === $tahunSekarang && $m === $bulanSekarang);

        $bulanData[$m] = [
            'bulan'            => $m,
            'jumlah_transaksi' => 0,
            'transfer_bank'    => 0,
            'e_wallet'         => 0,
            'tunai'            => 0,
            'lainnya'          => 0,
            'total'            => 0,
            'is_masa_depan'    => $isMasaDepan,
            'is_bulan_ini'     => $isBulanIni,
        ];
    }

    // 2. Rentang tanggal parameter (menjaga indeks status_dibayar_pada)
    $awalTahun = sprintf('%04d-01-01 00:00:00', $tahun);
    $awalTahunBerikutnya = sprintf('%04d-01-01 00:00:00', $tahun + 1);

    // 3. Query agregasi
    $sql = "
        SELECT 
            MONTH(p.dibayar_pada) AS bulan,
            COALESCE(p.metode_kelompok, 'lainnya') AS metode,
            COUNT(*) AS jml_transaksi,
            SUM(p.jumlah) AS subtotal
        FROM pembayaran p
        WHERE p.status = 'lunas'
          AND p.dibayar_pada >= ?
          AND p.dibayar_pada < ?
    ";
    $params = [$awalTahun, $awalTahunBerikutnya];

    if ($metode !== 'all') {
        $sql .= " AND p.metode_kelompok = ? ";
        $params[] = $metode;
    }

    $sql .= " GROUP BY MONTH(p.dibayar_pada), p.metode_kelompok ORDER BY bulan ASC ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as $r) {
        $m = (int)$r['bulan'];
        if ($m < 1 || $m > 12) continue;

        $nominal = (int)$r['subtotal'];
        $transaksi = (int)$r['jml_transaksi'];
        $met = $r['metode'];

        if (isset($bulanData[$m][$met])) {
            $bulanData[$m][$met] += $nominal;
        } else {
            $bulanData[$m]['lainnya'] += $nominal;
        }

        $bulanData[$m]['jumlah_transaksi'] += $transaksi;
        $bulanData[$m]['total'] += $nominal;
    }

    return $bulanData;
}

/**
 * Mendapatkan ringkasan pemasukan per tipe villa untuk tahun tertentu
 * Mengembalikan array tipe villa beserta persentase kontribusi pemasukan
 * 
 * @param int $tahun
 * @return array
 */
function pemasukan_per_tipe(int $tahun): array
{
    $db = get_db();

    $awalTahun = sprintf('%04d-01-01 00:00:00', $tahun);
    $awalTahunBerikutnya = sprintf('%04d-01-01 00:00:00', $tahun + 1);

    $stmt = $db->prepare("
        SELECT 
            t.id_tipe,
            t.slug,
            t.nama AS nama_tipe,
            COUNT(p.id_pembayaran) AS jumlah_transaksi,
            COALESCE(SUM(p.jumlah), 0) AS total_nominal
        FROM tipe_villa t
        LEFT JOIN reservasi r ON t.id_tipe = r.id_tipe
        LEFT JOIN pembayaran p ON r.id_reservasi = p.id_reservasi
            AND p.status = 'lunas'
            AND p.dibayar_pada >= ?
            AND p.dibayar_pada < ?
        GROUP BY t.id_tipe, t.slug, t.nama
        ORDER BY total_nominal DESC, t.id_tipe ASC
    ");
    $stmt->execute([$awalTahun, $awalTahunBerikutnya]);
    $tipeList = $stmt->fetchAll();

    $totalSemuaTipe = 0;
    foreach ($tipeList as $item) {
        $totalSemuaTipe += (int)$item['total_nominal'];
    }

    $hasil = [];
    foreach ($tipeList as $item) {
        $nominal = (int)$item['total_nominal'];
        $persen = $totalSemuaTipe > 0 ? round(($nominal / $totalSemuaTipe) * 100, 1) : 0.0;
        $hasil[] = [
            'id_tipe'          => (int)$item['id_tipe'],
            'slug'             => $item['slug'],
            'nama_tipe'        => $item['nama_tipe'],
            'jumlah_transaksi' => (int)$item['jumlah_transaksi'],
            'total_nominal'    => $nominal,
            'persentase'       => $persen,
        ];
    }

    return [
        'total' => $totalSemuaTipe,
        'data'  => $hasil
    ];
}

/**
 * Mendapatkan 4 kartu ringkasan eksekutif untuk Dashboard Pemilik:
 * 1. Bulan ini (nominal sampai tanggal saat ini)
 * 2. Bulan lalu (dan persentase bulan ini terhadap bulan lalu)
 * 3. Total tahun terpilih
 * 4. Rata-rata per bulan (dibagi bulan berjalan, bukan 12 bila tahun berjalan)
 * 
 * @param int $tahun
 * @param string $metode
 * @return array
 */
function ringkasan_pemasukan(int $tahun, string $metode = 'all'): array
{
    $bulanan = pemasukan_bulanan($tahun, $metode);

    $tahunSekarang = (int)date('Y');
    $bulanSekarang = (int)date('n');

    // Hitung total tahun terpilih & total transaksi
    $totalTahun = 0;
    $totalTransaksi = 0;
    $totalTransfer = 0;
    $totalEwallet = 0;
    $totalTunai = 0;
    $totalLainnya = 0;

    foreach ($bulanan as $m => $b) {
        $totalTahun += $b['total'];
        $totalTransaksi += $b['jumlah_transaksi'];
        $totalTransfer += $b['transfer_bank'];
        $totalEwallet += $b['e_wallet'];
        $totalTunai += $b['tunai'];
        $totalLainnya += $b['lainnya'];
    }

    // Bulan ini
    $nominalBulanIni = ($tahun === $tahunSekarang) ? ($bulanan[$bulanSekarang]['total'] ?? 0) : 0;

    // Bulan lalu
    $nominalBulanLalu = 0;
    if ($tahun === $tahunSekarang) {
        if ($bulanSekarang > 1) {
            $nominalBulanLalu = $bulanan[$bulanSekarang - 1]['total'] ?? 0;
        } else {
            // Jika Januari tahun berjalan, bulan lalu adalah Desember tahun sebelumnya
            $laluPrev = pemasukan_bulanan($tahun - 1, $metode);
            $nominalBulanLalu = $laluPrev[12]['total'] ?? 0;
        }
    }

    // Persentase bulan ini terhadap bulan lalu
    $persenBulanIni = null;
    if ($nominalBulanLalu > 0) {
        $persenBulanIni = round(($nominalBulanIni / $nominalBulanLalu) * 100, 1);
    }

    // Pembagi bulan untuk rata-rata:
    // Jika tahun berjalan: dibagi jumlah bulan yang sudah berjalan ($bulanSekarang)
    // Jika tahun lampau: dibagi 12
    // Jika tahun masa depan: dibagi 1
    if ($tahun === $tahunSekarang) {
        $pembagiBulan = max(1, $bulanSekarang);
    } elseif ($tahun < $tahunSekarang) {
        $pembagiBulan = 12;
    } else {
        $pembagiBulan = 1;
    }

    $rataRataBulanan = (int)round($totalTahun / $pembagiBulan);

    return [
        'tahun'               => $tahun,
        'metode'              => $metode,
        'bulan_ini'           => $nominalBulanIni,
        'bulan_lalu'          => $nominalBulanLalu,
        'persen_bulan_ini'    => $persenBulanIni,
        'total_tahun'         => $totalTahun,
        'total_transaksi'     => $totalTransaksi,
        'total_transfer'      => $totalTransfer,
        'total_ewallet'       => $totalEwallet,
        'total_tunai'         => $totalTunai,
        'total_lainnya'       => $totalLainnya,
        'rata_rata_bulanan'   => $rataRataBulanan,
        'pembagi_bulan'       => $pembagiBulan,
    ];
}
