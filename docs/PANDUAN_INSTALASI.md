# Panduan Instalasi — Villa Joglo Masayu

## Prasyarat

| Komponen       | Versi Minimal | Keterangan                          |
|---------------|--------------|--------------------------------------|
| PHP            | 8.0          | Wajib ekstensi `pdo_mysql` dan `curl` |
| MySQL/MariaDB  | 5.7 / 10.3  | Charset `utf8mb4`                    |
| Git            | 2.x          | Untuk version control                |

### Ekstensi PHP yang Diperlukan

- `pdo_mysql` — koneksi database via PDO
- `curl` — request ke BMKG API (cuaca)
- `mbstring` — penanganan string multibyte (opsional tapi disarankan)

---

## Cara 1: XAMPP / Laragon (Disarankan)

1. **Clone repository** ke folder `htdocs` (XAMPP) atau `www` (Laragon):
   ```bash
   cd C:\xampp\htdocs
   git clone <URL_REPO> villa-masayu
   ```

2. **Salin konfigurasi:**
   ```bash
   cd villa-masayu
   cp config/config.example.php config/config.php
   ```

3. **Edit `config/config.php`** — isi kredensial database:
   ```php
   'DB_HOST' => 'localhost',
   'DB_NAME' => 'villa_joglo_masayu',
   'DB_USER' => 'root',
   'DB_PASS' => '',
   ```

4. **Buat database** di phpMyAdmin atau terminal:
   ```sql
   CREATE DATABASE villa_joglo_masayu
     CHARACTER SET utf8mb4
     COLLATE utf8mb4_unicode_ci;
   ```

5. **Jalankan migrasi** (akan dibuat di tahap berikutnya):
   ```bash
   # Impor file SQL dari folder database/
   mysql -u root villa_joglo_masayu < database/migration.sql
   ```

6. **Akses di browser:**
   - XAMPP: `http://localhost/villa-masayu/`
   - Laragon: `http://villa-masayu.test/`

---

## Cara 2: PHP Built-in Server

```bash
cd "c:\path\to\website"
php -S localhost:8000
```

Buka `http://localhost:8000/` di browser.

> **Catatan:** PHP built-in server cocok untuk development.
> Untuk production, gunakan Apache/Nginx.

---

## Verifikasi Frontend

Setelah instalasi, pastikan file frontend asli tidak berubah:

```bash
sha256sum -c docs/frontend.sha256
```

Semua file harus menunjukkan status **OK**.

---

## Troubleshooting

| Masalah                         | Solusi                                                |
|--------------------------------|-------------------------------------------------------|
| `pdo_mysql` not found          | Aktifkan `extension=pdo_mysql` di `php.ini`           |
| `curl` not found               | Aktifkan `extension=curl` di `php.ini`                |
| Halaman blank / 500 error      | Cek `config/config.php` sudah ada dan benar           |
| Header/footer tidak muncul     | Pastikan `script.js` dan `style.css` ada di root      |
| Font tidak tampil              | Pastikan koneksi internet (Google Fonts via CDN)      |
