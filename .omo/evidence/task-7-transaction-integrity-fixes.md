# Task 8 / Evidence file task-7 - Kunjungan promo/sample stock lifecycle parity

## Scope executed

- Read plan Task 8 in `.omo/plans/transaction-integrity-fixes.md`.
- Scoped files read before edit:
  - `app/Http/Controllers/Api/KunjunganController.php`
  - `app/Filament/Resources/Kunjungans/Pages/ViewKunjungan.php`
  - `app/Filament/Resources/Kunjungans/Schemas/KunjunganForm.php`
  - `app/Services/InventoryMutationService.php`
  - Existing test context under `tests/Support/BuildsTransactionFixtures.php` and API contract tests.
- Added API regression coverage in `tests/Feature/Api/KunjunganContractTest.php`.
- Updated API and Filament approval/cancel paths to use `InventoryMutationService` inside DB transactions.

## Failing-first result

Command:

```powershell
php artisan test --filter=KunjunganContractTest
```

Initial result before production fix:

- 4 failed, 2 passed.
- Failures proved current gaps:
  - Promo Gratis approval did not decrement `gudang_produk.stok` or `stok_gratis`.
  - Promo Sample approval did not decrement `gudang_produk.stok` or `stok_sample`.
  - Insufficient promo stock approval returned 200 instead of 422.
  - Super-admin auto-approved Promo Gratis store did not decrement stock.

## Implementation notes

- API `store()` now decrements promo/sample stock for super-admin auto-approved promo/sample visits before commit.
- API `approve()` now wraps approval in `DB::transaction()` and decrements promo stock with `InventoryMutationService` before status becomes `Approved`.
- API `cancel()` now restores promo stock only when the previous status is `Approved`; pending cancel changes status only.
- Filament `ViewKunjungan` approve/cancel now uses the same service for promo/sample stock mutation.
- Non-promo visits and `Pemeriksaan Stock` remain status-only and do not mutate inventory.
- Service errors (`DomainException` / `InvalidArgumentException`) reject with 422 in API paths and rollback stock/status mutations.

## Adversarial checks encoded in tests

`tests/Feature/Api/KunjunganContractTest.php` covers:

1. Promo Gratis approval decrements total `stok` and `stok_gratis` only.
2. Promo Sample approval decrements total `stok` and `stok_sample` only.
3. Canceling an approved promo restores stock exactly once; canceling pending promo does not mutate stock.
4. Insufficient stock on multi-item approval returns 422 and leaves earlier item stock/status unchanged (rollback/no partial mutation).
5. Super-admin auto-approved promo store decrements stock atomically.
6. `Pemeriksaan Stock` and non-promo approval do not mutate inventory.

## Final verification

LSP diagnostics:

- PHP LSP unavailable: `LSP server 'php' (.php) is NOT INSTALLED; user previously declined installation — proceed without LSP.`

Final command:

```powershell
php -l "app/Http/Controllers/Api/KunjunganController.php"; if ($?) { php -l "app/Filament/Resources/Kunjungans/Pages/ViewKunjungan.php" }; if ($?) { php -l "tests/Feature/Api/KunjunganContractTest.php" }; if ($?) { php artisan test --filter=KunjunganContractTest }; if ($?) { php artisan test --filter=InventoryMutationServiceTest }; if ($?) { ./vendor/bin/pint --test "app/Http/Controllers/Api/KunjunganController.php" "app/Filament/Resources/Kunjungans/Pages/ViewKunjungan.php" "tests/Feature/Api/KunjunganContractTest.php" }
```

Final result:

- PHP syntax: no syntax errors in all 3 changed PHP files.
- `KunjunganContractTest`: PASS, 6 tests / 24 assertions.
- `InventoryMutationServiceTest`: PASS, 5 tests / 12 assertions.
- Pint scoped check: PASS, 3 files.

## Guardrails confirmed

- No sales/payment logic edited.
- No stock mutation for non-promo visits.
- No stock mutation for `Pemeriksaan Stock` visits.
- No silent clamp introduced.
- Insufficient stock rejects and rolls back all stock/status changes.
- No commit performed.
