# transaction-integrity-fixes - Work Plan

## TL;DR (For humans)

**What you'll get:** Sistem transaksi POS yang lebih aman untuk uang dan stok: approve/cancel/update penjualan, promo, pembayaran, pembelian, dan penerimaan barang akan divalidasi ulang, stok akan dimutasi secara atomik, dan status lunas/piutang/hutang tidak lagi mudah salah.

**Why this approach:** Perbaikan dimulai dari bug yang bisa langsung merusak stok/uang, lalu logic diduplikasi API/Filament dipusatkan ke service, kemudian database diberi guardrail, dan terakhir semua dikunci dengan test regresi.

**What it will NOT do:** Tidak merombak UI, tidak menghapus data produksi, tidak mengubah laporan di luar kebutuhan integritas transaksi, dan tidak melakukan rewrite besar yang tidak perlu.

**Effort:** XL
**Risk:** High - menyentuh uang, stok, status transaksi, migration, dan banyak entrypoint API/Filament.
**Decisions to sanity-check:** kebijakan hard delete untuk transaksi approved/lunas, handling cash sale apakah otomatis membuat payment atau tetap manual, dan overpayment apakah ditolak atau dicatat sebagai refund/change.

Your next move: setujui scope ini lalu mulai dari Phase 1. Full execution detail follows below.

---

> TL;DR (machine): XL/high-risk Laravel POS transaction integrity repair across sales, payment, purchase, receiving, promo stock, DB guardrails, and regression tests.

## Scope

### Must have

- Fix all audited stock-affecting transaction flows:
  - sales/penjualan create/update/approve/cancel/mark paid/unmark paid
  - promo/sample visits/kunjungan approve/cancel/update
  - goods receiving/penerimaan barang create/approve/cancel/edit
  - manual stock and stock opname concurrency-sensitive writes where needed
- Fix all audited money-affecting transaction flows:
  - sales totals, discounts, tax, shipping, payment terms, status
  - purchase totals, discounts, tax, shipping, update endpoint
  - receivable payments/piutang: create/approve/cancel/uncancel
  - payable payments/hutang: schema, create, approve, cancel, purchase status recomputation
- Centralize duplicated behavior used by API and Filament:
  - stock mutation service
  - money calculation service
  - payment settlement/status service
  - transaction number generation/uniqueness handling if needed
- Add tests before or alongside each behavior fix.
- Add safe database guardrails after behavior tests pass.
- Keep changes minimal and traceable; no unrelated feature work.

### Must NOT have (guardrails, anti-slop, scope boundaries)

- Must not trust client/Form/Livewire `grand_total`, `jumlah_baris`, or payment status for persistence.
- Must not mutate stock outside a database transaction for approval/cancel/edit paths.
- Must not use read-modify-write stock updates without `lockForUpdate()` or atomic increment/decrement in critical paths.
- Must not allow approved/lunas transaction hard delete without explicit reversal/blocking behavior.
- Must not introduce silent clamps like `max(0, ...)` for reversal when stock has already been consumed; fail loudly instead.
- Must not suppress errors with `@`, empty catch blocks, broad swallowing, `@ts-ignore`, or equivalent.
- Must not break existing API auth/gudang access rules.
- Must not run destructive migrations without dirty-data preflight and rollback strategy.
- Must not commit automatically unless the user explicitly asks.

## Audited findings inventory

| ID | Severity | Finding | Main files | Planned coverage |
| --- | --- | --- | --- | --- |
| B01 | Critical | API sales approval does not decrement stock | `app/Http/Controllers/Api/PenjualanController.php:350-376` | T01-T03 |
| B02 | Critical | API sales cancellation does not restore stock | `app/Http/Controllers/Api/PenjualanController.php:379-397` | T01-T03 |
| B03 | Critical | API promo/sample visit approval/cancel does not mutate stock | `app/Http/Controllers/Api/KunjunganController.php` | T07 |
| B04 | Critical | Filament sales/purchase forms persist form-state totals without server recomputation | `app/Filament/Resources/*/Schemas/*Form.php`, create/edit pages | T14-T16 |
| B05 | Critical | Payment creation/approval allows overpayment and invalid invoice status | `app/Http/Controllers/Api/PembayaranController.php:103-163` | T04-T06 |
| B06 | Critical | Cancel/delete approved/lunas transactions can leave payment/stock state inconsistent | Filament `DeleteAction`s, API cancel methods | T06, T08, T13 |
| B07 | Critical | Hutang payment schema/approval flow is incomplete/broken | `pembayarans` migration, `PembayaranController::storeHutang/approve` | T09-T10, T19 |
| B08 | High | API purchase update returns success without changing data | `app/Http/Controllers/Api/PembelianController.php:162-194` | T11 |
| B09 | High | API purchase item discount is used but not validated | `app/Http/Controllers/Api/PembelianController.php:68-97` | T11, T15 |
| B10 | High | Cash transaction status differs between create/update and can become `Lunas` without ledger | sales/purchase create/edit/update | T05, T15 |
| B11 | High | Updating invoices can invalidate existing payments without reconciliation | `PenjualanController::update`, Filament edit pages | T12, T15 |
| B12 | High | Shipping cost columns exist but API totals ignore them | migrations/models/API controllers | T15 |
| B13 | High | Receiving can over-receive PO quantity | `PenerimaanBarangController`, Filament receiving pages | T17 |
| B14 | High | Receiving stock add/subtract lacks row locks and can lose updates | receiving helpers/pages | T17-T18 |
| B15 | High | Receiving cancel/edit clamps stock to zero instead of rejecting impossible reversal | receiving helpers/pages | T18 |
| B16 | Medium | Tax/final discount validation is incomplete | sales/purchase API and Filament forms | T15 |
| B17 | Medium | Money calculations use PHP floats and inconsistent rounding | sales/purchase/payment calculations | T14-T16 |
| B18 | Medium | Transaction numbers are indexed but not unique; count-based generation is race-prone | migrations/models/generateNomor | T20 |
| B19 | Medium | DB allows negative/inconsistent stock/money and weak audit FKs | migrations/models | T19-T21 |

