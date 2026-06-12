# Feature Inventory Rebuild Laravel 13

Scope audit: repo lama `sales_hibiscusefsya` Laravel 7, dibaca statis dari route, middleware, model, migration, controller, view docs, dan composer. Tidak ada koneksi tulis ke database dan tidak ada perubahan pada repo lama.

Sumber utama:
- `routes/api.php`
- `routes/web.php`
- `app/Http/Kernel.php`
- `app/Http/Middleware/ApiTokenAuth.php`
- `app/Http/Middleware/CheckRole.php`
- `app/Http/Controllers/**`
- `app/Http/Controllers/Api/**`
- `app/*.php`
- `database/migrations/*.php`
- `composer.json`, `composer.lock`, `package.json`

Catatan validasi: `php artisan route:list` tidak dapat dipakai di mesin audit karena proyek Laravel 7 ini berjalan di PHP lokal 8.1.33 dan gagal pada kompatibilitas dependency lama. Mapping route memakai file route/controller sebagai sumber bukti.

## Ringkasan Sistem

Hibiscusefsya POS adalah aplikasi admin Blade dan API mobile untuk transaksi gudang/sales. Database lama tetap menjadi sumber data utama. Fitur bisnis inti berada pada modul gudang, produk, kontak, penjualan, pembelian, biaya, kunjungan, pembayaran, penerimaan barang, stok, report/export, invoice publik, dan print thermal/bluetooth.

Invarian kompatibilitas untuk rebuild:
- Prefix API mobile tetap `/api/v1`.
- Auth API tetap memakai bearer token plain text yang disimpan sebagai SHA-256 di tabel `personal_access_tokens`.
- Nama endpoint, method HTTP, request payload, response JSON, dan status code lama dipertahankan.
- Role string tetap `super_admin`, `admin`, `spectator`, `user`.
- Status transaksi lama dipertahankan sesuai controller: `Pending`, `Approved`, `Canceled`, `Lunas`; beberapa filter juga menyebut `Rejected`.
- Nomor dokumen lama dipertahankan: `INV`, `PR`, `EXP`, `VST`, `PAY`, `RCV` atau `GRN` pada sebagian PDF penerimaan.
- Business rule stok, approval, dan payment tidak diubah kecuali ada test yang membuktikan bug lama dan perubahan disetujui.

## Auth Web

Fakta:
- Web auth memakai `Auth::routes()` dari `laravel/ui` era Laravel 7.
- Guard web default adalah session guard `web`.
- Route profile tersedia untuk user login:
  - `GET /profil`
  - `PUT /profil`
  - `POST /profil/change-password`
  - `POST /profil/avatar`
  - `DELETE /profil/avatar`
- Middleware route web memakai `auth`, `role:admin`, `role:super_admin`, dan `customer.auth`.

Kompatibilitas rebuild:
- Login/logout/password reset web harus tetap kompatibel dengan URL lama selama Blade lama dipakai.
- Jika Laravel 13 tidak memakai `Auth::routes()` secara langsung, buat route manual atau gunakan scaffolding yang menghasilkan URL dan form behavior sama.
- Jangan ubah session key customer portal (`customer_id`, `customer_no_telp`, `customer_nama`) sebelum portal customer dites.

## Auth API Mobile

Fakta:
- `POST /api/v1/login` publik.
- Semua endpoint `/api/v1/*` lain memakai middleware `api.token`.
- Middleware membaca header `Authorization: Bearer <token>`.
- Token plain dikirim hanya saat login; database menyimpan hash SHA-256 di `personal_access_tokens.token`.
- Token expired setelah 30 hari.
- Middleware response:
  - Tanpa bearer token: `401 {"message":"Unauthenticated."}`
  - Token invalid/expired: `401 {"message":"Token invalid atau sudah expired."}`
- Login sukses mengembalikan `message`, `token`, dan object `user` berisi `id`, `name`, `email`, `role`, `alamat`, `no_telp`, `avatar_url`, `gudang_id`, `current_gudang_id`.

Kompatibilitas rebuild:
- Jangan mengganti ke Sanctum token format tanpa adapter compatibility.
- Tabel `personal_access_tokens` lama harus tetap dibaca.
- Response login/profile/logout/change-password/avatar harus disnapshot sebelum porting.

## User dan Role

Role aktif:
- `super_admin`: akses semua gudang, semua master, semua export, bisa uncancel.
- `admin`: akses gudang via pivot `admin_gudang`; `current_gudang_id` menentukan gudang aktif.
- `spectator`: akses gudang via pivot `spectator_gudang`; di web diperlakukan sebagai read-only pada beberapa modul, tetapi beberapa API perlu diuji karena tidak semua controller memblokir mutation.
- `user`: sales biasa, satu `gudang_id`, akses data sendiri.

