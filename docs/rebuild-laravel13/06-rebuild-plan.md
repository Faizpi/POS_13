# Safe Rebuild Plan Laravel 13

Prinsip rebuild:
- Rebuild fondasi baru, bukan upgrade in-place repo production lama.
- Database lama tetap sumber data utama.
- API mobile `/api/v1` menjadi kontrak utama.
- Stabilitas bisnis lebih penting daripada refactor besar.
- Semua perubahan behavior harus dibuktikan dengan test atau ditunda.
- Jangan menulis ke database production selama fase audit dan contract capture.

## Target Arsitektur Awal

Target awal Laravel 13:
- Laravel 13 fresh app di `sales_hibiscusefsya_laravel13`.
- Namespace modern `App\Models` boleh dipakai, tetapi table/column/API shape tetap lama.
- Route web/API lama dipertahankan.
- Middleware custom:
  - `api.token`
  - `role`
  - `customer.auth`
- Auth API tetap custom bearer token SHA-256.
- Blade admin lama dipindah bertahap, tidak redesign.
- Config session/cache/CORS/filesystem/mail eksplisit.
- Test kontrak API dibuat sebelum port business logic.

## Urutan Rebuild Paling Aman

### Fase 0 - Freeze dan Evidence Capture

Status: ✅ SELESAI (2026-06-05)

Tujuan:
- Menetapkan baseline behavior Laravel 7 lama.

Aktivitas yang sudah dilakukan:
- ✅ Dokumen audit lengkap (01-09) sebagai dasar.
- ✅ Analisis statis semua 14 API controller (validation, response shape, status codes, side effects, permissions).
- ✅ Contract test fixtures dibuat: `tests/Contracts/Fixtures/api-v1/*.json` (14 files).
- ✅ Baseline validation rules, exact Indonesian message strings, state machines, stock side effects tercatat.
- ⚠️ BELUM: Response capture langsung dari running app (butuh environment PHP 7 yang kompatibel atau staging).
- ⚠️ BELUM: Seed/fixture non-production dataset.
- ⚠️ BELUM: Screenshot/HTML/PDF visual capture.

Stop condition:
- ✅ Test contract baseline ada untuk semua endpoint dari code analysis.
- ✅ Tidak ada kebutuhan koneksi tulis ke production.
- ⚠️ Live response capture ditunda karena environment PHP lama tidak tersedia; baseline dari code sudah cukup untuk mulai Fase 1.

### Fase 1 - Skeleton Laravel 13 dan Konfigurasi Dasar

Status: ✅ SELESAI (2026-06-05)

Tujuan:
- Membuat fondasi Laravel 13 minimal tanpa business logic.

Aktivitas yang sudah dilakukan:
- ✅ Laravel 13.14.0 scaffolded di `sales_hibiscusefsya_laravel13`.
- ✅ PHP 8.5.7 terverifikasi.
- ✅ Filament v5.6.6 admin panel terinstall dan terkonfigurasi.
- ✅ Database configured (MySQL `sales_hibiscusefsya`).
- ✅ Timezone `Asia/Jakarta`, locale `id`.
- ✅ CORS, session, cache, filesystem defaults configured.
- ✅ Storage symlink dibuat.
- ✅ Middleware custom registered: `api.token`, `role`.
- ✅ API routes file lengkap (85 endpoint).
- ✅ 10 Filament Resources scaffolded.
- ✅ `php artisan test` pass.

Stop condition:
- ✅ Laravel 13 bisa boot, test kosong berjalan, dan tidak menyentuh DB production tulis.

### Fase 2 - Schema Compatibility dan Model Map

Tujuan:
- Laravel 13 bisa membaca/menulis schema lama di database dev/staging.

Aktivitas:
- Cocokkan migration audit dengan dump schema production read-only.
- Buat migration Laravel 13 yang merepresentasikan schema aktual.
- Port model, casts, fillable, relations, accessors:
  - User, Gudang, Produk, GudangProduk, Kontak.
  - Semua model transaksi dan item.
  - PersonalAccessToken, StokLog.
- Pastikan table name lama dipakai.

Stop condition:
- Test model relation/cast dasar pass di database test.
- Tidak ada rename column/table.

### Fase 3 - Auth, Role, dan Customer Session

Tujuan:
- Semua akses dasar kompatibel.

Aktivitas:
- Port web auth URL lama.
- Port `api.token` persis: bearer, SHA-256, expired, `last_used_at`, error message.
- Port `CheckRole` dan `CustomerAuth`.
- Port profile web/API dan avatar.
- Port customer portal login/session tanpa dashboard penuh dulu.

Stop condition:
- Contract test login/profile/logout/change-password/avatar pass.
- Permission matrix role dasar pass.

