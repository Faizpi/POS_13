# Implementation Guide - Laravel 13 Rebuild

Dokumen ini adalah panduan implementasi konkret untuk membangun proyek Laravel 13 baru berdasarkan hasil audit dokumen 01-07. Setiap fase memiliki langkah teknis, file yang harus dibuat, dan acceptance criteria.

## Prerequisites

Sebelum memulai implementasi:

1. **PHP 8.3+** terinstall dan aktif di PATH.
2. **Composer 2.x** tersedia.
3. **Node.js 20+** dan npm/pnpm tersedia.
4. **MySQL 8.0+** atau MariaDB 10.6+ tersedia lokal untuk development.
5. **Database staging/dev** yang merupakan clone/restore dari production (read-only OK untuk awal).
6. **Git** initialized di `sales_hibiscusefsya_laravel13`.

## Fase 1: Scaffold Laravel 13

### Langkah

```bash
# Di folder parent
cd "c:\Projek Website\Hibiscusefsya POS\sales_hibiscusefsya_laravel13"

# Install Laravel 13 via composer create-project
composer create-project laravel/laravel . "^13.0"

# Atau jika folder sudah ada, init dari skeleton:
composer init --name="hibiscusefsya/pos" --type="project"
composer require laravel/framework:^13.0
```

### File Konfigurasi yang Harus Disesuaikan

```
.env                          → DB, mail, app URL, session, cache
config/app.php                → timezone Asia/Jakarta, locale id
config/database.php           → MySQL connection ke DB lama/staging
config/auth.php               → Guard web session, custom API guard
config/cors.php               → Port dari fruitcake config lama
config/filesystems.php        → Disk public untuk lampiran/avatar
config/session.php            → Cookie name, lifetime, domain (match lama)
config/cache.php              → Prefix eksplisit agar tidak konflik
config/mail.php               → Symfony Mailer config dari env lama
```

### Dependencies yang Harus Dipasang

```bash
composer require barryvdh/laravel-dompdf:^3.1
composer require maatwebsite/excel:^3.1
composer require milon/barcode:^13.0
composer require laravel/tinker:^3.0

# Auth scaffolding - opsi A: laravel/ui untuk compatibility
composer require laravel/ui:^4.6
php artisan ui bootstrap --auth

# ATAU opsi B: route auth manual tanpa laravel/ui
# (buat route manual di routes/web.php)

# Dev dependencies
composer require --dev phpunit/phpunit:^12.0
composer require --dev fakerphp/faker
composer require --dev mockery/mockery
```

### Vite Setup

```bash
npm install
npm install bootstrap@4 jquery popper.js sass
```

```js
// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/sass/app.scss', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    resolve: {
        alias: {
            'jquery': 'jquery/dist/jquery.js',
        }
    }
});
```

### Acceptance Criteria Fase 1

- [ ] `php artisan serve` berjalan tanpa error.
- [ ] `php artisan test` (empty test suite) pass.
- [ ] Database connection berhasil (read).
- [ ] `.env` configured dengan APP_URL, DB, MAIL.

---

## Fase 2: Models dan Schema

### Struktur Folder

```
app/Models/
├── User.php
├── Gudang.php
├── GudangProduk.php
├── Produk.php
├── Kontak.php
├── Penjualan.php
├── PenjualanItem.php
├── Pembelian.php
├── PembelianItem.php
├── Biaya.php
├── BiayaItem.php
├── Kunjungan.php
├── KunjunganItem.php
├── Pembayaran.php
├── PenerimaanBarang.php
├── PenerimaanBarangItem.php
├── PersonalAccessToken.php
└── StokLog.php
```

### Strategi Migration

**Opsi A (Recommended untuk rebuild):** Satu migration per tabel yang merepresentasikan schema FINAL.

