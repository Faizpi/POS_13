# Implementation Plan — Hibiscusefsya POS

> Dibuat: 2026-06-15
> Referensi: `WEBSITE_OUTLINE.md` (dari Website_Outline.xlsx → OUTLINE REV 1)
> Stack: Laravel 13 + Filament v5 + Maatwebsite Excel + DomPDF

---

## Konteks Teknis Project

### Stack & Package
- **Framework**: Laravel 13 + PHP 8.3
- **UI/Admin**: Filament v5
- **Export**: `maatwebsite/excel` ^3.1 + `barryvdh/laravel-dompdf` ^3.1
- **Helper**: `app/Helpers/helpers.php`

### Role System
Roles disimpan di kolom `users.role` (string, bukan package permission):
- `user` → Sales
- `admin` → Admin
- `spectator` → Spectator (read-only)
- `super_admin` → Super Admin

Role helpers di `User` model: `isSuperAdmin()`, `isAdmin()`, `isSpectator()`
Export control: `can_export_pdf`, `can_export_excel` (boolean di tabel users)

### Pola Resource Filament (WAJIB DIIKUTI)
```
app/Filament/Resources/{ModelPlural}/
├── {Model}Resource.php          ← definisi navigasi, can*(), getPages()
├── Schemas/
│   └── {Model}Form.php          ← form fields
├── Tables/
│   └── {Model}sTable.php        ← table columns + filters
└── Pages/
    ├── List{Model}s.php
    ├── Create{Model}.php
    ├── Edit{Model}.php
    └── View{Model}.php
```

Trait `ScopeByRole` dipakai di semua resource transaksi untuk query scoping:
- `super_admin` → lihat semua
- `admin`/`spectator` → filter by `current_gudang_id`
- `user` (Sales) → filter by `user_id` sendiri

### Models Yang Sudah Ada
| Model | Tabel | Keterangan |
|-------|-------|------------|
| User | users | role, gudang_id, can_export_* |
| Gudang | gudangs | gudang master |
| GudangProduk | gudang_produk (pivot) | stok produk per gudang |
| Produk | produks | master produk |
| StokLog | stok_logs | log perubahan stok |
| Kontak | kontaks | kontak pelanggan/vendor |
| Penjualan | penjualans | transaksi penjualan |
| PenjualanItem | penjualan_items | item per penjualan |
| Pembelian | pembelians | transaksi pembelian |
| PembelianItem | pembelian_items | item per pembelian |
| Pembayaran | pembayarans | pembayaran (piutang/hutang) |
| PenerimaanBarang | penerimaan_barangs | penerimaan barang dari pembelian |
| PenerimaanBarangItem | penerimaan_barang_items | item per penerimaan |
| Kunjungan | kunjungans | kunjungan sales |
| KunjunganItem | kunjungan_items | item kunjungan |
| Biaya | biayas | biaya operasional |
| BiayaItem | biaya_items | item biaya |
| TutupBuku | tutup_buku | periode tutup buku |

---

## Fitur yang Perlu Diimplementasi (dari Excel, status NEW)

### Ringkasan NEW Items
1. **NERACA** — Dashboard card metrics (Spectator + Super Admin only)
2. **PIUTANG** — Dashboard Piutang (Graph + List Toko)
3. **HUTANG** — Dashboard Hutang (Graph + List Tempo)
4. **HUTANG** — Buat Pembelian: field tambahan (no_referensi, no_resi, biaya_pengiriman)
5. **HUTANG** — Pembayaran Hutang (form pembayaran)
6. **PENERIMAAN** — Item Penerimaan: Import Excel (no batch, tanggal, qty)
7. **GUDANG** — Dashboard: Informasi Stock Barang 7 Hari
8. **GUDANG** — Stock Opname (Before/After + Import Excel)
9. **KONTAK** — List hanya milik user, Pembaharuan no telp, Catatan Hutang

---

## FASE 1 — Database & Model (Fondasi)

> Selesaikan ini dulu sebelum mulai UI apapun.

### F1.1 — Audit Schema Pembelian (existing)

