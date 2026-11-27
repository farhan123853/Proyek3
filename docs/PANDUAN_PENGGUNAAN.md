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
     - **Kedaluwarsa**: Reservasi otomatis ditutup oleh sistem karena melewati batas waktu pembayaran tanpa pelunasan. Unit kamar dilepas kembali ke kalender.
     - **Ditolak**: Permintaan tidak dapat dipenuhi (misalnya jadwal bentrok atau unit penuh).
     - **Dibatalkan**: Reservasi offline/manual dibatalkan oleh staf pengelola dengan alasan tercatat.
   - **Hitung Mundur Pembayaran & Bar Progres**:
     - Menampilkan sisa waktu bayar secara real-time yang tersinkronisasi dengan stempel waktu server (bukan waktu jam perangkat).
     - Jika sisa waktu $\le 3\text{ jam}$, sistem menampilkan peringatan berwarna mencolok (merah) bahwa reservasi akan dibatalkan otomatis dan kamar dilepas jika belum dilunasi.
   - **Pilihan Metode Bayar (Online vs Tunai di Villa)**:
     - Tombol **"Bayar sekarang"**: Membuka popup pembayaran online Midtrans Snap (VA bank atau e-wallet).
     - Tombol **"Bayar tunai di villa"**: Mengubah rencana bayar menjadi tunai dan memperpanjang batas bayar hingga akhir hari check-in (23:59 WIB). Pelanggan tetap dapat beralih kembali ke pembayaran online kapan saja sebelum batas tersebut.
   - **Aksi Pasca-Kedaluwarsa**:
     - Lencana abu-abu **KEDALUWARSA** beserta penjelasan waktu kedaluwarsa.
     - Indikator ketersediaan real-time apakah tanggal yang sama saat ini masih kosong atau sudah diisi tamu lain.
     - Tombol **"Pesan ulang tanggal yang sama"**: Mengarahkan ke formulir reservasi dengan data villa, tanggal, dan jumlah tamu yang langsung terisi otomatis (tanpa membuat pesanan otomatis).
     - Tombol **"Hubungi via WhatsApp"** untuk bantuan layanan pelanggan.

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

---

## 6. Fitur Pembayaran: Virtual Account & Kode Pembayaran Unik (Midtrans)

Sistem telah dilengkapi modul penangkapan, penyimpanan, dan pemantauan nomor Virtual Account (VA) resmi yang diterbitkan oleh Midtrans untuk memenuhi transparansi transaksi:

### 1. Sisi Pelanggan (`reservasi-saya.php`):
- **Pemilihan Metode**: Pelanggan memilih metode transfer bank (BCA, BNI, BRI, Mandiri, Permata, dll.) atau gerai ritel pada popup Midtrans Snap.
- **Kartu Kode Pembayaran Unik**:
  - Setelah memilih metode, kartu khusus muncul pada kartu reservasi dengan latar belakang biru lembut dan batas aksen.
  - Menampilkan badge nama bank/toko (misal: *BCA Virtual Account*, *Mandiri Bill Payment*).
  - Menampilkan nomor Virtual Account / Kode Pembayaran dengan pemformatan spasi yang mudah dibaca.
  - **Tombol Salin**: Memudahkan pelanggan menyalin nomor VA ke clipboard perangkat dengan konfirmasi toast interaktif.
  - **Countdown Timer**: Menghitung mundur sisa waktu pembayaran hingga batas kedaluwarsa secara dinamis.
  - **3 Langkah Panduan Transfer**: Instruksi terstruktur berbahasa Indonesia atau Inggris sesuai preferensi bahasa aktif pengguna.
  - **Ganti Bank / Metode**: Memungkinkan pelanggan membatalkan instruksi bayar saat ini untuk memilih bank atau saluran pembayaran lain tanpa membuat reservasi baru.
  - **Bantuan WhatsApp**: Tautan langsung konfirmasi ke nomor pengelola dengan format pesan otomatis memuat kode reservasi dan nomor VA.
- **Privasi Pasca-Lunas**: Setelah transaksi dinyatakan lunas oleh Midtrans webhook, nomor VA disamarkan secara otomatis (contoh: `••••4567`) pada tampilan publik untuk menjaga privasi perbankan.

### 2. Sisi Staf Pengelola (`dashboard/pengelola.php` & `struk-pembayaran.php`):
- **Pencarian Cepat**: Kolom pencarian tabel reservasi dapat mengenali pencarian berdasarkan nomor Virtual Account, Order ID, maupun nama tamu.
- **Tampilan Tabel**: Kolom pembayaran menampilkan nomor VA (tersamar untuk privasi umum) beserta bank penyedia.
- **Drawer Rincian Pembayaran**: Staf dapat membuka panel geser untuk melihat nomor VA lengkap tak tersamar, waktu kedaluwarsa, serta riwayat percobaan transaksi pelanggan sebelumnya.
- **Struk Resmi**: Halaman cetak struk (`struk-pembayaran.php`) menyertakan rincian nomor VA dan riwayat transaksi untuk arsip operasional.

