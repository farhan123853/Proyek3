<?php
/**
 * Koneksi Basis Data PDO — Villa Joglo Masayu
 */

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    global $config;
    if (empty($config)) {
        $configFile = __DIR__ . '/../config/config.php';
        if (!file_exists($configFile)) {
            $configFile = __DIR__ . '/../config/config.example.php';
        }
        $config = require $configFile;
    }

    $host = $config['DB_HOST'] ?? 'localhost';
    $port = $config['DB_PORT'] ?? 3306;
    $dbName = $config['DB_NAME'] ?? 'villa_joglo_masayu';
    $user = $config['DB_USER'] ?? 'root';
    $pass = $config['DB_PASS'] ?? '';
    $charset = $config['DB_CHARSET'] ?? 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        if (!empty($config['APP_DEBUG'])) {
            die("Koneksi basis data gagal: " . $e->getMessage());
        }
        die("Maaf, layanan sedang mengalami kendala koneksi basis data. Silakan hubungi pengelola.");
    }

    return $pdo;
}
