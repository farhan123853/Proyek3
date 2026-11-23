
<?php
/**
 * Email Verification Service
 * Villa Joglo Masayu
 *
 * Gmail delivery : Google Apps Script
 * Database       : MySQL / PDO
 * Token          : SHA-256 hash
 * Validity       : 30 minutes
 */

 /**
  * Memeriksa apakah akun sudah memenuhi status verifikasi.
  * Akun internal tidak diwajibkan melakukan verifikasi email.
  */
function email_verification_is_complete(int $userId): bool
{
    $pdo = get_db();

    try {
        $stmt = $pdo->prepare(
            "SELECT peran, email_verified_at
             FROM pengguna
             WHERE id_pengguna = ?
             LIMIT 1"
        );

        $stmt->execute([$userId]);
        $user = $stmt->fetch();
    } catch (\PDOException $e) {
        // Fallback defensif jika kolom belum ada di database (sebelum migrasi)
        return true;
    }

    if (!$user) {
        return false;
    }

    if ($user['peran'] !== 'pelanggan') {
        return true;
    }

    return !empty($user['email_verified_at']);
}


/**
 * Mengirim permintaan verifikasi email.
 *
 * Rate limit:
 * - 60 detik antarpermintaan.
 * - Maksimal 5 permintaan per jam.
 */
