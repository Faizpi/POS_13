# Implementation Report 15 — Excel Export, Stok Page, Tabs, Customer Portal Complete

**Tanggal:** 7 Juni 2026  
**Baseline masuk:** 88 tests · 188 routes · 238 assertions  
**Status keluar:** 88 tests · ≥190 routes · 238 assertions · ALL PASS ✅

---

## Gap yang Ditutup dalam Pass Ini

### 1. Excel Export (`maatwebsite/excel` v3.1.68)

**Installed:** `composer require maatwebsite/excel`

**File baru:**
- `app/Exports/TransactionsExport.php` — export transaksi ke Excel via Blade views
- `app/Exports/StokExport.php` — export stok gudang ke Excel

**Fitur:**
- Auto-size kolom
- Header row bold
- Nomor telepon sebagai TEXT (tidak dikonversi Excel)
- Mendukung tipe: penjualan, pembelian, biaya, kunjungan, pembayaran, all

**Export fungsional di:**
- `DashboardController::export()` — sekarang benar-benar return file (PDF atau XLSX)
- `DashboardController::buildExportData()` — query data sesuai tipe dan filter
- `StokPage` action "Export Excel" — export stok per gudang

**Fix syntax bug:** `buildExportData` dan `downloadLampiran` ada syntax error (missing method name) — diperbaiki.

---

### 2. Stok Custom Page Filament

**File baru:**
- `app/Filament/Pages/StokPage.php`
- `app/Filament/Pages/StokLogPage.php`
- `resources/views/filament/pages/stok.blade.php`
- `resources/views/filament/pages/stok-log.blade.php`

**StokPage features:**
- Accordion per gudang (collapsible sections)
- Tabel stok: Produk, Item Code, Penjualan, Gratis, Sample, Total
- Footer per gudang: sum per kolom
- Header actions:
  - "Riwayat Perubahan" → link ke StokLogPage
  - "Export Excel" → modal pilih gudang → download XLSX
  - "Update Stok" (super_admin only) → modal form + log perubahan

**StokLogPage features:**
- Full Filament Table dengan InteractsWithTable
- Filters: Gudang, Produk, Date range (dari-sampai)
- Columns: Waktu, Produk, Gudang, Sebelum, Sesudah, Selisih (badge color), Diubah Oleh, Keterangan
- Admin scoped ke gudang sendiri

**Fix Filament 5 compatibility:**
- `$navigationGroup` must be `string|UnitEnum|null` (tidak bisa `?string`)
- `$navigationIcon` must be `string|BackedEnum|null` (tidak bisa `?string`)
- `$view` must be non-static property (tidak bisa `protected static string $view`)

---

### 3. Status Tabs di Semua List Pages

Semua List pages sekarang punya **tab filter status**:

| Resource | Tabs |
|----------|------|
| Penjualan | Semua, Pending (⚠️), Approved (🔵), Lunas (✅), Canceled (⬜) |
| Pembelian | Semua, Pending, Approved, Canceled |
| Biaya | Semua, Pending, Approved, Canceled |
| Kunjungan | Semua, Pending, Approved, Canceled |
| Pembayaran | Semua, Pending, Approved, Canceled |
| PenerimaanBarang | Semua, Pending, Approved, Canceled |

**Fix:** Filament 5 Tab class adalah `Filament\Schemas\Components\Tabs\Tab` (bukan `Filament\Resources\Components\Tab`).

---

### 4. Customer Portal Lengkap

**File baru:**
- `app/Http/Controllers/CustomerPortalController.php` — port persis dari lama
- `app/Http/Middleware/CustomerAuth.php` — session-based auth
- `resources/views/customer/login.blade.php` — form no. telepon
- `resources/views/customer/pin.blade.php` — form PIN 6 digit
- `resources/views/customer/dashboard.blade.php`
- `resources/views/customer/history.blade.php`
- `resources/views/customer/history-detail.blade.php`
- `resources/views/customer/kunjungan.blade.php`
- `resources/views/customer/kunjungan-detail.blade.php`
- `resources/views/customer/layouts/app.blade.php`

**Routes:** 9 routes di `/customer/*` dengan middleware `customer.auth`

**Session keys (backward compatible):** `customer_id`, `customer_no_telp`, `customer_nama`

**Phone normalization:** `08xxx` → `628xxx`, `8xxx` → `628xxx`, `+628xxx` → `628xxx`

---

### 5. Report Views Lengkap

