-- ============================================================
-- Migration 005: Kolom Virtual Account & Kode Pembayaran
-- Menambahkan kolom bank_atau_toko, kode_pembayaran, kedaluwarsa_pada, dan kode_dibuat_pada
-- serta indeks non-unik (bank_atau_toko, kode_pembayaran) pada tabel pembayaran.
-- Engine: InnoDB, Charset: utf8mb4_unicode_ci
-- ============================================================

DROP PROCEDURE IF EXISTS `migrate_005_pembayaran_va`;

DELIMITER $$
CREATE PROCEDURE `migrate_005_pembayaran_va`()
BEGIN
  -- 1. Tambah kolom bank_atau_toko jika belum ada
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pembayaran' AND COLUMN_NAME = 'bank_atau_toko'
  ) THEN
    ALTER TABLE `pembayaran` ADD COLUMN `bank_atau_toko` VARCHAR(30) NULL AFTER `metode_detail`;
  END IF;

  -- 2. Tambah kolom kode_pembayaran jika belum ada
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pembayaran' AND COLUMN_NAME = 'kode_pembayaran'
  ) THEN
    ALTER TABLE `pembayaran` ADD COLUMN `kode_pembayaran` VARCHAR(64) NULL AFTER `bank_atau_toko`;
  END IF;

  -- 3. Tambah kolom kedaluwarsa_pada jika belum ada
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pembayaran' AND COLUMN_NAME = 'kedaluwarsa_pada'
  ) THEN
    ALTER TABLE `pembayaran` ADD COLUMN `kedaluwarsa_pada` DATETIME NULL AFTER `kode_pembayaran`;
  END IF;

  -- 4. Tambah kolom kode_dibuat_pada jika belum ada
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pembayaran' AND COLUMN_NAME = 'kode_dibuat_pada'
  ) THEN
    ALTER TABLE `pembayaran` ADD COLUMN `kode_dibuat_pada` DATETIME NULL AFTER `kedaluwarsa_pada`;
  END IF;

  -- 5. Tambah indeks non-unik idx_pembayaran_kode_va jika belum ada
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pembayaran' AND INDEX_NAME = 'idx_pembayaran_kode_va'
  ) THEN
    ALTER TABLE `pembayaran` ADD INDEX `idx_pembayaran_kode_va` (`bank_atau_toko`, `kode_pembayaran`);
  END IF;
END$$
DELIMITER ;

CALL `migrate_005_pembayaran_va`();
DROP PROCEDURE IF EXISTS `migrate_005_pembayaran_va`;