function email_verification_request(int $userId): array
{
    global $config;

    $endpoint = trim($config['EMAIL_API_URL'] ?? '');
    $secret   = trim($config['EMAIL_API_SECRET'] ?? '');
    $baseUrl  = rtrim(trim($config['APP_URL'] ?? ''), '/');

    // Validasi konfigurasi.
    if (!$endpoint || !$secret || !$baseUrl) {
        return [
            'success' => false,
            'message' => 'Konfigurasi layanan email belum lengkap.'
        ];
    }

    // Pastikan endpoint berasal dari Google Apps Script.
    $endpointParts = parse_url($endpoint);

    if (
        !is_array($endpointParts) ||
        ($endpointParts['scheme'] ?? '') !== 'https' ||
        ($endpointParts['host'] ?? '') !== 'script.google.com' ||
        !preg_match(
            '#^/macros/s/[A-Za-z0-9_-]+/exec$#',
            $endpointParts['path'] ?? ''
        )
    ) {
        return [
            'success' => false,
            'message' => 'Konfigurasi endpoint email tidak valid.'
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'success' => false,
            'message' => 'PHP cURL belum tersedia.'
        ];
    }

    $pdo = get_db();

    try {
        $pdo->beginTransaction();

        // Kunci baris pengguna untuk mencegah request bersamaan.
        $stmtUser = $pdo->prepare(
            "SELECT
                id_pengguna,
                nama,
                email,
                peran,
                status_akun,
                email_verified_at
             FROM pengguna
             WHERE id_pengguna = ?
             LIMIT 1
             FOR UPDATE"
        );

        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch();

        if (!$user || $user['peran'] !== 'pelanggan') {
            $pdo->rollBack();

            return [
                'success' => false,
                'message' => 'Akun tidak diizinkan menggunakan fitur ini.'
            ];
        }

        if ($user['status_akun'] !== 'aktif') {
            $pdo->rollBack();

            return [
                'success' => false,
                'message' => 'Akun sedang tidak aktif.'
            ];
        }

        if ($user['email_verified_at'] !== null) {
            $pdo->rollBack();

            return [
                'success' => true,
                'already_verified' => true,
                'message' => 'Alamat email sudah terverifikasi.'
            ];
        }

        // Maksimal 5 permintaan dalam 1 jam.
        $stmtCount = $pdo->prepare(
            "SELECT COUNT(*)
             FROM email_verification_tokens
             WHERE id_pengguna = ?
             AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );

        $stmtCount->execute([$userId]);
        $requestCount = (int) $stmtCount->fetchColumn();

        if ($requestCount >= 5) {
            $pdo->rollBack();

            return [
                'success' => false,
                'message' => 'Batas permintaan verifikasi tercapai. Coba kembali setelah satu jam.'
            ];
        }

        // Cooldown 60 detik.
        $stmtLast = $pdo->prepare(
            "SELECT TIMESTAMPDIFF(SECOND, created_at, NOW())
             FROM email_verification_tokens
             WHERE id_pengguna = ?
             ORDER BY id DESC
             LIMIT 1"
        );

        $stmtLast->execute([$userId]);
        $secondsSinceLast = $stmtLast->fetchColumn();

        if (
            $secondsSinceLast !== false &&
            (int) $secondsSinceLast < 60
        ) {
            $pdo->rollBack();

            return [
                'success' => false,
                'message' => 'Mohon tunggu 60 detik sebelum meminta email verifikasi kembali.'
            ];
        }

        // Token lama dibatalkan.
        $stmtRevoke = $pdo->prepare(
            "UPDATE email_verification_tokens
             SET used_at = NOW()
             WHERE id_pengguna = ?
             AND used_at IS NULL"
        );

        $stmtRevoke->execute([$userId]);

        // Buat token acak 256-bit.
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        // Simpan hash, bukan token asli.
        $stmtInsert = $pdo->prepare(
            "INSERT INTO email_verification_tokens
                (id_pengguna, token_hash, expires_at, created_at)
             VALUES
                (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE), NOW())"
        );

        $stmtInsert->execute([$userId, $tokenHash]);

        $tokenId = (int) $pdo->lastInsertId();

        $pdo->commit();

        // URL verifikasi dikirim melalui Apps Script.
        $verificationUrl =
            $baseUrl .
            '/verifikasi-email.php?token=' .
            rawurlencode($rawToken);

        $payload = [
            'action' => 'send_verification',
            'secret' => $secret,
            'to' => $user['email'],
            'name' => $user['nama'],
            'verification_url' => $verificationUrl
        ];

        $jsonPayload = json_encode(
            $payload,
            JSON_THROW_ON_ERROR
        );

        $curl = curl_init($endpoint);

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 45
        ]);

        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpCode = (int) curl_getinfo(
            $curl,
            CURLINFO_HTTP_CODE
        );

        curl_close($curl);

        $result = is_string($response)
            ? json_decode($response, true)
            : null;

        $sent = (
            $response !== false &&
            $httpCode >= 200 &&
            $httpCode < 300 &&
            is_array($result) &&
            ($result['success'] ?? false) === true
        );

        if (!$sent) {
            // Token dinonaktifkan apabila pengiriman gagal.
            $stmtRevoke = $pdo->prepare(
                "UPDATE email_verification_tokens
                 SET used_at = NOW()
                 WHERE id = ?
                 AND used_at IS NULL"
            );

            $stmtRevoke->execute([$tokenId]);

            error_log(
                'Villa Joglo Masayu email delivery failed. ' .
                'User ID: ' . $userId .
                '. HTTP: ' . $httpCode .
                '. cURL: ' . $curlError
            );

            return [
                'success' => false,
                'message' => 'Email verifikasi belum berhasil dikirim. Silakan coba kembali.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Email verifikasi berhasil dikirim. Silakan periksa kotak masuk email kamu.'
        ];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Email verification request error for user ID ' .
            $userId . ': ' . $e->getMessage()
        );

        return [
            'success' => false,
            'message' => 'Terjadi kesalahan saat memproses permintaan verifikasi.'
        ];
    }
}


/**
 * Mengambil status token tanpa menggunakannya.
 * Digunakan ketika pengguna membuka tautan verifikasi.
 */
