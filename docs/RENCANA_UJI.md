# Rencana Pengujian Sistem — Villa Joglo Masayu

Dokumen ini memuat skenario pengujian black-box, white-box, keamanan, dan integrasi web service sesuai dengan Bab IV Tabel 4.3 Proposal Proyek 3 (T-01 sampai T-18). Kolom hasil sengaja dikosongkan untuk diisi pada saat pelaksanaan demonstrasi pengujian formal.

---

## Tabel Skenario Pengujian (T-01 s.d. T-18)

| ID | Fitur / Skenario | Prosedur & Data Uji | Hasil yang Diharapkan | Hasil Pengujian |
|---|---|---|---|---|
| **T-01** | Informasi villa, galeri, kontak, dan peta | Buka halaman beranda (`index.php`) dan detail villa (`villa.php?id=joglo-wulung`). Periksa profil, tipe villa, harga, galeri, peta, dan tombol kontak. | Profil villa, tiga tipe villa, harga, galeri, testimoni, kontak, dan peta tertanam tampil dengan baik dan konsisten. | |
| **T-02** | Pendaftaran Pelanggan | Buka `daftar.php`, isi nama, email valid, nomor kontak, kata sandi $\ge 6$ karakter, dan konfirmasi cocok. Coba kirim field peran buatan. | Akun tersimpan dengan peran "pelanggan", otomatis login, dan field peran buatan dari luar diabaikan. | |
| **T-03** | Login dan logout pengguna (4 peran) | Uji login dengan kredensial Pelanggan, Pengelola, Pemilik, dan Admin; uji akun nonaktif; uji logout. | Pengguna berhasil masuk dan diarahkan sesuai peran masing-masing; akun nonaktif ditolak; logout membersihkan sesi. | |
| **T-04** | Reservasi dengan data valid | Pelanggan memilih Joglo Saren, check-in H+7, check-out H+9 (2 malam), dan 3 tamu. | Permintaan tersimpan berstatus "menunggu", kode reservasi diterbitkan, dan pesan berhasil tampil. | |
| **T-05** | Tanggal atau kapasitas tidak valid | Masukkan check-out sebelum/sama dengan check-in; check-in di masa lampau; atau jumlah tamu melebihi kapasitas maksimum villa. | Sistem menolak permintaan dan menampilkan pesan validasi yang menerangkan letak kesalahan. | |
| **T-06** | Semua unit sudah terpesan | Ajukan reservasi pada tipe villa dan rentang tanggal di mana seluruh unit telah memiliki reservasi berstatus "dikonfirmasi". | Sistem menyatakan tanggal tidak tersedia dan menolak penyimpanan reservasi baru. | |
| **T-07** | Pelanggan melihat reservasi (Proteksi IDOR) | Pelanggan A mencoba membuka rincian/riwayat reservasi milik Pelanggan B dengan memanipulasi parameter URL atau sesi. | Pelanggan A hanya dapat melihat rincian reservasi miliknya sendiri (`id_pelanggan = user_id`). | |
| **T-08** | Pengelola memproses reservasi | Pengelola mengonfirmasi satu permintaan dan mencoba mengonfirmasi permintaan lain yang bertabrakan saat unit sudah habis. | Transaksi database dengan kunci baris (`FOR UPDATE`) mencegah dua reservasi terkonfirmasi melebihi jumlah unit. | |
| **T-09** | Dashboard Pemilik (Read-Only) | Masuk sebagai Pemilik (`dashboard/pemilik.php`). Periksa statistik, ringkasan per status, tipe villa, dan nilai estimasi. | Jumlah dan estimasi nilai reservasi terkonfirmasi tampil akurat; dashboard tidak menyediakan tombol ubah/hapus status. | |
| **T-10** | Admin membuat dan mengelola akun staf | Admin membuat akun Pengelola dan Pemilik, menonaktifkan salah satu akun staf, dan mencoba menonaktifkan akun Admin sendiri. | Akun staf memperoleh peran yang ditentukan; staf nonaktif tidak dapat login; Admin dicegah menonaktifkan akunnya sendiri. | |
| **T-11** | Masukan berupa perintah SQL (SQL Injection) | Isi kolom input (nama, email, catatan) dengan teks injeksi seperti `' OR '1'='1` atau `'; DROP TABLE...`. | Masukan diproses murni sebagai teks biasa melalui PDO prepared statement; struktur database tetap utuh dan aman. | |
| **T-12** | Masukan berupa skrip berbahaya (XSS) | Isi kolom catatan reservasi atau nama dengan `<script>alert('XSS')</script>`. | Karakter di-escape secara aman menggunakan `e()` / `htmlspecialchars`; skrip tidak dieksekusi di browser. | |
| **T-13** | Layanan prakiraan cuaca BMKG berhasil | Buka halaman beranda (`index.php`) atau detail villa (`villa.php`) saat API BMKG dapat diakses. | Data cuaca (suhu, kelembapan, cuaca, angin) tampil lengkap dan mencantumkan "BMKG" sebagai sumber data resmi. | |
| **T-14** | Layanan prakiraan cuaca BMKG gagal | Simulasikan kegagalan koneksi atau timeout pada pemanggilan API eksternal BMKG. | Pesan alternatif informatif tampil; seluruh halaman informasi dan formulir reservasi tetap dapat digunakan normal. | |
| **T-15** | Tampilan antarmuka responsif | Uji buka website pada resolusi layar 360px (ponsel), 768px (tablet), dan 1024px+ (komputer desktop). | Teks, menu navigasi, kartu villa, dan tombol kontrol tidak terpotong serta proporsional pada seluruh ukuran layar. | |
| **T-16** | Jalur logika pemeriksaan ketersediaan | Telusuri cabang algoritma saat jumlah bentrok $<$ `jumlah_unit` dan saat jumlah bentrok $\ge$ `jumlah_unit`. | Cabang logika menghitung reservasi tumpang tindih secara matematis dan menghasilkan boolean ketersediaan yang benar. | |
| **T-17** | Pemeriksaan hak akses (Role Authorization) | Akses `dashboard/*.php` tanpa login atau menggunakan akun Pelanggan. | Permintaan tanpa hak akses ditolak dan dialihkan kembali ke `login.php` atau `index.php` disertai pesan peringatan. | |
| **T-18** | Perhitungan estimasi nilai reservasi | Uji perhitungan durasi menginap campuran (malam weekday + malam weekend + extra bed). | Estimasi dihitung akurat dari formula durasi dikalikan tarif snapshot pemesanan, dan diberi label non-pembayaran aktual. | |

