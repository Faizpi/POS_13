# API Contract `/api/v1`

Scope: seluruh mapping diambil dari `routes/api.php`, `app/Http/Middleware/ApiTokenAuth.php`, dan controller di `app/Http/Controllers/Api`. Semua endpoint lama yang terdaftar di `routes/api.php` tercantum di dokumen ini.

Catatan penting:
- `GET /api/user` bawaan Laravel lama tetap ada dengan middleware `auth:api`, tetapi API mobile production memakai `/api/v1`.
- Semua route `/api/v1` selain login memakai middleware `api.token`.
- Response yang berasal dari Eloquent paginator/resource mentah harus disnapshot dari aplikasi lama sebelum porting karena bentuk detail bisa mengikuti atribut model/cast/appends.

## Auth dan Middleware

### Public login

`POST /api/v1/login`

Request:
- `email` required email
- `password` required string
- `device_name` nullable string max 255

Success `200`:

```json
{
  "message": "Login berhasil.",
  "token": "<plain bearer token>",
  "user": {
    "id": 1,
    "name": "User",
    "email": "user@example.com",
    "role": "user",
    "alamat": null,
    "no_telp": null,
    "avatar_url": null,
    "gudang_id": 1,
    "current_gudang_id": null
  }
}
```

Failure:
- `401 {"message":"Email atau password salah."}`
- `422` Laravel validation JSON.

### Protected API token behavior

Header:
- `Authorization: Bearer <token>`

Middleware behavior:
- Hash token dengan SHA-256.
- Cari `personal_access_tokens.token`.
- Reject expired token.
- Update `last_used_at`.
- Set authenticated user dan merge `api_token_id` ke request.

Failure:
- Missing token: `401 {"message":"Unauthenticated."}`
- Invalid/expired: `401 {"message":"Token invalid atau sudah expired."}`

## Endpoint Inventory

