# Current Parity Audit Laravel 7 vs Laravel 13

Tanggal audit: 2026-06-06

Repo lama: `sales_hibiscusefsya`

Repo baru: `sales_hibiscusefsya_laravel13`

Scope audit:
- API mobile `/api/v1`
- Controller dan route web/API
- Model Eloquent dan helper bisnis
- Filament resources, pages, actions, dan gap terhadap Blade lama
- Tombol dan aksi detail page lama, terutama `show.blade.php`

Dokumen ini adalah audit awal sebelum melengkapi tampilan. Ini bukan tanda bahwa parity sudah selesai.

## Ringkasan Status

| Area | Status | Catatan utama |
| --- | --- | --- |
| API route surface | Hampir sama | Jumlah definisi route API lama dan baru sama-sama 85. Route Laravel 13 berhasil terdaftar. |
| API implementation | Belum parity penuh | Beberapa endpoint masih placeholder/TODO atau belum terbukti response JSON-nya sama. |
| Controller web lama | Belum parity | Banyak controller web Laravel 7 belum ada di Laravel 13 karena sebagian diganti Filament, tetapi fitur publik/print/portal/export belum terganti penuh. |
| Model class | Nama model inti sudah ada | Semua model inti ada, tetapi beberapa helper lama belum dipertahankan. |
| Filament resources | CRUD dasar ada | Ada 10 resource utama, tetapi custom detail page/action bisnis baru lengkap sebagian di Penjualan. |
| Detail/show page | Belum parity | Hanya Penjualan punya custom view page. Show page lama untuk Pembelian, Biaya, Kunjungan, Pembayaran, Penerimaan Barang, Produk, dan Kontak belum setara. |
| Public invoice/struk | Belum parity | Route publik sudah ada sebagian, tetapi masih placeholder JSON. |
| Test coverage | Belum cukup | Ada test kontrak beberapa endpoint, tetapi belum mencakup semua modul dan action detail. |

Kesimpulan: fondasi Laravel 13 sudah masuk arah yang benar, tetapi belum siap disebut sama persis dengan aplikasi Laravel 7. Yang paling kritis bukan lagi route API, melainkan implementasi response/action parity dan detail page Filament.

## Bukti Validasi

Yang sudah diverifikasi:
- `php artisan route:list` di Laravel 13 berhasil jalan dan mendaftarkan 146 route.
- Definisi route API lama dan baru sama-sama 85.
- Route `/api/v1` utama sudah terdaftar di Laravel 13.
- PHP di repo Laravel 13 terbaca `8.5.7`, sehingga requirement PHP Laravel 13 di `composer.json` terpenuhi.
- Filament panel `/app` sudah terdaftar dengan resource utama.
- `php artisan test --filter=PanelBootTest` pernah dijalankan, tetapi timeout sekitar 64 detik. Jadi test tersebut belum boleh dianggap passing.

Catatan validasi:
- `php artisan route:list --compact` tidak valid di Laravel 13 karena opsi `--compact` tidak tersedia.
- Validasi ini belum membandingkan response JSON lama vs baru secara otomatis.
- Validasi ini belum smoke test browser untuk semua halaman Filament.

## API Parity

### Route Surface

Status: cukup baik.

Evidence:
- Route API lama: 85 definisi.
- Route API baru: 85 definisi.
- Route Laravel 13 berhasil tampil lewat `php artisan route:list`.

Catatan:
- Jumlah route yang sama tidak otomatis berarti behavior sama.
- Masih perlu contract test yang membandingkan request payload, response JSON, status code, dan auth behavior.

### Controller API

