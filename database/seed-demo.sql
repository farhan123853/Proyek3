-- ==============================================================================
-- SEED DATA DEMO / PENGUJIAN LAPORAN — Villa Joglo Masayu
-- PERINGATAN: DATA UJI, JANGAN DIPAKAI DI LINGKUNGAN PRODUKSI!
-- Berkas ini bersifat OPSIONAL untuk memvalidasi fitur laporan pemasukan bulanan
-- pada Dashboard Pemilik dan monitoring transaksi Admin lintas bulan & tahun.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Pastikan reservasi contoh untuk bulan-bulan di tahun 2026 dan 2025 tersedia
INSERT INTO `reservasi` (`id_reservasi`, `id_pelanggan`, `id_tipe`, `diproses_oleh`, `tanggal_checkin`, `tanggal_checkout`, `jumlah_tamu`, `catatan`, `status`, `harga_weekday_saat_pesan`, `harga_weekend_saat_pesan`, `harga_extrabed_saat_pesan`, `estimasi_total`, `dibuat_pada`) VALUES
(101, 4, 1, 2, '2026-01-10', '2026-01-12', 2, 'Demo Jan 2026', 'dikonfirmasi', 1500000, 2000000, 350000, 3000000, '2026-01-08 10:00:00'),
(102, 5, 2, 2, '2026-02-14', '2026-02-16', 4, 'Demo Feb 2026', 'dikonfirmasi', 2200000, 2900000, 350000, 4000000, '2026-02-10 11:00:00'),
(103, 4, 1, 2, '2026-03-05', '2026-03-07', 2, 'Demo Mar 2026', 'dikonfirmasi', 1500000, 2000000, 350000, 2600000, '2026-03-01 09:00:00'),
(104, 5, 3, 2, '2026-04-18', '2026-04-20', 6, 'Demo Apr 2026', 'dikonfirmasi', 3800000, 4800000, 400000, 4500000, '2026-04-12 14:00:00'),
(105, 4, 2, 2, '2026-05-22', '2026-05-24', 4, 'Demo Mei 2026', 'dikonfirmasi', 2200000, 2900000, 350000, 4000000, '2026-05-15 16:00:00'),
(106, 5, 3, 2, '2026-06-11', '2026-06-13', 6, 'Demo Jun 2026', 'dikonfirmasi', 3800000, 4800000, 400000, 6000000, '2026-06-05 08:30:00'),
(107, 4, 3, 2, '2026-07-20', '2026-07-22', 6, 'Demo Jul 2026', 'dikonfirmasi', 3800000, 4800000, 400000, 7000000, '2026-07-10 13:00:00'),
(108, 5, 2, 2, '2026-08-15', '2026-08-17', 4, 'Demo Agu 2026', 'dikonfirmasi', 2200000, 2900000, 350000, 5800000, '2026-08-08 15:45:00'),
(109, 4, 1, 2, '2026-09-09', '2026-09-11', 2, 'Demo Sep 2026', 'dikonfirmasi', 1500000, 2000000, 350000, 3600000, '2026-09-02 10:15:00'),
(110, 5, 2, 2, '2026-10-14', '2026-10-16', 4, 'Demo Okt 2026', 'dikonfirmasi', 2200000, 2900000, 350000, 5000000, '2026-10-05 11:20:00'),
(111, 4, 1, 2, '2026-11-10', '2026-11-12', 2, 'Demo Nov 2026', 'dikonfirmasi', 1500000, 2000000, 350000, 3300000, '2026-11-04 09:00:00'),
(112, 4, 1, 2, '2025-12-20', '2025-12-22', 2, 'Demo Des 2025', 'dikonfirmasi', 1500000, 2000000, 350000, 3000000, '2025-12-15 10:00:00')
ON DUPLICATE KEY UPDATE `estimasi_total` = VALUES(`estimasi_total`);

-- 2. Sampel data pembayaran lintas metode dan bulan
DELETE FROM `pembayaran` WHERE `order_id` LIKE 'DEMO-%';

