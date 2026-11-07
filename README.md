# Rancang Bangun Website Villa Joglo Masayu sebagai Media Promosi dan Informasi Penginapan Bernuansa Arsitektur Joglo

> **Proyek 3 — Program Studi D4 Teknik Informatika**  
> **Universitas Logistik dan Bisnis Internasional (ULBI)**  
> **Tahun Akademik 2026/2027**

---

## Identitas Pengembang & Pembimbing

- **Penyusun:**
  1. **Farhan Malik Ibrahim** — NIM: `714240026` (Spesialisasi: Antarmuka, Responsivitas, Integrasi Tampilan JSON, Tata Letak UI/UX)
  2. **Abdullah Averouse Ibnu Sina** — NIM: `714240027` (Spesialisasi: Arsitektur Backend PHP Native, Basis Data MySQL, Autentikasi 4 Peran, Logika Ketersediaan & Tarif)
- **Usulan Dosen Pembimbing:**
  **Nisa Hanum Harani, S.Kom., M.T., CDSP.**

---

## Ringkasan & Latar Belakang

Villa Joglo Masayu merupakan penginapan bernuansa rumah tradisional Jawa autentik berbahan kayu jati tua yang berlokasi di **Jalan Kaliurang Km 14, Sukoharjo, Kapanewon Ngaglik, Kabupaten Sleman, Daerah Istimewa Yogyakarta**.

Sebelum pengembangan proyek ini:
1. Promosi dan penyajian informasi villa masih bertumpu pada media sosial yang tersebar dan kurang terstruktur.
2. Tampilan website awal masih berupa berkas statis (`index.html`, `villa.html`), di mana data tipe villa tertulis langsung (*hardcoded*) di dalam berkas JavaScript.
3. Pemesanan dilakukan hanya lewat percakapan WhatsApp tanpa pencatatan basis data dan tanpa verifikasi ketersediaan kamar, sehingga rawan terjadi pemesanan ganda (*double-booking*).
4. Belum tersedia pembagian hak akses pengguna untuk membedakan tamu, pengelola, pemilik, dan administrator.
5. Calon tamu belum dapat melihat perkiraan cuaca di sekitar lokasi penginapan.

**Solusi yang dibangun:**
Mengembangkan sistem informasi berbasis web dengan PHP native dan MySQL untuk mengelola reservasi kamar secara online, memeriksa ketersediaan tanggal secara otomatis, membagi hak akses ke dalam 4 peran pengguna, menyajikan laporan eksekutif bagi pemilik villa, serta mengintegrasikan layanan prakiraan cuaca terbuka dari BMKG.

---

## Tujuan Proyek

1. Membangun website informasi dan promosi villa yang dinamis dengan arsitektur PHP native dan MySQL tanpa mengubah estetika visual arsitektur Joglo Masayu yang sudah ada.
2. Menyediakan fitur reservasi online bagi pelanggan dengan pemeriksaan ketersediaan jadwal menginap secara otomatis guna mencegah bentrok jadwal.
3. Mengembangkan hak akses terpisah untuk **Pelanggan**, **Pengelola**, **Pemilik**, dan **Admin**.
4. Menyediakan dashboard laporan performa bagi Pemilik yang menghitung estimasi nilai reservasi terkonfirmasi secara transparan.
5. Mengintegrasikan web service BMKG untuk menampilkan prakiraan cuaca di wilayah Ngaglik, Sleman secara real-time dan andal.

---

## Daftar Fitur per Peran Pengguna

