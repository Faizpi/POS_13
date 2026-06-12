# Dependency Replacement Laravel 7 ke Laravel 13

Scope: dependency lama dibaca dari `composer.json`, `composer.lock`, dan `package.json` repo lama. Rekomendasi versi Laravel 13 dicek terhadap dokumentasi resmi Laravel 13 dan Packagist pada 2026-06-05.

Referensi eksternal:
- Laravel 13 release notes: https://laravel.com/docs/13.x/releases
- Laravel 13 upgrade guide: https://laravel.com/docs/13.x/upgrade
- Laravel 13 starter kits: https://laravel.com/docs/13.x/starter-kits
- `barryvdh/laravel-dompdf`: https://packagist.org/packages/barryvdh/laravel-dompdf
- `maatwebsite/excel`: https://packagist.org/packages/maatwebsite/excel
- `milon/barcode`: https://packagist.org/packages/milon/barcode
- `laravel/ui`: https://packagist.org/packages/laravel/ui

## Baseline Target

Laravel 13.x membutuhkan PHP minimum 8.3 menurut release notes resmi Laravel 13. Mesin audit saat ini menjalankan PHP 8.1.33, sehingga tidak memenuhi target Laravel 13 dan juga tidak ideal untuk menjalankan Laravel 7 lama.

Target minimum rebuild:
- PHP `^8.3`
- Composer 2.x
- Laravel framework `^13.0`
- Laravel Tinker `^3.0`
- PHPUnit `^12.0` jika tetap memakai PHPUnit
- Node/npm modern dengan Vite, bukan Laravel Mix 5

## Composer Require Lama

| Package lama | Versi lama | Status Laravel 13 | Rekomendasi rebuild |
| --- | --- | --- | --- |
| `php` | `^7.2.5` | Tidak kompatibel | Ganti ke `^8.3`; audit semua deprecation PHP 8.x |
| `laravel/framework` | `^7.0` | Tidak kompatibel | Ganti ke `^13.0`; rebuild dari skeleton baru, bukan upgrade in-place production |
| `barryvdh/laravel-dompdf` | `^2.2` | Versi baru mendukung Illuminate `^13.0` | Upgrade ke `^3.1`; test semua invoice PDF dan asset remote/local |
| `doctrine/dbal` | `^2.10.3` | Versi lama tidak cocok | Jangan bawa dulu kecuali migration butuh schema introspection; jika perlu, pilih versi modern yang kompatibel PHP 8.3 |
| `fideloper/proxy` | `^4.2` | Legacy Laravel lama | Hapus; gunakan trusted proxy support bawaan Laravel modern |
| `fruitcake/laravel-cors` | `^3.0` | Legacy external middleware | Hapus jika CORS bawaan Laravel 13 cukup; port config CORS lama secara eksplisit |
| `guzzlehttp/guzzle` | `^6.3` | Versi lama | Upgrade ke versi modern yang dibawa Laravel/PHP 8.3; hanya tambahkan direct require jika kode memakai Guzzle langsung |
| `laravel/tinker` | `^2.0` | Upgrade guide Laravel 13 menyarankan `^3.0` | Ganti ke `^3.0` |
| `laravel/ui` | `^2.4` | `laravel/ui` v4 mendukung Laravel 13, tetapi legacy | Untuk compatibility Blade Bootstrap, boleh pakai `^4.6` atau route auth manual. Jangan gunakan starter kit yang mengubah URL/form tanpa adapter |
| `maatwebsite/excel` | `^3.1` | Packagist 3.1.x mendukung Illuminate `^13.0` | Pertahankan dengan latest `^3.1`, test semua export |
| `milon/barcode` | `^7.0` | Packagist v13 mendukung Laravel 13 | Upgrade ke `^13.0`; pastikan `ext-gd` tersedia |

## Composer Dev Lama

| Package lama | Versi lama | Rekomendasi |
| --- | --- | --- |
| `facade/ignition` | `^2.0` | Jangan bawa. Gunakan error/debug stack bawaan Laravel 13 |
| `fzaninotto/faker` | `^1.9.1` | Ganti ke `fakerphp/faker` jika factories/test butuh faker |
| `mockery/mockery` | `^1.3.1` | Pakai versi modern yang cocok dengan PHPUnit 12/PHP 8.3 |
| `nunomaduro/collision` | `^4.1` | Pakai versi yang dibawa Laravel 13 atau versi kompatibel |
| `phpunit/phpunit` | `^8.5` | Ganti ke `^12.0` sesuai upgrade guide Laravel 13 |

## Frontend Dependency Lama

`package.json` lama:
- `laravel-mix` `^5.0.1`
- `bootstrap` `^4.0.0`
- `jquery` `^3.2`
- `popper.js` `^1.12`
- `sass`, `sass-loader`, `resolve-url-loader`
- `axios` `^0.19`
- `lodash`
- `vue-template-compiler` `^2.7.16`
- `cross-env`

Rekomendasi:
- Ganti Laravel Mix dengan Vite Laravel 13.
- Pertahankan Bootstrap 4 dan jQuery pada fase compatibility jika view Blade lama bergantung pada class/JS lama.
- Jangan redesign ke Tailwind/React/Livewire sampai kontrak web/admin stabil.
- Jika tidak ada Vue component aktif, jangan bawa Vue 2 dan `vue-template-compiler`.
- Upgrade Axios ke versi modern hanya jika API JS web benar-benar memakai Axios; test CSRF/header behavior.
- `popper.js` v1 adalah dependency Bootstrap 4; jika tetap Bootstrap 4, pertahankan paket yang cocok. Jika nantinya migrasi Bootstrap 5, jadikan fase terpisah.

## Auth Replacement

