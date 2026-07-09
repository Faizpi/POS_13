# Task 16 - Recompute API and Filament sales/purchase totals server-side before save

Date: 2026-07-09

## Scope completed

- API `PenjualanController::store()` and `update()` now recompute item rows, `diskon_akhir`, `tax_percentage`, `biaya_pengiriman`, and `grand_total` through `SalesMoneyCalculator`.
- API `PembelianController::store()` and `update()` now recompute item rows, `diskon_akhir`, `tax_percentage`, `biaya_pengiriman`, and `grand_total` through `PurchaseMoneyCalculator`.
- Filament Penjualan create/edit pages recompute sales totals in mutate hooks and push recomputed repeater state back before relationship save.
- Filament Pembelian create/edit pages recompute purchase totals in mutate hooks and push recomputed repeater state back before relationship save.
- Filament form validation tightened for non-negative sales/purchase discounts and purchase tax/discount bounds.
- Contract tests cover tampered `grand_total`, `jumlah_baris`, and sale `harga_satuan` input for API and Filament persistence.

## LSP diagnostics

Command/tool: `lsp_diagnostics` on changed PHP implementation files.

Result:

```text
LSP server 'php' (.php) is NOT INSTALLED; user previously declined installation — proceed without LSP.
```

## Syntax verification

Command:

```powershell
php -l "app\Http\Controllers\Api\PenjualanController.php"; php -l "app\Http\Controllers\Api\PembelianController.php"; php -l "app\Filament\Resources\Penjualans\Pages\CreatePenjualan.php"; php -l "app\Filament\Resources\Penjualans\Pages\EditPenjualan.php"; php -l "app\Filament\Resources\Pembelians\Pages\CreatePembelian.php"; php -l "app\Filament\Resources\Pembelians\Pages\EditPembelian.php"; php -l "app\Filament\Resources\Penjualans\Schemas\PenjualanForm.php"; php -l "app\Filament\Resources\Pembelians\Schemas\PembelianForm.php"; php -l "tests\Feature\Api\PenjualanContractTest.php"; php -l "tests\Feature\Api\PembelianContractTest.php"
```

Result:

```text
No syntax errors detected in app\Http\Controllers\Api\PenjualanController.php
No syntax errors detected in app\Http\Controllers\Api\PembelianController.php
No syntax errors detected in app\Filament\Resources\Penjualans\Pages\CreatePenjualan.php
No syntax errors detected in app\Filament\Resources\Penjualans\Pages\EditPenjualan.php
No syntax errors detected in app\Filament\Resources\Pembelians\Pages\CreatePembelian.php
No syntax errors detected in app\Filament\Resources\Pembelians\Pages\EditPembelian.php
No syntax errors detected in app\Filament\Resources\Penjualans\Schemas\PenjualanForm.php
No syntax errors detected in app\Filament\Resources\Pembelians\Schemas\PembelianForm.php
No syntax errors detected in tests\Feature\Api\PenjualanContractTest.php
No syntax errors detected in tests\Feature\Api\PembelianContractTest.php
```

## Targeted tests

Command:

```powershell
php artisan test --filter=PenjualanContractTest; php artisan test --filter=PembelianContractTest
```

Result:

```text
PASS  Tests\Feature\Api\PenjualanContractTest
Tests: 32 passed (139 assertions)
Duration: 9.73s

PASS  Tests\Feature\Api\PembelianContractTest
Tests: 8 passed (46 assertions)
Duration: 2.54s
```

## Pint

Command:

```powershell
vendor\bin\pint --test "app\Http\Controllers\Api\PenjualanController.php" "app\Http\Controllers\Api\PembelianController.php" "app\Filament\Resources\Penjualans\Pages\CreatePenjualan.php" "app\Filament\Resources\Penjualans\Pages\EditPenjualan.php" "app\Filament\Resources\Pembelians\Pages\CreatePembelian.php" "app\Filament\Resources\Pembelians\Pages\EditPembelian.php" "app\Filament\Resources\Penjualans\Schemas\PenjualanForm.php" "app\Filament\Resources\Pembelians\Schemas\PembelianForm.php" "tests\Feature\Api\PenjualanContractTest.php" "tests\Feature\Api\PembelianContractTest.php"
```

Result:

```text
PASS  10 files
```

## Notes

- No database schema changes were made.
- No migrations were run.
- No servers were started.
- No commits were made.
