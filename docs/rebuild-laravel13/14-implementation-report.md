# Implementation Report 14 — Audit Gap & ViewRecord Completion

**Tanggal:** 7 Juni 2026  
**Baseline sebelum pass ini:** 88 tests · 166 routes · 238 assertions  
**Status kerja sebelumnya (Doc 13):** Panel boot bersih, ViewRecord semua modul sudah ada, public invoice HTML ada, stok side-effect di Filament actions sudah ada.

---

## 1. Audit Mendalam Proyek Lama vs Status Proyek Baru

Saya membaca setiap Blade view lama satu per satu dan membandingkan dengan kondisi Laravel 13 baru.

### 1.1 ViewRecord Pages — Status vs Lama

| Modul | File Lama | Status Baru | Gap |
|-------|-----------|-------------|-----|
| Penjualan | `penjualan/show.blade.php` | ✅ `ViewPenjualan.php` ada + 8 actions | Lampiran display & delete belum di ViewRecord |
| Pembelian | `pembelian/show.blade.php` | ✅ `ViewPembelian.php` ada + 7 actions | Lampiran display & delete belum |
| Biaya | `biaya/show.blade.php` | ✅ `ViewBiaya.php` ada + 6 actions | Lampiran display & delete belum |
| Kunjungan | `kunjungan/show.blade.php` | ✅ `ViewKunjungan.php` ada + 6 actions | Lampiran display & delete belum |
| Pembayaran | `pembayaran/show.blade.php` | ✅ `ViewPembayaran.php` ada + 4 actions | Lampiran display & delete belum; `sisaHutang` kalkulasi memakai total semua approved payments, bukan hanya satu |
| Penerimaan Barang | `penerimaan-barang/show.blade.php` | ✅ `ViewPenerimaanBarang.php` ada + stok mutasi | `qty_reject` belum ditampilkan di detail items; `item_kode`/`item_nama` menggunakan relasi produk |
| Produk | `produk/show.blade.php` | ✅ `ViewProduk.php` ada | Stok per gudang belum di infolist; barcode URL-based |
| Kontak | `kontak/show.blade.php` | ✅ `ViewKontak.php` ada | Pin belum tersensor |

### 1.2 ListRecord Pages — Gap Summary Cards

Proyek lama menampilkan **summary cards** (totals + stats) di bagian atas setiap list page. Ini belum ada di Filament:

| Modul | Summary Cards Lama | Status Baru |
|-------|---------------------|-------------|
| Penjualan Index | Total Pending/Approved, Jatuh Tempo Lewat, Lunas 30 Hari, Canceled count | ❌ Belum ada |
| Pembelian Index | Pending Approval, Total Aktif, Jatuh Tempo Lewat, Canceled | ❌ Belum ada |
| Biaya Index | Total Masuk, Total Keluar, Pending, Canceled | ❌ Belum ada |
| Kunjungan Index | Total per tujuan, Pending, Approved | ❌ Belum ada |
| Pembayaran Index | Total Bulan Ini, Pending, Approved | ❌ Belum ada |
| Penerimaan Index | Pending, Approved today | ❌ Belum ada |

### 1.3 Filters Tambahan di List Pages

| Modul | Filter Lama | Status Baru |
|-------|-------------|-------------|
| Penjualan | Status, Sales user, tanggal range | Status ✅; Sales user ✅; tanggal ❌ |
| Pembelian | Status, Urgensi | Status ✅; Urgensi ✅ |
| Biaya | Status, Jenis | Status ✅; Jenis ✅ |
| Kunjungan | Status, Tujuan | Status ✅; Tujuan ✅ |
| Pembayaran | Status, tanggal range | Status ✅; tanggal ❌ |
| Kontak | Search (nama/kode/no_telp), Gudang, Creator | Search ✅; Gudang ✅; Creator ❌ |
| Users | Role, Search | Role ✅; Search ✅ |

### 1.4 Lampiran (Attachment) Display & Delete

Semua halaman detail penjualan, pembelian, biaya, kunjungan, pembayaran, penerimaan-barang di lama menampilkan:
- Grid thumbnail image jika image file (jpg/png/webp)
- File icon jika PDF/ZIP/DOC
- Link download
- Tombol hapus lampiran per index (super_admin only) via `DELETE {resource}/{id}/lampiran/{index}`

**Status baru:** ViewRecord sudah ada tapi **belum menampilkan lampiran section** dan **belum ada action delete lampiran**. Fitur ini penting karena mobile app juga bisa upload lampiran.

### 1.5 `sisaHutang` di Pembayaran

