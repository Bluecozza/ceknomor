# cek.resource.my.id

Platform pelaporan dan pengecekan data penipuan di Indonesia. Memungkinkan pengguna untuk mencari dan melaporkan nomor telepon, rekening bank, serta akun dompet digital (DANA, OVO, GoPay, ShopeePay, dll) yang terindikasi penipuan.

---

## Fitur Utama

- **Pencarian instan** — cek nomor HP, rekening, atau akun e-wallet secara real-time
- **Skor risiko otomatis** — kalkulasi logaritmik berdasarkan jumlah dan severity laporan
- **6 level risiko** — Unknown → Safe → Low → Medium → High → Critical
- **Form laporan** — 4 langkah, mendukung upload bukti foto
- **Panel admin** — moderasi laporan, manajemen user, statistik, log aktivitas
- **Sistem modul** — Analytics, Sharing (OG Tags), Notification (Email), mudah dikembangkan
- **REST API** — endpoint publik & admin dengan autentikasi JWT
- **Rate limiting** — proteksi dari abuse tanpa library tambahan
- **Zero dependency** — PHP native + MySQL, tidak membutuhkan Composer

---

## Tech Stack

| Layer       | Teknologi                        |
|-------------|----------------------------------|
| Backend     | PHP 8.1+ (native, no framework)  |
| Database    | MySQL 8.0+ / MariaDB 10.6+       |
| Frontend    | Bootstrap 5.3, jQuery 3.7, AJAX  |
| Server      | Apache + mod_rewrite (.htaccess) |
| Auth        | JWT (HS256, implementasi sendiri)|

---

## Requirement

- PHP **8.1+** dengan ekstensi: `pdo`, `pdo_mysql`, `json`, `mbstring`, `openssl`, `fileinfo`
- MySQL **8.0+** atau MariaDB **10.6+**
- Apache dengan `mod_rewrite` aktif (atau Nginx dengan konfigurasi setara)
- Web server lokal: **Laragon** (Windows) / **XAMPP** / **LAMP** / **LEMP**

---

## Instalasi Cepat

### 1. Clone / Download

```bash
git clone https://github.com/youruser/cek-resource.git
# atau extract ZIP ke htdocs/www
```

### 2. Jalankan Setup Script

```bash
cd cek-resource
php setup.php
```

Script ini akan:
- Mengecek requirement PHP
- Membuat file `.env` (interaktif)
- Membuat database dan mengimport schema
- Membuat direktori yang dibutuhkan
- Generate `APP_KEY` dan `JWT_SECRET`

### 3. Atau Setup Manual

```bash
# Salin .env.example
cp .env.example .env
# Edit kredensial database
nano .env

# Import database
mysql -u root -p < database.sql

# Buat direktori upload
mkdir -p public/uploads logs storage/cache
```

### 4. Buka di Browser

```
http://localhost/cek-resource/
```

**Login admin:**
- Email: `admin@cek.resource.my.id`
- Password: `Admin@1234`

> ⚠️ **Segera ganti password default setelah login pertama!**

---

## Struktur Direktori

```
cek-resource/
├── .env.example          # Template konfigurasi environment
├── .htaccess             # URL routing + security headers
├── bootstrap.php         # Entry point, load semua core class
├── index.php             # Router halaman web
├── setup.php             # Installer CLI
├── database.sql          # Schema database lengkap + data awal
│
├── config/
│   └── config.php        # Konstanta aplikasi, DB, upload, JWT
│
├── core/                 # Core classes (no namespace)
│   ├── Database.php      # PDO wrapper (singleton)
│   ├── Response.php      # Standardized JSON responses
│   ├── Router.php        # URL router
│   ├── ModuleManager.php # Module loader & hook system
│   ├── ReportService.php # Business logic laporan
│   └── helpers.php       # Fungsi utilitas global
│
├── api/v1/               # REST API endpoints
│   ├── search/           # GET|POST /api/v1/search
│   ├── reports/          # GET|POST /api/v1/reports/{ulid}
│   ├── categories/       # GET /api/v1/categories
│   ├── auth/             # POST /api/v1/auth/login|logout
│   ├── modules/          # Admin: modul management
│   └── admin/            # Admin: stats, moderasi, users, settings
│
├── views/                # Halaman web (PHP template)
│   ├── home.php          # Halaman utama + search
│   ├── report-form.php   # Form laporan 4 langkah
│   ├── report-detail.php # Detail laporan standalone
│   ├── docs.php          # Dokumentasi API
│   └── 404.php           # Error 404
│
├── admin/
│   └── index.php         # SPA admin panel
│
├── modules/              # Plugin system
│   ├── analytics/        # Tracking pencarian & page views
│   ├── sharing/          # OG Tags + tombol share sosmed
│   └── notification/     # Email alert laporan baru
│
└── public/
    ├── uploads/          # File upload bukti laporan
    └── assets/
        ├── css/app.css   # Global stylesheet
        └── js/app.js     # Global JavaScript utilities
```

---

## API Reference

Base URL: `https://cek.resource.my.id/api/v1`

