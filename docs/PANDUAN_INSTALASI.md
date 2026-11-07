# Panduan Instalasi & Konfigurasi — Villa Joglo Masayu

Dokumen ini memandu langkah demi langkah pemasangan sistem aplikasi **Villa Joglo Masayu** pada lingkungan pengembangan lokal (XAMPP, Laragon, atau PHP CLI Built-in Server).

---

## 1. Prasyarat Sistem

| Komponen | Versi Minimal | Keterangan |
|---|---|---|
| **PHP** | 8.0 atau lebih baru | Wajib mengaktifkan ekstensi `pdo_mysql`, `curl`, dan `mbstring` |
| **MySQL / MariaDB** | 5.7+ / 10.3+ | Engine InnoDB, Charset `utf8mb4` |
| **Web Server** | Apache 2.4+ / Nginx / PHP CLI Server | Mendukung berkas `.htaccess` jika memakai Apache |
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
1. `database/schema.sql` (struktur tabel `pengguna`, `tipe_villa`, dan `reservasi`).
2. `database/seed.sql` (data awal tipe villa, akun pengujian, dan contoh reservasi).

Jika menggunakan terminal:
```bash
cd C:\xampp\htdocs\website
mysql -u root -p villa_joglo_masayu < database/schema.sql
mysql -u root -p villa_joglo_masayu < database/seed.sql
```

### Langkah 4: Siapkan Konfigurasi Lingkungan (`config.php`)
Salin berkas `config/config.example.php` menjadi `config/config.php`:
```bash
cp config/config.example.php config/config.php
```
Buka `config/config.php` dengan text editor, lalu sesuaikan konfigurasi:
```php
return [
    'DB_HOST'    => 'localhost',
    'DB_PORT'    => 3306,
    'DB_NAME'    => 'villa_joglo_masayu',
    'DB_USER'    => 'root',
    'DB_PASS'    => '', // Isi password database jika ada
    'DB_CHARSET' => 'utf8mb4',

    // Kode wilayah tingkat IV (Desa Sukoharjo, Ngaglik, Sleman)
    'BMKG_ADM4'  => '34.04.05.2003',

    // URL aplikasi untuk base href
    'APP_URL'    => 'http://localhost/website',
    'APP_DEBUG'  => true,
];
```

### Langkah 5: Beri Izin Tulis untuk Folder `cache/`
Pastikan folder `cache/` dapat ditulisi oleh web server untuk menyimpan cache prakiraan cuaca BMKG (`cache/cuaca.json`).

### Langkah 6: Buka Aplikasi di Browser
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

## 5. Konfigurasi Layanan Cuaca BMKG

- Sistem menggunakan API Terbuka BMKG:
  `https://api.bmkg.go.id/publik/prakiraan-cuaca?adm4={kode_wilayah_tingkat_iv}`
- Kode wilayah Desa Sukoharjo, Kapanewon Ngaglik, Kabupaten Sleman adalah: `34.04.05.2003` (sesuai Kepmendagri 100.1.1-6117 Tahun 2022).
- Sistem menyimpan respon cuaca selama 1 jam di `cache/cuaca.json` untuk menjaga batas pemanggilan API (60 permintaan/menit/IP).
- Jika layanan BMKG mengalami gangguan atau timeout (5 detik), sistem secara otomatis menyajikan pesan alternatif informatif tanpa mengganggu fitur pemesanan villa.

---

## 6. Pemecahan Masalah (Troubleshooting)

| Gejala Masalah | Penyebab Umum | Solusi |
|---|---|---|
| `Fatal error: Uncaught PDOException: could not find driver` | Ekstensi PDO MySQL dinonaktifkan pada `php.ini` | Buka `php.ini`, hapus tanda titik koma `;` di depan `extension=pdo_mysql`, simpan dan restart Apache. |
| Layanan cuaca menampilkan pesan alternatif | Tidak ada koneksi internet atau cURL belum aktif | Pastikan ekstensi `curl` aktif pada `php.ini` dan koneksi internet stabil. |
| Navigasi halaman dashboard rusak | `APP_URL` di `config.php` belum sesuai | Sesuaikan nilai `'APP_URL'` pada `config/config.php` dengan URL yang sedang diakses di browser. |
| Tidak bisa konfirmasi reservasi | Kapasitas unit telah terpesan pada tanggal tumpang tindih | Hal ini merupakan fitur proteksi overbooking (T-08 & T-16). Periksa tanggal lain atau tolak pemesanan yang bentrok. |
