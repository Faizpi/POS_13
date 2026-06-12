# Spesifikasi Halaman Web - Hibiscusefsya POS

Dokumen ini adalah blueprint lengkap setiap halaman aplikasi web. Sistem ini bukan "admin panel" — ini adalah **aplikasi POS utuh** yang diakses oleh SEMUA role (`super_admin`, `admin`, `spectator`, `user`) dengan visibility dan hak yang berbeda per role.

---

## Arsitektur Panel

### Path & URL Structure
```
/                           → redirect ke /app
/app/login                  → halaman login (bukan /admin/login)
/app                        → Dashboard (semua role)
/app/penjualans             → List penjualan
/app/penjualans/create      → Form buat penjualan
/app/penjualans/{id}        → Detail penjualan
/app/penjualans/{id}/edit   → Edit (super_admin only)
/app/pembelians             → List pembelian
/app/biayas                 → List biaya
/app/kunjungans             → List kunjungan
/app/pembayarans            → List pembayaran
/app/penerimaan-barangs     → List penerimaan
/app/kontaks                → List kontak
/app/produks                → List produk (super_admin)
/app/gudangs                → List gudang (super_admin)
/app/users                  → List user (super_admin)
/app/stok                   → Custom page stok
/app/stok-log               → Riwayat stok

# Public (no auth)
/invoice/{type}/{uuid}      → Public invoice
/invoice/{type}/{uuid}/download → PDF download
/struk/{type}/{uuid}        → Public receipt
/customer/...               → Customer portal (phone+PIN auth)

# API Mobile (unchanged)
/api/v1/*                   → 85 endpoint mobile API
```

### Panel Configuration
- **ID**: `app` (bukan `admin` agar tidak misleading)
- **Brand**: "Hibiscus Efsya POS"
- **Color**: Rose primary, Slate gray
- **Font**: Plus Jakarta Sans (modern, readable)
- **Width**: Full (lebih lega untuk tabel data)
- **Notifications**: Database-driven dengan 30s polling
- **Sidebar**: Collapsible dengan 4 navigation groups
- **Bluetooth Print**: Auto-injected via render hook (semua halaman)

### Sidebar Navigation Groups

```
┌─────────────────────────────────┐
│ MENU UTAMA                      │
│   • Dashboard                   │
│                                 │
│ TRANSAKSI                       │
│   • Kunjungan                   │
│   • Penjualan                   │
│   • Pembayaran                  │
│   • Biaya                       │
│   • Pembelian                   │
│   • Penerimaan Barang           │
│                                 │
│ MASTER DATA                     │
│   • Kontak                      │
│   • Stok Gudang                 │
│                                 │
│ PENGATURAN (super_admin only)   │
│   • Pengguna                    │
│   • Gudang                      │
│   • Produk                      │
└─────────────────────────────────┘
```

### Topbar
- Brand logo + "Hibiscus Efsya POS"
- Sidebar toggle
- **Gudang Switcher** (admin/spectator dengan multi-gudang)
- **Notification Bell** (pending approvals untuk admin/super_admin)
- **User Menu**: Profil Saya, Logout

---

## 1. Dashboard

**URL**: `/app`
**Akses**: Semua role
**Layout**: Multi-section dengan widgets

### Widgets per Section

#### A. Kanban Cards (Hari Ini & Bulan Ini)
Dua kolom side-by-side. Kiri: Hari Ini. Kanan: Bulan Ini.
Setiap kanban menampilkan:
- **Penjualan**: jumlah rupiah + count transaksi (icon shopping-cart)
- **Biaya**: jumlah rupiah + count (icon wallet)
- **Pembayaran**: jumlah rupiah + count (icon money-bill-wave)

#### B. Summary Cards Grid (Bulan Ini)
4 kolom × 2 baris (8 cards total):
1. Total Penjualan (warning border)
2. Total Pembelian (info border)
3. Total Kunjungan (success border)
4. Pending Approval (danger border, count clickable)
5. Biaya Masuk (success)
6. Biaya Keluar (danger)
7. Total Produk (info, super_admin only)
8. Canceled Bulan Ini (secondary)