**File baru di `resources/views/reports/`:**
- `penjualan.blade.php` — full columns termasuk no telepon (TEXT format)
- `pembelian.blade.php`
- `biaya.blade.php`
- `kunjungan.blade.php`
- `pembayaran.blade.php`
- `transactions.blade.php` — all transactions merged
- `daily-report.blade.php` — HTML untuk PDF laporan harian
- `stok.blade.php` — tabel stok per produk per gudang
- `pdf.blade.php` — generic landscape PDF report

---

### 6. Email Templates Lengkap

**File baru di `resources/views/emails/`:**
- `transaksi-notification.blade.php` — notification email (created/needs_approval/approved)
- `transaksi-invoice.blade.php` — invoice attachment email
- `invoice-penjualan.blade.php` → extends notification
- `invoice-pembelian.blade.php` → extends notification
- `invoice-biaya.blade.php` → extends notification
- `invoice-kunjungan.blade.php` → extends notification

---

## Status Fitur vs Proyek Lama

| Fitur | Proyek Lama | Proyek Baru |
|-------|------------|-------------|
| Auth web (Filament) | ✅ Login/logout | ✅ Filament built-in |
| Auth API mobile | ✅ SHA-256 token | ✅ Port identik |
| Customer portal | ✅ Phone+PIN | ✅ Selesai pass ini |
| Dashboard | ✅ Charts + stats | ✅ Lebih modern (Filament widgets) |
| Penjualan CRUD | ✅ Full | ✅ Full |
| Pembelian CRUD | ✅ Full | ✅ Full |
| Biaya CRUD | ✅ Full | ✅ Full |
| Kunjungan CRUD | ✅ Full | ✅ Full |
| Pembayaran CRUD | ✅ Full | ✅ Full |
| Penerimaan Barang CRUD | ✅ Full | ✅ Full |
| Kontak CRUD | ✅ Full | ✅ Full |
| Produk CRUD | ✅ Super admin | ✅ Super admin |
| Users CRUD | ✅ Super admin | ✅ Super admin |
| Gudang CRUD | ✅ Super admin | ✅ Super admin |
| Stok page | ✅ Accordion + form | ✅ Filament custom page |
| Stok log | ✅ Table + filters | ✅ Filament table |
| Public invoice (view) | ✅ Per modul | ✅ Generic + functional |
| Public invoice (PDF) | ✅ Per modul | ✅ Selesai pass 14 |
| Print A4 | ✅ Per modul | ✅ Generic template |
| Struk thermal 58mm | ✅ Per modul | ✅ Generic compact template |
| Bluetooth print | ✅ bluetooth-print.js | ✅ Copy utuh 1957 baris |
| Barcode scanner | ✅ html5-qrcode | 🔜 Belum di Filament forms |
| Excel export | ✅ Per tipe | ✅ Selesai pass ini |
| PDF export | ✅ Per tipe | ✅ Dompdf + views |
| Email notification | ✅ InvoiceEmailService | ✅ Controller + templates ada |
| Lampiran delete | ✅ Per index | ✅ Route + controller ada |
| Status tabs | ✅ Sidebar filter | ✅ Filament tabs |
| QR Code | ✅ Modal | ✅ Filament modal |
| API docs | ✅ /docs | 🔜 Belum |

---

## Gap Tersisa

| Gap | Prioritas | Estimasi |
|-----|-----------|----------|
| Barcode/QR scanner di Filament forms | High | 1 hari |
| API Docs page `/docs` | Low | 2 jam |
| maatwebsite/excel manual service provider register | Medium | 30 menit |
| Delete lampiran di ViewRecord (UI) | Medium | 1 jam |
| Profile page custom (no_telp, alamat) | Low | 2 jam |
| Browser smoke test full flow | High | 1 hari |
| Mobile app live test ke staging | High | 1 hari |
| Public invoice visual parity vs lama | Medium | 1 hari |

---

## Notes Teknis

### maatwebsite/excel Service Provider
Jika Excel export gagal dengan "Class not found", tambahkan manual di `config/app.php`:
```php
'providers' => [
    // ...
    Maatwebsite\Excel\ExcelServiceProvider::class,
],
'aliases' => [
    // ...
    'Excel' => Maatwebsite\Excel\Facades\Excel::class,
],
```
Atau jalankan `php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider"`.

### Filament 5 Type Compatibility
Properti navigation di custom Pages harus menggunakan union types:
- `$navigationIcon`: `string|BackedEnum|null` (bukan `?string`)
- `$navigationGroup`: `string|UnitEnum|null` (bukan `?string`)
- `$view`: non-static property (bukan `static`)
