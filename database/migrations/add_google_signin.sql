-- Migrasi Google Sign-In
-- Jalankan satu kali pada database yang sudah ada.

ALTER TABLE `pengguna`
    ADD COLUMN `google_id` VARCHAR(255)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NULL DEFAULT NULL
        AFTER `email`,
    ADD UNIQUE KEY `uq_pengguna_google_id` (`google_id`);
