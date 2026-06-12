# Test Plan Kontrak API dan Rebuild Laravel 13

Tujuan test plan:
- Mengunci behavior Laravel 7 lama sebelum porting.
- Membuktikan Laravel 13 baru kompatibel dengan mobile API lama.
- Mencegah perubahan business rule transaksi tanpa bukti.
- Memberi urutan test yang bisa dijalankan per modul rebuild.

Catatan: test ini harus dibuat sebelum porting logic modul terkait. Jika environment Laravel 7 lama tidak bisa berjalan di mesin PHP modern, jalankan baseline capture pada environment yang kompatibel atau staging clone read-only/non-production.

## Prinsip Test

- Test response JSON berdasarkan snapshot endpoint lama.
- Test status code, bukan hanya body.
- Test role/gudang scoping untuk setiap resource.
- Test side effect database untuk stok, pembayaran, approval, cancel.
- Test file/export endpoint untuk content type dan filename.
- Jangan pakai database production untuk operasi tulis.
- Fixture test harus non-production dan deterministik.

## Fixture Minimum

Buat dataset test/staging dengan:
- 1 `super_admin`.
- 1 `admin` dengan akses ke gudang A dan B.
- 1 `spectator` dengan akses ke gudang A.
- 1 `user` sales gudang A.
- 1 `user` sales gudang B.
- Gudang A dan B.
- Produk retail/grosir dengan `item_code`, `satuan`, `harga`, `harga_grosir`.
- `gudang_produk` dengan stok:
  - `stok_penjualan`
  - `stok_gratis`
  - `stok_sample`
  - `stok` legacy total.
- Kontak dengan `no_telp`, `pin`, `diskon_persen`, `gudang_id`, `created_by`.
- Penjualan Pending, Approved, Canceled, Lunas.
- Pembelian Pending, Approved, Canceled.
- Biaya Pending, Approved, Canceled, jenis `masuk` dan `keluar`.
- Kunjungan Pending/Approved/Canceled untuk semua `tujuan`.
- Pembayaran Pending/Approved/Canceled dan penjualan dengan sisa tagihan.
- Penerimaan barang Pending/Approved/Canceled.
- Lampiran dummy yang aman di storage test.

## Baseline Capture

Untuk setiap endpoint API lama:
1. Request dengan token `super_admin`, `admin`, `spectator`, dan `user` jika relevan.
2. Simpan method, URL, query/body, status code, selected headers, dan JSON body.
3. Mask nilai dinamis seperti token, timestamp, UUID, URL host, dan generated id.
4. Simpan snapshot sebagai fixture contract test Laravel 13.

Output baseline yang disarankan:
- `tests/Contracts/fixtures/api-v1/*.json`
- `tests/Contracts/fixtures/files/*.meta.json`
- `tests/Contracts/fixtures/print/*.json`

## Auth API Tests

| Case | Endpoint | Expected |
| --- | --- | --- |
| Login valid | `POST /api/v1/login` | `200`, keys `message`, `token`, `user` |
| Login invalid email/password | `POST /api/v1/login` | `401 {"message":"Email atau password salah."}` |
| Login validation | `POST /api/v1/login` | `422` Laravel validation shape |
| Missing bearer | protected endpoint | `401 {"message":"Unauthenticated."}` |
| Invalid bearer | protected endpoint | `401 {"message":"Token invalid atau sudah expired."}` |
| Expired token | protected endpoint | `401 {"message":"Token invalid atau sudah expired."}` |
| Logout | `POST /api/v1/logout` | token row deleted, `200` message |
| Profile | `GET /api/v1/profile` | `200 {user,gudang}` |
| Update profile | `PUT /api/v1/profile` | `200 {message,user}` |
| Change password valid | `POST /api/v1/change-password` | `200` message and password changed |
| Change password wrong old | `POST /api/v1/change-password` | `422` message |
| Avatar file upload | `POST /api/v1/profile/avatar` | `200`, file exists, `avatar_url` |
| Avatar base64 upload | `POST /api/v1/profile/avatar` | `200`, file exists |
| Delete avatar | `DELETE /api/v1/profile/avatar` | `200`, avatar null/file removed |

## Role and Gudang Scoping Tests

