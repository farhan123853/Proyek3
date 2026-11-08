# DESIGN TOKENS SISTEM VILLA JOGLO MASAYU
**Dokumentasi Pemetaan Token Desain Visual Joglo Tradisional ke Tailwind CSS**
*Villa Joglo Masayu — Proyek 3 D4 Teknik Informatika ULBI*

---

## 1. Filosofi & Karakter Visual
Desain antarmuka Villa Joglo Masayu mengakar pada arsitektur tradisional rumah Joglo Jawa dengan karakter:
- **Hangat & Bersahaja**: Didominasi warna krem lembut (`cream`) dan warna pasir halus (`sand`).
- **Elegan & Klasik**: Elemen kayu jati tua (`teak`) dan kayu sonokeling/mahoni (`wood`).
- **Agung & Istimewa**: Aksen emas keraton (`gold`) pada tombol aksi utama, garis dekoratif, dan status aktif.
- **Asri & Alami**: Aksen hijau daun tua (`green-dark`) mencerminkan kesejukan alam pedesaan Yogyakarta.

---

## 2. Pemetaan Token Warna (Color Tokens)

| Token Desain | Variabel CSS Asli | Nilai Heksadesimal | Kelas Tailwind CSS | Penggunaan Utama |
|---|---|---|---|---|
| **Cream** | `--cream` | `#FAF3E3` | `bg-cream`, `text-cream` | Warna latar belakang dasar halaman (*canvas*) |
| **Sand** | `--sand` | `#EFE0C0` | `bg-sand`, `border-sand` | Border kartu, garis pembatas, latar kartu sekunder |
| **Teak** | `--teak` | `#3B2416` | `text-teak`, `bg-teak` | Teks judul utama, warna teks body, warna latar footer |
| **Wood** | `--wood` | `#6B4A2F` | `text-wood`, `border-wood` | Teks sekunder, label formulir, ikon pendukung |
| **Gold** | `--gold` | `#E3A600` | `bg-gold`, `text-gold` | Tombol CTA primer ("Pesan Sekarang"), aksen highlight |
| **Gold Dark** | `--gold-dark` | `#B88400` | `hover:bg-gold-dark`, `text-gold-dark` | Status hover tombol CTA, link aktif, fokus aksen |
| **Green Dark**| `--green-dark`| `#2F4F3E` | `bg-green-dark`, `text-green-dark` | Badge sukses, status konfirmasi, aksen botani |
| **White** | `--white` | `#FFFFFF` | `bg-white`, `text-white` | Latar kartu form, popover dropdown, kontras teks tombol |

---

## 3. Pemetaan Tipografi (Typography Tokens)

| Token Font | Font Family | Kelas Tailwind | Penggunaan Spesifik |
|---|---|---|---|
| **Display** | `'Playfair Display', Georgia, serif` | `font-display` | Judul utama (H1–H3), nama villa, nama brand keraton |
| **Body** | `'Lato', -apple-system, sans-serif` | `font-body` | Paragraf teks, isi form, tombol, navigasi, tabel |
| **Script** | `'Kaushan Script', cursive` | `font-script` | Eyebrow text, kutipan selamat datang, aksen puitis |

---

## 4. Spasi, Elevasi, dan Radius (Metrics & Elevation)

| Metrik | Nilai Standar | Token Tailwind | Deskripsi Penggunaan |
|---|---|---|---|
| **Header Height (Desktop)** | `72px` | `h-[72px]` | Tinggi navigasi tetap saat posisi awal desktop |
| **Header Height (Scrolled)**| `64px` | `h-[64px]` | Tinggi navigasi saat discroll atau pada mobile |
| **Control Height** | `40px` (desktop) / `44px` (mobile)| `h-10` / `h-11` | Tinggi standar tombol, input form, dan switcher |
| **Border Radius** | `12px` / `16px` | `rounded-joglo` / `rounded-joglo-lg` | Sudut membulat lembut pada kartu dan form |
| **Shadow Joglo** | `0 10px 30px -5px rgba(59,36,22,0.08)` | `shadow-joglo` | Bayangan kartu hangat bernuansa kayu lembut |
| **Glow Gold** | `0 0 25px rgba(227,166,0,0.25)` | `shadow-joglo-glow` | Efek pendaran emas pada tombol fokus & aktif |

---

## 5. Pemetaan Komponen UI

| Komponen UI | Kelas Asli (style.css / auth.css) | Padanan Utilitas Tailwind CSS |
|---|---|---|
| **Tombol Emas** | `.btn.btn-gold` | `inline-flex items-center justify-center font-bold px-6 py-2.5 rounded-full bg-gold text-teak hover:bg-gold-dark transition duration-200 shadow-sm` |
| **Tombol Outline**| `.btn.btn-ghost` | `inline-flex items-center justify-center font-bold px-6 py-2.5 rounded-full border border-sand text-teak hover:bg-sand/30 transition duration-200` |
| **Kartu Form** | `.form-card` | `bg-white rounded-joglo-lg p-8 shadow-joglo border border-sand/40` |
| **Input Form** | `.form-control` | `w-full px-4 py-2.5 rounded-lg border border-sand bg-cream/30 text-teak focus:border-gold focus:ring-1 focus:ring-gold outline-none transition` |
| **Badge Status** | `.badge.badge-gold` | `inline-flex items-center text-xs font-semibold px-2.5 py-0.5 rounded-full bg-gold/20 text-gold-dark` |
| **Container** | `.container` | `max-w-7xl mx-auto px-4 sm:px-6 lg:px-8` |

