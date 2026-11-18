# Catatan Pengembangan — Villa Joglo Masayu

Dokumen ini mencatat riwayat pelaksanaan tahapan pengembangan, keputusan teknis, audit, dan integrasi fitur pada sistem Website Villa Joglo Masayu (Proyek 3 D4 Teknik Informatika ULBI).

---

## Tahap 1: Audit dan Persiapan Integrasi Midtrans Payment Gateway
- **Tanggal/Waktu**: 2026-11-16 (WIB)
- **Status Gerbang**: LULUS
- **Ringkasan Pekerjaan**:
  1. Audit menyeluruh terhadap kode eksisting:
     - Alur reservasi: Pelanggan mengajukan -> status `menunggu` -> Pengelola konfirmasi (`dikonfirmasi`) atau tolak (`ditolak`).
     - Perhitungan `estimasi_total`: Dihitung berdasarkan rincian malam weekday & weekend dikalikan tarif unit dan extra bed, menghasilkan bilangan bulat integer Rupiah.
     - Helper keamanan & layout: `includes/auth.php` (otentikasi & `require_role()`), `includes/csrf.php` (`csrf_field()`, `verify_csrf_token()`), `includes/helpers.php` (`e()`, `t()`, `flash()`, `get_active_lang()`), dan sistem toast vanilla JS.
     - Zona waktu: Ditetapkan secara konsisten ke `Asia/Jakarta` (WIB) pada `includes/bootstrap.php` (`date_default_timezone_set('Asia/Jakarta')`) dan sesi MySQL (`SET time_zone='+07:00'`) pada opsi koneksi PDO di `includes/db.php`.
     - Versi basis data terdeteksi: MySQL 8.0.30 (mendukung InnoDB, stored generated column, dan index unik).
  2. Pemasangan Pustaka Resmi:
     - `composer require midtrans/midtrans-php` berhasil dipasang (versi 2.6.2).
     - Berkas `composer.json` dan `composer.lock` ditambahkan ke version control.
     - Direktori `vendor/` dan `node_modules/` diamankan dalam `.gitignore`.
     - Inisialisasi otomatis via `vendor/autoload.php` diintegrasikan ke `includes/bootstrap.php`.
  3. Konfigurasi Lingkungan:
     - `config/config.example.php` diperbarui dengan konstanta `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_IS_PRODUCTION` (default `false`), dan `PEMBAYARAN_BATAS_JAM` (24 jam).
     - `docs/PANDUAN_INSTALASI.md` diperbarui dengan panduan `composer install`, registrasi sandbox Midtrans, pengambilan Access Keys, dan konfigurasi lingkungan.
- **Verifikasi Gerbang Tahap 1**:
  - `composer install` / autoloader berfungsi normal.
  - Kelas `\Midtrans\Config` dapat dimuat dan diakses.
  - Perintah `git grep "SB-Mid-"` bersih (tidak menemukan string kunci sandbox dalam repositori yang dilacak Git).

---

## Tahap 2: Penghapusan Total Fitur Cuaca BMKG
- **Tanggal/Waktu**: 2026-11-16 (WIB)
- **Status Gerbang**: LULUS
- **Ringkasan Pekerjaan**:
  1. Penghapusan Berkas:
     - Dihapus: `api/cuaca.php`, `assets/js/cuaca.js`, `cuaca.php`, `cache/cuaca.json`, dan direktori `cache/`.
  2. Pembersihan Kode & Konfigurasi:
     - Dihapus: konstanta `BMKG_ADM4` pada `config/config.example.php` dan `config/config.php`.
     - Dihapus: kontainer widget cuaca dan pemanggilan `assets/js/cuaca.js` pada `index.php` dan `villa.php`.
     - Dihapus: aturan CSS `.weather-widget` dan komponen turunannya pada `assets/css/app.css`.
     - Dihapus: kunci translasi cuaca (`weather.*` dan `nav.cuaca`) pada `lang/id.php` dan `lang/en.php`. Skrip `tests/cek-i18n.php` terverifikasi 148 kunci lengkap tanpa error.
  3. Pembaruan Dokumentasi & Rencana Uji:
     - Skenario uji cuaca T-13 dan T-14 pada `docs/RENCANA_UJI.md` ditandai dibatalkan atas arahan pembimbing.
     - `README.md`, `docs/PANDUAN_INSTALASI.md`, `docs/PANDUAN_PENGGUNAAN.md` diperbarui dengan menghapus seluruh referensi cuaca BMKG.
     - `docs/SINKRONISASI_PROPOSAL.md` diperbarui secara komprehensif mencatat seluruh bagian naskah proposal yang harus direvisi (Bab 1.2, 1.3, 1.4, 1.5, 1.6, F-02, F-03, T-13, T-14, Tabel 2.1, Bab 2.4, Tabel 3.2, Tabel 3.6, Gambar 3.1, Bab 6, Lembar Kontribusi, Referensi [11]).
  4. Kompilasi & Pengujian:
     - `npm run build` dijalankan ulang (bersih tanpa class yatim).
     - Seluruh berkas PHP lulus `php -l`.
     - `git grep -n -i -E "cuaca|bmkg|weather|prakiraan|adm4"` terbukti bersih (hanya terdapat di dokumen penyelarasan proposal dan catatan ini).
     - Seluruh rute halaman dimuat normal (status 200) tanpa error konsol.

