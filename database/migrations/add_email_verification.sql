
-- =====================================================
-- MIGRATION: EMAIL VERIFICATION
-- Villa Joglo Masayu
-- =====================================================

-- 1. Tambahkan status verifikasi email.
ALTER TABLE pengguna
ADD COLUMN email_verified_at DATETIME NULL DEFAULT NULL
AFTER status_akun;


-- 2. Pertahankan akun lama sebagai akun terverifikasi.
-- Termasuk seluruh akun seed yang sudah tersedia.
UPDATE pengguna
SET email_verified_at = COALESCE(dibuat_pada, NOW());


-- 3. Buat tabel token verifikasi.
CREATE TABLE email_verification_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    id_pengguna INT NOT NULL,

    token_hash CHAR(64)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NOT NULL,

    expires_at DATETIME NOT NULL,

    used_at DATETIME NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_email_token_hash (token_hash),

    INDEX idx_email_token_user_created (
        id_pengguna,
        created_at
    ),

    CONSTRAINT fk_email_token_pengguna
        FOREIGN KEY (id_pengguna)
        REFERENCES pengguna(id_pengguna)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
