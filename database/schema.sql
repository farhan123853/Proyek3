-- ============================================================
-- SKEMA BASIS DATA: Villa Joglo Masayu
-- Sesuai proposal Proyek 3 D4 Teknik Informatika ULBI
-- Engine: InnoDB, Charset: utf8mb4_unicode_ci
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Tabel Pengguna (Pelanggan, Pengelola, Pemilik, Admin)
CREATE TABLE IF NOT EXISTS `pengguna` (
  `id_pengguna` INT AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `google_id` VARCHAR(255)
    CHARACTER SET ascii
    COLLATE ascii_bin
    NULL DEFAULT NULL,
  `kontak` VARCHAR(30) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `peran` ENUM('pelanggan', 'pengelola', 'pemilik', 'admin') NOT NULL DEFAULT 'pelanggan',
  `status_akun` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
  `bahasa` VARCHAR(5) NOT NULL DEFAULT 'id',
  `email_verified_at` DATETIME NULL DEFAULT NULL,
  `dibuat_pada` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_pengguna_google_id` (`google_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Token verifikasi email pelanggan
CREATE TABLE IF NOT EXISTS `email_verification_tokens` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `id_pengguna` INT NOT NULL,
  `token_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_email_token_hash` (`token_hash`),
  INDEX `idx_email_token_user_created` (`id_pengguna`, `created_at`),
  CONSTRAINT `fk_email_token_pengguna`
    FOREIGN KEY (`id_pengguna`) REFERENCES `pengguna` (`id_pengguna`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabel Tipe Villa (Data diselaraskan dengan script.js)
CREATE TABLE IF NOT EXISTS `tipe_villa` (
  `id_tipe` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `nama` VARCHAR(100) NOT NULL,
  `kapasitas_standar` INT NOT NULL,
  `kapasitas_maks` INT NOT NULL,
  `harga_weekday` DECIMAL(12,2) NOT NULL,
  `harga_weekend` DECIMAL(12,2) NOT NULL,
  `harga_extrabed` DECIMAL(12,2) NOT NULL,
  `jumlah_unit` INT NOT NULL DEFAULT 1, -- TODO: konfirmasi jumlah unit riil pemilik
  `deskripsi` TEXT NULL,
  `fasilitas` TEXT NULL -- Menyimpan JSON array fasilitas
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabel Reservasi (Online & Offline)
CREATE TABLE IF NOT EXISTS `reservasi` (
  `id_reservasi` INT AUTO_INCREMENT PRIMARY KEY,
  `id_pelanggan` INT NULL,
  `nama_tamu` VARCHAR(100) NULL,
  `kontak_tamu` VARCHAR(20) NULL,
  `sumber` ENUM('online', 'walk_in', 'whatsapp', 'telepon') NOT NULL DEFAULT 'online',
  `id_tipe` INT NOT NULL,
  `diproses_oleh` INT NULL,
  `dibuat_oleh` INT NULL,
  `tanggal_checkin` DATE NOT NULL,
  `tanggal_checkout` DATE NOT NULL,
  `jumlah_tamu` INT NOT NULL,
  `catatan` TEXT NULL,
  `status` ENUM('menunggu', 'dikonfirmasi', 'ditolak', 'dibatalkan') NOT NULL DEFAULT 'menunggu',
  `harga_weekday_saat_pesan` DECIMAL(12,2) NOT NULL,
  `harga_weekend_saat_pesan` DECIMAL(12,2) NOT NULL,
  `harga_extrabed_saat_pesan` DECIMAL(12,2) NOT NULL,
  `estimasi_total` DECIMAL(12,2) NOT NULL,
  `dibuat_pada` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ketersediaan` (`id_tipe`, `tanggal_checkin`, `tanggal_checkout`, `status`),
  CONSTRAINT `fk_reservasi_pelanggan` FOREIGN KEY (`id_pelanggan`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_reservasi_tipe` FOREIGN KEY (`id_tipe`) REFERENCES `tipe_villa` (`id_tipe`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_reservasi_pengelola` FOREIGN KEY (`diproses_oleh`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_reservasi_dibuat_oleh` FOREIGN KEY (`dibuat_oleh`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabel Blokir Tanggal (Pemeliharaan, Pemilik, dll.)
CREATE TABLE IF NOT EXISTS `blokir_tanggal` (
  `id_blokir` INT AUTO_INCREMENT PRIMARY KEY,
  `id_tipe` INT NOT NULL,
  `tanggal_mulai` DATE NOT NULL,
  `tanggal_selesai` DATE NOT NULL COMMENT 'eksklusif, seperti tanggal checkout',
  `alasan` VARCHAR(200) NOT NULL,
  `dibuat_oleh` INT NULL,
  `dibuat_pada` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dibatalkan_pada` DATETIME NULL,
  CONSTRAINT `fk_blokir_tipe` FOREIGN KEY (`id_tipe`) REFERENCES `tipe_villa` (`id_tipe`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_blokir_dibuat` FOREIGN KEY (`dibuat_oleh`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX `idx_blokir_tipe_tanggal` (`id_tipe`, `tanggal_mulai`, `tanggal_selesai`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabel Pembayaran (Transaksi Midtrans & Kas Tunai Manual)
CREATE TABLE IF NOT EXISTS `pembayaran` (
  `id_pembayaran` INT AUTO_INCREMENT PRIMARY KEY,
  `id_reservasi` INT NOT NULL,
  `order_id` VARCHAR(50) NOT NULL UNIQUE,
  `sumber` ENUM('midtrans', 'manual') NOT NULL DEFAULT 'midtrans',
  `jumlah` INT NOT NULL,
  `status` ENUM('menunggu', 'lunas', 'gagal', 'kedaluwarsa', 'ganda', 'dibatalkan') NOT NULL DEFAULT 'menunggu',
  `metode_kelompok` ENUM('transfer_bank', 'e_wallet', 'tunai', 'lainnya') NULL,
  `metode_detail` VARCHAR(30) NULL,
  `bank_atau_toko` VARCHAR(30) NULL,
  `kode_pembayaran` VARCHAR(64) NULL,
  `kedaluwarsa_pada` DATETIME NULL,
  `kode_dibuat_pada` DATETIME NULL,
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
  INDEX `idx_pembayaran_kode_va` (`bank_atau_toko`, `kode_pembayaran`),
  CONSTRAINT `fk_pembayaran_reservasi` FOREIGN KEY (`id_reservasi`) REFERENCES `reservasi` (`id_reservasi`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_pembayaran_dicatat` FOREIGN KEY (`dicatat_oleh`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pembayaran_dibatalkan` FOREIGN KEY (`dibatalkan_oleh`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