## Verification strategy

> Zero human intervention - all verification is agent-executed.

- Test decision: **TDD for critical/high bug fixes**, tests-after for DB guardrails/refactor-only tasks where pre-existing behavior is missing or hard to isolate.
- Framework: Laravel PHPUnit via `php artisan test`; formatting via `./vendor/bin/pint --test`; schema validation via migrations in test database.
- Evidence: each task writes a short command transcript or note to `.omo/evidence/task-<N>-transaction-integrity-fixes.md`.
- Minimum final commands:
  - `php artisan test --filter=PenjualanContractTest`
  - `php artisan test --filter=Pembayaran`
  - `php artisan test --filter=Pembelian`
  - `php artisan test --filter=PenerimaanBarang`
  - `php artisan test --filter=Kunjungan`
  - `php artisan test`
  - `./vendor/bin/pint --test`
- LSP/diagnostics: run PHP diagnostics on changed PHP files when available; otherwise rely on PHPUnit/Pint and PHP syntax checks.

## Execution strategy

### Parallel execution waves

- **Wave 0 - Baseline and test scaffolding:** T00. Must run first to capture current behavior and test helpers.
- **Wave 1 - Stop bleeding:** T01-T08. Fix direct stock/payment corruption paths.
- **Wave 2 - Update and receiving correctness:** T09-T13, T17-T18. Fix incomplete update and locked receiving lifecycle.
- **Wave 3 - Centralized money and server recomputation:** T14-T16. Remove trusted form totals and normalize tax/discount/shipping/cash status.
- **Wave 4 - Database guardrails:** T19-T21. Add safe migrations/indexes/checks after app behavior is correct.
- **Wave 5 - Full regression hardening:** T22-T24. Expand tests, cleanup, and final verification.

### Dependency matrix

| Todo | Depends on | Blocks | Can parallelize with |
| --- | --- | --- | --- |
| T00 | none | all implementation tasks | none |
| T01 | T00 | T02, T03 | T04, T07 |
| T02 | T01 | T03 | T04, T05, T07 |
| T03 | T01, T02 | final verification | T06-T08 |
| T04 | T00 | T05, T06 | T01, T07 |
| T05 | T04 | T06, T10, T15 | T02, T07 |
| T06 | T04, T05 | final verification | T08, T13 |
| T07 | T00 | final verification | T01-T06 |
| T08 | T00 | final verification | T06, T13 |
| T09 | T00 | T10, T19 | T11, T17 |
| T10 | T09, T05 | final verification | T11-T13 |
| T11 | T00 | T15 | T09, T17 |
| T12 | T01, T04 | T15 | T11, T17 |
| T13 | T01, T07, T17 | final verification | T06, T08 |
| T14 | T00 | T15, T16 | T17, T19 |
| T15 | T11, T12, T14 | T16 | T18-T20 |
| T16 | T14, T15 | final verification | T19-T21 |
| T17 | T00 | T18 | T09, T14 |
| T18 | T17 | T21 | T15-T20 |
| T19 | T09, T14 | T20, T21 | T15-T18 |
| T20 | T19 | final verification | T21 |
| T21 | T18, T19 | final verification | T20 |
| T22 | T01-T21 | T23, T24 | none |
| T23 | T22 | T24 | none |
| T24 | T23 | final verification | none |

## Todos
> Implementation + Test = ONE todo. Never separate.
<!-- APPEND TASK BATCHES BELOW THIS LINE WITH edit/apply_patch - never rewrite the headers above. -->

### Wave 0 - Baseline and fixtures

- [x] 1. Baseline transaction test helpers and evidence setup
  What to do / Must NOT do: Create or extend test helpers/factories/builders only as needed for Produk, Gudang, GudangProduk, Penjualan, Pembelian, Pembayaran, PenerimaanBarang, and Kunjungan tests. Prefer reusable helper methods inside feature tests if factories are too broad. Capture current failing behavior with focused tests before changing production logic. Must not change production behavior in this todo except test-only helpers.
  Parallelization: Wave 0 | Blocked by: none | Blocks: T01-T24
  References: `database/factories/UserFactory.php`; existing tests under `tests/Feature/Api/`; `tests/TestCase.php`; seed patterns in `database/seeders/DatabaseSeeder.php`.
  Acceptance criteria (agent-executable): New/updated tests can create isolated warehouses/products/stock/users without depending on brittle seed row IDs.
  QA scenarios (name the exact tool + invocation): `php artisan test --filter=PenjualanContractTest`; `php artisan test --filter=StokContractTest`; write results to `.omo/evidence/task-0-transaction-integrity-fixes.md`.
  Commit: N | test(infra): add transaction integrity test helpers

