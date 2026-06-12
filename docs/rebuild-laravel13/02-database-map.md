# Database Map

Scope: mapping dibuat dari `database/migrations/*.php` dan model Eloquent lama. Audit ini tidak melakukan introspeksi database production. Sebelum rebuild menulis migration final, cocokkan dokumen ini dengan dump schema/read-only database production.

## Daftar Tabel

| Tabel | Modul | Catatan |
| --- | --- | --- |
| `users` | Auth, role, sales/admin | User web/API, role, gudang aktif, permission export |
| `password_resets` | Auth web | Reset password Laravel lama |
| `personal_access_tokens` | Auth API mobile | Bearer token custom SHA-256 |
| `gudangs` | Gudang | Master gudang |
| `admin_gudang` | Role/gudang | Pivot admin ke banyak gudang |
| `spectator_gudang` | Role/gudang | Pivot spectator ke banyak gudang |
| `produks` | Produk | Master produk, harga, satuan |
| `gudang_produk` | Stok | Stok per gudang/produk plus tipe stok |
| `stok_logs` | Stok log | Audit stok manual/stock movement snapshot |
| `kontaks` | Kontak/customer | Customer/vendor/contact, PIN portal |
| `penjualans` | Penjualan | Header transaksi penjualan |
| `penjualan_items` | Penjualan | Detail item penjualan |
| `pembelians` | Pembelian | Header transaksi pembelian |
| `pembelian_items` | Pembelian | Detail item pembelian |
| `biayas` | Biaya | Header biaya masuk/keluar |
| `biaya_items` | Biaya | Detail item biaya |
| `kunjungans` | Kunjungan | Header kunjungan sales/customer |
| `kunjungan_items` | Kunjungan | Detail produk kunjungan/promo |
| `pembayarans` | Pembayaran | Pembayaran atas penjualan |
| `penerimaan_barangs` | Penerimaan barang | Header penerimaan pembelian |
| `penerimaan_barang_items` | Penerimaan barang | Detail stok masuk/reject |

## Auth dan User

### `users`

Kolom penting:
- `id`
- `name`
- `email` unique
- `email_verified_at`
- `password`
- `role` default `user`
- `alamat`
- `no_telp`
- `avatar`
- `gudang_id` nullable FK ke `gudangs.id`
- `current_gudang_id` nullable
- `receives_transaction_email` boolean default true
- `can_export_pdf` boolean default false
- `can_export_excel` boolean default false
- `remember_token`
- `created_at`, `updated_at`

Relasi:
- `users.gudang_id` belongs to `gudangs.id`.
- `admin_gudang.user_id` pivot many-to-many admin.
- `spectator_gudang.user_id` pivot many-to-many spectator.
- Semua transaksi header memakai `user_id`; banyak transaksi juga memakai `approver_id`.

Index:
- `email` unique dari migration create users.
- Index tambahan: `role`, `gudang_id`.

Enum/status:
- `role`: `super_admin`, `admin`, `spectator`, `user`.

### `password_resets`

Kolom:
- `email` indexed
- `token`
- `created_at`

Catatan:
- Tabel bawaan Laravel 7. Laravel 13 skeleton baru biasanya memakai `password_reset_tokens`; jika memakai DB lama, pertahankan kompatibilitas atau migrasikan dengan adapter.

### `personal_access_tokens`

Kolom:
- `id` big increments
- `user_id` FK cascade ke `users.id`
- `name`
- `token` string(64) unique, berisi SHA-256 dari plain bearer token
- `last_used_at` nullable datetime
- `expires_at` nullable datetime
- `created_at`, `updated_at`

Relasi:
- belongs to `users`.

Index:
- `token` unique
- `user_id` index/FK

Kompatibilitas:
- Ini bukan Sanctum walau nama tabel mirip. Jangan ubah hash atau token lifetime tanpa adapter.

## Gudang, Produk, Stok

### `gudangs`

Kolom:
- `id`
- `nama_gudang`
- `alamat_gudang` nullable
- `created_at`, `updated_at`

Relasi:
- has many `users`
- has many `gudang_produk`
- referenced by transaction headers.