Wajib untuk setiap modul list/detail:
- `super_admin` melihat semua gudang.
- `admin` hanya data current/assigned gudang.
- `spectator` hanya data assigned/current gudang untuk modul yang scoped, atau sesuai behavior lama jika index kontak melihat semua.
- `user` hanya gudang sendiri atau transaksi sendiri.
- Admin/spectator tidak bisa akses gudang yang tidak assigned.
- `POST /api/v1/gudang/switch` mengubah `current_gudang_id` dan memengaruhi list berikutnya.

## Master Data Tests

### Gudang

- `GET /api/v1/gudang` per role.
- `POST /api/v1/gudang/switch` valid/invalid.
- `GET /api/v1/gudang/stok`.
- `GET /api/v1/gudang/stok-log`.
- `GET /api/v1/gudang/stok/export` headers XLSX.

### Produk

- `GET /api/v1/produk` search, pagination, role scoping.
- `GET /api/v1/produk/{id}` relation `stokDiGudang.gudang`.
- `GET /api/v1/produk/stok/{gudangId}` access allowed/forbidden.
- `POST/PUT/DELETE /api/v1/produk`:
  - super admin allowed.
  - admin/spectator/user forbidden.
  - validation for `satuan`, `harga`, `item_code`.

### Kontak

- `GET /api/v1/kontak` search, pagination, role scoping.
- `GET /api/v1/kontak/{id}` access allowed/forbidden.
- `POST /api/v1/kontak` creates `kode_kontak` if empty and sets `created_by`.
- `PUT /api/v1/kontak/{id}` updates contact and PIN.
- `DELETE /api/v1/kontak/{id}` behavior for each role.
- Explicit compatibility test for spectator mutation because controller behavior may not match read-only intent.

## Stok Tests

| Case | Expected |
| --- | --- |
| `GET /api/v1/stok` super admin | all/selected gudang returned |
| `GET /api/v1/stok` admin/spectator | current/assigned gudang only |
| `GET /api/v1/stok` user | forbidden or old behavior snapshot |
| `POST /api/v1/stok` super admin | updates absolute stok values |
| `POST /api/v1/stok` non-super | `403` |
| Stok total | response `stok = stok_penjualan + stok_gratis + stok_sample` |
| StokLog created | only when total delta changes |
| `GET /api/v1/stok/log` filters | gudang, produk, date range, pagination |

## Penjualan Contract Tests

Create payload cases:
- Retail price uses `produks.harga`.
- Grosir price uses `produks.harga_grosir`.
- Request `harga_satuan` does not override old price behavior if old controller ignores it.
- `diskon` percent and `diskon_nominal`.
- `Cash`, `Net 7`, `Net 14`, `Net 30`, `Net 60`.
- Lampiran file and `lampiran_paths`.
- Stok cukup and stok tidak cukup.

Status/action cases:
- Pending -> approve -> Approved.
- Approved -> mark-paid -> Lunas.
- Lunas -> unmark-paid by super admin -> Approved.
- Canceled -> uncancel by super admin -> Pending.
- Cancel behavior for owner user, admin current gudang, and forbidden roles.
- Admin cannot approve outside current/assigned gudang.
- Spectator cannot create/update/action.

Response assertions:
- Index paginator shape.
- Show relation keys.
- Store `201 {message,data}`.
- Action `200 {message,data}`.
- Stok insufficient `422 {"message":"Stok tidak mencukupi.","errors":[...]}`.
- Server error paths not forced, but transaction rollback should be tested with simulated exception if practical.

Side effects:
- `no_urut_harian` and `nomor` generated.
- `uuid` generated.
- `tgl_jatuh_tempo` calculated for Net terms.
- `approver_id` set on approval/uncancel as old behavior.
- Stock is not silently changed unless old code does so.

## Pembelian Contract Tests

Create/update:
- Required header fields and `items[]`.
- Item `harga_satuan` from payload.
- `diskon`, `diskon_akhir`, `tax_percentage`, `grand_total`.
- `urgensi`, `tahun_anggaran`, `tag`, `koordinat`, `memo`.
- Auto approver by role/gudang.
- Lampiran-only update by owner.

Actions:
- Pending -> approve -> Approved.
- Cancel by allowed roles.
- Canceled -> uncancel by super admin.
- Admin forbidden outside current gudang.
- Spectator forbidden mutation.

Side effects:
- Pembelian approval does not add stock.
- Penerimaan barang later reads remaining qty correctly.

## Biaya Contract Tests

