# Audit Frontend — Villa Joglo Masayu

> Dokumen ini hasil audit *read-only* terhadap file frontend statis.
> **Tidak boleh mengubah file asli.**

---

## 1. Palet Warna (CSS Custom Properties)

| Variable       | Nilai     | Keterangan                    |
|---------------|-----------|-------------------------------|
| `--gold`       | `#E3A600` | Aksen utama (tombol, border)  |
| `--gold-dark`  | `#B88400` | Aksen hover / eyebrow         |
| `--teak`       | `#3B2416` | Warna teks utama / heading    |
| `--wood`       | `#6B4A2F` | Teks sekunder / subtitle      |
| `--cream`      | `#FAF3E3` | Background utama              |
| `--sand`       | `#EFE0C0` | Background seksi alternatif   |
| `--green`      | `#2F4F3E` | Aksen hijau (belum banyak dipakai) |
| `--text`       | `#2B1B14` | Body text                     |

---

## 2. Font

| Penggunaan          | Font Family              | Fallback     |
|--------------------|--------------------------|--------------|
| Heading (h1-h4)    | `Playfair Display`       | `serif`      |
| Body / UI / Button  | `Lato`                   | `sans-serif` |
| Logo text           | `Kaushan Script`         | `cursive`    |

Dimuat via Google Fonts:
```
Playfair+Display:ital,wght@0,400;0,500;1,400
Lato:wght@300;400;700
Kaushan+Script
```

---

## 3. Class CSS Penting

### Tipografi
- **`.eyebrow`** — Label uppercase kecil (font: Lato 13-14.5px, 700, letter-spacing 0.22em, warna `--gold-dark`).
- **`.section-heading`** — Heading seksi (Playfair Display, clamp 1.9-2.9rem).
- **`.body-text`** — Paragraf (Lato 16-17px, line-height 1.85, max-width 52ch).

### Layout
- **`.container`** — Max-width `1160px`, padding-inline responsif.

### Tombol
- **`.btn`** — Tombol dasar: border 1px `--teak`, padding 14px 26px, font Lato 12.5px uppercase 700, hover → background `--gold`.
- **`.btn-ghost`** — Sama dengan `.btn` (transparan, border outline).
- **`.btn-gold`** — Background `--gold`, border `--gold`, hover → `--gold-dark` + teks putih.
- **`.btn-ghost-dark`** — Untuk di atas background gelap (border/teks `--cream`).
- **`.btn-back`** — Tombol kembali (padding lebih kecil).
- **`.btn-back-top`** — Fixed di kiri atas, muncul setelah scroll >300px.

### Animasi
- **`.reveal`** — Opacity 0, translateY 24px; `.is-visible` → opacity 1, translateY 0.
- **`.reveal-delay-1/2/3`** — Transition-delay 90/180/270ms.

