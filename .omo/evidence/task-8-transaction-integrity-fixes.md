# Task 8 / Plan Task 9 Evidence - Stock Movement Logs

## Scope

Implemented stock movement audit logs for transaction-driven stock mutations.

## Changes

- `app/Services/InventoryMutationService.php`
  - `decrement()` and `increment()` now accept optional context.
  - When context is provided, a `StokLog` is created in the same DB transaction with:
    - `stok_sebelum`
    - `stok_sesudah`
    - `selisih`
    - `keterangan` containing transaction type, id, and nomor.
  - Rollbacks remove the log because log creation happens inside the caller transaction.

- Transaction callers now pass audit context:
  - API sales approve/cancel: `app/Http/Controllers/Api/PenjualanController.php`
  - API promo approve/cancel: `app/Http/Controllers/Api/KunjunganController.php`
  - Filament sales approve/cancel: `app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php`
  - Filament promo approve/cancel: `app/Filament/Resources/Kunjungans/Pages/ViewKunjungan.php`

- Receiving helpers now create `StokLog` after their existing stock mutations without changing stock math:
  - API receiving store/approve/cancel: `app/Http/Controllers/Api/PenerimaanBarangController.php`
  - API super-admin auto-approved `store()` now passes the created `$penerimaan` context into `tambahStok()`, so committed stock additions create transaction-linked logs.
  - Filament receiving create/approve/cancel/edit: `app/Filament/Resources/PenerimaanBarangs/Pages/CreatePenerimaanBarang.php`, `ViewPenerimaanBarang.php`, `EditPenerimaanBarang.php`

- `tests/Feature/Services/InventoryMutationServiceTest.php`
  - Added coverage for one committed log.
  - Added coverage that rollback creates no log.
  - Added coverage that rejected decrement creates no duplicate/no log.

- `tests/Feature/Api/PenerimaanBarangContractTest.php`
  - Added coverage that super-admin auto-approved receiving store creates exactly one `StokLog` for one committed stock addition.
  - Added coverage that rejected receiving store creates no receiving, stock row, or stock log.

## Verification

Commands and results:

```powershell
php artisan test --filter=InventoryMutation
# PASS Tests\Feature\Services\InventoryMutationServiceTest
# Tests: 8 passed (19 assertions)
```

```powershell
php artisan test --filter=StokContractTest
# PASS Tests\Feature\Api\StokContractTest
# Tests: 8 passed (21 assertions)
```

```powershell
php artisan test --filter=PenjualanContractTest
# PASS Tests\Feature\Api\PenjualanContractTest
# Tests: 21 passed (69 assertions)
```

```powershell
php artisan test --filter=KunjunganContractTest
# PASS Tests\Feature\Api\KunjunganContractTest
# Tests: 6 passed (24 assertions)
```

```powershell
php artisan test --filter=PenerimaanBarang
# PASS Tests\Feature\Api\PenerimaanBarangContractTest
# Tests: 2 passed (11 assertions)
```

```powershell
vendor\bin\pint.bat --test app\Filament\Resources\PenerimaanBarangs\Pages\CreatePenerimaanBarang.php app\Http\Controllers\Api\PenerimaanBarangController.php app\Models\StokLog.php app\Services\InventoryMutationService.php tests\Feature\Api\PenerimaanBarangContractTest.php
# PASS 5 files
```

```powershell
php -l "app\Filament\Resources\PenerimaanBarangs\Pages\CreatePenerimaanBarang.php"; if ($?) { php -l "app\Http\Controllers\Api\PenerimaanBarangController.php" }; if ($?) { php -l "app\Models\StokLog.php" }; if ($?) { php -l "app\Services\InventoryMutationService.php" }; if ($?) { php -l "tests\Feature\Api\PenerimaanBarangContractTest.php" }
# No syntax errors detected in app\Filament\Resources\PenerimaanBarangs\Pages\CreatePenerimaanBarang.php
# No syntax errors detected in app\Http\Controllers\Api\PenerimaanBarangController.php
# No syntax errors detected in app\Models\StokLog.php
# No syntax errors detected in app\Services\InventoryMutationService.php
# No syntax errors detected in tests\Feature\Api\PenerimaanBarangContractTest.php
```

## Diagnostics

Attempted LSP diagnostics on changed PHP files. PHP LSP server is not installed and was previously declined, so diagnostics could not run. PHP syntax checks, targeted Pint, and PHPUnit filters above passed.
