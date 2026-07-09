# Task 7 / Evidence for task-6-transaction-integrity-fixes

Task: Block or safely replace hard deletes for stock/money transactions (B06).
Date: 2026-07-09

## Scope audited

Filament delete/bulk-delete action usage was inspected for:

- `app/Filament/Resources/Penjualans/Tables/PenjualansTable.php`
- `app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php`
- `app/Filament/Resources/Penjualans/Pages/EditPenjualan.php`
- `app/Filament/Resources/Pembelians/Tables/PembeliansTable.php`
- `app/Filament/Resources/Pembelians/Pages/ViewPembelian.php`
- `app/Filament/Resources/Pembelians/Pages/EditPembelian.php`
- `app/Filament/Resources/Pembelians/RelationManagers/PembayaransRelationManager.php`
- `app/Filament/Resources/Pembayarans/Tables/PembayaransTable.php`
- `app/Filament/Resources/Pembayarans/Pages/ViewPembayaran.php`
- `app/Filament/Resources/Pembayarans/Pages/EditPembayaran.php`
- `app/Filament/Resources/PembayaranHutangs/Tables/PembayaranHutangsTable.php`
- `app/Filament/Resources/PembayaranHutangs/Pages/EditPembayaranHutang.php`
- `app/Filament/Resources/PenerimaanBarangs/Tables/PenerimaanBarangsTable.php`
- `app/Filament/Resources/PenerimaanBarangs/Pages/ViewPenerimaanBarang.php`
- `app/Filament/Resources/PenerimaanBarangs/Pages/EditPenerimaanBarang.php`
- `app/Filament/Resources/Kunjungans/Tables/KunjungansTable.php`
- `app/Filament/Resources/Kunjungans/Pages/ViewKunjungan.php`
- `app/Filament/Resources/Kunjungans/Pages/EditKunjungan.php`

## Implementation

Added `app/Filament/Concerns/TransactionDeleteGuard.php` and wired all scoped transaction delete actions through it.

Policy implemented:

- Hard delete is visible only to super admin **and** only for side-effect-free statuses: `Pending`, `Rejected`, `Canceled`.
- `Approved` / `Lunas` records are hidden from DeleteAction in the scoped Filament table/view/edit surfaces.
- Bulk delete actions for scoped stock/money transaction tables are hidden (`visible(false)`) to avoid mixed selection deleting protected records.
- No stock reversal was implemented here.
- No API controller/service delete behavior was changed.

## Adversarial checks

- Approved/Lunas Penjualan: blocked from table/view/edit delete visibility because `TransactionDeleteGuard::canDeletePenjualan()` returns false.
- Approved/Lunas Pembelian: blocked from table/view/edit delete visibility because `TransactionDeleteGuard::canDeletePembelian()` returns false.
- Approved Pembayaran piutang/hutang: blocked from tables, view/edit pages, and Pembelian relation manager because `TransactionDeleteGuard::canDeletePembayaran()` returns false.
- Approved PenerimaanBarang: blocked from table/view/edit delete visibility because `TransactionDeleteGuard::canDeletePenerimaanBarang()` returns false.
- Approved Kunjungan: blocked from table/view/edit delete visibility because `TransactionDeleteGuard::canDeleteKunjungan()` returns false.
- Pending transaction records remain manageable because all guard methods return true for `Pending`.
- Canceled/Rejected transaction records remain manageable because all guard methods return true for `Canceled`/`Rejected`.
- Bulk delete cannot hard-delete a mixed selection containing approved/lunas/stock-affecting rows because scoped bulk delete actions are hidden.

## Tests added

Updated `tests/Feature/FilamentDetailPagesTest.php` with:

- `test_transaction_delete_guard_blocks_side_effect_records`
- `test_transaction_delete_guard_allows_pending_records`

## Command results

### PHP syntax

Command:

```powershell
php -l <20 changed PHP files>
```

Result:

```text
0 syntax errors found in 20 files
```

### FilamentDetailPagesTest

Command:

```powershell
php artisan test --filter=FilamentDetailPagesTest
```

Result:

```text
PASS Tests\Feature\FilamentDetailPagesTest
✓ super admin can render custom detail pages
✓ transaction delete guard blocks side effect records
✓ transaction delete guard allows pending records
Tests: 3 passed (18 assertions)
```

### PanelBootTest

Command:

```powershell
php artisan test --filter=PanelBootTest
```

Result:

```text
PASS Tests\Feature\PanelBootTest
Tests: 25 passed (45 assertions)
```

### Combined required test filter after Pint formatting

Command:

```powershell
php artisan test --filter="FilamentDetailPagesTest|PanelBootTest"
```

Result:

```text
PASS Tests\Feature\FilamentDetailPagesTest
PASS Tests\Feature\PanelBootTest
Tests: 28 passed (63 assertions)
Duration: 7.00s
```

### Pint

Initial `pint --test` found pre-existing/scoped style issues in 8 files; ran Pint on scoped changed files, then re-ran test mode.

Command:

```powershell
vendor\bin\pint --test <20 changed PHP files>
```

Result:

```text
PASS 20 files
```

### LSP diagnostics

Command:

```text
lsp_diagnostics app/Filament/Concerns/TransactionDeleteGuard.php
```

Result:

```text
LSP server 'php' (.php) is NOT INSTALLED; user previously declined installation — proceeded with PHP syntax checks, PHPUnit, and Pint.
```