### Wave 1 - Stop bleeding: sales stock and payment correctness

- [x] 2. Introduce locked stock mutation service for saleable/promo/receipt stock
  What to do / Must NOT do: Add a focused service, e.g. `app/Services/InventoryMutationService.php`, with methods for locked decrement/increment of `GudangProduk` subtype columns (`stok_penjualan`, `stok_gratis`, `stok_sample`) and total `stok`. Methods must run inside caller transaction or assert transaction usage where practical. They must throw clear domain exceptions when stock row missing or insufficient. Must not silently clamp to zero. Add unit/feature coverage through one small API flow test or direct service test.
  Parallelization: Wave 1 | Blocked by: T00 | Blocks: T02, T03, T07, T13, T17, T18
  References: desired pattern in `app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php:186-203`, `273-297`; stock model `app/Models/GudangProduk.php`; stock migration `database/migrations/0001_01_01_000004_create_gudang_pivots_and_stok.php`.
  Acceptance criteria (agent-executable): Service decrements both total and subtype under `lockForUpdate()` and rejects insufficient stock with no partial mutation.
  QA scenarios (name the exact tool + invocation): `php artisan test --filter=InventoryMutation`; `php artisan test --filter=PenjualanContractTest`; evidence `.omo/evidence/task-1-transaction-integrity-fixes.md`.
  Commit: N | feat(stock): centralize locked stock mutations

- [x] 3. Fix API Penjualan approve/cancel stock lifecycle
  What to do / Must NOT do: Update `app/Http/Controllers/Api/PenjualanController.php::approve()` to wrap approval in `DB::transaction()`, reload items, lock/decrement saleable stock via `InventoryMutationService`, and only then set status `Approved`. Update `cancel()` to restore saleable stock when canceling `Approved` or `Lunas`; canceling `Pending` should not mutate stock. Preserve role/gudang checks and notification behavior. Must not duplicate stock logic inline if service exists. Must not decrement twice if already approved.
  Parallelization: Wave 1 | Blocked by: T01 | Blocks: T03, T12, T13
  References: broken API lines `app/Http/Controllers/Api/PenjualanController.php:350-397`; working Filament lines `app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php:186-203`, `273-297`; tests `tests/Feature/Api/PenjualanContractTest.php`.
  Acceptance criteria (agent-executable): API approve decreases `gudang_produk.stok` and `stok_penjualan` exactly once; insufficient stock returns 422/meaningful error and leaves status pending; API cancel approved/lunas restores exactly once.
  QA scenarios (name the exact tool + invocation): Add failing tests then run `php artisan test --filter=PenjualanContractTest`; evidence `.omo/evidence/task-2-transaction-integrity-fixes.md`.
  Commit: N | fix(penjualan): mutate stock during API approve and cancel

- [x] 4. Align Filament Penjualan approval/cancel with shared stock service
  What to do / Must NOT do: Replace duplicated stock loops in `app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php` with `InventoryMutationService` while preserving current behavior and notifications. Add/adjust feature coverage if Filament action testing is practical; otherwise cover shared service and API parity. Must not change labels/visual UI.
  Parallelization: Wave 1 | Blocked by: T01, T02 | Blocks: T13
  References: `app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php:186-203`, `273-297`.
  Acceptance criteria (agent-executable): Filament and API use same mutation service for saleable stock; no duplicate decrement logic remains in ViewPenjualan.
  QA scenarios (name the exact tool + invocation): `php artisan test --filter=Penjualan`; `./vendor/bin/pint --test app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php app/Services/InventoryMutationService.php`; evidence `.omo/evidence/task-3-transaction-integrity-fixes.md`.
  Commit: N | refactor(penjualan): share stock mutation service

- [x] 5. Add payment settlement service and overpayment guards for piutang
  What to do / Must NOT do: Add `PaymentSettlementService` or equivalent to compute approved payment totals, remaining balance, and target status for `Penjualan`. Update API `PembayaranController::store()` to require sale status eligible for payment (`Approved` or maybe `Lunas` only when remaining > 0 is false) and reject `jumlah_bayar` greater than remaining balance unless explicit overpayment policy is added. Update approval to lock relevant payment/sale rows and reject approval that would overpay. Must not mark invoice `Lunas` without computing approved payment sum.
  Parallelization: Wave 1 | Blocked by: T00 | Blocks: T05, T06, T10
  References: `app/Http/Controllers/Api/PembayaranController.php:103-163`; `app/Models/Penjualan.php:pembayarans()`; existing payment Filament pages.
  Acceptance criteria (agent-executable): Payment for `Pending`/`Canceled` sale is rejected; payment over remaining balance is rejected; exact remaining balance payment marks sale `Lunas` after approval.
  QA scenarios (name the exact tool + invocation): Add `tests/Feature/Api/PembayaranContractTest.php`; run `php artisan test --filter=PembayaranContractTest`; evidence `.omo/evidence/task-4-transaction-integrity-fixes.md`.
  Commit: N | fix(pembayaran): reject invalid and overpaid receivable payments

