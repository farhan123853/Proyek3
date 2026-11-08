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
  `kontak` VARCHAR(30) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `peran` ENUM('pelanggan', 'pengelola', 'pemilik', 'admin') NOT NULL DEFAULT 'pelanggan',
  `status_akun` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
  `dibuat_pada` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
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

-- 3. Tabel Reservasi
CREATE TABLE IF NOT EXISTS `reservasi` (
  `id_reservasi` INT AUTO_INCREMENT PRIMARY KEY,
  `id_pelanggan` INT NOT NULL,
  `id_tipe` INT NOT NULL,
  `diproses_oleh` INT NULL,
  `tanggal_checkin` DATE NOT NULL,
  `tanggal_checkout` DATE NOT NULL,
  `jumlah_tamu` INT NOT NULL,
  `catatan` TEXT NULL,
  `status` ENUM('menunggu', 'dikonfirmasi', 'ditolak') NOT NULL DEFAULT 'menunggu',
  `harga_weekday_saat_pesan` DECIMAL(12,2) NOT NULL,
  `harga_weekend_saat_pesan` DECIMAL(12,2) NOT NULL,
  `harga_extrabed_saat_pesan` DECIMAL(12,2) NOT NULL,
  `estimasi_total` DECIMAL(12,2) NOT NULL,
  `dibuat_pada` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ketersediaan` (`id_tipe`, `tanggal_checkin`, `tanggal_checkout`, `status`),
  CONSTRAINT `fk_reservasi_pelanggan` FOREIGN KEY (`id_pelanggan`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_reservasi_tipe` FOREIGN KEY (`id_tipe`) REFERENCES `tipe_villa` (`id_tipe`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_reservasi_pengelola` FOREIGN KEY (`diproses_oleh`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