Aturan penting:
- `CheckRole` memberi `super_admin` semua akses.
- `role:admin` juga meloloskan `spectator`.
- `User::canAccessGudang()` adalah sumber utama otorisasi per gudang.
- Permission export admin memakai boolean `can_export_pdf` dan `can_export_excel`.
- Email transaksi memakai `receives_transaction_email`.

Risiko:
- `spectator` terlihat read-only secara konsep, tetapi API `KontakController` perlu regression test karena tidak ada guard eksplisit seperti modul transaksi.
- `admin` dan `spectator` bergantung pada `current_gudang_id`; fallback adalah gudang pivot pertama.

## Gudang

Fitur:
- CRUD gudang web untuk `super_admin`.
- API list gudang sesuai role.
- API switch gudang aktif untuk admin/spectator/user yang memiliki akses.
- Stok per gudang dan stok log.
- Export stok Excel.

Tabel terkait:
- `gudangs`
- `gudang_produk`
- `admin_gudang`
- `spectator_gudang`
- `stok_logs`

Kompatibilitas:
- `gudang_produk.stok` lama tetap ada dan dihitung sebagai total/legacy; kolom tipe stok baru adalah `stok_penjualan`, `stok_gratis`, `stok_sample`.
- API stok menormalisasi `stok = stok_penjualan + stok_gratis + stok_sample`.

## Produk

Fitur:
- CRUD produk API dan web hanya `super_admin`.
- List produk role-scoped:
  - `super_admin`: semua produk.
  - `admin`/`spectator`: produk pada current gudang.
  - `user`: produk pada gudang user.
- Harga retail dan grosir.
- Satuan produk: `Pcs`, `Lusin`, `Karton`.
- Print/download produk di web.

Kolom penting:
- `nama_produk`
- `item_code`
- `harga`
- `harga_grosir`
- `satuan`
- `deskripsi`

Kompatibilitas:
- Penjualan memakai `tipe_harga` untuk memilih `harga` atau `harga_grosir`; payload `harga_satuan` pada penjualan tidak menjadi sumber harga utama.

## Kontak dan Customer

Fitur:
- CRUD kontak API/web.
- Customer portal memakai nomor telepon dan PIN 6 digit.
- Kontak punya kode otomatis `KT00001`, gudang, creator, diskon persen, dan PIN.
- Customer portal menampilkan riwayat penjualan dan kunjungan.

Aturan akses API:
- `super_admin` dan `spectator` melihat semua kontak pada index.
- `admin` melihat kontak gudang yang bisa diakses plus kontak tanpa gudang.
- `user` melihat kontak yang dibuat sendiri atau legacy contact dari nama pelanggan transaksi penjualan.

Kompatibilitas:
- Matching customer portal masih berbasis `kontaks.no_telp` dan `penjualans.pelanggan = kontaks.nama`; ini rapuh tetapi harus dipertahankan sampai ada migrasi data relasional.
- Normalisasi nomor customer portal mengubah `08...` dan `8...` menjadi `62...`.

## Penjualan

Fitur:
- List/detail/create/update API.
- Approve/cancel/uncancel/mark-paid/unmark-paid API dan web.
- Lampiran single path legacy dan multi lampiran `lampiran_paths`.
- PDF invoice publik dan download.
- Print HTML, rich text, image/struk, QR, dan Bluetooth JSON.
- Harga retail/grosir.
- Stok keluar memakai `stok_penjualan`.

Aturan bisnis utama:
- Create memerlukan stok penjualan cukup.
- Status awal `Pending`.
- Approval oleh admin/super admin mengubah status ke `Approved`.
- `mark-paid` hanya dari `Approved` ke `Lunas`.
- `unmark-paid` hanya super admin dari `Lunas` ke `Approved`.
- Cancel lama cukup longgar: user boleh cancel miliknya; admin harus current gudang; tidak semua status dicegah.

Risiko:
- Update penjualan menetapkan status baru `Lunas` untuk `Cash`, berbeda dari create yang tetap `Pending`; perlu test kontrak agar perilaku lama diketahui.
- Nomor dokumen di model `custom_number` dan controller print punya variasi urutan token; jangan normalisasi tanpa snapshot.

## Pembelian

Fitur:
- List/detail/create/update API.
- Approve/cancel/uncancel.
- Lampiran.
- PDF invoice publik/download.
- Print/Bluetooth.
- Auto approver berdasarkan role dan gudang.

