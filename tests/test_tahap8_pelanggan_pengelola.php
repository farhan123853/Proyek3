<?php
/**
 * Test Gerbang Tahap 8 (Bagian 1): Tampilan Pelanggan dan Pengelola
 */

require_once __DIR__ . '/../includes/bootstrap.php';
$pdo = get_db();

echo "=== MEMULAI PENGUJIAN TAHAP 8 (BAGIAN 1): PELANGGAN & PENGELOLA ===\n";

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

$cookiePelanggan = sys_get_temp_dir() . '/cookie_pelanggan_t8.txt';
$cookiePengelola = sys_get_temp_dir() . '/cookie_pengelola_t8.txt';

$csrfPelanggan = login_helper('pengguna@gmail.com', 'Pelanggan2026!', $cookiePelanggan, 'http://localhost:8000/reservasi-saya.php');
$csrfPengelola = login_helper('pengelola@joglomasayu.com', 'Pengelola2026!', $cookiePengelola, 'http://localhost:8000/dashboard/pengelola.php');

// 0. Siapkan Data Reservasi Uji
$pdo->prepare("DELETE FROM pembayaran WHERE order_id LIKE 'TEST-T8-%'")->execute();
$pdo->prepare("DELETE FROM reservasi WHERE catatan LIKE 'TEST-T8-%'")->execute();

// R1: Dikonfirmasi, belum lunas
$pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, id_tipe, diproses_oleh, tanggal_checkin, tanggal_checkout, jumlah_tamu, catatan, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, dibuat_pada)
    VALUES (4, 1, 2, '2026-12-10', '2026-12-12', 2, 'TEST-T8-R1', 'dikonfirmasi', 1500000, 2000000, 350000, 3000000, NOW())
")->execute();
$r1Id = (int)$pdo->lastInsertId();

// R2: Dikonfirmasi, sudah LUNAS
$pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, id_tipe, diproses_oleh, tanggal_checkin, tanggal_checkout, jumlah_tamu, catatan, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, dibuat_pada)
    VALUES (4, 2, 2, '2026-12-15', '2026-12-17', 4, 'TEST-T8-R2', 'dikonfirmasi', 2200000, 2900000, 350000, 4400000, NOW())
")->execute();
$r2Id = (int)$pdo->lastInsertId();

$pdo->prepare("
    INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, metode_kelompok, metode_detail, dibayar_pada)
    VALUES (?, 'TEST-T8-PAID', 'midtrans', 4400000, 'lunas', 'transfer_bank', 'bca_va', NOW())
")->execute([$r2Id]);

// UJI 1: Pelanggan membuka reservasi-saya.php
$pagePelanggan = http_req('http://localhost:8000/reservasi-saya.php', 'GET', [], $cookiePelanggan);
assert($pagePelanggan['status'] === 200, "Uji 1 Gagal: reservasi-saya status bukan 200");
assert(strpos($pagePelanggan['body'], 'snap/snap.js') !== false, "Uji 1 Gagal: Midtrans Snap JS tidak termuat");
assert(strpos($pagePelanggan['body'], 'btn-pay-snap') !== false, "Uji 1 Gagal: Tombol bayar tidak ditemukan");
assert(strpos($pagePelanggan['body'], 'data-payment-status="lunas"') !== false, "Uji 1 Gagal: Badge lunas tidak ditemukan");
echo "UJI 1 LULUS: Halaman reservasi-saya Pelanggan memuat Snap JS, badge status pembayaran, dan tombol bayar.\n";

// UJI 2: Pelanggan dalam bahasa Inggris (reservasi-saya.php?lang=en)
$pageEn = http_req('http://localhost:8000/reservasi-saya.php?lang=en', 'GET', [], $cookiePelanggan);
assert(strpos($pageEn['body'], 'My Reservations') !== false, "Uji 2 Gagal: Bahasa Inggris 'My Reservations' tidak ditemukan");
// Kembalikan ke ID
http_req('http://localhost:8000/reservasi-saya.php?lang=id', 'GET', [], $cookiePelanggan);
echo "UJI 2 LULUS: Tampilan dwibahasa ID/EN pada halaman Pelanggan berfungsi baik.\n";

// UJI 3: Pengelola membuka dashboard/pengelola.php
$pagePengelola = http_req('http://localhost:8000/dashboard/pengelola.php', 'GET', [], $cookiePengelola);
assert($pagePengelola['status'] === 200, "Uji 3 Gagal: dashboard pengelola bukan 200");
assert(strpos($pagePengelola['body'], 'modal-catat-tunai') !== false, "Uji 3 Gagal: Modal catat tunai tidak ditemukan");
assert(strpos($pagePengelola['body'], 'btn-open-cash-modal') !== false, "Uji 3 Gagal: Tombol catat tunai tidak ditemukan");
assert(strpos($pagePengelola['body'], 'name="bayar"') !== false, "Uji 3 Gagal: Filter status bayar tidak ditemukan");
assert(strpos($pagePengelola['body'], 'wa.me') !== false, "Uji 3 Gagal: Tautan WhatsApp tidak ditemukan");
echo "UJI 3 LULUS: Dashboard Pengelola memuat kolom pembayaran, filter, tombol catat tunai, dan WhatsApp.\n";

// UJI 4: Penegakan Aturan Bisnis Server: Reservasi LUNAS tidak boleh ditolak oleh Pengelola
$resTolakLunas = http_req('http://localhost:8000/dashboard/pengelola.php', 'POST', [
    'id_reservasi' => $r2Id,
    'aksi' => 'tolak',
    'csrf_token' => $csrfPengelola
], $cookiePengelola);

// Cek status di DB: harus tetap 'dikonfirmasi', TIDAK berubah menjadi 'ditolak'
$cekStatusR2 = $pdo->query("SELECT status FROM reservasi WHERE id_reservasi = {$r2Id}")->fetchColumn();
assert($cekStatusR2 === 'dikonfirmasi', "Uji 4 Gagal: Reservasi lunas berhasil ditolak (seharusnya ditolak oleh server)!");
echo "UJI 4 LULUS: Penegakan aturan bisnis di server terbukti mencegah penolakan reservasi yang sudah lunas.\n";

// UJI 5: Ketiadaan alert() di JavaScript
$filesToCheck = [
    __DIR__ . '/../reservasi-saya.php',
    __DIR__ . '/../dashboard/pengelola.php'
];
foreach ($filesToCheck as $f) {
    $content = file_get_contents($f);
    assert(strpos($content, 'alert(') === false, "Uji 5 Gagal: Terdapat panggilan alert() di " . basename($f));
}
echo "UJI 5 LULUS: Tidak ada penggunaan alert() browser, semua notifikasi menggunakan sistem toast/flash.\n";

// Bersihkan data uji & cookie
$pdo->prepare("DELETE FROM pembayaran WHERE order_id LIKE 'TEST-T8-%'")->execute();
$pdo->prepare("DELETE FROM reservasi WHERE catatan LIKE 'TEST-T8-%'")->execute();
@unlink($cookiePelanggan);
@unlink($cookiePengelola);

echo "=== GERBANG TAHAP 8 (BAGIAN 1): SEMUA PENGUJIAN LOLOS DENGAN HASIL VALID ===\n";