| Modul | Status | Gap penting |
| --- | --- | --- |
| Auth | Sebagian parity | Endpoint utama ada. Helper lama seperti `formatUser` dan `compressImage` tidak terlihat sebagai method publik di controller baru. Pastikan response user/profile/avatar tetap sama. |
| User | Sebagian parity | CRUD API ada, tetapi perlu cek permission role dan response shape. |
| Gudang | Sebagian parity | `exportStok` masih TODO/501. |
| Produk | Sebagian parity | CRUD dan stok by gudang ada, tetapi perlu cek response stok dan akses gudang. |
| Kontak | Sebagian parity | CRUD ada, helper akses ada, perlu cek PIN/customer portal compatibility. |
| Penjualan | Paling maju | Endpoint action utama ada. Helper lama berubah nama seperti `buildPenjualanItemRows` menjadi `buildItemRows`; perlu contract test agar hasil tetap sama. |
| Pembelian | Sebagian parity | Endpoint action ada, perlu cek approve/cancel dan item rows. |
| Biaya | Sebagian parity | Endpoint action ada, perlu cek lampiran, approve/cancel, dan export/print parity. |
| Kunjungan | Sebagian parity | Endpoint action ada, tetapi helper lama `deriveTipeStokByTujuan` tidak terlihat. Perlu pastikan aturan stok kunjungan sama. |
| Pembayaran | Belum parity penuh | `exportHarianPdf` masih TODO/501. Helper lama `applyPenjualanExportAccess` dan `withTagihanInfo` tidak terlihat sebagai method controller baru. |
| Penerimaan Barang | Sebagian parity | Endpoint action ada, method stok tambah/kurang ada. Perlu test stok real saat approve/cancel. |
| Stok | Sebagian parity | Endpoint dasar ada. Perlu cek stok log, filter gudang, dan role. |
| Dashboard/Report | Belum parity | `dailyReportPdf` dan `export` masih TODO/501. |
| Print | Belum parity | `qrData` dan `bluetoothData` ada, tetapi ada TODO untuk Bluetooth JSON per tipe transaksi. |

### Endpoint Yang Perlu Diprioritaskan Untuk Contract Test

Minimal sebelum klaim API mobile kompatibel:
- `POST /api/v1/login`
- `GET /api/v1/profile`
- `GET /api/v1/gudang`
- `GET /api/v1/produk`
- `GET /api/v1/kontak`
- `GET /api/v1/penjualan`
- `POST /api/v1/penjualan`
- `GET /api/v1/penjualan/{id}`
- approve/cancel/uncancel penjualan
- mark paid/unmark paid penjualan
- pembelian create/detail/approve/cancel
- biaya create/detail/approve/cancel
- kunjungan create/detail/approve/cancel
- pembayaran create/detail/approve/cancel
- penerimaan barang create/detail/approve/cancel
- stok list/log
- dashboard daily report
- QR data
- Bluetooth print data

## Web Route dan Controller Parity

Controller web Laravel 7 yang belum muncul sebagai controller web Laravel 13:
- `AdminGudangController`
- `ApiDocController`
- `BiayaController`
- `CustomerPortalController`
- `DashboardController`
- `GudangController`
- `HomeController`
- `KontakController`
- `KunjunganController`
- `PembayaranController`
- `PembelianController`
- `PenerimaanBarangController`
- `PenjualanController`
- `PrintController`
- `PrintImageController`
- `ProdukController`
- `ProfileController`
- `PublicInvoiceController`
- `PublicReceiptController`
- `SpectatorGudangController`
- `StokController`
- `UserController`

Controller web Laravel 13 yang ada:
- `BluetoothPrintController`
- `Controller`

Interpretasi:
- Ini tidak selalu salah karena Filament memang menggantikan banyak controller CRUD lama.
- Tetapi fitur non-CRUD lama belum otomatis tergantikan hanya dengan Filament resource.
- Public invoice, public struk, API docs, customer portal, print image, export, profile custom, dan stok custom page masih perlu implementasi eksplisit.

### Route Web Yang Masih Placeholder atau Hilang

| Fitur lama | Status Laravel 13 | Catatan |
| --- | --- | --- |
| Public invoice page | Placeholder | Route ada sebagian, tetapi return JSON placeholder. |
| Public invoice download | Belum parity | Route download lama belum terlihat setara. |
| Public struk/receipt | Placeholder | Route ada, tetapi return JSON placeholder. |
| Customer portal | Belum ada | Route `/customer/*` lama belum terganti. |
| API docs | Belum ada | Route docs/download lama belum terganti. |
| Transaction print web | Belum parity | Route print lama seperti `penjualan.print`, `pembelian.print`, dan lainnya belum setara. |
| Print JSON/rich/image | Belum parity | Print controller/image controller lama belum terganti penuh. |
| Delete lampiran web | Belum parity | Aksi delete lampiran di show page lama belum terlihat di Filament. |
| Stok page/log/export | Belum parity | Belum ada custom Filament page stok/stok log/export. |
| Dashboard/report export | Belum parity | Belum ada widget/report/export setara. |
| Profile custom | Belum parity | Baru ada Filament profile bawaan, belum tentu sama dengan profile lama. |
| User export/email toggles | Belum parity | Patch route lama untuk permission user belum terlihat. |
| Product/Kontak print/download | Belum parity | Route download/print lama belum terganti. |

## Model Parity