| Method | Endpoint | Action | Middleware | Request/filter utama | Response/status utama | Risiko kompatibilitas |
| --- | --- | --- | --- | --- | --- | --- |
| GET | `/api/user` | Closure Laravel | `auth:api` | none | Auth user | Legacy default, bukan kontrak mobile utama |
| POST | `/api/v1/login` | `Api\AuthController@login` | public | `email`, `password`, `device_name` | `200` login shape, `401`, `422` | Token plain hanya muncul sekali |
| POST | `/api/v1/logout` | `AuthController@logout` | `api.token` | none | `200 {"message":"Logout berhasil."}` | Hapus token via `api_token_id` |
| GET | `/api/v1/profile` | `AuthController@profile` | `api.token` | none | `200 {user,gudang}` | Shape user harus sama dengan login |
| PUT | `/api/v1/profile` | `AuthController@updateProfile` | `api.token` | `name`, `alamat`, `no_telp` | `200 {message,user}`; `422` | Partial update |
| POST | `/api/v1/change-password` | `AuthController@changePassword` | `api.token` | `current_password`, `new_password`, `new_password_confirmation` | `200 message`; `422` old password salah/validation | Password min 8 |
| POST | `/api/v1/profile/avatar` | `AuthController@uploadAvatar` | `api.token` | `avatar` file or `avatar_base64` | `200 {message,avatar_url,user}`; `422` | GD resize JPG quality 82 |
| DELETE | `/api/v1/profile/avatar` | `AuthController@deleteAvatar` | `api.token` | none | `200 {message,user}` | Deletes storage file if exists |
| GET | `/api/v1/dashboard` | `DashboardController@index` | `api.token` | none | `200` dashboard metrics JSON | Role-scoped values |
| GET | `/api/v1/dashboard/daily-report` | `DashboardController@dailyReport` | `api.token` | `date` optional | `200 {date,sales_name,summary,penjualans,pembelians,biayas,kunjungans}` | Date default today |
| GET | `/api/v1/dashboard/daily-report/pdf` | `DashboardController@dailyReportPdf` | `api.token` | `date` optional | PDF download | Header/filename contract |
| GET | `/api/v1/dashboard/export/options` | `DashboardController@exportOptions` | `api.token` | none | `200 {role,permissions,transaction_types,status_filters,...}` | Mobile/UI filter contract |
| POST | `/api/v1/dashboard/export` | `DashboardController@export` | `api.token` | `date_from`, `date_to`, `transaction_type`, filters, `export_format` | PDF/XLSX download; `403`; `422` | Admin needs export permission |
| GET | `/api/v1/lampiran/download` | `DashboardController@downloadLampiran` | `api.token` | `path` | File download; JSON `403`/`404` | Prefix/path traversal check |
| GET | `/api/v1/print/{type}/{id}/qr` | `PrintController@qrData` | `api.token` | `type`, `id` | `200 {type,id,uuid,receipt_url,invoice_url,download_url,qr_payload}` | Supports more types than bluetooth |
| GET | `/api/v1/print/{type}/{id}/bluetooth` | `PrintController@bluetoothData` | `api.token` | `type`, `id` | Bluetooth JSON; `400` unsupported | Only 4 transaction types |
| GET | `/api/v1/gudang` | `GudangController@index` | `api.token` | none | `200` collection gudang | Role-scoped |
| POST | `/api/v1/gudang/switch` | `GudangController@switchGudang` | `api.token` | `gudang_id` | `200 {message,current_gudang}`; `403`; `422` | Updates `current_gudang_id` |
| GET | `/api/v1/gudang/stok` | `GudangController@stok` | `api.token` | `gudang_id` optional | `200` stok gudang | Uses normalized total stok |
| GET | `/api/v1/gudang/stok-log` | `GudangController@stokLog` | `api.token` | filters tanggal/produk/gudang/per_page | Paginator stok log | Admin/super only |
| GET | `/api/v1/gudang/stok/export` | `GudangController@exportStok` | `api.token` | `gudang_id` | XLSX download | Access by gudang |
| GET | `/api/v1/produk/stok/{gudangId}` | `ProdukController@stokByGudang` | `api.token` | path `gudangId` | `200` stok produk per gudang; `403` | Used by mobile picker |
| GET | `/api/v1/produk` | `ProdukController@index` | `api.token` | `search`, `per_page` | Paginator produk | Role-scoped |
| GET | `/api/v1/produk/{id}` | `ProdukController@show` | `api.token` | path id | Produk with `stokDiGudang.gudang` | Access shape Eloquent |
| POST | `/api/v1/produk` | `ProdukController@store` | `api.token` | `nama_produk`, `item_code`, `harga`, `harga_grosir`, `satuan`, `deskripsi` | `201 {message,data}`; `403`; `422` | Super admin only |
| PUT | `/api/v1/produk/{id}` | `ProdukController@update` | `api.token` | same as store partial/required per code | `200 {message,data}`; `403`; `422` | Super admin only |
| DELETE | `/api/v1/produk/{id}` | `ProdukController@destroy` | `api.token` | path id | `200 message`; `403` | Super admin only |
| GET | `/api/v1/kontak` | `KontakController@index` | `api.token` | `search`, `per_page` | Paginator kontak | Role scoping differs by role |
| GET | `/api/v1/kontak/{id}` | `KontakController@show` | `api.token` | path id | Kontak JSON; `403` | Access via `canAccessKontak` |
| POST | `/api/v1/kontak` | `KontakController@store` | `api.token` | `nama`, `email`, `no_telp`, `pin`, `alamat`, `kode_kontak`, `diskon_persen`, `gudang_id` | `201 {message,data}`; `422` | Spectator mutation risk |
| PUT | `/api/v1/kontak/{id}` | `KontakController@update` | `api.token` | contact fields | `200 {message,data}`; `403`; `422` | Keep permission behavior |
| DELETE | `/api/v1/kontak/{id}` | `KontakController@destroy` | `api.token` | path id | `200 message`; `403` | Delete behavior must be tested |
| GET | `/api/v1/penjualan` | `PenjualanController@index` | `api.token` | `status`, `search`, `per_page` | Paginator penjualan with relations | Role-scoped |
| GET | `/api/v1/penjualan/{id}` | `PenjualanController@show` | `api.token` | path id | Penjualan with user,gudang,approver,items.produk | Adds phone fallback behavior |
| POST | `/api/v1/penjualan` | `PenjualanController@store` | `api.token` | header fields, `items[]`, lampiran | `201 {message,data}`; `403`; `422`; `500` | Stock check and price source critical |
| PUT | `/api/v1/penjualan/{id}` | `PenjualanController@update` | `api.token` | header/items or lampiran only | `200 {message,data}`; `403`; `422`; `500` | Cash update status quirk |
| POST | `/api/v1/penjualan/{id}/approve` | `PenjualanController@approve` | `api.token` | none | `200 {message,data}`; `403`; `422` | Pending only |
| POST | `/api/v1/penjualan/{id}/cancel` | `PenjualanController@cancel` | `api.token` | none | `200 {message,data}`; `403` | Status guard loose |
| POST | `/api/v1/penjualan/{id}/uncancel` | `PenjualanController@uncancel` | `api.token` | none | `200 {message,data}`; `403`; `422` | Super admin only |
| POST | `/api/v1/penjualan/{id}/mark-paid` | `PenjualanController@markAsPaid` | `api.token` | none | `200 {message,data}`; `403`; `422` | Approved only |
| POST | `/api/v1/penjualan/{id}/unmark-paid` | `PenjualanController@unmarkAsPaid` | `api.token` | none | `200 {message,data}`; `403`; `422` | Super admin only |
| GET | `/api/v1/pembelian` | `PembelianController@index` | `api.token` | `status`, `search`, `per_page` | Paginator pembelian | Role-scoped |
| GET | `/api/v1/pembelian/{id}` | `PembelianController@show` | `api.token` | path id | Pembelian with user,gudang,approver,items.produk | Eloquent shape |
| POST | `/api/v1/pembelian` | `PembelianController@store` | `api.token` | header fields, `items[]`, lampiran | `201 {message,data}`; `403`; `422`; `500` | Auto approver |
| PUT | `/api/v1/pembelian/{id}` | `PembelianController@update` | `api.token` | header/items or lampiran only | `200 {message,data}`; `403`; `422` | Super admin except lampiran owner |
| POST | `/api/v1/pembelian/{id}/approve` | `PembelianController@approve` | `api.token` | none | `200 {message,data}`; `403`; `422` | Pending only |
| POST | `/api/v1/pembelian/{id}/cancel` | `PembelianController@cancel` | `api.token` | none | `200 {message,data}`; `403` | Status guard loose |
| POST | `/api/v1/pembelian/{id}/uncancel` | `PembelianController@uncancel` | `api.token` | none | `200 {message,data}`; `403`; `422` | Super admin only |
| GET | `/api/v1/biaya` | `BiayaController@index` | `api.token` | `status`, `jenis`, `per_page` | Paginator biaya | Role/gudang scoping |
| GET | `/api/v1/biaya/{id}` | `BiayaController@show` | `api.token` | path id | Biaya with user,approver,items,gudang | Access broader than other tx |
| POST | `/api/v1/biaya` | `BiayaController@store` | `api.token` | `jenis_biaya`, `bayar_dari`, `penerima`, `tgl_transaksi`, `items[]`, tax/lampiran | `201 {message,data}`; `403`; `422`; `500` | Super admin creates Approved |
| PUT | `/api/v1/biaya/{id}` | `BiayaController@update` | `api.token` | biaya fields/items/lampiran | `200 {message,data}`; `403`; `422` | Super admin except lampiran owner |
| POST | `/api/v1/biaya/{id}/approve` | `BiayaController@approve` | `api.token` | none | `200 {message,data}`; `403`; `422` | Admin cannot reapprove approved |
| POST | `/api/v1/biaya/{id}/cancel` | `BiayaController@cancel` | `api.token` | none | `200 {message,data}`; `403`; `422` | Approved cancel super only |
| POST | `/api/v1/biaya/{id}/uncancel` | `BiayaController@uncancel` | `api.token` | none | `200 {message,data}`; `403`; `422` | Super admin only |
| GET | `/api/v1/kunjungan` | `KunjunganController@index` | `api.token` | `status`, `per_page` | Paginator kunjungan | Role-scoped |
| GET | `/api/v1/kunjungan/{id}` | `KunjunganController@show` | `api.token` | path id | Kunjungan with transformed items | Derived `kuantitas` and `tipe_stok` |
| POST | `/api/v1/kunjungan` | `KunjunganController@store` | `api.token` | `kontak_id`, sales fields, `tgl_kunjungan`, `tujuan`, `items[]`, lampiran | `201 {message,data}`; `403`; `422`; `500` | Promo stock validation |
| PUT | `/api/v1/kunjungan/{id}` | `KunjunganController@update` | `api.token` | fields/items/lampiran | `200 {message,data}`; `403`; `422` | Super admin except lampiran owner |
| POST | `/api/v1/kunjungan/{id}/approve` | `KunjunganController@approve` | `api.token` | none | `200 {message,data}`; `403`; `422` | Pending only |
| POST | `/api/v1/kunjungan/{id}/cancel` | `KunjunganController@cancel` | `api.token` | none | `200 {message,data}`; `403`; `422` | Admin only Pending |
| POST | `/api/v1/kunjungan/{id}/uncancel` | `KunjunganController@uncancel` | `api.token` | none | `200 {message,data}`; `403`; `422` | Super admin only |
| GET | `/api/v1/pembayaran` | `PembayaranController@index` | `api.token` | `per_page` | Paginator pembayaran with user,gudang,penjualan | Role-scoped |
| GET | `/api/v1/pembayaran/export-harian-pdf` | `PembayaranController@exportHarianPdf` | `api.token` | `tanggal` or range | PDF download; `422` | Filename/header |
| GET | `/api/v1/pembayaran/penjualan-by-gudang/{gudangId}` | `PembayaranController@getPenjualanByGudang` | `api.token` | path gudang | `200` list unpaid sales | Only Approved/Lunas with balance |
| GET | `/api/v1/pembayaran/penjualan-detail/{id}` | `PembayaranController@getPenjualanDetail` | `api.token` | path penjualan id | `200 {id,nomor,pelanggan,grand_total,gudang_id,items}` | Access scoped |
| GET | `/api/v1/pembayaran/{id}` | `PembayaranController@show` | `api.token` | path id | Pembayaran detail with relations | Route order protects special routes |
| POST | `/api/v1/pembayaran` | `PembayaranController@store` | `api.token` | `penjualan_id`, `tgl_pembayaran`, `metode_pembayaran`, `jumlah_bayar`, lampiran | `201 {message,data}`; `403`; `422`; `500` | Does not mark paid until approve |
| POST | `/api/v1/pembayaran/{id}/approve` | `PembayaranController@approve` | `api.token` | none | `200 {message,data}`; `403`; `422`; `500` | Can set penjualan Lunas |
| POST | `/api/v1/pembayaran/{id}/cancel` | `PembayaranController@cancel` | `api.token` | none | `200 {message,data}`; `403`; `422` | Approved cancel super only |
| POST | `/api/v1/pembayaran/{id}/uncancel` | `PembayaranController@uncancel` | `api.token` | none | `200 {message,data}`; `403`; `422` | Super admin only |
| GET | `/api/v1/penerimaan-barang/pembelian-by-gudang/{gudangId}` | `PenerimaanBarangController@getPembelianByGudang` | `api.token` | path gudang | `200` list pembelian with remaining qty | Status Approved/Pending |
| GET | `/api/v1/penerimaan-barang/pembelian-detail/{id}` | `PenerimaanBarangController@getPembelianDetail` | `api.token` | path pembelian id | `200 {id,nomor,tgl_transaksi,items}` | Includes qty_sisa |
| GET | `/api/v1/penerimaan-barang` | `PenerimaanBarangController@index` | `api.token` | `status`, `per_page` | Paginator penerimaan | Role-scoped |
| GET | `/api/v1/penerimaan-barang/{id}` | `PenerimaanBarangController@show` | `api.token` | path id | Penerimaan detail with relations | Access scoped |
| POST | `/api/v1/penerimaan-barang` | `PenerimaanBarangController@store` | `api.token` | `gudang_id`, `pembelian_id`, `tgl_penerimaan`, `items[]`, lampiran | `201 {message,data}`; `403`; `422`; `500` | Super admin direct Approved adds stock |
| POST | `/api/v1/penerimaan-barang/{id}/approve` | `PenerimaanBarangController@approve` | `api.token` | none | `200 {message,data}`; `403`; `422`; `500` | Adds stock |
| POST | `/api/v1/penerimaan-barang/{id}/cancel` | `PenerimaanBarangController@cancel` | `api.token` | none | `200 {message,data}`; `403`; `422`; `500` | Super cancel Approved subtracts stock |
| GET | `/api/v1/stok/log` | `StokController@log` | `api.token` | `gudang_id`, `produk_id`, date range, `per_page` | Paginator stok log | Admin/super only |
| GET | `/api/v1/stok` | `StokController@index` | `api.token` | `gudang_id` optional | `200` stok grouped by gudang | Super/admin/spectator |
| POST | `/api/v1/stok` | `StokController@store` | `api.token` | `gudang_id`, `produk_id`, `stok_penjualan`, `stok_gratis`, `stok_sample`, `keterangan` | `200 {message,data}`; `403`; `422` | Super admin only |
| GET | `/api/v1/users` | `UserController@index` | `api.token` | likely `search`, `per_page` | Paginator/list users | Super/admin management contract |
| GET | `/api/v1/users/{id}` | `UserController@show` | `api.token` | path id | User detail | Include role/gudang data |
| POST | `/api/v1/users` | `UserController@store` | `api.token` | user fields, role/gudang permissions | `201/200 {message,data}`; `403`; `422` | Preserve password/role rules |
| PUT | `/api/v1/users/{id}` | `UserController@update` | `api.token` | user fields, role/gudang permissions | `200 {message,data}`; `403`; `422` | Export/email flags |
| DELETE | `/api/v1/users/{id}` | `UserController@destroy` | `api.token` | path id | `200 message`; `403` | Deletion restrictions must be snapshotted |