#### C. Charts Section (admin/super_admin/spectator)
- **Trend Penjualan 6 Bulan**: Line chart
- **Status Distribution**: Doughnut chart (Pending/Approved/Lunas/Canceled)
- **Transaksi per Gudang**: Bar chart (super_admin)
- **Sales Quantity per Produk**: Bar chart with date range filter (super_admin/spectator)

#### D. Activity Table
- **super_admin/spectator**: Semua transaksi terbaru (15 baris)
- **admin/user**: Transaksi sendiri/gudang aktif
- Kolom: Tanggal, Nomor, Pembuat, Tipe, Status, Total
- Search bar di atas tabel

### Action Buttons (admin/super_admin)
- **Generate Report** → Modal export dengan: date range, transaction type, status filter, gudang filter, sales filter, format (PDF/Excel)

---

## 2. Penjualan

### 2.1 Index Page (`/app/penjualans`)

#### Header
- Title: "Penjualan"
- Tombol "Buat Penagihan Baru" (semua kecuali spectator)

#### Summary Cards (4)
1. **Total (Pending/Approved)** - rupiah, warning color
2. **Jatuh Tempo Lewat** - rupiah, danger color
3. **Lunas (30 Hari)** - rupiah, success color
4. **Canceled** - count, secondary color

#### Filters
- Sales user dropdown (super_admin/admin/spectator)
- Status tabs: Semua, Pending, Approved, Lunas, Canceled
- Search: nomor, pelanggan
- Date range picker (optional)

#### Table Columns
| Tanggal | Nomor | Pembuat | Approver | Pelanggan | Total | Status | Aksi |

- **Tanggal**: format `d/m/Y` + jam kecil di bawah
- **Nomor**: clickable → detail page, custom_number format `INV-YYYYMMDD-{user_id}-{nourut}`
- **Approver**: "-" jika status Pending
- **Total**: format rupiah, bold
- **Status badges**:
  - Pending: warning (kuning)
  - Approved: info (biru)
  - Lunas: success (hijau)
  - Canceled: secondary (abu)
  - + badge "Telat" merah jika Approved & jatuh tempo lewat

#### Action Dropdown
- Lihat Detail (semua)
- Edit (super_admin only)
- Hapus (super_admin only) - dengan modal konfirmasi

---

### 2.2 Create/Edit Form

#### Header Card (2 kolom: 8/4)

**Kolom Kiri (col-md-8):**
- **Pelanggan** (required, dropdown kontak + button scan QR + link buat baru)
  - Dropdown menampilkan: `[KODE] Nama Kontak`
  - Auto-fill: no_telepon, alamat, diskon_persen
- **Nomor Telepon** (auto-fill dari kontak, editable)
- **Alamat Penagihan** (auto-fill, textarea 2 baris)
- **Tgl Transaksi** (required, date picker, default hari ini, readonly untuk role `user`)
- **Syarat Pembayaran** (required, dropdown: Cash, Net 7, Net 14, Net 30, Net 60)
- **Jatuh Tempo** (auto-calculated based on syarat_pembayaran, readonly)

**Kolom Kanan (col-md-4):**
- **No Transaksi (Preview)** - readonly, auto-generated `INV-YYYYMMDD-{userid}-{nourut}`
- **No Referensi Pelanggan** (optional)
- **Koordinat Lokasi**:
  - Auto-fill via `navigator.geolocation` saat halaman dimuat
  - Format: `-6.123456, 106.123456`
  - Button: "Ambil Lokasi" + "Buka di Google Maps"
- **Tag** (readonly = `auth()->user()->name`)
- **Gudang** (required):
  - super_admin: dropdown semua gudang
  - admin: readonly = current_gudang_id
  - user: readonly = user.gudang_id
- **Tipe Harga** (toggle button group: Retail / Grosir)

#### Item Repeater (Tabel)