Model inti Laravel 13 sudah tersedia:
- `Biaya`
- `BiayaItem`
- `Gudang`
- `GudangProduk`
- `Kontak`
- `Kunjungan`
- `KunjunganItem`
- `Pembayaran`
- `Pembelian`
- `PembelianItem`
- `PenerimaanBarang`
- `PenerimaanBarangItem`
- `Penjualan`
- `PenjualanItem`
- `PersonalAccessToken`
- `Produk`
- `StokLog`
- `User`

Gap model yang perlu dicek atau dipulihkan:

| Model | Gap |
| --- | --- |
| `Biaya` | Helper lama `getCustomNumberAttribute` dan `generateNomor` belum terlihat seperti di model lama. |
| `Kunjungan` | Helper lama `getCustomNumberAttribute`, `generateNomor`, dan `getTujuanBadgeAttribute` perlu dipastikan ada/terganti. |
| `Pembayaran` | Helper lama `getCustomNumberAttribute` dan `generateNomor` perlu dipastikan parity. |
| `Pembelian` | Helper lama `getCustomNumberAttribute`, `generateNomor`, dan status display perlu dipastikan parity. |
| `PenerimaanBarang` | Helper lama `getCustomNumberAttribute` dan `generateNomor` perlu dipastikan parity. |
| `Produk` | Relasi lama `gudangProduks` dan `kunjunganItems` tidak terlihat lengkap seperti lama. |
| `Gudang` | Nama relasi lama `produkStok` perlu dipertahankan atau diberi alias jika dipakai oleh kode/response lama. |
| `User` | Helper role/access sudah ada sebagian, tetapi harus dites dengan skenario admin, admin_gudang, sales, spectator, dan customer. |

Risiko:
- Jika atribut seperti `custom_number`, `status_display`, atau relasi lama dipakai di API/mobile, response JSON bisa berubah walaupun endpoint tetap ada.
- Jika generator nomor transaksi berbeda, data baru Laravel 13 bisa tidak konsisten dengan pola nomor lama.

## Filament Resource Parity

Resource Laravel 13 yang ada:
- `Users`
- `Gudangs`
- `Produks`
- `Kontaks`
- `Penjualans`
- `Pembelians`
- `Biayas`
- `Kunjungans`
- `Pembayarans`
- `PenerimaanBarangs`

Status route Filament:
- Index/create/edit sudah ada untuk resource utama.
- Hanya `Penjualan` yang punya custom view route `/app/penjualans/{record}`.
- Resource lain belum punya route view/show setara Blade lama.

Custom pages/widgets:
- Belum ditemukan custom page di `app/Filament/Pages`.
- Belum ditemukan custom widget di `app/Filament/Widgets`.

Gap penting:
- Dashboard lama belum terganti dengan widget/report yang setara.
- Stok dan stok log belum ada sebagai custom page.
- Action bisnis lintas transaksi belum dibuat reusable.
- Resource detail selain Penjualan belum memuat komponen show lama.

## Audit Tombol Show Blade Lama vs Filament Baru

### Penjualan

Legacy `resources/views/penjualan/show.blade.php` memuat:
- Approve
- Mark Paid
- Cancel
- Uncancel
- Bluetooth print
- Cetak Struk
- QR Code public invoice
- Back to index
- Google Maps link
- Delete lampiran
- Tabel item produk
- Memo/catatan
- Lampiran
- Informasi status, user, gudang, customer, dan pembayaran

Status Laravel 13:
- Sudah ada `ViewPenjualan` custom page.
- Sudah ada action approve, mark paid, unmark paid, cancel, uncancel, bluetooth print, QR code, edit, delete.
- Belum terlihat action `Cetak Struk` web print.
- QR code masih bergantung pada public invoice route yang saat ini placeholder.
- Back action bisa mengandalkan navigation Filament, tetapi belum sama secara eksplisit dengan tombol lama.
- Delete lampiran belum terlihat.
- Perlu cek apakah semua section detail lama sudah muncul di infolist.

Status: partial parity.

### Pembelian

Legacy `resources/views/pembelian/show.blade.php` memuat:
- Approve
- Cancel
- Bluetooth print
- Cetak Struk
- QR Code public invoice
- Back to index
- Google Maps link
- Delete lampiran
- Tabel item pembelian
- Memo, lampiran, status, user, gudang, approver

Status Laravel 13:
- Resource Pembelian punya index/create/edit.
- Table punya view action, tetapi route view custom belum terdaftar.
- Belum ada custom show page/action bisnis setara.