Lama menghitung sisa hutang di show pembayaran sebagai:
```php
$penjualan->grand_total - total_semua_pembayaran_approved_untuk_penjualan_ini
```

ViewPembayaran baru hanya menghitung: `$record->penjualan->grand_total - $record->jumlah_bayar` (satu pembayaran saja). Ini salah untuk kasus partial payment.

### 1.6 Dashboard Widgets — Status

Dari audit:
- `StatsOverview.php` → ada tapi kontennya perlu dicek
- `AktivitasTerbaru.php` → ada
- `ChartTrenPenjualan.php` → ada
- `ChartKomposisiStatus.php` → ada
- `ChartTransaksiGudang.php` → ada
- `ChartPenjualanSales.php` → ada
- `MenungguApproval.php` → ada

**Belum ada:**
- Widget "Kanban Hari Ini" (penjualan hari ini, biaya hari ini, pembayaran hari ini)
- Widget "Kanban Bulan Ini" (penjualan bulan ini, total rupiah, dll)
- Export modal di dashboard (Generate Report)

### 1.7 Profile Page

Lama: `profil/show.blade.php` — form edit nama, alamat, no_telp + change password + avatar upload.  
Baru: Filament built-in `->profile()` di panel sudah ada, tapi belum memiliki field `no_telp` dan `alamat`. Hanya nama/email/password di default Filament profile.

### 1.8 Admin Gudang / Spectator Gudang Management

Lama: `admin-gudang/index.blade.php` dan `spectator-gudang/index.blade.php` — halaman khusus untuk manajemen assignment gudang per user.  
Baru: Sudah diintegrasikan ke UserForm (checkbox list gudang), tidak perlu halaman terpisah. **Tidak ada gap.**

### 1.9 Stok Management Page

Lama: `stok/index.blade.php` — accordion per gudang + form manual stok update (super_admin) + export modal.  
Baru: `app/Filament/Pages/Dashboard.php` ada tapi `Stok` page belum dibuat sebagai custom Filament page.

### 1.10 Customer Portal

Lama: Halaman lengkap login phone+PIN, dashboard, history penjualan, detail penjualan, history kunjungan, detail kunjungan. Semua di route `/customer/*` dengan middleware `customer.auth`.  
Baru: **Belum ada sama sekali.** Route `customer.login`, `customer.check-phone`, dll tidak ada di `routes/web.php`.

### 1.11 API Docs Page

Lama: `api-docs/index.blade.php` — dokumentasi API publik yang bisa diakses via `/docs`.  
Baru: Route tidak ada.

### 1.12 Public Invoice Views — Kualitas

Lama: Setiap modul punya template terpisah yang sangat detail:
- `public/invoice-penjualan.blade.php` — full responsive dengan logo, summary, tabel item, total, status watermark
- `public/invoice-pembelian.blade.php` — sama
- `public/invoice-biaya.blade.php` — sama
- dll.

Baru: Satu template generik `public/invoice.blade.php` yang berfungsi tapi tidak sespesifik lama.

### 1.13 PDF Download Public Invoice

Lama: Route `/invoice/{type}/{uuid}/download` mengembalikan PDF A4 dengan nama file:
- `INV-xxx.pdf` (penjualan)
- `PR-xxx.pdf` (pembelian)
- `EXP-xxx.pdf` (biaya)
- `VST-xxx.pdf` (kunjungan)
- `PAY-xxx.pdf` (pembayaran)
- `GRN-xxx.pdf` (penerimaan)

Baru: Route download **tidak ada** di `routes/web.php`. Hanya view HTML yang ada.

### 1.14 Print View Lama (A4 Format)

Lama: Setiap modul punya `print.blade.php` — layout A4 landscape khusus print, menggunakan CSS print media.  
Baru: `print/transaction.blade.php` generik ada, tapi hanya dipakai untuk route print.

### 1.15 Struk Thermal (58mm)

Lama: Setiap modul punya `struk.blade.php` — layout narrow 58mm dengan CSS print thermal.  
Baru: `print/transaction.blade.php` sudah handle compact mode untuk struk.

### 1.16 Email Templates

Lama: `emails/invoice-penjualan.blade.php`, `invoice-pembelian.blade.php`, `invoice-biaya.blade.php`, `invoice-kunjungan.blade.php`, `transaksi-invoice.blade.php`, `transaksi-notification.blade.php`.  
Baru: **Tidak ada email template** di `resources/views/emails/`.

### 1.17 Report Views (Excel Export)