| Peran | Fitur Utama |
|---|---|
| **Pengunjung (Publik)** | • Melihat profil, filosofi arsitektur, dan galeri villa.<br>• Melihat detail 3 tipe villa: Joglo Wulung, Joglo Saren, Joglo Agung.<br>• Melihat prakiraan cuaca BMKG dan peta lokasi Google Maps.<br>• Melakukan registrasi akun Pelanggan.<br>• Menghubungi staf melalui tautan langsung WhatsApp. |
| **Pelanggan (Customer)** | • Mengajukan reservasi online (pilihan tipe villa, tanggal, jumlah tamu, catatan).<br>• Melihat kalkulasi estimasi tarif menginap real-time (weekday, weekend, extra bed).<br>• Memantau riwayat dan status permohonan reservasi miliknya (terproteksi IDOR). |
| **Pengelola (Staff)** | • Memantau statistik reservasi masuk.<br>• Menyaring daftar reservasi berdasarkan status (menunggu, dikonfirmasi, ditolak).<br>• Memproses (konfirmasi/tolak) reservasi dengan proteksi transaksi basis data (`FOR UPDATE`).<br>• Melihat identitas pemesan dan catatan khusus tamu. |
| **Pemilik (Owner)** | • Laporan eksekutif ringkas bersifat **Read-Only** (tanpa fitur manipulasi status).<br>• Akumulasi estimasi nilai reservasi terkonfirmasi.<br>• Keterangan wajib: *"estimasi, bukan uang masuk aktual"*, karena pembayaran fisik ditangani langsung oleh pengelola.<br>• Statistik pemesanan per tipe villa dan per periode waktu. |
| **Administrator** | • Membuat akun staf baru (Pengelola dan Pemilik).<br>• Mengaktifkan atau menonaktifkan akun pengguna (akun nonaktif tidak dapat login).<br>• Mengubah peran akun pengguna.<br>• Proteksi keamanan: Admin dicegah menonaktifkan akunnya sendiri. |

---

## Batasan Ruang Lingkup Proyek

1. **Tanpa Pembayaran Online (Payment Gateway):** Sistem tidak memproses transaksi pembayaran perbankan/kartu kredit online. Transaksi kas dan penagihan dilakukan langsung antara tamu dan staf villa (transfer bank manual / kas di tempat).
2. **Estimasi Bukan Bukti Uang Masuk:** Angka total nilai reservasi pada dashboard Pemilik murni merupakan estimasi perhitungan tarif berdasarkan durasi menginap dan tarif snapshot saat pemesanan, bukan catatan kas masuk aktual.
3. **Tanpa Aplikasi Mobile Native:** Aplikasi dibangun berbasis web responsif yang dapat diakses melalui peramban ponsel, tablet, maupun komputer desktop.
4. **Tanpa Uji Beban Berskala Besar (Stress Test):** Pengujian difokuskan pada pengujian fungsional (*black-box*), integritas alur (*white-box*), dan ketahanan keamanan dasar (*SQL Injection*, *XSS*, *CSRF*, dan *IDOR*).

---

## Teknologi yang Digunakan

- **Bahasa Pemrograman:** PHP 8.x Native (tanpa framework Laravel/CodeIgniter).
- **Basis Data:** MySQL / MariaDB (koneksi PDO dengan *prepared statements*).
- **Antarmuka (Frontend):** HTML5 Semantik, CSS3 Murni (Variabel CSS kustom), JavaScript Vanilla ES6+.
- **Web Service Eksternal:** API Prakiraan Cuaca Terbuka BMKG (`api.bmkg.go.id`).
- **Peta & Tipografi:** Google Maps Tertanam (*iframe*) dan Google Fonts (*Playfair Display*, *Lato*, *Kaushan Script*).
- **Version Control:** Git dan GitHub.
- **Metodologi Pengembangan:** Model Waterfall (Analisis Kebutuhan $\rightarrow$ Perancangan $\rightarrow$ Implementasi $\rightarrow$ Pengujian $\rightarrow$ Dokumentasi).

---

## Struktur Folder Proyek