---

## Tahap 3: Perancangan & Migrasi Tabel Pembayaran
- **Tanggal/Waktu**: 2026-11-16 (WIB)
- **Status Gerbang**: LULUS
- **Ringkasan Pekerjaan**:
  1. Pembuatan Skema Tabel `pembayaran`:
     - Berkas migrasi `database/migrations/003_pembayaran.sql` dibuat dengan atribut: `id_pembayaran`, `id_reservasi` (FK), `order_id` (UNIQUE), `sumber` (`midtrans`/`manual`), `jumlah`, `status` (`menunggu`, `lunas`, `gagal`, `kedaluwarsa`, `ganda`, `dibatalkan`), `metode_kelompok`, `metode_detail`, `transaction_id`, `snap_token`, `catatan`, `dicatat_oleh` (FK), `dibatalkan_oleh` (FK), `alasan_batal`, `dibuat_pada`, `dibayar_pada`.
     - Skema diperbarui ke `database/schema.sql` dan didokumentasikan di `docs/STRUKTUR_DATABASE.md`.
  2. Penegakan Aturan Satu-Lunas (One-Paid Rule):
     - Ditegakkan langsung pada level basis data via Stored Generated Column `lunas_key INT GENERATED ALWAYS AS (IF(status='lunas', id_reservasi, NULL)) STORED` dengan indeks unik `uq_pembayaran_reservasi_lunas (lunas_key)`.
     - Menolak duplikasi status `lunas` pada reservasi yang sama, sekaligus mengizinkan banyak percobaan berstatus non-lunas.
     - Relasi foreign key `fk_pembayaran_reservasi` menggunakan `ON DELETE RESTRICT ON UPDATE RESTRICT` sesuai spesifikasi MySQL 8 untuk kolom yang dirujuk oleh stored generated column.
  3. Pemetaan Helper & Seed Demo:
     - Fungsi `kelompok_metode(?string $paymentType): string` ditambahkan ke `includes/helpers.php` sesuai dokumentasi resmi Midtrans (memetakan VA bank, e-wallet, tunai, dan fallback 'lainnya').
     - Fungsi `status_bayar_efektif(int $idReservasi): array` ditambahkan untuk mengevaluasi status pembayaran efektif per reservasi.
     - Berkas `database/seed-demo.sql` dibuat dengan peringatan "DATA UJI, JANGAN DIPAKAI DI PRODUKSI" berisi data contoh transaksi lintas bulan (Jan-Nov 2026) dan lintas tahun (2025).
  4. Pengujian Gerbang:
     - Dijalankan `tests/test_tahap3_db.php`: berhasil menyisipkan multiple order menunggu, satu lunas berhasil, percobaan lunas kedua untuk reservasi yang sama ditolak MySQL (Error 23000 Integrity constraint violation). Lulus sempurna.

---