Create/update:
- `jenis_biaya` `masuk` and `keluar`.
- `bayar_dari`, `penerima`, `cara_pembayaran`.
- Items kategori/deskripsi/jumlah.
- Tax and grand total.
- Super admin direct approved behavior.
- Admin/user pending behavior.

Actions:
- Approve pending by admin/super.
- Admin cannot reapprove approved.
- Cancel pending by admin/super.
- Cancel approved forbidden for admin, allowed for super.
- Uncancel only super.

Access:
- Test gudang nullable behavior.
- Test broader role access exactly per old baseline.

## Kunjungan Contract Tests

Create/update:
- `tujuan = Pemeriksaan Stock`: items required; derived `tipe_stok = penjualan`.
- `tujuan = Penagihan`: items nullable.
- `tujuan = Promo Gratis`: items required; checks `stok_gratis`.
- `tujuan = Promo Sample`: items required; checks `stok_sample`.
- Batch/expired fields.
- `kontak_id`, sales fields, coordinates, memo, lampiran.

Show:
- Items include transformed `kuantitas`.
- Items include derived `tipe_stok`.

Actions:
- Pending -> Approved.
- Cancel allowed/forbidden by role/status.
- Canceled -> Pending by super admin.

## Pembayaran Contract Tests

Lookup:
- `GET /pembayaran/penjualan-by-gudang/{gudangId}` returns only Approved/Lunas sales with positive `sisa_tagihan`.
- `GET /pembayaran/penjualan-detail/{id}` returns item shape with produk info.
- Access forbidden outside gudang.

Create:
- Pending payment created for penjualan allowed.
- Reject invalid/forbidden penjualan.
- Lampiran paths.

Actions:
- Approve payment updates payment status.
- If approved payment sum >= grand total, penjualan becomes `Lunas`.
- If partial, penjualan remains `Approved`.
- Cancel approved by super admin can move penjualan from `Lunas` back to `Approved`.
- Admin cannot cancel approved if old behavior forbids.
- Uncancel only super.

Export:
- `GET /pembayaran/export-harian-pdf` validates date params.
- Header content type PDF and filename `Tagihan-Invoice-...pdf`.

## Penerimaan Barang Contract Tests

Lookup:
- `GET /penerimaan-barang/pembelian-by-gudang/{gudangId}` includes pembelian with remaining qty.
- `GET /penerimaan-barang/pembelian-detail/{id}` includes `qty_pesan`, `qty_diterima`, `qty_sisa`, `satuan`.

Create:
- Reject if `gudang_id` does not match pembelian.
- Items with `qty_diterima`, `qty_reject`, `tipe_stok`, batch/expired.
- Super admin direct approved adds stock.
- Admin/user pending behavior if old code allows.

Actions:
- Approve pending adds legacy `stok` and selected `stok_penjualan`/`stok_gratis`/`stok_sample`.
- Cancel pending sets Canceled.
- Cancel approved by super admin subtracts stock with old max-zero behavior.
- Admin forbidden cancel approved.

## Dashboard and Report Tests

Dashboard:
- `GET /dashboard` keys and role-scoped numeric values.
- `GET /dashboard/daily-report` date default and explicit date.
- `GET /dashboard/daily-report/pdf` PDF headers.

Export:
- `GET /dashboard/export/options` exact keys:
  - role
  - permissions
  - transaction_types
  - status_filters
  - biaya_jenis_filters
  - tujuan_kunjungan_filters
  - export_formats
  - gudang_options
  - sales_options
  - defaults
- `POST /dashboard/export` PDF and Excel.
- Super admin all formats.
- Admin only if `can_export_pdf`/`can_export_excel`.
- User/spectator forbidden.

Lampiran:
- Valid allowed path downloads.
- `../` traversal rejected.
- Invalid prefix rejected.
- Missing file returns 404.

## Print, QR, Public Invoice, Bluetooth Tests

QR API:
- Supported types return:
  - `type`
  - `id`
  - `uuid`
  - `receipt_url`
  - `invoice_url`
  - `download_url`
  - `qr_payload`
- Unknown type returns old baseline status/body.
- Access check by owner/admin/spectator/super.

Bluetooth API:
- `penjualan`, `pembelian`, `biaya`, `kunjungan` return exact key set.
- `pembayaran`, `penerimaan-barang`, unknown type return `400` unsupported with `supported_types`.
- Numeric totals and item arrays match old baseline.

