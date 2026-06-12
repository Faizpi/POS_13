# Comprehensive Parity Audit: Laravel 7 (Views) → Laravel 13 (Filament)

> **Tanggal**: 6 Juni 2026
> **Sumber**: Analisis manual seluruh folder `resources/views/` (L7) vs `app/Filament/Resources/` (L13)

---

## Ringkasan Status

| Area | Status | Gap |
|------|--------|-----|
| **Dashboard** | ❌ Belum ada | Halaman dashboard Filament dengan widget belum dibuat |
| **Layout/Sidebar/Navbar** | ⚠️ Sebagian | Sidebar menu ada via `navigationGroup`, notifikasi & gudang switcher belum |
| **Penjualan** | ⚠️ 70% | Index, Create, Edit, View sudah jalan. Print/Struk/Public invoice belum |
| **Pembelian** | ⚠️ 40% | Index, Create, Edit ada. View page belum ada (tidak ada actions) |
| **Biaya** | ⚠️ 40% | Index, Create, Edit ada. View page belum ada (tidak ada actions) |
| **Kunjungan** | ⚠️ 40% | Index, Create, Edit ada. View page belum ada (tidak ada actions) |
| **Pembayaran** | ⚠️ 40% | Index, Create, Edit ada. View page belum ada (tidak ada actions) |
| **Penerimaan Barang** | ⚠️ 40% | Index, Create, Edit ada. View page belum ada (tidak ada actions) |
| **Produk** | ⚠️ 40% | Index, Create, Edit ada. View page belum ada. Barcode/QR Print belum |
| **Kontak** | ⚠️ 40% | Index, Create, Edit ada. View page belum ada. Riwayat penjualan belum |
| **Stok** | ❌ Belum ada | Belum ada resource stok |
| **Users** | ⚠️ 40% | Index, Create, Edit ada. View page belum |
| **Gudang** | ⚠️ 40% | Index, Create, Edit ada. View page belum |
| **Profil** | ❌ Belum ada | Belum ada halaman profil + change password |
| **Reports** | ❌ Belum ada | 9 halaman reports (daily, biaya, kunjungan, dll) belum ada |
| **Public Invoices** | ❌ Belum ada | 12 halaman invoice publik belum ada |
| **Print/Struk** | ❌ Belum ada | Print thermal, struk, bluetooth print belum ada |
| **Export (Excel/PDF)** | ❌ Belum ada | Export report modal/dialog belum |
| **Email Templates** | ❌ Belum ada | 6 email template blum di-migrasi |
| **Customer Portal** | ❌ Belum ada | Dashboard customer, login PIN, history, dll |
| **Admin Gudang/Spectator** | ❌ Belum ada | Manajemen akses gudang admin/spectator |

---

## 1. LAYOUT & NAVIGASI

### Layout (`layouts/app.blade.php`) — 2584 baris

**Komponen yang ada:**
- ✅ Sidebar menu dengan grouping:
  - **Menu Utama**: Dashboard
  - **Transaksi**: Kunjungan → Penjualan → Pembayaran → Biaya → Pembelian → Penerimaan Barang
  - **Pengaturan** (super_admin only): Pengguna → Gudang → Produk
  - **Master Data** (admin/spectator/user): Kontak → Stok Gudang
  - **Akun**: Profil Saya
- ✅ Topbar: Logo, Search?
- ✅ Notification bell (pending approvals) dengan dropdown realtime
- ✅ User dropdown: Profil, Ganti Gudang (multi-gudang admin/spectator), Logout
- ✅ Responsive mobile sidebar (slide-in + overlay)
- ✅ Footer dengan copyright
- ✅ Logout modal

**Status Filament:**
- ⚠️ Sidebar grouping sudah ada via `navigationGroup` dan `navigationSort`
- ⚠️ Navigation badge (pending count) sudah ada untuk Penjualan & Pembelian
- ❌ Notification bell dropdown (pending transaksi) belum ada
- ❌ Gudang switcher di user dropdown belum ada
- ❌ Mobile responsive sidebar belum diimplementasi
- ❌ Custom styling (SB Admin 2 → Tailwind/Filament) belum selesai

---

## 2. DASHBOARD