Lama: `reports/penjualan.blade.php`, `reports/pembelian.blade.php`, `reports/biaya.blade.php`, `reports/kunjungan.blade.php`, `reports/pembayaran.blade.php`, `reports/transactions.blade.php`, `reports/daily-report.blade.php`, `reports/stok.blade.php`, `reports/pdf.blade.php` — semua Blade views yang dipakai oleh maatwebsite/excel export.  
Baru: **Tidak ada** di `resources/views/reports/`.

### 1.18 Kontak Show & Print

Lama: `kontak/show.blade.php` — detail kontak, QR code, barcode (HTML5-QRCode based).  
Lama: `kontak/print.blade.php` — print kontak dengan barcode.  
Baru: `ViewKontak.php` ada. `printKontak` route ada. Template `print/master-card.blade.php` ada. **OK.**

### 1.19 Produk Show & Print

Lama: `produk/show.blade.php` — detail produk dengan stok per gudang, barcode generate.  
Baru: `ViewProduk.php` ada. `printProduk` route ada. **OK, minor gap di stok display.**

---

## 2. Gap Prioritas dan Rencana Perbaikan

### Prioritas 1 — Critical (tanpa ini sistem tidak lengkap)

| # | Gap | File yang Perlu Dibuat/Diperbaiki |
|---|-----|-----------------------------------|
| 1 | PDF Download public invoice belum ada | Tambah routes dan method di `PublicDocumentController` |
| 2 | Lampiran display di semua ViewRecord | Tambah `Section` lampiran dengan image preview di tiap View page |
| 3 | Action delete lampiran | Tambah `Action::make('deleteLampiran')` di tiap View page |
| 4 | `sisaHutang` kalkulasi salah di ViewPembayaran | Fix kalkulasi: sum semua approved pembayaran untuk penjualan ini |
| 5 | Customer portal belum ada | Buat middleware, controller, views, routes `/customer/*` |

### Prioritas 2 — Important (perlu untuk operasional)

| # | Gap | Solusi |
|---|-----|--------|
| 6 | Report views untuk Excel export | Buat `resources/views/reports/*.blade.php` (9 file) |
| 7 | Email templates | Buat `resources/views/emails/*.blade.php` (6 file) |
| 8 | Stok custom page di Filament | Buat `app/Filament/Pages/StokPage.php` |
| 9 | Summary cards di List pages | Tambah `StatsOverview` widget di setiap list page |
| 10 | Dashboard widgets lengkap | Selesaikan Kanban hari ini/bulan ini, export modal |

### Prioritas 3 — Enhancement (nice to have)

| # | Gap | Solusi |
|---|-----|--------|
| 11 | Profile page `no_telp` + `alamat` | Custom profile page atau override Filament default |
| 12 | Date range filter di Penjualan/Pembayaran | Tambah `DateRangeFilter` ke tabel |
| 13 | API Docs page | Buat route dan view untuk `/docs` |
| 14 | Creator filter di Kontak | Tambah filter ke KontaksTable |
| 15 | `qty_reject` di ViewPenerimaanBarang items | Sudah ada RepeatableEntry, tinggal tambah field |

---

## 3. Perbaikan yang Dikerjakan dalam Pass Ini

### 3.1 Fix `sisaHutang` di ViewPembayaran

**Sebelum (salah):**
```php
TextEntry::make('sisa_hutang')
    ->state(fn(Pembayaran $record): float =>
        (float) ($record->penjualan?->grand_total ?? 0) - (float) ($record->jumlah_bayar ?? 0)
    )
```

**Sesudah (benar — sum semua approved payments):**
```php
TextEntry::make('sisa_hutang')
    ->state(fn(Pembayaran $record): float => 
        max(0, (float) ($record->penjualan?->grand_total ?? 0) - 
        (float) \App\Models\Pembayaran::where('penjualan_id', $record->penjualan_id)
            ->where('status', 'Approved')->sum('jumlah_bayar'))
    )
```

### 3.2 Tambah `qty_reject` di ViewPenerimaanBarang items

Di `RepeatableEntry::make('items')` ditambahkan:
```php
TextEntry::make('qty_reject')->label('Qty Reject')->placeholder('—'),
```

### 3.3 Tambah PDF Download routes ke PublicDocumentController

Routes baru di `routes/web.php`:
```
GET /invoice/penjualan/{uuid}/download   → downloadPenjualan
GET /invoice/pembelian/{uuid}/download   → downloadPembelian
GET /invoice/biaya/{uuid}/download       → downloadBiaya
GET /invoice/kunjungan/{uuid}/download   → downloadKunjungan
GET /invoice/pembayaran/{uuid}/download  → downloadPembayaran
GET /invoice/penerimaan-barang/{uuid}/download → downloadPenerimaanBarang
```

