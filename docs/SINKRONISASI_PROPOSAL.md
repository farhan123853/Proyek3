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
  Sistem secara konsisten menggunakan lokasi **Sleman, D.I. Yogyakarta**, termasuk untuk penentuan titik peta Google Maps.

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

## 4. Pembatalan Fitur Cuaca BMKG dan Penggantian dengan Payment Gateway Midtrans

Atas arahan dan persetujuan dosen pembimbing proyek, fitur prakiraan cuaca eksternal BMKG **dibatalkan dan digantikan sepenuhnya oleh Payment Gateway (Midtrans Snap mode sandbox dan pencatatan pembayaran tunai manual)**.

### Bagian Proposal yang Wajib Direvisi pada Naskah Akhir:
1. **Ringkasan Eksekutif & Abstrak**:
   - Menghapus klausa integrasi prakiraan cuaca BMKG.
   - Menambahkan deskripsi integrasi sistem pembayaran digital multi-metode (Virtual Account bank dan e-wallet via Midtrans Snap mode sandbox) serta pencatatan kas tunai oleh pengelola.
2. **Bab 1.2 (Identifikasi Masalah & Masalah 3)**:
   - Masalah ke-3 ("belum tersedianya informasi cuaca lokal") digantikan dengan masalah autentikasi dan alur pembayaran reservasi yang belum otomatis dan rawan sengketa.
3. **Bab 1.3 (Rumusan Masalah 2) & Bab 1.4 (Tujuan Penelitian 2)**:
   - Rumusan masalah dan tujuan integrasi web service cuaca digantikan dengan: *"Bagaimana merancang dan membangun modul integrasi payment gateway Midtrans dan pencatatan tunai guna mendukung otomatisasi konfirmasi pembayaran reservasi?"*
4. **Bab 1.5 & 1.6 (Batasan Masalah)**:
   - Menghapus batasan cuaca (kode wilayah adm4, cache cuaca).
   - Menambahkan batasan sistem pembayaran: mode sandbox Midtrans (tidak memproses dana riil), metode pembayaran dibatasi pada VA bank dan e-wallet (tanpa kartu kredit), pembayaran penuh tanpa uang muka (DP) dan tanpa sistem refund otomatis, serta pencatatan tunai manual oleh Pengelola.
5. **Kebutuhan Fungsional (F-02 dan F-03)**:
   - F-02 (Konsumsi API Cuaca BMKG) dan F-03 (Tampilan Widget Prakiraan Cuaca 3 Hari) dihapus dan digantikan oleh:
     - **F-15**: Pembuatan transaksi Snap token pembayaran digital (VA dan E-wallet) via Midtrans API.
     - **F-16**: Penanganan webhook notifikasi pembayaran otomatis dari Midtrans.
     - **F-17**: Pencatatan pembayaran tunai manual oleh Pengelola dan pembatalan catatan oleh Admin.
     - **F-18**: Laporan keuangan pemasukan bulanan berdasarkan tanggal pembayaran aktual untuk Pemilik dan tab Transaksi untuk Admin.
6. **Skenario Pengujian (T-13 dan T-14)**:
   - T-13 dan T-14 dihapus dari rencana uji (diberi tanda dibatalkan).
   - Digantikan oleh kelompok skenario pengujian pembayaran T-31 s.d. T-54.
7. **Tabel 2.1 (Tinjauan Pustaka & Dokumentasi BMKG)**:
   - Referensi API BMKG diganti dengan telaah teknis dokumentasi resmi Midtrans Snap API dan Core API.
8. **Bab 2.4 & Gambar Arsitektur Sistem (Gambar 3.1, Tabel 3.2, Tabel 3.6)**:
   - Komponen layanan eksternal BMKG dan mekanisme cache lokal (`cache/cuaca.json`) diganti dengan komponen Midtrans Payment Gateway, library resmi `midtrans/midtrans-php` via Composer, serta skema tabel `pembayaran`.
9. **Bab 6 (Analisis Risiko Sistem)**:
   - Risiko ketidaktersediaan server BMKG dihapus.
   - Diganti dengan mitigasi risiko payment gateway: pengamanan `MIDTRANS_SERVER_KEY` (hanya di server/config lokal), verifikasi `signature_key` SHA-512 webhook, penanganan webhook ganda/idempoten, dan penanganan status pembayaran ganda (*settlement* terlambat setelah tunai).
