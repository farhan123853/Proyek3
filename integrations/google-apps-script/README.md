# Integrasi Verifikasi Email

Layanan pengiriman email verifikasi menggunakan Google Apps Script dan
`MailApp`. Endpoint `doPost(e)` menerima permintaan dari aplikasi PHP,
memeriksa shared secret, lalu mengirim email verifikasi.

Implementasi Apps Script berada di [`google-apps-script/Code.gs`](google-apps-script/Code.gs).

## Konfigurasi Google Apps Script

1. Buat proyek Google Apps Script dan salin isi `Code.gs` ke proyek tersebut.
2. Buka **Project Settings** dan tambahkan Script Properties berikut:

   | Property | Nilai |
   | --- | --- |
   | `EMAIL_API_SECRET` | Secret acak yang kuat dan hanya diketahui aplikasi serta proyek Apps Script |
   | `APP_BASE_URL` | URL dasar publik aplikasi, tanpa garis miring di akhir |

   `APP_BASE_URL` harus sama dengan URL aplikasi yang digunakan PHP untuk
   membentuk tautan verifikasi, misalnya `https://example.com`. Jangan gunakan
   URL lokal yang tidak dapat dijangkau penerima email.

3. Pilih **Deploy > New deployment**, pilih tipe **Web app**, lalu deploy agar
   tersedia endpoint HTTP. Atur identitas eksekusi dan akses Web App sesuai
   kebutuhan deployment; aplikasi PHP harus dapat mengirim request ke endpoint.
4. Salin URL deployment Web App yang berakhiran `/exec`. Atur URL ini sebagai
   `EMAIL_API_URL` di `config/config.php`.

## Konfigurasi aplikasi PHP

Isi konfigurasi lokal `config/config.php` (jangan commit berkas ini):

- `EMAIL_API_URL`: URL deployment Web App Apps Script yang berakhiran `/exec`.
- `EMAIL_API_SECRET`: nilai yang sama dengan Script Property `EMAIL_API_SECRET`.
- `APP_URL`: URL dasar aplikasi yang sama dengan Script Property `APP_BASE_URL`.

Gunakan `config/config.example.php` hanya sebagai template; jangan menaruh nilai
secret yang sebenarnya di template atau berkas lain yang dilacak Git.

## Alur pengiriman

Ketika pengguna meminta email verifikasi, aplikasi PHP mengirim **HTTP POST**
ke `EMAIL_API_URL` dengan `Content-Type: application/json`. Body memuat action
`send_verification`, shared secret, alamat dan nama penerima, serta tautan
verifikasi yang dibuat aplikasi. Apps Script memvalidasi secret dan memastikan
tautan menggunakan `APP_BASE_URL` sebelum memanggil `MailApp.sendEmail()`.

## Keamanan secret

Jangan masukkan `EMAIL_API_SECRET` ke source code, README, konfigurasi contoh,
riwayat commit, atau GitHub. Simpan nilainya hanya di Script Properties dan
konfigurasi lokal/deployment PHP yang tidak dilacak Git. Jika secret terpapar,
ganti nilainya di kedua tempat.