```
website/
├── index.php                  # Beranda utama & widget cuaca BMKG
├── villa.php                  # Detail spesifikasi & foto tipe villa
├── daftar.php                 # Registrasi akun publik khusus peran Pelanggan
├── login.php                  # Halaman masuk untuk 4 peran pengguna
├── logout.php                 # Pembersihan sesi dan keluar akun
├── reservasi.php              # Formulir pengajuan reservasi online
├── reservasi-saya.php         # Riwayat reservasi pelanggan (proteksi IDOR)
├── cuaca.php                  # Halaman informasi prakiraan cuaca BMKG
│
├── dashboard/
│   ├── pengelola.php          # Dashboard pemrosesan reservasi oleh pengelola
│   ├── pemilik.php            # Dashboard laporan eksekutif pemilik (Read-Only)
│   └── admin.php              # Dashboard manajemen akun staf & pengguna oleh admin
│
├── api/
│   ├── villas.php             # Endpoint JSON daftar tipe villa dari database
│   ├── cek-ketersediaan.php   # Endpoint JSON kalkulasi tarif & cek unit tumpang tindih
│   └── cuaca.php              # Perantara cURL cuaca BMKG dengan cache 1 jam
│
├── config/
│   ├── config.example.php     # Template konfigurasi tanpa kredensial sensitif
│   └── config.php             # Konfigurasi aktif lokal (diabaikan oleh Git)
│
├── includes/
│   ├── bootstrap.php          # Inisialisasi sesi aman, konfigurasi, & konstanta
│   ├── db.php                 # Singleton koneksi basis data PDO
│   ├── auth.php               # Otentikasi, otorisasi peran, & helper login
│   ├── csrf.php               # Token & verifikasi proteksi CSRF
│   ├── helpers.php            # Sanitasi e(), format rupiah, tanggal ID, hitung malam
│   └── layout/
│       ├── head.php           # Template pembuka, <base href>, style, & status bar
│       └── foot.php           # Template penutup, modal lightbox, & skrip JS
│
├── assets/
│   ├── css/
│   │   ├── style.css          # Stylesheet visual asli tema Joglo Masayu
│   │   └── app.css            # Stylesheet komponen baru (form, tabel, dashboard)
│   ├── js/
│   │   ├── script.js          # Skrip antarmuka utama (slider, nav, bahasa ID/EN)
│   │   ├── cuaca.js           # Skrip widget cuaca BMKG (render via textContent)
│   │   └── reservasi.js       # Skrip kalkulasi tarif & cek ketersediaan real-time
│   └── images/                # Aset visual: logo.png, logo-icon.png, favicon.png
│
├── database/
│   ├── schema.sql             # Perintah DDL pembuatan tabel & indeks relasional
│   └── seed.sql               # Data awal: 3 tipe villa, akun pengujian, data contoh
│
├── cache/                     # Tempat penyimpanan cache lokal cuaca BMKG (cuaca.json)
├── docs/                      # Dokumentasi komprehensif sistem
├── .htaccess                  # Proteksi keamanan akses direktori sensitif
├── .gitignore                 # Daftar berkas sensitif yang diabaikan Git
└── README.md                  # Dokumentasi utama proyek
```

---

## Petunjuk Instalasi Cepat

1. **Pastikan Prasyarat Terpenuhi:** PHP $\ge$ 8.0 dengan ekstensi `pdo_mysql` dan `curl` aktif.
2. **Buat Database & Impor Data:**
   ```sql
   CREATE DATABASE villa_joglo_masayu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
   Impor berkas:
   - `database/schema.sql`
   - `database/seed.sql`
3. **Salin Berkas Konfigurasi:**
   ```bash
   cp config/config.example.php config/config.php
   ```
   Sesuaikan `DB_USER`, `DB_PASS`, dan `APP_URL` pada `config/config.php`.
4. **Jalankan Aplikasi:**
   - Melalui XAMPP/Laragon di folder `htdocs`/`www`.
   - Atau melalui PHP built-in server:
     ```bash
     php -S localhost:8000
     ```
   - Buka `http://localhost:8000/index.php`.

---

## Akun Awal untuk Pengujian (Seed)

| Peran | Email | Kata Sandi | Tujuan Pengujian |
|---|---|---|---|
| **Admin** | `admin@joglomasayu.com` | `AdminMasayu2026!` | Menguji manajemen akun staf di `dashboard/admin.php` |
| **Pengelola** | `pengelola@joglomasayu.com` | `Pengelola2026!` | Menguji konfirmasi/penolakan reservasi di `dashboard/pengelola.php` |
| **Pemilik** | `pemilik@joglomasayu.com` | `Pemilik2026!` | Menguji laporan read-only dan estimasi nilai di `dashboard/pemilik.php` |
| **Pelanggan** | `budi@gmail.com` | `Pelanggan2026!` | Menguji alur pemesanan dan riwayat reservasi di `reservasi-saya.php` |

*(Pengingat: Ganti kata sandi Administrator saat implementasi di server produksi).*

---

## Ringkasan Pengujian Sistem

Sistem diuji menggunakan 18 skenario pengujian komprehensif (T-01 sampai T-18) yang mencakup:
- **Pengujian Fungsional Black-Box:** T-01 (informasi villa), T-02 (registrasi pelanggan), T-03 (login/logout), T-04 (reservasi valid), T-05 (validasi tanggal & kapasitas), T-06 (unit habis), T-08 (pemrosesan pengelola), T-09 (dashboard pemilik read-only), T-10 (manajemen akun admin).
- **Pengujian Keamanan Dasar:** T-07 (pencegahan IDOR pada reservasi), T-10 (proteksi penonaktifan diri admin), T-11 (ketahanan SQL Injection dengan prepared statements), T-12 (ketahanan XSS dengan sanitasi `e()`).
- **Pengujian Integrasi Layanan Web:** T-13 (pemanggilan sukses BMKG), T-14 (penanganan kegagalan API BMKG dengan pesan alternatif dan cache stale).
- **Pengujian Kompatibilitas Antarmuka:** T-15 (pengujian responsif 360px, 768px, 1024px+).
- **Pengujian Alur Logika White-Box:** T-16 (cabang logika tumpang tindih tanggal), T-17 (jalur verifikasi otorisasi peran), T-18 (formula estimasi tarif campuran).