### `admin_gudang`

Kolom:
- `id`
- `user_id` FK cascade
- `gudang_id` FK cascade
- `created_at`, `updated_at`

Index/constraint:
- unique composite `user_id`, `gudang_id`.

### `spectator_gudang`

Kolom dan constraint sama seperti `admin_gudang`, untuk role `spectator`.

### `produks`

Kolom:
- `id`
- `nama_produk`
- `item_code` nullable unique
- `harga` decimal(15,2)
- `harga_grosir` decimal(15,2) default 0
- `satuan` default `Pcs`
- `deskripsi` nullable
- `created_at`, `updated_at`

Index:
- `item_code` unique
- `nama_produk` index

Enum/status:
- `satuan`: `Pcs`, `Lusin`, `Karton` berdasarkan validasi controller.

### `gudang_produk`

Kolom:
- `id`
- `gudang_id` FK cascade
- `produk_id` FK cascade
- `stok` integer default 0
- `stok_penjualan` integer default 0
- `stok_gratis` integer default 0
- `stok_sample` integer default 0

Relasi:
- belongs to `gudangs`
- belongs to `produks`

Index/constraint:
- unique composite `gudang_id`, `produk_id`
- index composite `gudang_id`, `stok`

Catatan:
- Model `GudangProduk` menonaktifkan timestamps.
- `stok` adalah legacy total. API stok menampilkan total dari tiga kolom tipe stok.

### `stok_logs`

Kolom:
- `id`
- `gudang_produk_id` nullable FK
- `produk_id` FK
- `gudang_id` FK
- `user_id` FK
- `produk_nama`
- `gudang_nama`
- `user_nama`
- `stok_sebelum`
- `stok_sesudah`
- `selisih`
- `keterangan`
- `created_at`, `updated_at`

Relasi:
- belongs to `GudangProduk`, `Produk`, `Gudang`, `User`.

Catatan:
- Snapshot nama disimpan supaya log tetap terbaca jika master berubah.

## Kontak dan Customer Portal

### `kontaks`

Kolom:
- `id`
- `kode_kontak` nullable/auto `KT00001`
- `nama`
- `email` nullable
- `no_telp` nullable
- `pin` string(6) nullable
- `alamat` nullable
- `diskon_persen` decimal default 0
- `gudang_id` nullable FK set null
- `created_by` nullable FK `users.id` set null
- `created_at`, `updated_at`

Relasi:
- belongs to `gudangs`
- belongs to creator `users`
- has many `kunjungans`

Index:
- `nama`
- `email`

Kompatibilitas:
- Customer portal login berbasis `no_telp` dan `pin`, bukan tabel user.
- Penjualan customer lama dicari dari `penjualans.pelanggan = kontaks.nama`.

## Transaksi Penjualan

### `penjualans`

Kolom penting:
- `id`
- `uuid` unique
- `user_id` FK
- `gudang_id` FK
- `approver_id` nullable FK users
- `no_urut_harian`
- `nomor` indexed
- `tipe_harga` default `retail`
- `pelanggan`
- `no_telepon` nullable, hasil rename dari field lama `email`
- `alamat_penagihan`
- `tgl_transaksi`
- `syarat_pembayaran`
- `tgl_jatuh_tempo`
- `no_referensi`
- `tag`
- `koordinat`
- `memo`
- `lampiran_path`
- `lampiran_paths` JSON/text cast array
- `status` default `Pending`
- `diskon_akhir`
- `tax_percentage`
- `grand_total`
- `created_at`, `updated_at`

Relasi:
- belongs to `users` as creator
- belongs to `users` as approver
- belongs to `gudangs`
- has many `penjualan_items`
- has many `pembayarans`

Index:
- `status`
- `tgl_transaksi`
- `user_id`
- `approver_id`
- `gudang_id`
- `tgl_jatuh_tempo`
- `created_at`
- composite `user_id`, `status`
- composite `approver_id`, `status`
- `nomor` index

