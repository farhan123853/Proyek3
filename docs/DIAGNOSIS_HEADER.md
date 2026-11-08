# Laporan Diagnosis Masalah Tata Letak Header (State Login)

Dokumen ini memuat analisis mendalam terkait penyebab kerusakan tata letak header saat pengguna masuk (login) sebagai Admin pada resolusi desktop 1600 px dan mobile 390 px, sebelum dilakukan perbaikan struktur dan CSS.

---

## 1. Bukti Visual Sebelum Perbaikan
Tangkapan layar kondisi awal sebelum perbaikan disimpan di:
- **Desktop 1600 px**: `docs/screenshots/sebelum_desktop_1600px.png`
- **Mobile 390 px**: `docs/screenshots/sebelum_mobile_390px.png`

---

## 2. Struktur DOM Header Saat Ini

Pada berkas `index.php` dan `villa.php`, struktur DOM bagian atas halaman tersusun sebagai berikut:

```html
<!-- Kontainer header dinamis yang diinjeksi oleh script.js -->
<div id="site-header">
  <header class="site-header is-transparent" id="siteHeader" role="banner">
    <div class="header-inner">
      <a href="index.php" class="header-logo">...</a>
      <nav aria-label="Navigasi utama">
        <ul class="header-nav" id="headerNav">...</ul>
      </nav>
      <div class="header-right">
        <div class="lang-switch">...</div>
        <div class="header-user-menu">...</div>
        <a href="reservasi.php" id="headerPesanBtn" class="btn btn-gold">Pesan Sekarang</a>
      </div>
    </div>
  </header>
</div>

<!-- Bar status pengguna statis yang dicetak langsung oleh PHP -->
<div class="user-status-bar">
  <div class="container user-status-inner">
    <div class="user-status-info">
      <span class="user-status-greeting">Halo, <strong>Admin</strong></span>
      <span class="user-role-badge badge-admin">ADMIN</span>
    </div>
    <div class="user-status-links">
      <a href="dashboard/admin.php" class="user-status-link">Dashboard Admin</a>
      <a href="logout.php" class="user-status-logout">Keluar</a>
    </div>
  </div>
</div>
```

---

## 3. Analisis Posisi Elemen (Positioning, Float, Flex, Z-Index)

| Elemen | Selektor CSS | Properti CSS Utama | Posisi / Z-Index |
|---|---|---|---|
| Header Utama | `.site-header` | `position: fixed; top: 0; left: 0; right: 0; height: var(--header-h); display: flex; align-items: center;` | Fixed (`z-index: 900`) |
| Inner Header | `.header-inner` | `display: flex; align-items: center; justify-content: space-between;` | Static di dalam fixed |
| Bar Status Pengguna | `.user-status-bar` | `background: var(--sand); position: relative; padding: 0.65rem 0; height: ~53px;` | Relative (`z-index: 100`), flow dokumen normal |
| Tombol Navigasi | `.btn-ghost`, `.btn-gold` | `height: auto; padding: 12px 28px; line-height: 1.5; font-size: 13.5px;` | Inline-flex |
| Logo Brand | `.header-logo` | `display: inline-flex; align-items: center; gap: 10px; height: 50px;` | Inline-flex |

---

## 4. Analisis Penyebab Masalah Spesifik (1 s/d 6)

### Masalah 1: Teks "Halo, Admin" + badge "ADMIN" menempel di samping logo dan bertabrakan dengan "Joglo Masayu"
- **Penyebab**: Elemen `.user-status-bar` dirender tepat di bawah `#site-header` dalam flow dokumen normal pada posisi vertikal `y = 0px` hingga `y = 53px`. Karena `.site-header` menggunakan `position: fixed; top: 0; left: 0`, elemen `.user-status-info` ("Halo, Admin [ADMIN]") di sisi kiri `.user-status-bar` berada di koordinat piksel yang sama persis dengan `.header-logo` (koordinat `x: 40px` s/d `250px`, `y: 14px` s/d `64px`). Akibatnya, kedua teks saling tumpang tindih dan bertabrakan.

### Masalah 2: Menu TENTANG / VILLA / GALERI / KONTAK berada lebih rendah daripada elemen lainnya
- **Penyebab**: `.header-inner` menggunakan `display: flex; justify-content: space-between; align-items: center;`, namun tinggi setiap kolom tidak terkunci pada satu variabel kontrol yang sama. `.header-logo` memiliki tinggi 50px, `.header-nav` memiliki margin/padding turunan dengan tinggi 28.8px, sedangkan `.header-right` memiliki tombol setinggi 42.5px. Karena tidak ada grid vertikal 1 baris yang mengunci titik tengah (*baseline centering*), nav links tampak melorot/tidak sejajar secara optik.

