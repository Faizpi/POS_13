# Task 3 Evidence - API Penjualan approve/cancel stock lifecycle

Date: 2026-07-09

## Scope

- Updated `app/Http/Controllers/Api/PenjualanController.php::approve()` to mutate saleable stock through `InventoryMutationService` inside `DB::transaction()` before setting status `Approved`.
- Updated `cancel()` to run inside `DB::transaction()` and restore saleable stock only when previous status is `Approved` or `Lunas`.
- Added regression coverage in `tests/Feature/Api/PenjualanContractTest.php` for exact-once approve/cancel stock mutation, insufficient stock rejection, and pending cancel without stock mutation.
- Did not edit payment logic or Filament pages.

## Failing-first check

Command:

```bash
php artisan test --filter=PenjualanContractTest
```

Result before production change: FAILED as expected.

Observed failures proved existing bug:

- `approve_decrements_saleable_stock_exactly_once`: stock stayed `10`, expected `7`.
- `approve_rejects_insufficient_stock_without_status_or_stock_changes`: approval returned `200`, expected `422`.
- `cancel_approved_restores_saleable_stock_exactly_once`: stock stayed `7`, expected `10`.
- `cancel_lunas_restores_saleable_stock`: stock stayed `7`, expected `10`.

## Final verification

Command:

```bash
php -l "app/Http/Controllers/Api/PenjualanController.php"; if ($?) { php -l "tests/Feature/Api/PenjualanContractTest.php" }
```

Result: PASS

```text
No syntax errors detected in app/Http/Controllers/Api/PenjualanController.php
No syntax errors detected in tests/Feature/Api/PenjualanContractTest.php
```

Command:

```bash
./vendor/bin/pint --test "app/Http/Controllers/Api/PenjualanController.php" "tests/Feature/Api/PenjualanContractTest.php"
```

Initial result: FAILED with 2 style issues, then fixed by scoped Pint run:

```bash
./vendor/bin/pint "app/Http/Controllers/Api/PenjualanController.php" "tests/Feature/Api/PenjualanContractTest.php"
```

Final result: PASS

```text
PASS  2 files
```

Command:

```bash
php artisan test --filter=PenjualanContractTest
```

Result: PASS

```text
Tests: 21 passed (69 assertions)
Duration: 5.81s
```

LSP diagnostics:

```text
LSP server 'php' (.php) is NOT INSTALLED; user previously declined installation — proceed without LSP.
```

## Adversarial checks

- Approve exact-once: first approve decrements `gudang_produk.stok` and `stok_penjualan` from `10` to `7`; second approve returns `422` and stock remains `7`.
- Insufficient stock: approve with sale quantity `3` and saleable stock `2` returns `422` message `Stok penjualan tidak cukup. Tersedia 2, diminta 3.`, sale remains `Pending`, stock remains unchanged.
- Pending cancel: canceling `Pending` sale only changes status to `Canceled`; stock remains `10`/`10`.
- Approved cancel exact-once: canceling previously approved sale restores stock from `7` to `10`; repeated cancel leaves stock at `10`.
- Lunas cancel: canceling `Lunas` sale restores stock from `7` to `10`.
- Authorization guardrails preserved: existing admin/current-gudang/user ownership tests still pass in `PenjualanContractTest`.