- [x] 6. Fix payment cancel/uncancel/status recomputation
  What to do / Must NOT do: Update API and Filament payment cancel/approve flows so sale status is recalculated from approved payments after every state change. If approved sum >= grand total => `Lunas`; else if sale was payment-driven `Lunas`, revert to `Approved`; never blindly force `Approved` without checking remaining approved payments. Clarify behavior for manual `markAsPaid` from Penjualan; prefer requiring explicit payment or tagging manual settlement. Must not cancel payment without transaction.
  Parallelization: Wave 1 | Blocked by: T04 | Blocks: T06, T10, T15
  References: API cancel `app/Http/Controllers/Api/PembayaranController.php:175-198`; Filament cancel `app/Filament/Resources/Pembayarans/Pages/ViewPembayaran.php`; Penjualan mark paid `app/Http/Controllers/Api/PenjualanController.php:419-459`, `app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php:217-245`.
  Acceptance criteria (agent-executable): Cancel one of multiple approved payments keeps sale `Lunas` if remaining approved payments still cover total; otherwise sale becomes `Approved`. Uncancel returns payment to `Pending` and does not mutate sale as approved money.
  QA scenarios (name the exact tool + invocation): `php artisan test --filter=PembayaranContractTest`; evidence `.omo/evidence/task-5-transaction-integrity-fixes.md`.
  Commit: N | fix(pembayaran): recompute invoice status after payment state changes

- [x] 7. Block or safely replace hard deletes for stock/money transactions
  What to do / Must NOT do: Audit and update Filament `DeleteAction`/bulk delete for `Penjualan`, `Pembelian`, `Pembayaran`, `PenerimaanBarang`, and `Kunjungan`. For approved/lunas/stock-affecting records, hide/delete-disable and direct user to cancel/reversal. For pending records, allow delete only if no stock/payment side effect exists. Must not delete approved/lunas rows silently. Tests may assert model/controller behavior where UI action testing is hard.
  Parallelization: Wave 1 | Blocked by: T04, T05 | Blocks: final verification
  References: sales delete actions `app/Filament/Resources/Penjualans/Tables/PenjualansTable.php`, `Pages/ViewPenjualan.php`, `Pages/EditPenjualan.php`; purchase/payment/receipt/visit table and view pages.
  Acceptance criteria (agent-executable): Approved/lunas sales, approved payments, approved receipts, and approved promo visits cannot be hard-deleted through Filament actions; pending/no-side-effect records remain manageable.
  QA scenarios (name the exact tool + invocation): `php artisan test --filter=FilamentDetailPagesTest` plus targeted action tests if feasible; evidence `.omo/evidence/task-6-transaction-integrity-fixes.md`.
  Commit: N | fix(transactions): prevent unsafe hard deletes

- [x] 8. Fix API and Filament Kunjungan promo/sample stock lifecycle parity
  What to do / Must NOT do: Update API `KunjunganController::store()` for super-admin auto-approved promo/sample visits to decrement stock atomically; update `approve()` to decrement promo/sample stock using service; update `cancel()` to restore only if previously approved. Align Filament `ViewKunjungan` with same service. Must not mutate stock for non-promo visits or `Pemeriksaan Stock` unless explicitly intended.
  Parallelization: Wave 1 | Blocked by: T01 | Blocks: T13
  References: `app/Http/Controllers/Api/KunjunganController.php`; Filament `app/Filament/Resources/Kunjungans/Pages/ViewKunjungan.php`; form `app/Filament/Resources/Kunjungans/Schemas/KunjunganForm.php`.
  Acceptance criteria (agent-executable): Promo Gratis approval decrements `stok` and `stok_gratis`; Promo Sample approval decrements `stok` and `stok_sample`; cancel restores; insufficient stock rejects approval with no partial mutation.
  QA scenarios (name the exact tool + invocation): Add `tests/Feature/Api/KunjunganContractTest.php`; run `php artisan test --filter=KunjunganContractTest`; evidence `.omo/evidence/task-7-transaction-integrity-fixes.md`.
  Commit: N | fix(kunjungan): apply promo stock mutations in API and Filament

- [x] 9. Add stock movement logs for transaction-driven stock mutations
  What to do / Must NOT do: Extend stock mutation service or callers to create `StokLog` entries for sales approvals/cancellations, promo approvals/cancellations, and receiving approvals/cancellations/edits. Include transaction type/id/nomor in `keterangan`. Must not log if transaction rolls back. Must not duplicate logs on repeated rejected operations.
  Parallelization: Wave 1 | Blocked by: T01 | Blocks: final verification
  References: existing logging in `app/Http/Controllers/Api/StokController.php:94-107`; `app/Filament/Pages/StokPage.php`; `app/Models/StokLog.php`.
  Acceptance criteria (agent-executable): Each committed stock mutation has one corresponding log with before/after/selisih; rollback leaves no log.
  QA scenarios (name the exact tool + invocation): `php artisan test --filter=StokContractTest`; targeted transaction stock log tests; evidence `.omo/evidence/task-8-transaction-integrity-fixes.md`.
  Commit: N | feat(stock): log transaction-driven stock movements

### Wave 2 - Update correctness and receiving lifecycle