```
database/migrations/
├── 0001_01_01_000001_create_gudangs_table.php
├── 0001_01_01_000002_create_produks_table.php
├── 0001_01_01_000003_create_gudang_produk_table.php
├── 0001_01_01_000004_create_users_table.php
├── 0001_01_01_000005_create_password_resets_table.php
├── 0001_01_01_000006_create_personal_access_tokens_table.php
├── 0001_01_01_000007_create_admin_gudang_table.php
├── 0001_01_01_000008_create_spectator_gudang_table.php
├── 0001_01_01_000009_create_kontaks_table.php
├── 0001_01_01_000010_create_penjualans_table.php
├── 0001_01_01_000011_create_penjualan_items_table.php
├── 0001_01_01_000012_create_pembelians_table.php
├── 0001_01_01_000013_create_pembelian_items_table.php
├── 0001_01_01_000014_create_biayas_table.php
├── 0001_01_01_000015_create_biaya_items_table.php
├── 0001_01_01_000016_create_kunjungans_table.php
├── 0001_01_01_000017_create_kunjungan_items_table.php
├── 0001_01_01_000018_create_pembayarans_table.php
├── 0001_01_01_000019_create_penerimaan_barangs_table.php
├── 0001_01_01_000020_create_penerimaan_barang_items_table.php
├── 0001_01_01_000021_create_stok_logs_table.php
└── 0001_01_01_000022_add_indexes.php
```

**Opsi B (Untuk produksi dengan DB existing):** Jangan jalankan migration ke DB production. Gunakan `--pretend` atau set `migrations` table sudah terisi. Cukup definisikan model tanpa migrate.