**Cek apakah kolom ini sudah ada di tabel `pembelians`:**
- `kontak_id` → foreign key ke kontaks
- `no_referensi` → string, nullable
- `nomor_resi` → string, nullable
- `biaya_pengiriman` → decimal(15,2), nullable
- `tipe_harga` → string, nullable

**Action**: Jika belum ada, buat migration `add_fields_to_pembelians_table`.

### F1.2 — Audit Schema Penjualan (existing)

**Cek apakah kolom ini sudah ada di tabel `penjualans`:**
- `no_referensi` → string, nullable
- `no_resi` → string, nullable (hanya Spectator+SuperAdmin yang bisa lihat)
- `biaya_pengiriman` → decimal(15,2), nullable
- `tipe_harga` → string, nullable

**Action**: Jika belum ada, buat migration `add_fields_to_penjualans_table`.

### F1.3 — Schema Pembayaran Hutang

**Cek tabel `pembayarans` apakah sudah support:**
- `type` → enum: `piutang` | `hutang` (untuk bedain pembayaran penjualan vs pembelian)
- `pembelian_id` → foreign key (untuk hutang)
- `penjualan_id` → foreign key (untuk piutang)
- `gudang_id` → foreign key
- `metode_pembayaran` → string

**Action**: Buat migration jika kolom belum ada.

### F1.4 — Schema Stock Opname (BARU)

Buat migration baru untuk tabel `stock_opnames` dan `stock_opname_items`:

```
stock_opnames:
  - id, uuid
  - user_id (FK users)
  - gudang_id (FK gudangs)
  - status (draft | submitted | applied)
  - memo
  - timestamps

stock_opname_items:
  - id
  - stock_opname_id (FK stock_opnames, cascade delete)
  - produk_id (FK produks)
  - no_batch
  - tgl_exp
  - qty_system (stok di sistem sebelum opname)
  - qty_aktual (stok hasil hitung fisik)
  - selisih (generated/computed: qty_aktual - qty_system)
  - timestamps
```

### F1.5 — Update Model Pembelian & Penjualan

Tambahkan field baru ke `$fillable` dan `$casts` sesuai hasil audit F1.1 & F1.2.

### F1.6 — Buat Model StockOpname & StockOpnameItem

```php
// app/Models/StockOpname.php
// app/Models/StockOpnameItem.php
```

---

## FASE 2 — Dashboard Neraca (Spectator + Super Admin)

> Halaman Filament Page (bukan Resource), hanya tampil untuk spectator dan super_admin.

### F2.1 — NerracaPage (Filament Custom Page)

**File**: `app/Filament/Pages/NeracaPage.php`

**Widget cards yang ditampilkan:**
| Card | Logic | Siapa |
|------|-------|-------|
| OMSET PERGUDANG | Sum grand_total penjualan per gudang (filter rentang waktu) + Export Excel & PDF | Spectator, SuperAdmin |
| NILAI PEMBELIAN GUDANG | Sum grand_total pembelians per gudang (filter rentang waktu) | Spectator, SuperAdmin |
| NILAI PENJUALAN RETAIL | Sum penjualan dengan tipe_harga='retail' | Spectator, SuperAdmin |
| NILAI PENJUALAN GROSIR | Sum penjualan dengan tipe_harga='grosir' | Spectator, SuperAdmin |
| JUMLAH PRODUK TERJUAL RETAIL | Sum kuantitas dari penjualan_items (retail) | Spectator, SuperAdmin |
| PEMBAYARAN BELUM LUNAS | Sum grand_total penjualans dengan status='Approved' (belum lunas) | Spectator, SuperAdmin |

**Access control**: `canAccess()` → return `in_array(auth()->user()->role, ['spectator', 'super_admin'])`

**Export button**: Filter rentang waktu (date range picker), export ke Excel dan PDF.

---

## FASE 3 — Dashboard Piutang (NEW)

### F3.1 — PiutangPage (Filament Custom Page)

**File**: `app/Filament/Pages/PiutangPage.php`

