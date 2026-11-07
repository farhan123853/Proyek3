# Villa Joglo Masayu

Website reservasi **Villa Joglo Masayu** — villa joglo premium dengan arsitektur Jawa autentik di Sleman, D.I. Yogyakarta.

**Proyek 3 — D4 Teknik Informatika, Universitas Logistik dan Bisnis Internasional (ULBI)**

## Tech Stack

- PHP 8.0+ (native, tanpa framework)
- MySQL / MariaDB (via PDO)
- HTML, CSS, JavaScript vanilla
- BMKG API (prakiraan cuaca)

## Struktur Proyek

```
website/
├── index.html              # Landing page (frontend statis)
├── villa.html              # Detail villa (frontend statis)
├── script.js               # JS utama (header/footer injection, data villa)
├── style.css               # Stylesheet utama
├── images/                 # Aset gambar (logo, favicon)
│
├── daftar.php              # Halaman registrasi pengguna
├── login.php               # Halaman login
├── logout.php              # Proses logout
├── reservasi.php           # Form reservasi villa
├── reservasi-saya.php      # Riwayat reservasi pengguna
├── cuaca.php               # Info cuaca dari BMKG API
├── dashboard-pengelola.php # Dashboard pengelola villa
├── dashboard-pemilik.php   # Dashboard pemilik villa
├── dashboard-admin.php     # Dashboard admin sistem
│
├── config/                 # Konfigurasi database & aplikasi
├── includes/               # Helper & fungsi PHP reusable
├── api/                    # Endpoint API internal
├── database/               # Migrasi & seed SQL
├── assets/
│   ├── css/app.css         # CSS tambahan untuk halaman PHP
│   └── js/                 # JS tambahan per halaman
└── docs/                   # Dokumentasi proyek
```

## Instalasi

Lihat [docs/PANDUAN_INSTALASI.md](docs/PANDUAN_INSTALASI.md).

## Verifikasi Frontend

Pastikan file frontend asli tidak berubah:

```bash
sha256sum -c docs/frontend.sha256
```

## Lisensi

Hak cipta © 2024 Joglo Masayu. Untuk keperluan akademik.