**Desktop columns:**
| Produk | [📷] | Deskripsi | Qty | Unit | Harga | Disc% | Disc Rp | Batch | Exp | Jumlah | [X] |

- **Produk**: select dropdown dengan filter by gudang yang dipilih
  - Format option: `[ITEM_CODE] Nama Produk`
  - Data attributes: harga, harga-grosir, deskripsi, satuan
- **[📷]**: button kecil scan barcode produk
- **Qty**: input number, min 1
- **Unit**: auto-fill dari produk.satuan, readonly
- **Harga**: hidden + display readonly (auto dari produk berdasarkan tipe_harga)
- **Disc%**: 0-100
- **Disc Rp**: nominal discount
- **Batch**: input text
- **Exp**: input date
- **Jumlah**: auto-calculated `(qty × harga) × (1 - disc%/100) - disc_nominal`
- **[X]**: hapus baris (kecuali baris pertama)

**Mobile**: card layout instead of table

**Button**: "Tambah Baris" (di bawah tabel)

#### Footer Card (Total + Memo + Lampiran)

**Kolom kanan (totals):**
- Subtotal (auto)
- Diskon Akhir (input rupiah)
- Pajak % (input number)
- **Grand Total** (large, bold, primary color)

**Live calculator JS:**
- Update saat qty/harga/diskon/pajak berubah
- Format rupiah Indonesia (titik ribuan, koma desimal)

**Memo**: textarea 3 baris

**Lampiran**: multiple file upload
- Accepted: jpg, jpeg, png, gif, webp, pdf, doc, docx, zip
- Max size: 2MB per file (atau 5MB untuk image)

#### Submit Buttons
- "Batal" (secondary, kembali ke index)
- "Simpan" (primary)

---

### 2.3 Show/Detail Page

#### Header
- Title: "Detail Penjualan #{nomor}"
- Meta: Status badge, tanggal, gudang
- **Action Buttons (responsive flex):**

#### Action Button Visibility Matrix

| Button | Pending | Approved | Lunas | Canceled |
|--------|---------|----------|-------|----------|
| ✅ Setujui | super_admin, admin (gudang) | - | - | - |
| 💰 Tandai Lunas | - | super_admin, admin (gudang) | - | - |
| ↩️ Buka Lunas | - | - | super_admin | - |
| ❌ Cancel | super_admin, admin (gudang), user (own) | super_admin only | super_admin only | - |
| ↪️ Uncancel | - | - | - | super_admin only |
| 🖨️ Print Bluetooth | semua | semua | semua | semua |
| 📄 Cetak Struk | semua | semua | semua | semua |
| 📱 QR Code | semua | semua | semua | semua |
| ⬅️ Kembali | semua | semua | semua | semua |

#### Info Card (2 kolom)

**Kolom kiri (informasi sales/customer):**
- Sales (user creator)
- Pelanggan
- Nomor Telepon (with 3-fallback resolution)
- Tgl Transaksi
- Jatuh Tempo
- Dibuat (created_at WIB)
- Diupdate (jika berbeda)
- Gudang
- Tipe Harga (badge: Retail/Grosir)
- Approver
- Syarat Pembayaran

**Kolom kanan (totals):**
- Status (badge)
- Subtotal
- Diskon Akhir (jika > 0, color danger)
- Pajak %
- **Grand Total** (large primary)
- No Referensi
- Tag
- Koordinat (with Google Maps button)

#### Product Detail Table
**Desktop:**
| Item Code | Produk | Deskripsi | Qty | Harga | Disc% | Disc Rp | Batch | Exp | Total |

**Mobile**: card layout

#### Memo & Lampiran (2 kolom)
- Memo card
- Lampiran card: image previews + file links + delete button (super_admin only)

#### Modals
- **Cancel Modal**: konfirmasi cancel
- **Uncancel Modal**: konfirmasi batalkan pembatalan
- **QR Code Modal**: 
  - QR image dari URL publik
  - Input field URL dengan tombol copy
  - Button "Buka Halaman Publik"