### Endpoint Publik

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| `GET` | `/search?q={query}` | Cari data berdasarkan query |
| `POST` | `/search` | Cari data (body JSON) |
| `GET` | `/reports` | Daftar laporan (approved) |
| `GET` | `/reports/{ulid}` | Detail satu laporan |
| `POST` | `/reports` | Kirim laporan baru |
| `GET` | `/categories` | Daftar kategori |
| `GET` | `/categories/report-types` | Daftar jenis laporan |

### Endpoint Admin (Require Bearer JWT)

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| `POST` | `/auth/login` | Login admin |
| `GET` | `/admin/stats` | Statistik dashboard |
| `GET` | `/admin/reports` | Daftar semua laporan |
| `POST` | `/admin/reports/{id}/moderate` | Approve/reject/flag |
| `POST` | `/admin/reports/bulk` | Bulk moderasi |
| `GET` | `/admin/users` | Daftar admin user |
| `POST` | `/admin/users` | Tambah admin user |
| `PUT` | `/admin/settings` | Update pengaturan |
| `GET` | `/admin/api-keys` | Daftar API keys |

Dokumentasi lengkap tersedia di `/docs`.

---

## Level Risiko

| Level | Skor | Keterangan |
|-------|------|------------|
| `unknown` | 0 | Belum ada laporan |
| `safe` | 1–15 | Laporan sangat minim |
| `low` | 16–35 | Risiko rendah |
| `medium` | 36–60 | Perlu waspada |
| `high` | 61–80 | Risiko tinggi |
| `critical` | 81–100 | Sangat berbahaya |

---

## Sistem Modul

Modul disimpan di `modules/{slug}/` dan terdiri dari:
- `module.json` — manifest (nama, versi, config schema, hooks)
- `Module.php` — class `{Slug}Module` dengan method `boot(array $config)`

### Membuat Modul Baru

```
modules/mymodule/
├── module.json
└── Module.php
```

```php
// modules/mymodule/Module.php
namespace Modules\Mymodule;

class MymoduleModule {
    public function boot(array $config): void {
        $manager = \Core\ModuleManager::getInstance();
        $manager->addHook('report.created', [$this, 'onReportCreated']);
    }

    public function onReportCreated(array $data): void {
        // Handle event
    }
}
```

### Hook yang tersedia

| Hook | Trigger | Data |
|------|---------|------|
| `report.created` | Laporan baru dibuat | `report_id`, `ulid`, `category`, `value`, `status` |
| `report.approved` | Laporan disetujui | `report_id`, `ulid` |
| `report.rejected` | Laporan ditolak | `report_id`, `ulid` |
| `search.performed` | Pencarian dilakukan | `query`, `normalized`, `category`, `has_result`, `count`, `ip` |
| `page.view` | Halaman web dibuka | `path`, `ip`, `user_agent`, `referrer` |
| `page.render` | Sebelum render HTML | `type`, `data` |

---

## Keamanan

- **Semua input** di-sanitize dengan `e()` (htmlspecialchars) sebelum output
- **SQL Injection** dicegah via PDO prepared statements
- **Upload file** dibatasi tipe (jpg/png/gif/webp) dan ukuran (5MB)
- **Rate limiting** berbasis file untuk mencegah brute force dan spam
- **JWT** untuk autentikasi admin (expire 24 jam)
- **Security headers** via .htaccess (X-Frame-Options, XSS-Protection, dll)
- **Direktori sensitif** diblokir via .htaccess (`/config`, `/core`, `/logs`)
- **Upload directory** dilindungi .htaccess (eksekusi PHP diblokir)
- **Password** di-hash dengan `password_hash()` (bcrypt, cost 12)

---

## Konfigurasi Production

### 1. Update `.env`

```env
APP_ENV=production
```

### 2. Apache VirtualHost

```apache
<VirtualHost *:80>
    ServerName cek.resource.my.id
    DocumentRoot /var/www/cek-resource

    <Directory /var/www/cek-resource>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/cek-resource-error.log
    CustomLog ${APACHE_LOG_DIR}/cek-resource-access.log combined
</VirtualHost>
```

### 3. Enable mod_rewrite

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 4. SSL (Let's Encrypt)

```bash
sudo certbot --apache -d cek.resource.my.id
```

---

## Cron Jobs (Opsional)

Tambahkan ke crontab untuk pembersihan data otomatis:

```cron
# Bersihkan data analytics lama (setiap hari jam 02:00)
0 2 * * * php /var/www/cek-resource/artisan.php analytics:cleanup

# Bersihkan file rate limit lama
0 3 * * * find /var/www/cek-resource/storage/cache -name "*.json" -mtime +7 -delete
```

---

## Lisensi

MIT License. Bebas digunakan dan dimodifikasi untuk keperluan apapun.

---

## Kontribusi

Pull request dan issue sangat disambut. Pastikan:
1. Fork repository
2. Buat branch fitur/fix: `git checkout -b feature/nama-fitur`
3. Commit dengan pesan deskriptif
4. Push dan buat Pull Request

---

*Platform ini dibuat untuk membantu masyarakat Indonesia dalam melindungi diri dari penipuan online. Data pada platform bersifat informatif berdasarkan laporan pengguna.*