### Masalah 3: Tombol "KELUAR" menimpa pengalih bahasa "ID | EN"
- **Penyebab**: Di sisi kanan `.user-status-bar`, terdapat tautan `.user-status-links` yang memuat tautan "Keluar" (tombol merah) dan "Dashboard Admin". Elemen ini berada pada `top: 0px` dan sisi kanan layar, bertumpuk langsung di atas `.lang-switch` dan `.header-right` yang berada di `top: 18px` s/d `60px`. Karena background `.site-header` pada state transparan adalah tembus pandang (`transparent`), tombol "KELUAR" merah menembus tepat di area sakelar "ID | EN".

### Masalah 4: Tombol "PESAN SEKARANG" lebih tinggi daripada header dan menembus garis bawahnya
- **Penyebab**: Pada state scroll (`.site-header.is-scrolled`) atau halaman dengan class `.page-villa`, tinggi header menyusut menjadi `--header-h-scrolled` (62px atau 52px). Sementara tombol `.btn-gold` / `.btn-ghost` memiliki padding `12px 28px` dengan line-height dan font sehingga total tingginya mencapai 46-52px. Ditambah margin atau alignment yang tidak terkunci variabel kontrol tunggal (`--control-h`), tombol tersebut melebihi batas bawah header.

### Masalah 5: Tinggi header terlalu kecil sehingga isi meluap
- **Penyebab**: Nilai `--header-h-scrolled: 62px` di `style.css` terlalu sempit untuk menampung logo setinggi 50px dan kontrol interaktif (44px) dengan padding atas-bawah yang aman. Tidak adanya `box-sizing: border-box` yang terpadu pada `.header-inner` serta penggunaan padding clamp yang tidak sinkron dengan tinggi header menyebabkan isi meluap ke luar *bounding box* header.

### Masalah 6: Tautan "DASHBOARD ADMIN" tidak sejajar dengan elemen sebelahnya
- **Penyebab**: Tautan "Dashboard Admin" berasal dari berkas layout PHP (`.user-status-bar`) yang terpisah dari DOM navigasi JavaScript `injectHeader()`. Keduanya berasal dari dua kontainer HTML berbeda yang ditumpuk tanpa koordinasi flexbox/grid global, sehingga tidak memiliki *baseline* atau titik tumpu vertikal yang sama.

---

## 5. Solusi & Rencana Tindakan Sesuai Panduan Langkah 2
1. **Hapus `.user-status-bar` dari layout PHP**: Seluruh informasi akun ("Halo, Admin", badge peran, tautan Dashboard, dan tombol Keluar) dipindahkan seutuhnya ke dalam **Chip Pengguna (User Dropdown)** di dalam `.header-actions`.
2. **Rekonstruksi Layout Header Menjadi Grid 3-Zona**:
   - Kontainer: `display: grid; grid-template-columns: auto minmax(0,1fr) auto; align-items: center; column-gap: 24px; height: var(--header-h); padding-inline: clamp(16px, 3vw, 40px);`
   - **Zona Kiri**: Hanya logo (gambar maks 44px + "Joglo Masayu").
   - **Zona Tengah**: Navigasi (`display: flex; justify-content: center; gap: clamp(1rem, 2vw, 2.25rem);`).
   - **Zona Kanan (`.header-actions`)**: `display: flex; align-items: center; gap: 12px;` berisi `[ID|EN] [Chip Pengguna / Masuk] [Pesan Sekarang]`.
3. **Standarisasi Tinggi Kontrol (`--control-h: 40px` desktop, `44px` mobile)**: Semua kontrol interaktif (pengalih bahasa, chip pengguna, tombol Pesan) memiliki tinggi tepat 40px, line-height seragam, dan *center-aligned*.
4. **Tinggi Header Terstandarisasi**: `--header-h: 72px` pada desktop dan `64px` pada mobile. Konten dijamin muat tanpa meluap.
5. **Aksesibilitas & Dropdown Aman**: Menggunakan `aria-haspopup="menu"`, `aria-expanded`, keyboard navigation (Enter, Esc, panah atas/bawah), form POST + CSRF untuk logout.