### Komponen
- **`.chip`** — Badge fasilitas (border 1px, padding 8px 16px, font 14px).
- **`.price-box`** — Kotak harga (border gold, bg #fffaf0).
- **`.lightbox`** — Modal foto fullscreen (z-index 9999).

---

## 4. Mekanisme Header & Footer

### `injectHeader()` (script.js baris 433-491)
- Mengisi `<div id="site-header">` dengan `<header>`, navigasi, hamburger, lang-switch.
- Logo: `<img src="images/logo.png">` + `<span>Joglo Masayu</span>`.
- Link navigasi: `#tentang`, `#villa`, `#galeri`, `#kontak` (path relatif ke `index.html`).
- Pada `page-villa`: prepend `index.html` ke semua href navigasi.
- Tombol "Pesan Sekarang" → WhatsApp link.
- Mobile nav overlay (`#mobileNav`).

### `injectFooter()` (script.js baris 494-545)
- Mengisi `<div id="site-footer">` dengan `<footer>`.
- Logo: `images/logo.png`, teks "Joglo Masayu" (Kaushan Script).
- Link footer mengarah ke `index.html#tentang`, `index.html#villa`, dll.
- Copyright: `© 2024 Joglo Masayu. Semua hak dilindungi.`
- Kontak: WhatsApp + Instagram (dari CONFIG).

### Implikasi untuk halaman PHP baru
- Halaman PHP baru **harus** berada di ROOT (sejajar index.html).
- Wajib memuat `<div id="site-header"></div>`, `<main>`, `<div id="site-footer"></div>`.
- Wajib `<link rel="stylesheet" href="style.css">` dan `<script src="script.js" defer>`.
- Path `images/logo.png` sudah relatif, bekerja otomatis selama file di root.

---

## 5. Struktur Data VILLAS (script.js)

Objek `VILLAS` (array 3 elemen) — acuan seed database:

### Villa 1: Joglo Wulung
```
id        : 'joglo-wulung'
slug      : 'joglo-wulung'
num       : '01'
name      : 'Joglo Wulung'
code      : 'Villa 01'
price.weekday  : Rp 1.500.000
price.weekend  : Rp 2.000.000
price.extrabed : Rp 350.000
capacity.std   : 2
capacity.max   : 3
meta (id) : '1 kamar · kolam renang · sarapan'
facilities (id): ['1 kamar tidur king', 'Kolam renang pribadi', 'Kamar mandi semi-terbuka',
                  'Teras & hammock', 'AC & kipas langit-langit', 'Wi-Fi',
                  'Sarapan untuk 2 orang', 'Pembuat kopi', 'Minibar', 'Handuk & amenities']
```

### Villa 2: Joglo Saren
```
id        : 'joglo-saren'
slug      : 'joglo-saren'
num       : '02'
name      : 'Joglo Saren'
code      : 'Villa 02'
price.weekday  : Rp 2.200.000
price.weekend  : Rp 2.900.000
price.extrabed : Rp 350.000
capacity.std   : 4
capacity.max   : 5
meta (id) : '2 kamar · ruang keluarga · kolam renang'
facilities (id): ['2 kamar tidur (1 king, 1 twin)', 'Ruang keluarga', 'Kolam renang bersama',
                  'Dapur kecil', 'Teras luas', 'AC & kipas angin', 'Wi-Fi',
                  'Sarapan untuk 4 orang', 'Pembuat kopi & teh', 'Handuk & amenities']
```

### Villa 3: Joglo Agung
```
id        : 'joglo-agung'
slug      : 'joglo-agung'
num       : '03'
name      : 'Joglo Agung'
code      : 'Villa 03'
price.weekday  : Rp 3.800.000
price.weekend  : Rp 4.800.000
price.extrabed : Rp 400.000
capacity.std   : 6
capacity.max   : 8
meta (id) : '3 kamar · pendopo · kolam renang · dapur penuh'
facilities (id): ['3 kamar tidur (2 king, 1 twin)', 'Pendopo besar', 'Kolam renang panoramik',
                  'Dapur penuh', 'Ruang makan outdoor', 'AC & kipas plafon', 'Wi-Fi',
                  'Sarapan untuk 6 orang', 'Layanan kamar', 'Concierge pribadi',
                  'Handuk & amenities premium']
```

### Struktur objek per villa
```
{
  id: string,           // ID unik (= slug)
  slug: string,         // URL slug untuk villa.html?id=
  num: string,          // Nomor tampil ('01', '02', '03')
  name: string,         // Nama villa
  code: string,         // Kode tampil ('Villa 01', dll.)
  photos: string[],     // Array URL foto (placeholder Unsplash)
  price: {
    weekday:  { id: string, en: string },
    weekend:  { id: string, en: string },
    extrabed: { id: string, en: string }
  },
  priceFrom: { id: string, en: string },
  capacity: { std: number, max: number },
  meta: { id: string, en: string },
  tagline: { id: string, en: string },
  description: { id: string, en: string },
  facilities: { id: string[], en: string[] }
}
```

---

## 6. Data Placeholder / Contoh

| Data               | Nilai Saat Ini                                    | Lokasi             |
|--------------------|-------------------------------------------------|-------------------|
| No. WhatsApp       | `6281234567890` (placeholder)                     | script.js CONFIG   |
| URL Instagram      | `https://www.instagram.com/joglomasayu/`          | script.js CONFIG   |
| URL Google Maps    | `https://maps.google.com/?q=Villa+Joglo+Yogyakarta` | script.js CONFIG |
| Maps Embed         | `https://maps.google.com/maps?q=Yogyakarta&...`  | script.js CONFIG + index.html |
| Foto Hero          | 4 URL Unsplash (900×q=80)                         | script.js CONFIG   |
| Foto Gallery       | 8 URL Unsplash (800×q=80)                         | script.js CONFIG   |
| Foto Villa         | 4 URL Unsplash per villa (1200×q=80)              | script.js VILLAS   |
| Foto Tentang       | Unsplash `photo-1570129477492` (720×q=80)         | index.html inline  |
| Tahun Copyright    | `2024`                                            | script.js TRANSLATIONS |
| Testimoni          | "Arinta & Baskara, Jakarta, 2024"                 | script.js TRANSLATIONS |
| Alamat             | Jl. Kaliurang Km 14, Sukoharjo, Ngaglik, Sleman   | script.js TRANSLATIONS + index.html |

---

## 7. File Frontend & Ukuran

| File                     | Ukuran   |
|--------------------------|----------|
| `index.html`             | 14.103 B |
| `villa.html`             | 3.258 B  |
| `script.js`              | 56.104 B |
| `style.css`              | 42.920 B |
| `images/favicon.png`     | 12.452 B |
| `images/logo-icon-dark.png` | 105.338 B |
| `images/logo-icon.png`   | 105.338 B |
| `images/logo.png`        | 430.590 B |

> **Catatan:** `logo-icon-dark.png` dan `logo-icon.png` memiliki hash identik.

---

## 8. Keputusan Desain & Spesifikasi Backend

Berdasarkan fakta dan batasan frontend statis, disepakati aturan arsitektur berikut:

### A. Lokasi dan Struktur File Halaman Baru
- **Semua halaman PHP baru berada langsung di ROOT**: `daftar.php`, `login.php`, `logout.php`, `reservasi.php`, `reservasi-saya.php`, `cuaca.php`, `dashboard-pengelola.php`, `dashboard-pemilik.php`, `dashboard-admin.php`.
- **Dilarang membuat subfolder `dashboard/`** untuk halaman web agar path relatif header/footer (`index.html`, `images/logo.png`) tidak rusak.
- **Folder `api/` murni untuk endpoint JSON**: tidak memanggil header/footer HTML.
- **Markup standar**: Menggunakan `<body class="page-villa">`, memuat `#site-header`, `#site-footer`, `style.css`, `assets/css/app.css`, dan `script.js` (defer). Tidak menggunakan atribut `data-i18n` pada halaman baru (semua teks langsung Bahasa Indonesia).

### B. Skema Tabel `tipe_villa` (Disesuaikan dengan Data Asli)
Tabel master villa diselaraskan dengan objek `VILLAS` di `script.js`:
- `id` (INT AUTO_INCREMENT PK)
- `nama` (VARCHAR 100) — misal: 'Joglo Wulung'
- `slug` (VARCHAR 100 UNIQUE) — misal: 'joglo-wulung'
- `kapasitas_standar` (INT) — kapasitas dasar tanpa extra bed
- `kapasitas_maks` (INT) — batas maksimal tamu dengan extra bed
- `harga_weekday` (DECIMAL 12,2) — tarif Senin s.d. Kamis
- `harga_weekend` (DECIMAL 12,2) — tarif Jumat s.d. Minggu
- `harga_extrabed` (DECIMAL 12,2) — tarif per extra bed per malam
- `jumlah_unit` (INT DEFAULT 1) — ketersediaan fisik unit
- `deskripsi` (TEXT)
- `fasilitas` (JSON / TEXT)

### C. Definisi Hari & Perhitungan Tarif
- **Weekday**: Menginap pada hari **Senin, Selasa, Rabu, Kamis**.
- **Weekend**: Menginap pada hari **Jumat, Sabtu, Minggu**.
- **Estimasi Nilai Reservasi**:
  $$\text{Estimasi Total} = (\text{malam\_weekday} \times \text{tarif\_weekday}) + (\text{malam\_weekend} \times \text{tarif\_weekend}) + (\text{extra\_bed} \times \text{tarif\_extrabed} \times \text{total\_malam})$$
- **Snapshot Historis**: Nilai `malam_weekday`, `malam_weekend`, tarif per malam saat pemesanan, dan `estimasi_total` wajib disimpan ke tabel transaksi reservasi agar tidak terpengaruh perubahan harga di kemudian hari.
- **Label Wajib**: Pada UI ringkasan dan dashboard, nilai ini wajib diberi keterangan/label: *"estimasi, bukan uang masuk aktual"*.

### D. Aturan Validasi Tamu & Extra Bed
- Jumlah tamu yang dipesan wajib $\le$ `kapasitas_maks`.
- Jika jumlah tamu $>$ `kapasitas_standar`, maka selisihnya dihitung otomatis sebagai extra bed:
  $$\text{jumlah extra bed} = \text{jumlah tamu} - \text{kapasitas\_standar}$$
- Jika jumlah tamu $\le$ `kapasitas_standar`, biaya extra bed = 0.

### E. Data Awal (Seed Database)
Mengacu langsung pada objek `VILLAS` di `script.js`:
1. **Joglo Wulung**: Kapasitas 2 std / 3 maks. Weekday: Rp 1.500.000, Weekend: Rp 2.000.000, Extra Bed: Rp 350.000.
2. **Joglo Saren**: Kapasitas 4 std / 5 maks. Weekday: Rp 2.200.000, Weekend: Rp 2.900.000, Extra Bed: Rp 350.000.
3. **Joglo Agung**: Kapasitas 6 std / 8 maks. Weekday: Rp 3.800.000, Weekend: Rp 4.800.000, Extra Bed: Rp 400.000.
*(TODO: Konfirmasi ulang dengan pemilik villa apabila terdapat perbedaan tarif promosi atau tarif musiman).*

