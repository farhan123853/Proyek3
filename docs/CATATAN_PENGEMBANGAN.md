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

---

## Tahap 3: Perancangan & Migrasi Tabel Pembayaran
- **Tanggal/Waktu**: 2026-11-16 (WIB)
- **Status Gerbang**: LULUS
- **Ringkasan Pekerjaan**:
  1. Pembuatan Skema Tabel `pembayaran`:
     - Berkas migrasi `database/migrations/003_pembayaran.sql` dibuat dengan atribut: `id_pembayaran`, `id_reservasi` (FK), `order_id` (UNIQUE), `sumber` (`midtrans`/`manual`), `jumlah`, `status` (`menunggu`, `lunas`, `gagal`, `kedaluwarsa`, `ganda`, `dibatalkan`), `metode_kelompok`, `metode_detail`, `transaction_id`, `snap_token`, `catatan`, `dicatat_oleh` (FK), `dibatalkan_oleh` (FK), `alasan_batal`, `dibuat_pada`, `dibayar_pada`.
     - Skema diperbarui ke `database/schema.sql` dan didokumentasikan di `docs/STRUKTUR_DATABASE.md`.
  2. Penegakan Aturan Satu-Lunas (One-Paid Rule):
     - Ditegakkan langsung pada level basis data via Stored Generated Column `lunas_key INT GENERATED ALWAYS AS (IF(status='lunas', id_reservasi, NULL)) STORED` dengan indeks unik `uq_pembayaran_reservasi_lunas (lunas_key)`.
     - Menolak duplikasi status `lunas` pada reservasi yang sama, sekaligus mengizinkan banyak percobaan berstatus non-lunas.
     - Relasi foreign key `fk_pembayaran_reservasi` menggunakan `ON DELETE RESTRICT ON UPDATE RESTRICT` sesuai spesifikasi MySQL 8 untuk kolom yang dirujuk oleh stored generated column.
  3. Pemetaan Helper & Seed Demo:
     - Fungsi `kelompok_metode(?string $paymentType): string` ditambahkan ke `includes/helpers.php` sesuai dokumentasi resmi Midtrans (memetakan VA bank, e-wallet, tunai, dan fallback 'lainnya').
     - Fungsi `status_bayar_efektif(int $idReservasi): array` ditambahkan untuk mengevaluasi status pembayaran efektif per reservasi.
     - Berkas `database/seed-demo.sql` dibuat dengan peringatan "DATA UJI, JANGAN DIPAKAI DI PRODUKSI" berisi data contoh transaksi lintas bulan (Jan-Nov 2026) dan lintas tahun (2025).
  4. Pengujian Gerbang:
     - Dijalankan `tests/test_tahap3_db.php`: berhasil menyisipkan multiple order menunggu, satu lunas berhasil, percobaan lunas kedua untuk reservasi yang sama ditolak MySQL (Error 23000 Integrity constraint violation). Lulus sempurna.



