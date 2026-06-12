# Risiko Utama dan Mitigasi

## Kategori Risiko

### 🔴 Critical (Bisa menghentikan production)

| # | Risiko | Dampak | Probabilitas | Mitigasi |
|---|--------|--------|--------------|----------|
| 1 | Token API berubah format/hash | Semua mobile user tidak bisa login | Rendah jika pakai custom middleware | Port `ApiTokenAuth` persis, jangan pakai Sanctum fase awal |
| 2 | Response JSON shape berubah | Mobile app crash/data salah | Sedang | Contract test SEBELUM port, bandingkan key per key |
| 3 | Stok miscalculation | Kerugian bisnis langsung | Sedang | Test stok sebelum/sesudah setiap transaksi, unit test stock logic |
| 4 | Schema production beda dari migration | Runtime error, data loss | Tinggi | Dump schema read-only, compare dengan migration, buat migration match actual |
| 5 | PHP version incompatibility di production | Laravel 13 tidak bisa deploy | Rendah jika disiapkan | Verifikasi server PHP 8.3+ sebelum mulai |

### 🟡 High (Mengganggu operasional signifikan)

| # | Risiko | Dampak | Probabilitas | Mitigasi |
|---|--------|--------|--------------|----------|
| 6 | Role scoping salah | Kebocoran data antar gudang | Sedang | Permission test matrix per role per gudang |
| 7 | PDF/Excel berubah format | Admin tidak bisa print/export | Sedang | Snapshot output lama, visual compare |
| 8 | Email notification tidak terkirim | Admin tidak tahu ada transaksi baru | Rendah | Test dengan Mail::fake, verifikasi SMTP config |
| 9 | Session/cookie berubah | User logout paksa, customer portal rusak | Rendah | Set session config eksplisit, test cookie name/domain |
| 10 | Nomor dokumen berubah format | Audit trail rusak, invoice tidak match | Rendah | Snapshot nomor generator, test format INV/PR/EXP/VST/PAY/RCV |

### 🟢 Medium (Inconvenience, bisa diperbaiki cepat)

| # | Risiko | Dampak | Probabilitas | Mitigasi |
|---|--------|--------|--------------|----------|
| 11 | Route name hilang | Blade form/button error | Rendah | Route name test, browser smoke |
| 12 | Asset Blade rusak (Mix → Vite) | Tampilan admin berantakan | Sedang | Port Bootstrap/jQuery via Vite, visual check |
| 13 | Timezone/locale berubah | Report tanggal salah 1 hari | Rendah | Set timezone eksplisit Asia/Jakarta |
| 14 | Pagination format berubah | Mobile list loading issue | Rendah | Test paginator JSON structure |
| 15 | CORS config salah | Mobile API blocked | Rendah | Port CORS config eksplisit |

## Mitigasi Struktural

### 1. Baseline Capture (Sebelum port apapun)

```
Untuk setiap endpoint API:
1. Request dengan token setiap role
2. Simpan: method, URL, status, headers, body
3. Mask: token, timestamp, UUID, host, auto-id
4. File: tests/Fixtures/api-v1/{module}/{case}.json
```

### 2. Contract Test First

Sebelum port modul X:
1. Buat contract test dari baseline capture modul X.
2. Test harus fail (karena controller belum ada).
3. Port controller.
4. Test harus pass.
5. Commit.

### 3. Feature Flag / Parallel Run

- Jalankan Laravel 13 di staging dengan subdomain terpisah.
- Mobile dev build bisa switch base URL.
- Compare response side-by-side selama 1 minggu.
- Jangan cutover sampai 0 discrepancy pada critical path.

### 4. Rollback Plan

```
Cutover day:
- T-0: DNS switch ke Laravel 13
- T+1h: Monitor error rate
- T+4h: Jika error > threshold, rollback DNS
- T+72h: Decommission Laravel 7 standby

Rollback:
- DNS revert ke Laravel 7 server (< 5 menit)
- Laravel 7 masih membaca DB yang sama
- Tidak ada migration destructive, jadi DB compatible kedua versi
```

### 5. Data Safety

- Migration Laravel 13 TIDAK dijalankan ke DB production pada fase awal.
- Gunakan `php artisan migrate --pretend` untuk verify.
- Jika DB production schema berbeda dari migration:
  - Dokumentasikan perbedaan.
  - Buat adapter di model (custom table/column jika perlu).
  - JANGAN alter production table tanpa maintenance window.

## Decision Log

Keputusan teknis yang harus diambil sebelum implementasi:

| # | Keputusan | Opsi | Rekomendasi | Status |
|---|-----------|------|-------------|--------|
| 1 | Auth scaffolding | laravel/ui v4 vs manual routes | laravel/ui v4 untuk speed | Pending |
| 2 | Model namespace | `App\Models` vs `App` | `App\Models` (Laravel 13 standard) | Pending |
| 3 | Migration strategy | Fresh vs match-existing | Match existing schema | Pending |
| 4 | Frontend tooling | Vite + Bootstrap 4 vs keep Mix | Vite + Bootstrap 4 | Pending |
| 5 | Test framework | PHPUnit vs Pest | PHPUnit (familiar) | Pending |
| 6 | Queue driver | sync vs redis/database | sync fase awal | Pending |
| 7 | Deploy target | Same server vs new | Decision needed | Pending |

## Monitoring Post-Cutover

Metrik yang harus dipantau 72 jam pertama:

- Error rate (5xx responses) — threshold: < 0.1%
- API response time — threshold: < 500ms p95
- Login success rate — threshold: > 99%
- Transaction creation success — threshold: > 98%
- Email delivery — threshold: > 95%
- Stock accuracy — spot check 10 produk random
- PDF generation — spot check 5 invoice
