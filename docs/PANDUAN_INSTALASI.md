# Panduan Instalasi & Konfigurasi — Villa Joglo Masayu

Dokumen ini memandu langkah demi langkah pemasangan sistem aplikasi **Villa Joglo Masayu** pada lingkungan pengembangan lokal (XAMPP, Laragon, atau PHP CLI Built-in Server).

---

## 1. Prasyarat Sistem

| Komponen | Versi Minimal | Keterangan |
|---|---|---|
| **PHP** | 8.0 atau lebih baru | Wajib mengaktifkan ekstensi `pdo_mysql`, `curl`, dan `mbstring` |
| **MySQL / MariaDB** | 5.7+ / 10.3+ | Engine InnoDB, Charset `utf8mb4` |
| **Web Server** | Apache 2.4+ / Nginx / PHP CLI Server | Mendukung berkas `.htaccess` jika memakai Apache |
| **Composer** | 2.0 atau lebih baru | Untuk instalasi pustaka Midtrans PHP SDK (`midtrans/midtrans-php`) |
| **Browser Modern** | Chrome, Firefox, Edge, Safari | Mendukung JavaScript ES6+ dan Fetch API |

---

## 2. Cara 1: Menggunakan XAMPP / Laragon (Disarankan)

### Langkah 1: Pindahkan Berkas Proyek
Salin folder proyek ini ke direktori web server:
- **XAMPP**: `C:\xampp\htdocs\website`
- **Laragon**: `C:\laragon\www\website`

### Langkah 2: Buat Basis Data MySQL
Buka phpMyAdmin (`http://localhost/phpmyadmin`) atau terminal MySQL, lalu jalankan:
```sql
CREATE DATABASE IF NOT EXISTS `villa_joglo_masayu`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### Langkah 3: Impor Skema dan Data Awal (Seed)
Impor kedua berkas SQL yang tersedia di folder `database/`:
1. `database/schema.sql` (struktur seluruh tabel, termasuk tabel token verifikasi email).
2. `database/seed.sql` (data awal tipe villa, akun pengujian, dan contoh reservasi).

Jika menggunakan terminal:
```bash
cd C:\xampp\htdocs\website
mysql -u root -p villa_joglo_masayu < database/schema.sql
mysql -u root -p villa_joglo_masayu < database/seed.sql
```

Jika database sudah dipakai sebelum fitur-fitur terbaru ditambahkan, **jangan impor ulang `schema.sql` atau `seed.sql`**. Cadangkan database terlebih dahulu, lalu pilih database `villa_joglo_masayu` di phpMyAdmin (atau via CLI) dan jalankan hanya migrasi yang belum diterapkan dengan urutan berikut:

1. `database/migrations/001_dwibahasa.sql` — menambahkan kolom bahasa preferensi pengguna.
2. `database/migrations/003_pembayaran.sql` — membuat tabel `pembayaran`.
3. `database/migrations/004_reservasi_offline.sql` (atau jalankan `php database/migrations/run_004.php`) — menambahkan kolom reservasi offline pada tabel `reservasi` dan membuat tabel `blokir_tanggal`.
4. `database/migrations/add_email_verification.sql` — menambahkan status verifikasi email (`pengguna.email_verified_at`), menandai akun lama sebagai terverifikasi, dan membuat tabel `email_verification_tokens`.
5. `database/migrations/add_google_signin.sql` — menambahkan kolom `pengguna.google_id` dan indeks unik `uq_pengguna_google_id`.

> **Aturan Migrasi Database:**
> Setiap perubahan struktur basis data berikutnya **wajib** dibuatkan berkas migrasi di direktori `database/migrations/` dengan format penamaan berurutan (misal: `005_nama_migrasi.sql`) dan dirancang **idempoten** (aman bila dijalankan ulang tanpa merusak data atau memicu error syntax). Jangan pernah mengubah skema langsung di database produksi tanpa file migrasi.

### Langkah 4: Pasang Ketergantungan Pustaka (Composer)
Jalankan Composer untuk memasang dependensi resmi Midtrans PHP SDK:
```bash
composer install --no-dev
```
Pastikan folder `vendor/` dan berkas `vendor/autoload.php` terbentuk secara sempurna.

### Langkah 5: Siapkan Konfigurasi Lingkungan (`config.php`)
Salin berkas `config/config.example.php` menjadi `config/config.php`:
```bash
cp config/config.example.php config/config.php
```
Buka `config/config.php` dengan text editor, lalu sesuaikan konfigurasi basis data dan payment gateway Midtrans:
```php
return [
    'DB_HOST'    => 'localhost',
    'DB_PORT'    => 3306,
    'DB_NAME'    => 'villa_joglo_masayu',
    'DB_USER'    => 'root',
    'DB_PASS'    => '', // Isi password database jika ada
    'DB_CHARSET' => 'utf8mb4',

    // ── Midtrans Payment Gateway (Mode Sandbox) ──────────────
    // Dapatkan kunci dari Midtrans Sandbox Dashboard (Settings > Access Keys)
    'MIDTRANS_SERVER_KEY'    => 'YOUR_MIDTRANS_SERVER_KEY',
    'MIDTRANS_CLIENT_KEY'    => 'YOUR_MIDTRANS_CLIENT_KEY',
    'MIDTRANS_IS_PRODUCTION' => false,
    'PEMBAYARAN_BATAS_JAM'   => 24,

    // URL aplikasi untuk base href
    'APP_URL'    => 'http://localhost/website',
    'APP_DEBUG'  => true,
];
```

### Langkah 6: Konfigurasi Akun Midtrans Sandbox
1. Kunjungi portal Midtrans di [https://dashboard.midtrans.com](https://dashboard.midtrans.com) atau [https://simulator.sandbox.midtrans.com](https://simulator.sandbox.midtrans.com).
2. Buat akun sandbox atau masuk ke dashboard Midtrans, lalu pastikan tombol mode berada pada **SANDBOX** (lingkungan uji coba).
3. Buka menu **Settings > Access Keys**.
4. Salin **Server Key** dan **Client Key**.
5. Tempelkan kunci tersebut ke berkas `config/config.php` pada kunci `MIDTRANS_SERVER_KEY` dan `MIDTRANS_CLIENT_KEY`.
6. Pastikan `MIDTRANS_IS_PRODUCTION` bernilai `false` selama tahap pengujian.

### Langkah 7: Buka Aplikasi di Browser
Akses URL: `http://localhost/website/index.php`

