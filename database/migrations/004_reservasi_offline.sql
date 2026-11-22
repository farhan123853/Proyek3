-- Migration 004: Reservasi Offline & Blokir Tanggal — Villa Joglo Masayu
-- Tahap 11: Kalender Ketersediaan dan Reservasi Offline
-- Jalankan: php database/migrations/run_004.php

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Lepas FK id_pelanggan agar bisa diubah nullable
SET @fk_reservasi_pelanggan_exists = (
  SELECT COUNT(*)
  FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reservasi'
    AND CONSTRAINT_NAME = 'fk_reservasi_pelanggan'
);

SET @drop_fk_reservasi_pelanggan = IF(
  @fk_reservasi_pelanggan_exists > 0,
  'ALTER TABLE `reservasi` DROP FOREIGN KEY `fk_reservasi_pelanggan`',
  'SELECT ''FK fk_reservasi_pelanggan tidak ditemukan; dilewati'''
);

PREPARE stmt_drop_fk_reservasi_pelanggan FROM @drop_fk_reservasi_pelanggan;
EXECUTE stmt_drop_fk_reservasi_pelanggan;
DEALLOCATE PREPARE stmt_drop_fk_reservasi_pelanggan;

-- 2. Modifikasi kolom id_pelanggan nullable + tambah kolom baru untuk reservasi offline
ALTER TABLE `reservasi`
  MODIFY `id_pelanggan` INT NULL,
  ADD COLUMN `nama_tamu`   VARCHAR(100) NULL AFTER `id_pelanggan`,
  ADD COLUMN `kontak_tamu` VARCHAR(20)  NULL AFTER `nama_tamu`,
  ADD COLUMN `sumber`      ENUM('online','walk_in','whatsapp','telepon') NOT NULL DEFAULT 'online' AFTER `kontak_tamu`,
  ADD COLUMN `dibuat_oleh` INT          NULL AFTER `sumber`;

-- 3. Pasang kembali FK id_pelanggan (nullable) + FK dibuat_oleh
ALTER TABLE `reservasi`
  ADD CONSTRAINT `fk_reservasi_pelanggan`
    FOREIGN KEY (`id_pelanggan`) REFERENCES `pengguna`(`id_pengguna`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reservasi_dibuat_oleh`
    FOREIGN KEY (`dibuat_oleh`) REFERENCES `pengguna`(`id_pengguna`)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- 4. Tabel blokir_tanggal
CREATE TABLE IF NOT EXISTS `blokir_tanggal` (
  `id_blokir`       INT AUTO_INCREMENT PRIMARY KEY,
  `id_tipe`         INT          NOT NULL,
  `tanggal_mulai`   DATE         NOT NULL,
  `tanggal_selesai` DATE         NOT NULL COMMENT 'eksklusif, seperti tanggal checkout',
  `alasan`          VARCHAR(200) NOT NULL,
  `dibuat_oleh`     INT          NULL,
  `dibuat_pada`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dibatalkan_pada` DATETIME     NULL,
  CONSTRAINT `fk_blokir_tipe`
    FOREIGN KEY (`id_tipe`) REFERENCES `tipe_villa`(`id_tipe`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_blokir_dibuat`
    FOREIGN KEY (`dibuat_oleh`) REFERENCES `pengguna`(`id_pengguna`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX `idx_blokir_tipe_tanggal` (`id_tipe`, `tanggal_mulai`, `tanggal_selesai`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