---

## 3. Pembelian

### 3.1 Index
**Summary Cards:** Pending Approval, Total Aktif, Jatuh Tempo Lewat, Canceled
**Table:** Tanggal, Nomor (`PR-...`), Pembuat, Approver, Gudang, Total, Status, Aksi
**Status badges:** Pending (warning), Approved (success), Canceled (secondary), Lunas (primary)

### 3.2 Create Form
**Fields:**
- Tgl Transaksi*, Syarat Pembayaran*, Urgensi* (Rendah/Sedang/Tinggi)
- Gudang*, Tahun Anggaran, Tag, Koordinat
- Memo, Lampiran

**Items:** Produk*, Deskripsi, Qty*, Unit, Harga* (manual input!), Disc%, Jumlah

**Footer:** Subtotal, Diskon Akhir, Pajak%, Grand Total

### 3.3 Show
Same pattern as Penjualan dengan action buttons:
- Approve, Cancel, Uncancel, Print Bluetooth, Cetak Struk, QR Code

---

## 4. Biaya

### 4.1 Index
**Filters:** Status, Jenis Biaya (Masuk/Keluar)
**Summary Cards:** Total Masuk, Total Keluar, Pending, Canceled
**Table:** Tanggal, Nomor (`EXP-...`), Jenis (badge), Pembuat, Penerima, Total, Status, Aksi

### 4.2 Create Form
**Fields:**
- Jenis Biaya* (Masuk/Keluar toggle)
- Bayar Dari* (text)
- Penerima (text)
- Alamat Penagihan (textarea)
- Tgl Transaksi*, Cara Pembayaran (Cash/Transfer/Cheque)
- Tag, Koordinat, Memo

**Items:** Kategori* (text), Deskripsi, Jumlah*

**Footer:** Subtotal, Pajak%, Grand Total

**Note:** Super admin yang create → status langsung 'Approved'

### 4.3 Show
Action buttons: Approve, Cancel, Uncancel, Print Bluetooth, Cetak Struk, QR Code

---

## 5. Kunjungan

### 5.1 Index
**Filters:** Status, Tujuan
**Table:** Tanggal, Nomor (`VST-...`), Tujuan, Pembuat, Kontak, Gudang, Status, Aksi

### 5.2 Create Form
**Fields:**
- Kontak* (dropdown + scan QR)
- Tgl Kunjungan*
- Tujuan* (Pemeriksaan Stock / Penagihan / Promo Gratis / Promo Sample)
- Sales Nama (default: user.name)
- Sales No Telepon (default: user.no_telp)
- Sales Alamat (default: user.alamat)
- Koordinat (auto geo)
- Memo, Lampiran

**Items (wajib kecuali Penagihan):**
| Produk | Qty | Batch | Exp | Keterangan | [X] |

**Conditional:**
- Promo Gratis: validasi stok_gratis di gudang
- Promo Sample: validasi stok_sample di gudang
- Pemeriksaan Stock: items required
- Penagihan: items optional

### 5.3 Show
Display items dengan transformed `tipe_stok` (gratis/sample/penjualan)
Actions: Approve, Cancel, Uncancel, Print Bluetooth, Cetak Struk, QR Code

---

## 6. Pembayaran

### 6.1 Index
**Filters:** Status, Date range
**Summary Cards:** Total Pembayaran Bulan Ini, Pending, Approved
**Table:** Tanggal, Nomor (`PAY-...`), Penjualan (link), Pelanggan, Metode, Jumlah, Status, Aksi

### 6.2 Create Form
**Fields:**
- Penjualan* (dropdown: cari by nomor/pelanggan, hanya yang punya sisa tagihan)
  - Auto-show: pelanggan, grand_total, sisa tagihan
- Tgl Pembayaran*
- Metode Pembayaran* (Cash/Transfer/Cheque/QRIS/Debit)
- Jumlah Bayar* (max = sisa tagihan, dengan warning jika lebih)
- Keterangan (textarea)
- Lampiran (bukti bayar)