**Komponen:**
1. **Graph Total Tempo Monthly** — Chart line/bar dari penjualan dengan jatuh tempo per bulan
   - Export button (hanya untuk user yang membuat + admin): Export Daily (PDF)
   - Access: Sales=VIEW, Admin=VIEW, Spectator=VIEW, SuperAdmin=ALL
2. **List Toko Tempo Belum & Sudah Terbayar** — Table penjualan yang punya jatuh tempo
   - Kolom: nama kontak, no transaksi, tgl jatuh tempo, grand total, status (Lunas/Belum Lunas)
   - Export button (Created By User and Admin Only)
   - Access: Spectator=VIEW, SuperAdmin=ALL (Sales dan Admin tidak punya akses ke list ini)

**Note**: Export button hanya muncul jika `auth()->user()->canExportReport()`.

---

## FASE 4 — Dashboard Hutang (NEW)

### F4.1 — HutangPage (Filament Custom Page)

**File**: `app/Filament/Pages/HutangPage.php`

**Komponen:**
1. **Graph Total Pembelian Monthly** — Chart dari pembelian dengan jatuh tempo per bulan
   - Export button (Created By User and Admin Only): Export Daily (PDF)
   - Access: Spectator=VIEW, SuperAdmin=ALL
2. **List Tempo Hutang Belum/Sudah Terbayar** — Table pembelians dengan jatuh tempo
   - Export button (Created By User and Admin Only)
   - Access: Spectator=VIEW, SuperAdmin=ALL

---

## FASE 5 — Update Form Pembelian (field baru)

> PembelianResource sudah ada. Ini hanya penambahan field ke form dan tabel yang sudah ada.

### F5.1 — Update PembelianForm.php

Tambahkan field ke `Schemas/PembelianForm.php`:
- `no_referensi` → TextInput, entry manual
  - Sales: ADD | Admin: ADD+EDIT+DEL | SuperAdmin: ALL
- `tipe_harga` → Select, choose from list (retail/grosir)
  - Sales: ADD | Admin: ADD+EDIT | SuperAdmin: ALL
- `no_resi` → TextInput (nomor resi), entry manual
  - Sales: ADD | Admin: ADD+EDIT | SuperAdmin: ALL
- `biaya_pengiriman` → TextInput numeric
  - Sales: ADD | Admin: ADD+EDIT | SuperAdmin: ALL

**Catatan penting**: Field `HARGA` pada pembelian = **manual entry** (bukan autofill dari produk). Pastikan form item pembelian field harga tidak disabled/readonly.

### F5.2 — Update PembeliansTable.php

Tambahkan kolom yang relevan ke table view.

---

## FASE 6 — Pembayaran Hutang (NEW)

### F6.1 — PembayaranHutangResource atau Page

Pembayaran hutang perlu fitur:
- **Form fields** (semua di section `DETAIL PEMBAYARAN`):
  - `no_transaksi` → Autofill
  - `gudang` → choose from list
  - `invoice_pembelian` → choose from list (link ke pembelians)
  - `metode_pembayaran` → choose from list

- **Access**:
  - Sales: ADD+VIEW
  - Admin: ADD+VIEW
  - Spectator: VIEW only
  - SuperAdmin: ALL

**Implementasi**: Extend model `Pembayaran` dengan type='hutang'. Atau buat sub-resource di dalam PembelianResource jika pakai RelationManager.

**Opsi yang direkomendasikan**: RelationManager `PembayaranHutangRelationManager` di dalam `ViewPembelian` — konsisten dengan pola pembayaran piutang yang kemungkinan sudah ada.

---

## FASE 7 — Penerimaan Barang: Import Excel (NEW)

### F7.1 — Import Excel untuk Item Penerimaan

**File baru**: `app/Imports/PenerimaanBarangItemImport.php`

**Format Excel yang diterima** (sesuai outline: NO BATCH, TANGGAL, QTY):
| Kolom Excel | Field Model |
|-------------|-------------|
| Nama Produk / Kode Produk | produk_id (lookup) |
| No Batch | no_batch |
| Tanggal Expired | tgl_expired |
| QTY | kuantitas |

