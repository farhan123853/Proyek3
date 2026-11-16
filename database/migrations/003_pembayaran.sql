-- ============================================================
-- Migrasi 003: Tabel Pembayaran
-- Menambahkan tabel pembayaran untuk mencatat transaksi Midtrans
-- dan pencatatan kas tunai oleh Pengelola.
-- Engine: InnoDB, Charset: utf8mb4_unicode_ci
-- ============================================================

CREATE TABLE IF NOT EXISTS `pembayaran` (
  `id_pembayaran` INT AUTO_INCREMENT PRIMARY KEY,
  `id_reservasi` INT NOT NULL,
  `order_id` VARCHAR(50) NOT NULL UNIQUE,
  `sumber` ENUM('midtrans', 'manual') NOT NULL DEFAULT 'midtrans',
  `jumlah` INT NOT NULL,
  `status` ENUM('menunggu', 'lunas', 'gagal', 'kedaluwarsa', 'ganda', 'dibatalkan') NOT NULL DEFAULT 'menunggu',
  `metode_kelompok` ENUM('transfer_bank', 'e_wallet', 'tunai', 'lainnya') NULL,
  `metode_detail` VARCHAR(30) NULL,
  `transaction_id` VARCHAR(64) NULL,
  `snap_token` VARCHAR(100) NULL,
  `catatan` VARCHAR(200) NULL,
  `dicatat_oleh` INT NULL,
  `dibatalkan_oleh` INT NULL,
  `alasan_batal` VARCHAR(200) NULL,
  `dibuat_pada` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dibayar_pada` DATETIME NULL,
  `lunas_key` INT GENERATED ALWAYS AS (IF(`status` = 'lunas', `id_reservasi`, NULL)) STORED,
  UNIQUE INDEX `uq_pembayaran_reservasi_lunas` (`lunas_key`),
  INDEX `idx_pembayaran_reservasi_status` (`id_reservasi`, `status`),
  INDEX `idx_pembayaran_status_dibayar` (`status`, `dibayar_pada`),
  CONSTRAINT `fk_pembayaran_reservasi` FOREIGN KEY (`id_reservasi`) REFERENCES `reservasi` (`id_reservasi`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_pembayaran_dicatat` FOREIGN KEY (`dicatat_oleh`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pembayaran_dibatalkan` FOREIGN KEY (`dibatalkan_oleh`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