### 6.3 Show
Action: Approve (cek Lunas side effect), Cancel, Uncancel, Print Bluetooth, QR Code

### 6.4 Export Harian PDF
- Modal pilih tanggal/range
- Generate PDF: Tagihan Invoice harian + cash hari ini

---

## 7. Penerimaan Barang

### 7.1 Index
**Table:** Tanggal, Nomor (`RCV-...`), Pembelian, Gudang, Total Items, Status, Aksi

### 7.2 Create Form
**Fields:**
- Gudang* (auto dari user atau pilih)
- Pembelian* (dropdown: hanya pembelian Approved/Pending dengan sisa qty)
  - Auto-load items + qty_pesan + qty_diterima + qty_sisa
- Tgl Penerimaan*
- No Surat Jalan
- Keterangan, Lampiran

**Items table:**
| Produk | Qty Pesan | Qty Diterima* | Qty Reject | Tipe Stok | Batch | Exp | Keterangan |

- **Tipe Stok**: dropdown (penjualan/gratis/sample)

### 7.3 Show
Actions: Approve (adds stock), Cancel (reverses stock if approved)

---

## 8. Stok (Custom Page)

### 8.1 Index `/app/stok`

**Layout 2 panel:**

**Kiri (super_admin only) - Form Tambah/Update Stok:**
- Gudang* (dropdown)
- Produk* (dropdown)
- Stok Penjualan* (number, min 0)
- Stok Gratis* (number, min 0)
- Stok Sample* (number, min 0)
- Keterangan (textarea)
- Submit "Simpan Stok"

**Kanan - Daftar Stok per Gudang:**
- Header card dengan tombol:
  - "Riwayat Perubahan" → /app/stok-log
  - "Export Excel" → modal pilih gudang
- Accordion per gudang:
  - Header: Nama Gudang + badge "Total Item: N"
  - Body: Table (Produk, Item Code, Penjualan, Gratis, Sample, Total)

### 8.2 Stok Log `/app/stok-log`
**Filters:**
- Gudang (dropdown)
- Produk (dropdown searchable)
- Dari Tanggal
- Sampai Tanggal

**Table:**
| Waktu | Produk | Gudang | Sebelum | Sesudah | Selisih (badge) | Diubah Oleh | Keterangan |

**Selisih badge color:**
- positif: success (+10)
- negatif: danger (-5)
- nol: secondary (0)

---

## 9. Kontak

### 9.1 Index
**Table:** Kode, Nama, No Telp, Email, Gudang, Diskon%, Creator, Aksi
**Filters:** Search (nama/kode/no_telp), Gudang, Sales (creator)
**Actions:** View, Edit, Print QR/Barcode, Delete

### 9.2 Create/Edit Form
- **Kode Kontak** (optional, kosongkan untuk auto-generate `KT00001`)
- **Nama** (required)
- **Email** (optional)
- **No Telepon** (optional, format `628xxxxxxxxxx`, untuk login customer portal)
- **PIN Customer** (6 digit, untuk login portal)
- **Alamat** (textarea)
- **Diskon Bawaan %** (number 0-100, default 0)
- **Gudang** (auto-set dari current gudang user)

### 9.3 Show / Print Page
- Display kontak info
- QR Code dari `kode_kontak` (untuk scan dari mobile)
- Barcode 1D dari `kode_kontak`
- Print button

---

## 10. Produk (super_admin only)

### 10.1 Index
**Table:** Item Code, Nama, Harga Retail, Harga Grosir, Satuan, Stok di Gudang, Aksi
**Filters:** Search, Satuan
**Actions:** View, Edit, Print barcode, Delete

### 10.2 Create/Edit Form
- **Nama Produk** (required)
- **Item Code (SKU)** (optional, unique)
- **Harga Retail** (required, number with Rp prefix + live preview)
- **Harga Grosir** (optional, default 0)
- **Satuan** (required, dropdown: Pcs/Lusin/Karton)
- **Deskripsi** (textarea)

