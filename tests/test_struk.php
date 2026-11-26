<?php
/**
 * Test Suite: Detail Pembayaran (Struk / Resi & Rincian Tagihan)
 * Menguji: RBAC, Anti-IDOR, Validasi Input, Struktur JSON, Keamanan Kunci, dan Tampilan Cetak
 */

require_once __DIR__ . '/../includes/bootstrap.php';
$pdo = get_db();

echo "========================================================\n";
echo "   PENGUJIAN DETAIL PEMBAYARAN (STRUK / RESI / TAGIHAN)  \n";
echo "========================================================\n";

function http_req($url, $method = 'GET', $data = [], $cookieJar = null, $followLocation = true) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $followLocation);
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

function do_login($email, $password, $cookieFile, $dashUrl = 'http://localhost:8000/reservasi-saya.php') {
    if (file_exists($cookieFile)) @unlink($cookieFile);
    $loginPage = http_req('http://localhost:8000/login.php', 'GET', [], $cookieFile, true);
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginPage['body'], $m);
    $csrfToken = $m[1] ?? '';

    $res = http_req('http://localhost:8000/login.php', 'POST', [
        'email' => $email,
        'password' => $password,
        'csrf_token' => $csrfToken
    ], $cookieFile, true);

    $dash = http_req($dashUrl, 'GET', [], $cookieFile, true);
    return $dash['status'] === 200;
}

$cookiePelangganA = sys_get_temp_dir() . '/cookie_pelanggan_a_struk.txt';
$cookiePelangganB = sys_get_temp_dir() . '/cookie_pelanggan_b_struk.txt';
$cookiePengelola  = sys_get_temp_dir() . '/cookie_pengelola_struk.txt';
$cookieAdmin      = sys_get_temp_dir() . '/cookie_admin_struk.txt';

// Siapkan 2 akun pelanggan untuk uji Anti-IDOR
$userA = $pdo->query("SELECT id_pengguna, email FROM pengguna WHERE peran = 'pelanggan' ORDER BY id_pengguna ASC LIMIT 1")->fetch();
if (!$userA) die("Pelanggan A tidak ditemukan.\n");

// Pastikan ada pelanggan kedua
$userB = $pdo->query("SELECT id_pengguna, email FROM pengguna WHERE peran = 'pelanggan' AND id_pengguna != {$userA['id_pengguna']} ORDER BY id_pengguna ASC LIMIT 1")->fetch();
if (!$userB) {
    $pdo->prepare("INSERT INTO pengguna (nama, email, password_hash, kontak, peran, status_akun, email_verified_at) VALUES ('Pelanggan B', 'pelanggan_b@test.com', ?, '081299998888', 'pelanggan', 'aktif', NOW())")
        ->execute([password_hash('Pelanggan2026!', PASSWORD_BCRYPT)]);
    $idB = $pdo->lastInsertId();
    $userB = ['id_pengguna' => $idB, 'email' => 'pelanggan_b@test.com'];
}

// Pastikan password kedua pelanggan sinkron ke Pelanggan2026!
$pdo->prepare("UPDATE pengguna SET password_hash = ? WHERE id_pengguna IN (?, ?)")
    ->execute([password_hash('Pelanggan2026!', PASSWORD_BCRYPT), $userA['id_pengguna'], $userB['id_pengguna']]);

// Reset dan buat reservasi uji
$pdo->prepare("DELETE FROM pembayaran WHERE order_id LIKE 'TEST-STRUK-%'")->execute();
$pdo->prepare("DELETE FROM reservasi WHERE catatan LIKE 'TEST-STRUK-%'")->execute();

$idTipe = (int)$pdo->query("SELECT id_tipe FROM tipe_villa LIMIT 1")->fetchColumn();

// R1: Milik Pelanggan A (Lunas)
$pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, status, catatan)
    VALUES (?, ?, '2026-06-01', '2026-06-03', 2, 1000000, 1000000, 0, 2000000, 'dikonfirmasi', 'TEST-STRUK-A')
")->execute([$userA['id_pengguna'], $idTipe]);
$idResvA = (int)$pdo->lastInsertId();

$pdo->prepare("
    INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, metode_kelompok, metode_detail, transaction_id, dibayar_pada)
    VALUES (?, 'TEST-STRUK-ORDER-A1', 'manual', 2000000, 'lunas', 'tunai', 'Tunai di Kasir', 'TUNAI-001-A', NOW())
")->execute([$idResvA]);

