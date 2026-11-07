# Sinkronisasi & Penyelarasan Proposal — Villa Joglo Masayu

Dokumen ini mencatat daftar perbedaan dan penyesuaian antara naskah Proposal Proyek 3 (*Rancang Bangun Website Villa Joglo Masayu sebagai Media Promosi dan Informasi Penginapan Bernuansa Arsitektur Joglo*) dengan implementasi teknis perangkat lunak yang dibangun.

---

## 1. Lokasi Geografis Villa

- **Tercantum pada Naskah Proposal:**
  Pada Bab 1.1 (Latar Belakang, halaman 2) sempat tertulis lokasi berada di *Subang, Jawa Barat*.
- **Kondisi Nyata & Implementasi Sistem:**
  Di seluruh bagian proposal lainnya (Ringkasan Eksekutif, Bab 2 Analisis Kebutuhan, Lampiran Kontribusi), serta data frontend dan peta kontak asli, lokasi villa yang sebenarnya adalah:
  **Jl. Kaliurang Km 14, Sukoharjo, Kapanewon Ngaglik, Kabupaten Sleman, Daerah Istimewa Yogyakarta.**
- **Tindakan Penyelarasan:**
  Sistem secara konsisten menggunakan lokasi **Sleman, D.I. Yogyakarta**, termasuk untuk penentuan titik peta Google Maps dan kode wilayah adm4 BMKG (`34.04.05.2003`).

---

## 2. Pembaruan Skema Master Data & Tarif Villa

- **Tercantum pada Naskah Proposal (Tabel 3.3 & ERD Awal):**
  Tabel `tipe_villa` hanya mencantumkan atribut sederhana: `kapasitas` dan `harga_per_malam`.
- **Kondisi Nyata pada Kode Frontend Asli (`script.js`):**
  Bisnis perhotelan/villa menerapkan diferensiasi tarif dan kapasitas:
  - Tarif Hari Kerja (**Weekday**: Senin–Kamis).
  - Tarif Akhir Pekan (**Weekend**: Jumat–Minggu).
  - Biaya kasur tambahan (**Extra Bed** per orang per malam).
  - Kapasitas dasar tamu (**Kapasitas Standar**) dan batas toleransi (**Kapasitas Maksimal**).
- **Tindakan Penyelarasan:**
  Skema tabel `tipe_villa` diperbarui menjadi:
  `kapasitas_standar`, `kapasitas_maks`, `harga_weekday`, `harga_weekend`, `harga_extrabed`, `jumlah_unit`, `deskripsi`, `fasilitas`.
  Hal ini membuat sistem mampu melakukan perhitungan tarif menginap campuran secara akurat sesuai data riil.

---

## 3. Penghitungan & Snapshot Estimasi Nilai Reservasi

- **Tercantum pada Naskah Proposal:**
  Menyimpan `harga_per_malam_saat_pesan` dan `estimasi_total`.
- **Implementasi Sistem:**
  Diselaraskan dengan skema harga baru:
  - Kolom snapshot: `harga_weekday_saat_pesan`, `harga_weekend_saat_pesan`, `harga_extrabed_saat_pesan`, dan `estimasi_total`.
  - Formula kalkulasi:
    $$\text{Estimasi Total} = (\text{malam\_weekday} \times \text{tarif\_weekday}) + (\text{malam\_weekend} \times \text{tarif\_weekend}) + (\text{extra\_bed} \times \text{tarif\_extrabed} \times \text{total\_malam})$$
  - Seluruh antarmuka laporan Pemilik wajib menyertakan label: *"estimasi, bukan uang masuk aktual"* karena pencatatan pembayaran kas fisik berada di luar ruang lingkup proyek.

---

## 4. URL & Spesifikasi Web Service Cuaca BMKG

- **Tercantum pada Naskah Proposal:**
  Menyebutkan integrasi API data cuaca BMKG.
- **Implementasi Resmi BMKG Terkini:**
  - Endpoint resmi: `https://api.bmkg.go.id/publik/prakiraan-cuaca?adm4={kode_wilayah_tingkat_iv}` (ejaan baku resmi BMKG adalah *"prakiraan"*, bukan *"perkiraan"*).
  - Format data JSON tingkat IV per desa/kelurahan untuk rentang 3 hari dengan interval 3 jam.
  - Dokumentasi resmi: `https://data.bmkg.go.id/prakiraan-cuaca/`.
  - Kode wilayah Sukoharjo, Ngaglik, Sleman berdasarkan Kepmendagri 100.1.1-6117 Tahun 2022: `34.04.05.2003`.
- **Penerapan Keamanan & Keandalan:**
  Sistem memanggil API BMKG melalui backend PHP (`api/cuaca.php`) dengan cURL berbatas waktu (timeout 5 detik), menerapkan cache lokal selama 1 jam di `cache/cuaca.json`, dan menyajikan pesan alternatif informatif jika API eksternal sedang tidak dapat dijangkau.
