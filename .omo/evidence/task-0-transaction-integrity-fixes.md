# Task 0 - Transaction Integrity Fixes Evidence

## Scope

Task 1 from `.omo/plans/transaction-integrity-fixes.md`: baseline transaction test helpers and evidence setup only. No production application files were changed.

## Changed files

- `tests/Support/BuildsTransactionFixtures.php`
  - Added reusable test-only builders for isolated `Gudang`, `Produk`, `GudangProduk`, `User`, `Kontak`, `Penjualan`, `Pembelian`, `Pembayaran`, `PenerimaanBarang`, and `Kunjungan` records.
  - Added direct API bearer token helper methods for tests.
  - Helpers generate fresh rows and unique numbers/codes instead of relying on seed row IDs.
- `tests/TestCase.php`
  - Mixed the transaction fixture trait into the base test case for future transaction contract tests.
- `database/factories/UserFactory.php`
  - Added role states for `superAdmin()`, `admin($gudang)`, `sales($gudang)`, and `spectator($gudang)` with the proper warehouse pivots where needed.

## Baseline commands

### `php artisan test --filter=PenjualanContractTest`

Result: PASS

- Tests: 16 passed
- Assertions: 42
- Duration: 32.70s

### `php artisan test --filter=StokContractTest`

Result: PASS

- Tests: 8 passed
- Assertions: 21
- Duration: 1.49s

## Verification

- LSP diagnostics attempted for changed PHP files; PHP LSP is not installed in this environment, so diagnostics could not run.
- `php -l tests\Support\BuildsTransactionFixtures.php`: no syntax errors.
- `php -l tests\TestCase.php`: no syntax errors.
- `php -l database\factories\UserFactory.php`: no syntax errors.

## Notes

- PHPUnit is configured for `APP_ENV=testing`, `DB_CONNECTION=sqlite`, and `DB_DATABASE=:memory:` in `phpunit.xml`; no non-test database migrations were run.
- No stock/payment behavior fixes were implemented in this task.
- No pre-existing failures were observed in the two required baseline command filters.
