# AUDIT LANJUTAN SISTEM VILLA JOGLO MASAYU
**Tahap 0: Baseline & Audit Arsitektur Sebelum Migrasi Tailwind CSS**
*Tanggal Audit: 8 November 2026*

---

## 1. Daftar Halaman PHP dan Tujuannya

| No | File Halaman | Peran Target / Akses | Tujuan & Deskripsi Fungsional |
|---|---|---|---|
| 1 | `index.php` | Publik / Guest & Autentikasi | Beranda utama: Menampilkan identitas Joglo, Hero Slider, Tipe Villa, Fasilitas Unggulan, Widget Cuaca BMKG, Galeri Foto, Peta Lokasi, dan Formulir Kontak. |
| 2 | `villa.php` | Publik / Guest & Autentikasi | Detail Tipe Villa: Informasi spesifikasi unit (`joglo-wulung`, `joglo-saren`, `joglo-agung`), galeri foto spesifik, kapasitas standar/maks, tarif weekday/weekend/extrabed, fasilitas lengkap, dan CTA Reservasi. |
| 3 | `login.php` | Tamu Belum Login | Autentikasi Terpadu: Form login multi-peran (Pelanggan, Pengelola, Pemilik, Admin) menggunakan email & password, dilengkapi proteksi CSRF dan rate limiting percobaan login. |
| 4 | `daftar.php` | Tamu Belum Login | Registrasi Akun Pelanggan: Form pembuatan akun baru khusus pelanggan (nama lengkap, email, nomor HP/WhatsApp, kata sandi). Otomatis login setelah pendaftaran berhasil. |
| 5 | `logout.php` | Semua Peran Login | Terminasi Sesi: Menghapus session autentikasi pengguna secara aman, meregenerasi session ID, menyimpan flash message keluar, dan mengarahkan kembali ke beranda. |
| 6 | `reservasi.php` | Publik / Pelanggan | Formulir Reservasi Interaktif: Pilihan tipe villa, tanggal check-in & check-out, jumlah tamu dewasa/anak, opsi extra bed, kalkulasi tarif real-time (weekday vs weekend), dan penyimpanan data pemesanan. |
| 7 | `reservasi-saya.php` | Pelanggan (`pelanggan`) | Riwayat Reservasi Tamu: Daftar pemesanan aktif dan lampau milik pelanggan yang sedang login, status reservasi (`menunggu`, `dikonfirmasi`, `selesai`, `dibatalkan`), rincian biaya, dan aksi pembatalan. |
| 8 | `dashboard/pengelola.php` | Pengelola (`pengelola`) | Panel Operasional Penginapan: Manajemen data reservasi harian, pembaruan status pemesanan tamu, filter periode, dan monitoring keterisian kamar. |
| 9 | `dashboard/pemilik.php` | Pemilik (`pemilik`) | Panel Eksekutif & Finansial: Laporan metrik okupansi, total omzet/pendapatan terkonfirmasi, grafik tren bulanan, dan rekapitulasi data reservasi selesai. |
| 10 | `dashboard/admin.php` | Administrator (`admin`) | Panel Administrasi Teknis: Manajemen master akun pengguna (Pelanggan, Pengelola, Pemilik, Admin), reset kata sandi sementara, monitoring audit trail aktivitas sistem. |
| 11 | `cuaca.php` | Publik / Semua Pengguna | Informasi Cuaca Lengkap: Menampilkan rincian prakiraan cuaca 3 hari kawasan Villa Joglo Masayu yang bersumber dari API BMKG. |

---

## 2. Struktur Peran dan Hak Akses Pengguna

Aplikasi menerapkan kontrol akses berbasis peran (*Role-Based Access Control* - RBAC) melalui session `$_SESSION['user']['role']` yang divalidasi oleh fungsi `require_role()` dan `require_login()` di `includes/auth.php`:

```
+-----------------------------------------------------------------------------------+
| Pengguna Tamu (Guest)                                                             |
| - Akses: index.php, villa.php, cuaca.php, login.php, daftar.php, reservasi.php    |
+-----------------------------------------------------------------------------------+
                                          | Login
+-----------------------------------------v-----------------------------------------+
| Pengguna Terautentikasi (Authenticated Users)                                      |
+-----------------------------------------------------------------------------------+
  |--> Role 'pelanggan':
  |    - Mengakses reservasi-saya.php
  |    - Mengisi reservasi dengan auto-fill data profil
  |    - Membatalkan reservasi sendiri yang masih berstatus 'menunggu'
  |    - Ditolak (403) jika mencoba membuka /dashboard/*
  |
  |--> Role 'pengelola':
  |    - Mengakses dashboard/pengelola.php
  |    - Menyetujui/mengonfirmasi reservasi ('menunggu' -> 'dikonfirmasi')
  |    - Menyelesaikan reservasi ('selesai') atau membatalkan ('dibatalkan')
  |
  |--> Role 'pemilik':
  |    - Mengakses dashboard/pemilik.php
  |    - Melihat ringkasan pendapatan, okupansi, dan grafik finansial eksekutif
  |
  |--> Role 'admin':
  |    - Mengakses dashboard/admin.php
  |    - Manajemen penuh akun pengguna (tambah, edit peran, reset password sementara)
  |    - Melihat catatan log aktivitas sistem
```

