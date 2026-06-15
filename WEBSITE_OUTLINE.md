# Website Outline — Hibiscusefsya POS

> **Sumber:** `Website_Outline.xlsx` → Sheet: `OUTLINE REV 1`
> **Catatan Global:** *Setiap transaksi yang dibuat hanya dapat dilihat oleh user yang membuat dan admin.*

---

## Struktur Permission

Kolom permission disusun sebagai berikut (20 kolom, 5 per role):

| # | Kolom | Role |
|---|-------|------|
| 0 | SALES → ADD | Sales |
| 1 | SALES → VIEW | Sales |
| 2 | SALES → APRV | Sales |
| 3 | SALES → EDIT | Sales |
| 4 | SALES → DEL | Sales |
| 5 | ADMIN → ADD | Admin |
| 6 | ADMIN → VIEW | Admin |
| 7 | ADMIN → APRV | Admin |
| 8 | ADMIN → EDIT | Admin |
| 9 | ADMIN → DEL | Admin |
| 10 | SPECTATOR → ADD | Spectator |
| 11 | SPECTATOR → VIEW | Spectator |
| 12 | SPECTATOR → APRV | Spectator |
| 13 | SPECTATOR → EDIT | Spectator |
| 14 | SPECTATOR → DEL | Spectator |
| 15 | SUPER ADMIN → ADD | Super Admin |
| 16 | SUPER ADMIN → VIEW | Super Admin |
| 17 | SUPER ADMIN → APRV | Super Admin |
| 18 | SUPER ADMIN → EDIT | Super Admin |
| 19 | SUPER ADMIN → DEL | Super Admin |

---

## 1. NERACA — DASHBOARD

**Status:** `NEW`

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| OMSET PERGUDANG | Export Excel & PDF (rentang waktu) | - | - | VIEW | VIEW |
| NILAI PEMBELIAN GUDANG | - | - | - | VIEW | VIEW |
| NILAI PENJUALAN GUDANG RETAIL | - | - | - | VIEW | VIEW |
| NILAI PENJULAN GUDANG GROSIR | - | - | - | VIEW | VIEW |
| JUMLAH PRODUK TERJUAL RETAIL | - | - | - | VIEW | VIEW |
| JUMLAH PRODUK TERJUAL GROSIR | - | - | - | - | - |
| PEMBAYARAN LUNAS PERGUDANG | - | - | - | - | - |
| PEMBAYARAN BELUM LUNAS PERGUDANG | - | - | - | VIEW | VIEW |

> Hanya Spectator dan Super Admin yang punya akses view ke Neraca.

---

## 2. KUNJUNGAN — BUAT KUNJUNGAN

### 2.1 Informasi Kunjungan

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| NAMA PELANGGAN | List Created Contact by User | ADD | VIEW+EDIT | - | ALL |
| TANGGAL KUNJUNGAN | choose from list | ADD | VIEW+EDIT | - | ALL |
| TUJUAN KUNJUNGAN | choose from list | ADD | VIEW+EDIT | - | ALL |
| KOORDINAT KUNJUNGAN | autofill based GPS | VIEW | VIEW+APRV | - | ALL |

### 2.2 Hasil Kunjungan

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| NAMA PRODUK | choose from list | VIEW | VIEW+APRV | - | ALL |
| JUMLAH PRODUK | entry | ADD | VIEW+EDIT | - | ALL |
| BATCH AND EXP | entry | ADD | VIEW+EDIT | - | ALL |

### 2.3 Catatan dan Lampiran

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| MEMO | entry | ADD | VIEW+EDIT | - | ALL |
| LAMPIRAN FOTO | entry | ADD | VIEW+EDIT | - | ALL |

---

## 3. PENJUALAN — BUAT PENJUALAN

### 3.1 Informasi Pelanggan

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| NAMA PELANGGAN | List Created Contact by User | ADD | VIEW+EDIT | - | ALL |
| NOMOR TELEPON PELANGGAN | List Created Contact by User | ADD | VIEW+EDIT | - | ALL |
| ALAMAT PELANGGAN | List Created Contact by User | ADD | VIEW+EDIT | - | ALL |

### 3.2 Detail Transaksi

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| NOMOR TRANSAKSI | autofill based date | VIEW | VIEW+APRV | - | ALL |
| TANGGAL TRANSAKSI | choose from list | ADD | VIEW+EDIT | - | ALL |
| JATUH TEMPO | choose from list | ADD | VIEW+EDIT | - | ALL |
| TIPE HARGA | choose from list | ADD | VIEW+EDIT | - | ALL |
| NO REFERENSI | entry | ADD | VIEW+EDIT+APRV+DEL | - | ALL |
| KOORDINAT | autofill based GPS | VIEW | VIEW+APRV | - | ALL |

