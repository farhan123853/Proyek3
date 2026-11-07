-- ============================================================
-- SEED DATA: Villa Joglo Masayu
-- Data awal untuk pengujian fungsional dan dashboard
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `reservasi`;
TRUNCATE TABLE `tipe_villa`;
TRUNCATE TABLE `pengguna`;

-- 1. Pengguna Awal
-- Password default:
-- admin@joglomasayu.com     : AdminMasayu2026!     (Wajib diganti setelah instalasi)
-- pengelola@joglomasayu.com : Pengelola2026!
-- pemilik@joglomasayu.com   : Pemilik2026!
-- budi@gmail.com            : Pelanggan2026!
INSERT INTO `pengguna` (`id_pengguna`, `nama`, `email`, `kontak`, `password_hash`, `peran`, `status_akun`, `dibuat_pada`) VALUES
(1, 'Admin Sistem', 'admin@joglomasayu.com', '081234567890', '$2y$12$qC68lXtawlKlc0qVVSttX.VPwEnqEtT16NgoI.GjE/UCFQOJnikU2', 'admin', 'aktif', NOW()),
(2, 'Pengelola Masayu', 'pengelola@joglomasayu.com', '081234567891', '$2y$12$i4apORqa1QG5MdvdpyFYreKTFUxnw4rK43Q2UWMEnStrQRJWPPO5a', 'pengelola', 'aktif', NOW()),
(3, 'Pemilik Villa', 'pemilik@joglomasayu.com', '081234567892', '$2y$12$gzNoLXliHUELuq.5S1eX..6cCKou71niVUIBMMSDCsT9wA1s1hyim', 'pemilik', 'aktif', NOW()),
(4, 'Budi Santoso', 'budi@gmail.com', '081298765432', '$2y$12$RjL5bxSO0XERmXe4Q5NJVeIaO8USqNio2JCm7jcp9BpWU0SlCLnuy', 'pelanggan', 'aktif', NOW()),
(5, 'Siti Rahma', 'siti@gmail.com', '081211223344', '$2y$12$RjL5bxSO0XERmXe4Q5NJVeIaO8USqNio2JCm7jcp9BpWU0SlCLnuy', 'pelanggan', 'aktif', NOW());

-- 2. Tipe Villa
-- Data mengacu pada objek VILLAS di script.js
-- TODO: Konfirmasi jumlah_unit asli ke pemilik villa (placeholder: 1 unit per tipe)
INSERT INTO `tipe_villa` (`id_tipe`, `slug`, `nama`, `kapasitas_standar`, `kapasitas_maks`, `harga_weekday`, `harga_weekend`, `harga_extrabed`, `jumlah_unit`, `deskripsi`, `fasilitas`) VALUES
(1, 'joglo-wulung', 'Joglo Wulung', 2, 3, 1500000.00, 2000000.00, 350000.00, 1, 
 'Joglo Wulung adalah villa paling intim di Masayu. Dibangun dari kayu jati tua berusia lebih dari 80 tahun, villa ini menghadirkan nuansa rumah leluhur yang hangat dan autentik. Satu kamar tidur dengan ranjang king, kamar mandi semi-terbuka, teras dengan pemandangan sawah, dan kolam renang pribadi menjadikan Wulung tempat sempurna untuk bulan madu atau retreat dua orang.',
 '["1 kamar tidur king", "Kolam renang pribadi", "Kamar mandi semi-terbuka", "Teras & hammock", "AC & kipas langit-langit", "Wi-Fi", "Sarapan untuk 2 orang", "Pembuat kopi", "Minibar", "Handuk & amenities"]'),

(2, 'joglo-saren', 'Joglo Saren', 4, 5, 2200000.00, 2900000.00, 350000.00, 1,
 'Joglo Saren dirancang untuk keluarga atau teman yang ingin berbagi keindahan Masayu bersama. Dua kamar tidur, ruang keluarga yang lapang dengan sofa rendah khas Jawa, dapur kecil, dan kolam renang yang luas menghadap taman menciptakan suasana yang hangat dan inklusif.',
 '["2 kamar tidur (1 king, 1 twin)", "Ruang keluarga", "Kolam renang bersama", "Dapur kecil", "Teras luas", "AC & kipas angin", "Wi-Fi", "Sarapan untuk 4 orang", "Pembuat kopi & teh", "Handuk & amenities"]'),

(3, 'joglo-agung', 'Joglo Agung', 6, 8, 3800000.00, 4800000.00, 400000.00, 1,
 'Joglo Agung adalah mahkota Masayu. Dengan pendopo besar yang dapat menampung pertemuan keluarga atau resepsi kecil, tiga kamar tidur mewah, dapur penuh, dan kolam renang panoramik yang menghadap sawah dan perbukitan, villa ini menawarkan pengalaman yang tak tertandingi. Setiap detail, dari ornamen ukiran hingga furniture antik, dipilih untuk menceritakan keagungan budaya Jawa.',
 '["3 kamar tidur (2 king, 1 twin)", "Pendopo besar", "Kolam renang panoramik", "Dapur penuh", "Ruang makan outdoor", "AC & kipas plafon", "Wi-Fi", "Sarapan untuk 6 orang", "Layanan kamar", "Concierge pribadi", "Handuk & amenities premium"]');

