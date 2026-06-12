# Web Route Map

Scope: mapping dari `routes/web.php`. Semua route web lama tercantum di dokumen ini sebagai kontrak URL/route name untuk rebuild Laravel 13. Tujuan rebuild adalah mempertahankan URL, method, nama route, middleware, dan field form utama selama fase compatibility.

## Root dan Auth Web

| Method | Path | Name | Action | Middleware | Catatan |
| --- | --- | --- | --- | --- | --- |
| GET | `/` | none | redirect ke `/login` | none | Landing lama hanya redirect |
| Laravel Auth | auth routes | generated | `Auth::routes()` | web | Login, logout, register/password reset bawaan Laravel UI lama |

Catatan rebuild:
- Jika Laravel 13 tidak memakai `Auth::routes()`, buat route manual yang mempertahankan URL standar lama (`/login`, `/logout`, `/password/reset`, dll) sesuai kebutuhan production.
- Jangan ubah form login web sebelum view Blade lama disnapshot.

## Customer Portal

Prefix: `/customer`

Name prefix: `customer.`

| Method | Path | Name | Action | Middleware | Catatan |
| --- | --- | --- | --- | --- | --- |
| GET | `/customer` | `customer.login` | `CustomerPortalController@loginForm` | web | Alias halaman login |
| GET | `/customer/login` | `customer.login.form` | `loginForm` | web | Step 1 nomor telepon |
| POST | `/customer/check-phone` | `customer.check-phone` | `checkPhone` | web | Validasi nomor, tampil form PIN |
| POST | `/customer/login` | `customer.login.post` | `login` | web | Login PIN, set session customer |
| POST | `/customer/logout` | `customer.logout` | `logout` | web | Hapus session customer |
| GET | `/customer/dashboard` | `customer.dashboard` | `dashboard` | `customer.auth` | Ringkasan transaksi customer |
| GET | `/customer/history` | `customer.history` | `history` | `customer.auth` | Riwayat penjualan |
| GET | `/customer/history/{id}` | `customer.history.detail` | `historyDetail` | `customer.auth` | Detail penjualan |
| GET | `/customer/kunjungan` | `customer.kunjungan` | `kunjungan` | `customer.auth` | Riwayat kunjungan |
| GET | `/customer/kunjungan/{id}` | `customer.kunjungan.detail` | `kunjunganDetail` | `customer.auth` | Detail kunjungan |

Kompatibilitas:
- Session keys harus tetap `customer_id`, `customer_no_telp`, `customer_nama`.
- Nomor telepon dinormalisasi ke format `62...`.
- Customer history penjualan masih berbasis `penjualans.pelanggan = kontaks.nama`.

## Dokumentasi API Publik Internal

| Method | Path | Name | Action | Middleware | Catatan |
| --- | --- | --- | --- | --- | --- |
| GET | `/docs` | `api.docs` | `ApiDocController@index` | web | UI docs |
| GET | `/docs/json` | `api.docs.json` | `ApiDocController@json` | web | JSON docs |
| GET | `/docs/download` | `api.docs.download` | `ApiDocController@download` | web | Download docs |
| GET | `/docs/download/postman` | `api.docs.download.postman` | `ApiDocController@downloadPostman` | web | Download Postman collection |

## Public Invoice

Prefix: `/invoice`

Name prefix: `public.invoice.`