## Response Shape per Modul

### Dashboard

`GET /dashboard` mengembalikan object scalar dan collections ringkas. Keys utama yang harus dipertahankan:
- `total_produk`
- `total_user`
- `total_gudang` atau `current_gudang`
- total/count penjualan, pembelian, pembayaran, biaya
- `biaya_masuk`, `biaya_keluar`
- `pending_approval`
- `recent_penjualan`
- `recent_kunjungan`
- `recent_activity`

`GET /dashboard/export/options` wajib mempertahankan keys:
- `role`
- `permissions.can_export_full_report`
- `permissions.can_export_pdf`
- `permissions.can_export_excel`
- `permissions.can_export_daily_pdf`
- `permissions.allowed_formats`
- `transaction_types`
- `status_filters`
- `biaya_jenis_filters`
- `tujuan_kunjungan_filters`
- `export_formats`
- `gudang_options`
- `sales_options`
- `defaults`

### Penjualan

Index:
- Laravel paginator JSON.
- Data memuat transaksi sesuai role.

Show:
- Header penjualan.
- Relations: `user`, `gudang`, `approver`, `items.produk`.
- Phone fallback: `no_telepon`, fallback field lama `email`, fallback `kontaks.no_telp` via nama pelanggan.

Store/update response:

```json
{
  "message": "Penjualan berhasil dibuat.",
  "data": {
    "id": 1,
    "uuid": "...",
    "nomor": "INV-...",
    "status": "Pending",
    "items": []
  }
}
```

