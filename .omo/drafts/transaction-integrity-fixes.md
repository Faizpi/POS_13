---
slug: transaction-integrity-fixes
status: reviewed-ready-for-execution
intent: clear
pending-action: user approval to execute Phase 1 or adjust scope
approach: phased Laravel POS transaction integrity repair covering API, Filament, services, database guardrails, and regression tests for all audited money/stock findings.
---

# Draft: transaction-integrity-fixes

## Components (topology ledger)
| id | outcome | status | evidence path |
| --- | --- | --- | --- |
| C1 | Sales API/Filament stock lifecycle is consistent and atomic | active | .omo/evidence/phase-1-sales-stock.md |
| C2 | Payment/piutang/hutang lifecycle prevents overpayment and recalculates invoice status | active | .omo/evidence/phase-1-payment.md |
| C3 | Promo visit stock lifecycle is consistent across API and Filament | active | .omo/evidence/phase-1-kunjungan-stock.md |
| C4 | Goods receiving prevents over-receive and uses locked stock mutations | active | .omo/evidence/phase-2-receiving.md |
| C5 | Purchase/Biaya update stubs are implemented or blocked honestly | active | .omo/evidence/phase-2-update-stubs.md |
| C6 | Server-side money calculation services replace trusted form totals | active | .omo/evidence/phase-3-money-services.md |
| C7 | Database constraints protect impossible stock/money/number states | active | .omo/evidence/phase-4-db-guardrails.md |
| C8 | Regression tests cover critical/high flows and known edge cases | active | .omo/evidence/phase-5-tests.md |

## Open assumptions (announced defaults)
| assumption | adopted default | rationale | reversible? |
| --- | --- | --- | --- |
| Existing production data may contain inconsistent stock or duplicate numbers | Add validation/audit commands or pre-migration checks before enforcing hard DB constraints | Hard constraints can fail on dirty data | Yes |
| Transaction records involving stock/money should not be hard-deleted once approved/lunas | Disable hard delete and use cancel/reversal lifecycle | Safer audit trail for POS | Yes, but should be explicit owner decision |
| Cash transactions should become paid only through explicit settlement/payment record | Do not infer `Lunas` solely from `syarat_pembayaran = Cash` during edit | Keeps ledger and status aligned | Yes |
| Filament can keep live UI calculations for UX | Recompute on server before save regardless of form state | Prevents tampered/stale Livewire state from persisting wrong totals | Yes |
| Money calculation precision should improve without adding dependency first | Use explicit `round(..., 2)` and decimal string/cents helpers initially; avoid broad dependency unless needed | Minimizes scope/risk | Yes |

## Findings (cited - path:lines)
- API sales approval only updates status and does not decrement stock: `app/Http/Controllers/Api/PenjualanController.php:350-376`.
- API sales cancellation only updates status and does not restore stock: `app/Http/Controllers/Api/PenjualanController.php:379-397`.
- Filament sales approval/cancel already contains the desired locked stock pattern: `app/Filament/Resources/Penjualans/Pages/ViewPenjualan.php:186-203`, `273-297`.
- API sales update replaces items and status without stock/payment reconciliation: `app/Http/Controllers/Api/PenjualanController.php:261-340`.
- API payment creation allows any amount >= 1 and does not check remaining invoice balance/status: `app/Http/Controllers/Api/PembayaranController.php:103-135`.
- API payment approval marks sale `Lunas` on `sum >= grand_total` but has no overpayment or hutang branching: `app/Http/Controllers/Api/PembayaranController.php:153-163`.
- API payment cancellation blindly changes a `Lunas` sale back to `Approved` instead of recomputing: `app/Http/Controllers/Api/PembayaranController.php:190-198`.
- API hutang payment creates `pembelian_id/type=hutang` without `penjualan_id`: `app/Http/Controllers/Api/PembayaranController.php:244-292`; base migration makes `penjualan_id` non-null: `database/migrations/0001_01_01_000010_create_pembayarans_table.php:17`.
- API purchase store uses `items.*.diskon` without validating it: `app/Http/Controllers/Api/PembelianController.php:68-97`, `137-150`.
- API purchase update returns success without updating totals/items: `app/Http/Controllers/Api/PembelianController.php:162-194`.
- Filament sales form dehydrates editable/form-calculated money fields: `app/Filament/Resources/Penjualans/Schemas/PenjualanForm.php:291-374`.
- DB lacks non-negative stock/money constraints and stock total consistency constraints in transaction/stock migrations.
- Tests exist for some API sales/stock contracts but not payment, purchase update, receiving, concurrency, or DB guardrails: `tests/Feature/Api/PenjualanContractTest.php`, `tests/Feature/Api/StokContractTest.php`.

## Decisions (with rationale)
1. Implement test-first for bug fixes touching money/stock. These are regression-prone and business-critical.
2. Centralize stock mutations in an application service before broad reuse. API and Filament currently diverge.
3. Centralize payment settlement/status recomputation before changing all payment surfaces. Status should be derived from approved payment totals.
4. Recompute all server-side totals before persistence. UI totals remain display-only.
5. Add DB guardrails only after application behavior and tests pass; migrations should be safe against existing dirty data.
6. Do not perform broad UI redesign or unrelated refactors in this plan.

## Scope IN
- Sales/Penjualan API and Filament create/update/approve/cancel/mark paid behavior where it affects money or stock.
- Visit/Kunjungan promo/sample stock approval/cancel behavior.
- Payment/Pembayaran piutang and hutang creation/approval/cancel/status recomputation.
- Purchase/Pembelian update and item/discount/tax/shipping total correctness.
- Goods receiving/Penerimaan Barang over-receive prevention, locking, cancel/edit reversal correctness.
- Manual stock/stock opname concurrency guard review and minimal locking/logging fixes.
- Database migrations/constraints/indexes required for transaction integrity.
- Feature/unit tests and relevant factories/helpers needed to verify the above.

## Scope OUT (Must NOT have)
- No unrelated UI redesign, navigation changes, styling changes, or report formatting work.
- No silent data deletion or migration that destroys production data.
- No changing public API response shapes unless required for error correctness; if changed, tests must assert it.
- No hard-coded warehouse/user/product IDs in production code.
- No type/error suppression or swallowed exceptions in new logic.
- No broad rewrite of the entire Laravel app; keep changes focused and reversible.

## Open questions
None blocking for a plan. Human sanity-check decisions: hard delete policy for approved/lunas records; whether cash sale should create an approved payment automatically or require explicit payment entry; whether overpayment should be rejected or recorded as change/refund.

## Approval gate
status: reviewed-ready-for-execution
Momus review: [OKAY] Plan can be executed by a capable developer; references are relevant, each task has a clear starting point, QA scenarios include concrete tool invocations and evidence paths, and no practical blockers were found.
Next action: ask user whether to execute Phase 1 first or adjust scope.