Opsi aman:
1. Port route auth web secara manual agar URL lama tetap sama.
2. Atau gunakan `laravel/ui` v4 hanya untuk scaffolding Bootstrap/auth compatibility, lalu sesuaikan view lama.
3. Jangan memakai starter kit modern yang mengubah struktur URL, form, session, atau tampilan sebelum compatibility selesai.

Untuk API mobile:
- Jangan mengganti custom `personal_access_tokens` ke Sanctum pada fase awal.
- Implementasikan middleware custom Laravel 13 yang meniru `ApiTokenAuth`.
- Sanctum boleh dipakai nanti hanya jika ada adapter yang menerima token lama dan response lama.

## PDF, Excel, Barcode

PDF:
- `barryvdh/laravel-dompdf` v3.1 mendukung Laravel 13.
- Risiko: remote access dompdf baru default disabled. Invoice lama mungkin memakai asset public/storage; test semua template PDF.
- Pertahankan facade/import style atau adaptasikan secara minimal.

Excel:
- `maatwebsite/excel` 3.1.x mendukung Laravel 13.
- Risiko: writer response header dan auto-size bisa berubah. Snapshot filename/content type.

Barcode/QR:
- `milon/barcode` v13 mendukung Laravel 13 dan butuh `ext-gd`.
- Risiko: QR/barcode output HTML/SVG/base64 bisa berubah antar versi. Snapshot public invoice/print yang memakai QR/barcode.

## Laravel 13 Framework Changes yang Relevan

Dari upgrade guide/release notes Laravel 13:
- PHP minimum 8.3.
- `laravel/framework` ke `^13.0`.
- `laravel/tinker` ke `^3.0`.
- `phpunit/phpunit` ke `^12.0`.
- Ada perubahan CSRF/request forgery protection; route API bearer token dan web form harus dites.
- Cache/session prefix default berubah pada Laravel 13 jika tidak dikonfigurasi eksplisit. Set `SESSION_COOKIE`, cache prefix, dan redis prefix secara eksplisit agar session lama tidak berubah diam-diam.
- Pagination Bootstrap view names disebut pada upgrade guide; admin Blade lama yang memakai Bootstrap pagination harus dites.
- MySQL/MariaDB `upsert` behavior berubah; tidak terlihat sebagai fitur utama lama, tetapi perlu diperhatikan jika rebuild menambahkan upsert.

## Package yang Tidak Perlu Dibawa di Fase Compatibility

Jangan tambahkan dependency baru untuk:
- Role/permission package eksternal. Role lama cukup string dan middleware custom.
- API token package eksternal. Token lama harus kompatibel.
- Admin panel generator. Web admin lama harus dipertahankan dari Blade/routes lama.
- PDF/browser rendering alternatif seperti Browsershot, kecuali dompdf gagal setelah bukti test.
- Queue/notification package baru sebelum email transaksi lama dipetakan.

## Lingkungan Wajib

Server/runtime:
- PHP 8.3+.
- Extension umum Laravel: Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PDO, Session, Tokenizer, XML.
- `ext-gd` wajib untuk avatar compression dan `milon/barcode`.
- Extension zip/xml/spreadsheet dependencies untuk Excel export sesuai kebutuhan PhpSpreadsheet.

Storage:
- `storage/app/public` dan symlink public storage untuk avatar/lampiran.
- Permission write untuk lampiran dan generated exports.

Mail:
- Laravel 7 lama memakai stack SwiftMailer transitive; Laravel modern memakai Symfony Mailer. Test semua email approval/transaction.
- Email features yang aktif: `TransaksiInvoiceMail`, `TransaksiNotificationMail`, `InvoiceEmailService`.
- Dispatch `afterResponse()` equivalent di Laravel 13 mungkin perlu dicek; `dispatch()` global masih tersedia tetapi pastikan `afterResponse` behavior sama.
- SMTP/Mailgun/SES config lama di `.env` harus dimigrasikan ke format Symfony Mailer.

## Dependency Replacement Order

1. Siapkan PHP 8.3+ dan Composer 2 tanpa menyentuh repo lama.
2. Buat skeleton Laravel 13 baru setelah fase audit selesai.
3. Pasang dependency compatibility minimal:
   - Laravel framework 13
   - Tinker 3
   - Dompdf 3.1
   - Laravel Excel 3.1 latest
   - Milon Barcode 13
   - Auth scaffolding/manual route sesuai keputusan
4. Port config CORS, filesystem, mail, session, cache prefix secara eksplisit.
5. Port custom middleware `api.token`, `role`, `customer.auth`.
6. Jalankan contract tests sebelum menambah modul bisnis.

## Risiko dan Mitigasi

| Risiko | Dampak | Mitigasi |
| --- | --- | --- |
| PHP target tidak tersedia | Laravel 13 tidak bisa install/run | Upgrade local/server ke PHP 8.3+ sebelum scaffold |
| Token diganti ke Sanctum | Mobile lama tidak bisa login/request | Middleware custom kompatibel dulu |
| `laravel/ui` diganti starter kit modern | URL/form web lama berubah | Route auth manual atau `laravel/ui` v4 compatibility |
| Mix ke Vite merusak asset Blade | Admin UI rusak | Port Bootstrap/jQuery lama dulu, baru refactor |
| Dompdf v3 asset policy berubah | PDF invoice kosong/asset hilang | Snapshot dan render PDF semua invoice |
| Excel output berubah | Report/admin export pecah | Contract test headers, filename, row summary |
| Barcode output berubah | QR/public invoice print beda | Snapshot QR payload dan rendered invoice |
| Schema migration menambah constraint baru | Data production gagal migrate | Verifikasi dump schema/data sebelum constraint |