### Source: `dashboard.blade.php` — ~920 baris

**Komponen:**
- **Kanan Hari Ini**: Penjualan (total + count), Biaya (total + count), Pembayaran (total + count)
- **Kanan Bulan Ini**: Penjualan, Biaya, Pembayaran (total + count)
- **4 Main Cards**: Penjualan, Pembelian, Kunjungan, Canceled (nominal bulan ini)
- **4 Secondary Cards**: Biaya Masuk, Biaya Keluar, Total Produk, Card Dinamis (pending approvals/total transaksi)
- **Filter Gudang**: Dropdown gudang (super_admin/admin/spectator)
- **Charts** (super_admin/spectator only):
  - Line Chart: Tren 6 Bulan (Penjualan, Pembelian, Biaya)
  - Doughnut Chart: Komposisi Status (Pending, Approved, Canceled)
  - Bar Chart: Transaksi per Gudang (Penjualan, Pembelian)
  - Bar Chart: Kuantitas Terjual per Sales (dengan filter tanggal & produk)
- **Tabel Semua Aktivitas** (super_admin):
  - Nomor (link), Tanggal, Pembuat, Status, Total
  - Dengan search input + pagination
- **Tabel Menunggu Approval** (admin/spectator)
- **Welcome Card** (user biasa) dengan link laporan harian PDF
- **Modal Export**:
  - Tipe transaksi, rentang tanggal, status filter, gudang filter, jenis biaya (conditional), tujuan kunjungan (conditional), sales filter
  - Export ke PDF atau Excel

**Status Filament:**
- ❌ **Belum ada sama sekali**. Tidak ada file di `app/Filament/Pages/` atau `app/Filament/Widgets/`

---

## 3. PENJUALAN (Penjualan)

### Lama: `resources/views/penjualan/` — 6 files
### Baru: `app/Filament/Resources/Penjualans/`

| View | Status | Detail |
|------|--------|--------|
| `index.blade.php` (242L) | ✅ Done (Filament Table) | 4 summary cards + table + pagination + delete modal |
| `create.blade.php` (770+L) | ✅ Done (Filament Form) | Produk items table, Select2, auto-calculate, mobile cards |
| `edit.blade.php` | ✅ Done (Filament Form) | Sama dengan create |
| `show.blade.php` (493L) | ✅ Done (Filament ViewRecord) | Info, Items, Total, Memo, Lampiran + 7 actions |
| `print.blade.php` (352L) | ❌ Belum | Thermal receipt print |
| `struk.blade.php` (281L) | ❌ Belum | Struk versi lebih sederhana |

### Detail `show.blade.php` OLD — Actions:
1. ✅ **Setujui** (approve) — sudah ada di ViewPenjualan
2. ✅ **Lunas** (markAsPaid) — sudah ada
3. ✅ **Cancel** (cancel) — sudah ada (dengan modal konfirmasi)
4. ✅ **Uncancel** (uncancel) — sudah ada (dengan modal)
5. ✅ **Print Bluetooth** — sudah ada (onclick js)
6. ✅ **Cetak Struk** — link ke print page
7. ✅ **QR Code** — sudah ada (modal QR)
8. ✅ **Kembali** (back) — back to index

### Detail `show.blade.php` OLD — Info fields:
**Kolom Kiri:**
- Sales (user name)
- Pelanggan
- Nomor Telepon
- Tgl Transaksi
- Jatuh Tempo
- Dibuat (datetime)
- Diupdate (datetime, conditional)
- Gudang
- Tipe Harga (badge: Retail/Grosir)
- Approver
- Syarat Pembayaran

**Kolom Kanan:**
- Status (badge: Lunas/Approved/Pending/Canceled)
- Subtotal (hitung dari items)
- Diskon Akhir (conditional)
- Pajak (X%) + nominal
- Grand Total (besar + warna biru)
- No Referensi
- Tag
- Koordinat (link Google Maps)

**Rincian Produk Items:**
- Item Code, Produk, Deskripsi, Qty, Harga, Disc%, Disc Rp, Batch, Exp, Total
- Juga Mobile cards version

