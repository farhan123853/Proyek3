<?php
/**
 * Test Gerbang Tahap 7: Layanan Data Laporan Pemasukan
 * Menguji akurasi matematis data fixture, batas waktu WIB, lintas tahun, non-lunas, dan index EXPLAIN
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/laporan.php';

$pdo = get_db();

echo "=== MEMULAI PENGUJIAN TAHAP 7: LAPORAN PEMASUKAN ===\n";

// 0. Bersihkan data pengujian
$pdo->prepare("DELETE FROM pembayaran WHERE order_id LIKE 'FIXTURE-T7-%'")->execute();
$pdo->prepare("DELETE FROM reservasi WHERE catatan LIKE 'FIXTURE-T7-%'")->execute();

// Siapkan reservasi untuk fixture tipe villa 1 (Joglo Wulung) dan tipe 2 (Joglo Saren)
$pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, id_tipe, diproses_oleh, tanggal_checkin, tanggal_checkout, jumlah_tamu, catatan, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, dibuat_pada)
    VALUES (4, 1, 2, '2026-12-01', '2026-12-03', 2, 'FIXTURE-T7-R1', 'dikonfirmasi', 1500000, 2000000, 350000, 3000000, '2025-12-01 10:00:00')
")->execute();
$r1Id = (int)$pdo->lastInsertId();

$pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, id_tipe, diproses_oleh, tanggal_checkin, tanggal_checkout, jumlah_tamu, catatan, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, dibuat_pada)
    VALUES (4, 2, 2, '2026-12-05', '2026-12-07', 4, 'FIXTURE-T7-R2', 'dikonfirmasi', 2200000, 2900000, 350000, 4400000, '2025-12-01 10:00:00')
")->execute();
$r2Id = (int)$pdo->lastInsertId();

// 1. Sisipkan Data Fixture
// F1: Tepat pada batas bulan 23:59:59 WIB pada 31 Januari 2026 -> Harus masuk Januari (Bulan 1)
$pdo->prepare("
    INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, metode_kelompok, metode_detail, dibayar_pada)
    VALUES (?, 'FIXTURE-T7-JAN-LAST', 'midtrans', 1000000, 'lunas', 'transfer_bank', 'bca_va', '2026-01-31 23:59:59')
")->execute([$r1Id]);

// F2: Tepat pada batas bulan 00:00:00 WIB pada 1 Februari 2026 -> Harus masuk Februari (Bulan 2)
$pdo->prepare("
    INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, metode_kelompok, metode_detail, dibayar_pada)
    VALUES (?, 'FIXTURE-T7-FEB-FIRST', 'midtrans', 2000000, 'lunas', 'e_wallet', 'gopay', '2026-02-01 00:00:00')
")->execute([$r2Id]);

// F3: Transaksi tengah bulan Maret 2026 -> Harus masuk Maret (Bulan 3)
// Pakai reservasi baru agar tidak melanggar aturan satu-lunas DB
$pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, id_tipe, diproses_oleh, tanggal_checkin, tanggal_checkout, jumlah_tamu, catatan, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, dibuat_pada)
    VALUES (4, 1, 2, '2026-03-20', '2026-03-22', 2, 'FIXTURE-T7-R3', 'dikonfirmasi', 1500000, 2000000, 350000, 3000000, '2026-03-01 10:00:00')
")->execute();
$r3Id = (int)$pdo->lastInsertId();

$pdo->prepare("
    INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, metode_kelompok, metode_detail, dibayar_pada, dicatat_oleh)
    VALUES (?, 'FIXTURE-T7-MAR-MID', 'manual', 3000000, 'lunas', 'tunai', 'cash', '2026-03-15 14:30:00', 2)
")->execute([$r3Id]);

// F4: Transaksi lintas tahun (31 Desember 2025 23:59:59 WIB) -> Harus masuk Tahun 2025, bukan 2026
$pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, id_tipe, diproses_oleh, tanggal_checkin, tanggal_checkout, jumlah_tamu, catatan, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, dibuat_pada)
    VALUES (4, 2, 2, '2025-12-28', '2025-12-30', 4, 'FIXTURE-T7-R4', 'dikonfirmasi', 2200000, 2900000, 350000, 4000000, '2025-12-01 10:00:00')
")->execute();
$r4Id = (int)$pdo->lastInsertId();

$pdo->prepare("
    INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, metode_kelompok, metode_detail, dibayar_pada)
    VALUES (?, 'FIXTURE-T7-2025-DEC', 'midtrans', 4000000, 'lunas', 'transfer_bank', 'bni_va', '2025-12-31 23:59:59')
")->execute([$r4Id]);

// F5: Transaksi status NON-LUNAS pada rentang tahun 2026 -> TIDAK BOLEH DIHITUNG SAMA SEKALI
$nonLunas = ['menunggu', 'gagal', 'kedaluwarsa', 'ganda', 'dibatalkan'];
foreach ($nonLunas as $idx => $st) {
    $pdo->prepare("
        INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, metode_kelompok, dibayar_pada)
        VALUES (?, 'FIXTURE-T7-NON-{$st}', 'midtrans', 5000000, ?, 'transfer_bank', '2026-02-15 10:00:00')
    ")->execute([$r1Id, $st]);
}

echo "Data fixture berhasil disisipkan.\n";

// UJI 1: Pengujian Batas Bulan & Akurasi Bulanan 2026
$laporan2026 = pemasukan_bulanan(2026, 'all');

// Bulan 1: harus memuat 1000000 (batas 23:59:59)
assert($laporan2026[1]['total'] === 1000000, "Uji 1 Gagal: Total Januari bukan 1.000.000 (Didapat: {$laporan2026[1]['total']})");
assert($laporan2026[1]['transfer_bank'] === 1000000, "Uji 1 Gagal: Transfer bank Januari salah");
assert($laporan2026[1]['jumlah_transaksi'] === 1, "Uji 1 Gagal: Transaksi Januari bukan 1");

// Bulan 2: harus memuat 2000000 (batas 00:00:00)
assert($laporan2026[2]['total'] === 2000000, "Uji 1 Gagal: Total Februari bukan 2.000.000 (Didapat: {$laporan2026[2]['total']})");
assert($laporan2026[2]['e_wallet'] === 2000000, "Uji 1 Gagal: E-wallet Februari salah");
assert($laporan2026[2]['jumlah_transaksi'] === 1, "Uji 1 Gagal: Transaksi Februari bukan 1 (non-lunas mungkin ikut terhitung)");

// Bulan 3: harus memuat 3000000
assert($laporan2026[3]['total'] === 3000000, "Uji 1 Gagal: Total Maret bukan 3.000.000 (Didapat: {$laporan2026[3]['total']})");
assert($laporan2026[3]['tunai'] === 3000000, "Uji 1 Gagal: Tunai Maret salah");
assert($laporan2026[3]['jumlah_transaksi'] === 1, "Uji 1 Gagal: Transaksi Maret bukan 1");

// Bulan 4..12: harus 0
for ($m = 4; $m <= 12; $m++) {
    assert($laporan2026[$m]['total'] === 0, "Uji 1 Gagal: Bulan {$m} harus 0 (Didapat: {$laporan2026[$m]['total']})");
}
echo "UJI 1 LULUS: Batas bulan WIB (23:59:59 vs 00:00:00) dan penolakan status non-lunas akurat 100%.\n";

// UJI 2: Filter Metode Pembayaran
$laporanTunai = pemasukan_bulanan(2026, 'tunai');
assert($laporanTunai[1]['total'] === 0, "Uji 2 Gagal: Tunai Januari harus 0");
assert($laporanTunai[2]['total'] === 0, "Uji 2 Gagal: Tunai Februari harus 0");
assert($laporanTunai[3]['total'] === 3000000, "Uji 2 Gagal: Tunai Maret bukan 3.000.000");
echo "UJI 2 LULUS: Filter metode pembayaran spesifik ('tunai') mengisolasi transaksi dengan benar.\n";

// UJI 3: Pengujian Lintas Tahun (2025 vs 2026)
$laporan2025 = pemasukan_bulanan(2025, 'all');
assert($laporan2025[12]['total'] === 4000000, "Uji 3 Gagal: Desember 2025 bukan 4.000.000 (Didapat: {$laporan2025[12]['total']})");
assert($laporan2025[12]['transfer_bank'] === 4000000, "Uji 3 Gagal: Transfer bank Desember 2025 salah");
echo "UJI 3 LULUS: Pemisahan data lintas tahun (2025 vs 2026) akurat.\n";

// UJI 4: Pemasukan per Tipe Villa
$tipe2026 = pemasukan_per_tipe(2026);
assert($tipe2026['total'] === 6000000, "Uji 4 Gagal: Total seluruh tipe 2026 bukan 6.000.000 (Didapat: {$tipe2026['total']})");
// Villa Wulung (tipe 1) mendapat Jan (1.000.000) + Mar (3.000.000) = 4.000.000 (66.7%)
// Villa Saren (tipe 2) mendapat Feb (2.000.000) = 2.000.000 (33.3%)
foreach ($tipe2026['data'] as $item) {
    if ($item['id_tipe'] === 1) {
        assert($item['total_nominal'] === 4000000, "Uji 4 Gagal: Nominal Tipe 1 bukan 4.000.000");
        assert($item['persentase'] === 66.7, "Uji 4 Gagal: Persentase Tipe 1 bukan 66.7% (Didapat: {$item['persentase']})");
    } elseif ($item['id_tipe'] === 2) {
        assert($item['total_nominal'] === 2000000, "Uji 4 Gagal: Nominal Tipe 2 bukan 2.000.000");
        assert($item['persentase'] === 33.3, "Uji 4 Gagal: Persentase Tipe 2 bukan 33.3% (Didapat: {$item['persentase']})");
    }
}
echo "UJI 4 LULUS: Pemasukan per tipe villa dan perhitungan persentase kontribusi akurat.\n";

// UJI 5: Daftar Tahun Laporan
$tahunList = daftar_tahun_laporan();
assert(in_array(2025, $tahunList, true), "Uji 5 Gagal: Tahun 2025 tidak masuk daftar tahun");
assert(in_array(2026, $tahunList, true), "Uji 5 Gagal: Tahun 2026 tidak masuk daftar tahun");
echo "UJI 5 LULUS: Fungsi daftar_tahun_laporan() mengembalikan rentang tahun yang valid.\n";

// UJI 6: Verifikasi Index EXPLAIN Query
// Pastikan query laporan memakai index (tidak melakukan Full Table Scan / ALL)
$sqlExplain = "
    EXPLAIN SELECT 
        MONTH(p.dibayar_pada) AS bulan,
        p.metode_kelompok,
        COUNT(*) AS jml_transaksi,
        SUM(p.jumlah) AS subtotal
    FROM pembayaran p
    WHERE p.status = 'lunas'
      AND p.dibayar_pada >= '2026-01-01 00:00:00'
      AND p.dibayar_pada < '2027-01-01 00:00:00'
    GROUP BY MONTH(p.dibayar_pada), p.metode_kelompok
";
$stmtExp = $pdo->query($sqlExplain);
$explainRow = $stmtExp->fetch();

$usedKey = $explainRow['key'] ?? null;
$type = $explainRow['type'] ?? '';

echo "EXPLAIN ANALISIS: Type='{$type}', Key='{$usedKey}'\n";
assert(!empty($usedKey) && $type !== 'ALL', "Uji 6 Gagal: Query tidak memakai indeks (Type: {$type}, Key: {$usedKey})");
echo "UJI 6 LULUS: Query laporan terbukti memakai indeks basis data ({$usedKey}) dengan efisien.\n";

// Bersihkan data fixture
$pdo->prepare("DELETE FROM pembayaran WHERE order_id LIKE 'FIXTURE-T7-%'")->execute();
$pdo->prepare("DELETE FROM reservasi WHERE catatan LIKE 'FIXTURE-T7-%'")->execute();

echo "=== GERBANG TAHAP 7: SEMUA PENGUJIAN LOLOS DENGAN HASIL VALID ===\n";