10. **Lembar Kontribusi Penyusun & Referensi [11]**:
    - Kontribusi modul cuaca diganti modul pembayaran Midtrans.
    - Referensi nomor [11] (BMKG Open Data API) dihapus dari daftar pustaka dan digantikan oleh dokumentasi resmi Midtrans (docs.midtrans.com).
11. **Kesimpulan**:
    - Menyesuaikan butir kesimpulan implementasi sistem dengan menyertakan keandalan modul pembayaran dan laporan pemasukan eksekutif.

---

## 5. Penyelarasan Kalender Ketersediaan, Reservasi Offline, dan Pemblokiran Tanggal (Tahap 11)

### Bagian Proposal yang Direvisi:
1. **Bab 1.2 (Identifikasi Masalah & Batasan Masalah Poin 4)**:
   - Masalah pencatatan reservasi saluran ganda: selain reservasi daring oleh pelanggan terdaftar, villa juga sering menerima tamu langsung (*walk-in*), pemesanan via WhatsApp, dan panggilan telepon. Saluran offline ini kini dapat dicatat secara terpusat oleh staf Pengelola.
2. **Perluasan Kebutuhan Fungsional F-08**:
   - F-08 yang semula hanya memeriksa ketersediaan berdasarkan reservasi `dikonfirmasi` kini diperluas menjadi **fungsi ketersediaan terpadu `hitung_ketersediaan()`**:
     $$\text{Unit Terpakai Malam } D = \text{Reservasi Terkonfirmasi} + \text{Blokir Tanggal Aktif}$$
   - Reservasi `menunggu` berstatus sebagai peringatan ketersediaan dan tidak memblokir unit (sesuai aturan bisnis F-08 proposal awal).
3. **Penambahan Kebutuhan Fungsional Baru**:
   - **F-19**: Kalender Ketersediaan Tanggal Interaktif (dua bulan berdampingan di desktop, satu bulan di mobile, navigasi bulan, indikator pola aksesibel).
   - **F-20**: Pencatatan Reservasi Offline (*walk-in*, WhatsApp, telepon) dengan validasi ketersediaan real-time dan opsi pencatatan tunai atomik.
   - **F-21**: Pemblokiran Tanggal (*Date Blocking*) untuk pemeliharaan/perbaikan villa dan penggunaan pribadi pemilik.
   - **F-22**: Kalender Hunian Staf (*Occupancy Calendar*) dengan visualisasi jadwal hunian bulanan per unit villa dan tampilan agenda pada perangkat mobile.
4. **Pembaruan Diagram ERD dan Kamus Data (Tabel 3.3)**:
   - Tabel `reservasi`: `id_pelanggan` diubah menjadi nullable; penambahan kolom `nama_tamu`, `kontak_tamu`, `sumber` (`online`, `walk_in`, `whatsapp`, `telepon`), dan `dibuat_oleh`.
   - Tabel baru `blokir_tanggal`: `id_blokir`, `id_tipe`, `tanggal_mulai`, `tanggal_selesai`, `alasan`, `dibuat_oleh`, `dibuat_pada`, `dibatalkan_pada`.
5. **Pembaruan Use Case Diagram**:
   - Aktor Pengelola: Mengelola reservasi offline, memblokir tanggal kamar, dan melihat kalender hunian.
   - Aktor Pemilik & Admin: Memantau kalender hunian dalam mode *Read-Only*.
6. **Tabel Pengujian Sistem (Tabel 4.2)**:
   - Penambahan skenario uji ketersediaan dan reservasi offline: **T-55 s.d. T-67**.
7. **Keterbatasan Bisnis Sistem**:
   - Permintaan reservasi berstatus `menunggu` tidak memblokir kalender ketersediaan; jika terjadi bentrok jadwal akibat konfirmasi reservasi lain, permintaan tersebut ditandai "Bentrok" di dashboard dan Pengelola menindaklanjutinya secara manual via WhatsApp.
   - *TODO Master Data*: Jumlah unit aktual (`jumlah_unit`) per tipe villa saat ini bernilai default 1 unit sesuai data awal; perlu dikonfirmasi kembali dengan pemilik jika terdapat penambahan unit fisik di masa mendatang.