Status: belum parity.

### Biaya

Legacy `resources/views/biaya/show.blade.php` memuat:
- Approve
- Cancel
- Bluetooth print
- Cetak Struk
- QR Code public invoice
- Back to index
- Google Maps link
- Delete lampiran
- Tabel item biaya
- Memo, lampiran, status, user, gudang, approver

Status Laravel 13:
- Resource Biaya punya index/create/edit.
- Belum ada custom show page/action bisnis setara.

Status: belum parity.

### Kunjungan

Legacy `resources/views/kunjungan/show.blade.php` memuat:
- Approve
- Cancel
- Bluetooth print
- Cetak Struk
- QR Code public invoice
- Back to index
- Google Maps link
- Delete lampiran
- Tabel produk/item kunjungan
- Tujuan kunjungan, kontak, memo, lampiran, status, user, gudang, approver

Status Laravel 13:
- Resource Kunjungan punya index/create/edit.
- Belum ada custom show page/action bisnis setara.
- Aturan tipe stok berdasarkan tujuan harus dipastikan sama dengan kode lama.

Status: belum parity.

### Pembayaran

Legacy `resources/views/pembayaran/show.blade.php` memuat:
- Print
- QR invoice
- Back
- Approve
- Cancel
- Uncancel
- Delete
- Delete lampiran
- Detail penjualan terkait
- Informasi pembayaran, user, gudang, approver, lampiran

Status Laravel 13:
- Resource Pembayaran punya index/create/edit.
- Belum ada custom show page/action bisnis setara.
- Export harian PDF di API masih TODO/501.

Status: belum parity.

### Penerimaan Barang

Legacy `resources/views/penerimaan-barang/show.blade.php` memuat:
- Print
- QR invoice
- Back
- Approve dan tambah stok
- Cancel dan reverse stok
- Uncancel
- Delete
- Delete lampiran
- Pembelian terkait
- Tabel item penerimaan
- Informasi status, user, gudang, approver

Status Laravel 13:
- Resource Penerimaan Barang punya index/create/edit.
- Belum ada custom show page/action bisnis setara.
- Method stok tambah/kurang ada di controller API, tetapi flow Filament detail belum setara.

Status: belum parity.

### Produk

Legacy `resources/views/produk/show.blade.php` memuat:
- Edit
- Back
- Download PDF
- Print
- QR Code
- Barcode
- Stok per gudang
- Informasi produk

Status Laravel 13:
- Resource Produk punya index/create/edit.
- Belum ada custom show page setara.
- Product print/download/QR/barcode belum terlihat setara di Filament.

Status: belum parity.

### Kontak

Legacy `resources/views/kontak/show.blade.php` memuat:
- Edit
- Back
- PIN toggle/customer access
- Download PDF
- Print
- QR Code
- Barcode
- Riwayat penjualan
- Informasi kontak/customer

Status Laravel 13:
- Resource Kontak punya index/create/edit.
- Belum ada custom show page setara.
- Customer portal dan PIN flow belum terlihat setara.

Status: belum parity.

## Gap UI dan Komponen Yang Harus Dibuat

| Area | Yang harus ada | Status sekarang |
| --- | --- | --- |
| Dashboard | Ringkasan transaksi, piutang, stok, grafik, filter gudang/tanggal, report/export | Belum setara, belum ada widget custom. |
| Stok | Page stok, filter gudang/produk, penyesuaian stok, stok log, export | Belum ada custom page. |
| Transaction show pages | Detail lengkap dan action approve/cancel/uncancel/print/QR/Bluetooth/lampiran/maps | Baru Penjualan partial. |
| Public invoice | Public page HTML, download, QR target valid | Masih placeholder. |
| Public struk | Public receipt page/printable | Masih placeholder. |
| Bluetooth print | JSON lengkap per transaksi dan UI action | Baru terlihat di Penjualan, API print masih TODO. |
| Thermal/web print | Print route/view untuk semua transaksi | Belum setara. |
| Lampiran | Tampil, download, hapus aman | Belum terlihat setara di Filament show. |
| Maps/geolocation | Link maps dan capture lokasi seperti lama | Baru terlihat sebagian, perlu audit form/detail. |
| QR/barcode | QR invoice dan barcode produk/kontak | Belum setara selain QR modal Penjualan. |
| Customer portal | Login/customer access, invoice/history | Belum ada. |
| API docs | Docs page dan JSON/download | Belum ada. |
| Profile | Edit profile/password/avatar sesuai lama | Belum tentu setara dengan Filament built-in profile. |
| User permissions | Toggle email/export/admin gudang/spectator access | Belum terbukti setara. |

