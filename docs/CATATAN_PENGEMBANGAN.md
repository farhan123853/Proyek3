# Catatan Pengembangan — Villa Joglo Masayu

Dokumen ini mencatat riwayat pelaksanaan tahapan pengembangan, keputusan teknis, audit, dan integrasi fitur pada sistem Website Villa Joglo Masayu (Proyek 3 D4 Teknik Informatika ULBI).

---

## Tahap 1: Audit dan Persiapan Integrasi Midtrans Payment Gateway
- **Tanggal/Waktu**: 2026-11-16 (WIB)
- **Status Gerbang**: LULUS
- **Ringkasan Pekerjaan**:
  1. Audit menyeluruh terhadap kode eksisting:
     - Alur reservasi: Pelanggan mengajukan -> status `menunggu` -> Pengelola konfirmasi (`dikonfirmasi`) atau tolak (`ditolak`).
     - Perhitungan `estimasi_total`: Dihitung berdasarkan rincian malam weekday & weekend dikalikan tarif unit dan extra bed, menghasilkan bilangan bulat integer Rupiah.
     - Helper keamanan & layout: `includes/auth.php` (otentikasi & `require_role()`), `includes/csrf.php` (`csrf_field()`, `verify_csrf_token()`), `includes/helpers.php` (`e()`, `t()`, `flash()`, `get_active_lang()`), dan sistem toast vanilla JS.
     - Zona waktu: Ditetapkan secara konsisten ke `Asia/Jakarta` (WIB) pada `includes/bootstrap.php` (`date_default_timezone_set('Asia/Jakarta')`) dan sesi MySQL (`SET time_zone='+07:00'`) pada opsi koneksi PDO di `includes/db.php`.
     - Versi basis data terdeteksi: MySQL 8.0.30 (mendukung InnoDB, stored generated column, dan index unik).
  2. Pemasangan Pustaka Resmi:
     - `composer require midtrans/midtrans-php` berhasil dipasang (versi 2.6.2).
     - Berkas `composer.json` dan `composer.lock` ditambahkan ke version control.
     - Direktori `vendor/` dan `node_modules/` diamankan dalam `.gitignore`.
     - Inisialisasi otomatis via `vendor/autoload.php` diintegrasikan ke `includes/bootstrap.php`.
  3. Konfigurasi Lingkungan:
     - `config/config.example.php` diperbarui dengan konstanta `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_IS_PRODUCTION` (default `false`), dan `PEMBAYARAN_BATAS_JAM` (24 jam).
     - `docs/PANDUAN_INSTALASI.md` diperbarui dengan panduan `composer install`, registrasi sandbox Midtrans, pengambilan Access Keys, dan konfigurasi lingkungan.
- **Verifikasi Gerbang Tahap 1**:
  - `composer install` / autoloader berfungsi normal.
  - Kelas `\Midtrans\Config` dapat dimuat dan diakses.
  - Perintah `git grep "SB-Mid-"` bersih (tidak menemukan string kunci sandbox dalam repositori yang dilacak Git).