Enum/status:
- `status`: `Pending`, `Approved`, `Canceled`, `Lunas`.
- `tipe_harga`: `retail`, `grosir`.
- `syarat_pembayaran`: controller menangani `Cash`, `Net 7`, `Net 14`, `Net 30`, `Net 60`.

### `penjualan_items`

Kolom:
- `id`
- `penjualan_id` FK cascade
- `produk_id` FK
- `deskripsi`
- `kuantitas`
- `unit`
- `harga_satuan`
- `diskon`
- `diskon_nominal` decimal(15,2) default 0
- `batch_number`
- `expired_date`
- `jumlah_baris`

Relasi:
- belongs to `penjualans`
- belongs to `produks`

Index:
- `produk_id`

Catatan:
- Model menonaktifkan timestamps.
- `expired_date` cast date.

## Transaksi Pembelian

### `pembelians`

Kolom penting:
- `id`
- `uuid` unique
- `user_id` FK
- `gudang_id` FK
- `approver_id` nullable FK users
- `no_urut_harian`
- `nomor` indexed
- `staf_penyetuju`
- `email_penyetuju`
- `tgl_transaksi`
- `syarat_pembayaran`
- `tgl_jatuh_tempo`
- `urgensi`
- `tahun_anggaran`
- `tag`
- `koordinat`
- `memo`
- `lampiran_path`
- `lampiran_paths` JSON/text cast array
- `status` default `Pending`
- `diskon_akhir`
- `tax_percentage`
- `grand_total`
- `created_at`, `updated_at`

Relasi:
- belongs to creator `users`
- belongs to approver `users`
- belongs to `gudangs`
- has many `pembelian_items`
- has many `penerimaan_barangs`

Index:
- `status`
- `tgl_transaksi`
- `user_id`
- `approver_id`
- `gudang_id`
- `tgl_jatuh_tempo`
- `urgensi`
- `created_at`
- composite `user_id`, `status`
- composite `approver_id`, `status`
- `nomor` index

Enum/status:
- `status`: `Pending`, `Approved`, `Canceled`; model display juga menangani `Lunas`.
- `syarat_pembayaran`: sama seperti penjualan.

### `pembelian_items`

Kolom:
- `id`
- `pembelian_id` FK cascade
- `produk_id` FK
- `deskripsi`
- `kuantitas`
- `unit`
- `harga_satuan`
- `diskon`
- `jumlah_baris`

Relasi:
- belongs to `pembelians`
- belongs to `produks`

Index:
- `produk_id`

Catatan:
- Model menonaktifkan timestamps.

## Biaya

### `biayas`

Kolom penting:
- `id`
- `uuid` unique
- `user_id` FK
- `gudang_id` nullable FK set null
- `approver_id` nullable FK users
- `no_urut_harian`
- `jenis_biaya`
- `nomor` indexed
- `bayar_dari`
- `penerima`
- `alamat_penagihan`
- `tgl_transaksi`
- `cara_pembayaran`
- `tag`
- `koordinat`
- `memo`
- `lampiran_path`
- `lampiran_paths` JSON/text cast array
- `status` default `Pending`
- `tax_percentage`
- `grand_total`
- `created_at`, `updated_at`

Relasi:
- belongs to creator `users`
- belongs to approver `users`
- belongs to `gudangs`
- has many `biaya_items`

Index:
- `status`
- `tgl_transaksi`
- `user_id`
- `approver_id`
- `created_at`
- composite `user_id`, `status`
- composite `approver_id`, `status`
- `nomor` index

Enum/status:
- `status`: `Pending`, `Approved`, `Canceled`.
- `jenis_biaya`: `masuk`, `keluar`.

### `biaya_items`

Kolom:
- `id`
- `biaya_id` FK cascade
- `kategori`
- `deskripsi`
- `jumlah`

Relasi:
- belongs to `biayas`

Index:
- `kategori`

Catatan:
- Migration awal memiliki timestamps, tetapi model menonaktifkan timestamps. Verifikasi real schema production.

## Kunjungan

### `kunjungans`

