<?php
/**
 * Google Authentication Service
 * Villa Joglo Masayu
 *
 * Provider : Google Identity Services
 * Token    : Google ID Token (JWT)
 * Verify   : Google API Client Library for PHP
 */

 /**
  * Membuat nonce acak untuk satu proses Google Sign-In.
  * Berlaku maksimal 10 menit dan hanya boleh digunakan sekali.
  */
function google_auth_create_nonce(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $nonce = bin2hex(random_bytes(32));

    $_SESSION['google_signin_nonce'] = [
        'value'      => $nonce,
        'created_at' => time(),
    ];

    return $nonce;
}

/**
 * Memvalidasi Google ID Token.
 *
 * Pemeriksaan:
 * - Signature JWT
 * - Audience (OAuth Client ID)
 * - Issuer
 * - Expiration
 * - Nonce
 * - Format sub dan email
 */
function google_auth_verify_id_token(string $idToken): array
{
    global $config;

    $idToken = trim($idToken);

    if ($idToken === '' || strlen($idToken) > 20000) {
        throw new InvalidArgumentException('Google ID Token tidak valid.');
    }

    $clientId = trim($config['GOOGLE_CLIENT_ID'] ?? '');

    if ($clientId === '') {
        throw new RuntimeException('Google Client ID belum dikonfigurasi.');
    }

    // Muat Composer autoloader.
    $autoload = ROOT_PATH . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

    if (!class_exists(\Google\Client::class)) {
        if (!is_file($autoload)) {
            throw new RuntimeException('Composer dependencies belum tersedia.');
        }

        require_once $autoload;
    }

    if (!class_exists(\Google\Client::class)) {
        throw new RuntimeException('Google API Client Library tidak ditemukan.');
    }

    // Validasi token menggunakan OAuth Client ID aplikasi.
    $client = new \Google\Client([
        'client_id' => $clientId,
    ]);

    $payload = $client->verifyIdToken($idToken);

    if (!is_array($payload)) {
        throw new InvalidArgumentException('Google ID Token gagal diverifikasi.');
    }

    // Pemeriksaan issuer.
    $issuer = $payload['iss'] ?? '';

    if (!in_array(
        $issuer,
        ['accounts.google.com', 'https://accounts.google.com'],
        true
    )) {
        throw new InvalidArgumentException('Issuer Google tidak valid.');
    }

    // Pemeriksaan audience tambahan.
    $audience = $payload['aud'] ?? null;

    if ($audience !== $clientId) {
        throw new InvalidArgumentException('OAuth Client ID tidak cocok.');
    }

    // Periksa nonce dan langsung konsumsi agar tidak dapat digunakan ulang.
    $nonceState = $_SESSION['google_signin_nonce'] ?? null;
    unset($_SESSION['google_signin_nonce']);

    if (
        !is_array($nonceState) ||
        empty($nonceState['value']) ||
        empty($nonceState['created_at']) ||
        !is_string($payload['nonce'] ?? null)
    ) {
        throw new InvalidArgumentException('Nonce Google tidak valid.');
    }

    $nonceAge = time() - (int) $nonceState['created_at'];

    if (
        $nonceAge < 0 ||
        $nonceAge > 600 ||
        !hash_equals($nonceState['value'], $payload['nonce'])
    ) {
        throw new InvalidArgumentException('Nonce Google kedaluwarsa atau tidak cocok.');
    }

    // Identitas unik akun Google.
    $googleId = trim((string) ($payload['sub'] ?? ''));

    if (
        $googleId === '' ||
        strlen($googleId) > 255 ||
        !preg_match('/^[\x20-\x7E]+$/', $googleId)
    ) {
        throw new InvalidArgumentException('Identitas akun Google tidak valid.');
    }

    // Alamat email.
    $email = strtolower(trim((string) ($payload['email'] ?? '')));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Alamat email Google tidak valid.');
    }

    $emailVerified = filter_var(
        $payload['email_verified'] ?? false,
        FILTER_VALIDATE_BOOLEAN
    );

    $name = trim((string) ($payload['name'] ?? ''));

    if ($name === '') {
        $name = strstr($email, '@', true) ?: 'Pelanggan Google';
    }

    $name = mb_substr($name, 0, 100);

    return [
        'google_id'     => $googleId,
        'email'         => $email,
        'nama'          => $name,
        'email_verified'=> $emailVerified,
        'hd'           => $payload['hd'] ?? null,
        'picture'       => $payload['picture'] ?? null,
    ];
}
