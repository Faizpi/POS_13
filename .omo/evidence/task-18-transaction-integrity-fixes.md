# Task 19 Evidence - Receiving cancel/edit reversal without silent clamp

Date: 2026-07-09

## Scope

Implemented Task 19 from `.omo/plans/transaction-integrity-fixes.md`:

- Removed silent `max(0, ...)` stock reversal behavior from API and Filament receiving cancel/edit paths.
- API cancel now locks the receipt row, reverses approved receipt stock through `InventoryMutationService::decrement()`, returns `422` on insufficient stock, and leaves receipt/stock unchanged.
- Filament cancel now uses the same locked decrement behavior and reports a danger notification when reversal is impossible.
- Filament approved edit now updates the record, reverses old items, replaces items, and applies new items inside one DB transaction. If reversing old approved stock is impossible, the record update, item replacement, stock mutation, and logs all roll back.
- Task 18 over-receive validation and Task 9 `StokLog` behavior were preserved by continuing to use existing over-receive checks and `InventoryMutationService` logging context.

## Tests added

`tests/Feature/Api/PenerimaanBarangContractTest.php` now covers:

- Cancel approved receipt with enough stock subtracts exactly from total and subtype stock.
- Cancel approved receipt with insufficient current stock returns `422`, preserves `Approved` status, preserves stock, and creates no stock log.
- Filament approved edit applies exact stock delta atomically (`current - old + new`).
- Filament approved edit with insufficient current stock fails and rolls back record fields, items, stock, and logs.

## Failing-first evidence

Initial focused run after adding cancel regression tests failed before the production fix:

```text
php artisan test --filter=PenerimaanBarangContractTest
FAIL Tests\Feature\Api\PenerimaanBarangContractTest
✓ cancel approved receipt subtracts exact received stock
⨯ cancel approved receipt with insufficient current stock is rejected without mutation
Expected response status code [422] but received 200.
Tests: 1 failed, 5 passed
```

This confirmed the existing cancel path silently clamped stock instead of rejecting impossible reversal.

## Verification

LSP diagnostics were attempted for all changed PHP files, but PHP LSP is not installed in this environment and was previously declined:

```text
LSP server 'php' (.php) is NOT INSTALLED; user previously declined installation — proceed without LSP.
```

Final verification commands:

```text
php artisan test --filter=PenerimaanBarangContractTest
PASS Tests\Feature\Api\PenerimaanBarangContractTest
Tests: 8 passed (50 assertions)

php -l app/Http/Controllers/Api/PenerimaanBarangController.php
No syntax errors detected

php -l app/Filament/Resources/PenerimaanBarangs/Pages/ViewPenerimaanBarang.php
No syntax errors detected

php -l app/Filament/Resources/PenerimaanBarangs/Pages/EditPenerimaanBarang.php
No syntax errors detected

php -l tests/Feature/Api/PenerimaanBarangContractTest.php
No syntax errors detected

php artisan test --filter=PenerimaanBarang
PASS Tests\Feature\Api\PenerimaanBarangContractTest
Tests: 8 passed (50 assertions)

./vendor/bin/pint --test app/Http/Controllers/Api/PenerimaanBarangController.php app/Filament/Resources/PenerimaanBarangs/Pages/ViewPenerimaanBarang.php app/Filament/Resources/PenerimaanBarangs/Pages/EditPenerimaanBarang.php tests/Feature/Api/PenerimaanBarangContractTest.php
PASS 4 files
```
