# Implementation Report 17 - Verification Hardening

**Tanggal:** 7 Juni 2026  
**Status:** Verification hardening selesai  
**Tests:** 95 passed, 270 assertions  
**Routes:** 195 registered

---

## Ringkasan Pass Ini

Pass ini tidak menambah fitur atau route baru. Fokusnya mengunci area optional yang tersisa dari Report 16 dengan automated tests lokal:

- Email invoice dispatch via `InvoiceEmailService::sendInvoice()`
- PDF attachment generation untuk email invoice
- Resolusi email approver transaksi per gudang
- Public invoice PDF download response
- API docs HTML/JSON/Postman endpoints
- Customer portal phone/PIN login flow with legacy phone-number compatibility

---

## 1. Email Invoice Service Test

**File baru:** `tests/Feature/InvoiceEmailServiceTest.php`

Coverage:

- Membuat transaksi penjualan fixture dari seed data
- Memanggil `InvoiceEmailService::sendInvoice($penjualan, 'penjualan', 'customer@example.test')`
- Menggunakan `Mail::fake()` untuk memastikan `TransaksiInvoiceMail` terkirim ke alamat target
- Memastikan PDF attachment benar-benar digenerate dengan konten yang diawali `%PDF`
- Memastikan `getApproverEmails($gudangId)` mengembalikan:
  - `superadmin@hibiscusefsya.com`
  - `admin@hibiscusefsya.com`

---

## 2. Public Invoice PDF Rendering Test

**File diupdate:** `tests/Feature/PublicDocumentRoutesTest.php`

Coverage baru:

- Route `public.invoice.penjualan.download`
- HTTP 200 OK
- Header `content-type: application/pdf`
- Konten response diawali `%PDF`

Ini mengunci integrasi route, controller, Blade template PDF, dan DomPDF untuk invoice penjualan publik.

---

## 3. Customer Portal Phone Compatibility

**File diupdate:** `app/Http/Controllers/CustomerPortalController.php`

Perbaikan:

- Customer lookup sekarang mencoba variasi nomor telepon `08...`, `8...`, `62...`, dan `+62...`
- Login PIN tetap menyimpan session keys lama:
  - `customer_id`
  - `customer_no_telp`
  - `customer_nama`

**File baru:** `tests/Feature/CustomerPortalTest.php`

Coverage:

- `POST /customer/check-phone` menerima input `+62 ...` meskipun data kontak lama tersimpan sebagai `08...`
- `POST /customer/login` menerima input `62...` dan PIN valid
- Dashboard customer bisa dibuka setelah session dibuat

---

## 4. API Docs Test

**File baru:** `tests/Feature/ApiDocsTest.php`

Coverage:

- `GET /docs` render halaman dokumentasi
- `GET /docs/json` menghasilkan OpenAPI JSON dengan path penting
- `GET /docs/download/postman` menghasilkan Postman collection JSON

---

## 5. Validasi

```bash
php artisan test tests/Feature/PublicDocumentRoutesTest.php tests/Feature/InvoiceEmailServiceTest.php
# 5 passed, 22 assertions

php artisan test tests/Feature/CustomerPortalTest.php
# 2 passed, 11 assertions

php artisan test tests/Feature/ApiDocsTest.php
# 2 passed, 14 assertions

php artisan test
# 95 passed, 270 assertions

vendor/bin/pint --test app/Http/Controllers/CustomerPortalController.php tests/Feature/PublicDocumentRoutesTest.php tests/Feature/InvoiceEmailServiceTest.php tests/Feature/ApiDocsTest.php tests/Feature/CustomerPortalTest.php
# PASS

php artisan route:list --json
# 195 routes
```

---

## Catatan Remaining Risk

- Browser visual QA untuk membandingkan invoice lama vs baru belum dijalankan karena membutuhkan sesi browser visual dan target pembanding yang eksplisit.
- Mobile app live test ke staging belum dijalankan karena membutuhkan endpoint/staging server aktif.
- `sendCreatedNotification()` dan `sendApprovedNotification()` memakai `afterResponse()`; pass ini mengunci path email langsung dan PDF generation, bukan lifecycle HTTP termination callback.
