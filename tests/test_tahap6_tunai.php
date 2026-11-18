<?php
/**
 * Test Gerbang Tahap 6: Pembayaran Tunai & Pembatalan Catatan Tunai
 */

require_once __DIR__ . '/../includes/bootstrap.php';
$pdo = get_db();

echo "=== MEMULAI PENGUJIAN TAHAP 6: PEMBAYARAN TUNAI ===\n";

function http_request($url, $method = 'GET', $data = [], $cookieJar = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
    return ['status' => $status, 'body' => $body, 'json' => json_decode($body, true)];
}

function login_helper($email, $password, $cookieFile, $dashUrl) {
    if (file_exists($cookieFile)) @unlink($cookieFile);
    $loginPage = http_request('http://localhost:8000/login.php', 'GET', [], $cookieFile);
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginPage['body'], $m);
    $csrfToken = $m[1] ?? '';

    $loginRes = http_request('http://localhost:8000/login.php', 'POST', [
        'email' => $email,
        'password' => $password,
        'csrf_token' => $csrfToken
    ], $cookieFile);

    // Ambil halaman dashboard pengguna untuk mendapatkan CSRF token sesi aktif
    $dashPage = http_request($dashUrl, 'GET', [], $cookieFile);
    preg_match('/"csrf":"([^"]+)"/', $dashPage['body'], $mCsrf);
    return $mCsrf[1] ?? $csrfToken;
}

$cookiePelanggan = sys_get_temp_dir() . '/cookie_pelanggan_t6.txt';
$cookiePengelola = sys_get_temp_dir() . '/cookie_pengelola_t6.txt';
$cookiePemilik   = sys_get_temp_dir() . '/cookie_pemilik_t6.txt';
$cookieAdmin     = sys_get_temp_dir() . '/cookie_admin_t6.txt';

$csrfPelanggan = login_helper('pengguna@gmail.com', 'Pelanggan2026!', $cookiePelanggan, 'http://localhost:8000/reservasi-saya.php');
$csrfPengelola = login_helper('pengelola@joglomasayu.com', 'Pengelola2026!', $cookiePengelola, 'http://localhost:8000/dashboard/pengelola.php');
$csrfPemilik   = login_helper('pemilik@joglomasayu.com', 'Pemilik2026!', $cookiePemilik, 'http://localhost:8000/dashboard/pemilik.php');
$csrfAdmin     = login_helper('admin@joglomasayu.com', 'AdminMasayu2026!', $cookieAdmin, 'http://localhost:8000/dashboard/admin.php');

// 0. Siapkan Data Reservasi Uji
$pdo->prepare("DELETE FROM pembayaran WHERE order_id LIKE 'TEST-T6-%' OR order_id LIKE 'TUNAI-%'")->execute();
$pdo->prepare("DELETE FROM reservasi WHERE catatan LIKE 'TEST-T6-%'")->execute();

// R1: Dikonfirmasi, belum lunas
$pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, id_tipe, diproses_oleh, tanggal_checkin, tanggal_checkout, jumlah_tamu, catatan, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, dibuat_pada)
    VALUES (4, 1, 2, '2026-12-01', '2026-12-03', 2, 'TEST-T6-RES-1', 'dikonfirmasi', 1500000, 2000000, 350000, 3000000, '2026-11-01 10:00:00')
")->execute();
$r1Id = (int)$pdo->lastInsertId();

// R2: Menunggu (belum dikonfirmasi)
$pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, id_tipe, diproses_oleh, tanggal_checkin, tanggal_checkout, jumlah_tamu, catatan, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, dibuat_pada)
    VALUES (4, 1, NULL, '2026-12-05', '2026-12-07', 2, 'TEST-T6-RES-2', 'menunggu', 1500000, 2000000, 350000, 3000000, '2026-11-01 10:00:00')
")->execute();
$r2Id = (int)$pdo->lastInsertId();

// UJI 1: Pelanggan, Pemilik, Admin tidak bisa mencatat tunai (Harus 403)
$t1a = http_request('http://localhost:8000/api/catat-tunai.php', 'POST', ['id_reservasi' => $r1Id, 'csrf_token' => $csrfPelanggan], $cookiePelanggan);
assert($t1a['status'] === 403, "Uji 1A Gagal: Pelanggan tidak ditolak 403 (Status: {$t1a['status']})");

