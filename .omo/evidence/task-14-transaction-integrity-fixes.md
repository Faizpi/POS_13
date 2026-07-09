# Task 15 - Money calculation services for sales and purchases

## Scope
- Plan task: T15 from `.omo/plans/transaction-integrity-fixes.md`.
- Audit findings addressed in foundation layer: B04, B10, B11, B12, B16, B17.
- Constraint observed: calculators are pure services only; no controller/Filament integration, DB writes, migrations, commits, or local servers.

## Files created
- `app/Services/MoneyCalculator.php`
  - Shared decimal-safe integer calculator base.
  - Parses money/quantity/rate inputs into scaled integers before arithmetic.
  - Calculates line totals, subtotal, final discount, tax, shipping, and grand total with deterministic half-up rounding to 2 money decimals.
  - Rejects invalid/negative values, discount > 100%, item discount exceeding line gross, and final discount exceeding subtotal.
- `app/Services/SalesMoneyCalculator.php`
  - Sales-specific unit price resolution.
  - Supports explicit `harga_satuan`, retail fallback, and `grosir` price selection.
- `app/Services/PurchaseMoneyCalculator.php`
  - Purchase-specific unit price resolution.
  - Supports explicit purchase price fields including `harga_satuan` and `harga_beli`.
- `tests/Unit/Services/SalesMoneyCalculatorTest.php`
- `tests/Unit/Services/PurchaseMoneyCalculatorTest.php`

## Covered scenarios
- Quantity * unit price line totals.
- Percentage item discount.
- Nominal item discount.
- Subtotal aggregation.
- Final discount.
- Tax calculation and tax rounding.
- Shipping inclusion in grand total.
- Zero quantity and zero price.
- Negative discount rejection.
- Discount percentage above 100 rejection.
- Final discount exceeding subtotal rejection.
- Invalid money input rejection instead of silent zero fallback.
- Float input rejection at the parser level before any normalization/conversion.
- Sales retail/grosir price resolution.
- Decimal precision cases such as `0.10 * 3` and `0.05 * 10%` without float drift in public results.

## Verification

### PHP syntax
Command:
```powershell
php -l "app\Services\MoneyCalculator.php"; if ($?) { php -l "app\Services\SalesMoneyCalculator.php" }; if ($?) { php -l "app\Services\PurchaseMoneyCalculator.php" }; if ($?) { php -l "tests\Unit\Services\SalesMoneyCalculatorTest.php" }; if ($?) { php -l "tests\Unit\Services\PurchaseMoneyCalculatorTest.php" }
```
Result:
```text
No syntax errors detected in app\Services\MoneyCalculator.php
No syntax errors detected in app\Services\SalesMoneyCalculator.php
No syntax errors detected in app\Services\PurchaseMoneyCalculator.php
No syntax errors detected in tests\Unit\Services\SalesMoneyCalculatorTest.php
No syntax errors detected in tests\Unit\Services\PurchaseMoneyCalculatorTest.php
```

### Unit tests
Command:
```powershell
php artisan test --testsuite=Unit
```
Result:
```text
PASS Tests\Unit\ExampleTest
✓ that true is true

PASS Tests\Unit\Services\PurchaseMoneyCalculatorTest
✓ line total applies quantity percent discount and nominal discount
✓ purchase totals include item discount final discount tax shipping and grand total
✓ zero quantity and zero price are supported
✓ negative final discount is rejected
✓ discount percentage above one hundred is rejected
✓ tax rounding is deterministic to two decimals
✓ invalid money input is rejected instead of silently becoming zero
✓ float inputs are rejected at parser level

PASS Tests\Unit\Services\SalesMoneyCalculatorTest
✓ line total applies quantity percent discount and nominal discount
✓ totals include subtotal final discount tax shipping and grand total
✓ sales calculator can resolve retail and grosir prices
✓ zero quantity and zero price return zero totals
✓ negative item discount is rejected
✓ negative nominal discount is rejected
✓ tax rounding uses half up cents without float drift
✓ final discount cannot exceed subtotal
✓ float inputs are rejected at parser level

Tests: 18 passed (46 assertions)
Duration: 0.41s
```

### Pint
Command:
```powershell
./vendor/bin/pint --test "app/Services/MoneyCalculator.php" "app/Services/SalesMoneyCalculator.php" "app/Services/PurchaseMoneyCalculator.php" "tests/Unit/Services/SalesMoneyCalculatorTest.php" "tests/Unit/Services/PurchaseMoneyCalculatorTest.php"
```
Result:
```text
.....

──────────────────────────────────────────────────────────────────────────────────────────────────────────── Laravel
  PASS   ................................................................................................... 5 files
```

### LSP diagnostics
Command:
```text
lsp_diagnostics app/Services/MoneyCalculator.php
lsp_diagnostics tests/Unit/Services/SalesMoneyCalculatorTest.php
lsp_diagnostics tests/Unit/Services/PurchaseMoneyCalculatorTest.php
```
Result:
```text
LSP server 'php' (.php) is NOT INSTALLED; user previously declined installation — proceeded with PHP syntax checks, Unit tests, and Pint.
```