### 3.3 Item Penjualan

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| NAMA PRODUK | choose from list | ADD | VIEW+EDIT | - | ALL |
| QTY PRODUK | entry | ADD | VIEW+EDIT | - | ALL |
| UNIT | auto fill dari produk | VIEW | VIEW+APRV | - | ALL |
| HARGA | auto fill dari produk | VIEW | VIEW+APRV | - | ALL |
| DISCONT PERCENT | entry | ADD | VIEW+EDIT | - | ALL |
| DISCONT RP | entry | ADD | VIEW+EDIT | - | ALL |
| BATCH AND EXP | entry | ADD | VIEW+EDIT | - | ALL |
| DESKRIPSI | entry | ADD | VIEW+EDIT | - | ALL |
| JUMLAH SUB | Auto Calculated | VIEW | VIEW+APRV | - | ALL |

### 3.4 Total dan Pajak

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| DISKON AKHIR | entry | ADD | VIEW+EDIT | - | ALL |
| PAJAK | entry | ADD | VIEW+EDIT | - | ALL |
| BIAYA PENGIRIMAN | entry | ADD | VIEW+EDIT | - | ALL |

### 3.5 Catatan dan Lampiran

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| TAG SALES | Autofill based Account | VIEW | VIEW+APRV | - | ALL |
| MEMO | entry | ADD | VIEW+EDIT | - | ALL |
| LAMPIRAN FOTO | entry | ADD | VIEW+EDIT | - | ALL |

---

## 4. BIAYA — BUAT BIAYA

### 4.1 Informasi Biaya

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| JENIS BIAYA | choose from list | VIEW | VIEW+APRV | - | ALL |
| GUDANG | choose from list | VIEW | VIEW+APRV | - | ALL |
| TAG SALES | Autofill | VIEW | VIEW+APRV | - | ALL |
| BAYAR DARI | choose from list | VIEW | VIEW+APRV | - | ALL |
| PENERIMA | List Created Contact by User | VIEW | VIEW+APRV | - | ALL |
| NOMOR TELEPON | List Created Contact by User | VIEW | VIEW+APRV | - | ALL |
| TANGGAL TRANSAKSI | choose from list | VIEW | VIEW+APRV | - | ALL |
| CARA PEMBAYARAN | choose from list | VIEW | VIEW+APRV | - | ALL |
| ALAMAT PENAGIHAN | Autofill | VIEW | VIEW+APRV | - | ALL |

### 4.2 Item

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| KATEGORI | choose from list | VIEW | VIEW+APRV | - | ALL |
| DESKERIPSI | entry | ADD | VIEW+APRV | - | ALL |
| JUMLAH | entry | ADD | VIEW+APRV | - | ALL |

### 4.3 Total dan Pajak

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| DISKON AKHIR | entry | ADD | - | - | ALL |
| PAJAK | entry | ADD | VIEW+EDIT | - | ALL |

### 4.4 Catatan dan Lampiran

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| MEMO | entry | ADD | VIEW+EDIT | - | ALL |
| LAMPIRAN FOTO | entry | ADD | VIEW+EDIT | - | ALL |

---

## 5. PIUTANG

### 5.1 Dashboard Piutang `NEW`

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| Graph Total Tempo Monthly + Export Daily (PDF) | Export Button (Created By User and Admin Only) | VIEW | VIEW | VIEW | ALL |
| List Toko Tempo Belum dan Sudah Terbayar + Export Button (Created By User and Admin Only) | Export | - | VIEW | VIEW | ALL |

### 5.2 Buat Penjualan (Piutang)

#### 5.2.1 Informasi Pelanggan

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| NAMA PELANGGAN | List Created Contact by User | ADD | VIEW+EDIT | - | ALL |
| NOMOR TELEPON PELANGGAN | List Created Contact by User | ADD | VIEW+EDIT | - | ALL |
| ALAMAT PELANGGAN | List Created Contact by User | ADD | VIEW+EDIT | - | ALL |

#### 5.2.2 Detail Transaksi

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| NOMOR TRANSAKSI | autofill based date | VIEW | VIEW+APRV | - | ALL |
| TANGGAL TRANSAKSI | choose from list | ADD | VIEW+EDIT | - | ALL |
| JATUH TEMPO | choose from list | ADD | VIEW+EDIT | - | ALL |
| TIPE HARGA | choose from list | ADD | VIEW+EDIT | - | ALL |
| NO REFERENSI | entry | ADD | VIEW+EDIT+APRV+DEL | - | ALL |
| KOORDINAT | autofill based GPS | VIEW | VIEW+APRV | - | ALL |