| Method | Path | Name | Action | Middleware | Catatan |
| --- | --- | --- | --- | --- | --- |
| GET | `/invoice/penjualan/{uuid}` | `public.invoice.penjualan` | `PublicInvoiceController@showPenjualan` | web | Public invoice by UUID |
| GET | `/invoice/penjualan/{uuid}/download` | `public.invoice.penjualan.download` | `downloadPenjualan` | web | PDF `INV-...pdf` |
| GET | `/invoice/pembelian/{uuid}` | `public.invoice.pembelian` | `showPembelian` | web | Public invoice by UUID |
| GET | `/invoice/pembelian/{uuid}/download` | `public.invoice.pembelian.download` | `downloadPembelian` | web | PDF `PR-...pdf` |
| GET | `/invoice/biaya/{uuid}` | `public.invoice.biaya` | `showBiaya` | web | Public invoice by UUID |
| GET | `/invoice/biaya/{uuid}/download` | `public.invoice.biaya.download` | `downloadBiaya` | web | PDF `EXP-...pdf` |
| GET | `/invoice/kunjungan/{uuid}` | `public.invoice.kunjungan` | `showKunjungan` | web | Public invoice by UUID |
| GET | `/invoice/kunjungan/{uuid}/download` | `public.invoice.kunjungan.download` | `downloadKunjungan` | web | PDF `VST-...pdf` |
| GET | `/invoice/pembayaran/{uuid}` | `public.invoice.pembayaran` | `showPembayaran` | web | Public invoice by UUID |
| GET | `/invoice/pembayaran/{uuid}/download` | `public.invoice.pembayaran.download` | `downloadPembayaran` | web | PDF `PAY-...pdf` |
| GET | `/invoice/penerimaan-barang/{uuid}` | `public.invoice.penerimaan` | `showPenerimaanBarang` | web | Public invoice by UUID |
| GET | `/invoice/penerimaan-barang/{uuid}/download` | `public.invoice.penerimaan.download` | `downloadPenerimaanBarang` | web | PDF `GRN-...pdf` |

Kompatibilitas:
- Public invoice memakai `uuid`, bukan id.
- Template Blade dan filename PDF harus disnapshot.
- Prefix PDF penerimaan di download adalah `GRN`, sedangkan model custom number memakai `RCV`.

## Public Receipt

| Method | Path | Name | Action | Middleware | Catatan |
| --- | --- | --- | --- | --- | --- |
| GET | `/struk/{type}/{uuid}` | `public.receipt.show` | `PublicReceiptController@show` | web | Public struk by UUID |

Supported `type`:
- `penjualan`
- `pembelian`
- `biaya`
- `kunjungan`
- `pembayaran`
- `penerimaan-barang`

## Authenticated Web Dashboard dan Profile

Middleware group: `auth`

| Method | Path | Name | Action | Middleware tambahan | Catatan |
| --- | --- | --- | --- | --- | --- |
| GET | `/dashboard` | `dashboard` | `DashboardController@index` | none | Dashboard utama |
| GET | `/home` | `home` | `DashboardController@index` | none | Alias dashboard |
| GET | `/report/daily` | `report.daily` | `DashboardController@dailyReport` | none | Daily report web |
| GET | `/profil` | `profile.show` | `ProfileController@show` | none | Profile user |
| PUT | `/profil` | `profile.update` | `ProfileController@update` | none | Update profile |
| POST | `/profil/change-password` | `profile.change-password` | `ProfileController@changePassword` | none | Change password |
| POST | `/profil/avatar` | `profile.upload-avatar` | `ProfileController@uploadAvatar` | none | Upload avatar |
| DELETE | `/profil/avatar` | `profile.delete-avatar` | `ProfileController@deleteAvatar` | none | Delete avatar |

## Web Bluetooth JSON

Middleware group: `auth`

| Method | Path | Name | Action | Catatan |
| --- | --- | --- | --- | --- |
| GET | `/bluetooth/penjualan/{id}` | `bluetooth.penjualan` | `BluetoothPrintController@penjualanJson` | JSON for client-side Bluetooth print |
| GET | `/bluetooth/pembelian/{id}` | `bluetooth.pembelian` | `BluetoothPrintController@pembelianJson` | JSON for client-side Bluetooth print |
| GET | `/bluetooth/biaya/{id}` | `bluetooth.biaya` | `BluetoothPrintController@biayaJson` | JSON for client-side Bluetooth print |
| GET | `/bluetooth/kunjungan/{id}` | `bluetooth.kunjungan` | `BluetoothPrintController@kunjunganJson` | JSON for client-side Bluetooth print |