- [x] 10. Repair hutang payment schema compatibility
  What to do / Must NOT do: Add a safe migration to support hutang payments: either make `pembayarans.penjualan_id` nullable and enforce type-specific relationship in application, or split payable payments if chosen. Keep existing data safe. Update model casts/fillable if needed. Must not break existing piutang payments or FKs. Add migration tests where possible.
  Parallelization: Wave 2 | Blocked by: T00 | Blocks: T10, T19
  References: base migration `database/migrations/0001_01_01_000010_create_pembayarans_table.php:17`; sync migrations adding `pembelian_id/type`; `app/Models/Pembayaran.php`; `app/Http/Controllers/Api/PembayaranController.php:244-292`.
  Acceptance criteria (agent-executable): Fresh test database can insert piutang payment with `penjualan_id` and hutang payment with `pembelian_id`; invalid missing relation for type is rejected at application validation.
  QA scenarios (name the exact tool + invocation): `php artisan test --filter=PembayaranHutang`; `php artisan migrate:fresh --env=testing` if test config supports it; evidence `.omo/evidence/task-9-transaction-integrity-fixes.md`.
  Commit: N | fix(pembayaran): support hutang payment schema safely

- [x] 11. Implement hutang approval/cancel and purchase status recomputation
  What to do / Must NOT do: Update `PembayaranController::approve/cancel/uncancel` or add type-specific methods so `type=hutang` payments update linked `Pembelian`, not `Penjualan`. Add service logic equivalent to piutang: remaining payable, overpayment rejection, status recomputation. Update Filament Pembelian relation manager and/or PembayaranHutang resource actions to use same service. Must not dereference `$pembayaran->penjualan` for hutang.
  Parallelization: Wave 2 | Blocked by: T09, T05 | Blocks: final verification
  References: `app/Http/Controllers/Api/PembayaranController.php:138-198`, `244-292`; `app/Filament/Resources/Pembelians/RelationManagers/PembayaransRelationManager.php`; `app/Filament/Resources/PembayaranHutangs/*`.
  Acceptance criteria (agent-executable): Hutang payment approval reduces purchase payable balance and marks pembelian `Lunas` only when approved hutang payments cover `grand_total`; cancel recomputes correctly; overpayment rejected.
  QA scenarios (name the exact tool + invocation): `php artisan test --filter=PembayaranHutangContractTest`; evidence `.omo/evidence/task-10-transaction-integrity-fixes.md`.
  Commit: N | fix(hutang): settle purchase payments correctly

- [x] 12. Implement real API Pembelian update with validation and tests
  What to do / Must NOT do: Replace the stub in `PembelianController::update()` with full update logic mirroring corrected store calculation. Validate `items.*.diskon` as numeric 0..100, tax 0..100, shipping if supported, final discount non-negative and not greater than subtotal unless explicitly allowed. Recompute totals server-side; delete/recreate or diff items transactionally. Must not return success without persistence.
  Parallelization: Wave 2 | Blocked by: T00 | Blocks: T15
  References: stub `app/Http/Controllers/Api/PembelianController.php:162-194`; store `:68-159`; `app/Models/Pembelian.php`, `app/Models/PembelianItem.php`.
  Acceptance criteria (agent-executable): API update changes header/items/grand_total; invalid discount >100 or negative rejects; response matches persisted DB.
  QA scenarios (name the exact tool + invocation): Add `tests/Feature/Api/PembelianContractTest.php`; run `php artisan test --filter=PembelianContractTest`; evidence `.omo/evidence/task-11-transaction-integrity-fixes.md`.
  Commit: N | fix(pembelian): implement validated update endpoint

- [x] 13. Guard sales update against stock/payment corruption
  What to do / Must NOT do: Update sales edit/update logic to prevent unsafe edits of approved/lunas records unless full stock delta and payment reconciliation are performed. Recommended default: if sale is `Approved`/`Lunas` and items/gudang/total-affecting fields change, require cancel/reversal workflow or perform locked delta adjustment plus payment status recomputation. Implement one consistent policy in API and Filament. Must not reset `Approved/Lunas` sale to `Pending` silently. Must not make `Cash` edit mark sale `Lunas` without settlement.
  Parallelization: Wave 2 | Blocked by: T01, T04 | Blocks: T15
  References: API update `app/Http/Controllers/Api/PenjualanController.php:261-340`; Filament edit `app/Filament/Resources/Penjualans/Pages/EditPenjualan.php`; payment relation `app/Models/Penjualan.php`.
  Acceptance criteria (agent-executable): Updating a pending sale works and recomputes totals; updating approved/lunas stock/money fields is either rejected with clear message or applies exact stock delta and payment recomputation under locks; tests document the chosen policy.
  QA scenarios (name the exact tool + invocation): `php artisan test --filter=PenjualanContractTest`; targeted update tests; evidence `.omo/evidence/task-12-transaction-integrity-fixes.md`.
  Commit: N | fix(penjualan): prevent unsafe approved sale edits

