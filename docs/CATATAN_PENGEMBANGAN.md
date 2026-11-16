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

---

## Tahap 2: Penghapusan Total Fitur Cuaca BMKG
- **Tanggal/Waktu**: 2026-11-16 (WIB)
- **Status Gerbang**: LULUS
- **Ringkasan Pekerjaan**:
  1. Penghapusan Berkas:
     - Dihapus: `api/cuaca.php`, `assets/js/cuaca.js`, `cuaca.php`, `cache/cuaca.json`, dan direktori `cache/`.
  2. Pembersihan Kode & Konfigurasi:
     - Dihapus: konstanta `BMKG_ADM4` pada `config/config.example.php` dan `config/config.php`.
     - Dihapus: kontainer widget cuaca dan pemanggilan `assets/js/cuaca.js` pada `index.php` dan `villa.php`.
     - Dihapus: aturan CSS `.weather-widget` dan komponen turunannya pada `assets/css/app.css`.
     - Dihapus: kunci translasi cuaca (`weather.*` dan `nav.cuaca`) pada `lang/id.php` dan `lang/en.php`. Skrip `tests/cek-i18n.php` terverifikasi 148 kunci lengkap tanpa error.
  3. Pembaruan Dokumentasi & Rencana Uji:
     - Skenario uji cuaca T-13 dan T-14 pada `docs/RENCANA_UJI.md` ditandai dibatalkan atas arahan pembimbing.
     - `README.md`, `docs/PANDUAN_INSTALASI.md`, `docs/PANDUAN_PENGGUNAAN.md` diperbarui dengan menghapus seluruh referensi cuaca BMKG.
     - `docs/SINKRONISASI_PROPOSAL.md` diperbarui secara komprehensif mencatat seluruh bagian naskah proposal yang harus direvisi (Bab 1.2, 1.3, 1.4, 1.5, 1.6, F-02, F-03, T-13, T-14, Tabel 2.1, Bab 2.4, Tabel 3.2, Tabel 3.6, Gambar 3.1, Bab 6, Lembar Kontribusi, Referensi [11]).
  4. Kompilasi & Pengujian:
     - `npm run build` dijalankan ulang (bersih tanpa class yatim).
     - Seluruh berkas PHP lulus `php -l`.
     - `git grep -n -i -E "cuaca|bmkg|weather|prakiraan|adm4"` terbukti bersih (hanya terdapat di dokumen penyelarasan proposal dan catatan ini).
     - Seluruh rute halaman dimuat normal (status 200) tanpa error konsol.