### Fase 4 - Master Data dan Gudang

Tujuan:
- Master yang dibutuhkan transaksi tersedia.

Aktivitas:
- Port Gudang API/web.
- Port User management.
- Port Produk API/web.
- Port Kontak API/web.
- Port switch current gudang.

Stop condition:
- CRUD dan list role-scoped pass.
- Route web resource/names lama tersedia.

### Fase 5 - Stok Core

Tujuan:
- Stok tersedia sebelum transaksi keluar/masuk.

Aktivitas:
- Port `gudang_produk` read.
- Port stok index, stok update manual, stok log.
- Port export stok.
- Test total legacy `stok` dan tipe stok.

Stop condition:
- Manual stok update membuat log sesuai baseline.
- Role-scoped stok read pass.

### Fase 6 - Penjualan

Tujuan:
- Modul transaksi paling kritis mobile berjalan.

Aktivitas:
- Port list/show/create/update.
- Port item calculation, harga retail/grosir, diskon, diskon nominal.
- Port stok check `stok_penjualan`.
- Port approve/cancel/uncancel/mark-paid/unmark-paid.
- Port lampiran.
- Port invoice/print penjualan minimal.

Stop condition:
- Contract test penjualan pass untuk semua role dan status transition.
- Stok tidak berubah kecuali sesuai rule lama.

### Fase 7 - Pembelian dan Penerimaan Barang

Tujuan:
- Alur pembelian sampai stok masuk berjalan.

Aktivitas:
- Port pembelian list/show/create/update/approve/cancel/uncancel.
- Port penerimaan barang lookup pembelian, create, approve, cancel.
- Port stock increment/decrement penerimaan.
- Port PDF/print terkait.

Stop condition:
- Pembelian tidak menambah stok langsung.
- Penerimaan approved menambah stok tipe dan legacy.
- Cancel approved oleh super admin mengurangi stok.

### Fase 8 - Biaya

Tujuan:
- Biaya masuk/keluar dan approval kompatibel.

Aktivitas:
- Port biaya list/show/create/update.
- Port items, tax, total.
- Port direct approved super admin.
- Port approve/cancel/uncancel.
- Port print/PDF.

Stop condition:
- Contract biaya untuk role dan status pass.

### Fase 9 - Kunjungan

Tujuan:
- Kunjungan dan promo stock kompatibel.

Aktivitas:
- Port kunjungan list/show/create/update.
- Port kontak/customer relation.
- Port tujuan dan item validation.
- Port promo gratis/sample stok check.
- Port approval/cancel/uncancel.
- Port invoice/print.

Stop condition:
- Contract transformed item shape pass.
- Promo stock validation pass.

### Fase 10 - Pembayaran

Tujuan:
- Payment flow dan status `Lunas` kompatibel.

Aktivitas:
- Port lookup penjualan by gudang/detail.
- Port pembayaran list/show/create.
- Port approve/cancel/uncancel.
- Port side effect penjualan `Lunas`/`Approved`.
- Port export harian PDF.

Stop condition:
- Partial/full payment behavior pass.
- Status penjualan update sesuai baseline.

### Fase 11 - Dashboard, Report, Export

Tujuan:
- Reporting operasional dan export admin kembali jalan.

Aktivitas:
- Port dashboard role-scoped metrics.
- Port daily report JSON/PDF.
- Port export options.
- Port export PDF/Excel.
- Port lampiran download security.

Stop condition:
- Contract keys dashboard/export options pass.
- Permission export pass.
- PDF/XLSX headers/filename pass.

### Fase 12 - QR, Public Invoice, Bluetooth, Thermal Print

Tujuan:
- Output dokumen dan mobile print kompatibel.

Aktivitas:
- Port public invoice by UUID.
- Port public receipt `/struk/{type}/{uuid}`.
- Port QR data API.
- Port Bluetooth JSON API/web.
- Port rich text ESC/POS endpoints.
- Snapshot rendered PDF/HTML/text.

Stop condition:
- QR payload/URLs pass.
- Bluetooth JSON keys pass.
- Unsupported type returns 400 sesuai baseline.

### Fase 13 - Web Admin Blade Completion

Tujuan:
- Semua tampilan utama admin kompatibel.

Aktivitas:
- Port layout, partial, forms, tables, filters, modals.
- Port Bootstrap/jQuery behavior melalui Vite.
- Pastikan route names lama ada.
- Browser smoke untuk setiap modul.

Stop condition:
- Role super/admin/spectator/user bisa membuka halaman sesuai akses.
- Form create/edit/action utama bekerja di staging.

### Fase 14 - Parallel Run dan Cutover