### 10.3 Show
- Info produk
- Tabel stok per gudang
- Print barcode button

---

## 11. Users (super_admin only)

### 11.1 Index
**Filters:**
- Role dropdown (Semua, Super Admin, Admin, Spectator, User)
- Search (nama/email)

**Table columns:**
| Nama (avatar+nama) | Email | Role (badge with icon) | Gudang | Penerima Email | Hak Export | Aksi |

**Role Badges:**
- Super Admin: blue, crown icon
- Admin: emerald, user-tie icon
- Spectator: cyan, eye icon
- User: blue, user icon

**Penerima Email**: toggle checkbox (admin/super_admin only)
**Hak Export**: 2 checkbox (PDF + Excel) untuk admin saja
- super_admin: "Semua" badge
- user/spectator: "Tidak berlaku"

**Actions:** Edit, Delete (kecuali user diri sendiri)

### 11.2 Create/Edit Form
- **Nama** (required)
- **Email** (required, unique)
- **No Telepon**, **Alamat**
- **Role** (required, dropdown):
  - Super Admin
  - Admin
  - Spectator
  - User
- **Gudang** (conditional based on role):
  - Role = User: single dropdown (required)
  - Role = Admin/Spectator: multi-checkbox grid (required min 1)
  - Role = Super Admin: hidden
- **Hak Export** (admin only):
  - Checkbox PDF
  - Checkbox Excel
- **Penerima Email Transaksi** (admin/super_admin)
- **Password** (required, min 8) + confirmation

---

## 12. Gudang (super_admin only)

### 12.1 Index
**Table:** Nama Gudang, Alamat, Jumlah User, Jumlah Produk, Aksi
**Actions:** View, Edit, Delete

### 12.2 Create/Edit Form
- **Nama Gudang** (required)
- **Alamat Gudang** (textarea)

---

## 13. Profil Saya `/app/profil`

**Sections:**
- **Avatar Upload** (drag/drop + crop)
- **Info Pribadi**: Nama, Email (readonly), Role (readonly), No Telepon, Alamat
- **Ganti Password**: Current Password, New Password, Confirm Password

---

## 14. Customer Portal (Subdomain/Path Terpisah)

**URL**: `/customer/*` atau subdomain `customer.hibiscusefsya.com`

### 14.1 Login Flow
1. `/customer` atau `/customer/login` → form input no_telp
2. POST `/customer/check-phone` → validasi nomor exists → redirect ke PIN form
3. Form PIN 6 digit → POST `/customer/login` → set session
4. Redirect ke `/customer/dashboard`

### 14.2 Dashboard
- Greeting + nama customer
- Summary cards: total transaksi, total kunjungan, sisa tagihan
- Recent activity

### 14.3 History (Penjualan)
- List penjualan customer
- Click → detail (read-only invoice view)

### 14.4 Kunjungan History
- List kunjungan customer
- Detail kunjungan

---

## Public Routes (No Auth)

### Public Invoice
**URL**: `/invoice/{type}/{uuid}`
**Display**: Read-only invoice view dengan styling print-friendly
**Type**: penjualan, pembelian, biaya, kunjungan, pembayaran, penerimaan-barang

### Public Invoice PDF
**URL**: `/invoice/{type}/{uuid}/download`
**Output**: PDF file download dengan filename `{PREFIX}-{nomor}.pdf`

### Public Receipt (Struk Thermal)
**URL**: `/struk/{type}/{uuid}`
**Display**: Layout struk thermal printer (58mm width)

---

## JavaScript Features (Wajib Ada)

### 1. Bluetooth Thermal Print
**File**: `public/js/bluetooth-print.js` (1957 baris, sudah di-copy)
**Class**: `BluetoothThermalPrinter`
**Pakai di**: Detail page penjualan/pembelian/biaya/kunjungan
**Fitur**:
- Web Bluetooth API (Chrome/Edge)
- Preview dialog 58mm/80mm sebelum print
- Logo bitmap printing (ESC *)
- QR Code generation (offline via QRCode.js)
- Chunked BLE sending (128 bytes, 150ms delay)
- ESC/POS commands lengkap

