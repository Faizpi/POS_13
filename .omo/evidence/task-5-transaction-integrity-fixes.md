# Task 5/6 Evidence - Payment cancel/uncancel/status recomputation

Plan item: `.omo/plans/transaction-integrity-fixes.md` Task 6.

## Scope changed

- Added API regression tests in `tests/Feature/Api/PembayaranContractTest.php`:
  - cancel one approved payment while another approved payment still covers `grand_total` keeps `Penjualan.status = Lunas`.
  - cancel one approved payment when remaining approved payments are below `grand_total` recomputes `Penjualan.status = Approved`.
  - uncancel returns payment to `Pending`, clears `approver_id`, and does not count the payment as approved money or mutate the sale.
- Extended `app/Services/PaymentSettlementService.php` with transaction-wrapped, row-locked piutang state transitions:
  - `approvePiutangPayment()` locks payment, sale, and sibling payments, guards overpayment, then recomputes sale status.
  - `cancelPiutangPayment()` locks payment, sale, and sibling payments, cancels payment, then recomputes sale status only when approved money changed.
  - `uncancelPiutangPayment()` locks payment, sale, and sibling payments, returns payment to `Pending`, and clears `approver_id` without recomputing sale as approved money.
- Updated API `PembayaranController::cancel()` and `::uncancel()` to call the shared service and preserve JSON error/success style.
- Updated Filament `ViewPembayaran` approve/cancel/uncancel actions to call the same shared service and preserve notification style.

## Failing-first test run

Command:

```powershell
php artisan test --filter=PembayaranContractTest
```

Result before production changes: failed as expected.

- `cancel approved payment keeps sale lunas when remaining approved payments cover total` failed because existing API cancel blindly set sale status to `Approved`.
- `uncancel returns payment to pending without treating it as approved money` failed because existing uncancel left `approver_id` set.
- Summary: `2 failed, 5 passed`.

## Verification commands/results

### LSP diagnostics

Attempted on changed PHP files:

- `app/Services/PaymentSettlementService.php`
- `app/Http/Controllers/Api/PembayaranController.php`
- `app/Filament/Resources/Pembayarans/Pages/ViewPembayaran.php`
- `tests/Feature/Api/PembayaranContractTest.php`

Result: PHP LSP is not installed and was previously declined by user, so diagnostics could not run. Used PHP syntax checks, targeted PHPUnit, and Pint instead.

### PHP syntax

Command:

```powershell
php -l "app\Services\PaymentSettlementService.php"; if ($?) { php -l "app\Http\Controllers\Api\PembayaranController.php" }; if ($?) { php -l "app\Filament\Resources\Pembayarans\Pages\ViewPembayaran.php" }; if ($?) { php -l "tests\Feature\Api\PembayaranContractTest.php" }
```

Result: PASS.

- No syntax errors in `PaymentSettlementService.php`.
- No syntax errors in `PembayaranController.php`.
- No syntax errors in `ViewPembayaran.php`.
- No syntax errors in `PembayaranContractTest.php`.

After Pint formatting `ViewPembayaran.php`, re-ran syntax for that file:

```powershell
php -l "app\Filament\Resources\Pembayarans\Pages\ViewPembayaran.php"
```

Result: PASS.

### Targeted tests

Command:

```powershell
php artisan test --filter=PembayaranContractTest; if ($?) { php artisan test --filter=Pembayaran }
```

Result: PASS.

- `Tests\Feature\Api\PembayaranContractTest`: `7 passed (28 assertions)`.
- `php artisan test --filter=Pembayaran`: `9 passed (33 assertions)`.

### Pint

Initial command:

```powershell
./vendor/bin/pint --test "app/Services/PaymentSettlementService.php" "app/Http/Controllers/Api/PembayaranController.php" "app/Filament/Resources/Pembayarans/Pages/ViewPembayaran.php" "tests/Feature/Api/PembayaranContractTest.php"
```

Initial result: failed with one style issue in `ViewPembayaran.php`.

Fix command:

```powershell
./vendor/bin/pint "app/Filament/Resources/Pembayarans/Pages/ViewPembayaran.php"
```

Result: fixed one style issue.

Final check:

```powershell
./vendor/bin/pint --test "app/Services/PaymentSettlementService.php" "app/Http/Controllers/Api/PembayaranController.php" "app/Filament/Resources/Pembayarans/Pages/ViewPembayaran.php" "tests/Feature/Api/PembayaranContractTest.php"
```

Result: PASS (`4 files`).

## Adversarial checks

- Canceling an approved payment now recomputes sale status from remaining `Approved` payment totals after the payment is already marked `Canceled`, so canceled money is not counted.
- If remaining approved totals still cover `grand_total`, sale stays `Lunas`; if they do not, sale becomes `Approved`.
- Uncancel only returns the payment to `Pending` and clears `approver_id`; it does not mark the payment approved and does not increase approved totals.
- API and Filament approve/cancel/uncancel now share `PaymentSettlementService`, avoiding divergent status rules.
- Status changes run inside DB transactions and use `lockForUpdate()` for the target payment, related sale, and sibling payments on the sale.
- Hutang settlement was not implemented; the new service methods explicitly target piutang payments and reject hutang records.
- Sales stock logic was not touched.
- Non-test migrations, servers, commits, and stock flow edits were not run/performed.