**Implementasi di PenerimaanBarangForm.php**:
- Tambah `FileUpload` untuk upload file Excel
- Setelah upload, parse dan populate repeater items
- Atau: buat action "Import dari Excel" di halaman Create/Edit

**Access**: Sales=ADD, Admin=ADD+VIEW, SuperAdmin=ALL

---

## FASE 8 — Dashboard Gudang: Stock Barang 7 Hari (NEW)

### F8.1 — Update StokPage.php

File `app/Filament/Pages/StokPage.php` kemungkinan sudah ada. Update dengan:
- Widget **Informasi Stock Barang 7 Hari**: tabel atau card yang menampilkan produk dengan pergerakan stok 7 hari terakhir (dari `stok_logs`)
- Access: Sales=VIEW, Admin=VIEW, SuperAdmin=ALL
- Spectator: tidak ada akses

---

## FASE 9 — Stock Opname (NEW)

### F9.1 — StockOpnameResource (Filament Resource baru)

**File**: `app/Filament/Resources/StockOpnames/StockOpnameResource.php`

**Fitur**:
1. **List halaman**: tampilkan history stock opname
2. **Create halaman**:
   - Pilih gudang (autofill dari user)
   - **Import Excel** (NO BATCH, TANGGAL, QTY) → parse dan isi repeater items
   - Tampilkan: produk, qty sistem (stok saat ini), qty aktual (input), selisih (auto calculated)
3. **View halaman**: Gambaran before/after, tabel selisih sebelum direplace
4. **Apply action**: Ketika disubmit/apply, update `gudang_produk` stok sesuai qty aktual

**Access**:
- Sales: VIEW
- Admin: VIEW (tidak bisa create/apply)
- Spectator: tidak ada akses
- SuperAdmin: ALL

### F9.2 — StockOpnameItemImport.php

Format Excel import (sama dengan penerimaan barang):
| Kolom | Field |
|-------|-------|
| Nama/Kode Produk | produk_id |
| No Batch | no_batch |
| Tanggal | tgl_opname |
| QTY Aktual | qty_aktual |

---

## FASE 10 — Kontak: Update (NEW)

### F10.1 — Scoping List Kontak by User

Update `KontakResource.php`:
- `getEloquentQuery()` → untuk role `user` (Sales): filter `where('user_id', auth()->id())`
- Admin: lihat semua kontak di gudangnya
- SuperAdmin: lihat semua

### F10.2 — Restrict Edit: Nomor Telepon Only

Update `KontakForm.php`:
- Role `user` (Sales): hanya field `no_telp` yang bisa diedit (field lain disabled/hidden)
- Role `admin`: ADD+VIEW+APRV+EDIT+DEL (full)
- Role `spectator`: EDIT no_telp only
- Role `super_admin`: ALL

### F10.3 — Catatan Hutang (RelationManager atau Section)

Di halaman View Kontak, tambahkan:
- Section/Tab **Catatan Hutang**: list pembelians milik kontak ini yang belum lunas
- Kolom: no transaksi, tgl jatuh tempo, grand total, status
- Access: Sales=VIEW, Admin=VIEW+EDIT, SuperAdmin=ALL
- Auto populated dari relasi `Kontak → hasMany Pembelian`

---

## Urutan Implementasi yang Direkomendasikan

```
FASE 1  → Audit & Migration (fondasi DB) ← MULAI SINI
FASE 2  → Neraca Dashboard
FASE 3  → Dashboard Piutang
FASE 4  → Dashboard Hutang
FASE 5  → Update Form Pembelian (field baru)
FASE 6  → Pembayaran Hutang
FASE 7  → Import Excel Penerimaan Barang
FASE 8  → Dashboard Gudang (Stock 7 Hari)
FASE 9  → Stock Opname + Import Excel
FASE 10 → Update Kontak
```

---

## Checklist Per Fitur

### NERACA
- [ ] F1.x — Audit/tambah field penjualan (tipe_harga)
- [ ] F2.1 — Buat NeracaPage.php
- [ ] F2.2 — Widget cards (6 metrics)
- [ ] F2.3 — Filter rentang waktu
- [ ] F2.4 — Export Excel & PDF
- [ ] F2.5 — Access control (spectator + super_admin only)