Kolom penting:
- `id`
- `uuid` unique
- `user_id` FK
- `approver_id` nullable FK users
- `gudang_id` nullable FK
- `kontak_id` nullable FK
- `no_urut_harian`
- `nomor` indexed
- `sales_nama`
- `sales_no_telepon`, hasil rename dari `sales_email`
- `sales_alamat`
- `tgl_kunjungan`
- `tujuan`
- `koordinat`
- `memo`
- `lampiran_path`
- `lampiran_paths` JSON/text cast array
- `status` default `Pending`
- `created_at`, `updated_at`

Relasi:
- belongs to creator `users`
- belongs to approver `users`
- belongs to `gudangs`
- belongs to `kontaks`
- has many `kunjungan_items`

Index:
- `nomor` index

Enum/status:
- `status`: `Pending`, `Approved`, `Canceled`.
- `tujuan`: API create/update membatasi `Pemeriksaan Stock`, `Penagihan`, `Promo Gratis`, `Promo Sample`.

### `kunjungan_items`

Kolom:
- `id`
- `kunjungan_id` FK cascade
- `produk_id` FK
- `jumlah`
- `batch_number`
- `expired_date`
- `keterangan`
- timestamps sesuai migration lama

Relasi:
- belongs to `kunjungans`
- belongs to `produks`

Catatan:
- `expired_date` cast date.

## Pembayaran

### `pembayarans`

Kolom penting:
- `id`
- `uuid` unique
- `user_id` FK
- `approver_id` nullable FK users
- `gudang_id` FK
- `penjualan_id` FK
- `no_urut_harian`
- `nomor`
- `tgl_pembayaran`
- `metode_pembayaran`
- `jumlah_bayar`
- `bukti_bayar`
- `lampiran_paths` text cast array
- `keterangan`
- `status` default `Pending`
- `created_at`, `updated_at`

Relasi:
- belongs to creator `users`
- belongs to approver `users`
- belongs to `gudangs`
- belongs to `penjualans`

Index:
- `gudang_id`
- `penjualan_id`
- `status`
- `tgl_pembayaran`

Enum/status:
- `status`: `Pending`, `Approved`, `Canceled`.

## Penerimaan Barang

### `penerimaan_barangs`

Kolom penting:
- `id`
- `uuid` unique
- `user_id` FK
- `approver_id` nullable FK users
- `gudang_id` FK
- `pembelian_id` FK
- `no_urut_harian`
- `nomor`
- `tgl_penerimaan`
- `no_surat_jalan`
- `lampiran_paths` text cast array
- `keterangan`
- `status` default `Pending`
- `created_at`, `updated_at`

Relasi:
- belongs to creator `users`
- belongs to approver `users`
- belongs to `gudangs`
- belongs to `pembelians`
- has many `penerimaan_barang_items`

Index:
- `gudang_id`
- `pembelian_id`
- `status`
- `tgl_penerimaan`

Enum/status:
- `status`: `Pending`, `Approved`, `Canceled`.

### `penerimaan_barang_items`

Kolom:
- `id`
- `penerimaan_barang_id` FK cascade
- `produk_id` FK
- `qty_diterima`
- `qty_reject` default 0
- `tipe_stok` default `penjualan`
- `batch_number`
- `expired_date`
- `keterangan`
- `created_at`, `updated_at`

Relasi:
- belongs to `penerimaan_barangs`
- belongs to `produks`

Enum/status:
- `tipe_stok`: `penjualan`, `gratis`, `sample`.

## JSON/Text Cast Columns

| Tabel | Kolom | Cast lama | Catatan |
| --- | --- | --- | --- |
| `penjualans` | `lampiran_paths` | array | Multi lampiran transaksi |
| `pembelians` | `lampiran_paths` | array | Multi lampiran transaksi |
| `biayas` | `lampiran_paths` | array | Multi lampiran transaksi |
| `kunjungans` | `lampiran_paths` | array | Multi lampiran transaksi |
| `pembayarans` | `lampiran_paths` | array | Migration memakai text, model cast array |
| `penerimaan_barangs` | `lampiran_paths` | array | Migration memakai text, model cast array |

