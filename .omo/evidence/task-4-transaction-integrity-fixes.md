# Task 4 Evidence - Payment settlement service and piutang overpayment guards

## Scope executed

- Added `app/Services/PaymentSettlementService.php` for piutang settlement checks.
- Updated `app/Http/Controllers/Api/PembayaranController.php` piutang `store()` and `approve()` flows.
- Added `tests/Feature/Api/PembayaranContractTest.php` regression coverage.
- Did not implement hutang payment and did not touch sales stock logic.

## Failing-first evidence

Command:

```powershell
php artisan test --filter=PembayaranContractTest
```

Result before implementation:

- `FAIL Tests\Feature\Api\PembayaranContractTest`
- `test_store_rejects_payment_for_pending_and_canceled_sales`: expected `422`, received `201`.
- `test_store_rejects_payment_above_remaining_balance`: expected `422`, received `201`.
- `test_approve_rejects_pending_payment_that_would_overpay_after_other_approval`: expected `422`, received `200`.
- Existing exact-remaining approval behavior already passed.

## Implementation notes

- `PaymentSettlementService::assertPiutangPaymentCanBeCreated()` rejects non-`Approved` sales and over-remaining payment creation.
- `PaymentSettlementService::approvePiutangPayment()` wraps approval in `DB::transaction()` and locks both payment and sale rows via `lockForUpdate()`.
- Approval recomputes sale status from approved payment sum; exact coverage marks `Penjualan.status = Lunas`.
- Approval excludes the payment being approved from existing approved sum, preventing race/stale pending approvals from overpaying.
- API response style preserved with JSON `message` and `422` domain failures.
- Admin active-gudang check is preserved/applied for approval before settlement.

## Adversarial checks covered

- Pending sale payment create rejected.
- Canceled sale payment create rejected.
- Payment create over remaining balance rejected.
- Exact remaining approved payment marks sale `Lunas`.
- Pending payment approval rejected when another approved payment already consumes enough remaining balance.
- Overpay rejection leaves payment `Pending` and sale `Approved`.

## Verification

Command:

```powershell
php -l "app\Services\PaymentSettlementService.php"; if ($?) { php -l "app\Http\Controllers\Api\PembayaranController.php" }; if ($?) { php -l "tests\Feature\Api\PembayaranContractTest.php" }; if ($?) { php artisan test --filter=PembayaranContractTest }; if ($?) { php artisan test --filter=Pembayaran }; if ($?) { php artisan test --filter=PenjualanContractTest }; if ($?) { & ".\vendor\bin\pint.bat" --test "app\Services\PaymentSettlementService.php" "app\Http\Controllers\Api\PembayaranController.php" "tests\Feature\Api\PembayaranContractTest.php" }
```

Result:

- PHP syntax: no syntax errors in all changed PHP files.
- `php artisan test --filter=PembayaranContractTest`: 4 passed, 16 assertions.
- `php artisan test --filter=Pembayaran`: 6 passed, 21 assertions.
- `php artisan test --filter=PenjualanContractTest`: 16 passed, 42 assertions.
- `./vendor/bin/pint.bat --test ...`: PASS, 3 files.

## LSP diagnostics

Attempted on changed PHP files. Result: PHP LSP server is not installed; previous user decision declined installation. Verification relied on PHP syntax checks, scoped Laravel tests, and Pint.