Status penting:
- Create success `201`.
- Update/action success `200`.
- Stok tidak cukup `422 {"message":"Stok tidak mencukupi.","errors":[...]}`
- Spectator create/update `403`.
- Server transaction failure `500`.

### Pembelian

Index/show sama pola penjualan.

Store payload item memakai request `harga_satuan`; pembelian tidak mengubah stok langsung.

Store/update/action response berupa `{message,data}`. Create success `201`.

### Biaya

Index filters:
- `status`
- `jenis`
- `per_page`

Show relations:
- `user`
- `approver`
- `items`
- `gudang`

Store response message berbeda jika `super_admin` membuat biaya langsung approved. Test harus menyimpan exact message dari aplikasi lama.

### Kunjungan

Show response mentransformasi item:
- `kuantitas` diisi dari `jumlah`.
- `tipe_stok` diturunkan dari `tujuan`.
- `Promo Gratis` menjadi `gratis`.
- `Promo Sample` menjadi `sample`.
- `Pemeriksaan Stock` menjadi `penjualan`.

Validation:
- `items` wajib untuk `Pemeriksaan Stock`, `Promo Gratis`, `Promo Sample`.
- Promo gratis/sample memeriksa stok tipe terkait.

### Pembayaran

`GET /pembayaran/penjualan-by-gudang/{gudangId}` response item:

