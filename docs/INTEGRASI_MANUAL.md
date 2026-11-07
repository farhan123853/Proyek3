# Panduan Integrasi Manual — Villa Joglo Masayu

Dokumen ini memuat catatan teknis mengenai integrasi pihak ketiga, data aset eksternal, dan penyesuaian manual yang dapat dilakukan oleh pihak pengelola di masa depan tanpa mengubah arsitektur inti sistem.

---

## 1. Integrasi Layanan Komunikasi WhatsApp

- **Tautan Saat Ini:** `https://wa.me/6281234567890` (placeholder pada `assets/js/script.js` konfigurasi `CONFIG.whatsappNumber`).
- **Alasan Penggunaan:** Sistem reservasi sengaja tidak menggunakan Payment Gateway berbayar (Midtrans/Xendit) sesuai batasan proposal. Komunikasi konfirmasi lanjutan dan pembayaran dilakukan langsung antara tamu dan staf melalui WhatsApp.
- **Cara Memperbarui Nomor:**
  Buka berkas `assets/js/script.js` pada baris 4:
  ```javascript
  const CONFIG = {
    whatsappNumber: '628XXXXXXXXXX', // Ganti dengan nomor WhatsApp aktif (tanpa tanda + atau spasi)
  ```

---

## 2. Integrasi Peta Google Maps

- **Status:** Menggunakan Google Maps Embed Iframe dan URL pencarian lokasi.
- **Koordinat Saat Ini:** `Jl. Kaliurang Km 14, Sukoharjo, Ngaglik, Sleman, D.I. Yogyakarta`.
- **Lokasi Kode:**
  - `index.php` (baris ~240): elemen `<iframe>` dengan URL embed Google Maps.
  - `assets/js/script.js` (`CONFIG.mapsUrl` dan `CONFIG.mapsEmbedUrl`).
- **Cara Memperbarui Titik Presisi:**
  1. Buka Google Maps di peramban, cari titik presisi bangunan Villa Joglo Masayu.
  2. Klik tombol **Bagikan** -> **Sematkan peta (Embed a map)** -> Salin kode HTML iframe.
  3. Perbarui atribut `src` pada elemen `#kontakMapIframe` di `index.php`.

---

## 3. Penggantian Foto Asli Villa (Menggantikan Placeholder Unsplash)

Saat ini galeri dan detail villa menggunakan foto beresolusi tinggi dari Unsplash bertema arsitektur kayu tradisional. Jika foto asli villa telah diambil secara profesional:
1. Simpan foto ke direktori:
   - `assets/images/villa-wulung-1.jpg`, `villa-wulung-2.jpg`, dst.
   - `assets/images/villa-saren-1.jpg`, `villa-saren-2.jpg`, dst.
   - `assets/images/villa-agung-1.jpg`, `villa-agung-2.jpg`, dst.
2. Perbarui tautan URL foto pada tabel `tipe_villa` di database atau fallback array `VILLAS` di `assets/js/script.js`.

---

## 4. Penyesuaian Jumlah Unit Fisik Villa

- Pada skema awal, kolom `jumlah_unit` pada tabel `tipe_villa` diatur dengan nilai default `1` (placeholder TODO).
- Jika pada lokasi fisik Joglo Masayu memiliki lebih dari 1 unit untuk tipe tertentu (misal Joglo Wulung memiliki 2 unit rumah kayu):
  ```sql
  UPDATE `tipe_villa` SET `jumlah_unit` = 2 WHERE `slug` = 'joglo-wulung';
  ```
  Sistem secara otomatis akan mengizinkan hingga 2 reservasi berstatus *dikonfirmasi* pada rentang tanggal yang sama sebelum menyatakan kamar penuh.