#### 5.2.3 Item Penjualan

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| NAMA PRODUK | choose from list | ADD | VIEW+EDIT | - | ALL |
| QTY PRODUK | entry | ADD | VIEW+EDIT | - | ALL |
| UNIT | auto fill dari produk | VIEW | VIEW+APRV | - | ALL |
| HARGA | auto fill dari produk | VIEW | VIEW+APRV | - | ALL |
| DISCONT PERCENT | entry | ADD | VIEW+EDIT | - | ALL |
| DISCONT RP | entry | ADD | VIEW+EDIT | - | ALL |
| BATCH AND EXP | entry | ADD | VIEW+EDIT | - | ALL |
| DESKRIPSI | entry | ADD | VIEW+EDIT | - | ALL |
| JUMLAH SUB | Auto Calculated | VIEW | VIEW+APRV | - | ALL |

#### 5.2.4 Total dan Pajak

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| DISKON AKHIR | entry | ADD | VIEW+EDIT | - | ALL |
| PAJAK | entry | ADD | VIEW+EDIT | - | ALL |
| **NO RESI** | entry | - | - | VIEW | ALL |
| BIAYA PENGIRIMAN | entry | ADD | VIEW+EDIT | - | ALL |

#### 5.2.5 Catatan dan Lampiran

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| TAG SALES | Autofill based Account | VIEW | VIEW+APRV | - | ALL |
| MEMO | entry | ADD | VIEW+EDIT | - | ALL |
| LAMPIRAN FOTO | entry | ADD | VIEW+EDIT | - | ALL |

### 5.3 Pembayaran Piutang

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| NO TRANSAKSI | Autofill | - | - | VIEW | ALL |
| GUDANG | choose from list | - | - | VIEW | ALL |
| INVOICE PENJUALAN | choose from list | - | - | VIEW | ALL |
| METODE PEMBAYARAN | choose from list | - | - | VIEW | ALL |

> Hanya Spectator dan Super Admin yang punya akses ke Pembayaran Piutang.

---

## 6. HUTANG `NEW`

### 6.1 Dashboard Hutang `NEW`

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| Graph Total Pembelian Monthly + Export Daily (PDF) | Export Button (Created By User and Admin Only) | - | - | VIEW | ALL |
| List Tempo Hutang Belum/Sudah Terbayar + Export Button (Created By User and Admin Only) | Export | - | VIEW | VIEW | ALL |

### 6.2 Buat Pembelian `NEW`

#### 6.2.1 Informasi Penjual

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| NAMA PELANGGAN | List Created Contact by User | ADD | VIEW+EDIT | - | ALL |
| NOMOR TELEPON PELANGGAN | List Created Contact by User | ADD | VIEW+EDIT | - | ALL |
| ALAMAT PELANGGAN | List Created Contact by User | ADD | VIEW+EDIT | - | ALL |

#### 6.2.2 Detail Transaksi

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| NOMOR TRANSAKSI | autofill based date | VIEW | VIEW+APRV | - | ALL |
| TANGGAL TRANSAKSI | choose from list | ADD | VIEW+EDIT | - | ALL |
| JATUH TEMPO | choose from list | ADD | VIEW+EDIT | - | ALL |
| TIPE HARGA | choose from list | ADD | VIEW+EDIT | - | ALL |
| NO REFERENSI | entry | ADD | VIEW+EDIT+APRV+DEL | - | ALL |
| KOORDINAT | autofill based GPS | VIEW | VIEW+APRV | - | ALL |

#### 6.2.3 Item Pembelian

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| NAMA PRODUK | choose from list | ADD | VIEW+EDIT | - | ALL |
| QTY PRODUK | entry | ADD | VIEW+EDIT | - | ALL |
| UNIT | auto fill dari produk | VIEW | VIEW+APRV | - | ALL |
| HARGA | **entry** (manual, bukan autofill) | VIEW | VIEW+APRV | - | ALL |
| DISCONT PERCENT | entry | ADD | VIEW+EDIT | - | ALL |
| DISCONT RP | entry | ADD | VIEW+EDIT | - | ALL |
| BATCH AND EXP | entry | ADD | VIEW+EDIT | - | ALL |
| DESKRIPSI | entry | ADD | VIEW+EDIT | - | ALL |
| JUMLAH SUB | Auto Calculated | VIEW | VIEW+APRV | - | ALL |

#### 6.2.4 Total dan Pajak

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| DISKON AKHIR | entry | ADD | VIEW+EDIT | - | ALL |
| PAJAK | entry | ADD | VIEW+EDIT | - | ALL |
| NOMOR RESI | entry | ADD | VIEW+EDIT | - | ALL |
| BIAYA PENGIRIMAN | entry | ADD | VIEW+EDIT | - | ALL |

#### 6.2.5 Catatan dan Lampiran

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| TAG SALES | Autofill based Account | VIEW | VIEW+APRV | - | ALL |
| MEMO | entry | ADD | VIEW+EDIT | - | ALL |
| LAMPIRAN FOTO | entry | ADD | VIEW+EDIT | - | ALL |

