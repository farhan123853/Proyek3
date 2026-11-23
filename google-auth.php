<?php
/**
 * Google Sign-In Endpoint
 * Villa Joglo Masayu
 *
 * Method     : POST
 * Input      : credential (Google ID Token)
 * Validation : Google Identity Services
 * Session    : login_user()
 */

require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Mengirim respons JSON.
 */
function google_auth_response(array $data, int $status = 200): void
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

// Hanya menerima POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    google_auth_response([
        'success' => false,
        'message' => 'Metode permintaan tidak diizinkan.'
    ], 405);
}

// Pengguna yang sudah login tidak perlu login kembali.
if (is_logged_in()) {
    google_auth_response([
        'success' => false,
        'message' => 'Anda sudah masuk ke akun.'
    ], 409);
}

// Periksa CSRF token aplikasi.
$submittedCsrfToken = $_POST['csrf_token'] ?? '';
$sessionCsrfToken = $_SESSION['csrf_token'] ?? '';

if (
    !is_string($submittedCsrfToken) ||
    !is_string($sessionCsrfToken) ||
    $submittedCsrfToken === '' ||
    $sessionCsrfToken === '' ||
    !hash_equals($sessionCsrfToken, $submittedCsrfToken)
) {
    google_auth_response([
        'success' => false,
        'message' => 'Permintaan tidak sah. Silakan muat ulang halaman.'
    ], 403);
}

// Ambil Google ID Token.
$idToken = $_POST['credential'] ?? '';

if (!is_string($idToken) || trim($idToken) === '') {
    google_auth_response([
        'success' => false,
        'message' => 'Google credential tidak ditemukan.'
    ], 400);
}

// Periksa apakah Google Client ID sudah dikonfigurasi
$googleClientId = trim($config['GOOGLE_CLIENT_ID'] ?? '');
if ($googleClientId === '') {
    google_auth_response([
        'success' => false,
        'message' => 'Layanan Google Sign-In belum dikonfigurasi pada sistem.'
    ], 503);
}

try {
    // 1. Verifikasi identitas Google.
    $identity = google_auth_verify_id_token($idToken);

    $pdo = get_db();

    // 2. Cari akun berdasarkan Google ID.
    $stmtGoogle = $pdo->prepare(
        "SELECT
            id_pengguna,
            nama,
            email,
            kontak,
            peran,
            status_akun
         FROM pengguna
         WHERE google_id = ?
         LIMIT 1"
    );

    $stmtGoogle->execute([$identity['google_id']]);

    $user = $stmtGoogle->fetch(PDO::FETCH_ASSOC);

    // 3. Jika Google ID sudah terhubung, lakukan login.
    if ($user) {

        if ($user['peran'] !== 'pelanggan') {
            google_auth_response([
                'success' => false,
                'message' => 'Akun ini tidak diizinkan menggunakan Google Sign-In.'
            ], 403);
        }

        if ($user['status_akun'] !== 'aktif') {
            google_auth_response([
                'success' => false,
                'message' => 'Akun Anda sedang tidak aktif.'
            ], 403);
        }

        // Gunakan sistem session yang sudah ada.
        login_user($user);

        flash_set(
            'success',
            t('auth.login_success', ['nama' => $user['nama']])
        );

        google_auth_response([
            'success' => true,
            'redirect' => get_role_redirect_url($user['peran'])
        ]);
    }

    // 4. Periksa apakah email sudah dimiliki akun lain.
    $stmtEmail = $pdo->prepare(
        "SELECT id_pengguna
         FROM pengguna
         WHERE email = ?
         LIMIT 1"
    );

    $stmtEmail->execute([$identity['email']]);

    $existingEmail = $stmtEmail->fetch(PDO::FETCH_ASSOC);

    if ($existingEmail) {
        google_auth_response([
            'success' => false,
            'message' => 'Email ini sudah memiliki akun. Silakan masuk menggunakan metode login yang sebelumnya digunakan.'
        ], 409);
    }

    // 5. Periksa apakah Google merupakan pihak yang berwenang
    //    memverifikasi alamat email tersebut.
    $isAuthoritativeEmail = (
        $identity['email_verified'] === true &&
        (
            str_ends_with($identity['email'], '@gmail.com') ||
            !empty($identity['hd'])
        )
    );

    // 6. Simpan informasi sementara sebelum melengkapi profil.
    //    Akun belum dibuat pada tahap ini.
    $_SESSION['google_signup_pending'] = [
        'google_id' => $identity['google_id'],
        'email' => $identity['email'],
        'nama' => $identity['nama'],
        'email_verified' => $isAuthoritativeEmail,
        'expires_at' => time() + 600
    ];

    google_auth_response([
        'success' => true,
        'redirect' => 'google-complete-profile.php'
    ]);

} catch (InvalidArgumentException $e) {

    error_log('Google Sign-In invalid token: ' . $e->getMessage());

    google_auth_response([
        'success' => false,
        'message' => 'Identitas Google tidak valid. Silakan coba kembali.'
    ], 401);

} catch (Throwable $e) {

    error_log('Google Sign-In error: ' . $e->getMessage());

    google_auth_response([
        'success' => false,
        'message' => 'Terjadi kesalahan saat memproses Google Sign-In.'
    ], 500);
}