**Sections:**
- Memo
- Lampiran (gambar + non-gambar, preview, delete super_admin)
- Cancel Modal
- Uncancel Modal
- QR Code Modal
- Bluetooth Print JS

### Filament ViewPenjualan yang sudah:
✅ Info Utama (Section, 3 columns)
✅ Item Penjualan (RepeatableEntry)
✅ Total & Pajak (Section)
✅ Catatan & Lampiran (Section, collapsible)
✅ Approve, MarkAsPaid, BukaLunas, Cancel, Uncancel, Bluetooth Print, QR Code, Edit, Delete actions

**Yang KURANG dari ViewPenjualan Filament:**
- ❌ Lampiran images preview (belum ada gambar thumbnail)
- ❌ Lampiran delete per-item
- ❌ Koordinat dengan link Google Maps
- ❌ Tag field
- ❌ Mobile responsive product cards
- ❌ `no_telepon` field (resolved_no_telepon)
- ❌ `alamat_penagihan` field
- ❌ Tipe harga badge (Retail/Grosir)
- ❌ No Referensi
- ❌ Approver name hanya dari user, perlu fallback

### Print/Struk:
- `print.blade.php` → Thermal receipt 58mm (Courier New, auto-print)
- `struk.blade.php` → Versi lebih sederhana
- Keduanya **belum ada di Filament**

### Public Invoice: `public/invoice-penjualan*.blade.php` (2 files)
- Belum ada rute publik di Filament

---

## 4. PEMBELIAN

### Lama: `resources/views/pembelian/` — 6 files
### Baru: `app/Filament/Resources/Pembelians/`

| View | Status | Detail |
|------|--------|--------|
| `index.blade.php` | ✅ Done | Table with summary cards |
| `create.blade.php` | ✅ Done | Form with items |
| `edit.blade.php` | ✅ Done | |
| `show.blade.php` (411L) | ❌ **Belum ada ViewRecord** | Tidak ada halaman view/detail |
| `print.blade.php` | ❌ Belum | |
| `struk.blade.php` | ❌ Belum | |

### Detail `show.blade.php` OLD — Actions:
- ✅ Setujui (approve) — BUT no ViewPenjualan equivalent for Pembelian
- ❌ Cancel — with modal
- ❌ Print Bluetooth
- ❌ Cetak Struk
- ❌ QR Code — dengan modal
- ❌ Kembali

### Info fields yang harus ada di ViewPembelian:
**Kolom Kiri:**
- Pembuat (user name)
- Staf Penyetuju
- Email Penyetuju
- Gudang
- Urgensi
- Tahun Anggaran

**Kolom Kanan:**
- Tanggal
- Jatuh Tempo
- Dibuat
- Diupdate (conditional)
- Syarat Bayar
- Status (badge)
- Koordinat (link Google Maps)

**Rincian Produk:**
- Produk (nama + item_code), Deskripsi, Qty, Harga, Disc%, Total
- Summary: Subtotal, Diskon Akhir, Pajak, Grand Total
- Mobile cards version

**Sections:**
- Memo
- Lampiran (dengan preview gambar + delete super_admin)
- Cancel Modal
- QR Code Modal

### Filament Class:
- `PembelianResource.php` — ✅ form & table sudah
- `Pages/` — hanya index, create, edit
- ❌ **ViewPembelian** belum ada

---

## 5. BIAYA

### Lama: `resources/views/biaya/` — 6 files
### Baru: `app/Filament/Resources/Biayas/`

| View | Status | Detail |
|------|--------|--------|
| `index.blade.php` | ✅ Done | |
| `create.blade.php` | ✅ Done | |
| `edit.blade.php` | ✅ Done | |
| `show.blade.php` (387L) | ❌ **Belum ada ViewRecord** | Tidak ada halaman view/detail |
| `print.blade.php` | ❌ Belum | |
| `struk.blade.php` | ❌ Belum | |

### Detail `show.blade.php` OLD — Actions:
- ✅ Setujui (approve)
- ❌ Cancel — with modal
- ❌ Print Bluetooth
- ❌ Cetak Struk
- ❌ QR Code — dengan modal
- ❌ Kembali