-- 3. Data Contoh Reservasi (untuk pengujian dashboard Pengelola dan Pemilik)
-- Catatan: Estimasi nilai reservasi dihitung dari tarif per malam saat pemesanan.
-- Reservasi 1: Joglo Saren (id_tipe: 2), Budi (id: 4), 2 malam weekday, dikonfirmasi oleh pengelola (id: 2)
-- Tarif: 2 x Rp 2.200.000 = Rp 4.400.000
INSERT INTO `reservasi` (`id_reservasi`, `id_pelanggan`, `id_tipe`, `diproses_oleh`, `tanggal_checkin`, `tanggal_checkout`, `jumlah_tamu`, `catatan`, `status`, `harga_weekday_saat_pesan`, `harga_weekend_saat_pesan`, `harga_extrabed_saat_pesan`, `estimasi_total`, `dibuat_pada`) VALUES
(1, 4, 2, 2, '2026-11-16', '2026-11-18', 4, 'Mohon disiapkan sarapan tradisional gudeg.', 'dikonfirmasi', 2200000.00, 2900000.00, 350000.00, 4400000.00, NOW() - INTERVAL 5 DAY);

-- Reservasi 2: Joglo Wulung (id_tipe: 1), Siti (id: 5), 1 malam weekday + 1 malam weekend, 3 tamu (1 extra bed)
-- Cek: 2026-11-26 (Kamis) s.d. 2026-11-28 (Sabtu) -> 1 weekday (Kamis), 1 weekend (Jumat).
-- Tarif: (1 x Rp 1.500.000) + (1 x Rp 2.000.000) + (1 extra bed x 2 malam x Rp 350.000 = Rp 700.000) = Rp 4.200.000
-- Status: menunggu (menunggu persetujuan pengelola)
INSERT INTO `reservasi` (`id_reservasi`, `id_pelanggan`, `id_tipe`, `diproses_oleh`, `tanggal_checkin`, `tanggal_checkout`, `jumlah_tamu`, `catatan`, `status`, `harga_weekday_saat_pesan`, `harga_weekend_saat_pesan`, `harga_extrabed_saat_pesan`, `estimasi_total`, `dibuat_pada`) VALUES
(2, 5, 1, NULL, '2026-11-26', '2026-11-28', 3, 'Extra bed untuk anak usia 10 tahun.', 'menunggu', 1500000.00, 2000000.00, 350000.00, 4200000.00, NOW() - INTERVAL 2 DAY);

-- Reservasi 3: Joglo Agung (id_tipe: 3), Budi (id: 4), 2 malam weekend (Jumat-Minggu), 6 tamu
-- Tarif: 2 x Rp 4.800.000 = Rp 9.600.000
-- Status: ditolak (alasan pemeliharaan pendopo)
INSERT INTO `reservasi` (`id_reservasi`, `id_pelanggan`, `id_tipe`, `diproses_oleh`, `tanggal_checkin`, `tanggal_checkout`, `jumlah_tamu`, `catatan`, `status`, `harga_weekday_saat_pesan`, `harga_weekend_saat_pesan`, `harga_extrabed_saat_pesan`, `estimasi_total`, `dibuat_pada`) VALUES
(3, 4, 3, 2, '2026-11-20', '2026-11-22', 6, 'Acara reuni keluarga kecil.', 'ditolak', 3800000.00, 4800000.00, 400000.00, 9600000.00, NOW() - INTERVAL 3 DAY);

-- Reservasi 4: Joglo Agung (id_tipe: 3), Siti (id: 5), 3 malam weekday (Senin-Kamis), 6 tamu
-- Tarif: 3 x Rp 3.800.000 = Rp 11.400.000
-- Status: dikonfirmasi
INSERT INTO `reservasi` (`id_reservasi`, `id_pelanggan`, `id_tipe`, `diproses_oleh`, `tanggal_checkin`, `tanggal_checkout`, `jumlah_tamu`, `catatan`, `status`, `harga_weekday_saat_pesan`, `harga_weekend_saat_pesan`, `harga_extrabed_saat_pesan`, `estimasi_total`, `dibuat_pada`) VALUES
(4, 5, 3, 2, '2026-12-07', '2026-12-10', 6, 'Liburan keluarga akhir tahun.', 'dikonfirmasi', 3800000.00, 4800000.00, 400000.00, 11400000.00, NOW() - INTERVAL 1 DAY);

SET FOREIGN_KEY_CHECKS = 1;