- [x] 14. Normalize cancel/delete lifecycle for all stock-affecting records
  What to do / Must NOT do: Ensure Penjualan, Kunjungan promo, and PenerimaanBarang have one lifecycle: Pending can cancel/delete without stock effect; Approved/Lunas can cancel with reversal if possible; hard delete blocked; uncancel returns Pending and does not reapply stock until approval. Must not allow uncancel to bypass stock validation.
  Parallelization: Wave 2 | Blocked by: T02, T07, T17 | Blocks: final verification
  References: sales controller/pages, kunjungan controller/pages, receiving controller/pages.
  Acceptance criteria (agent-executable): Status transition matrix is documented in code/tests and all critical flows pass.
  QA scenarios (name the exact tool + invocation): `php artisan test --filter=PenjualanContractTest`; `php artisan test --filter=KunjunganContractTest`; `php artisan test --filter=PenerimaanBarangContractTest`; evidence `.omo/evidence/task-13-transaction-integrity-fixes.md`.
  Commit: N | fix(transactions): standardize stock lifecycle transitions

### Wave 3 - Server-side money correctness

- [x] 15. Add money calculation services for sales and purchases
  What to do / Must NOT do: Add focused calculator(s), e.g. `SalesTotalsCalculator` and `PurchaseTotalsCalculator`, that accept validated input and product/price context, return normalized item rows and header totals. Include tax, final discount, item discount, item discount nominal, and shipping where applicable. Use deterministic rounding to 2 decimals. Must not persist from calculator; it should be pure logic.
  Parallelization: Wave 3 | Blocked by: T00 | Blocks: T15, T16
  References: API sales helper `app/Http/Controllers/Api/PenjualanController.php:464-527`; purchase store calculation `app/Http/Controllers/Api/PembelianController.php:90-97`, `137-150`; Filament form calculators.
  Acceptance criteria (agent-executable): Unit/feature tests prove expected totals for retail/grosir, item discount %, nominal discount, final discount, tax, and shipping.
  QA scenarios (name the exact tool + invocation): `php artisan test --filter=TotalsCalculator`; evidence `.omo/evidence/task-14-transaction-integrity-fixes.md`.
  Commit: N | feat(transactions): add server-side totals calculators

- [x] 16. Recompute API and Filament sales/purchase totals server-side before save
  What to do / Must NOT do: Wire calculators into API store/update and Filament Create/Edit mutate hooks. Keep UI live calculations for convenience, but ignore/deprioritize submitted `jumlah_baris`/`grand_total` and recompute on server. Normalize validation for tax max 100, discount min/max, shipping inclusion, and final discount rules. Must not trust disabled/dehydrated form totals. Must preserve intended ability to edit purchase unit price; for sales, decide whether price is product-derived or explicitly overridden and test it.
  Parallelization: Wave 3 | Blocked by: T11, T12, T14 | Blocks: T16
  References: `app/Filament/Resources/Penjualans/Schemas/PenjualanForm.php:291-374`; `CreatePenjualan.php`; `EditPenjualan.php`; `app/Filament/Resources/Pembelians/Schemas/PembelianForm.php`; `CreatePembelian.php`; `EditPembelian.php`; API controllers.
  Acceptance criteria (agent-executable): Tampered `grand_total`/`jumlah_baris` in request/form state does not persist; persisted totals equal server calculation; invalid tax/discount rejected.
  QA scenarios (name the exact tool + invocation): `php artisan test --filter=PenjualanContractTest`; `php artisan test --filter=PembelianContractTest`; targeted Filament form persistence tests if feasible; evidence `.omo/evidence/task-15-transaction-integrity-fixes.md`.
  Commit: N | fix(transactions): recompute persisted totals server-side

- [x] 17. Remove cash-status side effects and define settlement policy
  What to do / Must NOT do: Change create/update/edit flows so `syarat_pembayaran = Cash` does not silently mark `Lunas` unless an explicit payment/settlement record is created in the same transaction. Preferred default: create sale/purchase as `Pending` or `Approved` according to approval flow, then payment service marks `Lunas`. If product owner chooses auto-cash settlement, implement explicit `Pembayaran` record creation with amount exactly equal to grand total. Must not leave status `Lunas` without ledger evidence.
  Parallelization: Wave 3 | Blocked by: T14, T15 | Blocks: final verification
  References: API sales update `app/Http/Controllers/Api/PenjualanController.php:300-305`; Filament sales edit; purchase create/edit/update; payment services.
  Acceptance criteria (agent-executable): Cash edit no longer produces ledgerless `Lunas`; tests assert chosen policy for cash create/update.
  QA scenarios (name the exact tool + invocation): `php artisan test --filter=CashStatus`; `php artisan test --filter=PembayaranContractTest`; evidence `.omo/evidence/task-16-transaction-integrity-fixes.md`.
  Commit: N | fix(transactions): align cash status with payment ledger

### Wave 4 - Receiving and inventory consistency

- [x] 18. Prevent Penerimaan Barang over-receive and lock approval stock additions
  What to do / Must NOT do: Update API and Filament receiving create/approve to compute remaining PO quantity under transaction and reject `qty_diterima` beyond remaining quantity. Use locked stock mutation service for adding stock. Handle multiple pending receipts for same PO safely. Must not rely only on form prefill/filtering.
  Parallelization: Wave 4 | Blocked by: T01, T00 | Blocks: T18, T13
  References: `app/Http/Controllers/Api/PenerimaanBarangController.php`; `app/Filament/Resources/PenerimaanBarangs/Schemas/PenerimaanBarangForm.php`; `Pages/CreatePenerimaanBarang.php`; `Pages/ViewPenerimaanBarang.php`.
  Acceptance criteria (agent-executable): Over-receive attempt returns validation error; concurrent-like sequential pending receipts cannot both approve beyond PO quantity; approved receipt increases correct stock subtype.
  QA scenarios (name the exact tool + invocation): Add `tests/Feature/Api/PenerimaanBarangContractTest.php`; run `php artisan test --filter=PenerimaanBarangContractTest`; evidence `.omo/evidence/task-17-transaction-integrity-fixes.md`.
  Commit: N | fix(penerimaan): prevent over-receiving and lock stock adds

