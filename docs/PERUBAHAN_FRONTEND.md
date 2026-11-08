# Dokumentasi Perubahan Frontend — Villa Joglo Masayu

Dokumen ini mencatat seluruh perubahan pada file frontend statis lama sesuai aturan dan prinsip perubahan yang diizinkan. Tampilan, tata letak, warna, font, animasi, dan fungsi dwibahasa (ID/EN) tetap dipertahankan 100%.

---

## 1. Pemindahan Berkas (Struktur Folder Baru via `git mv`)

| Berkas Asal | Berkas Tujuan | Alasan |
|---|---|---|
| `index.html` | `index.php` | Konversi ke PHP native untuk memuat bootstrap dan session status bar. |
| `villa.html` | `villa.php` | Konversi ke PHP native untuk memuat bootstrap dan widget cuaca. |
| `style.css` | `assets/css/style.css` | Merapikan aset stylesheet ke direktori standar `assets/css/`. Isi tidak diubah. |
| `script.js` | `assets/js/script.js` | Merapikan aset JavaScript ke direktori standar `assets/js/`. |
| `images/*` | `assets/images/*` | Merapikan berkas gambar (logo, favicon) ke direktori `assets/images/`. |

---

## 2. Rincian Perubahan Kode Sumber Awal

### A. `index.php` (Sebelumnya `index.html`)
1. **Bagian `<head>`**: Menambahkan `<base href="<?= BASE_URL ?>">`, link `assets/css/style.css`, `assets/css/app.css`, dan `assets/css/auth.css`. Menambahkan injeksi variabel JavaScript `window.MASAYU_AUTH` sebelum `script.js`.
2. **Kontainer Cuaca BMKG**: Menambahkan `<section id="cuaca">` untuk merender widget BMKG Sleman.
3. **Penutup `<body>`**: Memuat `assets/js/script.js` dan `assets/js/cuaca.js`.

### B. `villa.php` (Sebelumnya `villa.html`)
1. **Bagian `<head>`**: Penyesuaian path aset, `<base href>`, dan `window.MASAYU_AUTH`.
2. **Kontainer Cuaca**: Menambahkan `#weatherWidgetContainer` di dalam main.
3. **Penutup `<body>`**: Memuat skrip aset `script.js` dan `cuaca.js`.

### C. `assets/js/script.js`
1. **Penyediaan Sumber Data Dinamis dari API (Baris ~328)**: Data master villa dimuat dinamis dari `api/villas.php` dengan fallback ke `VILLAS`.
2. **Arah Tombol Pesan Sekarang**: Diarahkan dinamis ke `reservasi.php` (bila login) atau `login.php?lanjut=reservasi.php` (bila belum login).
3. **Penyelarasan Tautan**: Mengubah tautan `.html` ke `.php` dan folder aset ke `assets/`.

---

## 3. Perbaikan Total Tata Letak Header (State Login & Responsif)

### A. Penyebab Masalah Awal (Hasil Diagnosis)
1. **Tabrakan Logo dengan Sapaan**: Blok `.user-status-bar` dicetak langsung oleh PHP di bawah `#site-header` dengan flow normal di `top: 0`, sehingga menumpuk tepat di atas `.header-logo` yang berposisi `fixed`.
2. **Navigasi Melorot**: `.header-inner` menggunakan flexbox tanpa penguncian tinggi baris (`--header-h`) dan perataan vertikal terpadu.
3. **Tombol Keluar Menimpa ID | EN**: Tautan `.user-status-links` di sisi kanan `.user-status-bar` bertumpuk langsung di atas `.lang-switch`.
4. **Tombol Pesan Sekarang Meluap**: Ukuran padding dan line-height tombol tidak terkunci pada variabel kontrol `--control-h: 40px`, sehingga menembus batas bawah header.
5. **Header Menyusut Terlalu Sempit**: `--header-h-scrolled: 62px` di `style.css` membuat isi meluap.
6. **Tautan Dashboard Tidak Sejajar**: Berada di luar kontainer header utama.