### Info fields:
**Kolom Kiri:**
- Jenis Biaya (badge: Masuk/Keluar)
- Pembuat
- Gudang
- Approver
- Penerima
- Tgl Transaksi
- Dibuat / Diupdate
- Bayar Dari
- Cara Pembayaran

**Kolom Kanan:**
- Status (badge)
- Subtotal (dari items sum)
- Pajak
- Grand Total (besar)
- Tag
- Koordinat (Google Maps)

**Rincian Biaya Items:**
- Akun Biaya (Kategori), Deskripsi, Jumlah
- Mobile cards version

**Sections:**
- Memo
- Lampiran (preview + delete)
- Cancel Modal
- QR Code Modal

### Filament:
- `BiayaResource.php` — ✅ form & table
- ❌ **ViewBiaya** belum ada

---

## 6. KUNJUNGAN

### Lama: `resources/views/kunjungan/` — 5 files
### Baru: `app/Filament/Resources/Kunjungans/`

| View | Status |
|------|--------|
| `index.blade.php` | ✅ Done |
| `create.blade.php` | ✅ Done |
| `edit.blade.php` | ✅ Done |
| `show.blade.php` (359L) | ❌ **Belum ada ViewRecord** |
| `print.blade.php` | ❌ Belum |

### Detail `show.blade.php` OLD — Actions:
- ✅ Setujui (approve)
- ❌ Cancel — with modal
- ❌ Print Bluetooth
- ❌ Cetak Struk
- ❌ QR Code — dengan modal
- ❌ Kembali

### Info fields:
- Tujuan Kunjungan (badge: Pemeriksaan Stock, Penagihan, Promo Gratis, Promo Sample, dll)
- No Kunjungan
- Pembuat
- Approver
- Gudang
- Tgl Kunjungan
- Dibuat
- Status
- Kode Kontak
- Pelanggan (sales_nama)
- No Telepon
- Alamat
- Koordinat (link Google Maps)
- **Produk Terkait** (items dengan kode, nama, qty, batch, expired)
- Memo
- Lampiran (preview + delete)
- Cancel Modal
- QR Code Modal

### Filament:
- `KunjunganResource.php` — ✅ form & table
- ❌ **ViewKunjungan** belum ada

---

## 7. PEMBAYARAN

### Lama: `resources/views/pembayaran/` — 5 files
### Baru: `app/Filament/Resources/Pembayarans/`

| View | Status |
|------|--------|
| `index.blade.php` | ✅ Done |
| `create.blade.php` | ✅ Done |
| `edit.blade.php` | ✅ Done |
| `show.blade.php` (286L) | ❌ **Belum ada ViewRecord** |
| `print.blade.php` | ❌ Belum |
| `daily-export-pdf.blade.php` | ❌ Belum |

### Detail `show.blade.php` OLD — Actions:
- ❌ Print
- ❌ QR Code — dengan modal
- ❌ Approve (button di action card)
- ❌ Batalkan (Cancel)
- ❌ Batalkan Pembatalan (Uncancel)
- ❌ Hapus (super_admin only)

### Info fields:
- Nomor (badge besar)
- Tanggal
- Metode Pembayaran
- Status
- Dibuat oleh
- Approver
- Gudang
- Dibuat (datetime)

**Referensi Invoice Penjualan:**
- Nomor Invoice (link ke penjualan.show)
- Pelanggan
- Total Invoice
- Sisa Hutang (text-danger)

**Jumlah Bayar** (besar, text-success)
**Keterangan**

**Action Card:**
- Approve (admin/super_admin, Pending)
- Batalkan (admin/super_admin, Pending)
- Batalkan Pembatalan (super_admin, Canceled)
- Hapus (super_admin)

**Lampiran Card:**
- List lampiran dengan link + delete (super_admin)

### Filament:
- `PembayaranResource.php` — ✅ form & table
- ❌ **ViewPembayaran** belum ada

---

## 8. PENERIMAAN BARANG

### Lama: `resources/views/penerimaan-barang/` — 4 files
### Baru: `app/Filament/Resources/PenerimaanBarangs/`

| View | Status |
|------|--------|
| `index.blade.php` | ✅ Done |
| `create.blade.php` | ✅ Done |
| `edit.blade.php` | ✅ Done |
| `show.blade.php` (329L) | ❌ **Belum ada ViewRecord** |
| `print.blade.php` | ❌ Belum |