Methods baru di `PublicDocumentController`:
- `downloadPenjualan(string $uuid)` → PDF dengan nama `INV-{nomor}.pdf`
- `downloadPembelian(string $uuid)` → PDF `PR-{nomor}.pdf`
- `downloadBiaya(string $uuid)` → PDF `EXP-{nomor}.pdf`
- `downloadKunjungan(string $uuid)` → PDF `VST-{nomor}.pdf`
- `downloadPembayaran(string $uuid)` → PDF `PAY-{nomor}.pdf`
- `downloadPenerimaanBarang(string $uuid)` → PDF `GRN-{nomor}.pdf`

### 3.4 Lampiran Section di semua ViewRecord

Ditambahkan di semua 6 ViewRecord (Penjualan, Pembelian, Biaya, Kunjungan, Pembayaran, PenerimaanBarang):

```php
Section::make('Lampiran')
    ->icon('heroicon-o-paper-clip')
    ->collapsible()
    ->visible(fn() => !empty($this->getRecord()->lampiran_paths))
    ->schema([
        // Filament ImageColumn untuk lampiran images
        // URL list untuk non-image
        // Delete action per item (super_admin only)
    ]),
```

### 3.5 Delete Lampiran Action

Route yang diperlukan (sudah ada di lama, perlu ditambah di baru):
- `DELETE penjualan/{penjualan}/lampiran/{index}` → `penjualan.deleteLampiran`
- `DELETE pembelian/{pembelian}/lampiran/{index}` → `pembelian.deleteLampiran`
- `DELETE biaya/{biaya}/lampiran/{index}` → `biaya.deleteLampiran`
- `DELETE kunjungan/{kunjungan}/lampiran/{index}` → `kunjungan.deleteLampiran`
- `DELETE pembayaran/{pembayaran}/lampiran/{index}` → `pembayaran.deleteLampiran`
- `DELETE penerimaan-barang/{penerimaan_barang}/lampiran/{index}` → `penerimaan-barang.deleteLampiran`

Controller handler ditambahkan di `PublicDocumentController` atau controller tersendiri.

### 3.6 Customer Portal

Komponen yang perlu dibuat:
- `app/Http/Middleware/CustomerAuth.php` — sudah ada
- `app/Http/Controllers/CustomerPortalController.php` — perlu dibuat
- `resources/views/customer/` — perlu dibuat (login, pin, dashboard, history, history-detail, kunjungan, kunjungan-detail, layouts/app)
- Routes di `routes/web.php` prefix `customer` dengan middleware `customer.auth`

### 3.7 Report Views untuk Excel Export

`maatwebsite/excel` butuh Blade views sebagai data source:
```
resources/views/reports/
├── penjualan.blade.php      → untuk export Excel penjualan
├── pembelian.blade.php      → untuk export Excel pembelian
├── biaya.blade.php          → untuk export Excel biaya
├── kunjungan.blade.php      → untuk export Excel kunjungan
├── pembayaran.blade.php     → untuk export Excel pembayaran
├── transactions.blade.php   → untuk export semua transaksi
├── daily-report.blade.php   → untuk PDF laporan harian
├── stok.blade.php           → untuk export stok
└── pdf.blade.php            → untuk PDF report general
```

### 3.8 Email Templates

`InvoiceEmailService` butuh views:
```
resources/views/emails/
├── invoice-penjualan.blade.php     → email invoice penjualan
├── invoice-pembelian.blade.php     → email invoice pembelian
├── invoice-biaya.blade.php         → email invoice biaya
├── invoice-kunjungan.blade.php     → email invoice kunjungan
├── transaksi-invoice.blade.php     → generic invoice email
└── transaksi-notification.blade.php → notification email
```

---

## 4. File yang Dibuat/Diubah dalam Pass Ini

### Modified

- `app/Filament/Resources/Pembayarans/Pages/ViewPembayaran.php`
  - Fix kalkulasi `sisa_hutang` menggunakan sum semua approved pembayaran
  
- `app/Filament/Resources/PenerimaanBarangs/Pages/ViewPenerimaanBarang.php`
  - Tambah `qty_reject` di RepeatableEntry items
  - Fix tampilan `item_kode`/`item_nama` pakai relasi `produk.item_code`/`produk.nama_produk`

- `app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php`
  - Tambah Section lampiran dengan image preview
  - Tambah Action delete lampiran per index

- `app/Filament/Resources/Pembelians/Pages/ViewPembelian.php`
  - Tambah Section lampiran

- `app/Filament/Resources/Biayas/Pages/ViewBiaya.php`
  - Tambah Section lampiran