### B. Tindakan Perbaikan yang Dilakukan

1. **Penghapusan `.user-status-bar` dari Layout**:
   - `index.php` dan `villa.php`: Blok `.user-status-bar` dihapus total dari body.
   - `includes/layout/head.php`: Dihapus dan digantikan oleh Chip Pengguna terpadu di dalam header.
   - `assets/css/auth.css`: Diberikan aturan `.user-status-bar { display: none !important; }`.

2. **Penerapan Grid 3-Zona pada `.header-inner`**:
   - `display: grid !important; grid-template-columns: auto minmax(0, 1fr) auto !important; align-items: center !important; column-gap: 24px !important; height: var(--header-h) !important;`
   - **Zona Kiri**: Hanya logo (`.header-logo`, gambar maks 44px + "Joglo Masayu").
   - **Zona Tengah**: Navigasi (`.header-nav-wrap`, `display: flex; justify-content: center;`).
   - **Zona Kanan**: `.header-actions` (`display: flex; align-items: center; gap: 12px;`).
     - State Belum Login: `[ID|EN]` `[Masuk]` `[Pesan Sekarang]`
     - State Sudah Login: `[ID|EN]` `[Chip Pengguna (Avatar + Nama)]` `[Pesan Sekarang]`

3. **Standarisasi Variabel Ukuran**:
   - Desktop: `--header-h: 72px;` (tetap stabil baik di state transparan maupun scrolled), `--control-h: 40px;`.
   - Mobile (< 900px): `--header-h: 64px;`, `--control-h: 44px;`.
   - Seluruh elemen kontrol di zona kanan terkunci pada `height: var(--control-h);`, menghasilkan perbedaan pusat vertikal **0.0 px** dari pusat header.

4. **Komponen Chip Pengguna & Dropdown**:
   - Avatar bulat 32px berisi inisial nama pengguna.
   - Nama pengguna dengan pemotongan teks rapi (`max-width: 140px; text-overflow: ellipsis;`).
   - Dropdown memuat: "Halo, {nama}" + badge peran, pemisah, tautan "Dashboard / Reservasi saya", pemisah, dan tombol "Keluar".
   - Logout menggunakan formulir POST ke `logout.php` dengan token CSRF (`window.MASAYU_AUTH.csrf`).
   - Aksesibilitas keyboard penuh: Enter/Space membuka, Esc menutup dan mengembalikan fokus, panah atas/bawah memilih item, klik luar menutup.
   - Proteksi XSS: Pengisian nama menggunakan `textContent`, bukan `innerHTML`.

5. **Responsif & Mobile Menu Overlay**:
   - Layar 900–1199 px: Nama pada chip disembunyikan (hanya avatar), nav dirapatkan dengan `clamp()`.
   - Layar < 900 px: Nav desktop dan zona kanan disembunyikan; tampil logo + tombol hamburger 44x44 px. Menu overlay memuat kartu pengguna/login di atas, tautan navigasi besar (>= 48px), pemilih bahasa, tombol Pesan Sekarang, dan tombol Keluar di paling bawah dengan dukungan `env(safe-area-inset-bottom)`. Menu tertutup memiliki `display: none !important;` sehingga tidak menimbulkan tumpang tindih tak terlihat.

6. **Perbaikan Notifikasi Logout**:
   - `includes/auth.php`: `logout_user()` membersihkan data autentikasi dan meregenerasi session ID tanpa menghapus cookie session flash.
   - `logout.php`: Memverifikasi CSRF, logout, menyetel notifikasi `"Anda sudah keluar."`, dan mengarahkan ke `index.php`.

---

## 4. Hasil Pengujian Otomatis di Browser (Playwright)