### Detail `show.blade.php` OLD — Actions:
- ❌ Print
- ❌ QR Code — with modal
- ❌ Approve & Tambah Stok
- ❌ Batalkan
- ❌ Batalkan Pembatalan (super_admin)
- ❌ Hapus (super_admin)

### Info fields:
- Nomor (badge besar)
- Tanggal
- No Surat Jalan
- Status
- Dibuat oleh
- Approver
- Gudang
- Dibuat

**Referensi Invoice Pembelian:**
- Nomor Invoice (link ke pembelian.show)
- Supplier

**Detail Barang Diterima:**
- Kode, Nama Produk, Tipe Stok (badge: Gratis/Sample/Penjualan), Batch, Expired, Qty Diterima, Keterangan
- Total Qty di tfoot
- Keterangan

**Action Card:**
- Approve & Tambah Stok
- Batalkan
- Batalkan Pembatalan
- Hapus

**Status Info:**
- Card khusus: "Stok telah ditambahkan ke gudang" (jika Approved)

**Lampiran Card**

### Filament:
- `PenerimaanBarangResource.php` — ✅ form & table
- ❌ **ViewPenerimaanBarang** belum ada

---

## 9. PRODUK

### Lama: `resources/views/produk/` — 5 files
### Baru: `app/Filament/Resources/Produks/`

| View | Status |
|------|--------|
| `index.blade.php` | ✅ Done |
| `create.blade.php` | ✅ Done |
| `edit.blade.php` | ✅ Done |
| `show.blade.php` (208L) | ❌ **Belum ada ViewRecord** |
| `print.blade.php` | ❌ Belum |

### Detail `show.blade.php` OLD:
- **Actions**: Edit, Kembali
- **Info Produk**: Kode Produk, Nama, Harga Retail, Harga Grosir, Satuan, Deskripsi, Dibuat, Diupdate
- **Stok per Gudang**: Tabel gudang + stok (color-coded: green >10, yellow >0, red 0), total
- **Barcode**: SVG barcode EAN-13 (via milon/barcode), fallback warning, Download PDF, Print
- **QR Code**: QR dengan data produk

### Filament:
- `ProdukResource.php` — ✅ form & table
- ❌ **ViewProduk** belum ada
- ❌ Barcode SVG
- ❌ QR Code
- ❌ Download PDF / Print
- ❌ Stok per Gudang

---

## 10. KONTAK

### Lama: `resources/views/kontak/` — 5 files
### Baru: `app/Filament/Resources/Kontaks/`

| View | Status |
|------|--------|
| `index.blade.php` | ✅ Done |
| `create.blade.php` | ✅ Done |
| `edit.blade.php` | ✅ Done |
| `show.blade.php` (255L) | ❌ **Belum ada ViewRecord** |
| `print.blade.php` | ❌ Belum |

### Detail `show.blade.php` OLD:
- **Actions**: Edit, Kembali
- **Info Kontak**: Kode Kontak (badge), Nama, Email, No Telepon, PIN (show/hide toggle), Alamat, Diskon %, Gudang, Dibuat, Diupdate
- **Barcode**: Barcode 128, Download PDF, Print
- **QR Code**: QR dengan data kontak
- **Riwayat Penjualan**: Tabel transaksi penjualan (Tanggal, No Invoice link, Gudang, Sales, Produk, Grand Total, Status)

### Filament:
- `KontakResource.php` — ✅ form & table
- ❌ **ViewKontak** belum ada
- ❌ Riwayat Penjualan (walaupun sudah ada penjualan query di resource, belum ditampilkan)
- ❌ PIN toggle
- ❌ Barcode/QR

---

## 11. STOK

### Lama: `resources/views/stok/` — 2 files
### Baru: **Tidak ada resource stok**

| View | Status |
|------|--------|
| `index.blade.php` (220L) | ❌ **Belum ada** |
| `log.blade.php` | ❌ Belum ada |

