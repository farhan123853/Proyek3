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

## 2. Rincian Perubahan Kode Sumber

### A. `index.php` (Sebelumnya `index.html`)

1. **Baris 1–5**
   - **Sebelum:**
     ```html
     <!DOCTYPE html>
     <html lang="id" dir="ltr">
     ```
   - **Sesudah:**
     ```php
     <?php
     require_once __DIR__ . '/includes/bootstrap.php';
     $pageTitle = 'Joglo Masayu — Villa Joglo Premium di Jawa';
     $pageDesc  = 'Joglo Masayu menawarkan pengalaman menginap premium di villa joglo kayu jati autentik. Rasakan ketenangan dan keindahan arsitektur Jawa yang sesungguhnya.';
     ?>
     <!DOCTYPE html>
     <html lang="id" dir="ltr">
     ```
   - **Alasan:** Memuat modul inisialisasi aplikasi, sesi aman, dan helper PHP.

2. **Bagian `<head>` (Baris 15–25)**
   - **Sebelum:**
     ```html
     <link rel="icon" type="image/png" href="images/logo.png">
     ...
     <link rel="stylesheet" href="style.css" />
     ```
   - **Sesudah:**
     ```html
     <base href="<?= BASE_URL ?>">
     <link rel="icon" type="image/png" href="assets/images/logo.png">
     ...
     <link rel="stylesheet" href="assets/css/style.css" />
     <link rel="stylesheet" href="assets/css/app.css" />
     ```
   - **Alasan:** Menambahkan `<base href>` untuk mengatasi masalah path relatif pada subfolder `dashboard/` serta menyesuaikan path folder aset baru.

3. **Setelah `#site-header`**
   - **Sebelum:** Langsung ke tombol `#btnBackToTop`.
   - **Sesudah:** Menambahkan bar status pengguna yang sedang login (`.user-status-bar`) dan container notifikasi (`.flash-container`).
   - **Alasan:** Menyediakan informasi akun aktif dan navigasi dashboard bagi pengguna yang telah masuk (login).

4. **Sebelum Bagian Galeri (Baris 180)**
   - **Sebelum:** Tidak ada widget cuaca di beranda.
   - **Sesudah:**
     ```html
     <!-- ===== PRAKIRAAN CUACA BMKG ===== -->
     <section id="cuaca" class="section-cuaca" style="background: var(--cream); padding-block: 3.5rem;">
       <div class="container">
         <div id="weatherWidgetContainer"></div>
       </div>
     </section>
     ```
   - **Alasan:** Kebutuhan F-02 & F-03 untuk menampilkan prakiraan cuaca lokasi villa dari BMKG.

5. **Penutup `<body>`**
   - **Sebelum:** `<script src="script.js" defer></script>`
   - **Sesudah:**
     ```html
     <script src="assets/js/script.js" defer></script>
     <script src="assets/js/cuaca.js" defer></script>
     ```
   - **Alasan:** Penyesuaian path berkas `script.js` dan pemuatan skrip widget cuaca BMKG.

---

### B. `villa.php` (Sebelumnya `villa.html`)

1. **Baris 1–5 & `<head>`**
   - **Sebelum:** Format HTML murni dengan tautan `images/logo.png` dan `style.css`.
   - **Sesudah:** Dimulai dengan `require_once __DIR__ . '/includes/bootstrap.php';`, `<base href="<?= BASE_URL ?>">`, tautan `assets/images/logo.png`, `assets/css/style.css`, dan `assets/css/app.css`.
   - **Alasan:** Integrasi backend PHP, base path, dan style komponen sistem.

2. **Di dalam kontainer `<main>`**
   - **Sebelum:** Hanya `#villaDetailContent`.
   - **Sesudah:** Ditambahkan kontainer widget cuaca:
     ```html
     <div class="container" style="margin-top: 1.5rem; margin-bottom: 2.5rem;">
       <div id="weatherWidgetContainer"></div>
     </div>
     ```
   - **Alasan:** Kebutuhan F-02 menampilkan perkiraan cuaca di halaman detail villa.

3. **Penutup `<body>`**
   - **Sebelum:** `<script src="script.js" defer></script>`
   - **Sesudah:**
     ```html
     <script src="assets/js/script.js" defer></script>
     <script src="assets/js/cuaca.js" defer></script>
     ```
   - **Alasan:** Penyesuaian lokasi path aset dan pemuatan modul cuaca BMKG.

---

### C. `assets/js/script.js`

1. **Penyediaan Sumber Data Dinamis dari API (Baris ~328)**
   - **Sebelum:** Menggunakan array lokal konstan `VILLAS`.
   - **Sesudah:** Menambahkan variabel `activeVillas = VILLAS;` dan fungsi asynchronous `loadVillasFromApi()` yang memanggil `api/villas.php`.
   - **Alasan:** Kebutuhan F-14: data master villa dimuat dinamis dari database MySQL, dengan array `VILLAS` tetap dipertahankan utuh sebagai cadangan (fallback).

2. **Tombol "Pesan Sekarang" Header & Mobile (Baris ~405–410, ~471, ~488)**
   - **Sebelum:**
     `headerPesan.href = waUrl;` (diarahkan ke WhatsApp).
   - **Sesudah:**
     `headerPesan.href = 'reservasi.php';` dan `mobilePesan.href = 'reservasi.php';`.
   - **Alasan:** Kebutuhan F-06 / aturan 2e: Tombol "Pesan Sekarang" diarahkan ke halaman formulir reservasi online internal website. Tombol WhatsApp lain tetap dipertahankan.

3. **Tombol "Pesan sekarang" pada Halaman Detail Villa (Baris ~1230)**
   - **Sebelum:**
     `<a href="${waUrl}${waMsg}" class="btn btn-ghost" target="_blank" rel="noopener" data-i18n="villa.book">${t('villa.book')}</a>`
   - **Sesudah:**
     `<a href="reservasi.php?villa=${encodeURIComponent(villa.slug)}" class="btn btn-ghost" data-i18n="villa.book">${t('villa.book')}</a>`
   - **Alasan:** Aturan 2e: diarahkan ke formulir reservasi online dengan membawa parameter villa yang dipilih. Tombol "Tanya via WhatsApp" di sampingnya (`${waUrl}${waAsk}`) tetap utuh.

4. **Tautan Navigasi & Logo (Baris ~438, ~454, ~503, ~513–516)**
   - **Sebelum:** Menunjuk ke `index.html`, `index.html#tentang`, `images/logo.png`.
   - **Sesudah:** Menunjuk ke `index.php`, `index.php#tentang`, `assets/images/logo.png`.
   - **Alasan:** Penyelarasan dengan ekstensi berkas PHP dan lokasi baru folder gambar.

5. **Tautan Detail Villa (Baris ~950, ~1147)**
   - **Sebelum:** `villa.html?id=${villa.slug}`
   - **Sesudah:** `villa.php?id=${villa.slug}`
   - **Alasan:** Penyelarasan navigasi ke berkas detail villa berbasis PHP.

6. **Inisialisasi `DOMContentLoaded` (Baris ~1340)**
   - **Sebelum:** Langsung memanggil inisialisasi antarmuka.
   - **Sesudah:** Menjalankan `await loadVillasFromApi()` sebelum merender daftar villa.
   - **Alasan:** Memastikan data villa termutakhir dari basis data telah siap sebelum dirender ke DOM.
