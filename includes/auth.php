<?php
/**
 * Autentikasi dan Otorisasi Peran — Villa Joglo Masayu
 * Mendukung 4 peran: pelanggan, pengelola, pemilik, admin
 */

function is_logged_in(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return !empty($_SESSION['user_id']);
}

function current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function current_user_name(): string
{
    return $_SESSION['user_name'] ?? 'Tamu';
}

function current_user_email(): string
{
    return $_SESSION['user_email'] ?? '';
}

function current_role(): ?string
{
    return $_SESSION['user_role'] ?? null;
}

function login_user(array $pengguna): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Mencegah session fixation
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$pengguna['id_pengguna'];
    $_SESSION['user_name'] = $pengguna['nama'];
    $_SESSION['user_email'] = $pengguna['email'];
    $_SESSION['user_kontak'] = $pengguna['kontak'] ?? '';
    $_SESSION['user_role'] = $pengguna['peran'];
}

function logout_user(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

/**
 * Dapatkan URL tujuan redirect sesuai peran pengguna
 */
function get_role_redirect_url(?string $role): string
{
    switch ($role) {
        case 'pengelola':
            return 'dashboard/pengelola.php';
        case 'pemilik':
            return 'dashboard/pemilik.php';
        case 'admin':
            return 'dashboard/admin.php';
        case 'pelanggan':
        default:
            return 'reservasi-saya.php';
    }
}

/**
 * Wajib login: jika belum login, redirect ke login.php
 */
function require_login(string $redirectTo = 'login.php'): void
{
    if (!is_logged_in()) {
        flash_set('warning', 'Silakan masuk ke akun Anda terlebih dahulu.');
        redirect($redirectTo);
    }
}

/**
 * Wajib peran tertentu: $roles bisa berupa string tunggal atau array
 */
function require_role($roles, string $unauthorizedRedirect = 'index.php'): void
{
    require_login();
    $rolesArray = (array)$roles;
    $userRole = current_role();

    if (!in_array($userRole, $rolesArray, true)) {
        flash_set('danger', 'Anda tidak memiliki hak akses ke halaman tersebut.');
        redirect($unauthorizedRedirect);
    }
}
