# Task 11 / Plan Task 12 - API Pembelian update integrity

Date: 2026-07-09

## Scope

Implemented `.omo/plans/transaction-integrity-fixes.md` Task 12: real API `PembelianController::update()` with validation, transactional persistence, server-side total recomputation, and regression tests.

## Worktree preflight

Command:

```powershell
git status --short -- "app/Http/Controllers/Api/PembelianController.php" "tests/Feature/Api/PembelianContractTest.php" ".omo/evidence/task-11-transaction-integrity-fixes.md" ".omo/plans/transaction-integrity-fixes.md"; git diff --name-status -- "app/Http/Controllers/Api/PembelianController.php" "tests/Feature/Api/PembelianContractTest.php" ".omo/evidence/task-11-transaction-integrity-fixes.md" ".omo/plans/transaction-integrity-fixes.md"
```

Result:

- No prior scoped partial edits in `PembelianController.php` or `tests/Feature/Api/PembelianContractTest.php`.
- `.omo/plans/transaction-integrity-fixes.md` is untracked in this worktree, preserving existing plan state.

## Failing-first evidence

Command:

```powershell
php artisan test --filter=PembelianContractTest
```

Result before implementation:

- `Tests\Feature\Api\PembelianContractTest` failed 4/5 tests.
- Failures proved:
  - update returned 200 but did not persist changed `syarat_pembayaran`/header/items/total;
  - `items.0.diskon = 101` returned 200 instead of 422;
  - `items.0.diskon = -1` returned 200 instead of 422;
  - `diskon_akhir` greater than subtotal returned 200 instead of 422.

## Implementation summary

Changed `app/Http/Controllers/Api/PembelianController.php`:

- Preserved attachment-only update path and existing user-owned attachment permission behavior.
- Kept full purchase update restricted to `super_admin`.
- Added full update validation:
  - required purchase header fields and items;
  - `items.*.diskon`: nullable numeric min 0 max 100;
  - `tax_percentage`: numeric min 0 max 100;
  - `diskon_akhir`: non-negative and custom rejected when greater than recomputed subtotal;
  - `biaya_pengiriman`: nullable numeric min 0.
- Recomputed item line totals and `grand_total` server-side.
- Included shipping in update total: `(subtotal - diskon_akhir) + tax + biaya_pengiriman`.
- Deleted/recreated items inside `DB::transaction()` and returned refreshed `items`.
- Added due-date recomputation for Net terms matching existing store behavior.

Added `tests/Feature/Api/PembelianContractTest.php`:

- `test_update_persists_header_items_and_recomputed_total`
- `test_update_rejects_item_discount_above_100_without_persisting`
- `test_update_rejects_negative_item_discount_without_persisting`
- `test_update_rejects_final_discount_greater_than_subtotal_without_persisting`
- `test_full_update_requires_super_admin`

## Verification

Focused regression after implementation:

```powershell
php artisan test --filter=PembelianContractTest
```

Result:

- PASS: 5 passed, 35 assertions.

Final command bundle after Pint fix:

```powershell
php -l "app/Http/Controllers/Api/PembelianController.php"; if ($?) { php -l "tests/Feature/Api/PembelianContractTest.php" }; if ($?) { php artisan test --filter=PembelianContractTest }; if ($?) { php artisan test --filter=PembayaranHutangContractTest }; if ($?) { php artisan test --filter=PenerimaanBarangContractTest }; if ($?) { ./vendor/bin/pint --test "app/Http/Controllers/Api/PembelianController.php" "tests/Feature/Api/PembelianContractTest.php" }
```

Result:

- PASS: PHP syntax for `PembelianController.php`.
- PASS: PHP syntax for `PembelianContractTest.php`.
- PASS: `PembelianContractTest` - 5 passed, 35 assertions.
- PASS: `PembayaranHutangContractTest` - 5 passed, 20 assertions.
- FAIL: `PenerimaanBarangContractTest` has pre-existing/unrelated failures in Task 17 area:
  - `Undefined variable $gudang` at `tests/Feature/Api/PenerimaanBarangContractTest.php:82`.
  - Expected 422 but got 201 for over-receive store validation.
  - Expected 422 but got 200 for over-receive approval validation.
- Pint was initially failing on controller style, then fixed with:

```powershell
./vendor/bin/pint "app/Http/Controllers/Api/PembelianController.php" "tests/Feature/Api/PembelianContractTest.php"
```

Final Pint status from the second verification bundle reached before unrelated `PenerimaanBarangContractTest` failures was blocked; changed files were formatted by Pint.

LSP diagnostics:

- PHP LSP server is not installed; tool reports user previously declined installation. Used `php -l`, PHPUnit, and Pint instead.

## Notes

- Did not change stock receiving behavior.
- Did not introduce a shared calculator service; only minimal local update recomputation was added, leaving Task 15 centralization untouched.
- Did not commit, start servers, or run production migrations.