## Tahap 4: Midtrans — Endpoint Buat Transaksi (Snap Token)
- **Tanggal/Waktu**: 2026-11-17 (WIB)
- **Status Gerbang**: LULUS
- **Ringkasan Pekerjaan**:
  1. Pembuatan Endpoint `api/buat-pembayaran.php`:
     - Metode dibatasi hanya `POST` dengan respons JSON.
     - Wajib terautentikasi dengan peran `pelanggan` dan verifikasi CSRF token ganda (form-data, header `X-CSRF-Token`, atau raw JSON body).
     - Validasi ID reservasi dengan query prepared statement terikat pada `id_pelanggan` pengguna yang aktif dan `status = 'dikonfirmasi'`. Memberikan respons generik HTTP 404 jika tidak sesuai guna mencegah serangan IDOR dan enumerasi ID.
     - Penolakan jika reservasi telah berstatus `lunas` (HTTP 400).
     - Kebijakan efisiensi token: penggunaan kembali `snap_token` jika ada transaksi `menunggu` aktif yang belum kedaluwarsa dalam rentang waktu `PEMBAYARAN_BATAS_JAM`.
     - Pembuatan format `order_id`: `MSY-{id_reservasi}-{timestamp}` (unik, panjang <= 50 karakter).
     - Parameter Midtrans Snap: `transaction_details` (nominal dari `estimasi_total` di server), `item_details` (1 item nama villa maksimal 50 karakter), `customer_details` (nama, email, kontak pelanggan), `expiry` (durasi sesuai konfigurasi), dan pembatasan eksplisit `enabled_payments` hanya untuk Virtual Account bank (`bca_va`, `echannel`, `bni_va`, `bri_va`, `permata_va`, `cimb_va`, `other_va`) dan e-wallet (`gopay`, `shopeepay`, `qris`). Kartu kredit tidak ditawarkan.
     - Penanganan exception `\Midtrans\Snap::getSnapToken()` yang aman dengan penyaringan error log (mencegah kebocoran Server Key), pesan generik dwibahasa, dan HTTP 502 bila gagal menghubungi API Midtrans.
     - Penyimpanan baris pembayaran awal ke tabel `pembayaran` dengan `sumber='midtrans'`, `status='menunggu'`, dan `snap_token`.
  2. Pengujian Otomatis Gerbang Tahap 4:
     - Dibuat dan dijalankan skrip pengujian HTTP end-to-end `tests/test_tahap4_http.php`.
     - Test 1: Permintaan tanpa login -> ditolak (HTTP 403) [LULUS].
     - Test 2: Permintaan dengan token CSRF tidak valid -> ditolak (HTTP 403) [LULUS].
     - Test 3: Upaya IDOR mengakses reservasi pelanggan lain -> ditolak (HTTP 404) [LULUS].
     - Test 4: Upaya pembayaran reservasi berstatus `menunggu` (belum disetujui pengelola) -> ditolak (HTTP 404) [LULUS].
     - Test 5: Upaya pembayaran pada reservasi yang sudah lunas -> ditolak (HTTP 400) [LULUS].
     - Test 6: Verifikasi parameter Snap terbukti hanya mencantumkan kode VA bank & E-wallet resmi serta tanpa `credit_card` [LULUS].

---

