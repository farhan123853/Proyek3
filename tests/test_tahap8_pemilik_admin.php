<?php
/**
 * Test Gerbang Tahap 8 (Bagian 2): Dashboard Pemilik & Admin
 * Menguji:
 * 1. Hak akses dan halaman Pemilik (Read-only, 4 kartu ringkasan, grafik batang bertumpuk, tabel bulanan, kartu tipe villa, kartu terpisah estimasi)
 * 2. API Laporan Pemilik (api/laporan-pemilik.php) dan verifikasi pemasukan bulan ini bertambah setelah pembayaran lunas
 * 3. Filter tahun dan metode bekerja secara konsisten
 * 4. Halaman Admin (Badge mode sandbox, Tab Transaksi dengan tombol sinkronkan & batalkan tunai, modal alasan batal wajib, proteksi akun admin sendiri)
 * 5. Tampilan dwibahasa ID/EN pada Pemilik dan Admin
 * 6. Bebas alert() browser
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/laporan.php';

echo "=== MEMULAI PENGUJIAN TAHAP 8 (BAGIAN 2): PEMILIK & ADMIN ===\n";

$baseUrl = 'http://localhost:8000';
$pdo = get_db();

function http_req($url, $method = 'GET', $data = [], $cookieJar = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    if ($cookieJar) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => $body];
}

function login_helper($email, $password, $cookieFile, $dashUrl) {
    if (file_exists($cookieFile)) @unlink($cookieFile);
    $loginPage = http_req('http://localhost:8000/login.php', 'GET', [], $cookieFile);
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginPage['body'], $m);
    $csrfToken = $m[1] ?? '';

    $loginRes = http_req('http://localhost:8000/login.php', 'POST', [
        'email' => $email,
        'password' => $password,
        'csrf_token' => $csrfToken
    ], $cookieFile);

    $dashPage = http_req($dashUrl, 'GET', [], $cookieFile);
    preg_match('/"csrf":"([^"]+)"/', $dashPage['body'], $mCsrf);
    return $mCsrf[1] ?? $csrfToken;
}

$cookiePemilik = sys_get_temp_dir() . '/cookie_pemilik_t8.txt';
$cookieAdmin   = sys_get_temp_dir() . '/cookie_admin_t8.txt';

$csrfPemilik = login_helper('pemilik@joglomasayu.com', 'Pemilik2026!', $cookiePemilik, 'http://localhost:8000/dashboard/pemilik.php');
$csrfAdmin   = login_helper('admin@joglomasayu.com', 'AdminMasayu2026!', $cookieAdmin, 'http://localhost:8000/dashboard/admin.php');

// -------------------------------------------------------------------
// UJI 1: Hak Akses & Elemen Dashboard Pemilik (Read-Only)
// -------------------------------------------------------------------
$resPemilik = http_req($baseUrl . '/dashboard/pemilik.php', 'GET', [], $cookiePemilik);
assert($resPemilik['status'] === 200, "Akses dashboard/pemilik.php harus 200, dapat: " . $resPemilik['status']);

// Verifikasi komponen wajib di Dashboard Pemilik
assert(strpos($resPemilik['body'], 'Dashboard Pemilik') !== false, "Dashboard Pemilik harus memiliki judul");
assert(strpos($resPemilik['body'], 'Pemasukan per bulan, berdasarkan tanggal pembayaran') !== false, "Subjudul harus menjelaskan dasar tanggal pembayaran");
assert(strpos($resPemilik['body'], 'cardBulanIni') !== false, "Harus memuat kartu Bulan Ini");
assert(strpos($resPemilik['body'], 'cardBulanLalu') !== false, "Harus memuat kartu Bulan Lalu");
assert(strpos($resPemilik['body'], 'cardTotalTahun') !== false, "Harus memuat kartu Total Tahun");
assert(strpos($resPemilik['body'], 'cardRataRata') !== false, "Harus memuat kartu Rata-rata per Bulan");
assert(strpos($resPemilik['body'], 'chartBarsContainer') !== false, "Harus memuat kontainer grafik batang 12 bulan");
assert(strpos($resPemilik['body'], 'desktopTable') !== false, "Harus memuat tabel rincian per bulan");
assert(strpos($resPemilik['body'], 'mobileCardsContainer') !== false, "Harus memuat kartu per bulan untuk mobile");
assert(strpos($resPemilik['body'], 'villaBreakdownContainer') !== false, "Harus memuat kontainer rincian per tipe villa");
assert(strpos($resPemilik['body'], 'Estimasi, belum tentu sudah dibayar') !== false, "Kartu estimasi terkonfirmasi harus memiliki label kepatuhan wajib");

// Verifikasi Pemilik READ-ONLY: Tidak boleh ada form POST untuk mutasi data
assert(strpos($resPemilik['body'], '<form method="POST"') === false, "Dashboard Pemilik TIDAK BOLEH memuat form metode POST (Murni Read-Only)");

echo "UJI 1 LULUS: Dashboard Pemilik terbukti Read-Only dan memuat seluruh elemen visual laporan.\n";

// -------------------------------------------------------------------
// UJI 2: API Laporan Pemilik & Peningkatan Pemasukan Pasca Pembayaran Lunas
// -------------------------------------------------------------------
$resApiAwal = http_req($baseUrl . '/api/laporan-pemilik.php?tahun=' . date('Y') . '&metode=all', 'GET', [], $cookiePemilik);
assert($resApiAwal['status'] === 200, "API laporan-pemilik.php harus mengembalikan HTTP 200");
$dataAwal = json_decode($resApiAwal['body'], true);
assert(!empty($dataAwal['ok']) && isset($dataAwal['ringkasan']), "Respons API laporan-pemilik.php harus valid");

$pemasukanAwalBulanIni = (int)$dataAwal['ringkasan']['bulan_ini'];
$totalAwalTahun = (int)$dataAwal['ringkasan']['total_tahun'];

// Buat fixture reservasi dan catat pembayaran lunas
$nominalUji = 1750000;
$stmtRsv = $pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, id_tipe, diproses_oleh, tanggal_checkin, tanggal_checkout, jumlah_tamu, catatan, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, dibuat_pada)
    VALUES (4, 1, 2, '2026-11-20', '2026-11-22', 2, 'TEST-PEMILIK-RSV', 'dikonfirmasi', 1500000, 2000000, 350000, ?, NOW())
");
$stmtRsv->execute([$nominalUji]);
$idRsvUji = (int)$pdo->lastInsertId();

$orderIdUji = 'TEST-PEMILIK-' . time() . '-' . rand(100, 999);
$stmtPay = $pdo->prepare("
    INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, metode_kelompok, metode_detail, dibayar_pada, dibuat_pada)
    VALUES (?, ?, 'midtrans', ?, 'lunas', 'transfer_bank', 'bank_transfer', NOW(), NOW())
");
$stmtPay->execute([$idRsvUji, $orderIdUji, $nominalUji]);

// Ambil data laporan kembali melalui API
$resApiSetelah = http_req($baseUrl . '/api/laporan-pemilik.php?tahun=' . date('Y') . '&metode=all', 'GET', [], $cookiePemilik);
$dataSetelah = json_decode($resApiSetelah['body'], true);

$pemasukanSetelahBulanIni = (int)$dataSetelah['ringkasan']['bulan_ini'];
$totalSetelahTahun = (int)$dataSetelah['ringkasan']['total_tahun'];

assert($pemasukanSetelahBulanIni === ($pemasukanAwalBulanIni + $nominalUji), "Pemasukan bulan ini harus bertambah tepat sebesar nominal transaksi lunas");
assert($totalSetelahTahun === ($totalAwalTahun + $nominalUji), "Total pemasukan tahun terpilih harus bertambah tepat sebesar nominal transaksi lunas");

echo "UJI 2 LULUS: API laporan-pemilik.php terverifikasi dan pemasukan bulan ini terbukti bertambah secara akurat.\n";

// -------------------------------------------------------------------
// UJI 3: Filter Tahun dan Metode
// -------------------------------------------------------------------
$resFilterBank = http_req($baseUrl . '/api/laporan-pemilik.php?tahun=' . date('Y') . '&metode=transfer_bank', 'GET', [], $cookiePemilik);
$dataBank = json_decode($resFilterBank['body'], true);
assert($dataBank['ringkasan']['total_tahun'] >= $nominalUji, "Filter transfer_bank harus menyertakan transaksi bank yang baru dibuat");

$resFilterTunai = http_req($baseUrl . '/api/laporan-pemilik.php?tahun=' . date('Y') . '&metode=tunai', 'GET', [], $cookiePemilik);
$dataTunai = json_decode($resFilterTunai['body'], true);
assert($dataTunai['ringkasan']['total_transfer'] === 0, "Filter tunai tidak boleh memuat transaksi transfer_bank");

echo "UJI 3 LULUS: Filter metode pembayaran bekerja memilah data secara konsisten.\n";

// -------------------------------------------------------------------
// UJI 4: Dashboard Admin (Badge Mode, Tab Transaksi, Batalkan Tunai)
// -------------------------------------------------------------------
$resAdmin = http_req($baseUrl . '/dashboard/admin.php?tab=transaksi', 'GET', [], $cookieAdmin);
assert($resAdmin['status'] === 200, "Dashboard Admin harus mengembalikan HTTP 200");

// Verifikasi elemen wajib Dashboard Admin
assert(strpos($resAdmin['body'], 'Mode: Sandbox') !== false || strpos($resAdmin['body'], 'Mode: Production') !== false, "Harus menampilkan badge Mode Sandbox/Production");
assert(strpos($resAdmin['body'], 'Transaksi Pembayaran') !== false, "Harus memuat tab Transaksi Pembayaran");
assert(strpos($resAdmin['body'], 'Manajemen Akun Staf') !== false, "Harus memuat tab Manajemen Akun Staf");
assert(strpos($resAdmin['body'], 'modalBatalTunai') !== false, "Harus memuat modal Batalkan Catatan Tunai");
assert(strpos($resAdmin['body'], 'alasanBatalInput') !== false, "Modal harus memuat input alasan pembatalan wajib");

// Verifikasi proteksi akun Admin sendiri di Tab Staf
$resAdminStaff = http_req($baseUrl . '/dashboard/admin.php?tab=staff', 'GET', [], $cookieAdmin);
assert(strpos($resAdminStaff['body'], 'Akun Anda') !== false, "Akun admin aktif harus ditandai khusus");
assert(strpos($resAdminStaff['body'], 'Akun Admin Aktif') !== false, "Admin tidak boleh memiliki tombol nonaktifkan untuk akunnya sendiri");

echo "UJI 4 LULUS: Dashboard Admin memuat tab transaksi, badge mode, dialog pembatalan tunai, dan proteksi akun.\n";

// -------------------------------------------------------------------
// UJI 5: Pengujian Dwibahasa ID & EN
// -------------------------------------------------------------------
$resLangEn = http_req($baseUrl . '/dashboard/pemilik.php?lang=en', 'GET', [], $cookiePemilik);
assert(strpos($resLangEn['body'], 'Owner Dashboard') !== false, "Dashboard Pemilik harus mendukung bahasa Inggris ('Owner Dashboard')");
assert(strpos($resLangEn['body'], 'Monthly revenue, based on payment date') !== false, "Subjudul harus dalam bahasa Inggris");
assert(strpos($resLangEn['body'], 'Estimated, not necessarily paid yet') !== false, "Label estimasi harus dalam bahasa Inggris");

$resAdminEn = http_req($baseUrl . '/dashboard/admin.php?tab=transaksi&lang=en', 'GET', [], $cookieAdmin);
assert(strpos($resAdminEn['body'], 'Payment Transactions') !== false, "Tab transaksi Admin harus dalam bahasa Inggris");
assert(strpos($resAdminEn['body'], 'Staff Account Management') !== false, "Tab staf Admin harus dalam bahasa Inggris");

// Kembalikan ke ID
http_req($baseUrl . '/dashboard/pemilik.php?lang=id', 'GET', [], $cookiePemilik);

echo "UJI 5 LULUS: Tampilan dwibahasa ID/EN pada Pemilik dan Admin berfungsi sempurna.\n";

// -------------------------------------------------------------------
// UJI 6: Bebas dari alert() browser
// -------------------------------------------------------------------
$filesToScan = [
    __DIR__ . '/../dashboard/pemilik.php',
    __DIR__ . '/../dashboard/admin.php',
    __DIR__ . '/../api/laporan-pemilik.php',
];

foreach ($filesToScan as $f) {
    $content = file_get_contents($f);
    assert(preg_match('/[^\w]alert\s*\(/', $content) === 0, "Berkas " . basename($f) . " tidak boleh menggunakan alert() browser!");
}

echo "UJI 6 LULUS: Tidak ada penggunaan alert() browser, seluruh interaksi menggunakan toast dan modal.\n";

echo "=== GERBANG TAHAP 8 (BAGIAN 2): SEMUA PENGUJIAN LOLOS DENGAN HASIL VALID ===\n";
