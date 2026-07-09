# Task 11 - Hutang settlement / purchase recomputation evidence

Date: 2026-07-09

## Scope executed

- Inspected worktree before edits. The repository already contained broad uncommitted Task 1-10 work plus Task 10 hutang schema compatibility artifacts; no valid Task 11 hutang settlement implementation was present.
- Read Task 11 from `.omo/plans/transaction-integrity-fixes.md` and current `PaymentSettlementService`, API payment controller, hutang Filament resources, purchase relation manager, and hutang tests.
- Added failing-first hutang regression coverage:
  - approving exact remaining `type=hutang` payment marks linked `Pembelian` as `Lunas`;
  - canceling an approved hutang payment recomputes a `Lunas` purchase back to `Approved` when remaining approved hutang totals are below `grand_total`;
  - uncanceling a hutang payment returns it to `Pending` and recomputes purchase status from still-approved totals;
  - storing or approving hutang overpayment is rejected.
- Implemented shared `PaymentSettlementService` branching:
  - `approvePayment`, `cancelPayment`, `uncancelPayment` route by `Pembayaran.type`;
  - `type=piutang` keeps existing `Penjualan` logic;
  - `type=hutang` locks `Pembayaran` + `Pembelian`, totals approved hutang payments by `pembelian_id`, rejects overpayment, and recomputes `Pembelian.status`.
- Updated API generic approve/cancel/uncancel routes to call shared branching and `storeHutang` to reject overpayment before insert.
- Updated Filament hutang creation flows (`PembayaranHutangResource` create page and `Pembelian` relation manager) to use shared service validation.
- Added Filament hutang approve/cancel/uncancel table actions backed by the shared hutang settlement service while preserving admin active-gudang checks and super-admin-only approved cancel behavior.

## Failing-first evidence

Command:

```powershell
php artisan test --filter=PembayaranHutangContractTest
```

Result before service/API fix:

- 3 failed, 2 passed.
- Failures showed generic API approve/cancel still called piutang path and returned `Endpoint ini hanya mendukung pembayaran piutang.` for hutang payments.

## Verification

### Syntax

Command:

```powershell
php -l "app/Services/PaymentSettlementService.php"; if ($?) { php -l "app/Http/Controllers/Api/PembayaranController.php" }; if ($?) { php -l "app/Filament/Resources/Pembelians/RelationManagers/PembayaransRelationManager.php" }; if ($?) { php -l "app/Filament/Resources/PembayaranHutangs/Pages/CreatePembayaranHutang.php" }; if ($?) { php -l "app/Filament/Resources/PembayaranHutangs/Tables/PembayaranHutangsTable.php" }; if ($?) { php -l "tests/Feature/Api/PembayaranHutangContractTest.php" }
```

Result: all checked files reported `No syntax errors detected`.

### LSP diagnostics

Attempted diagnostics on changed PHP files. PHP LSP is not installed and the user previously declined installation, so verification relied on `php -l`, PHPUnit, and Pint.

### Hutang focused tests

Command:

```powershell
php artisan test --filter=PembayaranHutangContractTest
```

Result: PASS — 7 passed, 27 assertions.

### Payment regression tests

Command:

```powershell
php artisan test --filter=Pembayaran
```

Result: PASS — 20 passed, 74 assertions.

### Pint

Initial scoped Pint check found one existing/new style issue in `CreatePembayaranHutang.php`; it was fixed with scoped Pint formatting.

Final command:

```powershell
.\vendor\bin\pint --test "app/Services/PaymentSettlementService.php" "app/Http/Controllers/Api/PembayaranController.php" "app/Filament/Resources/Pembelians/RelationManagers/PembayaransRelationManager.php" "app/Filament/Resources/PembayaranHutangs/Pages/CreatePembayaranHutang.php" "app/Filament/Resources/PembayaranHutangs/Tables/PembayaranHutangsTable.php" "tests/Feature/Api/PembayaranHutangContractTest.php"
```

Result: PASS — 6 files.

## Notes

- No schema changes were made for Task 11.
- Generic API routes now branch by `type`; hutang approval/cancel/uncancel no longer dereference `$pembayaran->penjualan`.
- Existing piutang payment regression coverage still passes after shared branching.

## Task 11 scoped Pint blocker follow-up

Date: 2026-07-09

Scope: style-only fixes in `app/Filament/Resources/PembayaranHutangs/PembayaranHutangResource.php` and `app/Filament/Resources/PembayaranHutangs/Schemas/PembayaranHutangForm.php` for verifier `bg_e46493d7` Pint blockers. No hutang settlement logic was changed.

### Syntax

Command:

```powershell
php -l "app/Filament/Resources/PembayaranHutangs/PembayaranHutangResource.php"; if ($?) { php -l "app/Filament/Resources/PembayaranHutangs/Schemas/PembayaranHutangForm.php" }
```

Result: PASS — `No syntax errors detected` for both checked files.

### LSP diagnostics

Attempted diagnostics on both changed PHP files. PHP LSP is not installed and the user previously declined installation, so verification relied on `php -l`, targeted Pint, and PHPUnit.

### Targeted Pint

Command:

```powershell
./vendor/bin/pint --test "app/Services/PaymentSettlementService.php" "app/Http/Controllers/Api/PembayaranController.php" "app/Filament/Resources/Pembelians/RelationManagers/PembayaransRelationManager.php" "app/Filament/Resources/PembayaranHutangs/Pages/CreatePembayaranHutang.php" "app/Filament/Resources/PembayaranHutangs/Tables/PembayaranHutangsTable.php" "app/Filament/Resources/PembayaranHutangs/PembayaranHutangResource.php" "app/Filament/Resources/PembayaranHutangs/Schemas/PembayaranHutangForm.php" "tests/Feature/Api/PembayaranHutangContractTest.php"
```

Result: PASS — 8 files.

### Hutang focused tests

Command:

```powershell
php artisan test --filter=PembayaranHutangContractTest
```

Result: PASS — 7 passed, 27 assertions. Duration: 1.39s.

### Payment regression tests

Command:

```powershell
php artisan test --filter=Pembayaran
```

Result: PASS — 20 passed, 74 assertions. Duration: 2.71s.