### 2. Barcode/QR Scanner
**Library**: `html5-qrcode`
**Pakai di**: Form create penjualan/pembelian/kunjungan/pembayaran/penerimaan
**Modes**:
- Kontak: QR Code (square)
- Produk: 1D barcode (EAN-13/UPC/Code128) - wide
**Validasi**: produk harus exist di gudang yang dipilih

### 3. Geolocation
**Trigger**: Auto saat halaman create dimuat
**Fields**: koordinat (semua transaksi)
**UI**: Button "Ambil Lokasi" + "Buka di Google Maps"

### 4. Live Calculator
**Item**: `(qty × harga × (1 - disc%/100)) - disc_nominal`
**Total**: `subtotal - diskon_akhir + pajak`
**Format**: Indonesian rupiah (`Rp 1.000.000,00`)

### 5. Select2 Dropdown
**Pakai di**: Kontak, Produk, Gudang select fields
**Features**: search, clear button, AJAX loading

### 6. Charts.js
**Pakai di**: Dashboard widgets

---

## Role Visibility Matrix Lengkap

| Komponen | super_admin | admin | spectator | user |
|----------|-------------|-------|-----------|------|
| Login web panel | ✅ | ✅ | ✅ | ✅ |
| Dashboard | ✅ all | ✅ gudang | ✅ gudang | ✅ own |
| **Penjualan** | | | | |
| - Buat baru | ✅ | ✅ | ❌ | ✅ |
| - Lihat semua | ✅ | ✅ gudang | ✅ gudang | own |
| - Edit full | ✅ | ❌ | ❌ | ❌ |
| - Tambah lampiran | ✅ | ✅ | ❌ | ✅ own |
| - Hapus | ✅ | ❌ | ❌ | ❌ |
| - Approve (Pending→Approved) | ✅ | ✅ gudang | ❌ | ❌ |
| - Mark Paid (Approved→Lunas) | ✅ | ✅ gudang | ❌ | ❌ |
| - Unmark Paid | ✅ | ❌ | ❌ | ❌ |
| - Cancel pending | ✅ | ✅ gudang | ❌ | ✅ own |
| - Cancel approved/lunas | ✅ | ❌ | ❌ | ❌ |
| - Uncancel | ✅ | ❌ | ❌ | ❌ |
| - Print Bluetooth | ✅ | ✅ | ✅ | ✅ |
| - QR Code | ✅ | ✅ | ✅ | ✅ |
| **Master Data** | | | | |
| - Kontak (CRUD) | ✅ | ✅ scoped | view all | ✅ own |
| - Produk (CRUD) | ✅ | ❌ | ❌ | ❌ |
| - Lihat Produk | ✅ | ✅ gudang | ✅ gudang | ✅ own |
| **Stok** | | | | |
| - Lihat Stok | ✅ | ✅ gudang | ✅ gudang | ❌ |
| - Update Manual | ✅ | ❌ | ❌ | ❌ |
| - Stok Log | ✅ | ✅ gudang | ❌ | ❌ |
| - Export Excel | ✅ | ✅ permission | ✅ permission | ❌ |
| **Pengaturan** | | | | |
| - Users (CRUD) | ✅ | ❌ | ❌ | ❌ |
| - Gudang (CRUD) | ✅ | ❌ | ❌ | ❌ |
| - Switch Gudang | ✅ | ✅ multi | ✅ multi | ❌ |
| **Report** | | | | |
| - Generate Report | ✅ | ✅ permission | ❌ | ❌ |
| - Export PDF | ✅ | ✅ flag | ❌ | ❌ |
| - Export Excel | ✅ | ✅ flag | ❌ | ❌ |
| - Daily Report PDF | ✅ | ✅ | ✅ | ✅ |

---

## Implementasi di Filament