- [x] 19. Fix receiving cancel/edit reversal without silent zero clamp
  What to do / Must NOT do: Replace `max(0, current - qty)` reversal with locked insufficient-stock detection. If stock has already been consumed, cancel/edit should fail with clear message or require an explicit stock adjustment workflow. For approved receipt edit, reverse old items then apply new items atomically; if reversal impossible, rollback all. Must not leave receipt status changed when stock reversal fails.
  Parallelization: Wave 4 | Blocked by: T17 | Blocks: T21, final verification
  References: API receiving `kurangiStok()`; Filament `ViewPenerimaanBarang.php`, `EditPenerimaanBarang.php` stock subtraction logic.
  Acceptance criteria (agent-executable): Cancel approved receipt with enough stock subtracts exactly; cancel with insufficient current stock fails and leaves receipt/stock unchanged; edit approved receipt applies exact delta atomically.
  QA scenarios (name the exact tool + invocation): `php artisan test --filter=PenerimaanBarangContractTest`; evidence `.omo/evidence/task-18-transaction-integrity-fixes.md`.
  Commit: N | fix(penerimaan): reject impossible stock reversals

### Wave 5 - Database guardrails and numbering

- [x] 20. Add safe payment/stock/money database guardrail migrations
  What to do / Must NOT do: Add migrations for non-negative constraints where DB supports them and application preflight checks where existing dirty data may block. Include `pembayarans` hutang relation compatibility from T09 if not already done. Add check constraints for `jumlah_bayar >= 0`, stock subtype columns >= 0, money totals >= 0, tax/discount ranges where feasible. Must include rollback methods. Must not apply hard constraints without considering existing dirty rows; if necessary add an artisan audit command first.
  Parallelization: Wave 5 | Blocked by: T09, T14 | Blocks: T20, T21
  References: migrations `0001_01_01_000004`, `0006`, `0007`, `0010`, `0011`, sync migrations; models `GudangProduk`, `PenjualanItem`, `PembelianItem`, `Pembayaran`.
  Acceptance criteria (agent-executable): Fresh migrations succeed; invalid direct insert/update fails or application validation catches it; rollback succeeds in test environment.
  QA scenarios (name the exact tool + invocation): `php artisan migrate:fresh --env=testing`; `php artisan test --filter=DatabaseGuardrail`; evidence `.omo/evidence/task-19-transaction-integrity-fixes.md`.
  Commit: N | feat(db): add transaction integrity guardrails

- [x] 21. Make transaction numbers unique and generation race-safe
  What to do / Must NOT do: Add unique indexes for `nomor` where business rules require uniqueness, handling nullable values correctly. Update generation to retry on duplicate key or use a transaction/sequence-safe strategy. Cover Penjualan, Pembelian, Pembayaran, PenerimaanBarang, StockOpname, and any Biaya numbering if applicable. Must not break existing duplicate dirty data; provide audit/preflight if duplicates exist.
  Parallelization: Wave 5 | Blocked by: T19 | Blocks: final verification
  References: model `generateNomor()` methods; migrations for `nomor` columns; create controllers/pages.
  Acceptance criteria (agent-executable): Two rapid creates cannot persist duplicate non-null `nomor`; duplicate insert fails predictably; generation retry succeeds.
  QA scenarios (name the exact tool + invocation): `php artisan test --filter=TransactionNumber`; evidence `.omo/evidence/task-20-transaction-integrity-fixes.md`.
  Commit: N | fix(db): enforce unique transaction numbers

- [x] 22. Strengthen audit foreign keys and stock consistency checks
  What to do / Must NOT do: Add/repair FKs for `approver_id` where safe and `stok_logs.gudang_produk_id`. Add application or DB-level guard that `gudang_produk.stok` equals subtype sum. If DB check constraints are impractical, enforce in `InventoryMutationService` and add audit command/test. Must not cascade-delete audit history unexpectedly.
  Parallelization: Wave 5 | Blocked by: T18, T19 | Blocks: final verification
  References: migrations with `approver_id`; `stok_logs` migration/model; `GudangProduk` model.
  Acceptance criteria (agent-executable): New stock mutations always keep total equal to subtype sum; audit log references valid stock rows where present; migrations pass on fresh DB.
  QA scenarios (name the exact tool + invocation): `php artisan test --filter=StockConsistency`; evidence `.omo/evidence/task-21-transaction-integrity-fixes.md`.
  Commit: N | feat(db): enforce stock audit consistency

### Wave 6 - Regression hardening and cleanup