### PIUTANG DASHBOARD
- [ ] F3.1 — Buat PiutangPage.php
- [ ] F3.2 — Chart tempo monthly
- [ ] F3.3 — List toko tempo (belum/sudah bayar)
- [ ] F3.4 — Export button (per permission)

### HUTANG DASHBOARD
- [ ] F4.1 — Buat HutangPage.php
- [ ] F4.2 — Chart pembelian monthly
- [ ] F4.3 — List tempo hutang
- [ ] F4.4 — Export button (per permission)

### FORM PEMBELIAN (field baru)
- [ ] F1.1 — Cek & migration pembelians fields
- [ ] F5.1 — Update PembelianForm.php
- [ ] F5.2 — Update PembeliansTable.php
- [ ] F5.3 — Pastikan field HARGA = manual entry (bukan autofill)

### PEMBAYARAN HUTANG
- [ ] F1.3 — Cek & migration pembayarans
- [ ] F6.1 — PembayaranHutangRelationManager atau Resource
- [ ] F6.2 — Access control (Sales: ADD+VIEW, Admin: ADD+VIEW, Spectator: VIEW, SuperAdmin: ALL)

### PENERIMAAN BARANG — IMPORT EXCEL
- [ ] F7.1 — PenerimaanBarangItemImport.php
- [ ] F7.2 — Template Excel download
- [ ] F7.3 — Integrasi ke PenerimaanBarangForm

### GUDANG — STOCK 7 HARI
- [ ] F8.1 — Update StokPage.php dengan widget 7 hari
- [ ] F8.2 — Access control

### STOCK OPNAME
- [ ] F1.4 — Migration stock_opnames & stock_opname_items
- [ ] F1.6 — Model StockOpname & StockOpnameItem
- [ ] F9.1 — StockOpnameResource (List, Create, View)
- [ ] F9.2 — Import Excel untuk stock opname
- [ ] F9.3 — Tampilan Before/After + selisih
- [ ] F9.4 — Apply action (update stok)
- [ ] F9.5 — Access control

### KONTAK (update)
- [ ] F10.1 — Scoping list kontak by user_id
- [ ] F10.2 — Edit restrict: no_telp only untuk Sales & Spectator
- [ ] F10.3 — Catatan Hutang section di View Kontak

---

## Catatan Penting untuk Implementasi

1. **Visibility Rule (GLOBAL)**: Setiap transaksi hanya bisa dilihat oleh user yang membuat + admin. Implementasi via `ScopeByRole` trait yang sudah ada.

2. **Export Permission**: Export button HANYA muncul untuk user yang membuat atau admin. Cek `auth()->user()->canExportReport()` atau buat kondisi khusus sesuai context.

3. **HARGA pada Pembelian**: `entry` (manual) — berbeda dengan Penjualan yang `auto fill dari produk`. Jangan samakan behavior field harga di form pembelian dengan penjualan.

4. **Import Excel format**: Semua import (Penerimaan Barang & Stock Opname) pakai format: NO BATCH, TANGGAL, QTY. Buat 1 template Excel yang bisa didownload user.

5. **Koordinat**: `autofill based GPS` — implementasi via JavaScript `navigator.geolocation` di form, isi hidden input.

6. **Kontak List**: Hanya menampilkan kontak yang dibuat oleh user sendiri (Sales). Admin melihat semua kontak di gudangnya.

7. **Stock Opname Apply**: Ketika diapply, update `gudang_produk.stok` sesuai `qty_aktual` dan catat ke `stok_logs`.

8. **Filament Panel**: Ada 2 panel — main panel (user/admin/spectator/super_admin) dan `Customer` panel. Semua fitur baru masuk ke main panel.

9. **NavigationGroup yang sudah ada**: `Transaksi` (Penjualan, Pembelian, Biaya, dst). Tambahkan fitur baru ke group yang sesuai atau buat group baru (misal: `Laporan` untuk Neraca/Dashboard).
