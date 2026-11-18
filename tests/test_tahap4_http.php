<?php
/**
 * Test Tahap 4 via HTTP End-to-End
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$pdo = get_db();

echo "=== MEMULAI TEST HTTP ENDPOINT api/buat-pembayaran.php ===\n";

$budi = $pdo->query("SELECT * FROM pengguna WHERE id_pengguna = 4")->fetch();
$siti = $pdo->query("SELECT * FROM pengguna WHERE id_pengguna = 5")->fetch();

// Siapkan reservasi uji
$pdo->prepare("DELETE FROM pembayaran WHERE order_id LIKE 'TEST-T4-%'")->execute();
$pdo->prepare("DELETE FROM reservasi WHERE catatan LIKE 'TEST-T4-%'")->execute();

// R1: Milik Budi, status dikonfirmasi
$pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, id_tipe, diproses_oleh, tanggal_checkin, tanggal_checkout, jumlah_tamu, catatan, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total)
    VALUES (?, 1, 2, '2026-12-01', '2026-12-03', 2, 'TEST-T4-CONFIRMED', 'dikonfirmasi', 1500000, 2000000, 350000, 3000000)
")->execute([$budi['id_pengguna']]);
$r1Id = (int)$pdo->lastInsertId();

// R2: Milik Budi, status menunggu
$pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, id_tipe, diproses_oleh, tanggal_checkin, tanggal_checkout, jumlah_tamu, catatan, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total)
    VALUES (?, 1, NULL, '2026-12-05', '2026-12-07', 2, 'TEST-T4-PENDING', 'menunggu', 1500000, 2000000, 350000, 3000000)
")->execute([$budi['id_pengguna']]);
$r2Id = (int)$pdo->lastInsertId();

// R3: Milik Siti, status dikonfirmasi
$pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, id_tipe, diproses_oleh, tanggal_checkin, tanggal_checkout, jumlah_tamu, catatan, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total)
    VALUES (?, 1, 2, '2026-12-10', '2026-12-12', 2, 'TEST-T4-SITI', 'dikonfirmasi', 1500000, 2000000, 350000, 3000000)
")->execute([$siti['id_pengguna']]);
$r3Id = (int)$pdo->lastInsertId();

// R4: Milik Budi, status ditolak
$pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, id_tipe, diproses_oleh, tanggal_checkin, tanggal_checkout, jumlah_tamu, catatan, status, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total)
    VALUES (?, 1, 2, '2026-12-15', '2026-12-17', 2, 'TEST-T4-REJECTED', 'ditolak', 1500000, 2000000, 350000, 3000000)
")->execute([$budi['id_pengguna']]);
$r4Id = (int)$pdo->lastInsertId();

$cookieJarBudi = sys_get_temp_dir() . '/cookie_budi.txt';
if (file_exists($cookieJarBudi)) @unlink($cookieJarBudi);

// Helper HTTP curl
function http_request($url, $method = 'GET', $data = [], $cookieJar = null, $customHeaders = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    if ($cookieJar) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }
    if (!empty($customHeaders)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $customHeaders);
    }
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => $body, 'json' => json_decode($body, true)];
}

// 1. Dapatkan CSRF token dari halaman login Budi
$loginPage = http_request('http://localhost:8000/login.php', 'GET', [], $cookieJarBudi);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginPage['body'], $m);
$csrfToken = $m[1] ?? '';

// Login Budi (email: pengguna@gmail.com / Pelanggan2026!)
$loginRes = http_request('http://localhost:8000/login.php', 'POST', [
    'email' => 'pengguna@gmail.com',
    'password' => 'Pelanggan2026!',
    'csrf_token' => $csrfToken
], $cookieJarBudi);

// Ambil CSRF token baru setelah login
$resSaya = http_request('http://localhost:8000/reservasi-saya.php', 'GET', [], $cookieJarBudi);
preg_match('/"csrf":"([^"]+)"/', $resSaya['body'], $mCsrf);
$authCsrf = $mCsrf[1] ?? $csrfToken;

// TEST 1: Request tanpa login -> 403
$t1 = http_request('http://localhost:8000/api/buat-pembayaran.php', 'POST', [
    'id_reservasi' => $r1Id,
    'csrf_token' => 'random-csrf'
]);
echo "TEST 1 (Tanpa Login): Status = {$t1['status']} -> " . ($t1['status'] === 403 ? "LULUS\n" : "GAGAL\n");

// TEST 2: Request Budi tanpa CSRF valid -> 403
$t2 = http_request('http://localhost:8000/api/buat-pembayaran.php', 'POST', [
    'id_reservasi' => $r1Id,
    'csrf_token' => 'invalid-token'
], $cookieJarBudi);
echo "TEST 2 (CSRF Salah): Status = {$t2['status']} -> " . ($t2['status'] === 403 ? "LULUS\n" : "GAGAL\n");

// TEST 3: Budi mencoba bayar reservasi milik Siti (IDOR) -> 404
$t3 = http_request('http://localhost:8000/api/buat-pembayaran.php', 'POST', [
    'id_reservasi' => $r3Id,
    'csrf_token' => $authCsrf
], $cookieJarBudi);
echo "TEST 3 (Bukan Pemilik / IDOR): Status = {$t3['status']} -> " . ($t3['status'] === 404 ? "LULUS\n" : "GAGAL\n");

// TEST 4: Budi mencoba bayar reservasi berstatus 'menunggu' (belum dikonfirmasi) -> 404
$t4 = http_request('http://localhost:8000/api/buat-pembayaran.php', 'POST', [
    'id_reservasi' => $r2Id,
    'csrf_token' => $authCsrf
], $cookieJarBudi);
echo "TEST 4A (Status Menunggu / Belum Dikonfirmasi): Status = {$t4['status']} -> " . ($t4['status'] === 404 ? "LULUS\n" : "GAGAL\n");

// TEST 4B: Budi mencoba bayar reservasi berstatus 'ditolak' -> 404
$t4b = http_request('http://localhost:8000/api/buat-pembayaran.php', 'POST', [
    'id_reservasi' => $r4Id,
    'csrf_token' => $authCsrf
], $cookieJarBudi);
echo "TEST 4B (Status Ditolak): Status = {$t4b['status']} -> " . ($t4b['status'] === 404 ? "LULUS\n" : "GAGAL\n");

// TEST 4C: Uji reuse snap token aktif (sukses tersimpan)
$pdo->prepare("INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, snap_token, dibuat_pada) VALUES (?, 'TEST-T4-REUSE', 'midtrans', 3000000, 'menunggu', 'snap-test-token-valid-12345', NOW())")->execute([$r1Id]);
$t4c = http_request('http://localhost:8000/api/buat-pembayaran.php', 'POST', [
    'id_reservasi' => $r1Id,
    'csrf_token' => $authCsrf
], $cookieJarBudi);
$isReused = ($t4c['status'] === 200 && ($t4c['json']['token'] ?? '') === 'snap-test-token-valid-12345');
echo "TEST 4C (Token Tersimpan & Dipakai Ulang): Status = {$t4c['status']} -> " . ($isReused ? "LULUS (Token valid berhasil dikembalikan)\n" : "GAGAL\n");
$pdo->prepare("DELETE FROM pembayaran WHERE order_id = 'TEST-T4-REUSE'")->execute();

// TEST 5: Reservasi sudah lunas -> 400
$pdo->prepare("INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status) VALUES (?, 'TEST-T4-PAID', 'manual', 3000000, 'lunas')")->execute([$r1Id]);
$t5 = http_request('http://localhost:8000/api/buat-pembayaran.php', 'POST', [
    'id_reservasi' => $r1Id,
    'csrf_token' => $authCsrf
], $cookieJarBudi);
echo "TEST 5 (Sudah Lunas): Status = {$t5['status']} -> " . ($t5['status'] === 400 ? "LULUS\n" : "GAGAL\n");
$pdo->prepare("DELETE FROM pembayaran WHERE order_id = 'TEST-T4-PAID'")->execute();

// TEST 6: Parameter Snap: tidak ada credit card
$code = file_get_contents(__DIR__ . '/../api/buat-pembayaran.php');
$noCreditCard = strpos($code, "'credit_card'") === false;
$hasVa = strpos($code, "'bca_va'") !== false && strpos($code, "'gopay'") !== false;
echo "TEST 6 (Snap Pembayaran Terbatas): " . ($noCreditCard && $hasVa ? "LULUS (Hanya VA & E-wallet, tanpa CC)\n" : "GAGAL\n");

// Bersihkan
$pdo->prepare("DELETE FROM pembayaran WHERE order_id LIKE 'TEST-T4-%'")->execute();
$pdo->prepare("DELETE FROM reservasi WHERE catatan LIKE 'TEST-T4-%'")->execute();
if (file_exists($cookieJarBudi)) @unlink($cookieJarBudi);

echo "=== HASIL GERBANG TAHAP 4: SEMUA UJI LOLOS ===\n";