Public web endpoints:
- `/invoice/{type}/{uuid}` reachable without auth.
- `/invoice/{type}/{uuid}/download` PDF download.
- `/struk/{type}/{uuid}` reachable without auth.
- Invalid UUID returns 404.
- Sequential id is not accepted in public invoice path unless old app accepted it.

Thermal/rich print:
- `print-rich` endpoints return `text/plain; charset=utf-8`.
- ESC/POS output contains expected header, document number, items, total.
- Width-sensitive output should be snapshot-normalized for CRLF if needed.

## Web Route Smoke Tests

Automated route tests:
- All named routes in `04-web-route-map.md` exist.
- Resource route names exist for `penjualan`, `pembelian`, `biaya`, `kunjungan`, `pembayaran`, `penerimaan-barang`, `kontak`, `users`, `gudang`, `produk`.
- Authenticated user can access dashboard/profile.
- Public invoice/receipt unauthenticated.
- Customer portal login flow.

Browser smoke:
- Login page renders.
- Dashboard renders.
- Each index page renders for allowed role.
- Create/edit form renders and submits in staging fixture.
- Approval/cancel buttons generate correct method/path.
- Bootstrap/jQuery interactions used by old Blade work under Vite.

## Non-Regression Side Effect Tests

Critical database assertions:
- API login updates token `last_used_at`.
- Profile avatar writes/removes storage.
- Penjualan create preserves stock rule.
- Kunjungan promo validates stock but does not accidentally change stock unless old code does.
- Penerimaan barang approve/cancel adjusts stock exactly once.
- Pembayaran approve/cancel adjusts penjualan status exactly once.
- Manual stok creates `stok_logs`.
- Transaction rollback leaves no partial header/items on exception.

## Email Notification Tests

| Case | Expected |
| --- | --- |
| Create penjualan | `TransaksiNotificationMail` queued to creator (`created`) AND approvers (`needs_approval`) |
| Create pembelian | Same pattern as penjualan |
| Create biaya | Same pattern |
| Create kunjungan | Same pattern |
| Approve penjualan | `TransaksiNotificationMail` queued to creator (`approved`) |
| Approve pembelian/biaya/kunjungan | Same pattern |
| Recipient filter | Only users with `receives_transaction_email = true` receive email |
| Admin gudang filter | Only admin of transaction's gudang receives needs_approval |
| Super admin always | Super admin with `receives_transaction_email = true` always receives |
| No double send | Creator does not receive both created and needs_approval |
| PDF attachment | Email contains `invoice-{type}-{nomor}.pdf` attachment |
| Subject format | `[Notif Label] Type #{nomor} - Hibiscus Efsya` |
| Dispatch afterResponse | Email sent non-blocking after HTTP response |

## Test Execution Order

1. Unit/model cast/relation tests.
2. Auth/token middleware tests.
3. Master data API tests.
4. Stok tests.
5. Penjualan tests.
6. Pembelian and penerimaan tests.
7. Biaya tests.
8. Kunjungan tests.
9. Pembayaran tests.
10. Dashboard/report/export tests.
11. Print/public invoice/Bluetooth tests.
12. Web route/browser smoke.
13. Full mobile app staging smoke.

## Pass Criteria per Module

A module is portable only when:
- All old endpoints for that module are listed in contract tests.
- Happy path and forbidden path pass for relevant roles.
- Validation status `422` shape is compatible.
- Side effects are asserted.
- JSON keys are snapshot-compatible.
- File/export endpoints match content type and filename.
- No unrelated route/model behavior changed.

## Final Go/No-Go Criteria

Go only when:
- All `/api/v1` contract tests pass.
- All main DB tables from `02-database-map.md` exist with compatible columns/casts.
- All web routes from `04-web-route-map.md` exist.
- Mobile app lama passes staging login, browse, create transaction, approval/payment/print flows.
- Dashboard/report/PDF/Excel smoke tests pass.
- No known critical data integrity or auth bugs remain.

No-go if:
- Any endpoint changes method, URL, status code, required payload, or response shape without explicit documented exception.
- Token auth compatibility breaks.
- Stok/payment/penerimaan side effects differ from baseline.
- Role/gudang scoping leaks data.
- Public invoice exposes sequential ids or fails UUID links.