$t1b = http_request('http://localhost:8000/api/catat-tunai.php', 'POST', ['id_reservasi' => $r1Id, 'csrf_token' => $csrfPemilik], $cookiePemilik);
assert($t1b['status'] === 403, "Uji 1B Gagal: Pemilik tidak ditolak 403 (Status: {$t1b['status']})");

$t1c = http_request('http://localhost:8000/api/catat-tunai.php', 'POST', ['id_reservasi' => $r1Id, 'csrf_token' => $csrfAdmin], $cookieAdmin);
assert($t1c['status'] === 403, "Uji 1C Gagal: Admin tidak ditolak 403 (Status: {$t1c['status']})");
echo "UJI 1 LULUS: Akses catat tunai hanya untuk Pengelola (Pelanggan, Pemilik, Admin ditolak 403).\n";

// UJI 2A: Reservasi bukan status 'dikonfirmasi' ditolak (Harus 400)
$t2a = http_request('http://localhost:8000/api/catat-tunai.php', 'POST', ['id_reservasi' => $r2Id, 'csrf_token' => $csrfPengelola], $cookiePengelola);
assert($t2a['status'] === 400, "Uji 2A Gagal: Status reservasi 'menunggu' tidak ditolak 400 (Status: {$t2a['status']})");
echo "UJI 2A LULUS: Reservasi belum disetujui ditolak mencatat tunai.\n";

// UJI 3: Tanggal masa depan ditolak (Harus 400)
$t3 = http_request('http://localhost:8000/api/catat-tunai.php', 'POST', [
    'id_reservasi' => $r1Id,
    'tanggal_bayar' => '2029-01-01 10:00:00',
    'csrf_token' => $csrfPengelola
], $cookiePengelola);
assert($t3['status'] === 400, "Uji 3 Gagal: Tanggal masa depan tidak ditolak 400 (Status: {$t3['status']})");
echo "UJI 3 LULUS: Tanggal pembayaran di masa depan ditolak.\n";

// UJI 4: Transaksi Midtrans pending ikut diakhiri saat Pengelola mencatat tunai
// Sisipkan order Midtrans berstatus 'menunggu' untuk R1
$pdo->prepare("INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status) VALUES (?, 'TEST-T6-MIDTRANS-PENDING', 'midtrans', 3000000, 'menunggu')")->execute([$r1Id]);

$t4 = http_request('http://localhost:8000/api/catat-tunai.php', 'POST', [
    'id_reservasi' => $r1Id,
    'tanggal_bayar' => '2026-11-15 14:00:00',
    'catatan' => 'Uji pencatatan tunai pengelola',
    'csrf_token' => $csrfPengelola
], $cookiePengelola);
assert($t4['status'] === 200, "Uji 4 Gagal: Catat tunai Pengelola gagal (Status: {$t4['status']}, Body: {$t4['body']})");

// Cek apakah order Midtrans pending diubah menjadi 'kedaluwarsa'
$cekPending = $pdo->query("SELECT status FROM pembayaran WHERE order_id = 'TEST-T6-MIDTRANS-PENDING'")->fetch();
assert($cekPending['status'] === 'kedaluwarsa', "Uji 4 Gagal: Transaksi Midtrans pending tidak diubah menjadi 'kedaluwarsa'");

// Cek order tunai baru berstatus 'lunas'
$cekTunai = $pdo->query("SELECT * FROM pembayaran WHERE id_reservasi = {$r1Id} AND sumber = 'manual' AND status = 'lunas'")->fetch();
assert(!empty($cekTunai), "Uji 4 Gagal: Baris pembayaran tunai lunas tidak ditemukan");
assert($cekTunai['metode_kelompok'] === 'tunai', "Uji 4 Gagal: Kelompok metode bukan tunai");
assert((int)$cekTunai['jumlah'] === 3000000, "Uji 4 Gagal: Nominal tidak otomatis sesuai estimasi total");
echo "UJI 4 LULUS: Catat tunai Pengelola berhasil, nominal otomatis dari server, dan Midtrans pending diakhiri ('kedaluwarsa').\n";