## Tahap 5: Webhook Notifikasi Midtrans dan Sinkronisasi Status
- **Tanggal/Waktu**: 2026-11-18 (WIB)
- **Status Gerbang**: LULUS
- **Ringkasan Pekerjaan**:
  1. Pembuatan Webhook `notifikasi-midtrans.php` (dan alias `api/notifikasi-midtrans.php`):
     - Menerima notifikasi resmi server-to-server dari Midtrans tanpa login dan tanpa CSRF.
     - Verifikasi `signature_key`: `hash('sha512', order_id . status_code . gross_amount . SERVER_KEY)` dibandingkan dengan `hash_equals()`. Nilai `gross_amount` digunakan tepat sebagai string dari body tanpa konversi tipe data. Permintaan dengan tanda tangan tidak valid ditolak dengan HTTP 403.
     - Konfirmasi status resmi ke Midtrans melalui `\Midtrans\Transaction::status(order_id)` dengan fallback aman ke body terverifikasi jika diuji dalam sandbox/mock lokal.
     - Pemetaan status Midtrans: `settlement` atau `capture` (fraud `accept`) -> `lunas`; `pending` -> `menunggu`; `expire` -> `kedaluwarsa`; `deny`/`cancel`/`failure` -> `gagal`; status tak dikenal -> `menunggu` + log peringatan.
     - Validasi keberadaan `order_id` di database dan kesesuaian nilai nominal terhadap kolom `jumlah` (HTTP 400 jika tidak cocok).
     - Pembaruan idempoten dalam transaksi database (`SELECT ... FOR UPDATE` pada reservasi dan pembayaran): webhook duplikat tidak memicu galat dan tidak menurunkan status yang sudah `lunas`.
     - Penanganan Kasus Pembayaran Ganda: jika notifikasi settlement tiba untuk order Midtrans ketika reservasi sudah lunas melalui pembayaran lain (misalnya pembayaran tunai telah dicatat), status baris Midtrans ditetapkan menjadi `ganda`, diberi catatan rujukan, dicatat di error log, dan tidak dihitung ke pemasukan sistem.
     - Mengembalikan respons HTTP 200 hanya saat pemrosesan sukses.
  2. Implementasi Helper Sinkronisasi & API Cek Status:
     - Dibuat fungsi helper `sinkron_pembayaran(string $orderId): array` di `includes/helpers.php` sebagai mekanisme Plan B jika webhook tidak tembus.
     - Dibuat endpoint `api/cek-status-pembayaran.php` (metode POST + verifikasi CSRF): peran Pelanggan hanya diizinkan memeriksa transaksinya sendiri (proteksi IDOR), sementara Pengelola dan Admin dapat memeriksa transaksi manapun.
  3. Pengujian Otomatis Gerbang Tahap 5:
     - Dibuat dan dieksekusi skrip pengujian `tests/test_tahap5_webhook.php`.
     - Uji 1: Signature palsu/salah -> ditolak (HTTP 403) [LULUS].
     - Uji 2: Order ID tidak dikenal -> ditolak (HTTP 400) [LULUS].
     - Uji 3: Nominal tidak cocok antara webhook dan database -> ditolak (HTTP 400) [LULUS].
     - Uji 4: Webhook dikirim dua kali berturut-turut (idempoten) -> HTTP 200, tetap 1 lunas [LULUS].
     - Uji 5: Settlement terlambat setelah pembayaran tunai tercatat -> status `ganda`, tidak dihitung lunas [LULUS].
     - Uji 6: Helper `sinkron_pembayaran()` berfungsi normal dan idempoten [LULUS].
     - Uji 7: Proteksi endpoint `api/cek-status-pembayaran.php` (POST only & wajib login) [LULUS].

---

## Tahap 6: Pembayaran Tunai Manual & Pembatalan Catatan
- **Tanggal/Waktu**: 2026-11-18 (WIB)
- **Status Gerbang**: LULUS
- **Ringkasan Pekerjaan**:
  1. Pembuatan Endpoint `api/catat-tunai.php`:
     - Metode hanya `POST` dengan respons JSON.
     - Dibatasi secara ketat hanya untuk peran `pengelola` (`require_role`), verifikasi CSRF.
     - Validasi nominal otomatis dari database di server (`estimasi_total`), tanpa menerima nominal dari browser (mencegah manipulasi client-side).
     - Validasi `tanggal_bayar`: tidak boleh di masa depan dan tidak boleh sebelum tanggal reservasi dibuat.
     - Integrasi pengakhiran transaksi online: jika ada transaksi Midtrans 'menunggu' pada reservasi terkait, sistem otomatis membatalkan via `\Midtrans\Transaction::cancel()` dan mengubah status barisnya menjadi `kedaluwarsa`.
     - Transaksi database aman dengan `SELECT ... FOR UPDATE` pada baris reservasi dan pengecekan ulang status `lunas` guna mencegah klik ganda (idempoten).
     - Penyimpanan baris pembayaran: `order_id` format `TUNAI-{id_reservasi}-{timestamp}`, `sumber='manual'`, `status='lunas'`, `metode_kelompok='tunai'`, `metode_detail='cash'`, `dicatat_oleh=id_pengelola`.
  2. Pembuatan Endpoint `api/batal-tunai.php`:
     - Metode hanya `POST` dengan respons JSON.
     - Dibatasi eksklusif hanya untuk peran `admin` (`require_role`), verifikasi CSRF.
     - Wajib mengisi alasan pembatalan (`alasan_batal` maksimal 200 karakter).
     - Mengubah status pembayaran tunai menjadi `dibatalkan`, mencatat `dibatalkan_oleh` dan `alasan_batal`. Jejak baris tetap tersimpan aman (audit trail) dan reservasi kembali berstatus belum lunas sehingga dapat dibayar ulang.
  3. Pengujian Otomatis Gerbang Tahap 6:
     - Dibuat dan dieksekusi skrip pengujian `tests/test_tahap6_tunai.php`.
     - Uji 1: Pelanggan, Pemilik, Admin tidak bisa mencatat tunai -> HTTP 403 [LULUS].
     - Uji 2A: Reservasi bukan status 'dikonfirmasi' ditolak -> HTTP 400 [LULUS].
     - Uji 2B: Reservasi yang sudah berstatus lunas ditolak dari pencatatan ganda -> HTTP 400 [LULUS].
     - Uji 3: Tanggal pembayaran di masa depan ditolak -> HTTP 400 [LULUS].
     - Uji 4: Pencatatan tunai oleh Pengelola sukses, nominal sesuai server, dan order Midtrans pending diakhiri -> HTTP 200 [LULUS].
     - Uji 5: Pembatalan catatan tunai hanya diizinkan untuk Admin, alasan wajib diisi, jejak audit tersimpan, dan reservasi kembali dapat dibayar -> HTTP 200 [LULUS].

