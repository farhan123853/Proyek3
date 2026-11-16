<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pdo = get_db();

// 1. Ambil salah satu reservasi yang ada
$res = $pdo->query("SELECT id_reservasi, estimasi_total FROM reservasi LIMIT 1")->fetch();
if (!$res) {
    die("ERROR: Tidak ada data reservasi untuk diuji!\n");
}
$idRes = (int)$res['id_reservasi'];
$jumlah = (int)$res['estimasi_total'];

// Bersihkan data uji jika ada
$pdo->prepare("DELETE FROM pembayaran WHERE order_id LIKE 'TEST-DB-%'")->execute();

echo "Mulai pengujian aturan satu-lunas pada reservasi ID: $idRes...\n";

// 2. Insert percobaan 1 (menunggu)
$stmt = $pdo->prepare("INSERT INTO pembayaran (id_reservasi, order_id, sumber, jumlah, status) VALUES (?, ?, 'midtrans', ?, 'menunggu')");
$stmt->execute([$idRes, 'TEST-DB-001', $jumlah]);
echo "Percobaan 1 (menunggu): BERHASIL disisipkan.\n";

// 3. Insert percobaan 2 (menunggu) - harus boleh banyak percobaan order_id beda
$stmt->execute([$idRes, 'TEST-DB-002', $jumlah]);
echo "Percobaan 2 (menunggu): BERHASIL disisipkan.\n";

// 4. Ubah percobaan 1 menjadi 'lunas'
$pdo->prepare("UPDATE pembayaran SET status = 'lunas', dibayar_pada = NOW() WHERE order_id = 'TEST-DB-001'")->execute();
echo "Percobaan 1 diubah menjadi 'lunas': BERHASIL.\n";

// 5. Coba jadikan percobaan 2 juga 'lunas' - HARUS GAGAL karena aturan satu-lunas (uq_pembayaran_reservasi_lunas)
$failedAsExpected = false;
try {
    $pdo->prepare("UPDATE pembayaran SET status = 'lunas', dibayar_pada = NOW() WHERE order_id = 'TEST-DB-002'")->execute();
    echo "GAGAL: Seharusnya tidak boleh ada dua baris 'lunas' untuk reservasi yang sama!\n";
} catch (PDOException $e) {
    echo "LULUS: Percobaan 'lunas' kedua ditolak basis data (Error: " . $e->getCode() . ")!\n";
    $failedAsExpected = true;
}

// Bersihkan data uji
$pdo->prepare("DELETE FROM pembayaran WHERE order_id LIKE 'TEST-DB-%'")->execute();

if ($failedAsExpected) {
    echo "=== GERBANG TAHAP 3 DATABASE: LOLOS SEMPURNA ===\n";
    exit(0);
} else {
    echo "=== GERBANG TAHAP 3 DATABASE: GAGAL ===\n";
    exit(1);
}