### Detail `stok/index.blade.php` OLD:
- **Tambah/Update Stok Awal** (super_admin only): Pilih Gudang, Pilih Produk, Stok Penjualan, Stok Gratis, Submit
- **Tabel Stok**: Filter berdasarkan gudang, tabel dengan kolom: Kode, Nama Produk, Gudang, Stok Penjualan, Stok Gratis, Stok Rusak, Ttl Stok, Aksi

### Stok Log:
- Riwayat perubahan stok

### Filament:
- ❌ **Tidak ada resource stok sama sekali**

---

## 12. USERS

### Lama: `resources/views/users/` — 3 files
### Baru: `app/Filament/Resources/Users/`

| View | Status |
|------|--------|
| `index.blade.php` | ✅ Done |
| `create.blade.php` | ✅ Done |
| `edit.blade.php` | ✅ Done |
| (Tidak ada show) | N/A |

---

## 13. GUDANG

### Lama: `resources/views/gudang/` — 3 files
### Baru: `app/Filament/Resources/Gudangs/`

| View | Status |
|------|--------|
| `index.blade.php` | ✅ Done |
| `create.blade.php` | ✅ Done |
| `edit.blade.php` | ✅ Done |

---

## 14. PROFIL

### Source: `profil/show.blade.php` — 280 baris

**Komponen:**
- **Hero Section**: Ikon, judul, deskripsi, role badge, gudang aktif badge
- **Avatar Card**: Foto/initial, upload avatar (dengan preview + cancel), hapus foto
- **Informasi Akun**: Nama, Email, No Telepon, Alamat, Gudang Aktif
- **Edit Profil Form**: Nama (readonly), Email (readonly), No Telepon (editable), Alamat (textarea)
- **Ubah Password**: Password saat ini, Password baru, Konfirmasi password
- Toggle show/hide password

### Status Filament:
- ❌ **Belum ada halaman profil**

---

## 15. REPORTS

### Source: `resources/views/reports/` — 9 files

| File | Deskripsi | Status |
|------|-----------|--------|
| `daily-report.blade.php` (561L) | Laporan harian PDF | ❌ |
| `penjualan.blade.php` | Report penjualan | ❌ |
| `pembelian.blade.php` | Report pembelian | ❌ |
| `biaya.blade.php` | Report biaya | ❌ |
| `kunjungan.blade.php` | Report kunjungan | ❌ |
| `pembayaran.blade.php` | Report pembayaran | ❌ |
| `stok.blade.php` | Report stok | ❌ |
| `transactions.blade.php` | Report transaksi | ❌ |
| `pdf.blade.php` | PDF wrapper | ❌ |

### Status Filament:
- ❌ **Belum ada reports sama sekali**

---

## 16. PUBLIC INVOICES

### Source: `resources/views/public/` — 12 files

| File | Status |
|------|--------|
| `invoice-penjualan.blade.php` | ❌ |
| `invoice-penjualan-pdf.blade.php` | ❌ |
| `invoice-pembelian.blade.php` | ❌ |
| `invoice-pembelian-pdf.blade.php` | ❌ |
| `invoice-biaya.blade.php` | ❌ |
| `invoice-biaya-pdf.blade.php` | ❌ |
| `invoice-kunjungan.blade.php` | ❌ |
| `invoice-kunjungan-pdf.blade.php` | ❌ |
| `invoice-pembayaran.blade.php` | ❌ |
| `invoice-pembayaran-pdf.blade.php` | ❌ |
| `invoice-penerimaan.blade.php` | ❌ |
| `invoice-penerimaan-pdf.blade.php` | ❌ |

Setiap file berisi tampilan invoice untuk publik (tanpa login) dengan data transaksi lengkap. Versi PDF adalah versi yang dioptimalkan untuk PDF (biasanya pakai DomPDF).

---

## 17. PRINT & STRUK

### Source: `resources/views/print/` — 3 files + masing-masing `print.blade.php` di tiap modul