```json
{
  "id": 1,
  "nomor": "INV-...",
  "pelanggan": "Nama",
  "tgl_transaksi": "2026-01-01",
  "grand_total": 100000,
  "sisa_tagihan": 50000
}
```

`GET /pembayaran/penjualan-detail/{id}`:

```json
{
  "id": 1,
  "nomor": "INV-...",
  "pelanggan": "Nama",
  "grand_total": 100000,
  "gudang_id": 1,
  "items": [
    {
      "produk_id": 1,
      "nama_produk": "Produk",
      "kuantitas": 1,
      "harga_satuan": 100000
    }
  ]
}
```

Approval pembayaran dapat mengubah status penjualan menjadi `Lunas`.

### Penerimaan Barang

`GET /penerimaan-barang/pembelian-by-gudang/{gudangId}` mengembalikan pembelian yang masih punya sisa qty:
- `id`
- `nomor`
- `tgl_transaksi`
- `status`
- `total_items`

`GET /penerimaan-barang/pembelian-detail/{id}` response item:
- `produk_id`
- `nama_produk`
- `item_code`
- `qty_pesan`
- `qty_diterima`
- `qty_sisa`
- `satuan`

Approve penerimaan menambah stok; cancel approved oleh super admin mengurangi stok.

### Stok

`GET /stok` mengembalikan stok per gudang dan produk. Total `stok` dinormalisasi dari:

