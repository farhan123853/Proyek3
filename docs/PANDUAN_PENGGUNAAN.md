# Panduan Penggunaan Sistem — Villa Joglo Masayu

Dokumen ini menjelaskan alur operasional dan tata cara penggunaan website **Villa Joglo Masayu** untuk setiap peran pengguna: **Pengunjung**, **Pelanggan**, **Pengelola**, **Pemilik**, dan **Administrator**.

---

## 1. Peran: Pengunjung (Publik)

Pengunjung adalah calon tamu yang membuka website tanpa perlu masuk ke akun terlebih dahulu.

### Fitur yang Dapat Diakses:
1. **Melihat Profil & Tipe Villa (`index.php`)**:
   - Membaca latar belakang arsitektur Joglo Masayu.
   - Memeriksa daftar 3 tipe villa: **Joglo Wulung**, **Joglo Saren**, dan **Joglo Agung**.
   - Melihat spesifikasi kapasitas, fasilitas, serta galeri foto.
2. **Melihat Detail Villa (`villa.php?id={slug}`)**:
   - Mengakses detail lengkap masing-masing villa, foto interaktif, fasilitas, rincian harga (weekday, weekend, extra bed), serta villa terkait.
3. **Melihat Prakiraan Cuaca BMKG (`cuaca.php` / Widget Beranda)**:
   - Memantau kondisi cuaca, suhu, kelembapan, dan kecepatan angin terkini di wilayah Sukoharjo, Ngaglik, Sleman.
4. **Pendaftaran Akun Pelanggan (`daftar.php`)**:
   - Mendaftar dengan mengisi nama lengkap, alamat email, nomor kontak/WhatsApp, dan kata sandi minimal 6 karakter.
   - Pendaftaran publik secara otomatis menghasilkan akun dengan peran **Pelanggan**.
5. **Menghubungi Pengelola via WhatsApp**:
   - Tombol "Tanya via WhatsApp" dan tautan WhatsApp pada bagian kontak tetap dapat diakses kapan saja.

---

## 2. Peran: Pelanggan (Customer)

Pelanggan adalah tamu yang telah memiliki akun terdaftar dan berhasil masuk (login).

### Alur Reservasi Villa:
1. **Masuk ke Akun (`login.php`)**:
   - Masukkan email dan kata sandi yang telah didaftarkan.
2. **Membuka Formulir Reservasi (`reservasi.php`)**:
   - Klik tombol **Pesan Sekarang** pada header atau menu status bar.
   - Pilih tipe villa yang diinginkan.
   - Masukkan tanggal **Check-In** (minimal hari ini) dan **Check-Out** (minimal 1 hari setelah check-in).
   - Masukkan **Jumlah Tamu** (sistem otomatis memvalidasi agar tidak melebihi kapasitas maksimum villa).
   - Isi catatan atau permintaan khusus (opsional).
3. **Melihat Kalkulasi Estimasi Real-Time**:
   - Sistem akan langsung menampilkan status ketersediaan unit dan rincian tarif:
     - Durasi malam menginap.
     - Subtotal malam weekday (Senin–Kamis).
     - Subtotal malam weekend (Jumat–Minggu).
     - Biaya extra bed (jika tamu melebihi kapasitas standar).
     - Estimasi total biaya sewa.
4. **Mengajukan Reservasi**:
   - Klik tombol **Ajukan Permintaan Reservasi**.
   - Sistem menyimpan pesanan dengan status awal **"menunggu"** dan menerbitkan Kode Reservasi (contoh: `#RSV-12`).
5. **Memantau Status Pesanan (`reservasi-saya.php`)**:
   - Pelanggan dapat melihat riwayat seluruh pesanan miliknya.
   - Status yang ditampilkan:
     - **Menunggu**: Permintaan sedang ditinjau oleh pihak pengelola.
     - **Dikonfirmasi**: Jadwal telah disetujui resmi oleh pengelola.
     - **Ditolak**: Permintaan tidak dapat dipenuhi (misalnya jadwal bentrok atau unit penuh).
   - Terdapat tombol **Tanya WA** untuk berkomunikasi langsung dengan pengelola mengenai pesanan terkait.