## Test Coverage Gap

Test yang sudah terlihat ada:
- Auth API contract
- Gudang API contract
- Kontak API contract
- Penjualan API contract
- Produk API contract
- Stok API contract
- Panel boot/basic access test

Test yang masih perlu dibuat sebelum parity claim:
- Biaya API contract
- Pembelian API contract
- Kunjungan API contract
- Pembayaran API contract
- Penerimaan Barang API contract
- Dashboard/export/report API contract
- QR/Bluetooth print API contract
- User CRUD dan permission role contract
- Public invoice/struk route test
- Filament action test untuk approve/cancel/uncancel/mark paid/print/QR/lampiran
- Stock mutation test untuk penjualan, pembelian, kunjungan, penerimaan barang
- Role permission test per halaman dan per action

## Prioritas Berikutnya

### P0: Wajib Sebelum Polish Tampilan

1. Selesaikan public invoice dan public struk asli.
2. Buat custom view/show Filament untuk:
   - Penjualan
   - Pembelian
   - Biaya
   - Kunjungan
   - Pembayaran
   - Penerimaan Barang
   - Produk
   - Kontak
3. Pindahkan semua action lama dari `show.blade.php` ke Filament action:
   - approve
   - cancel
   - uncancel
   - mark paid/unmark paid khusus Penjualan
   - print
   - QR code
   - Bluetooth print
   - delete lampiran
   - maps
4. Hilangkan TODO/501 pada endpoint export dan print yang masih dipakai.
5. Tambahkan contract test untuk response API mobile utama.

### P1: Stabilitas Business Rule

1. Samakan helper nomor transaksi dan status display di semua model transaksi.
2. Pastikan stok bertambah/berkurang sama persis dengan Laravel 7.
3. Pastikan role admin, admin_gudang, sales, spectator, dan customer sama.
4. Pastikan filter gudang/access scope sama di API dan Filament.
5. Buat reusable Filament action/service untuk action transaksi agar behavior tidak beda antar modul.

### P2: Kelengkapan Admin dan Portal

1. Buat Dashboard widgets dan report/export page.
2. Buat Stok dan Stok Log custom pages.
3. Buat Customer Portal.
4. Buat API Docs.
5. Lengkapi Product/Kontak PDF, print, QR, barcode.
6. Lengkapi profile/avatar/password flow.

### P3: Polish Tampilan

Baru dilakukan setelah P0 dan P1 aman:
- Rapikan layout infolist detail.
- Samakan urutan section dengan Blade lama.
- Rapikan warna status badge.
- Rapikan table columns, filters, summaries, dan empty state.
- Optimalkan UX action modal approve/cancel/uncancel.

## Definition of Done Untuk Parity

Laravel 13 baru boleh dianggap parity dengan Laravel 7 jika:
- Semua endpoint `/api/v1` lama tersedia.
- Response JSON endpoint mobile cocok atau beda secara terdokumentasi.
- Semua action bisnis lama tersedia di API dan Filament.
- Semua show/detail page lama punya pengganti Filament yang memuat informasi dan tombol setara.
- Public invoice, public struk, QR, barcode, print, dan Bluetooth print berjalan.
- Customer portal dan API docs tersedia jika masih dipakai.
- Database lama bisa dibaca tanpa migration destruktif.
- Flow transaksi utama sudah dites:
  - create
  - update
  - approve
  - cancel
  - uncancel
  - list
  - detail
  - permission role
  - stok mutation
- Test utama tidak timeout dan bisa dijalankan ulang.

## Rekomendasi Eksekusi Setelah Audit Ini

Urutan kerja yang paling aman:

1. Buat shared transaction action/service untuk approve, cancel, uncancel, print, QR, Bluetooth, dan lampiran.
2. Port custom view page per transaksi mulai dari Pembelian, Biaya, Kunjungan, Pembayaran, dan Penerimaan Barang.
3. Perbaiki public invoice/struk karena QR dan print bergantung ke situ.
4. Lengkapi TODO export/print API.
5. Buat contract test per modul.
6. Baru lakukan polish visual seluruh Filament.

Alasan:
- Kalau tampilan dipoles dulu, masih ada risiko tombol terlihat siap tetapi route/action belum benar.
- Kalau action/service dan public routes diselesaikan dulu, UI Filament tinggal memasang action yang sama di semua detail page.