```text
stok_penjualan + stok_gratis + stok_sample
```

`POST /stok` menerima nilai absolut, bukan delta:
- `stok_penjualan`
- `stok_gratis`
- `stok_sample`

Jika total berubah, buat `stok_logs`.

### Print QR

`GET /print/{type}/{id}/qr`

Supported:
- `penjualan`
- `pembelian`
- `biaya`
- `kunjungan`
- `pembayaran`
- `penerimaan-barang`

Response:

```json
{
  "type": "penjualan",
  "id": 1,
  "uuid": "...",
  "receipt_url": "https://.../struk/penjualan/{uuid}",
  "invoice_url": "https://.../invoice/penjualan/{uuid}",
  "download_url": "https://.../invoice/penjualan/{uuid}/download",
  "qr_payload": "..."
}
```

### Print Bluetooth

`GET /print/{type}/{id}/bluetooth`

Supported:
- `penjualan`
- `pembelian`
- `biaya`
- `kunjungan`

Unsupported:
- `pembayaran`
- `penerimaan-barang`
- unknown type

Unsupported response:

```json
{
  "message": "Tipe transaksi tidak didukung untuk Bluetooth print.",
  "supported_types": ["penjualan", "pembelian", "biaya", "kunjungan"]
}
```

Status: `400`.