// R2: Milik Pelanggan B (Menunggu)
$pdo->prepare("
    INSERT INTO reservasi (id_pelanggan, id_tipe, tanggal_checkin, tanggal_checkout, jumlah_tamu, harga_weekday_saat_pesan, harga_weekend_saat_pesan, harga_extrabed_saat_pesan, estimasi_total, status, catatan)
    VALUES (?, ?, '2026-06-10', '2026-06-12', 3, 1500000, 1500000, 0, 3000000, 'dikonfirmasi', 'TEST-STRUK-B')
")->execute([$userB['id_pengguna'], $idTipe]);
$idResvB = (int)$pdo->lastInsertId();

$pdo->prepare("
    INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status, metode_kelompok, snap_token, dibuat_pada)
    VALUES (?, 'TEST-STRUK-ORDER-B1', 'midtrans', 3000000, 'menunggu', 'transfer_bank', 'DUMMY_SNAP_TOKEN_XYZ_SECRET', NOW())
")->execute([$idResvB]);

echo "Data uji berhasil disiapkan:\n";
echo " - Reservasi A (ID: {$idResvA}, Pelanggan A: {$userA['email']}, Status: Lunas)\n";
echo " - Reservasi B (ID: {$idResvB}, Pelanggan B: {$userB['email']}, Status: Menunggu)\n\n";

// Login masing-masing sesi
$okA = do_login($userA['email'], 'Pelanggan2026!', $cookiePelangganA, 'http://localhost:8000/reservasi-saya.php');
$okB = do_login($userB['email'], 'Pelanggan2026!', $cookiePelangganB, 'http://localhost:8000/reservasi-saya.php');
$okP = do_login('pengelola@joglomasayu.com', 'Pengelola2026!', $cookiePengelola, 'http://localhost:8000/dashboard/pengelola.php');
$okAdm = do_login('admin@joglomasayu.com', 'AdminMasayu2026!', $cookieAdmin, 'http://localhost:8000/dashboard/admin.php');

echo "Status Login Uji:\n";
echo " - Pelanggan A: " . ($okA ? "OK" : "GAGAL") . "\n";
echo " - Pelanggan B: " . ($okB ? "OK" : "GAGAL") . "\n";
echo " - Pengelola: " . ($okP ? "OK" : "GAGAL") . "\n";
echo " - Admin: " . ($okAdm ? "OK" : "GAGAL") . "\n\n";

$testsPassed = 0;
$totalTests = 0;

function assertTest($name, $condition, $info = '') {
    global $testsPassed, $totalTests;
    $totalTests++;
    if ($condition) {
        $testsPassed++;
        echo " [PASS] {$name}\n";
    } else {
        echo " [FAIL] {$name} - {$info}\n";
    }
}

// ----------------------------------------------------
// 1. PENGUJIAN API: AKSES TANPA AUTENTIKASI (401)
// ----------------------------------------------------
$resNoAuth = http_req("http://localhost:8000/api/struk.php?id={$idResvA}");
assertTest("1. Akses API tanpa login menghasilkan HTTP 401", $resNoAuth['status'] === 401, "Status: " . $resNoAuth['status']);

// ----------------------------------------------------
// 2. PENGUJIAN API: PARAMETER ID TIDAK VALID (400)
// ----------------------------------------------------
$resInvalidId = http_req("http://localhost:8000/api/struk.php?id=abc", 'GET', [], $cookiePelangganA);
assertTest("2. Akses API dengan id string tidak valid menghasilkan HTTP 400", $resInvalidId['status'] === 400, "Status: " . $resInvalidId['status']);

$resNegId = http_req("http://localhost:8000/api/struk.php?id=-5", 'GET', [], $cookiePelangganA);
assertTest("3. Akses API dengan id negatif menghasilkan HTTP 400", $resNegId['status'] === 400, "Status: " . $resNegId['status']);

// ----------------------------------------------------
// 3. PENGUJIAN API: RESERVASI TIDAK DITEMUKAN (404)
// ----------------------------------------------------
$resNotFound = http_req("http://localhost:8000/api/struk.php?id=999999", 'GET', [], $cookiePelangganA);
assertTest("4. Akses API dengan ID tidak ada menghasilkan HTTP 404", $resNotFound['status'] === 404, "Status: " . $resNotFound['status']);

// ----------------------------------------------------
// 4. PENGUJIAN KEAMANAN: ANTI-IDOR (404 GENERIC)
// ----------------------------------------------------
// Pelanggan A mencoba mengakses Reservasi B
$resIdor = http_req("http://localhost:8000/api/struk.php?id={$idResvB}", 'GET', [], $cookiePelangganA);
assertTest("5. Anti-IDOR: Pelanggan A mengakses reservasi milik Pelanggan B menghasilkan HTTP 404 generic", $resIdor['status'] === 404, "Status: " . $resIdor['status']);

// ----------------------------------------------------
// 5. PENGUJIAN API: AKSES SAH PELANGGAN (200 & SKEMA DATA)
// ----------------------------------------------------
$resOwn = http_req("http://localhost:8000/api/struk.php?id={$idResvA}", 'GET', [], $cookiePelangganA);
assertTest("6. Pelanggan A mengakses reservasi miliknya menghasilkan HTTP 200", $resOwn['status'] === 200, "Status: " . $resOwn['status']);

$dataOwn = $resOwn['json']['data'] ?? [];
assertTest("7. Data JSON memiliki kunci utama (id_reservasi, tamu, villa, jadwal, keuangan, pembayaran)", 
    isset($dataOwn['id_reservasi'], $dataOwn['tamu'], $dataOwn['villa'], $dataOwn['jadwal'], $dataOwn['keuangan'], $dataOwn['pembayaran']),
    "Data keys: " . implode(', ', array_keys($dataOwn)));

assertTest("8. Status lunas terdeteksi benar pada data struk", 
    ($dataOwn['is_lunas'] === true && $dataOwn['status_bayar'] === 'lunas'),
    "is_lunas: " . json_encode($dataOwn['is_lunas'] ?? null));

// ----------------------------------------------------
// 6. PENGUJIAN KEAMANAN DATA: TIDAK MEMBOCORKAN TOKEN RAHASIA
// ----------------------------------------------------
$resOwnB = http_req("http://localhost:8000/api/struk.php?id={$idResvB}", 'GET', [], $cookiePelangganB);
$rawBodyB = $resOwnB['body'];
assertTest("9. API TIDAK membocorkan snap_token atau rahasia internal ke browser/JSON", 
    strpos($rawBodyB, 'DUMMY_SNAP_TOKEN_XYZ_SECRET') === false,
    "snap_token ditemukan di respons!");

// ----------------------------------------------------
// 7. PENGUJIAN RBAC: AKSES STAF (PENGELOLA & ADMIN)
// ----------------------------------------------------
$resStaffOnB = http_req("http://localhost:8000/api/struk.php?id={$idResvB}", 'GET', [], $cookiePengelola);
assertTest("10. Pengelola dapat mengakses struk/tagihan reservasi tamu mana pun (HTTP 200)", 
    $resStaffOnB['status'] === 200, "Status: " . $resStaffOnB['status']);

$resAdminOnA = http_req("http://localhost:8000/api/struk.php?id={$idResvA}", 'GET', [], $cookieAdmin);
assertTest("11. Admin dapat mengakses struk/tagihan reservasi tamu mana pun (HTTP 200)", 
    $resAdminOnA['status'] === 200, "Status: " . $resAdminOnA['status']);

// ----------------------------------------------------
// 8. PENGUJIAN HALAMAN MANDIRI: struk-pembayaran.php
// ----------------------------------------------------
$resPageNoAuth = http_req("http://localhost:8000/struk-pembayaran.php?id={$idResvA}", 'GET', [], null, false);
assertTest("12. Akses struk-pembayaran.php tanpa login dialihkan (302/redirect) ke login.php", 
    $resPageNoAuth['status'] === 302, "Status: " . $resPageNoAuth['status']);

$resPageIdor = http_req("http://localhost:8000/struk-pembayaran.php?id={$idResvB}", 'GET', [], $cookiePelangganA);
assertTest("13. struk-pembayaran.php anti-IDOR menghasilkan HTTP 404 saat dibuka tamu lain", 
    $resPageIdor['status'] === 404, "Status: " . $resPageIdor['status']);

$resPageOwn = http_req("http://localhost:8000/struk-pembayaran.php?id={$idResvA}", 'GET', [], $cookiePelangganA);
assertTest("14. struk-pembayaran.php milik sendiri menghasilkan HTTP 200 dengan konten resmi", 
    $resPageOwn['status'] === 200 && strpos($resPageOwn['body'], 'Villa Joglo Masayu') !== false && strpos($resPageOwn['body'], '@media print') !== false,
    "Status: " . $resPageOwn['status']);

// ----------------------------------------------------
// 9. PENGUJIAN UNIT HELPER: evaluasi_status_bayar() & ambil_pembayaran_batch()
// ----------------------------------------------------
$evalEmpty = evaluasi_status_bayar([]);
assertTest("15. evaluasi_status_bayar([]) menghasilkan status 'belum_dibayar'", $evalEmpty['status'] === 'belum_dibayar');

$evalMix = evaluasi_status_bayar([
    ['status' => 'gagal', 'id_pembayaran' => 10],
    ['status' => 'lunas', 'id_pembayaran' => 9],
    ['status' => 'menunggu', 'id_pembayaran' => 8]
]);
assertTest("16. evaluasi_status_bayar() memprioritaskan 'lunas' meski ada percobaan gagal terbaru", $evalMix['status'] === 'lunas');

$batchResult = ambil_pembayaran_batch($pdo, [$idResvA, $idResvB]);
assertTest("17. ambil_pembayaran_batch() mengembalikan data pembayaran terkelompok tanpa N+1", 
    isset($batchResult[$idResvA], $batchResult[$idResvB]) && $batchResult[$idResvA]['status'] === 'lunas' && $batchResult[$idResvB]['status'] === 'menunggu');

// Bersihkan data uji
$pdo->prepare("DELETE FROM pembayaran WHERE order_id LIKE 'TEST-STRUK-%'")->execute();
$pdo->prepare("DELETE FROM reservasi WHERE catatan LIKE 'TEST-STRUK-%'")->execute();

echo "\n========================================================\n";
echo "HASIL PENGUJIAN: {$testsPassed} / {$totalTests} tes lolos!\n";
if ($testsPassed === $totalTests) {
    echo "SEMUA PENGUJIAN STRUK & ANTI-IDOR BERHASIL DENGAN SEMPURNA!\n";
    exit(0);
} else {
    echo "BEBERAPA PENGUJIAN GAGAL!\n";
    exit(1);
}