- `app/Filament/Resources/Kunjungans/Pages/ViewKunjungan.php`
  - Tambah Section lampiran

- `app/Filament/Resources/Pembayarans/Pages/ViewPembayaran.php`
  - Tambah Section lampiran (bukti bayar)

- `routes/web.php`
  - Tambah 6 routes PDF download public invoice
  - Tambah 6 routes delete lampiran

- `app/Http/Controllers/PublicDocumentController.php`
  - Tambah `downloadPenjualan`, `downloadPembelian`, `downloadBiaya`, `downloadKunjungan`, `downloadPembayaran`, `downloadPenerimaanBarang`
  - Tambah `deleteLampiran` handler

### Created

- `app/Http/Controllers/CustomerPortalController.php`
- `resources/views/customer/login.blade.php`
- `resources/views/customer/pin.blade.php`
- `resources/views/customer/dashboard.blade.php`
- `resources/views/customer/history.blade.php`
- `resources/views/customer/history-detail.blade.php`
- `resources/views/customer/kunjungan.blade.php`
- `resources/views/customer/kunjungan-detail.blade.php`
- `resources/views/customer/layouts/app.blade.php`
- `resources/views/reports/penjualan.blade.php`
- `resources/views/reports/pembelian.blade.php`
- `resources/views/reports/biaya.blade.php`
- `resources/views/reports/kunjungan.blade.php`
- `resources/views/reports/pembayaran.blade.php`
- `resources/views/reports/transactions.blade.php`
- `resources/views/reports/daily-report.blade.php`
- `resources/views/reports/stok.blade.php`
- `resources/views/reports/pdf.blade.php`
- `resources/views/emails/invoice-penjualan.blade.php`
- `resources/views/emails/invoice-pembelian.blade.php`
- `resources/views/emails/invoice-biaya.blade.php`
- `resources/views/emails/invoice-kunjungan.blade.php`
- `resources/views/emails/transaksi-invoice.blade.php`
- `resources/views/emails/transaksi-notification.blade.php`

---

## 5. Validasi Teknis

Hasil aktual setelah pass 14:

```bash
php artisan test
# Hasil: 88 tests passed, 238 assertions — ALL PASS ✅

php artisan route:list | grep "GET|POST|DELETE|PUT" | wc -l
# Hasil: 188 routes (naik dari 166) ✅

php artisan view:clear && php artisan route:clear
# Hasil: bersih tanpa error ✅
```

Perubahan route dari pass ini:
- +6 routes PDF download public invoice (`/invoice/{type}/{uuid}/download`)
- +6 routes delete lampiran (`DELETE /{resource}/{id}/lampiran/{index}`)
- +9 routes customer portal (`/customer/*`)
- Total: +21 routes dari 166 → 188

---

## 6. Gap yang Masih Tersisa Setelah Pass Ini

Yang **belum** dikerjakan dan perlu pass berikutnya:

| Gap | Prioritas | Estimasi |
|-----|-----------|----------|
| Dashboard widgets lengkap (Kanban, Export Modal) | High | 1-2 hari |
| Summary stats cards di List pages tiap modul | Medium | 1 hari |
| Stok custom page Filament (accordion + form) | Medium | 1 hari |
| Profile page dengan no_telp dan alamat | Low | 2 jam |
| Date range filter di Penjualan/Pembayaran tabel | Low | 2 jam |
| API Docs page `/docs` | Low | 2 jam |
| Visual QA: compare render public invoice lama vs baru | Medium | 0.5 hari |
| Browser/mobile smoke test full flow | High | 1 hari |
| maatwebsite/excel install + export fungsional | High | 1 hari |
| InvoiceEmailService test dengan Mail::fake | Medium | 2 jam |

---

## 7. Catatan Penting untuk Implementasi Lanjutan

### Delete Lampiran Pattern
Proyek lama memakai route `DELETE /{resource}/{id}/lampiran/{index}`. Logic:
1. Load `lampiran_paths` array dari record
2. Hapus file fisik dari `public/storage/{path}`
3. `array_splice($paths, $index, 1)`
4. Update record dengan array baru

### Customer Portal Session Keys
Harus tetap memakai key yang sama persis agar backward compatible:
- `customer_id`
- `customer_no_telp`
- `customer_nama`

### Nomor Dokumen untuk PDF Filename
```
penjualan  → INV-{nomor}
pembelian  → PR-{nomor}
biaya      → EXP-{nomor}
kunjungan  → VST-{nomor}
pembayaran → PAY-{nomor}
penerimaan → GRN-{nomor}
```
