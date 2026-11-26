-- ============================================================
-- Migration 006: Status Kedaluwarsa, Batas Bayar & Log Riwayat Reservasi
-- Menambahkan batas_bayar_pada, rencana_bayar, kedaluwarsa_pada,
-- memperluas ENUM status dengan 'kedaluwarsa', serta membuat tabel reservasi_log.
-- Engine: InnoDB, Charset: utf8mb4_unicode_ci
-- ============================================================

DROP PROCEDURE IF EXISTS `migrate_006_reservasi_kedaluwarsa`;

DELIMITER $$
CREATE PROCEDURE `migrate_006_reservasi_kedaluwarsa`()
BEGIN
  -- 1. Perluas ENUM status pada tabel reservasi
  ALTER TABLE `reservasi` 
    MODIFY COLUMN `status` ENUM('menunggu','dikonfirmasi','ditolak','dibatalkan','kedaluwarsa') NOT NULL DEFAULT 'menunggu';

  -- 2. Tambah kolom batas_bayar_pada jika belum ada
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservasi' AND COLUMN_NAME = 'batas_bayar_pada'
  ) THEN
    ALTER TABLE `reservasi` ADD COLUMN `batas_bayar_pada` DATETIME NULL AFTER `status`;
  END IF;

  -- 3. Tambah kolom rencana_bayar jika belum ada
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservasi' AND COLUMN_NAME = 'rencana_bayar'
  ) THEN
    ALTER TABLE `reservasi` ADD COLUMN `rencana_bayar` ENUM('online','tunai') NOT NULL DEFAULT 'online' AFTER `batas_bayar_pada`;
  END IF;

  -- 4. Tambah kolom kedaluwarsa_pada jika belum ada
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservasi' AND COLUMN_NAME = 'kedaluwarsa_pada'
  ) THEN
    ALTER TABLE `reservasi` ADD COLUMN `kedaluwarsa_pada` DATETIME NULL AFTER `rencana_bayar`;
  END IF;

  -- 5. Tambah indeks pencarian status & batas bayar jika belum ada
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservasi' AND INDEX_NAME = 'idx_reservasi_status_batas'
  ) THEN
    ALTER TABLE `reservasi` ADD INDEX `idx_reservasi_status_batas` (`status`, `batas_bayar_pada`);
  END IF;

  -- 6. Buat tabel reservasi_log jika belum ada
  CREATE TABLE IF NOT EXISTS `reservasi_log` (
    `id_log` INT AUTO_INCREMENT PRIMARY KEY,
    `id_reservasi` INT NOT NULL,
    `dari_status` ENUM('menunggu','dikonfirmasi','ditolak','dibatalkan','kedaluwarsa') NULL,
    `ke_status` ENUM('menunggu','dikonfirmasi','ditolak','dibatalkan','kedaluwarsa') NOT NULL,
    `oleh_pengguna` INT NULL,
    `alasan` VARCHAR(200) NULL,
    `dibuat_pada` DATETIME NOT NULL,
    INDEX `idx_reservasi_log_id` (`id_reservasi`),
    INDEX `idx_reservasi_log_dibuat` (`dibuat_pada`),
    CONSTRAINT `fk_reservasi_log_reservasi` FOREIGN KEY (`id_reservasi`) REFERENCES `reservasi` (`id_reservasi`) ON DELETE CASCADE,
    CONSTRAINT `fk_reservasi_log_pengguna` FOREIGN KEY (`oleh_pengguna`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  -- 7. Isi batas_bayar_pada untuk reservasi berstatus dikonfirmasi yang sudah ada
  -- Sesuai perhitungan tampilan sekarang: dibuat_pada + 24 jam
  UPDATE `reservasi`
  SET `batas_bayar_pada` = DATE_ADD(`dibuat_pada`, INTERVAL 24 HOUR)
  WHERE `status` = 'dikonfirmasi' AND `batas_bayar_pada` IS NULL;

  -- 8. Inisialisasi entri log awal jika tabel log masih kosong untuk reservasi tersebut
  INSERT INTO `reservasi_log` (`id_reservasi`, `dari_status`, `ke_status`, `oleh_pengguna`, `alasan`, `dibuat_pada`)
  SELECT r.`id_reservasi`, NULL, r.`status`, r.`diproses_oleh`, 'Inisialisasi migrasi data status awal', r.`dibuat_pada`
  FROM `reservasi` r
  WHERE NOT EXISTS (
    SELECT 1 FROM `reservasi_log` rl WHERE rl.`id_reservasi` = r.`id_reservasi`
  );

END$$
DELIMITER ;

CALL `migrate_006_reservasi_kedaluwarsa`();
DROP PROCEDURE IF EXISTS `migrate_006_reservasi_kedaluwarsa`;