Bluetooth penjualan keys:
- `nomor`, `tanggal`, `jatuh_tempo`, `pembayaran`, `pelanggan`, `no_telepon`, `alamat_penagihan`, `tipe_harga`, `no_referensi`, `tag`, `koordinat`, `memo`, `sales`, `sales_no_telp`, `approver`, `gudang`, `status`, `items`, `subtotal`, `diskon_akhir`, `tax_percentage`, `pajak`, `grand_total`, `invoice_url`.

Bluetooth pembelian keys:
- `nomor`, `tanggal`, `jatuh_tempo`, `pembayaran`, `vendor`, `urgensi`, `tahun_anggaran`, `staf_penyetuju`, `memo`, `sales`, `approver`, `gudang`, `status`, `items`, `subtotal`, `diskon_akhir`, `tax_percentage`, `pajak`, `grand_total`, `invoice_url`.

Bluetooth biaya keys:
- `nomor`, `tanggal`, `jenis_biaya`, `cara_pembayaran`, `bayar_dari`, `penerima`, `alamat_penagihan`, `tag`, `koordinat`, `memo`, `gudang`, `sales`, `approver`, `status`, `items`, `subtotal`, `tax_percentage`, `pajak`, `grand_total`, `invoice_url`.

Bluetooth kunjungan keys:
- `nomor`, `tanggal`, `waktu`, `tujuan`, `sales_nama`, `sales_no_telepon`, `sales_alamat`, `pembuat`, `approver`, `gudang`, `status`, `koordinat`, `memo`, `items`, `invoice_url`.

## Permission Matrix API Ringkas

| Modul | super_admin | admin | spectator | user |
| --- | --- | --- | --- | --- |
| Auth/profile | own | own | own | own |
| Gudang list/switch | all | assigned | assigned | own |
| Produk read | all | current gudang | current gudang | own gudang |
| Produk mutate | yes | no | no | no |
| Kontak read | all | assigned/null | all by index | own/legacy |
| Kontak mutate | likely yes | likely yes scoped | risk: test old behavior | likely scoped |
| Penjualan create | yes | current gudang | no | own gudang |
| Penjualan approve/mark paid | yes | current gudang | no | no |
| Penjualan uncancel/unmark paid | yes | no | no | no |
| Pembelian create | yes | current gudang | no | own gudang |
| Biaya create | direct approved | pending | no | pending |
| Kunjungan create | direct/normal per code | current gudang | no | own/current |
| Pembayaran create | yes | current gudang | no | own access |
| Penerimaan create | direct approved | current gudang | no | own access |
| Stok update | yes | no | no | no |
| Report export | yes | permission flags | no | no |

## Contract Test Priority

Highest risk endpoints for mobile compatibility:
1. `POST /login`, all protected unauthenticated cases.
2. `GET /profile`.
3. Resource index paginators for `produk`, `kontak`, `penjualan`, `pembelian`, `biaya`, `kunjungan`, `pembayaran`, `penerimaan-barang`.
4. Transaction create/update payloads and `{message,data}` shapes.
5. Approval/cancel status transition status codes.
6. Stok check side effects for penjualan, kunjungan promo, penerimaan barang, and manual stok.
7. Payment approval side effect to penjualan `Lunas`.
8. QR/Bluetooth response keys and unsupported type status.
9. Export/download endpoints headers and filenames.