Pengujian dijalankan pada 11 ukuran viewport (1600, 1440, 1280, 1100, 1024, 900, 768, 390, 360, 320 px, dan landscape ponsel 844x390 px) melintasi 5 peran akun (Admin, Pengelola, Pemilik, Pelanggan Nama Panjang "Abdullah Averouse Ibnu Sina", dan Belum Login) serta 2 status header (Transparan & Scrolled Solid):

| Kategori Pemeriksaan | Kriteria Evaluasi | Target | Hasil Pengujian | Status |
|---|---|---|---|---|
| **1. Tumpang Tindih Interaktif** | Tidak ada elemen `<a>` atau `<button>` yang bertabrakan bounding box-nya | 0 tabrakan | **0 tabrakan** (dari 110 pengujian) | **LULUS** |
| **2. Batas Bawah Header** | Elemen tidak melewati batas bawah header (`rect.bottom <= header.bottom`) | 0 overflow | **0 overflow** (semua elemen muat) | **LULUS** |
| **3. Presisi Vertikal** | Pusat vertikal elemen zona kanan berbeda <= 1.0 px dari pusat header | Selisih <= 1.0 px | **Selisih 0.0 px** | **LULUS** |
| **4. Scroll Horizontal** | `document.documentElement.scrollWidth <= window.innerWidth` | Bebas scroll horisontal | **Lolos di seluruh lebar (termasuk 320px)** | **LULUS** |
| **5. Ukuran Kontrol Mobile** | Area sentuh hamburger dan tombol mobile >= 44 x 44 px | Min 44x44 px | **Hamburger 44x44 px, link mobile >= 48 px** | **LULUS** |
| **6. Ellipsis Nama Panjang** | Nama panjang ("Abdullah Averouse Ibnu Sina") tidak merusak layout | Teks terpotong elipsis | **Terpotong rapi dengan ellipsis (maks 140px)** | **LULUS** |
| **7. Aksesibilitas Keyboard** | Buka Enter/Space, Esc menutup dan fokus kembali ke pemicu | Fungsi keyboard valid | **Enter membuka, Esc menutup & restore focus** | **LULUS** |
| **8. Alur Logout Aman** | POST + CSRF redirect ke `index.php` dengan pesan "Anda sudah keluar." | Status notifikasi valid | **Pesan "Anda sudah keluar." tampil di beranda** | **LULUS** |

**Total Pengujian**: 110 skenario kombinasi | **Lulus**: 110 | **Gagal**: 0 (100% Lulus).

---

## 5. Berkas Bukti Tangkapan Layar (`docs/screenshots/`)

- `sebelum_desktop_1600px.png`: Kondisi sebelum perbaikan pada resolusi desktop 1600 px (terlihat tabrakan logo, KELUAR, ID|EN).
- `sebelum_mobile_390px.png`: Kondisi sebelum perbaikan pada resolusi mobile 390 px.
- `sesudah_desktop_1600px_admin.png`: Header presisi desktop 1600 px state transparan (Admin).
- `sesudah_desktop_1600px_scrolled.png`: Header presisi desktop 1600 px state solid scrolled.
- `sesudah_desktop_1280px_pelanggan_longname.png`: Tampilan pelanggan dengan nama panjang terpotong elipsis rapi.
- `sesudah_tablet_1100px.png`: Tampilan tablet 1100 px (chip mode avatar, nav rapat).
- `sesudah_tablet_768px.png`: Tampilan tablet 768 px mode mobile header.
- `sesudah_mobile_390px.png`: Tampilan mobile 390 px header bersih dengan hamburger 44x44 px.
- `sesudah_mobile_390px_menu_open.png`: Menu mobile terbuka lengkap dengan kartu profil, nav, bahasa, pesan, dan tombol keluar di dasar panel.
- `sesudah_mobile_320px.png`: Uji perangkat layar kecil 320 px (tanpa scroll horizontal).
- `sesudah_mobile_landscape_844x390.png`: Uji tampilan ponsel orientasi horizontal.