---

## 3. Cara 2: Menggunakan PHP Built-in Server (Untuk Pengujian Cepat)

1. Buka PowerShell atau Command Prompt pada root folder proyek:
   ```bash
   cd "c:\Users\Lenovo\Documents\PROYEK 3\website"
   ```
2. Pastikan file `config/config.php` sudah ada dengan `APP_URL` diatur ke `http://localhost:8000`:
   ```php
   'APP_URL' => 'http://localhost:8000',
   ```
3. Jalankan server lokal:
   ```bash
   php -S localhost:8000
   ```
4. Buka peramban di `http://localhost:8000/index.php`.

---

## 4. Akun Awal untuk Pengujian Sistem

| Peran | Alamat Email | Kata Sandi Default | Hak Akses Utama |
|---|---|---|---|
| **Admin** | `admin@joglomasayu.com` | `AdminMasayu2026!` | Manajemen akun staf, status keaktifan, peran pengguna |
| **Pengelola** | `pengelola@joglomasayu.com` | `Pengelola2026!` | Memproses (konfirmasi/tolak) reservasi, pantau unit |
| **Pemilik** | `pemilik@joglomasayu.com` | `Pemilik2026!` | Laporan eksekutif, statistik, estimasi nilai reservasi (read-only) |
| **Pelanggan** | `budi@gmail.com` | `Pelanggan2026!` | Pengajuan reservasi baru, riwayat reservasi pribadi |

> **PERINGATAN KEAMANAN:** Kata sandi akun Administrator awal **wajib diubah** saat aplikasi dipasang pada lingkungan produksi yang sebenarnya.

---

## 5. Pemecahan Masalah (Troubleshooting)

| Gejala Masalah | Penyebab Umum | Solusi |
|---|---|---|
| `Fatal error: Uncaught PDOException: could not find driver` | Ekstensi PDO MySQL dinonaktifkan pada `php.ini` | Buka `php.ini`, hapus tanda titik koma `;` di depan `extension=pdo_mysql`, simpan dan restart Apache. |
| Popup Snap Midtrans tidak muncul | Kunci Client Key belum diisi atau format token tidak valid | Pastikan `MIDTRANS_CLIENT_KEY` dan `MIDTRANS_SERVER_KEY` sudah terisi di `config/config.php` serta koneksi internet aktif. |
| Navigasi halaman dashboard rusak | `APP_URL` di `config.php` belum sesuai | Sesuaikan nilai `'APP_URL'` pada `config/config.php` dengan URL yang sedang diakses di browser. |
| Tidak bisa konfirmasi reservasi | Kapasitas unit telah terpesan pada tanggal tumpang tindih | Hal ini merupakan fitur proteksi overbooking (T-08 & T-16). Periksa tanggal lain atau tolak pemesanan yang bentrok. |