### 3. Sisi Administrator (`dashboard/admin.php`):
- **Tab Transaksi**: Memuat seluruh mutasi pembayaran baik tunai maupun Midtrans.
- **Kolom Bank & Kode VA**: Menampilkan nomor VA lengkap secara transparan untuk keperluan audit rekonsiliasi keuangan.
- **Filter Pencarian**: Mendukung filter pencarian instan berdasarkan kode VA, Order ID, atau nama pengguna.
- **Audit Duplikasi**: Indikator penanda khusus akan muncul jika sistem mendeteksi ketidakwajaran nomor VA yang terdaftar pada transaksi lain.

---

## 7. Batas Waktu Pembayaran, Kedaluwarsa Otomatis, dan Riwayat Mutasi Status

Sistem menerapkan penegakan siklus hidup reservasi yang pasti untuk memastikan **tidak ada lagi reservasi yang berstatus menggantung**:

### 1. Perhitungan Batas Waktu Pembayaran:
- **Batas Waktu Online**: Waktu konfirmasi pengelola + `PEMBAYARAN_BATAS_JAM` (24 jam), dibatasi paling lambat pukul 12:00 WIB pada hari check-in, dan tidak kurang dari 2 jam sejak konfirmasi.
- **Batas Waktu Tunai di Villa**: Jika tamu memilih "Bayar tunai di villa", batas bayar diperpanjang hingga pukul 23:59:59 WIB pada akhir hari check-in. Pembayaran online tetap dapat dilakukan sebelum batas tersebut.
- **Pengecualian Reservasi Offline**: Reservasi walk-in / telepon yang dikonfirmasi langsung oleh staf tidak memiliki batas bayar otomatis (`batas_bayar_pada = NULL`) agar fleksibel ditagihkan saat tamu menginap.

### 2. Algoritma Kedaluwarsa Otomatis (`expire_reservasi_lewat_batas()`):
- Sistem secara periodik mengecek reservasi berstatus `'dikonfirmasi'` yang telah melewati `batas_bayar_pada` dan belum memiliki pembayaran `'lunas'`.
- **Perlindungan Transaksi Aktif**: Jika transaksi online di Midtrans masih dalam rentang masa berlaku, sistem menunda penutupan reservasi hingga sesi bayar gateway selesai.
- **Penutupan Bersih**: Status reservasi diubah menjadi `'kedaluwarsa'`, transaksi pembayaran pending ditutup, dan unit kamar seketika terlepas di kalender untuk tamu lain.
- **Pemicu Ganda**: Didukung eksekusi oportunistik (setiap 60 detik saat halaman diakses) dan eksekusi background via CLI Task Scheduler / Cron (`bin/expire-reservasi.php`).

### 3. Penanganan Pembayaran Terlambat (Late Payment Reconciliation):
- Jika notifikasi settlement Midtrans tiba untuk reservasi yang sudah kedaluwarsa:
  - **Kamar Masih Kosong**: Sistem secara otomatis mengaktifkan kembali reservasi menjadi `'dikonfirmasi'`, mencatat pembayaran `'lunas'`, dan mencatat log reaktivasi.
  - **Kamar Sudah Penuh**: Reservasi tetap berstatus `'kedaluwarsa'`, dan pembayaran ditandai sebagai status `'ganda'` dengan catatan *"perlu ditinjau: tanggal sudah terisi"* agar staf pengelola dan admin dapat memproses pengembalian dana (*refund*) secara manual.

### 4. Panel Riwayat Mutasi Status (`reservasi_log`):
- Pada dashboard Pengelola (`dashboard/pengelola.php`), setiap baris reservasi dilengkapi panel riwayat mutasi (*status history*) yang dapat dibuka (*collapsible*).
- Menampilkan jejak waktu, pelaku mutasi (nama staf, nama pelanggan, atau *"Sistem"*), perubahan status dari dan ke, serta alasan perubahan status secara transparan.

### 5. Tombol Pengingat & Pemberitahuan WhatsApp Dwibahasa:
- **Pengingat WA (Aktif)**: Template pesan otomatis berbahasa Indonesia atau Inggris sesuai preferensi tamu, memuat nominal tagihan dan sisa waktu bayar.
- **Kabari WA (Kedaluwarsa)**: Template pesan pemberitahuan ramah bahwa batas waktu pembayaran telah berakhir dan unit telah dilepas kembali ke kalender.