| File | Deskripsi | Status |
|------|-----------|--------|
| `print/penjualan-image.blade.php` | Print image untuk penjualan | ❌ |
| `print/pembelian-image.blade.php` | Print image untuk pembelian | ❌ |
| `print/biaya-image.blade.php` | Print image untuk biaya | ❌ |
| `penjualan/print.blade.php` (352L) | Print thermal 58mm | ❌ |
| `penjualan/struk.blade.php` (281L) | Struk versi alternatif | ❌ |
| `pembelian/print.blade.php` | Print thermal pembelian | ❌ |
| `pembelian/struk.blade.php` | Struk pembelian | ❌ |
| `biaya/print.blade.php` | Print thermal biaya | ❌ |
| `biaya/struk.blade.php` | Struk biaya | ❌ |
| `kunjungan/print.blade.php` | Print kunjungan | ❌ |
| `pembayaran/print.blade.php` | Print pembayaran | ❌ |
| `penerimaan-barang/print.blade.php` | Print penerimaan | ❌ |
| `produk/print.blade.php` | Print barcode produk | ❌ |
| `kontak/print.blade.php` | Print kontak | ❌ |

---

## 18. PDF INVOICES

### Source: `resources/views/pdf/` — 4 files
- `invoice-penjualan.blade.php`
- `invoice-pembelian.blade.php`
- `invoice-biaya.blade.php`
- `invoice-kunjungan.blade.php`

PDF version untuk DomPDF/mPDF conversion.

---

## 19. EMAIL TEMPLATES

### Source: `resources/views/emails/` — 6 files
- `invoice-penjualan.blade.php`
- `invoice-pembelian.blade.php`
- `invoice-biaya.blade.php`
- `invoice-kunjungan.blade.php`
- `transaksi-invoice.blade.php`
- `transaksi-notification.blade.php`

---

## 20. CUSTOMER PORTAL

### Source: `resources/views/customer/` — 8 files

| File | Deskripsi | Status |
|------|-----------|--------|
| `dashboard.blade.php` | Dashboard customer | ❌ |
| `login.blade.php` | Login customer | ❌ |
| `pin.blade.php` | PIN verification | ❌ |
| `history.blade.php` | Riwayat transaksi | ❌ |
| `history-detail.blade.php` | Detail riwayat | ❌ |
| `kunjungan.blade.php` | Data kunjungan | ❌ |
| `kunjungan-detail.blade.php` | Detail kunjungan | ❌ |
| `layouts/` | Layout customer | ❌ |

---

## 21. ADMIN GUDANG & SPECTATOR

### Source: `resources/views/admin-gudang/` — 2 files, `resources/views/spectator-gudang/` — 2 files

| File | Deskripsi | Status |
|------|-----------|--------|
| `admin-gudang/index.blade.php` | Manajemen akses admin ke gudang | ❌ |
| `admin-gudang/edit.blade.php` | Edit akses admin ke gudang | ❌ |
| `spectator-gudang/index.blade.php` | Manajemen akses spectator ke gudang | ❌ |
| `spectator-gudang/edit.blade.php` | Edit akses spectator ke gudang | ❌ |

---

## 22. OTHER PAGES

| Source | Status | Notes |
|--------|--------|-------|
| `auth/login.blade.php` | ❌ | Filament handle sendiri |
| `auth/register.blade.php` | ❌ | Filament handle sendiri |
| `auth/passwords/` | ❌ | Filament handle sendiri |
| `auth/verify.blade.php` | ❌ | |
| `errors/503.blade.php` | ❌ | Maintenance mode |
| `partials/barcode-scanner-modal.blade.php` | ❌ | Modal scanner barcode |
| `api-docs/index.blade.php` | ❌ | Dokumentasi API |
| `print_trigger.blade.php` | ❌ | Trigger print |
| `vendor/pagination/` | ❌ | Custom pagination |

---

## 23. SUMMARY: GAPS PER MODUL

### Modul dengan GAP KRITIS (harus segera dibuat):

| # | Modul | Yang Kurang |
|---|-------|-------------|
| 1 | **Dashboard** | Belum ada sama sekali (charts, cards, export modal, filter) |
| 2 | **ViewPage Biaya** | Belum ada ViewRecord + actions (approve, cancel, qr) |
| 3 | **ViewPage Pembelian** | Belum ada ViewRecord + actions (approve, cancel, qr) |
| 4 | **ViewPage Kunjungan** | Belum ada ViewRecord + actions (approve, cancel, qr) |
| 5 | **ViewPage Pembayaran** | Belum ada ViewRecord + actions (approve, cancel, qr, hapus) |
| 6 | **ViewPage PenerimaanBarang** | Belum ada ViewRecord + actions (approve+tambah stok, cancel, qr, hapus) |
| 7 | **ViewPage Produk** | Belum ada ViewRecord (stok per gudang, barcode, qr, print) |
| 8 | **ViewPage Kontak** | Belum ada ViewRecord (riwayat penjualan, pin, barcode, qr) |