### Model Template - User.php

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'alamat', 'no_telp',
        'avatar', 'gudang_id', 'current_gudang_id',
        'receives_transaction_email', 'can_export_pdf', 'can_export_excel',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'receives_transaction_email' => 'boolean',
        'can_export_pdf' => 'boolean',
        'can_export_excel' => 'boolean',
    ];

    protected $appends = ['avatar_url'];

    // Port semua method dari app/User.php lama
    // - gudang(), gudangs(), spectatorGudangs()
    // - getCurrentGudang(), canAccessGudang()
    // - isSpectator(), isSuperAdmin(), isAdmin()
    // - canExportPdf(), canExportExcel(), canExportReport()
    // - getAvatarUrlAttribute()
    // - getAvailableRoles()
}
```

### Acceptance Criteria Fase 2

- [ ] Semua 18 model dibuat dengan fillable, casts, relations yang match.
- [ ] `php artisan tinker` bisa query tabel lewat model.
- [ ] Model test (unit) untuk relations dan casts pass.
- [ ] Table name eksplisit di model jika tidak konvensi Laravel.

---

## Fase 3: Middleware dan Auth

### File yang Harus Dibuat

```
app/Http/Middleware/
├── ApiTokenAuth.php          → Port persis dari lama
├── CheckRole.php             → Port persis dari lama
├── CustomerAuth.php          → Port persis dari lama
└── CustomerSubdomainRedirect.php → Port dari lama
```

### Registrasi Middleware (Laravel 13 style)

Di Laravel 13, middleware diregister di `bootstrap/app.php`:

```php
// bootstrap/app.php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'customer.auth' => \App\Http\Middleware\CustomerAuth::class,
            'api.token' => \App\Http\Middleware\ApiTokenAuth::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\CustomerSubdomainRedirect::class,
        ]);

        $middleware->api(prepend: [
            // throttle sudah default
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
```

### Auth Route Manual (jika tidak pakai laravel/ui)

```php
// routes/web.php - Auth routes
Route::get('login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout'])->name('logout');
Route::get('password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('password/reset', [ResetPasswordController::class, 'reset'])->name('password.update');
```

### Acceptance Criteria Fase 3

- [ ] Login web berhasil dengan email/password dari DB.
- [ ] API login `POST /api/v1/login` mengembalikan token.
- [ ] Protected API endpoint menolak tanpa token (401).
- [ ] Protected API endpoint menolak expired token (401).
- [ ] `role:admin` middleware mengizinkan admin dan spectator.
- [ ] `role:super_admin` hanya mengizinkan super_admin.
- [ ] Customer portal login via phone + PIN berhasil set session.

---

## Fase 4: API Routes dan Controllers

### Struktur

```
app/Http/Controllers/Api/
├── AuthController.php
├── DashboardController.php
├── GudangController.php
├── ProdukController.php
├── KontakController.php
├── PenjualanController.php
├── PembelianController.php
├── BiayaController.php
├── KunjunganController.php
├── PembayaranController.php
├── PenerimaanBarangController.php
├── PrintController.php
├── StokController.php
└── UserController.php
```

### Route File API

```php
// routes/api.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api;

// Public
Route::prefix('v1')->group(function () {
    Route::post('login', [Api\AuthController::class, 'login']);
});

// Protected
Route::prefix('v1')->middleware('api.token')->group(function () {
    // Port semua route dari routes/api.php lama
    // PENTING: urutan route sama persis
});
```

### Acceptance Criteria Fase 4

- [ ] `php artisan route:list --path=api/v1` menampilkan semua 80+ endpoint.
- [ ] Setiap controller method minimal mengembalikan response shape yang benar.
- [ ] Contract test untuk login/profile/logout pass.

---

## Fase 5: Business Logic Per Modul

### Urutan Port

1. **Gudang** - CRUD sederhana, switch gudang.
2. **Produk** - CRUD + role scoping.
3. **Kontak** - CRUD + kode auto + role scoping.
4. **Stok** - Read + manual update + log.
5. **Penjualan** - CRUD + stock check + approval flow + mark paid.
6. **Pembelian** - CRUD + approval flow.
7. **Penerimaan Barang** - Create + approve (adds stock) + cancel (removes stock).
8. **Biaya** - CRUD + approval + direct approved super admin.
9. **Kunjungan** - CRUD + promo stock validation + approval.
10. **Pembayaran** - Create + approve (can mark penjualan Lunas) + cancel.

### Pattern untuk Setiap Controller Method

```php
// Pattern store penjualan (contoh)
public function store(Request $request)
{
    // 1. Validate - sama persis dengan rule lama
    // 2. Check role/gudang access
    // 3. Check stock availability
    // 4. DB::transaction
    //    - Generate nomor
    //    - Create header
    //    - Create items
    //    - Deduct stock (jika rule lama begitu)
    //    - Upload lampiran
    // 5. Send email notification (async)
    // 6. Return 201 {message, data}
}
```

### Acceptance Criteria Fase 5

- [ ] Contract test per modul pass (lihat 07-test-plan.md).
- [ ] Stock side effects verified.
- [ ] Status transition verified.
- [ ] Role scoping verified.

---

## Fase 6: Web Routes dan Blade

### Struktur Views

```
resources/views/
├── layouts/
│   └── app.blade.php         → Layout utama Bootstrap 4
├── auth/                     → Login/register/reset
├── dashboard.blade.php
├── penjualan/
│   ├── index.blade.php
│   ├── create.blade.php
│   ├── edit.blade.php
│   ├── show.blade.php
│   └── print.blade.php
├── pembelian/                → Same pattern
├── biaya/                    → Same pattern
├── kunjungan/                → Same pattern
├── pembayaran/               → Same pattern
├── penerimaan-barang/        → Same pattern
├── kontak/
├── produk/
├── gudang/
├── users/
├── stok/
├── profil/
├── customer/                 → Portal customer
├── public/                   → Public invoice views
├── pdf/                      → PDF templates
├── emails/                   → Email templates
├── print/                    → Print/struk templates
└── reports/                  → Excel export views
```

### Acceptance Criteria Fase 6

- [ ] Semua named routes dari 04-web-route-map.md exist.
- [ ] Dashboard renders untuk setiap role.
- [ ] CRUD forms work di staging.
- [ ] Public invoice accessible tanpa auth.
- [ ] Print/PDF/struk endpoints return correct content type.

---

## Fase 7: Email, PDF, Export, Print

### File yang Harus Dibuat

```
app/Services/InvoiceEmailService.php
app/Mail/TransaksiInvoiceMail.php
app/Mail/TransaksiNotificationMail.php
app/Exports/TransactionsExport.php
app/Exports/StokExport.php
app/Helpers/helpers.php           → autoload via composer.json
app/Helpers/EscPosHelper.php
```

### Composer Autoload

```json
{
    "autoload": {
        "files": [
            "app/Helpers/helpers.php"
        ],
        "psr-4": {
            "App\\": "app/"
        }
    }
}
```

### Acceptance Criteria Fase 7

- [ ] PDF invoice renders sama visual dengan lama.
- [ ] Excel export menghasilkan file dengan column format benar.
- [ ] Email terkirim saat create/approve.
- [ ] Bluetooth JSON response keys match.
- [ ] ESC/POS output byte-compatible.

---

## Fase 8: Testing dan QA

### Test Structure

```
tests/
├── Unit/
│   ├── Models/
│   │   ├── UserTest.php
│   │   ├── PenjualanTest.php
│   │   └── ...
│   └── Helpers/
│       ├── HelpersTest.php
│       └── EscPosHelperTest.php
├── Feature/
│   ├── Api/
│   │   ├── AuthTest.php
│   │   ├── PenjualanTest.php
│   │   ├── PembelianTest.php
│   │   └── ...
│   ├── Web/
│   │   ├── DashboardTest.php
│   │   └── ...
│   └── Contract/
│       ├── ApiResponseShapeTest.php
│       └── ...
└── Fixtures/
    └── api-v1/
        ├── login-success.json
        ├── login-failure.json
        └── ...
```

### Acceptance Criteria Fase 8

- [ ] 100% API endpoints have contract tests.
- [ ] Role permission matrix tested.
- [ ] Stock side effects asserted.
- [ ] Email dispatch asserted (Mail::fake).
- [ ] PDF generation doesn't throw.

---

## Fase 9: Staging Parallel Run

### Langkah

1. Deploy Laravel 13 ke staging server.
2. Point ke clone database production (read-write OK di staging).
3. Test mobile app lama terhadap staging.
4. Compare API response side-by-side.
5. Run full test suite.
6. Fix discrepancies.

### Acceptance Criteria Fase 9

- [ ] Mobile app login successful.
- [ ] Mobile app browse transactions.
- [ ] Mobile app create penjualan.
- [ ] Mobile app approve flow.
- [ ] Mobile app payment flow.
- [ ] Mobile app print/QR.
- [ ] Web admin semua fungsi utama.
- [ ] No data corruption.

---

## Fase 10: Cutover Production

### Checklist Pre-Cutover

- [ ] PHP 8.3+ on production server.
- [ ] All dependencies installed.
- [ ] .env production configured.
- [ ] Storage symlink created.
- [ ] Queue worker configured (if using queue).
- [ ] Cron/scheduler configured (if applicable).
- [ ] SSL/domain configured.
- [ ] DNS ready for switch.
- [ ] Rollback plan documented.
- [ ] Database backup taken.
- [ ] Monitoring alerts configured.

### Cutover Steps

1. Maintenance mode on old app.
2. Final database backup.
3. Switch DNS/proxy to Laravel 13.
4. Verify health check endpoint.
5. Test critical path (login, create penjualan, approve, payment).
6. Monitor error logs 1 hour.
7. If OK, remove maintenance mode notice.
8. Keep old app ready for instant rollback 72 hours.

---

## Timeline Estimasi

| Fase | Durasi Estimasi | Dependency |
| --- | --- | --- |
| 1. Scaffold | 1 hari | PHP 8.3 ready |
| 2. Models/Schema | 2-3 hari | Fase 1 |
| 3. Auth/Middleware | 2 hari | Fase 2 |
| 4. API Routes | 1-2 hari | Fase 3 |
| 5. Business Logic | 7-10 hari | Fase 4 |
| 6. Web/Blade | 5-7 hari | Fase 5 |
| 7. Email/PDF/Print | 3-4 hari | Fase 5 |
| 8. Testing/QA | 5-7 hari | Fase 5-7 |
| 9. Staging | 3-5 hari | Fase 8 |
| 10. Cutover | 1 hari | Fase 9 |
| **Total** | **30-42 hari kerja** | |

Catatan: timeline bisa lebih pendek jika dikerjakan per modul secara parallel (API + Web), atau lebih panjang jika ditemukan incompatibility yang memerlukan adapter.

---

## File Checklist Lengkap

### Root Config

- [ ] `composer.json`
- [ ] `vite.config.js`
- [ ] `package.json`
- [ ] `.env.example`
- [ ] `bootstrap/app.php`

### Routes

- [ ] `routes/api.php` - Semua 80+ endpoint
- [ ] `routes/web.php` - Semua web routes
- [ ] `routes/console.php` - Artisan commands jika ada

### Models (18 files)

- [ ] User, Gudang, GudangProduk, Produk, Kontak
- [ ] Penjualan, PenjualanItem
- [ ] Pembelian, PembelianItem
- [ ] Biaya, BiayaItem
- [ ] Kunjungan, KunjunganItem
- [ ] Pembayaran
- [ ] PenerimaanBarang, PenerimaanBarangItem
- [ ] PersonalAccessToken, StokLog

### Middleware (4 custom)

- [ ] ApiTokenAuth, CheckRole, CustomerAuth, CustomerSubdomainRedirect

### API Controllers (14 files)

- [ ] Auth, Dashboard, Gudang, Produk, Kontak
- [ ] Penjualan, Pembelian, Biaya, Kunjungan
- [ ] Pembayaran, PenerimaanBarang
- [ ] Print, Stok, User

### Web Controllers (24+ files)

- [ ] Dashboard, Profile, BluetoothPrint, Print, PrintImage
- [ ] Penjualan, Pembelian, Biaya, Kunjungan
- [ ] Pembayaran, PenerimaanBarang
- [ ] Kontak, Produk, Gudang, User, Stok
- [ ] CustomerPortal, PublicInvoice, PublicReceipt
- [ ] ApiDoc, AdminGudang, SpectatorGudang

### Services & Helpers

- [ ] `app/Services/InvoiceEmailService.php`
- [ ] `app/Helpers/helpers.php`
- [ ] `app/Helpers/EscPosHelper.php`
- [ ] `app/Exports/TransactionsExport.php`
- [ ] `app/Exports/StokExport.php`
- [ ] `app/Mail/TransaksiInvoiceMail.php`
- [ ] `app/Mail/TransaksiNotificationMail.php`

### Views (25+ directories)

- [ ] layouts, auth, dashboard, profil
- [ ] penjualan, pembelian, biaya, kunjungan, pembayaran, penerimaan-barang
- [ ] kontak, produk, gudang, users, stok
- [ ] customer, public, pdf, emails, print, reports
- [ ] api-docs, errors, partials

### Migrations (22 files)

- [ ] Semua tabel dari 02-database-map.md

---

## Aturan Implementasi

1. **Jangan refactor** business logic. Port as-is dulu.
2. **Jangan rename** tabel atau kolom.
3. **Jangan ubah** response JSON shape.
4. **Jangan tambah** package baru yang mengubah flow.
5. **Test dulu**, baru commit.
6. **Satu modul per branch**, merge setelah test pass.
7. **Snapshot baseline** sebelum port setiap modul.
8. **Document** setiap deviation dari kode lama.