### File Structure (target)
```
app/Filament/
├── Resources/
│   ├── Penjualans/
│   │   ├── PenjualanResource.php
│   │   ├── Pages/
│   │   │   ├── ListPenjualans.php (dengan stats widget)
│   │   │   ├── CreatePenjualan.php
│   │   │   ├── EditPenjualan.php
│   │   │   └── ViewPenjualan.php (custom dengan action buttons)
│   │   ├── Schemas/
│   │   │   └── PenjualanForm.php (dengan Repeater untuk items)
│   │   └── Tables/
│   │       └── PenjualansTable.php (dengan filters, actions)
│   ├── Pembelians/, Biayas/, Kunjungans/, Pembayarans/, PenerimaanBarangs/
│   ├── Kontaks/, Produks/
│   ├── Users/, Gudangs/
├── Pages/
│   ├── Stok.php (custom Livewire page)
│   ├── StokLog.php
│   └── ProfileEdit.php (atau pakai built-in profile)
├── Widgets/
│   ├── KanbanHariIni.php
│   ├── KanbanBulanIni.php
│   ├── PenjualanTrendChart.php
│   ├── StatusDoughnutChart.php
│   ├── TransaksiPerGudangChart.php
│   ├── PendingApprovalCount.php
│   └── RecentActivityTable.php
└── Actions/ (reusable)
    ├── ApproveAction.php
    ├── CancelAction.php
    ├── UncancelAction.php
    ├── MarkPaidAction.php
    ├── BluetoothPrintAction.php
    └── QrCodeAction.php
```

### Filament Components Used
- `Section` dengan icon untuk group form
- `Tabs` jika form panjang
- `Repeater` untuk items (with reorderable)
- `TextInput::numeric()` dengan prefix Rp
- `Select::searchable()` untuk dropdown kontak/produk
- `DatePicker`, `DateTimePicker`
- `FileUpload::multiple()` untuk lampiran
- `Toggle` untuk switch
- `BadgeColumn` untuk status
- `Action` dengan modal confirmation
- `BulkAction` untuk batch operations
- `Filter` dengan SelectFilter, TernaryFilter, Filter (custom)
- `Stats` widget untuk summary cards
- `Chart` widget untuk graphs
- `RelationManager` untuk pembayaran di penjualan detail

### Custom Pages Needed (Livewire)
- **StokPage** — form + accordion stok (left/right layout)
- **StokLogPage** — filtered table
- **DashboardPage** (atau pakai widget Filament dashboard)

### Render Hooks
- `BODY_END` → inject `bluetooth-print.js` di semua halaman
- `PAGE_HEADER_ACTIONS_BEFORE` → tombol Generate Report di dashboard
- `USER_MENU_PROFILE_BEFORE` → custom menu items

### Resource Action Buttons (per record)
Untuk tabel transaksi, pakai pattern:
```php
->recordActions([
    ViewAction::make(),
    EditAction::make()->visible(fn() => auth()->user()->isSuperAdmin()),
    Action::make('approve')->action(...)->visible(fn($record) => ...),
    Action::make('cancel')->...
    Action::make('bluetoothPrint')->extraAttributes(['data-bt-print' => true]),
    Action::make('qrCode')->modalContent(...)
])
```

### Visibility Pattern
```php
public static function canCreate(): bool {
    return !auth()->user()?->isSpectator();
}
public static function canEdit($record): bool {
    return auth()->user()?->isSuperAdmin();
}
public static function canDelete($record): bool {
    return auth()->user()?->isSuperAdmin();
}
public static function getEloquentQuery(): Builder {
    $query = parent::getEloquentQuery();
    $user = auth()->user();
    if ($user->isSuperAdmin()) return $query;
    if ($user->role === 'admin' || $user->role === 'spectator') {
        $cg = $user->getCurrentGudang();
        return $cg ? $query->where('gudang_id', $cg->id) : $query->whereRaw('1=0');
    }
    return $query->where('user_id', $user->id);
}
```