Aturan bisnis:
- Status awal `Pending`, kecuali update tertentu bisa menyesuaikan status berdasarkan `syarat_pembayaran`.
- Pembelian tidak langsung menambah stok; stok masuk terjadi lewat modul penerimaan barang.
- `get-pembelian-by-gudang` untuk penerimaan barang menghitung sisa qty dari penerimaan approved.

Risiko:
- `status_display` menampilkan `Approved` sebagai `Belum Lunas`; walau pembelian bukan payment flow utama, tampilan invoice bergantung pada accessor ini.

## Biaya

Fitur:
- List/detail/create/update API.
- Approve/cancel/uncancel.
- Jenis biaya `masuk`/`keluar`.
- Item biaya berdasarkan kategori dan jumlah.
- PDF invoice publik/download.
- Print/Bluetooth.

Aturan bisnis:
- `super_admin` dapat membuat biaya langsung `Approved`.
- Admin/user membuat biaya dengan alur approval.
- Cancel biaya approved hanya super admin.
- Gudang biaya nullable dan ditambahkan belakangan.

Risiko:
- Akses biaya lebih longgar daripada transaksi gudang lain pada beberapa cabang role; port sesuai kode lama dulu.

## Kunjungan

Fitur:
- List/detail/create/update API.
- Approve/cancel/uncancel.
- Tujuan kunjungan: `Pemeriksaan Stock`, `Penagihan`, `Promo Gratis`, `Promo Sample`.
- Items wajib untuk `Pemeriksaan Stock`, `Promo Gratis`, `Promo Sample`.
- Batch/expired untuk item kunjungan.
- PDF invoice publik/download.
- Print/Bluetooth.

Aturan bisnis:
- `Promo Gratis` memakai stok `stok_gratis`.
- `Promo Sample` memakai stok `stok_sample`.
- Detail API menambahkan `kuantitas` dan `tipe_stok` turunan agar mobile membaca shape lama.

Risiko:
- Model masih punya badge untuk `Penawaran` dan `Promo`, tetapi validasi create API membatasi opsi baru; dokumentasikan sebagai legacy display only.

## Pembayaran

Fitur:
- List/detail/create API.
- Approve/cancel/uncancel.
- Lookup penjualan by gudang dan detail penjualan.
- Export tagihan harian PDF.
- Public invoice dan download.

Aturan bisnis:
- Pembayaran dibuat `Pending`.
- Approval pembayaran menjumlah semua pembayaran `Approved`; jika total bayar >= `penjualan.grand_total`, status penjualan menjadi `Lunas`.
- Cancel pembayaran approved oleh super admin dapat mengembalikan penjualan `Lunas` ke `Approved`.

Kompatibilitas:
- `getPenjualanByGudang` hanya mengembalikan penjualan status `Approved` atau `Lunas` yang masih punya sisa tagihan.

## Penerimaan Barang

Fitur:
- List/detail/create API.
- Approve/cancel.
- Lookup pembelian by gudang dan detail pembelian.
- Public invoice dan download.

Aturan bisnis:
- Penerimaan harus cocok dengan `gudang_id` pembelian.
- Item punya `qty_diterima`, `qty_reject`, `tipe_stok`, `batch_number`, `expired_date`.
- `tipe_stok`: `penjualan`, `gratis`, `sample`.
- Approval menambah stok legacy `stok` dan kolom tipe stok.
- Cancel penerimaan approved hanya super admin dan mengurangi stok.

Risiko:
- Filename PDF penerimaan download memakai prefix `GRN`, sedangkan model custom number memakai `RCV`; pertahankan keduanya sesuai endpoint.

## Stok dan Stok Log

Fitur:
- API list stok gudang.
- API update stok manual hanya `super_admin`.
- API stok log untuk admin/super admin.
- Web stok dan stok log di group `role:admin`.

Aturan:
- `gudang_produk` punya stok legacy dan stok tipe.
- Update stok manual membuat `StokLog` jika delta total stok berubah.
- `stok_logs` menyimpan snapshot nama produk/gudang/user selain FK.

Kompatibilitas:
- Jangan menghapus `stok` legacy walau tipe stok baru tersedia.
- Stok log harus tetap mencatat `stok_sebelum`, `stok_sesudah`, `selisih`, dan `keterangan`.

## Dashboard dan Report

Fitur API:
- `GET /dashboard`
- `GET /dashboard/daily-report`
- `GET /dashboard/daily-report/pdf`
- `GET /dashboard/export/options`
- `POST /dashboard/export`
- `GET /lampiran/download`

