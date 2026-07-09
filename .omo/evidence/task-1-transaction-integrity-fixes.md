# Task 1 / Plan Task 2 - Locked inventory mutation service

Date: 2026-07-09

## Scope executed

- Added `app/Services/InventoryMutationService.php`.
- Added direct service coverage in `tests/Feature/Services/InventoryMutationServiceTest.php`.
- Did not edit API/Filament controllers or pages in this task.

## Implementation notes

- Service API:
  - `decrement(int $gudangId, int $produkId, int $quantity, string $stockType): GudangProduk`
  - `increment(int $gudangId, int $produkId, int $quantity, string $stockType): GudangProduk`
- Stock type mapping:
  - saleable aliases (`penjualan`, `saleable`, `sales`, `sale`, `receipt`, `penerimaan`) mutate `stok_penjualan` plus total `stok`.
  - promo/free aliases (`gratis`, `promo`, `free`) mutate `stok_gratis` plus total `stok`.
  - `sample` mutates `stok_sample` plus total `stok`.
- Existing rows are selected with `lockForUpdate()` before mutation.
- Decrement rejects missing stock rows and insufficient subtype/total stock before saving, so failed decrements leave no partial mutation.
- Increment can create a missing `GudangProduk` row for receipt/restoration-style flows, initialized with zero subtype columns before applying the mutation.
- Invalid stock type and non-positive quantity throw `InvalidArgumentException`; missing/insufficient stock throws `DomainException`.

## Failing-first result

Command:

```bash
php artisan test --filter=InventoryMutation
```

Initial result before service implementation:

- Failed as expected: `Class "App\\Services\\InventoryMutationService" not found`.
- 5 tests failed, 0 assertions.

## Verification commands/results

Command:

```bash
php artisan test --filter=InventoryMutation
```

Result after implementation:

- PASS `Tests\\Feature\\Services\\InventoryMutationServiceTest`
- 5 passed, 12 assertions.

Command:

```bash
php artisan test --filter=PenjualanContractTest
```

Result:

- PASS `Tests\\Feature\\Api\\PenjualanContractTest`
- 16 passed, 42 assertions.

Command:

```bash
php -l "app\\Services\\InventoryMutationService.php"; if ($?) { php -l "tests\\Feature\\Services\\InventoryMutationServiceTest.php" }; if ($?) { .\\vendor\\bin\\pint --test "app\\Services\\InventoryMutationService.php" "tests\\Feature\\Services\\InventoryMutationServiceTest.php" }
```

Result:

- PASS PHP syntax checks for both changed PHP files.
- PASS Pint targeted style check for both changed PHP files.
- LSP diagnostics attempted for changed PHP files; PHP LSP server is not installed and was previously declined, so verification relied on PHPUnit, PHP syntax, and Pint.

## Adversarial checks

- Insufficient `stok_penjualan` decrement requested more than available and asserted all four stock columns remained unchanged.
- Missing stock row decrement asserted no `gudang_produk` row was created.
- Valid decrement/increment asserted only the requested subtype and total `stok` changed; other subtype columns remained unchanged.
- Receipt increment against a missing stock row asserted a new row was created with total and `stok_penjualan` updated, while promo/sample columns stayed zero.
- Invalid stock type asserted mutation is rejected before stock values change.