---

## 3. Daftar Class CSS yang Sering Dipakai (style.css & auth.css)

### A. Token Warna & Tipografi
- **Warna Utama**:
  - `--cream`: `#FAF3E3` (Latar belakang utama bernuansa hangat)
  - `--teak`: `#3B2416` (Kayu jati tua / teks utama)
  - `--wood`: `#6B4A2F` (Aksen kayu / teks sekunder)
  - `--sand`: `#EFE0C0` (Warna pasir / border lembut)
  - `--gold`: `#E3A600` (Emas hangat / tombol CTA utama)
  - `--gold-dark`: `#B88400` (Emas gelap / hover state)
  - `--green-dark`: `#2F4F3E` (Hijau daun tua Jawa / aksen alam)
  - `--white`: `#FFFFFF`
- **Tipografi**:
  - `--font-display`: `'Playfair Display', Georgia, serif` (Judul dan heading berkarakter klasik)
  - `--font-body`: `'Lato', -apple-system, sans-serif` (Teks konten umum)
  - `--font-script`: `'Kaushan Script', cursive` (Aksen tradisional Jawa)

### B. Komponen dan Class Tata Letak
- **Header & Navigasi**:
  - `.header-inner`: Tata letak 3-zona (grid `auto 1fr auto`) yang sejajar presisi.
  - `.header-brand`, `.header-logo-img`, `.brand-title`, `.brand-sub`: Identitas logo Joglo.
  - `.header-nav`, `.nav-link`: Navigasi utama dengan garis bawah animasi emas.
  - `.header-actions`: Grup aksi header (bahasa switcher, user chip, tombol reservasi).
  - `.user-chip`, `.user-avatar`, `.user-name`, `.user-role-badge`: Chip pengguna login.
  - `.user-menu-dropdown`, `.user-menu-item`: Dropdown menu akun responsif.
  - `.hamburger`, `.mobile-nav`, `.mobile-nav-link`: Navigasi layar sentuh mobile.
- **Formulir & Kartu**:
  - `.form-card`: Kotak kartu bergaya kayu halus dengan bayangan lembut dan sudut membulat.
  - `.form-group`, `.form-label`, `.form-control`: Elemen input form konsisten.
  - `.btn`, `.btn-gold`, `.btn-ghost`: Tombol aksi primer (emas) dan sekunder (outline kayu).
  - `.badge`, `.badge-gold`, `.badge-green`, `.badge-wood`: Label status kecil.
  - `.alert`, `.alert-danger`, `.alert-success`: Kotak pesan sistem.
- **Komponen Beranda & Villa**:
  - `.hero-slider`, `.hero-slide`, `.hero-content`: Banner beranda dengan slider otomatis.
  - `.card-villa`, `.villa-thumb`, `.villa-meta`: Kartu presentasi tipe villa.
  - `.lightbox`, `.lightbox-content`: Tampilan pratinjau gambar layar penuh.
  - `.weather-card`, `.weather-temp`, `.weather-desc`: Kartu prakiraan cuaca.
- **Animasi**:
  - `@keyframes heroZoom`: Efek Ken Burns lembut pada gambar latar slider.
  - `@keyframes fadeIn`: Kemunculan transisi halus untuk dropdown dan modal.

---

## 4. Format Data yang Dikembalikan oleh API