INSERT INTO `pembayaran` (`id_reservasi`, `order_id`, `sumber`, `jumlah`, `status`, `metode_kelompok`, `metode_detail`, `transaction_id`, `snap_token`, `catatan`, `dicatat_oleh`, `dibuat_pada`, `dibayar_pada`) VALUES
-- Tahun 2025 (Pengujian lintas tahun)
(112, 'DEMO-2025-12-01', 'midtrans', 3000000, 'lunas', 'transfer_bank', 'bca_va', 'TRX-2025-12-01', 'token-demo-2025', 'Pembayaran via BCA VA', NULL, '2025-12-15 10:30:00', '2025-12-15 11:00:00'),

-- Tahun 2026 (Jan - Nov)
(101, 'DEMO-2026-01-01', 'midtrans', 3000000, 'lunas', 'transfer_bank', 'bni_va', 'TRX-2026-01-01', 'token-demo-01', 'Pembayaran BNI VA', NULL, '2026-01-08 10:30:00', '2026-01-08 11:00:00'),
(102, 'DEMO-2026-02-01', 'midtrans', 4000000, 'lunas', 'transfer_bank', 'mandiri_bill', 'TRX-2026-02-01', 'token-demo-02', 'Pembayaran Mandiri Bill', NULL, '2026-02-10 11:30:00', '2026-02-10 12:00:00'),
(103, 'DEMO-2026-03-01', 'midtrans', 2600000, 'lunas', 'transfer_bank', 'bri_va', 'TRX-2026-03-01', 'token-demo-03', 'Pembayaran BRI VA', NULL, '2026-03-01 09:30:00', '2026-03-01 10:00:00'),
(104, 'DEMO-2026-04-01', 'midtrans', 4500000, 'lunas', 'transfer_bank', 'bca_va', 'TRX-2026-04-01', 'token-demo-04', 'Pembayaran BCA VA', NULL, '2026-04-12 14:30:00', '2026-04-12 15:00:00'),
(105, 'DEMO-2026-05-01', 'midtrans', 4000000, 'lunas', 'transfer_bank', 'bni_va', 'TRX-2026-05-01', 'token-demo-05', 'Pembayaran BNI VA', NULL, '2026-05-15 16:30:00', '2026-05-15 17:00:00'),
(106, 'DEMO-2026-06-01', 'midtrans', 6000000, 'lunas', 'transfer_bank', 'mandiri_bill', 'TRX-2026-06-01', 'token-demo-06', 'Pembayaran Mandiri Bill', NULL, '2026-06-05 09:00:00', '2026-06-05 09:30:00'),
(107, 'DEMO-2026-07-01', 'midtrans', 7000000, 'lunas', 'transfer_bank', 'bca_va', 'TRX-2026-07-01', 'token-demo-07', 'Pembayaran BCA VA', NULL, '2026-07-10 13:30:00', '2026-07-10 14:00:00'),
(108, 'DEMO-2026-08-01', 'midtrans', 5800000, 'lunas', 'transfer_bank', 'bri_va', 'TRX-2026-08-01', 'token-demo-08', 'Pembayaran BRI VA', NULL, '2026-08-08 16:00:00', '2026-08-08 16:30:00'),
(109, 'DEMO-2026-09-01', 'midtrans', 3600000, 'lunas', 'transfer_bank', 'bni_va', 'TRX-2026-09-01', 'token-demo-09', 'Pembayaran BNI VA', NULL, '2026-09-02 10:30:00', '2026-09-02 11:00:00'),
(110, 'DEMO-2026-10-01', 'midtrans', 5000000, 'lunas', 'transfer_bank', 'bca_va', 'TRX-2026-10-01', 'token-demo-10', 'Pembayaran BCA VA', NULL, '2026-10-05 11:45:00', '2026-10-05 12:15:00'),
(111, 'DEMO-2026-11-01', 'midtrans', 3300000, 'lunas', 'transfer_bank', 'gopay', 'TRX-2026-11-01', 'token-demo-11', 'Pembayaran GoPay', NULL, '2026-11-04 09:30:00', '2026-11-04 10:00:00');

SET FOREIGN_KEY_CHECKS = 1;