Rincian skenario pengujian lengkap tercatat pada [`docs/RENCANA_UJI.md`](docs/RENCANA_UJI.md).

---

## Sumber Data & Atribusi Cuaca

Layanan cuaca mengintegrasikan data resmi dari **Badan Meteorologi, Klimatologi, dan Geofisika (BMKG)** melalui API Terbuka Prakiraan Cuaca Wilayah Tingkat IV:
- Dokumentasi Resmi: `https://data.bmkg.go.id/prakiraan-cuaca/`
- Endpoint API: `https://api.bmkg.go.id/publik/prakiraan-cuaca?adm4=34.04.05.2003`
- Hak cipta data milik BMKG. Seluruh tampilan widget cuaca wajib dan telah mencantumkan atribusi *"Sumber: BMKG"*.

---

## Catatan Keamanan Dasar

1. **Prepared Statements PDO:** Seluruh manipulasi dan pembacaan SQL menggunakan parameter terikat (*bound parameters*) untuk menolak upaya *SQL Injection*.
2. **Sanitasi Output Anti-XSS:** Seluruh data dinamis yang ditampilkan ke antarmuka di-escape menggunakan fungsi `e()` (`htmlspecialchars` dengan flag `ENT_QUOTES`). Skrip JavaScript cuaca merender teks murni dengan properti `.textContent`.
3. **Perlindungan CSRF:** Setiap formulir yang mengirimkan metode POST memuat token acak aman yang divalidasi oleh fungsi `csrf_verify()`.
4. **Manajemen Sesi Aman:** Cookie sesi diatur dengan atribut `HttpOnly`, `SameSite=Lax`, dan regenerasi ID sesi (`session_regenerate_id(true)`) dilakukan setiap kali pengguna berhasil login.
5. **Proteksi Akses Direktori:** Berkas `.htaccess` menolak akses langsung peramban ke direktori internal `config/`, `includes/`, `database/`, `cache/`, dan `docs/`.

---

## Status Pengembangan & Daftar TODO

Sistem telah menyelesaikan seluruh target implementasi fungsional F-01 sampai F-14 sesuai dengan proposal Proyek 3.
Berikut adalah catatan penyesuaian (*TODO*) untuk tahap serah terima akhir bersama pengelola villa:
- [ ] **TODO-01 (Kapasitas Fisik Unit):** Mengonfirmasi jumlah unit riil masing-masing tipe villa di lapangan (saat ini diset *placeholder* 1 unit per tipe villa).
- [ ] **TODO-02 (Nomor WhatsApp Operasional):** Mengganti nomor WhatsApp contoh (`6281234567890`) dengan nomor telepon kontak resmi staf reservasi Joglo Masayu.
- [ ] **TODO-03 (Foto Asli Bangunan):** Mengganti foto placeholder Unsplash dengan dokumentasi foto resolusi tinggi bangunan kayu asli Joglo Masayu.
- [ ] **TODO-04 (Ganti Kata Sandi Admin):** Mengubah kata sandi awal akun Administrator pada database produksi.

---

## Tautan Dokumen Pendukung

- [Panduan Instalasi Lengkap](docs/PANDUAN_INSTALASI.md)
- [Panduan Penggunaan per Peran](docs/PANDUAN_PENGGUNAAN.md)
- [Struktur & Kamus Basis Data](docs/STRUKTUR_DATABASE.md)
- [Rencana & Skenario Pengujian (T-01 s.d. T-18)](docs/RENCANA_UJI.md)
- [Dokumentasi Perubahan Frontend](docs/PERUBAHAN_FRONTEND.md)
- [Panduan Integrasi Manual](docs/INTEGRASI_MANUAL.md)
- [Sinkronisasi & Penyelarasan Proposal](docs/SINKRONISASI_PROPOSAL.md)