### A. `api/villas.php`
- **Metode**: `GET`
- **Format Respon**: `application/json`
- **Struktur Payload**:
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "nama": "Joglo Wulung",
      "slug": "joglo-wulung",
      "kapasitas_standar": 2,
      "kapasitas_maks": 3,
      "harga_weekday": 1500000,
      "harga_weekend": 2000000,
      "harga_extrabed": 350000,
      "jumlah_unit": 2,
      "deskripsi": "Villa Joglo bernuansa kayu hitam eksotis...",
      "fasilitas": ["King Bed", "Private Pool", "WiFi 50 Mbps", "AC", "Bathtub"],
      "gambar_utama": "images/wulung-1.jpg",
      "galeri": ["images/wulung-1.jpg", "images/wulung-2.jpg"]
    }
  ]
}
```

### B. `api/cuaca.php`
- **Metode**: `GET`
- **Format Respon**: `application/json`
- **Struktur Payload**:
```json
{
  "status": "success",
  "sumber": "Badan Meteorologi, Klimatologi, dan Geofisika (BMKG)",
  "lokasi": "Villa Joglo Masayu, Yogyakarta",
  "terakhir_diperbarui": "2026-11-08 12:00:00",
  "prakiraan": [
    {
      "waktu": "2026-11-08 12:00",
      "cuaca": "Cerah Berawan",
      "suhu": 31,
      "kelembapan": 65,
      "ikon": "cerah-berawan.svg"
    }
  ]
}
```

### C. `api/cek-ketersediaan.php`
- **Metode**: `GET` / `POST`
- **Parameter**: `tipe_villa_id`, `check_in`, `check_out`
- **Format Respon**: `application/json`
- **Struktur Payload**:
```json
{
  "status": "success",
  "tersedia": true,
  "unit_tersedia": 2,
  "unit_terisi": 0,
  "total_unit": 2,
  "pesan": "Kamar tersedia untuk tanggal yang dipilih."
}
```

---

## 5. Lokasi Teks Dwibahasa Saat Ini

- **Klien (Frontend)**:
  - Tersimpan di objek JavaScript `TRANSLATIONS = { id: { ... }, en: { ... } }` di dalam file `assets/js/script.js`.
  - Pergantian bahasa dijalankan di sisi browser melalui pemindaian atribut DOM `data-i18n="key"` dan `data-i18n-attr="placeholder:key"`.
  - Preferensi disimpan di `localStorage.getItem('preferredLanguage')`.
- **Backend (PHP)**:
  - Teks antarmuka di file template PHP (`login.php`, `daftar.php`, `reservasi.php`, `dashboard/*`) saat ini masih berupa string statis Bahasa Indonesia yang di-hardcode dalam markup HTML.
  - Belum ada kamus terpusat di sisi server (`lang/id.php` dan `lang/en.php`) dan helper terpadu `t('kunci')`.
  - Sinkronisasi status bahasa antara backend cookie `masayu_lang` dan `localStorage` belum terhubung sepenuhnya.

---

## 6. Identifikasi Celah Visual dan UX yang Ada Sekarang

1. **Umpan Balik Notifikasi (Toast vs Banner)**:
   - Pesan feedback saat ini memakai parameter URL query string seperti `?keluar=1` atau `?pesan=sukses` yang merender kotak peringatan statis di atas form.
   - Peringatan ini mendesak dan menggeser layout halaman (*layout shift*). Dibutuhkan komponen Toast terapung (`#toast-region`) di pojok layar dengan animasi mulus dan waktu dismiss otomatis.
2. **Visualisasi Pemilihan Tanggal Reservasi**:
   - Pemilihan tanggal check-in dan check-out di `reservasi.php` menggunakan input teks/date standar HTML5 tanpa indikasi tanggal mana yang sudah habis dipesan (*fully booked*). Tamu baru mengetahui ketersediaan setelah menekan tombol atau memilih tanggal.
   - Diperlukan kalender interaktif visual 2 bulan (desktop) / 1 bulan (mobile) yang secara jelas memberi warna abu-abu pada malam yang penuh.
3. **Konfirmasi Reservasi & Akses WhatsApp**:
   - Setelah membuat reservasi, tamu belum memiliki tautan cepat langsung untuk menghubungi pengelola via WhatsApp dengan pesan template yang sudah terisi otomatis (kode reservasi, nama villa, tanggal).
4. **Konteks Prakiraan Cuaca BMKG**:
   - Widget cuaca belum memberikan label batasan jelas bahwa data BMKG hanya mencakup prakiraan hingga 3 hari ke depan. Pemesan untuk 2 minggu ke depan belum mendapatkan deskripsi musim kualitatif (*data/musim.php*).
5. **Reset Kata Sandi Pengguna**:
   - Administrator belum memiliki aksi satu-klik untuk mereset kata sandi pengguna secara acak dengan flag `wajib_ganti_sandi=1` yang memaksa pergantian sandi saat login berikutnya.

---

## 7. Inventaris Screenshot Baseline

Telah diambil 36 screenshot baseline lengkap yang mencakup 4 breakpoint utama (Desktop 1440px, Laptop 1024px, Tablet 768px, Mobile 390px) pada seluruh halaman dan kondisi peran di direktori `docs/baseline/`:

1. `index_desktop-1440_guest.png`, `index_laptop-1024_guest.png`, `index_tablet-768_guest.png`, `index_mobile-390_guest.png`
2. `villa_desktop-1440_guest.png`, `villa_laptop-1024_guest.png`, `villa_tablet-768_guest.png`, `villa_mobile-390_guest.png`
3. `login_desktop-1440_guest.png`, `login_laptop-1024_guest.png`, `login_tablet-768_guest.png`, `login_mobile-390_guest.png`
4. `reservasi_desktop-1440_guest.png`, `reservasi_laptop-1024_guest.png`, `reservasi_tablet-768_guest.png`, `reservasi_mobile-390_guest.png`
5. `index-logged-in_*_pelanggan.png` (4 breakpoint)
6. `reservasi-saya_*_pelanggan.png` (4 breakpoint)
7. `dashboard-pengelola_*_pengelola.png` (4 breakpoint)
8. `dashboard-pemilik_*_pemilik.png` (4 breakpoint)
9. `dashboard-admin_*_admin.png` (4 breakpoint)

*Semua screenshot tersimpan utuh dan terverifikasi untuk menjadi standar pembanding paritas visual pada tahap migrasi berikutnya.*