Fitur web:
- `/dashboard`
- `/home`
- `/report/daily`
- `/report/export`

Aturan:
- Dashboard role-scoped.
- Export PDF/Excel hanya `super_admin` atau admin dengan permission.
- `downloadLampiran` hanya mengizinkan prefix `lampiran_` dan menolak path traversal.

Kompatibilitas:
- `dashboard/export/options` adalah kontrak penting untuk UI/mobile karena mengembalikan daftar filter dan permission.

## PDF dan Excel Export

Paket lama:
- PDF: `barryvdh/laravel-dompdf`
- Excel: `maatwebsite/excel`

Fitur:
- Daily report PDF.
- Export dashboard/report PDF/Excel.
- Export stok Excel.
- Export kontak.
- Download invoice publik.
- Export tagihan harian pembayaran PDF.

Kompatibilitas:
- Header response download, filename, dan content type harus masuk test kontrak.
- Dompdf versi baru menonaktifkan remote access by default; asset invoice lama perlu dicek.

## QR, Barcode, Invoice Publik, Public Receipt

Fitur:
- API `print/{type}/{id}/qr` mengembalikan URL receipt/invoice/download dan `qr_payload`.
- Web public invoice:
  - `/invoice/penjualan/{uuid}`
  - `/invoice/pembelian/{uuid}`
  - `/invoice/biaya/{uuid}`
  - `/invoice/kunjungan/{uuid}`
  - `/invoice/pembayaran/{uuid}`
  - `/invoice/penerimaan-barang/{uuid}`
- Download PDF dengan suffix `/download`.
- Public receipt:
  - `/struk/{type}/{uuid}`

Tipe QR yang didukung API:
- `penjualan`
- `pembelian`
- `biaya`
- `kunjungan`
- `pembayaran`
- `penerimaan-barang`

Kompatibilitas:
- UUID menjadi mekanisme anti-enumeration public invoice; jangan ganti ke ID publik.

## Bluetooth dan Thermal Print API

Fitur:
- API `GET /api/v1/print/{type}/{id}/bluetooth` untuk `penjualan`, `pembelian`, `biaya`, `kunjungan`.
- Web endpoint JSON/rich text untuk Bluetooth:
  - `/bluetooth/penjualan/{id}`
  - `/bluetooth/pembelian/{id}`
  - `/bluetooth/biaya/{id}`
  - `/bluetooth/kunjungan/{id}`
  - `/{module}/{id}/print-json`
  - `/{module}/{id}/print-rich`
- Rich text thermal memakai width 32 karakter dan ESC/POS control characters.

Kompatibilitas:
- Response JSON Bluetooth punya shape khusus per tipe, bukan resource standar Eloquent.
- API Bluetooth untuk `pembayaran` dan `penerimaan-barang` mengembalikan 400 unsupported walau QR mendukung tipe tersebut.

## Customer Portal

Fitur:
- Login customer dua tahap: nomor telepon lalu PIN.
- Dashboard customer.
- Riwayat penjualan customer.
- Detail penjualan customer.
- Riwayat kunjungan customer.
- Detail kunjungan customer.
- Logout.

Aturan:
- Session keys: `customer_id`, `customer_no_telp`, `customer_nama`.
- Penjualan customer dicari dari `pelanggan = kontak.nama`.
- Kunjungan customer dicari dari `kontak_id`.

Risiko:
- Matching penjualan berbasis nama kontak rentan nama berubah/duplikat. Rebuild harus mempertahankan perilaku untuk kompatibilitas, lalu dapat diberi rencana migrasi terpisah.

## Web Admin Blade

Fitur web utama:
- Dashboard.
- CRUD users, gudang, produk, kontak.
- Penjualan, pembelian, biaya, kunjungan, pembayaran, penerimaan barang.
- Approval/cancel/uncancel action.
- Stok dan stok log.
- Profile.
- Report/export.
- Print/PDF/struk/invoice public.

Kompatibilitas UI:
- Target rebuild bukan redesign. Blade Bootstrap lama harus dipindah secara bertahap dengan URL, nama route, dan form field lama tetap cocok.
- Asset pipeline pindah dari Laravel Mix ke Vite, tetapi tampilan utama tetap harus terlihat dan berperilaku sama.

## Email Notification System