---

## Tahap 8 (Bagian 1): Tampilan Pembayaran Pelanggan dan Pengelola (Dwibahasa, Responsif)
- **Tanggal/Waktu**: 2026-11-18 (WIB)
- **Status Gerbang**: LULUS
- **Ringkasan Pekerjaan**:
  1. Komponen Bersama & Helper:
     - `render_badge_pembayaran(string $status, bool $isStaff = false)`: Komponen badge dengan ikon SVG + teks deskriptif (tidak mengandalkan warna semata) untuk status: belum dibayar (netral), menunggu (kuning), lunas (hijau), gagal (merah), kedaluwarsa (abu-abu), serta badge ganda & dibatalkan khusus staf.
     - `format_metode_pembayaran(?string $metodeKelompok)`: Menampilkan label dwibahasa untuk metode ("Transfer bank", "E-wallet", "Tunai", "Lainnya").
     - `format_status_reservasi(string $status)`: Menampilkan label status reservasi dwibahasa secara simetris dan terverifikasi oleh pengujian i18n.
  2. Tampilan Pelanggan (`reservasi-saya.php`):
     - Tata letak kartu per reservasi dengan badge status reservasi + status pembayaran, estimasi total, dan rincian periode menginap.
     - Indikator alur 3 langkah jelas: (1) Konfirmasi, (2) Bayar, (3) Selesai.
     - Tombol aksi kondisional:
       - Status 'menunggu': teks "Menunggu konfirmasi Pengelola" + tombol kontak WhatsApp.
       - Status 'dikonfirmasi' & belum bayar: tombol utama "Bayar Sekarang" (Midtrans Snap) + batas waktu bayar 24 jam + catatan bayar tunai di villa.
       - Status 'menunggu' (Snap token aktif): tombol "Lanjutkan Pembayaran" + tombol "Cek Status Pembayaran".
       - Status 'gagal' / 'kedaluwarsa': tombol "Bayar Lagi".
       - Status 'lunas': badge Lunas dengan metode pembayaran, tanggal bayar, tanpa tombol bayar, dan bantuan WhatsApp untuk perubahan.
       - Status 'ditolak' / 'dibatalkan': tanpa aksi bayar.
     - Bagian Riwayat Pembayaran (collapsible) yang menampilkan waktu, Order ID, metode, nominal, dan badge status.
     - Pemuatan library Midtrans Snap JS (`app.sandbox.midtrans.com/snap/snap.js`) hanya jika halaman memuat tombol bayar aktif. Integrasi aman via token CSRF, disabling tombol saat loading, dan penanganan callback (`onSuccess`, `onPending`, `onError`, `onClose`) dengan sinkronisasi ke server sebelum reload.
  3. Tampilan Pengelola (`dashboard/pengelola.php`):
     - Tabel daftar reservasi dengan kolom "Pembayaran" (badge + metode) dan penyaring ganda (status reservasi & status pembayaran).
     - Di layar mobile (<768px), tabel otomatis bertransformasi menjadi tumpukan kartu yang responsif.
     - Tombol "Catat pembayaran tunai" pada reservasi dikonfirmasi yang belum lunas dengan modal 2 langkah: nominal otomatis dari server (hanya tampil), tanggal bayar, catatan, dan checkbox konfirmasi.
     - Penegakan aturan bisnis di sisi server: penolakan atau pembatalan reservasi yang sudah lunas diblokir di backend dengan pesan penolakan yang tegas.
     - Penanda visual "Lewat batas bayar" untuk reservasi dikonfirmasi yang melebihi 24 jam belum lunas (tanpa pembatalan otomatis).
     - Tombol "Cek status pembayaran" per reservasi dengan AJAX POST dan proteksi CSRF.
     - Tombol "Kabari via WhatsApp" cerdas: pesan pra-konfirmasi berisi instruksi pembayaran online/tunai beserta nominal dan batas waktu; pesan pasca-lunas berisi ucapan terima kasih dan konfirmasi jadwal.
  4. Pengujian Otomatis Gerbang Tahap 8 (Bagian 1):
     - Dibuat dan dieksekusi skrip pengujian `tests/test_tahap8_pelanggan_pengelola.php`.
     - Uji 1: Halaman Pelanggan memuat Snap JS bersyarat, badge status, dan tombol bayar [LULUS].
     - Uji 2: Pengujian dwibahasa ID/EN pada halaman Pelanggan [LULUS].
     - Uji 3: Dashboard Pengelola memuat filter pembayaran, modal tunai 2 langkah, dan template WhatsApp [LULUS].
     - Uji 4: Penegakan aturan bisnis server mencegah penolakan reservasi lunas [LULUS].
     - Uji 5: Pemindaian bebas `alert()` browser (seluruh notifikasi menggunakan toast/flash) [LULUS].
  5. Verifikasi Tambahan:
     - `php tests/cek-i18n.php`: 100% konsisten antara ID (192 kunci) dan EN (192 kunci), 0 missing keys.
     - Rebuild Tailwind CSS: `npm run build` sukses.
     - `git grep "SB-Mid-"`: Bersih dari bocoran API keys.
     - PHP Lint: Seluruh berkas PHP lulus sintaks tanpa galat.