// UJI 2B: Reservasi yang SUDAH lunas ditolak jika dicatat tunai lagi (Harus 400)
$t2b = http_request('http://localhost:8000/api/catat-tunai.php', 'POST', ['id_reservasi' => $r1Id, 'csrf_token' => $csrfPengelola], $cookiePengelola);
assert($t2b['status'] === 400, "Uji 2B Gagal: Reservasi yang sudah lunas tidak ditolak (Status: {$t2b['status']})");
echo "UJI 2B LULUS: Reservasi yang sudah lunas ditolak mencatat tunai ganda.\n";

// UJI 5: Pembatalan Catatan Tunai (api/batal-tunai.php)
$idTunai = (int)$cekTunai['id_pembayaran'];

// 5A: Pelanggan/Pengelola/Pemilik mencoba membatalkan -> Harus 403
$t5a = http_request('http://localhost:8000/api/batal-tunai.php', 'POST', [
    'id_pembayaran' => $idTunai,
    'alasan_batal' => 'Salah input oleh pengelola',
    'csrf_token' => $csrfPengelola
], $cookiePengelola);
assert($t5a['status'] === 403, "Uji 5A Gagal: Pengelola tidak ditolak 403 saat batal tunai (Status: {$t5a['status']})");

// 5B: Admin mencoba membatalkan TANPA alasan -> Harus 400
$t5b = http_request('http://localhost:8000/api/batal-tunai.php', 'POST', [
    'id_pembayaran' => $idTunai,
    'alasan_batal' => '',
    'csrf_token' => $csrfAdmin
], $cookieAdmin);
assert($t5b['status'] === 400, "Uji 5B Gagal: Admin tanpa alasan tidak ditolak 400 (Status: {$t5b['status']})");

// 5C: Admin membatalkan DENGAN alasan valid -> Harus 200
$t5c = http_request('http://localhost:8000/api/batal-tunai.php', 'POST', [
    'id_pembayaran' => $idTunai,
    'alasan_batal' => 'Salah pencatatan oleh staf pengelola',
    'csrf_token' => $csrfAdmin
], $cookieAdmin);
assert($t5c['status'] === 200, "Uji 5C Gagal: Admin batal tunai gagal (Status: {$t5c['status']}, Body: {$t5c['body']})");

// Verifikasi status di DB: status harus 'dibatalkan', jejak tersimpan (tidak terhapus)
$cekBatal = $pdo->query("SELECT status, dibatalkan_oleh, alasan_batal FROM pembayaran WHERE id_pembayaran = {$idTunai}")->fetch();
assert($cekBatal['status'] === 'dibatalkan', "Uji 5C Gagal: Status bukan 'dibatalkan'");
assert(!empty($cekBatal['dibatalkan_oleh']), "Uji 5C Gagal: dibatalkan_oleh kosong");
assert($cekBatal['alasan_batal'] === 'Salah pencatatan oleh staf pengelola', "Uji 5C Gagal: alasan_batal tidak cocok");

// Verifikasi: Reservasi kembali dapat dibayar karena lunas_key menjadi NULL
$efektif = status_bayar_efektif($r1Id);
assert($efektif['status'] !== 'lunas', "Uji 5C Gagal: Status bayar efektif reservasi masih lunas!");
echo "UJI 5 LULUS: Batal tunai HANYA untuk Admin, wajib alasan, jejak baris tersimpan aman, dan reservasi kembali dapat dibayar.\n";

// Bersihkan data uji & cookie
$pdo->prepare("DELETE FROM pembayaran WHERE order_id LIKE 'TEST-T6-%' OR order_id LIKE 'TUNAI-%'")->execute();
$pdo->prepare("DELETE FROM reservasi WHERE catatan LIKE 'TEST-T6-%'")->execute();
@unlink($cookiePelanggan);
@unlink($cookiePengelola);
@unlink($cookiePemilik);
@unlink($cookieAdmin);

echo "=== GERBANG TAHAP 6: SEMUA PENGUJIAN LOLOS DENGAN HASIL VALID ===\n";