- [x] 23. Expand transaction regression test suite for all 19 findings
  What to do / Must NOT do: Add/complete feature tests covering each B01-B19 finding. Organize tests by API resource: `PenjualanContractTest`, `PembayaranContractTest`, `PembelianContractTest`, `PenerimaanBarangContractTest`, `KunjunganContractTest`, and DB guardrail tests. Must not rely on execution order or production seed IDs. Each bug gets at least one failing-before/passing-after assertion.
  Parallelization: Wave 6 | Blocked by: T01-T21 | Blocks: T23, T24
  References: audited findings table above; existing tests under `tests/Feature/Api/`.
  Acceptance criteria (agent-executable): Every finding ID B01-B19 is referenced in at least one test name/comment/assertion group; full `php artisan test` passes or pre-existing unrelated failures are documented.
  QA scenarios (name the exact tool + invocation): `php artisan test`; evidence `.omo/evidence/task-22-transaction-integrity-fixes.md`.
  Commit: N | test(transactions): cover audited integrity findings

- [x] 24. Code cleanup without behavior drift
  What to do / Must NOT do: Remove duplicated inline stock/payment calculation logic now replaced by services. Ensure controllers stay thin and Filament pages call shared services. Add type hints where touched. Must not refactor unrelated reports/UI or rename public routes.
  Parallelization: Wave 6 | Blocked by: T22 | Blocks: T24
  References: all changed controllers/services/pages.
  Acceptance criteria (agent-executable): No remaining duplicated critical stock decrement/reversal loops in API and Filament except through service; no dead helper methods left unused; tests still pass.
  QA scenarios (name the exact tool + invocation): `php artisan test`; `./vendor/bin/pint --test`; evidence `.omo/evidence/task-23-transaction-integrity-fixes.md`.
  Commit: N | refactor(transactions): remove duplicated integrity logic

- [x] 25. Produce final verification and rollout notes
  What to do / Must NOT do: Write a concise rollout note in `.omo/evidence/final-transaction-integrity-fixes.md` listing changed flows, migration considerations, commands run, known pre-existing issues, and manual production preflight checks. Must not create user-facing docs unless requested; evidence file under `.omo/evidence` is allowed as work artifact.
  Parallelization: Wave 6 | Blocked by: T23 | Blocks: final verification
  References: evidence files T00-T23; git diff; migration list.
  Acceptance criteria (agent-executable): Evidence file includes command results, data migration warnings, and rollback considerations.
  QA scenarios (name the exact tool + invocation): `git diff --stat`; `php artisan test`; `./vendor/bin/pint --test`; evidence `.omo/evidence/task-24-transaction-integrity-fixes.md`.
  Commit: N | chore(transactions): document verification evidence

## Final verification wave
> Runs in parallel after ALL todos. ALL must APPROVE. Surface results and wait for the user's explicit okay before declaring complete.

- [ ] F1. Plan compliance audit
  - Verify every B01-B19 finding is either fixed, explicitly deferred with user approval, or blocked by documented dirty-data/migration condition.
  - Tooling: read `.omo/plans/transaction-integrity-fixes.md`, grep changed files/tests for finding coverage, inspect `.omo/evidence/*`.

- [ ] F2. Code quality review
  - Verify service boundaries are clear, controllers/pages did not grow messy, no duplicate critical money/stock logic remains, and no silent exception swallowing was added.
  - Tooling: `./vendor/bin/pint --test`, targeted reads, optional Oracle review for high-risk logic.

- [ ] F3. Real QA through automated commands
  - Run `php artisan test` and targeted filters for Penjualan, Pembayaran, Pembelian, PenerimaanBarang, Kunjungan, Stok, DB guardrails.
  - No browser/local server required unless user explicitly asks.

- [ ] F4. Scope fidelity
  - Verify no unrelated UI/report/refactor changes were made.
  - Tooling: `git diff --stat`, `git diff --name-only`, inspect changed files.

## Commit strategy

No commits unless the user explicitly requests them.

If user requests commits after implementation, use atomic commits by phase:

1. `test(infra): add transaction integrity fixtures`
2. `fix(penjualan): make sales stock lifecycle atomic`
3. `fix(pembayaran): enforce settlement and overpayment rules`
4. `fix(kunjungan): align promo stock mutations`
5. `fix(penerimaan): lock receiving stock lifecycle`
6. `fix(pembelian): implement update and validated totals`
7. `feat(transactions): centralize server-side calculations`
8. `feat(db): add transaction integrity guardrails`
9. `test(transactions): cover audited money and stock flows`

Before any commit: inspect `git status`, `git diff`, and `git log --oneline -10`; stage only intended files.

## Success criteria

- API and Filament behave consistently for stock-affecting approvals/cancellations.
- Sales approval/cancel mutates stock exactly once and under lock.
- Promo/sample visit approval/cancel mutates correct stock subtype exactly once and under lock.
- Receiving cannot exceed PO remaining quantity and cannot silently reverse consumed stock.
- Payment creation/approval cannot overpay unless explicit recorded overpayment policy is implemented.
- Payment cancel/uncancel recomputes invoice/purchase status from approved payment totals.
- Hutang payments work on fresh schema and update purchase payable status correctly.
- Sales/purchase totals are recomputed server-side; form-provided `grand_total`/`jumlah_baris` cannot corrupt persistence.
- Cash status is ledger-backed or explicitly remains not-lunas until settlement.
- DB constraints/indexes prevent or detect impossible stock/money/number states.
- Tests cover every audited finding B01-B19.
- Full `php artisan test` and `./vendor/bin/pint --test` pass, or unrelated pre-existing failures are documented separately.