Catatan:
- Migration lama mungkin memakai `json` pada sebagian tabel dan `text` pada tabel baru. Laravel 13 harus mempertahankan decoding/encoding lama, bukan memaksa tipe baru tanpa migrasi data.

## Enum dan Status Global

| Domain | Nilai |
| --- | --- |
| User role | `super_admin`, `admin`, `spectator`, `user` |
| Status transaksi umum | `Pending`, `Approved`, `Canceled` |
| Status penjualan tambahan | `Lunas` |
| Filter report legacy | `Rejected` disebut pada export options |
| Syarat pembayaran | `Cash`, `Net 7`, `Net 14`, `Net 30`, `Net 60` |
| Tipe harga penjualan | `retail`, `grosir` |
| Satuan produk | `Pcs`, `Lusin`, `Karton` |
| Jenis biaya | `masuk`, `keluar` |
| Tujuan kunjungan API | `Pemeriksaan Stock`, `Penagihan`, `Promo Gratis`, `Promo Sample` |
| Tipe stok penerimaan | `penjualan`, `gratis`, `sample` |

## Index dan Constraint Penting

Harus dipertahankan atau diverifikasi ulang pada schema production:
- `users.email` unique.
- `produks.item_code` unique nullable.
- `gudang_produk` unique composite `gudang_id`, `produk_id`.
- `admin_gudang` unique composite `user_id`, `gudang_id`.
- `spectator_gudang` unique composite `user_id`, `gudang_id`.
- `personal_access_tokens.token` unique.
- `uuid` pada transaksi public invoice harus unique: `penjualans`, `pembelians`, `biayas`, `kunjungans`, `pembayarans`, `penerimaan_barangs`.
- Index transaksi untuk status, tanggal, user, approver, gudang, created_at.
- Index lookup pembayaran/penerimaan pada `gudang_id`, `penjualan_id`/`pembelian_id`, `status`, dan tanggal.

Peringatan:
- Dokumen lama `DATABASE_INDEXES.md` menyebut beberapa unique constraint nomor transaksi, tetapi migration `add_nomor_to_transactions_tables.php` yang dibaca memakai index. Jangan menambahkan unique pada nomor tanpa membuktikan schema production dan data existing bebas duplikat.

## Relasi Utama

Relasi master:
- `User` belongs to `Gudang`.
- `User` belongs to many `Gudang` melalui `admin_gudang` untuk admin.
- `User` belongs to many `Gudang` melalui `spectator_gudang` untuk spectator.
- `Gudang` has many `GudangProduk`.
- `GudangProduk` belongs to `Gudang` dan `Produk`.

Relasi transaksi:
- `Penjualan` belongs to `User`, `Approver`, `Gudang`; has many `PenjualanItem`; has many `Pembayaran`.
- `PenjualanItem` belongs to `Penjualan` dan `Produk`.
- `Pembelian` belongs to `User`, `Approver`, `Gudang`; has many `PembelianItem`; has many `PenerimaanBarang`.
- `PembelianItem` belongs to `Pembelian` dan `Produk`.
- `Biaya` belongs to `User`, `Approver`, `Gudang`; has many `BiayaItem`.
- `Kunjungan` belongs to `User`, `Approver`, `Gudang`, `Kontak`; has many `KunjunganItem`.
- `Pembayaran` belongs to `User`, `Approver`, `Gudang`, `Penjualan`.
- `PenerimaanBarang` belongs to `User`, `Approver`, `Gudang`, `Pembelian`; has many `PenerimaanBarangItem`.
- `PenerimaanBarangItem` belongs to `PenerimaanBarang` dan `Produk`.

## Rebuild Schema Strategy

1. Ambil dump schema production read-only sebelum menulis migration Laravel 13 final.
2. Buat migration Laravel 13 yang merepresentasikan schema aktual, bukan hanya migration source lama.
3. Jangan rename table/column pada tahap compatibility.
4. Pertahankan nullable/default lama agar insert lama dan mobile payload lama tidak pecah.
5. Tambahkan constraint baru hanya setelah data production diverifikasi.
6. Pisahkan improvement schema seperti foreign key tambahan, normalized customer id, atau enum database ke fase pasca compatibility.
