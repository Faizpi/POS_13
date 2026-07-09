# Task 16 / Plan Task 17 Evidence — Remove cash-status side effects

Date: 2026-07-09

## Settlement policy defined

- `syarat_pembayaran = Cash` does not automatically set `Penjualan.status = Lunas` during create, approve, or update.
- Cash sales are created as `Pending` and become `Approved` only through the existing approval flow.
- `Lunas` requires an approved `pembayarans` ledger row.
- The manual/API and Filament `Tandai Lunas` action now creates and approves a `Pembayaran` record for the exact remaining balance with `metode_pembayaran = Cash`; the payment settlement service then recomputes the sale status to `Lunas`.
- No payment settlement service logic was changed.
- No schema/migration changes were made.

## Files changed

- `app/Services/SaleCashSettlementService.php`
  - New small service that creates an explicit cash payment record for the remaining sale balance, then approves it through `PaymentSettlementService`.
- `app/Http/Controllers/Api/PenjualanController.php`
  - `markAsPaid()` now delegates to `SaleCashSettlementService`; it no longer writes `status = Lunas` directly.
  - Added missing `SalesMoneyCalculator` import needed by existing calculator wiring.
- `app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php`
  - `markAsPaid` action now delegates to `SaleCashSettlementService`; it no longer writes `status = Lunas` directly.
- `app/Filament/Resources/Penjualans/Pages/CreatePenjualan.php`
  - Clarified code comment that cash settlement requires an explicit approved `Pembayaran` record before `Lunas`.
- `tests/Feature/Api/PenjualanContractTest.php`
  - Added/updated assertions proving cash create/approve stay non-`Lunas` without payment records and `mark-paid` produces an approved cash payment ledger row before `Lunas`.

## Command results

### PHP syntax checks

Command:

```powershell
php -l "app/Http/Controllers/Api/PenjualanController.php"; php -l "app/Filament/Resources/Penjualans/Pages/CreatePenjualan.php"; php -l "app/Filament/Resources/Penjualans/Pages/EditPenjualan.php"; php -l "app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php"; php -l "app/Services/SaleCashSettlementService.php"; php -l "tests/Feature/Api/PenjualanContractTest.php"
```

Result:

```text
No syntax errors detected in app/Http/Controllers/Api/PenjualanController.php
No syntax errors detected in app/Filament/Resources/Penjualans/Pages/CreatePenjualan.php
No syntax errors detected in app/Filament/Resources/Penjualans/Pages/EditPenjualan.php
No syntax errors detected in app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php
No syntax errors detected in app/Services/SaleCashSettlementService.php
No syntax errors detected in tests/Feature/Api/PenjualanContractTest.php
```

Additional post-format syntax check for the final touched Filament create page:

```text
No syntax errors detected in app/Filament/Resources/Penjualans/Pages/CreatePenjualan.php
```

### Target regression tests

Command:

```powershell
php artisan test --filter=PenjualanContractTest
```

Result:

```text
PASS  Tests\Feature\Api\PenjualanContractTest
✓ index returns paginated for super admin
✓ user sees only own penjualan
✓ store success creates pending
✓ store insufficient stock returns 422
✓ store spectator forbidden
✓ store calculates grosir price
✓ approve pending to approved
✓ approve decrements saleable stock exactly once
✓ approve rejects insufficient stock without status or stock changes
✓ approve non pending fails
✓ cancel own transaction
✓ cancel pending has no stock effect
✓ cancel approved restores saleable stock exactly once
✓ cancel lunas restores saleable stock
✓ cancel other user forbidden
✓ uncancel super admin only
✓ uncancel non super admin forbidden
✓ uncancel returns pending without reapplying stock then approve validates stock
✓ mark paid approved to lunas
✓ mark paid non approved fails
✓ cash sale cannot become lunas without payment record
✓ store cash sale remains pending without payment record
✓ approve cash sale remains approved until payment record is approved
✓ unmark paid lunas to approved
✓ unmark paid non super admin forbidden
✓ update rejects approved sale stock and money changes without corruption
✓ update rejects lunas sale stock and money changes without corrupting payments
✓ attachment only update still allows owner to add lampiran on lunas sale
✓ update pending sale recomputes totals and keeps cash pending without payment

Tests: 29 passed (126 assertions)
Duration: 7.14s
```

### Pint

Initial command:

```powershell
vendor/bin/pint --test "app/Http/Controllers/Api/PenjualanController.php" "app/Filament/Resources/Penjualans/Pages/CreatePenjualan.php" "app/Filament/Resources/Penjualans/Pages/EditPenjualan.php" "app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php" "app/Services/SaleCashSettlementService.php" "tests/Feature/Api/PenjualanContractTest.php"
```

Initial result:

```text
FAIL  6 files, 3 style issues
⨯ app\Filament\Resources\Penjualans\Pages\CreatePenjualan.php fully_qualified_strict_types, control_structure_braces...
⨯ app\Filament\Resources\Penjualans\Pages\ViewPenjualan.php fully_qualified_strict_types, unary_operator_spaces, no...
⨯ app\Http\Controllers\Api\PenjualanController.php unary_operator_spaces, not_operator_with_successor_space, ordered...
```

Fix command:

```powershell
vendor/bin/pint "app/Http/Controllers/Api/PenjualanController.php" "app/Filament/Resources/Penjualans/Pages/CreatePenjualan.php" "app/Filament/Resources/Penjualans/Pages/EditPenjualan.php" "app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php" "app/Services/SaleCashSettlementService.php" "tests/Feature/Api/PenjualanContractTest.php"
```

Fix result:

```text
FIXED  6 files, 3 style issues fixed
✓ app\Filament\Resources\Penjualans\Pages\CreatePenjualan.php fully_qualified_strict_types, control_structure_braces...
✓ app\Filament\Resources\Penjualans\Pages\ViewPenjualan.php fully_qualified_strict_types, unary_operator_spaces, no...
✓ app\Http\Controllers\Api\PenjualanController.php unary_operator_spaces, not_operator_with_successor_space, ordered...
```

Final command:

```powershell
vendor/bin/pint --test "app/Http/Controllers/Api/PenjualanController.php" "app/Filament/Resources/Penjualans/Pages/CreatePenjualan.php" "app/Filament/Resources/Penjualans/Pages/EditPenjualan.php" "app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php" "app/Services/SaleCashSettlementService.php" "tests/Feature/Api/PenjualanContractTest.php"
```

Final result:

```text
......
PASS  6 files
```

Post-comment one-file Pint check:

```text
vendor/bin/pint "app/Filament/Resources/Penjualans/Pages/CreatePenjualan.php"; if ($?) { vendor/bin/pint --test "app/Filament/Resources/Penjualans/Pages/CreatePenjualan.php" }

FIXED  1 file, 1 style issue fixed
PASS   1 file
```

### LSP diagnostics

Command: `lsp_diagnostics` on changed PHP files.

Result:

```text
LSP server 'php' (.php) is NOT INSTALLED; user previously declined installation — proceed without LSP.
```

## Notes

- A first `php artisan test --filter=PenjualanContractTest` run exposed a container error: `Target class [App\Http\Controllers\Api\SalesMoneyCalculator] does not exist.` The existing controller referenced `SalesMoneyCalculator::class` without importing `App\Services\SalesMoneyCalculator`; adding the import fixed the test run.
- No migrations were run.
- No servers were started.
- No commits were made.