Fakta:
- `app/Services/InvoiceEmailService.php` mengatur semua pengiriman email transaksi.
- Email dikirim async via `dispatch()->afterResponse()` agar tidak blocking request.
- Tipe notifikasi:
  - `created`: dikirim ke pembuat transaksi saat transaksi baru dibuat.
  - `needs_approval`: dikirim ke semua approver gudang (admin + super_admin yang `receives_transaction_email = true`).
  - `approved`: dikirim ke pembuat saat transaksi disetujui.
- Tipe transaksi yang didukung: `penjualan`, `pembelian`, `biaya`, `kunjungan`.
- PDF invoice dilampirkan ke email menggunakan view `public.invoice-{type}-pdf`.
- Mailable classes:
  - `App\Mail\TransaksiInvoiceMail` - invoice langsung.
  - `App\Mail\TransaksiNotificationMail` - notifikasi created/needs_approval/approved.
- Subject format:
  - Invoice: `Invoice {Type} #{nomor} - Hibiscus Efsya`
  - Notification: `[{NotifLabel}] {Type} #{nomor} - Hibiscus Efsya`
- View templates:
  - `emails/transaksi-invoice.blade.php`
  - `emails/transaksi-notification.blade.php`
  - `emails/invoice-{type}.blade.php` (type-specific styled like public invoice)

Kompatibilitas:
- Email notification harus tetap dikirim saat transaksi dibuat dan diapprove.
- Recipient logic (super_admin + admin gudang yang `receives_transaction_email = true`) harus dipertahankan.
- Laravel 13 memakai Symfony Mailer; SwiftMailer config lama perlu dimigrasikan.
- PDF attachment format dan filename `invoice-{type}-{nomor}.pdf` harus dipertahankan.

## Global Helpers

Fakta:
- File `app/Helpers/helpers.php` di-autoload via `composer.json` `files` array.
- Helper functions:
  - `formatJson($jsonString)` - syntax highlight JSON untuk API docs view.
  - `format_rupiah($value, $prefix)` - format angka ke rupiah `Rp1.000,00`.
  - `receipt_limit_text($value, $max)` - potong teks untuk struk thermal max karakter.
  - `receipt_format_phone($value)` - normalisasi nomor telepon Indonesia ke `+62` format.

Kompatibilitas:
- Helper ini dipakai di view Blade dan struk/print. Harus di-port utuh.
- Format rupiah harus tetap konsisten (`Rp` prefix, titik ribuan, koma desimal).
- Phone format dipakai di struk thermal dan invoice.

## ESC/POS Helper

Fakta:
- `app/Helpers/EscPosHelper.php` class untuk generate raw ESC/POS data.
- Menyediakan: initialize, align, bold, textSize, text, line, feed, cut, separator, twoColumn.
- Output: `base64_encode($buffer)` untuk web Bluetooth print dan `outputRaw()` untuk raw.
- Width default thermal receipt: 32 karakter.

Kompatibilitas:
- Class ini dipakai oleh `PrintController` rich text endpoints.
- Harus di-port tanpa perubahan byte-level karena hardware printer bergantung pada ESC/POS sequence.

## Export Classes

Fakta:
- `app/Exports/TransactionsExport.php`:
  - Implements `FromView`, `WithTitle`, `ShouldAutoSize`, `WithStyles`, `WithColumnFormatting`.
  - View-based export: `reports/transactions`, `reports/penjualan`, `reports/pembelian`, `reports/biaya`, `reports/kunjungan`, `reports/pembayaran`.
  - Column formatting untuk phone numbers sebagai TEXT agar tidak diubah Excel.
  - Header row bold.
- `app/Exports/StokExport.php`:
  - Export stok per gudang ke Excel.

Kompatibilitas:
- `maatwebsite/excel` 3.1.x kompatibel Laravel 13.
- View Blade untuk export harus di-port.
- Phone column format TEXT harus dipertahankan.

## Gap Audit yang Harus Ditutup dengan Test

- Snapshot JSON semua endpoint API karena beberapa response memakai paginator/resource Eloquent mentah.
- Snapshot status code untuk validation error, forbidden, unauthenticated, unsupported print type, dan not found.
- Verifikasi real schema production sebelum membuat migration Laravel 13 karena migration lama mungkin tidak sama dengan database production.
- Verifikasi variasi nomor dokumen (`custom_number`, `nomor`, print output, filename PDF).
- Verifikasi spectator mutation pada API kontak dan endpoint lain yang tidak punya guard eksplisit.
- Test email notification dispatch untuk setiap tipe transaksi.
- Verifikasi helper function output (format_rupiah, receipt_format_phone) konsisten.
- Verifikasi ESC/POS byte sequence tidak berubah setelah port.