### 6.3 Pembayaran Hutang `NEW`

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| NO TRANSAKSI | Autofill | ADD+VIEW | ADD+VIEW | - | ALL |
| GUDANG | choose from list | ADD+VIEW | ADD+VIEW | - | ALL |
| INVOICE PEMBELIAN | choose from list | ADD+VIEW | ADD+VIEW | - | ALL |
| METODE PEMBAYARAN | choose from list | ADD+VIEW | ADD+VIEW | - | ALL |

### 6.4 Buat Penerimaan Barang

#### 6.4.1 Detail Penerimaan

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| NO TRANSAKSI | Auto | ADD | VIEW+APRV | - | ALL |
| GUDANG | Auto | ADD | VIEW+APRV | - | ALL |
| INVOICE PEMBELIAN | choose from list | ADD | VIEW+APRV | - | ALL |
| TANGGAL PENERIMAAN | choose from list | ADD | VIEW+APRV | - | ALL |
| NOMOR SURAT JALAN | Entry | ADD | VIEW+APRV | - | ALL |

#### 6.4.2 Item Penerimaan

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| ITEM PENERIMAAN | Import Excel (NO BATCH, TANGGAL, QTY) `NEW` | ADD | VIEW+APRV | - | ALL |

#### 6.4.3 Catatan dan Lampiran

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| TAG SALES | Autofill based Account | VIEW | ADD+VIEW+APRV | - | ALL |
| MEMO | entry | ADD | VIEW+EDIT | - | ALL |
| LAMPIRAN FOTO | entry | ADD | VIEW+EDIT | - | ALL |

---

## 7. GUDANG `NEW`

### 7.1 Dashboard — Stock Barang `NEW`

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| INFORMASI STOCK BARANG 7 HARI | Entry | VIEW | VIEW | - | ALL |

### 7.2 Stock Opname `NEW`

**Gambaran Before/After hasil check stock, selisih sebelum direplace**

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| Import Excel (NO BATCH, TANGGAL, QTY) | Entry | VIEW | VIEW | - | ALL |
| Import Excel (NO BATCH, TANGGAL, QTY) | Entry | VIEW | VIEW | - | ALL |

---

## 8. KONTAK `NEW`

### 8.1 Buat Kontak — List Kontak `NEW`

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| LIST KONTAK (hanya tampil yang dibuat oleh user) | Entry | ADD+VIEW | VIEW | - | ALL |

### 8.2 Pembaharuan `NEW`

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| NOMOR TELPON ONLY | Entry | ADD+VIEW | ADD+VIEW+APRV+EDIT+DEL | EDIT | ALL |

### 8.3 Catatan Hutang `NEW`

| Fitur | Fill Type | SALES | ADMIN | SPECTATOR | SUPER ADMIN |
|-------|-----------|-------|-------|-----------|-------------|
| NAMA KONTAK DAN HUTANGNYA (LIST) | Auto | VIEW | VIEW+APRV | - | ALL |

---

## Ringkasan Fitur NEW

| # | Modul | Sub-Fitur | Prioritas |
|---|-------|-----------|-----------|
| 1 | NERACA | Dashboard Neraca (8 card metrics) | Dashboard |
| 2 | PIUTANG | Dashboard Piutang (Graph + List Toko) | Dashboard |
| 3 | HUTANG | Dashboard Hutang (Graph + List Tempo) | Dashboard |
| 4 | HUTANG | Buat Pembelian (form lengkap + item) | Transaksi |
| 5 | HUTANG | Pembayaran Hutang | Transaksi |
| 6 | HUTANG | Buat Penerimaan Barang — Item Import Excel | Transaksi |
| 7 | GUDANG | Dashboard Stock Barang 7 Hari | Dashboard |
| 8 | GUDANG | Stock Opname (Before/After + Import Excel) | Inventory |
| 9 | KONTAK | List Kontak, Pembaharuan No Telp, Catatan Hutang | Master Data |

---

## Catatan Implementasi

1. **Visibility Rule:** Setiap transaksi hanya bisa dilihat oleh user yang membuat dan admin.
2. **Export Button:** Hanya bisa dibuat oleh User dan Admin (bukan Spectator).
3. **HARGA pada Pembelian** = manual entry (berbeda dengan Penjualan yang auto fill dari produk).
4. **Import Excel** digunakan di: Item Penerimaan Barang dan Stock Opname (format: NO BATCH, TANGGAL, QTY).
5. **Kontak List:** Hanya menampilkan kontak yang dibuat oleh user sendiri (scoping by user).
6. **TAG SALES:** Autofill berdasarkan account yang login.
7. **KOORDINAT:** Autofill berdasarkan GPS device/browser.
8. **NOMOR TRANSAKSI:** Autofill berdasarkan tanggal.
