# Task 9 / Plan Task 10 - Repair hutang payment schema compatibility

## Scope
- Read `.omo/plans/transaction-integrity-fixes.md` Task 10.
- Inspected base payment migration `database/migrations/0001_01_01_000010_create_pembayarans_table.php` line 17.
- Inspected sync migrations adding `pembayarans.type` and `pembayarans.pembelian_id`:
  - `database/migrations/2026_06_15_120443_fix_2026_06_15_110705_idempotent.php`
  - `database/migrations/2026_06_19_120000_sync_schema_after_import.php`
- Inspected `app/Models/Pembayaran.php` and `app/Http/Controllers/Api/PembayaranController.php`.

## Changes
- Updated base `pembayarans` migration so `penjualan_id` is nullable from a fresh migration and keeps the existing FK with `nullOnDelete()`.
- Added safe follow-up migration:
  - `database/migrations/2026_07_09_100000_make_pembayarans_penjualan_id_nullable.php`
  - Checks `Schema::getColumns('pembayarans')` before altering.
  - Drops/recreates the `penjualan_id` FK only when the column is currently non-nullable.
- Updated `Pembayaran` model:
  - Added integer casts for `penjualan_id` and `pembelian_id`.
  - Defaults missing `type` to `piutang` on create.
  - Adds model-level typed relation validation:
    - `type=piutang` requires `penjualan_id`.
    - `type=hutang` requires `pembelian_id`.
- Updated API validation:
  - `/api/v1/pembayaran` only accepts piutang payloads and requires `penjualan_id`.
  - `/api/v1/pembayaran-hutang` only accepts hutang payloads and requires `pembelian_id`; `penjualan_id` is prohibited.
- Added test fixture helper for hutang payments.
- Added tests proving:
  - Fresh test schema has nullable `pembayarans.penjualan_id`.
  - Piutang rows can insert with `penjualan_id`.
  - Hutang rows can insert with `pembelian_id` and `penjualan_id = null`.
  - Missing type-specific relation is rejected at model/application level.
  - API rejects hutang without `pembelian_id`.
  - API rejects piutang without `penjualan_id`.

## Verification

### PHPUnit - targeted contract
Command:
```bash
php artisan test --filter=PembayaranContractTest
```
Result:
```text
PASS Tests\Feature\Api\PembayaranContractTest
Tests: 11 passed (42 assertions)
Duration: 1.78s
```

### PHPUnit - PembayaranHutang filter
Command:
```bash
php artisan test --filter=PembayaranHutang
```
Result:
```text
PASS Tests\Feature\Api\PembayaranContractTest
Tests: 2 passed (8 assertions)
Duration: 1.09s
```

### PHPUnit - broader pembayaran filter
Command:
```bash
php artisan test --filter=Pembayaran
```
Result:
```text
PASS Tests\Feature\Api\ExportAndPrintParityTest
PASS Tests\Feature\Api\PembayaranContractTest
PASS Tests\Feature\PanelBootTest
Tests: 13 passed (47 assertions)
Duration: 2.33s
```

### PHP syntax
Command:
```bash
php -l app\Models\Pembayaran.php
php -l app\Http\Controllers\Api\PembayaranController.php
php -l database\migrations\2026_07_09_100000_make_pembayarans_penjualan_id_nullable.php
php -l database\migrations\0001_01_01_000010_create_pembayarans_table.php
```
Result:
```text
No syntax errors detected in app\Models\Pembayaran.php
No syntax errors detected in app\Http\Controllers\Api\PembayaranController.php
No syntax errors detected in database\migrations\2026_07_09_100000_make_pembayarans_penjualan_id_nullable.php
No syntax errors detected in database\migrations\0001_01_01_000010_create_pembayarans_table.php
```

### LSP diagnostics
Attempted on changed PHP files. PHP LSP is not installed in this environment and was previously declined, so diagnostics could not run. PHPUnit and `php -l` were used instead.

## Verifier fix pass

### Issues addressed
- Targeted Pint failures fixed on:
  - `app/Models/Pembayaran.php`
  - `tests/Feature/Api/PembayaranContractTest.php`
  - `tests/Support/BuildsTransactionFixtures.php`
- Added `tests/Feature/Api/PembayaranHutangContractTest.php` so the required `--filter=PembayaranHutang` command has a stable Pint-compliant class match.
- Updated `database/migrations/2026_07_09_100000_make_pembayarans_penjualan_id_nullable.php::down()` to preflight nullable payment rows:
  - If any row has `penjualan_id = NULL`, rollback throws a clear `RuntimeException` before schema changes.
  - No fake `penjualan_id` backfill is attempted.
  - Hutang rows are not corrupted.

### Targeted Pint
Command:
```bash
.\vendor\bin\pint --test app\Models\Pembayaran.php database\migrations\0001_01_01_000010_create_pembayarans_table.php database\migrations\2026_07_09_100000_make_pembayarans_penjualan_id_nullable.php tests\Feature\Api\PembayaranContractTest.php tests\Feature\Api\PembayaranHutangContractTest.php tests\Support\BuildsTransactionFixtures.php
```
Result:
```text
PASS 6 files
```

### Syntax + tests
Command:
```bash
php -l app\Models\Pembayaran.php
php -l app\Http\Controllers\Api\PembayaranController.php
php -l database\migrations\0001_01_01_000010_create_pembayarans_table.php
php -l database\migrations\2026_07_09_100000_make_pembayarans_penjualan_id_nullable.php
php -l tests\Feature\Api\PembayaranContractTest.php
php -l tests\Feature\Api\PembayaranHutangContractTest.php
php -l tests\Support\BuildsTransactionFixtures.php
php artisan test --filter=PembayaranHutang
php artisan test --filter=PembayaranContractTest
php artisan test --filter=Pembayaran
```
Result:
```text
No syntax errors detected in all checked PHP files.

PASS Tests\Feature\Api\PembayaranHutangContractTest
Tests: 2 passed (7 assertions)

PASS Tests\Feature\Api\PembayaranContractTest
Tests: 11 passed (42 assertions)

PASS Tests\Feature\Api\ExportAndPrintParityTest
PASS Tests\Feature\Api\PembayaranContractTest
PASS Tests\Feature\Api\PembayaranHutangContractTest
PASS Tests\Feature\PanelBootTest
Tests: 15 passed (54 assertions)
```

### LSP diagnostics
Attempted again on the changed migration. PHP LSP is not installed in this environment and was previously declined, so diagnostics could not run. PHPUnit, Pint, and `php -l` passed.

## Notes
- Did not drop or rename `penjualan_id`.
- Did not implement hutang approval/cancel settlement logic; that remains Task 11.
- Existing piutang FK remains present and now uses nullable-safe `nullOnDelete()` behavior.
