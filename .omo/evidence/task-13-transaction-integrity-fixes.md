# Task 14 Evidence - Normalize stock-affecting lifecycle

Date: 2026-07-09

## Scope

Normalized and locked the cancel/delete lifecycle for stock-affecting records:

- `Penjualan`
- promo `Kunjungan` (`Promo Gratis`, `Promo Sample`)
- `PenerimaanBarang`

No new business statuses were introduced. Money calculators were not changed.

## Status transition matrix

| Record | Current status | Action | Result status | Stock effect | Validation / guard |
| --- | --- | --- | --- | --- | --- |
| Penjualan | Pending | approve | Approved | decrement `stok` + `stok_penjualan` | requires sufficient saleable stock; rejects with 422 and leaves Pending/no mutation |
| Penjualan | Pending | cancel | Canceled | none | owner/admin/super_admin rules apply |
| Penjualan | Pending | hard delete | deleted | none | allowed by `TransactionDeleteGuard::canDeletePenjualan()` because Pending is side-effect-free |
| Penjualan | Approved | cancel | Canceled | safe reversal: increment `stok` + `stok_penjualan` once | duplicate cancel now rejected with 422 before a second reversal |
| Penjualan | Lunas | cancel | Canceled | safe reversal: increment `stok` + `stok_penjualan` once | duplicate cancel rejected with 422 |
| Penjualan | Approved/Lunas | hard delete | blocked | none | Filament `DeleteAction` hidden by `TransactionDeleteGuard` |
| Penjualan | Canceled | uncancel | Pending | none | super_admin only; next approval is required and re-runs stock validation |
| Kunjungan promo | Pending | approve | Approved | decrement `stok` + `stok_gratis` or `stok_sample` | requires sufficient promo/sample stock; rejects with 422 and leaves Pending/no partial mutation |
| Kunjungan promo | Pending | cancel | Canceled | none | admin can cancel Pending for own gudang; super_admin can cancel |
| Kunjungan promo | Pending | hard delete | deleted | none | allowed by `TransactionDeleteGuard::canDeleteKunjungan()` because Pending is side-effect-free |
| Kunjungan promo | Approved | cancel | Canceled | safe reversal: increment matching promo stock once | duplicate cancel rejected with 422 |
| Kunjungan promo | Approved | hard delete | blocked | none | Filament `DeleteAction` hidden by `TransactionDeleteGuard` |
| Kunjungan promo | Canceled | uncancel | Pending | none | super_admin only; next approval is required and re-runs promo stock validation |
| PenerimaanBarang | Pending | approve | Approved | increment `stok` + selected stock bucket | validates remaining PO quantity under locks; rejects over-receive |
| PenerimaanBarang | Pending | cancel | Canceled | none | admin/super_admin rules apply |
| PenerimaanBarang | Pending | hard delete | deleted | none | allowed by `TransactionDeleteGuard::canDeletePenerimaanBarang()` because Pending is side-effect-free |
| PenerimaanBarang | Approved | cancel | Canceled | safe reversal: decrement exact received stock | rejects impossible reversal if current stock is insufficient; no silent clamp |
| PenerimaanBarang | Approved | hard delete | blocked | none | Filament `DeleteAction` hidden by `TransactionDeleteGuard` |
| PenerimaanBarang | Canceled | uncancel | Pending | none | super_admin only; next approval is required and re-runs remaining-PO validation |

## Code changes

- `app/Http/Controllers/Api/PenjualanController.php`
  - Added explicit `Canceled` guard before and inside cancel transaction so duplicate cancel cannot perform a second stock reversal.
- `app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php`
  - Added matching `Canceled` guard in cancel action.
- `app/Filament/Resources/Kunjungans/Pages/ViewKunjungan.php`
  - Added matching `Canceled` guard in cancel action.
- `app/Filament/Resources/PenerimaanBarangs/Pages/ViewPenerimaanBarang.php`
  - Added matching `Canceled` guard in cancel action.
- `tests/Feature/Api/PenjualanContractTest.php`
  - Added uncancel lifecycle coverage: uncancel returns Pending, does not reapply stock, and approval still validates stock.
  - Tightened duplicate cancel expectation to 422/no second stock reversal.
- `tests/Feature/Api/KunjunganContractTest.php`
  - Added promo uncancel lifecycle coverage: uncancel returns Pending, does not reapply promo stock, and approval still validates promo stock.
- `tests/Feature/Api/PenerimaanBarangContractTest.php`
  - Added pending cancel/no stock mutation coverage.
  - Added uncancel lifecycle coverage: uncancel returns Pending, does not reapply receiving stock, and approval still validates remaining PO quantity.

## Hard delete guard evidence

Existing guard remains the lifecycle source for Filament delete actions:

- `app/Filament/Concerns/TransactionDeleteGuard.php`
- Approved/Lunas stock/money side-effect records are blocked because only `Pending`, `Rejected`, and `Canceled` are deletable.
- Pending records remain deletable because they have no stock effect.
- Existing coverage: `tests/Feature/FilamentDetailPagesTest.php::test_transaction_delete_guard_blocks_side_effect_records` and `test_transaction_delete_guard_allows_pending_records`.

## Verification transcript

### Focused contract tests

Command:

```powershell
php artisan test --filter=PenjualanContractTest; if ($?) { php artisan test --filter=KunjunganContractTest }; if ($?) { php artisan test --filter=PenerimaanBarangContractTest }
```

Result:

```text
PASS Tests\Feature\Api\PenjualanContractTest
Tests: 26 passed (112 assertions)

PASS Tests\Feature\Api\KunjunganContractTest
Tests: 7 passed (33 assertions)

PASS Tests\Feature\Api\PenerimaanBarangContractTest
Tests: 10 passed (64 assertions)
```

### PHP syntax

Command:

```powershell
php -l "app/Http/Controllers/Api/PenjualanController.php"; if ($?) { php -l "app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php" }; if ($?) { php -l "app/Filament/Resources/Kunjungans/Pages/ViewKunjungan.php" }; if ($?) { php -l "app/Filament/Resources/PenerimaanBarangs/Pages/ViewPenerimaanBarang.php" }; if ($?) { php -l "tests/Feature/Api/PenjualanContractTest.php" }; if ($?) { php -l "tests/Feature/Api/KunjunganContractTest.php" }; if ($?) { php -l "tests/Feature/Api/PenerimaanBarangContractTest.php" }
```

Result:

```text
No syntax errors detected in all 7 changed PHP files.
```

### Pint

Command:

```powershell
./vendor/bin/pint --test "app/Http/Controllers/Api/PenjualanController.php" "app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php" "app/Filament/Resources/Kunjungans/Pages/ViewKunjungan.php" "app/Filament/Resources/PenerimaanBarangs/Pages/ViewPenerimaanBarang.php" "tests/Feature/Api/PenjualanContractTest.php" "tests/Feature/Api/KunjunganContractTest.php" "tests/Feature/Api/PenerimaanBarangContractTest.php"
```

Result:

```text
PASS 7 files
```

### LSP diagnostics

Attempted on all changed PHP files. PHP LSP is not installed in this environment and was previously declined, so diagnostics could not run. PHP syntax, focused contract tests, and Pint passed.