## Penjualan Web

Middleware group: `auth`

| Method | Path | Name | Action | Catatan |
| --- | --- | --- | --- | --- |
| GET | `/penjualan/{id}/print` | `penjualan.print` | `PenjualanController@print` | HTML print |
| GET | `/penjualan/{id}/print-json` | `penjualan.printJson` | `PenjualanController@printJson` | ESC/POS JSON/text helper |
| GET | `/penjualan/{id}/print-rich` | `penjualan.printRich` | `PrintController@penjualanRichText` | Plain text ESC/POS |
| GET | `/penjualan/{id}/struk-image` | `penjualan.strukImage` | `PrintImageController@penjualan` | Image struk |
| POST | `/penjualan/{id}/approve` | `penjualan.approve` | `PenjualanController@approve` | Approve |
| POST | `/penjualan/{id}/cancel` | `penjualan.cancel` | `PenjualanController@cancel` | Cancel |
| POST | `/penjualan/{id}/uncancel` | `penjualan.uncancel` | `PenjualanController@uncancel` | Restore canceled |
| POST | `/penjualan/{id}/mark-paid` | `penjualan.mark-paid` | `PenjualanController@markAsPaid` | Mark Lunas |
| POST | `/penjualan/{id}/unmark-paid` | `penjualan.unmark-paid` | `PenjualanController@unmarkAsPaid` | Back to Approved |
| DELETE | `/penjualan/{id}/lampiran` | `penjualan.lampiran.delete` | `PenjualanController@deleteLampiran` | Delete attachment |
| Resource | `/penjualan` | `penjualan.*` | `PenjualanController` resource | index/create/store/show/edit/update/destroy |

## Pembelian Web

Middleware group: `auth`

| Method | Path | Name | Action | Catatan |
| --- | --- | --- | --- | --- |
| GET | `/pembelian/{id}/print` | `pembelian.print` | `PembelianController@print` | HTML print |
| GET | `/pembelian/{id}/print-json` | `pembelian.printJson` | `PembelianController@printJson` | ESC/POS helper |
| GET | `/pembelian/{id}/print-rich` | `pembelian.printRich` | `PrintController@pembelianRichText` | Plain text ESC/POS |
| GET | `/pembelian/{id}/struk-image` | `pembelian.strukImage` | `PrintImageController@pembelian` | Image struk |
| POST | `/pembelian/{id}/approve` | `pembelian.approve` | `PembelianController@approve` | Approve |
| POST | `/pembelian/{id}/cancel` | `pembelian.cancel` | `PembelianController@cancel` | Cancel |
| POST | `/pembelian/{id}/uncancel` | `pembelian.uncancel` | `PembelianController@uncancel` | Restore canceled |
| DELETE | `/pembelian/{id}/lampiran` | `pembelian.lampiran.delete` | `PembelianController@deleteLampiran` | Delete attachment |
| Resource | `/pembelian` | `pembelian.*` | `PembelianController` resource | index/create/store/show/edit/update/destroy |

## Biaya Web

Middleware group: `auth`

| Method | Path | Name | Action | Catatan |
| --- | --- | --- | --- | --- |
| GET | `/biaya/{id}/print` | `biaya.print` | `BiayaController@print` | HTML print |
| GET | `/biaya/{id}/print-json` | `biaya.printJson` | `BiayaController@printJson` | ESC/POS helper |
| GET | `/biaya/{id}/print-rich` | `biaya.printRich` | `PrintController@biayaRichText` | Plain text ESC/POS |
| GET | `/biaya/{id}/struk-image` | `biaya.strukImage` | `PrintImageController@biaya` | Image struk |
| POST | `/biaya/{id}/approve` | `biaya.approve` | `BiayaController@approve` | Approve |
| POST | `/biaya/{id}/cancel` | `biaya.cancel` | `BiayaController@cancel` | Cancel |
| POST | `/biaya/{id}/uncancel` | `biaya.uncancel` | `BiayaController@uncancel` | Restore canceled |
| DELETE | `/biaya/{id}/lampiran` | `biaya.lampiran.delete` | `BiayaController@deleteLampiran` | Delete attachment |
| Resource | `/biaya` | `biaya.*` | `BiayaController` resource | index/create/store/show/edit/update/destroy |