---

## 3. Peran: Pengelola (Manager / Staff)

Pengelola bertanggung jawab menangani operasional penerimaan dan validasi ketersediaan kamar.

### Alur Kerja Dashboard Pengelola (`dashboard/pengelola.php`):
1. **Masuk ke Akun**: Gunakan kredensial peran pengelola (misal: `pengelola@joglomasayu.com`).
2. **Memantau Kartu Statistik**:
   - Melihat jumlah permohonan yang berstatus *Menunggu*, *Terkonfirmasi*, dan *Ditolak*.
3. **Menyaring Daftar Reservasi**:
   - Gunakan menu dropdown untuk menyaring daftar berdasarkan status tertentu.
4. **Memproses Reservasi (Konfirmasi / Tolak)**:
   - **Tolak**: Klik tombol merah *Tolak* jika tamu tidak memenuhi syarat atau villa sedang renovasi.
   - **Konfirmasi**: Klik tombol hijau *Konfirmasi*.
     - Sistem secara otomatis menjalankan transaksi basis data dengan kunci baris (`FOR UPDATE`) untuk memeriksa apakah masih ada unit villa yang tersisa pada rentang tanggal tersebut.
     - Jika unit masih ada, status berubah menjadi *dikonfirmasi* dan nama pengelola pencatat disimpan.
     - Jika unit telah habis karena telah dikonfirmasi sebelumnya, sistem otomatis membatalkan tindakan, mempertahankan status *menunggu*, dan menampilkan peringatan bahwa unit telah penuh.

---

## 4. Peran: Pemilik (Owner)

Pemilik memiliki hak akses eksekutif untuk meninjau performa bisnis secara keseluruhan tanpa mengubah data operasional harian.

### Fitur Dashboard Pemilik (`dashboard/pemilik.php`):
1. **Tampilan Bersifat Read-Only**:
   - Tidak disediakan tombol ubah status, hapus, maupun edit reservasi (mencegah perubahan data tanpa sengaja).
2. **Kartu Estimasi Nilai Reservasi Terkonfirmasi**:
   - Menghitung akumulasi nilai sewa dari seluruh reservasi yang telah disetujui (dikonfirmasi).
   - Terdapat label kepatuhan wajib: *"estimasi, bukan uang masuk aktual"*.
3. **Statistik Jumlah Reservasi**:
   - Memantau volume pesanan masuk, rasio konfirmasi, dan penolakan.
4. **Laporan Berdasarkan Tipe Villa**:
   - Membandingkan villa terfavorit berdasarkan jumlah pemesanan dan kontribusi estimasi nilai sewa.
5. **Filter Periode (Bulan/Tahun) & Villa**:
   - Pemilik dapat memfilter laporan pendapatan estimasi per bulan tertentu.

---

## 5. Peran: Administrator (System Admin)

Administrator bertanggung jawab atas kelangsungan akun pengguna dan keamanan hak akses sistem.

### Fitur Dashboard Admin (`dashboard/admin.php`):
1. **Membuat Akun Staf Baru**:
   - Mengisi formulir pembuatan akun untuk staf **Pengelola** atau **Pemilik**.
   - Akun staf tidak dapat dibuat melalui formulir pendaftaran publik.
2. **Mengelola Status Keaktifan Akun**:
   - Tombol *Aktifkan* / *Nonaktifkan* akun pengguna.
   - Akun yang dinonaktifkan tidak akan dapat masuk (login) ke dalam sistem.
   - **Proteksi Khusus**: Admin **tidak dapat menonaktifkan akunnya sendiri** untuk mencegah hilangnya akses administratif sistem.
3. **Mengubah Peran Pengguna**:
   - Admin dapat menaikkan atau menurunkan peran pengguna (Pelanggan, Pengelola, Pemilik, Admin) sesuai wewenang penugasan.