- **Tanggal/Waktu**: 2026-11-18 (WIB)
- **Status Gerbang**: LULUS
- **Ringkasan Pekerjaan**:
  1. Pembuatan Modul Layanan Data `includes/laporan.php`:
     - Bersifat murni baca (read-only), tidak mengubah data di basis data.
     - Definisi operasional Pemasukan: total pembayaran berstatus `lunas` yang dikelompokkan berdasarkan atribut `dibayar_pada` (WIB), bukan tanggal check-in. Status non-lunas (`menunggu`, `gagal`, `kedaluwarsa`, `ganda`, `dibatalkan`) tidak dihitung.
     - Fungsi `pemasukan_bulanan(int $tahun, string $metode = 'all')`: mengembalikan data 12 bulan (bulan masa depan ditandai, bulan ini disorot) mencakup jumlah transaksi, subtotal per metode (`transfer_bank`, `e_wallet`, `tunai`, `lainnya`), dan total. Menggunakan kueri berparameter dengan rentang `>= awal tahun AND < awal tahun berikutnya` guna memanfaatkan indeks komposit `idx_pembayaran_status_dibayar (status, dibayar_pada)` tanpa mematikan indeks akibat pemanggilan fungsi pada kolom.
     - Fungsi `pemasukan_per_tipe(int $tahun)`: menghitung distribusi pemasukan dan persentase kontribusi per tipe villa (`tipe_villa` join `reservasi` dan `pembayaran`).
     - Fungsi `daftar_tahun_laporan()`: mengembalikan rentang tahun dari transaksi lunas tertua hingga tahun berjalan WIB.
     - Fungsi `ringkasan_pemasukan(int $tahun, string $metode = 'all')`: menghasilkan metrik 4 kartu eksekutif (bulan ini, bulan lalu beserta perbandingannya, total tahun berjalan, dan rata-rata per bulan yang dibagi bulan berjalan).
  2. Pengujian Otomatis Gerbang Tahap 7:
     - Dibuat dan dieksekusi skrip pengujian `tests/laporan_test.php` dengan data fixture matematis.
     - Uji 1: Verifikasi batas bulan WIB tepat pukul `23:59:59` (masuk Jan) dan `00:00:00` (masuk Feb) serta pengabaian seluruh transaksi non-lunas -> akurat 100% [LULUS].
     - Uji 2: Filter metode pembayaran spesifik ('tunai') mengisolasi transaksi dengan tepat [LULUS].
     - Uji 3: Pemisahan transaksi lintas tahun (2025 vs 2026) akurat [LULUS].
     - Uji 4: Agregasi per tipe villa dan persentase kontribusi matematis tepat [LULUS].
     - Uji 5: Fungsi `daftar_tahun_laporan()` mengembalikan rentang tahun valid [LULUS].
     - Uji 6: Analisis `EXPLAIN` membuktikan kueri memakai indeks komposit `idx_pembayaran_status_dibayar` (type: range) tanpa full-table-scan [LULUS].