function email_verification_lookup(string $rawToken): array
{
    if (!preg_match('/^[a-f0-9]{64}$/i', $rawToken)) {
        return ['status' => 'invalid'];
    }

    $tokenHash = hash('sha256', $rawToken);
    $pdo = get_db();

    $stmt = $pdo->prepare(
        "SELECT
            t.id,
            t.id_pengguna,
            t.expires_at,
            t.used_at,
            (t.expires_at <= NOW()) AS is_expired,
            u.nama,
            u.peran,
            u.status_akun,
            u.email_verified_at
         FROM email_verification_tokens t
         INNER JOIN pengguna u
            ON u.id_pengguna = t.id_pengguna
         WHERE t.token_hash = ?
         LIMIT 1"
    );

    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['status' => 'invalid'];
    }

    if ($row['email_verified_at'] !== null) {
        return ['status' => 'already_verified'];
    }

    if ($row['used_at'] !== null) {
        return ['status' => 'used'];
    }

    if ((int) $row['is_expired'] === 1) {
        return ['status' => 'expired'];
    }

    if (
        $row['peran'] !== 'pelanggan' ||
        $row['status_akun'] !== 'aktif'
    ) {
        return ['status' => 'invalid'];
    }

    return [
        'status' => 'valid',
        'user_name' => $row['nama'],
        'user_id' => (int) $row['id_pengguna']
    ];
}


/**
 * Mengonsumsi token dan memperbarui status email.
 * Fungsi ini hanya dipanggil melalui POST konfirmasi.
 */
function email_verification_confirm(string $rawToken): array
{
    if (!preg_match('/^[a-f0-9]{64}$/i', $rawToken)) {
        return ['success' => false, 'status' => 'invalid'];
    }

    $tokenHash = hash('sha256', $rawToken);
    $pdo = get_db();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "SELECT
                t.id,
                t.id_pengguna,
                t.used_at,
                (t.expires_at <= NOW()) AS is_expired,
                u.peran,
                u.status_akun,
                u.email_verified_at
             FROM email_verification_tokens t
             INNER JOIN pengguna u
                ON u.id_pengguna = t.id_pengguna
             WHERE t.token_hash = ?
             LIMIT 1
             FOR UPDATE"
        );

        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch();

        if (!$row) {
            $pdo->rollBack();
            return ['success' => false, 'status' => 'invalid'];
        }

        if ($row['email_verified_at'] !== null) {
            $pdo->rollBack();
            return ['success' => true, 'status' => 'already_verified'];
        }

        if ($row['used_at'] !== null) {
            $pdo->rollBack();
            return ['success' => false, 'status' => 'used'];
        }

        if ((int) $row['is_expired'] === 1) {
            $pdo->rollBack();
            return ['success' => false, 'status' => 'expired'];
        }

        if (
            $row['peran'] !== 'pelanggan' ||
            $row['status_akun'] !== 'aktif'
        ) {
            $pdo->rollBack();
            return ['success' => false, 'status' => 'invalid'];
        }

        // Verifikasi email pelanggan.
        $stmtUser = $pdo->prepare(
            "UPDATE pengguna
             SET email_verified_at = NOW()
             WHERE id_pengguna = ?
             AND email_verified_at IS NULL"
        );

        $stmtUser->execute([(int) $row['id_pengguna']]);

        // Tandai token sudah digunakan.
        $stmtToken = $pdo->prepare(
            "UPDATE email_verification_tokens
             SET used_at = NOW()
             WHERE id = ?
             AND used_at IS NULL"
        );

        $stmtToken->execute([(int) $row['id']]);

        // Batalkan token lain milik pengguna.
        $stmtOthers = $pdo->prepare(
            "UPDATE email_verification_tokens
             SET used_at = NOW()
             WHERE id_pengguna = ?
             AND id <> ?
             AND used_at IS NULL"
        );

        $stmtOthers->execute([
            (int) $row['id_pengguna'],
            (int) $row['id']
        ]);

        $pdo->commit();

        return ['success' => true, 'status' => 'verified'];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Email verification confirmation failed: ' .
            $e->getMessage()
        );

        return ['success' => false, 'status' => 'error'];
    }
}