Tujuan:
- Mengurangi risiko go-live.

Aktivitas:
- Jalankan Laravel 13 di staging dengan clone database.
- Bandingkan API response lama vs baru.
- Jalankan test kontrak penuh.
- Uji mobile app lama ke staging Laravel 13.
- Buat rollback plan.

Stop condition:
- Semua contract tests pass.
- Mobile lama bisa login dan menjalankan alur transaksi utama.
- Tidak ada known error kritis.

## Prioritas Modul

Urutan prioritas bisnis:
1. Auth API dan role.
2. Gudang, produk, kontak.
3. Stok.
4. Penjualan.
5. Pembelian dan penerimaan barang.
6. Pembayaran.
7. Biaya.
8. Kunjungan.
9. Dashboard/report/export.
10. Print, QR, invoice publik.
11. Web admin Blade polish.
12. Customer portal.

Alasan:
- Mobile API dan transaksi/stok adalah risiko operasional tertinggi.
- Web admin bisa dipindah bertahap setelah kontrak data dan endpoint stabil.
- Customer portal penting, tetapi alurnya lebih kecil dan bisa dites setelah transaksi/customer data siap.

## Modul Boundary

Jangan mencampur porting antar modul tanpa test:
- Penjualan jangan diport bersamaan dengan pembayaran side effect kecuali fixture dan contract sudah ada.
- Penerimaan barang harus menunggu pembelian dan stok core.
- Dashboard/report harus menunggu transaksi karena agregasi role-scoped.
- Print/PDF harus menunggu data transaksi dan public UUID.

## Business Rule yang Harus Dipertahankan

- Admin/spectator memakai current gudang.
- User sales memakai gudang sendiri.
- Super admin akses semua.
- Penjualan create cek `stok_penjualan`.
- Kunjungan promo cek `stok_gratis` atau `stok_sample`.
- Penerimaan barang approve menambah stok.
- Pembayaran approve dapat membuat penjualan `Lunas`.
- Public invoice memakai UUID.
- Custom bearer token tetap SHA-256.
- Lampiran multi memakai `lampiran_paths`.
- Response API lama tidak dibungkus ulang dengan format baru.

## Refactor yang Ditunda

Tunda sampai compatibility selesai:
- Mengganti token API ke Sanctum.
- Mengganti role string ke package permission.
- Normalisasi customer penjualan dari `pelanggan` string ke FK.
- Menghapus `stok` legacy.
- Mengubah enum status menjadi database enum.
- Mengubah Bootstrap Blade ke frontend baru.
- Menstandarkan semua response dengan API Resource baru.
- Mengganti Dompdf ke renderer lain.
- Menambahkan constraint unik baru pada nomor transaksi.

## Data Migration Policy

Pada fase compatibility:
- Tidak ada destructive migration.
- Tidak ada rename/drop column.
- Tidak ada data mass update production tanpa script reversible dan backup.
- Semua migration schema harus cocok dengan database aktual.
- Gunakan read replica/staging clone untuk test.

Jika ditemukan mismatch:
- Dokumentasikan di migration notes.
- Buat adapter model/controller jika memungkinkan.
- Jadwalkan migration data terpisah setelah production freeze.

## Cutover Checklist

Sebelum cutover:
- PHP 8.3+ production tersedia.
- Env Laravel 13 lengkap.
- Storage symlink dan permission benar.
- Mail config teruji.
- Queue/scheduler jika dipakai sudah dipetakan.
- API contract tests pass.
- Browser smoke web admin pass.
- PDF/Excel/print pass.
- Mobile app lama pass login, list, create transaksi, approve/payment flow sesuai role.
- Rollback DNS/app server tersedia.

## Risiko Utama dan Mitigasi

| Risiko | Dampak | Mitigasi |
| --- | --- | --- |
| Response mobile berubah | Mobile lama crash | Snapshot contract sebelum porting; compare JSON keys/status |
| Auth token berubah | Semua API mobile gagal | Custom middleware kompatibel |
| Schema production beda dari migration | Error data runtime | Dump schema read-only sebelum migration |
| Stok salah | Kerugian bisnis | Test stok sebelum/sesudah transaksi; audit log |
| Role scoping salah | Kebocoran data gudang | Permission tests per role/gudang |
| PDF/Excel beda | Operasional admin terganggu | Contract headers/filename dan visual smoke |
| Web route names hilang | Blade form/action rusak | Route name test dan browser smoke |
| Nomor dokumen berubah | Audit/invoice kacau | Snapshot nomor per modul |
| Timezone/date berubah | Report tidak cocok | Set timezone dan date tests |
| Refactor terlalu besar | Rebuild sulit selesai | Port behavior dulu, refactor pasca cutover |