### Modul dengan GAP SEDANG:

| # | Modul | Yang Kurang |
|---|-------|-------------|
| 9 | **Print/Struk** | Semua print thermal (8 modul) belum ada |
| 10 | **Public Invoices** | Semua 12 halaman publik belum ada |
| 11 | **Reports** | Semua 9 halaman report belum ada |
| 12 | **Stok Management** | Resource stok belum ada |
| 13 | **Export (Excel/PDF)** | Export modal + functionality belum ada |
| 14 | **Profil** | Halaman profil + change password belum ada |

### Modul dengan GAP RENDAH (opsional/sedang berjalan):

| # | Modul | Yang Kurang |
|---|-------|-------------|
| 15 | **Customer Portal** | 8 halaman customer belum ada |
| 16 | **Admin/Spectator Gudang** | 4 halaman manajemen akses gudang belum ada |
| 17 | **Email Templates** | 6 template email belum di-migrasi |
| 18 | **PDF Invoices** | 4 file PDF invoice |
| 19 | **Penjualan View** | Lampiran preview, mobile responsive, missing fields |
| 20 | **Notifications** | Notification bell dengan realtime list |

---

## 24. FILE COUNT SUMMARY

| Kategori | Laravel 7 (Views) | Laravel 13 (Filament) | Gap |
|----------|-------------------|----------------------|-----|
| Layout/Partial | 3 | 0 | -3 |
| Dashboard | 1 | 0 | -1 |
| Auth | 4+ | (Filament built-in) | 0 |
| Penjualan | 6 | 4 (View done) | -2 |
| Pembelian | 6 | 3 (no View) | -3 |
| Biaya | 6 | 3 (no View) | -3 |
| Kunjungan | 5 | 3 (no View) | -2 |
| Pembayaran | 5 | 3 (no View) | -2 |
| Penerimaan Barang | 4 | 3 (no View) | -1 |
| Produk | 5 | 3 (no View) | -2 |
| Kontak | 5 | 3 (no View) | -2 |
| Stok | 2 | 0 | -2 |
| Users | 3 | 3 | 0 |
| Gudang | 3 | 3 | 0 |
| Profil | 1 | 0 | -1 |
| Reports | 9 | 0 | -9 |
| Public Invoices | 12 | 0 | -12 |
| Print/Struk | 14 | 0 | -14 |
| PDF Invoices | 4 | 0 | -4 |
| Email Templates | 6 | 0 | -6 |
| Customer Portal | 8 | 0 | -8 |
| Admin Gudang | 2 | 0 | -2 |
| Spectator Gudang | 2 | 0 | -2 |
| Other (errors, api-docs, dll) | 4 | 0 | -4 |
| **TOTAL** | **~110 files** | **~30 files (3 resource files x 10)** | **~80 file gap** |

---

## 25. REKOMENDASI PRIORITAS

### Prioritas 1 (Fundamental — harus sebelum deployment):
1. Buat **ViewRecord untuk** Pembelian, Biaya, Kunjungan, Pembayaran, PenerimaanBarang, Produk, Kontak (7 resource)
2. Implementasi **actions** di setiap ViewRecord: Approve, Cancel, Uncancel, QR Code
3. Buat **Dashboard** halaman dengan widget charts
4. Buat **Print/Struk thermal** untuk semua modul transaksi

### Prioritas 2 (Operasional):
5. Implementasi **Export Report** (Excel + PDF)
6. Public invoice pages (customer-facing)
7. **Stok management** resource (read stok + log)
8. **Profil** page (edit profil + change password)

### Prioritas 3 (Nice to have):
9. Customer portal
10. Notification bell widget
11. Email templates
12. Admin/Spectator gudang management
13. API docs
