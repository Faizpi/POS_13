# API v1 Documentation — Hibiscus Efsya POS

> Base URL: `https://new.hibiscusefsya.com/api/v1`
> Auth: Bearer token via `Authorization: Bearer <token>` header
> Format: All responses are JSON

---

## Table of Contents

- [Authentication](#authentication)
- [User Management](#user-management)
- [Dashboard & Reports](#dashboard--reports)
- [Gudang (Warehouse)](#gudang-warehouse)
- [Produk (Product)](#produk-product)
- [Kontak (Contact)](#kontak-contact)
- [Penjualan (Sales)](#penjualan-sales)
- [Pembelian (Purchase)](#pembelian-purchase)
- [Biaya (Expense)](#biaya-expense)
- [Kunjungan (Visit)](#kunjungan-visit)
- [Pembayaran (Payment)](#pembayaran-payment)
- [Pembayaran Hutang (Debt Payment)](#pembayaran-hutang-debt-payment)
- [Penerimaan Barang (Goods Receipt)](#penerimaan-barang-goods-receipt)
- [Stok (Stock)](#stok-stock)
- [Stock Opname](#stock-opname)
- [Neraca (Balance Sheet)](#neraca-balance-sheet)
- [Piutang Dashboard](#piutang-dashboard)
- [Hutang Dashboard](#hutang-dashboard)
- [Catatan Hutang (Debt Records)](#catatan-hutang-debt-records)
- [Tutup Buku (Year-End Closing)](#tutup-buku-year-end-closing)
- [Print & QR](#print--qr)
- [Common Response Formats](#common-response-formats)
- [Role-Based Access](#role-based-access)
- [API Status & Remaining Gaps](#api-status--remaining-gaps)

---

## Authentication

### POST `/login` (Public)

```json
// Request
{ "email": "user@example.com", "password": "secret" }

// Response 200
{
  "message": "Login berhasil.",
  "token": "abc123...",
  "user": {
    "id": 1,
    "name": "Super Admin",
    "email": "admin@example.com",
    "role": "super_admin|admin|spectator|user",
    "alamat": "...",
    "no_telp": "08...",
    "avatar_url": "https://...",
    "gudang_id": 1,
    "current_gudang_id": 1,
    "receives_transaction_email": true,
    "receives_transaction_whatsapp": true,
    "can_export_pdf": false,
    "can_export_excel": false
  }
}
```

### POST `/logout`

### GET `/profile` → Returns current user + gudang info

### PUT `/profile` → Update name, alamat, no_telp

### POST `/change-password`
```json
{ "current_password": "...", "password": "...", "password_confirmation": "..." }
```

### POST `/profile/avatar` → Upload file (multipart) or base64

### DELETE `/profile/avatar` → Remove avatar

---

## User Management

> 🔒 super_admin only

### GET `/users` — List users
**Query:** `?role=admin&search=nama&per_page=20&page=1`
**Response:** Paginated users with gudang relations

### GET `/users/{id}` — Show user

### POST `/users` — Create user
```json
{
  "name": "...", "email": "...", "password": "...",
  "role": "user|admin|spectator|super_admin",
  "gudang_id": 1,           // required for role=user
  "gudangs": [1, 2],        // required for role=admin
  "spectator_gudangs": [1],  // required for role=spectator
  "no_telp": "...",
  "receives_transaction_email": true,
  "receives_transaction_whatsapp": true,
  "can_export_pdf": false,   // admin only
  "can_export_excel": false  // admin only
}
```

### PUT `/users/{id}` — Update user (same fields as create, password optional)

### DELETE `/users/{id}` — Delete user (blocked if has transactions, can't delete self)

---

## Dashboard & Reports

### GET `/dashboard` — Dashboard stats
**Response:**
```json
{
  "stats": { "penjualan_hari_ini": ..., "pembayaran_hari_ini": ..., ... },
  "recent_penjualan": [...],
  "recent_kunjungan": [...]
}
```

### GET `/dashboard/daily-report` — Single day report

### GET `/dashboard/daily-report/pdf` → PDF download

### GET `/dashboard/export/options` — Available export options
Returns filter options and canonical transaction types for export.

**Response:**
```json
{
  "role": "super_admin|admin|spectator|user",
  "permissions": {
    "can_export_full_report": true,
    "can_export_pdf": true,
    "can_export_excel": true,
    "can_export_daily_pdf": true,
    "allowed_formats": ["pdf", "excel"]
  },
  "transaction_types": [
    { "value": "all", "label": "Semua Transaksi" },
    { "value": "penjualan", "label": "Penjualan" },
    { "value": "pembelian", "label": "Pembelian" },
    { "value": "biaya", "label": "Biaya" },
    { "value": "kunjungan", "label": "Kunjungan" },
    { "value": "pembayaran_piutang", "label": "Pembayaran Piutang" },
    { "value": "pembayaran_hutang", "label": "Pembayaran Hutang" }
  ],
  "status_filters": [
    { "value": "all", "label": "Semua Status" },
    { "value": "Pending", "label": "Pending" },
    { "value": "Approved", "label": "Approved" },
    { "value": "Lunas", "label": "Lunas" },
    { "value": "Rejected", "label": "Rejected" },
    { "value": "Canceled", "label": "Canceled" }
  ],
  "biaya_jenis_filters": [
    { "value": "", "label": "Semua Jenis" },
    { "value": "masuk", "label": "Masuk" },
    { "value": "keluar", "label": "Keluar" }
  ],
  "tujuan_kunjungan_filters": [
    { "value": "", "label": "Semua Tujuan" },
    { "value": "Pemeriksaan Stock", "label": "Pemeriksaan Stock" },
    { "value": "Penagihan", "label": "Penagihan" },
    { "value": "Promo", "label": "Promo" },
    { "value": "Promo Gratis", "label": "Promo Gratis" },
    { "value": "Promo Sample", "label": "Promo Sample" },
    { "value": "Penawaran", "label": "Penawaran" }
  ],
  "export_formats": [
    { "value": "pdf", "label": "PDF" },
    { "value": "excel", "label": "Excel" }
  ],
  "gudang_options": [
    { "id": 1, "nama_gudang": "Gudang Utama" }
  ],
  "sales_options": [
    { "id": 5, "name": "Sales A", "gudang_id": 1 }
  ],
  "defaults": {
    "transaction_type": "all",
    "status_filter": "all",
    "export_format": "excel"
  }
}
```

**Permissions:**
- `super_admin`: sees all gudangs and sales users
- `admin`: sees only assigned gudangs and their sales users
- `spectator`, `user`: sees empty gudang_options and sales_options (no access to export endpoint)

### POST `/dashboard/export` — Export report
Export transactions to PDF or Excel format.

**Request Body:**
```json
{
  "transaction_type": "all",
  "export_format": "excel",
  "date_from": "2026-01-01",
  "date_to": "2026-06-30",
  "status_filter": "Approved",
  "gudang_id": 1,
  "sales_id": 5,
  "biaya_jenis": "masuk",
  "tujuan_filter": "Penagihan"
}
```

**Parameters:**
- `transaction_type` (required): One of `all`, `penjualan`, `pembelian`, `biaya`, `kunjungan`, `pembayaran_piutang`, `pembayaran_hutang`
- `export_format` (required): `pdf` or `excel`
- `date_from` (required): Start date (YYYY-MM-DD)
- `date_to` (required): End date (YYYY-MM-DD)
- `status_filter` (optional): Filter by transaction status (use `all` for no filter)
- `gudang_id` (optional): Filter by warehouse
- `sales_id` (optional): Filter by sales user (admin/super_admin only)
- `biaya_jenis` (optional): Filter biaya by `masuk` or `keluar`
- `tujuan_filter` (optional): Filter kunjungan by purpose

**Response:**
- **PDF**: `application/pdf` with `Content-Disposition: attachment; filename="Laporan_{TypeLabel}_{YYYYMMDD}_sd_{YYYYMMDD}.pdf"`
- **Excel**: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` with `Content-Disposition: attachment; filename="Laporan_{TypeLabel}_{YYYYMMDD}_sd_{YYYYMMDD}.xlsx"`

**Filenames:**
| Transaction Type | Filename Pattern |
|------------------|------------------|
| `all` | `Laporan_All_20260101_sd_20260630.pdf/xlsx` |
| `penjualan` | `Laporan_Penjualan_20260101_sd_20260630.pdf/xlsx` |
| `pembelian` | `Laporan_Pembelian_20260101_sd_20260630.pdf/xlsx` |
| `biaya` | `Laporan_Biaya_20260101_sd_20260630.pdf/xlsx` |
| `kunjungan` | `Laporan_Kunjungan_20260101_sd_20260630.pdf/xlsx` |
| `pembayaran_piutang` | `Laporan_Pembayaran_Piutang_20260101_sd_20260630.pdf/xlsx` |
| `pembayaran_hutang` | `Laporan_Pembayaran_Hutang_20260101_sd_20260630.pdf/xlsx` |

**Export Content:**
- **PDF**: Formatted report with summary cards, transaction table, and grouping summaries (by status, type, method, etc.)
- **Excel**: Single-sheet workbook with transaction data and summary rows

**Payment Type Split:**
In `all` exports, payment rows are labeled `Pembayaran Piutang` or `Pembayaran Hutang` based on the original payment type. The `pembayaran_kind` field retains the original value (`piutang` or `hutang`) for programmatic access.

**Legacy Compatibility:**
The legacy `transaction_type=pembayaran` is deprecated. Use `pembayaran_piutang` or `pembayaran_hutang` instead. The legacy alias still works but returns both types combined.

**Permissions:**
- Endpoint restricted to `super_admin` and `admin` roles
- `can_export_pdf` and `can_export_excel` user flags control format access
- `spectator` and `user` roles cannot access this endpoint (403 Forbidden)

### GET `/lampiran/download?path=...` → Download attachment file

---

## Gudang (Warehouse)

### GET `/gudang` — List accessible gudangs

### POST `/gudang/switch` — Switch active gudang
```json
{ "gudang_id": 2 }
```

### GET `/gudang/stok` — Stock levels per gudang
**Query:** `?gudang_id=1`
**Response:** Array of gudang with produk stock (penjualan/gratis/sample/total)

### GET `/gudang/stok-log` — Stock change log
**Query:** `?gudang_id=1&produk_id=5&date_from=2026-01-01&date_to=2026-06-30&per_page=50`

### POST `/gudang` — Create gudang (super_admin only)
```json
{ "nama_gudang": "...", "alamat_gudang": "..." }
```

### PUT `/gudang/{id}` — Update gudang (super_admin only)

### DELETE `/gudang/{id}` — Delete gudang (super_admin only, blocked if has users/products)

---

## Produk (Product)

### GET `/produk` — List products
**Query:** `?search=nama&per_page=20`

### GET `/produk/{id}` — Show product with stock relations

### GET `/produk/stok/{gudangId}` — Stock levels for specific gudang

### POST `/produk` — Create (super_admin only)
```json
{
  "nama_produk": "...", "item_code": "SKU-001",
  "harga": 25000, "harga_grosir": 20000,
  "satuan": "Pcs|Lusin|Karton", "deskripsi": "..."
}
```

### PUT `/produk/{id}` — Update

### DELETE `/produk/{id}` — Delete

---

## Kontak (Contact)

### GET `/kontak` — List contacts
**Query:** `?search=nama&per_page=20`

### GET `/kontak/{id}` — Show contact

### POST `/kontak` — Create
```json
{
  "nama": "...", "no_telp": "08...", "email": "...",
  "pin": "123456", // 6 digits, for customer portal login
  "alamat": "...", "diskon_persen": 5, "gudang_id": 1
}
```

### PUT `/kontak/{id}` — Update

### DELETE `/kontak/{id}` — Delete

---

## Penjualan (Sales)

### GET `/penjualan` — List sales
**Query:** `?status=Pending|Approved|Canceled|Lunas&search=nomor&per_page=20`

### GET `/penjualan/{id}` — Show sale with items + phone fallback

### POST `/penjualan` — Create sale
```json
{
  "gudang_id": 1,
  "tipe_harga": "retail|grosir",
  "pelanggan": "Nama Toko",
  "no_telepon": "08...",
  "alamat_penagihan": "...",
  "syarat_pembayaran": "Cash|Kredit",
  "tgl_jatuh_tempo": "2026-07-01",
  "no_referensi": "...",
  "koordinat": "-6.123,106.456",
  "memo": "...",
  "diskon_akhir": 0,
  "tax_percentage": 0,
  "items": [
    {
      "produk_id": 1,
      "deskripsi": "...",
      "kuantitas": 10,
      "unit": "Pcs",
      "harga_satuan": 25000,
      "diskon": 0
    }
  ]
}
```
**Notes:** Price auto-resolved based on `tipe_harga`. Stock validated. Auto-assigns approver. Sends WA + email notifications.

### PUT `/penjualan/{id}` — Update (super_admin: full update; owner: lampiran only)
```json
// Full update (super_admin) — same fields as create
// Lampiran only (owner) — just files
{ "lampiran": [File, File] }
```

### POST `/penjualan/{id}/approve` — Approve (admin/super_admin)
### POST `/penjualan/{id}/cancel` — Cancel (owner for user, gudang-match for admin)
### POST `/penjualan/{id}/uncancel` — Uncancel (super_admin only)
### POST `/penjualan/{id}/mark-paid` — Mark as Lunas (admin/super_admin)
### POST `/penjualan/{id}/unmark-paid` — Unmark Lunas (super_admin only)

---

## Pembelian (Purchase)

### GET `/pembelian` — List purchases
**Query:** `?status=Pending|Approved|Canceled&search=nomor`

### GET `/pembelian/{id}` — Show with items

### POST `/pembelian` — Create
```json
{
  "gudang_id": 1, "kontak_id": 5,
  "tipe_harga": "retail",
  "syarat_pembayaran": "Kredit",
  "tgl_jatuh_tempo": "2026-07-01",
  "urgensi": "Tinggi|Sedang|Rendah",
  "tahun_anggaran": "2026",
  "memo": "...",
  "diskon_akhir": 0, "tax_percentage": 0,
  "items": [
    { "produk_id": 1, "kuantitas": 50, "unit": "Pcs", "harga_satuan": 20000, "diskon": 0 }
  ]
}
```

### PUT `/pembelian/{id}` — Update (same as create; owner: lampiran only)

### POST `/pembelian/{id}/approve` — Approve (admin/super_admin)
### POST `/pembelian/{id}/cancel` — Cancel
### POST `/pembelian/{id}/uncancel` — Uncancel (super_admin only)

---

## Biaya (Expense)

### GET `/biaya` — List expenses
**Query:** `?status=Pending|Approved|Canceled&jenis=masuk|keluar`

### GET `/biaya/{id}` — Show with items

### POST `/biaya` — Create
```json
{
  "gudang_id": 1,
  "jenis_biaya": "masuk|keluar",
  "penerima": "Nama Vendor",
  "no_telepon": "08...",
  "cara_pembayaran": "Cash|Transfer",
  "tgl_transaksi": "2026-06-18",
  "memo": "...",
  "tax_percentage": 0,
  "items": [
    { "kategori": "Operasional", "deskripsi": "Transport", "jumlah": 50000 }
  ]
}
```

### PUT `/biaya/{id}` — Update (super_admin only)

### POST `/biaya/{id}/approve` — Approve (admin/super_admin)
### POST `/biaya/{id}/cancel` — Cancel
### POST `/biaya/{id}/uncancel` — Uncancel (super_admin only)

---

## Kunjungan (Visit)

### GET `/kunjungan` — List visits
**Query:** `?status=Pending|Approved|Canceled&search=nomor`

### GET `/kunjungan/{id}` — Show with items + derived `tipe_stok`

### POST `/kunjungan` — Create
```json
{
  "gudang_id": 1,
  "kontak_id": 5,
  "sales_nama": "Nama Sales",
  "sales_no_telepon": "08...",
  "tgl_kunjungan": "2026-06-18",
  "tujuan": "Pemeriksaan Stock|Penagihan|Penawaran|Promo Gratis|Promo Sample",
  "koordinat": "-6.123,106.456",
  "memo": "...",
  "items": [
    { "produk_id": 1, "jumlah": 10 }
  ]
}
```
**Notes:** Items required only for Pemeriksaan Stock / Promo types.

### PUT `/kunjungan/{id}` — Update (super_admin: full; owner: lampiran only)

### POST `/kunjungan/{id}/approve` — Approve (admin/super_admin)
### POST `/kunjungan/{id}/cancel` — Cancel
### POST `/kunjungan/{id}/uncancel` — Uncancel (super_admin only)

---

## Pembayaran (Payment)

### GET `/pembayaran` — List payments (type=piutang only)
**Query:** `?status=Pending|Approved|Canceled`

### GET `/pembayaran/{id}` — Show with penjualan items

### POST `/pembayaran` — Create payment against penjualan
```json
{
  "gudang_id": 1,
  "penjualan_id": 42,
  "tgl_pembayaran": "2026-06-18",
  "metode_pembayaran": "Cash|Transfer|Cheque|QRIS|Debit",
  "jumlah_bayar": 500000,
  "keterangan": "...",
  "lampiran": [File]
}
```
**Notes:** Penjualan must be Approved/Lunas. Auto-marks penjualan as Lunas if fully paid.

### POST `/pembayaran/{id}/approve` — Approve (admin/super_admin)
### POST `/pembayaran/{id}/cancel` — Cancel
### POST `/pembayaran/{id}/uncancel` — Uncancel (super_admin only)

---

## Pembayaran Hutang (Debt Payment)

### GET `/pembayaran-hutang` — List hutang payments (type=hutang)
**Query:** `?status=Pending|Approved|Canceled&per_page=20`

### GET `/pembayaran-hutang/pembelian-by-gudang/{gudangId}` — Pembelians with remaining hutang

### GET `/pembayaran-hutang/pembelian-detail/{id}` — Pembelian detail for hutang payment form

### POST `/pembayaran-hutang` — Create hutang payment
```json
{
  "pembelian_id": 30,
  "tgl_pembayaran": "2026-06-18",
  "metode_pembayaran": "Cash|Transfer|Cheque|QRIS|Debit",
  "jumlah_bayar": 500000,
  "keterangan": "..."
}
```
**Notes:** Auto-generates nomor `BAYH-YYYYMMDD-userId-XXX`. Wrapped in DB transaction.

### POST `/pembayaran-hutang/{id}/approve` — Approve (admin/super_admin)
### POST `/pembayaran-hutang/{id}/cancel` — Cancel
### POST `/pembayaran-hutang/{id}/uncancel` — Uncancel (super_admin only)

### GET `/pembayaran/penjualan-by-gudang/{gudangId}` — List approved penjualans with remaining balance
### GET `/pembayaran/penjualan-detail/{id}` — Penjualan detail for payment form
### GET `/pembayaran/export-harian-pdf` — Daily payment report PDF

---

## Penerimaan Barang (Goods Receipt)

### GET `/penerimaan-barang` — List goods receipts
**Query:** `?status=Pending|Approved|Canceled`

### GET `/penerimaan-barang/{id}` — Show with items

### POST `/penerimaan-barang` — Create
```json
{
  "gudang_id": 1,
  "pembelian_id": 30,
  "tgl_penerimaan": "2026-06-18",
  "no_surat_jalan": "SJ-001",
  "keterangan": "...",
  "items": [
    {
      "produk_id": 1,
      "qty_diterima": 50,
      "qty_reject": 2,
      "tipe_stok": "penjualan|gratis|sample",
      "batch_number": "LOT-001",
      "expired_date": "2027-12-31"
    }
  ]
}
```
**Notes:** Stock added on approve. Reversed on cancel.

### POST `/penerimaan-barang/{id}/approve` — Approve (admin/super_admin)
### POST `/penerimaan-barang/{id}/cancel` — Cancel
### POST `/penerimaan-barang/{id}/uncancel` — Uncancel (super_admin only, reverts to Pending)

### GET `/penerimaan-barang/pembelian-by-gudang/{gudangId}` — Pembelians with remaining qty
### GET `/penerimaan-barang/pembelian-detail/{id}` — Pembelian items with received/remaining

---

## Stok (Stock)

### GET `/stok` — List stock levels (admin/spectator/super_admin only)
**Response:** Array of gudang → produk → stok (penjualan/gratis/sample/total)

### POST `/stok` — Manual stock adjustment (super_admin only)
```json
{
  "gudang_id": 1, "produk_id": 5,
  "stok_penjualan": 100, "stok_gratis": 10, "stok_sample": 5,
  "keterangan": "Manual adjustment"
}
```

### GET `/stok/log` — Stock change log (admin/super_admin only)
**Query:** `?gudang_id=1&produk_id=5&date_from=2026-01-01&date_to=2026-06-30&per_page=50`

---

## Stock Opname

### GET `/stock-opname` — List stock opnames
**Query:** `?status=Draft|Submitted|Applied&gudang_id=1&per_page=20`
**Response:** Paginated with user, gudang, items_count

### GET `/stock-opname/{id}` — Show with items + produk relations

### POST `/stock-opname` — Create (admin/super_admin only)
```json
{
  "gudang_id": 1,
  "tgl_opname": "2026-06-18",
  "memo": "...",
  "items": [
    { "produk_id": 1, "qty_system": 100, "qty_aktual": 98, "keterangan": "Selisih 2 pcs rusak" }
  ]
}
```
**Notes:** Auto-generates nomor `SOP-YYYYMMDD-userId-XXX`. Auto-calculates `selisih = qty_aktual - qty_system`. Status = Draft.

### POST `/stock-opname/{id}/submit` — Submit (Draft → Submitted)
**Notes:** Creator or admin/super_admin can submit. Admin restricted to active gudang.

### POST `/stock-opname/{id}/apply` — Apply (Submitted → Applied, super_admin only)
**Notes:** Adjusts stock in gudang_produk based on selisih. Creates StokLog entries for each item. Wrapped in DB transaction.

---

## Neraca (Balance Sheet)

> 🔒 Access: spectator + super_admin only

### GET `/neraca` — Neraca summary
**Query:** `?from=2026-01-01&to=2026-06-30&gudang_id=1`
**Response:** Omset, pembelian, retail, grosir, qty, belum_lunas per gudang

### GET `/neraca/export-pdf` — Download neraca PDF
**Query:** Same as above
**Response:** PDF file download

### GET `/neraca/export-excel` — Download neraca Excel
**Query:** Same as above
**Response:** Excel file download

---

## Piutang Dashboard

### GET `/piutang` — Piutang summary
**Query:** `?from=2026-01-01&to=2026-06-30&gudang_id=1`
**Response:** `{ chart: [...], list_toko: [...] }` — Monthly chart data + list of customers with total piutang, bayar, sisa

### GET `/piutang/export-pdf` — Daily piutang PDF report
**Query:** Same as above
**Response:** PDF file download

---

## Hutang Dashboard

> 🔒 Access: spectator + super_admin only

### GET `/hutang` — Hutang summary
**Query:** `?from=2026-01-01&to=2026-06-30&gudang_id=1`
**Response:** `{ chart: [...], list_tempo: [...] }` — Monthly chart data + list of purchases with sisa hutang

### GET `/hutang/export-pdf` — Daily hutang PDF report
**Query:** Same as above
**Response:** PDF file download

---

## Catatan Hutang (Debt Records)

### GET `/catatan-hutang` — Hutang aggregated per kontak
**Query:** `?per_page=20`
**Response:**
```json
{
  "data": [
    {
      "kontak": { "id": 1, "nama": "...", "no_telp": "..." },
      "total_hutang": 5000000,
      "total_sisa": 3000000,
      "jumlah_transaksi": 5,
      "items": [
        {
          "nomor": "PO-20260618-1-001",
          "gudang": "GUDANG JAKARTA",
          "tgl_jatuh_tempo": "2026-07-18",
          "grand_total": 1000000,
          "sudah_bayar": 500000,
          "sisa": 500000,
          "jatuh_tempo_lewat": false
        }
      ]
    }
  ]
}
```
**Notes:** Sorted by sisa terbesar. Scoped by role.

---

## Tutup Buku (Year-End Closing)

> 🔒 Access: super_admin only

### GET `/tutup-buku` — List all year-end closing records
**Response:** Array of TutupBuku records with closedBy relation

### POST `/tutup-buku/execute` — Execute year-end closing
```json
{ "tahun": 2025 }
```
**Notes:** Archives all transactions for the year, deletes originals, snapshots stock. Validates sequential closing order and no pending transactions.

### GET `/tutup-buku/backup-db` — SQL database dump
**Response:** `.sql` file download

### GET `/tutup-buku/export-data` — Export yearly data as ZIP
**Query:** `?tahun=2025&gudang_id=1`
**Response:** ZIP file download containing CSVs + lampiran files

---

## Print & QR

### GET `/print/{type}/{id}/qr` — QR code data
**Type:** `penjualan|pembelian|biaya|kunjungan|pembayaran|penerimaan-barang`
**Response:** `{ receipt_url, invoice_url, download_url }`

### GET `/print/{type}/{id}/bluetooth` — Thermal printer JSON data

---

## Common Response Formats

### Success (200)
```json
{ "message": "...", "data": { ... } }
```

### Created (201)
```json
{ "message": "...", "data": { ... } }
```

### Validation Error (422)
```json
{ "message": "...", "errors": { "field": ["Error message"] } }
```

### Unauthorized (401)
```json
{ "message": "Unauthenticated." }
```

### Forbidden (403)
```json
{ "message": "Unauthorized" }
```

### Not Found (404)
```json
{ "message": "No query results..." }
```

### Paginated Response
```json
{
  "data": [...],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "last_page": 5, "per_page": 20, "total": 100 }
}
```

---

## Role-Based Access

| Role | Access Level |
|------|-------------|
| **super_admin** | Full access to everything + user management + manual stock + uncancel + tutup buku |
| **admin** | Scoped to assigned gudangs. Can approve/cancel transactions. Export if permitted |
| **spectator** | Read-only on assigned gudangs. Cannot create/update/delete |
| **user (sales)** | Create transactions for own gudang. View/edit own data only |

### Transaction Approval Hierarchy
- **user (sales)** → creates → Pending
- **admin** → approves → Approved
- **super_admin** → overrides (uncancel, unmark-paid, tutup buku)

---

## API Status & Remaining Gaps

> ✅ All major features from the Filament web panel are now available via API.
> Total API routes: **110 endpoints**

### ✅ Recently Implemented

| Feature | Status | Endpoints |
|---------|--------|-----------|
| **Stock Opname** | ✅ Done | `GET /stock-opname`, `GET /stock-opname/{id}`, `POST /stock-opname`, `POST /stock-opname/{id}/submit`, `POST /stock-opname/{id}/apply` |
| **Neraca** | ✅ Done | `GET /neraca`, `GET /neraca/export-pdf`, `GET /neraca/export-excel` |
| **Tutup Buku** | ✅ Done | `GET /tutup-buku`, `POST /tutup-buku/execute`, `GET /tutup-buku/backup-db`, `GET /tutup-buku/export-data` |
| **Piutang Dashboard** | ✅ Done | `GET /piutang`, `GET /piutang/export-pdf` |
| **Hutang Dashboard** | ✅ Done | `GET /hutang`, `GET /hutang/export-pdf` |
| **Catatan Hutang** | ✅ Done | `GET /catatan-hutang` |
| **Pembayaran Hutang** | ✅ Done | `GET /pembayaran-hutang`, `POST /pembayaran-hutang`, `GET /pembayaran-hutang/pembelian-by-gudang/{id}`, `GET /pembayaran-hutang/pembelian-detail/{id}` |
| **Gudang CRUD** | ✅ Done | `POST /gudang`, `PUT /gudang/{id}`, `DELETE /gudang/{id}` |
| **Penerimaan Barang uncancel** | ✅ Done | `POST /penerimaan-barang/{id}/uncancel` |
| **User `receives_transaction_whatsapp`** | ✅ Done | Added to UserController store + update |

### ⚠️ Remaining Gaps

#### P1 — Missing Critical Functionality

| Issue | Description |
|-------|-------------|
| Pembayaran missing `update` (PUT) | Cannot edit existing pembayaran via API (only create/approve/cancel) |
| PenerimaanBarang missing `update` (PUT) | Cannot edit existing penerimaan-barang via API |

#### P2 — Missing File Attachment Support

| Transaction | Status |
|-------------|--------|
| Penjualan | ✅ Supported |
| Pembelian | ✅ Supported |
| Kunjungan | ✅ Supported |
| Biaya | ❌ Missing lampiran upload |
| Pembayaran | ❌ Missing lampiran upload |
| PenerimaanBarang | ❌ Missing lampiran upload |

#### P3 — Quality Issues

| Issue | Location |
|-------|----------|
| PembayaranController@show has NO authorization check | Any user can view any payment |
| Multiple index endpoints lack search (Kunjungan, Pembayaran, Penerimaan, Biaya) | — |
| N+1 query in PenerimaanBarangController@getPembelianByGudang | Per-item sub-query |

### Mobile Implementation Priority

Untuk develop mobile apps, fitur yang **sudah tersedia** via API:

1. ✅ **Login / Logout / Profile** — full auth flow
2. ✅ **Dashboard** — stats, daily report, export PDF/Excel
3. ✅ **Penjualan** — full CRUD + approve/cancel/uncancel/mark-paid
4. ✅ **Pembelian** — full CRUD + approve/cancel/uncancel
5. ✅ **Biaya** — full CRUD + approve/cancel/uncancel
6. ✅ **Kunjungan** — full CRUD + approve/cancel/uncancel
7. ✅ **Pembayaran Piutang** — full CRUD + approve/cancel/uncancel
8. ✅ **Pembayaran Hutang** — create + approve/cancel/uncancel
9. ✅ **Penerimaan Barang** — create + approve/cancel/uncancel
10. ✅ **Stock Opname** — create + submit + apply
11. ✅ **Stok** — view + manual update + log
12. ✅ **Neraca** — data + export PDF/Excel
13. ✅ **Piutang/Hutang Dashboard** — chart + list + export PDF
14. ✅ **Catatan Hutang** — per-kontak aggregated
15. ✅ **Tutup Buku** — list + execute + backup + export
16. ✅ **Produk** — full CRUD
17. ✅ **Kontak** — full CRUD
18. ✅ **Gudang** — full CRUD + switch + stok
19. ✅ **Print/QR** — QR data + bluetooth thermal print
20. ✅ **User Management** — full CRUD (super_admin only)

**Fitur yang belum via API (optional untuk mobile):**
- Lampiran upload untuk Biaya/Pembayaran/PenerimaanBarang
- Edit (PUT) Pembayaran dan PenerimaanBarang
- Gudang management dari mobile (super_admin only)

---

## Appendix: User Model Fields

```json
{
  "id": 1,
  "name": "Super Admin",
  "email": "admin@example.com",
  "role": "super_admin|admin|spectator|user",
  "alamat": "...",
  "no_telp": "08...",
  "avatar": "avatars/avatar.png",
  "avatar_url": "https://...",
  "gudang_id": 1,                    // user (sales) only
  "current_gudang_id": 1,            // admin/spectator: active gudang
  "receives_transaction_email": true,
  "receives_transaction_whatsapp": true,
  "can_export_pdf": false,            // admin only
  "can_export_excel": false,          // admin only
  "gudangs": [{ "id": 1, ... }],     // admin: assigned gudangs (many-to-many)
  "spectatorGudangs": [{ ... }],     // spectator: read-only gudangs (many-to-many)
  "created_at": "...",
  "updated_at": "..."
}
```

## Appendix: Transaction Status Flow

```
Pending → Approved → Lunas (penjualan only)
Pending → Canceled
Canceled → Pending (uncancel, super_admin only)
Lunas → Approved (unmark-paid, super_admin only)
```

## Appendix: Numbering Format

| Transaction | Format |
|-------------|--------|
| Penjualan | `INV-YYYYMMDD-{gudangId}-{sequential}` |
| Pembelian | `PO-YYYYMMDD-{gudangId}-{sequential}` |
| Biaya | `COST-YYYYMMDD-{gudangId}-{sequential}` |
| Kunjungan | `VST-YYYYMMDD-{gudangId}-{sequential}` |
| Pembayaran | `PAY-YYYYMMDD-{gudangId}-{sequential}` |
| Penerimaan Barang | `RCV-YYYYMMDD-{gudangId}-{sequential}` |
