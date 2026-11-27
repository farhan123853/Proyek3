-- ============================================================
-- Migration 007: Perpanjangan Batas Bayar Reservasi
-- Menambahkan kolom perpanjang_kali pada tabel reservasi
-- untuk melacak jumlah perpanjangan batas bayar (maksimal 2x).
-- Engine: InnoDB, Charset: utf8mb4_unicode_ci
-- ============================================================

DROP PROCEDURE IF EXISTS `migrate_007_perpanjang_kedaluwarsa`;

DELIMITER $$
CREATE PROCEDURE `migrate_007_perpanjang_kedaluwarsa`()
BEGIN
  -- 1. Tambah kolom perpanjang_kali jika belum ada
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservasi' AND COLUMN_NAME = 'perpanjang_kali'
  ) THEN
    ALTER TABLE `reservasi` ADD COLUMN `perpanjang_kali` TINYINT NOT NULL DEFAULT 0 AFTER `kedaluwarsa_pada`;
  END IF;

END$$
DELIMITER ;

CALL `migrate_007_perpanjang_kedaluwarsa`();
DROP PROCEDURE IF EXISTS `migrate_007_perpanjang_kedaluwarsa`;