## Kunjungan Web

Middleware group: `auth`

| Method | Path | Name | Action | Catatan |
| --- | --- | --- | --- | --- |
| GET | `/kunjungan/{id}/print` | `kunjungan.print` | `KunjunganController@print` | HTML print |
| GET | `/kunjungan/{id}/print-json` | `kunjungan.printJson` | `KunjunganController@printJson` | ESC/POS helper |
| POST | `/kunjungan/{id}/approve` | `kunjungan.approve` | `KunjunganController@approve` | Approve |
| POST | `/kunjungan/{id}/cancel` | `kunjungan.cancel` | `KunjunganController@cancel` | Cancel |
| POST | `/kunjungan/{id}/uncancel` | `kunjungan.uncancel` | `KunjunganController@uncancel` | Restore canceled |
| DELETE | `/kunjungan/{id}/lampiran` | `kunjungan.lampiran.delete` | `KunjunganController@deleteLampiran` | Delete attachment |
| Resource | `/kunjungan` | `kunjungan.*` | `KunjunganController` resource | index/create/store/show/edit/update/destroy |

## Pembayaran Web

Middleware group: `auth`

| Method | Path | Name | Action | Catatan |
| --- | --- | --- | --- | --- |
| GET | `/pembayaran/{id}/print` | `pembayaran.print` | `PembayaranController@print` | HTML print |
| POST | `/pembayaran/{id}/approve` | `pembayaran.approve` | `PembayaranController@approve` | Approve |
| POST | `/pembayaran/{id}/cancel` | `pembayaran.cancel` | `PembayaranController@cancel` | Cancel |
| POST | `/pembayaran/{id}/uncancel` | `pembayaran.uncancel` | `PembayaranController@uncancel` | Restore canceled |
| DELETE | `/pembayaran/{id}/lampiran` | `pembayaran.lampiran.delete` | `PembayaranController@deleteLampiran` | Delete attachment |
| GET | `/pembayaran/get-penjualan/{id}` | `pembayaran.get-penjualan` | `PembayaranController@getPenjualan` | Lookup detail |
| GET | `/pembayaran/get-penjualan-by-gudang/{gudangId}` | `pembayaran.get-penjualan-by-gudang` | `PembayaranController@getPenjualanByGudang` | Lookup by gudang |
| GET | `/pembayaran/export-harian-pdf` | `pembayaran.export-harian-pdf` | `PembayaranController@exportHarianPdf` | PDF tagihan |
| Resource | `/pembayaran` | `pembayaran.*` | `PembayaranController` resource | index/create/store/show/edit/update/destroy |

## Penerimaan Barang Web

Middleware group: `auth`

| Method | Path | Name | Action | Catatan |
| --- | --- | --- | --- | --- |
| GET | `/penerimaan-barang/{id}/print` | `penerimaan-barang.print` | `PenerimaanBarangController@print` | HTML print |
| POST | `/penerimaan-barang/{id}/approve` | `penerimaan-barang.approve` | `PenerimaanBarangController@approve` | Approve, add stock |
| POST | `/penerimaan-barang/{id}/cancel` | `penerimaan-barang.cancel` | `PenerimaanBarangController@cancel` | Cancel |
| POST | `/penerimaan-barang/{id}/uncancel` | `penerimaan-barang.uncancel` | `PenerimaanBarangController@uncancel` | Restore canceled if implemented |
| DELETE | `/penerimaan-barang/{id}/lampiran` | `penerimaan-barang.lampiran.delete` | `PenerimaanBarangController@deleteLampiran` | Delete attachment |
| GET | `/penerimaan-barang/get-pembelian/{id}` | `penerimaan-barang.get-pembelian` | `PenerimaanBarangController@getPembelian` | Lookup detail |
| GET | `/penerimaan-barang/get-pembelian-by-gudang/{gudangId}` | `penerimaan-barang.get-pembelian-by-gudang` | `PenerimaanBarangController@getPembelianByGudang` | Lookup by gudang |
| Resource | `/penerimaan-barang` | `penerimaan-barang.*` | `PenerimaanBarangController` resource | index/create/store/show/edit/update/destroy |

