# Task 18 - Penerimaan Barang over-receive and locked approval stock additions

Date: 2026-07-09

## Scope

Executed Task 18 from `.omo/plans/transaction-integrity-fixes.md`.

Changed scoped receiving flow files:

- `app/Http/Controllers/Api/PenerimaanBarangController.php`
- `app/Filament/Resources/PenerimaanBarangs/Pages/CreatePenerimaanBarang.php`
- `app/Filament/Resources/PenerimaanBarangs/Pages/ViewPenerimaanBarang.php`
- `tests/Feature/Api/PenerimaanBarangContractTest.php`

Preserved existing Task 9 stock log behavior by routing approval stock additions through `InventoryMutationService::increment()` with `transaction_type`, `transaction_id`, and `transaction_nomor` context.

## Worktree preflight

Ran:

```bash
git status --short
git diff -- app/Http/Controllers/Api/PenerimaanBarangController.php tests/Feature/Api/PenerimaanBarangContractTest.php app/Filament .omo/evidence/task-17-transaction-integrity-fixes.md .omo/plans/transaction-integrity-fixes.md
git diff --name-only -- app/Http/Controllers/Api/PenerimaanBarangController.php "app/Filament/Resources/PenerimaanBarangs" tests/Feature/Api/PenerimaanBarangContractTest.php .omo/evidence/task-17-transaction-integrity-fixes.md
git diff --stat -- app/Http/Controllers/Api/PenerimaanBarangController.php "app/Filament/Resources/PenerimaanBarangs" tests/Feature/Api/PenerimaanBarangContractTest.php .omo/evidence/task-17-transaction-integrity-fixes.md
```

Observed partial prior work in receiving files from the canceled attempt, including stock log additions and delete guard edits. Kept valid stock log/delete guard work and layered Task 18 quantity/locking logic on top.

## Failing-first proof

Added tests before production changes:

- `test_api_store_rejects_qty_diterima_beyond_remaining_purchase_quantity`
- `test_sequential_pending_receipts_cannot_both_approve_beyond_purchase_remaining_quantity`

Initial run before the fix:

```bash
php artisan test --filter=PenerimaanBarangContractTest
```

Result: failed as expected.

- API store over-receive returned `201` instead of `422`.
- Sequential pending second approval returned `200` instead of `422`.

## Implementation summary

- API `store()` now locks the target `Pembelian` and its items inside the transaction, computes remaining PO quantity from approved receipts, and rejects over-receive with validation errors before creating receipt rows or stock mutations.
- API `approve()` now re-locks the receipt row, locks the purchase/items, revalidates remaining quantity at approval time, then updates status and increments stock inside the same transaction.
- Filament create flow now performs the same server-side remaining quantity validation inside `handleRecordCreation()` per PO group; it does not rely on form prefill/filtering.
- Filament view approve action now locks the receipt and purchase state, rejects stale/over-receive approvals, and increments stock through `InventoryMutationService`.
- Approval additions now use the locked mutation service and preserve stock subtype behavior (`penjualan`, `gratis`, `sample`) plus existing stock log context.

Task 19 cancel/edit reversal clamp was intentionally not fixed.

## Verification

Syntax:

```bash
php -l "app/Http/Controllers/Api/PenerimaanBarangController.php"
php -l "app/Filament/Resources/PenerimaanBarangs/Pages/CreatePenerimaanBarang.php"
php -l "app/Filament/Resources/PenerimaanBarangs/Pages/ViewPenerimaanBarang.php"
php -l "tests/Feature/Api/PenerimaanBarangContractTest.php"
```

Result: all reported `No syntax errors detected`.

Targeted tests:

```bash
php artisan test --filter=PenerimaanBarangContractTest
```

Result: PASS, 4 tests / 28 assertions.

Broader receiving filter:

```bash
php artisan test --filter=PenerimaanBarang
```

Result: PASS, 4 tests / 28 assertions.

Pint:

```bash
./vendor/bin/pint --test "app/Http/Controllers/Api/PenerimaanBarangController.php" "app/Filament/Resources/PenerimaanBarangs/Pages/CreatePenerimaanBarang.php" "app/Filament/Resources/PenerimaanBarangs/Pages/ViewPenerimaanBarang.php" "tests/Feature/Api/PenerimaanBarangContractTest.php"
```

Result: PASS, 4 files.

LSP diagnostics:

```text
lsp_diagnostics app/Http/Controllers/Api/PenerimaanBarangController.php
```

Result: PHP LSP server is not installed and was previously declined, so diagnostics could not run. Syntax, tests, and Pint passed.
