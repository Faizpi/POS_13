# Task 4 - Align Filament Penjualan approval/cancel with shared stock service

Date: 2026-07-09

## Scope executed

- Read Task 4 in `.omo/plans/transaction-integrity-fixes.md`.
- Updated `app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php` only for Filament Penjualan stock lifecycle alignment.
- Replaced duplicated inline `GudangProduk` stock decrement/restore loops in approve/cancel actions with `InventoryMutationService` calls.
- Preserved existing behavior:
  - `DB::beginTransaction()` / `commit()` / `rollBack()` flow remains in both actions.
  - Admin active gudang authorization checks remain before mutation.
  - Approval still updates `status = Approved` and `approver_id`.
  - Cancel still restores stock only for `Approved` / `Lunas`, then updates `status = Canceled`.
  - Existing notifications and email notification behavior remain.
  - Labels/visual UI unchanged.

## Code changes

- Removed `use App\Models\GudangProduk;` from `ViewPenjualan.php`.
- Added `use App\Services\InventoryMutationService;`.
- Approve action now resolves `InventoryMutationService` and calls:
  - `decrement((int) $gudangId, (int) $item->produk_id, (int) $item->kuantitas, 'penjualan')`
- Cancel action now resolves `InventoryMutationService` and calls:
  - `increment((int) $record->gudang_id, (int) $item->produk_id, (int) $item->kuantitas, 'penjualan')`

## Verification commands and results

### PHP syntax

Command:

```bash
php -l "app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php"; if ($?) { php -l "app/Services/InventoryMutationService.php" }
```

Result:

```text
No syntax errors detected in app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php
No syntax errors detected in app/Services/InventoryMutationService.php
```

### Penjualan regression tests

Command:

```bash
php artisan test --filter=Penjualan
```

Result:

```text
PASS Tests\Feature\Api\PenjualanContractTest
PASS Tests\Feature\PanelBootTest
PASS Tests\Feature\PublicDocumentRoutesTest

Tests: 25 passed (89 assertions)
Duration: 7.61s
```

Coverage rationale: Filament action testing was not added because current Penjualan API parity tests already prove the shared `InventoryMutationService` stock lifecycle for approve/cancel, including single decrement, insufficient-stock rollback, pending cancel no-op, approved cancel restore, and lunas cancel restore.

### Pint formatting

Initial targeted Pint check found one style issue in `ViewPenjualan.php` after the edit. Ran targeted Pint fix, then re-ran targeted check.

Command:

```bash
./vendor/bin/pint --test "app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php" "app/Services/InventoryMutationService.php"
```

Final result:

```text
PASS 2 files
```

### Adversarial duplicate-stock-loop check

Command:

```text
grep pattern: GudangProduk|decrement\('stok|increment\('stok|lockForUpdate\(\)
path: app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php
```

Result:

```text
No matches found
```

Interpretation: `ViewPenjualan.php` no longer contains direct `GudangProduk` access, inline `lockForUpdate()` stock row lookup, or direct `stok` decrement/increment loops for approve/cancel.

### LSP diagnostics

Command/tool:

```text
lsp_diagnostics app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php
```

Result:

```text
LSP server 'php' (.php) is NOT INSTALLED; user previously declined installation — proceed without LSP.
```

Fallback verification used: PHP syntax check, Penjualan regression tests, and targeted Pint.

## Outcome

Task 4 acceptance criteria satisfied:

- Filament Penjualan approve/cancel now uses `InventoryMutationService` for saleable stock mutation.
- API and Filament share the same stock mutation service for Penjualan lifecycle.
- No duplicated inline stock decrement/restore loop remains in `ViewPenjualan.php`.
- Required targeted tests and formatting checks pass.