## Kontak Web

Middleware group: `auth`

| Method | Path | Name | Action | Catatan |
| --- | --- | --- | --- | --- |
| GET | `/kontak/print` | `kontak.print` | `KontakController@print` | Print list |
| GET | `/kontak/download` | `kontak.download` | `KontakController@download` | Export/download |
| Resource | `/kontak` | `kontak.*` | `KontakController` resource | index/create/store/show/edit/update/destroy |

## Admin/Spectator Gudang and Report Group

Middleware group: `auth`, `role:admin`

Note: `CheckRole` memperbolehkan `spectator` untuk `role:admin`. Controller/view harus tetap menahan write action yang seharusnya read-only.

| Method | Path | Name | Action | Catatan |
| --- | --- | --- | --- | --- |
| POST | `/switch-gudang` | `gudang.switch` | `GudangController@switchGudang` | Ubah current gudang |
| GET | `/stok` | `stok.index` | `StokController@index` | Halaman stok |
| POST | `/stok/export` | `stok.export` | `StokController@export` | Export stok |
| GET | `/stok/log` | `stok.log` | `StokController@log` | Stok log |
| GET | `/report/export` | `report.export` | `DashboardController@export` | Export report web |

## Super Admin Group

Middleware group: `auth`, `role:super_admin`

### User management

| Method | Path | Name | Action | Catatan |
| --- | --- | --- | --- | --- |
| Resource | `/users` | `users.*` | `UserController` resource | CRUD users |
| PATCH | `/users/{user}/email-recipient` | `users.email-recipient` | `UserController@updateEmailRecipient` | Toggle receives transaction email |
| PATCH | `/users/{user}/export-permission` | `users.export-permission` | `UserController@updateExportPermission` | Toggle export PDF/Excel |

### Gudang, produk, stok

| Method | Path | Name | Action | Catatan |
| --- | --- | --- | --- | --- |
| Resource | `/gudang` | `gudang.*` | `GudangController` resource | CRUD gudang |
| GET | `/produk/print` | `produk.print` | `ProdukController@print` | Print product list |
| GET | `/produk/download` | `produk.download` | `ProdukController@download` | Download/export product |
| Resource | `/produk` | `produk.*` | `ProdukController` resource | CRUD produk |
| POST | `/stok` | `stok.store` | `StokController@store` | Manual stock update |

## Route Compatibility Risks

- Resource routes must preserve route names because Blade forms/buttons likely use route helpers.
- Route order matters for paths like `/pembayaran/export-harian-pdf` before `/pembayaran/{id}` on API; web resource routes should also avoid catching custom paths.
- `role:admin` includes spectator by middleware, so write operations in this group must rely on controller/view guards from old behavior.
- Public invoice and receipt use UUID. Do not expose sequential id in public URL.
- Print endpoints return mixed content types: HTML, PDF, image, JSON, and plain text ESC/POS.

## Web Rebuild Acceptance Criteria

Before switching web traffic:
- All paths above return either same view/content type or same redirect behavior.
- All named routes used by Blade templates exist.
- Login/logout/profile/customer portal sessions work.
- Public invoice and `/struk/{type}/{uuid}` are reachable without auth.
- Admin/spectator/user route visibility matches old role behavior.
- Print/PDF/export endpoints preserve filename and content type.
