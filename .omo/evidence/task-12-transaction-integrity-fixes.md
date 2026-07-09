# Task 13 - Guard sales update against stock/payment corruption

Date: 2026-07-09

## Scope

Implemented the reject policy for unsafe sales edits:

- API full update now rejects `Approved`/`Lunas` sales before validation/persistence with HTTP 422.
- Filament sales edit now blocks stock/money-affecting edits for `Approved`/`Lunas` sales and shows the same reversal-workflow guidance.
- Pending sales update still recomputes item rows and `grand_total` server-side.
- Cash updates no longer silently mark sales `Lunas`; pending updates remain `Pending` unless payment/settlement flow changes status.
- API attachment-only update remains allowed for the owning user, including on `Lunas` records.

## Tests added

Updated `tests/Feature/Api/PenjualanContractTest.php` with coverage for:

- Approved sale update rejection leaves status, total, item quantity, and stock unchanged.
- Lunas sale update rejection leaves status, payment, total, item quantity, and stock unchanged.
- Attachment-only update still appends lampiran without changing Lunas/payment state.
- Pending sale update succeeds, recomputes totals, and keeps Cash sale Pending without settlement.

## Failing-first evidence

Command:

```powershell
php artisan test --filter=update_
```

Observed expected failures before implementation:

- Approved update returned 200 instead of expected 422.
- Lunas update returned 200 instead of expected 422.
- Pending Cash update returned `Lunas` instead of expected `Pending`.

## Verification

Command:

```powershell
php artisan test --filter=PenjualanContractTest
```

Result:

```text
PASS Tests\Feature\Api\PenjualanContractTest
Tests: 25 passed (101 assertions)
```

Command:

```powershell
php -l "app\Http\Controllers\Api\PenjualanController.php"
php -l "app\Filament\Resources\Penjualans\Pages\EditPenjualan.php"
php -l "tests\Feature\Api\PenjualanContractTest.php"
.\vendor\bin\pint --test "app\Http\Controllers\Api\PenjualanController.php" "app\Filament\Resources\Penjualans\Pages\EditPenjualan.php" "tests\Feature\Api\PenjualanContractTest.php"
```

Result:

```text
No syntax errors detected in app\Http\Controllers\Api\PenjualanController.php
No syntax errors detected in app\Filament\Resources\Penjualans\Pages\EditPenjualan.php
No syntax errors detected in tests\Feature\Api\PenjualanContractTest.php
PASS 3 files
```

LSP diagnostics were attempted for all changed PHP files; PHP LSP is not installed and was previously declined, so diagnostics could not run in this environment.
