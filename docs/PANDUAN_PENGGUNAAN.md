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
3. **Pendaftaran Akun Pelanggan (`daftar.php`)**:
   - Mendaftar dengan mengisi nama lengkap, alamat email, nomor kontak/WhatsApp, dan kata sandi minimal 6 karakter.
   - Pendaftaran publik secara otomatis menghasilkan akun dengan peran **Pelanggan**.
4. **Menghubungi Pengelola via WhatsApp**:
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
5. **Mencatat Reservasi Offline (Walk-in, WhatsApp, Telepon)**:
   - Klik tombol **+ Reservasi Offline** pada header dashboard atau kalender hunian.
   - Pilih sumber pemesanan (*Datang Langsung*, *WhatsApp*, atau *Telepon*).
   - Masukkan nama tamu (2–100 karakter) dan nomor kontak aktif (9–15 digit).
   - Tentukan tipe villa, tanggal check-in/out via kalender interaktif, dan jumlah tamu.
   - Tersedia opsi **Catat pembayaran tunai sekarang** untuk langsung menerbitkan status lunas secara atomik.
   - Klik **Simpan**: sistem memvalidasi ketersediaan unit dengan kunci baris mutex. Jika terdapat permintaan online menunggu pada tanggal yang sama, sistem memberikan peringatan konfirmasi terlebih dahulu.
6. **Memblokir Tanggal Villa (Maintenance / Pribadi Pemilik)**:
   - Klik tombol **Blokir Tanggal** di pojok kanan bawah dashboard.
   - Pilih tipe villa, tanggal mulai (inklusif), tanggal selesai (eksklusif), dan alasan penutupan (misal: *Perawatan & Perbaikan Rutin* atau *Dipakai Pribadi oleh Pemilik*).
   - Sistem menolak pemblokiran jika terdapat reservasi terkonfirmasi yang sedang berjalan pada tanggal tersebut.
   - Pemblokiran yang aktif dapat dibatalkan sewaktu-waktu (*soft-cancel*) sehingga tanggal kembali terbuka.
7. **Memantau Kalender Hunian (`kalender-hunian.php`)**:
   - Klik menu **Kalender Hunian** pada header dashboard.
   - Pada layar desktop/tablet ($\ge 640\text{ px}$), visualisasi jadwal tampil sebagai *timeline* per tipe villa dengan batang pembeda: Online (biru), Offline (ungu), Menunggu (pola garis kuning), dan Blokir (abu-abu silang).
   - Pada ponsel ($< 640\text{ px}$), tampilan bertransformasi menjadi **Daftar Agenda Harian** vertikal tanpa *horizontal scroll*.
   - Pengelola dapat melihat nama tamu dan mengakses tombol kontak WhatsApp secara langsung.
8. **Membatalkan Reservasi Offline**:
   - Reservasi offline yang **belum lunas** dapat dibatalkan melalui dashboard (wajib mencantumkan alasan, status berubah menjadi *dibatalkan*, dan tanggal otomatis terbuka kembali).
   - Reservasi yang sudah berstatus lunas dilindungi dari pembatalan sepihak.
9. **Menangani Permohonan Menunggu yang Bentrok**:
   - Jika suatu tanggal telah habis terisi oleh reservasi lain, permohonan *menunggu* yang tersisa akan ditandai dengan lencana merah **⚠ Bentrok Jadwal**.
   - Sistem menolak tombol persetujuan pada reservasi bentrok. Pengelola dapat menolaknya dan mengabari tamu melalui tombol template WhatsApp.
10. **Melihat Struk Pembayaran & Rincian Tagihan (Slide-Over Drawer)**:
   - Pengelola dapat mengklik tautan **"Lihat struk"** (pada reservasi lunas) atau **"Rincian tagihan"** (pada reservasi belum lunas/menunggu) pada kolom *Pembayaran*.
   - Panel geser (*slide-over drawer*) akan terbuka dari kanan tanpa memuat ulang halaman:
     - **Tampilan Lunas**: Memuat nomor resi / Order ID, tanggal & jam pembayaran (WIB), metode pembayaran (kelompok & rincian), nama petugas kasir (jika tunai), catatan pembayaran, dan riwayat transaksi.
     - **Tampilan Tagihan**: Memuat rincian tarif sewa per malam $\times$ jumlah malam menginap, sisa tagihan, serta tombol aksi cepat **"Catat Pembayaran Tunai"** untuk membuka modal kasir.
     - **Aksi Drawer**: Tombol *Salin No. Resi* (dengan konfirmasi toast), *Cetak / Simpan PDF* (membuka tampilan ramah cetak), *Kirim via WhatsApp* (pesan konfirmasi otomatis ke tamu), dan tombol *Tutup* (didukung tombol keyboard `Esc`).

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

