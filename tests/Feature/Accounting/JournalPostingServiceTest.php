<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Accounting\AccountCategory;
use App\Accounting\DomainException;
use App\Accounting\JournalPostingLine;
use App\Accounting\JournalPostingRequest;
use App\Accounting\JournalStatus;
use App\Accounting\JournalType;
use App\Accounting\LineOrder;
use App\Accounting\MappingKey;
use App\Accounting\Money;
use App\Accounting\SourceIdentity;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\AccountMappingService;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class JournalPostingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_posts_balanced_exact_totals_with_effective_mapping_snapshots_and_deterministic_order(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $actor = User::factory()->create(['role' => 'super_admin']);
        $receivable = $this->map($actor, MappingKey::ArReceivable, AccountCategory::Aset, '2026-01-01');
        $revenue = $this->map($actor, MappingKey::SalesRetailRevenue, AccountCategory::Pendapatan, '2026-01-01');

        $this->assertFalse((bool) $receivable->fresh()->is_used);
        $this->assertFalse((bool) $revenue->fresh()->is_used);

        $journal = app(JournalPostingService::class)->post($actor, $this->request(101, '2026-07-22'));

        $this->assertSame(JournalStatus::Posted, $journal->status);
        $this->assertSame('1000000.01', $journal->total_debit);
        $this->assertSame($journal->total_debit, $journal->total_credit);
        $this->assertSame('JRN-20260722-000001', $journal->journal_number);
        $this->assertSame(1, $journal->posting_sequence);
        $this->assertSame([$receivable->id, $revenue->id], $journal->lines->pluck('account_id')->all());
        $this->assertSame([10, 20], $journal->lines->pluck('line_sequence')->all());

        $this->assertTrue((bool) $receivable->fresh()->is_used);
        $this->assertTrue((bool) $revenue->fresh()->is_used);
    }

    public function test_mapping_changes_do_not_mutate_historical_account_snapshots(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $old = $this->map($actor, MappingKey::ArReceivable, AccountCategory::Aset, '2026-01-01', '2026-06-30');
        $this->map($actor, MappingKey::SalesRetailRevenue, AccountCategory::Pendapatan, '2026-01-01');
        $new = $this->map($actor, MappingKey::ArReceivable, AccountCategory::Aset, '2026-07-01');

        $historical = app(JournalPostingService::class)->post($actor, $this->request(102, '2026-06-30'));
        $current = app(JournalPostingService::class)->post($actor, $this->request(103, '2026-07-01'));

        $this->assertSame($old->id, $historical->lines->first()->account_id);
        $this->assertSame($new->id, $current->lines->first()->account_id);
        $this->assertSame($old->id, $historical->fresh('lines')->lines->first()->account_id);
    }

    public function test_repeated_calls_return_the_single_posted_journal_without_extra_sequence(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $this->validMappings($actor);
        $service = app(JournalPostingService::class);
        $request = $this->request(104, '2026-07-22');
        $first = $service->post($actor, $request);

        $second = $service->post($actor, $request);
        $third = $service->post($actor, $request);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->id, $third->id);
        $this->assertSame(1, $first->posting_sequence);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('journal_lines', 2);
        $this->assertSame(1, (int) DB::table('journal_posting_sequences')->sum('last_value'));
    }

    public function test_source_lock_is_acquired_and_released_deterministically(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $actor = User::factory()->create(['role' => 'super_admin']);
        $this->validMappings($actor);
        $service = app(JournalPostingService::class);

        $this->assertDatabaseCount('journal_source_locks', 0);

        $journal = $service->post($actor, $this->request(301, '2026-07-22'));

        $this->assertDatabaseHas('journal_source_locks', [
            'source_type' => 'sale',
            'source_id' => 301,
            'journal_type' => 'sale',
            'source_version' => 1,
        ]);

        $this->assertSame(JournalStatus::Posted, $journal->status);
    }

    public function test_duplicate_source_lock_is_handled_gracefully(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $actor = User::factory()->create(['role' => 'super_admin']);
        $this->validMappings($actor);
        $service = app(JournalPostingService::class);

        $first = $service->post($actor, $this->request(302, '2026-07-22'));
        $second = $service->post($actor, $this->request(302, '2026-07-22'));

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('journal_source_locks', 1);
    }

    public function test_numbers_sequences_and_line_order_are_deterministic_and_gapless_after_rollback(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $this->validMappings($actor);
        $service = app(JournalPostingService::class);

        $first = $service->post($actor, $this->request(106, '2026-07-22'));
        $second = $service->post($actor, $this->request(107, '2026-07-22'));
        $this->assertSame([1, 2], [$first->posting_sequence, $second->posting_sequence]);
        $this->assertSame(['JRN-20260722-000001', 'JRN-20260722-000002'], [$first->journal_number, $second->journal_number]);
    }

    public function test_future_date_is_rejected_with_exact_message(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $actor = User::factory()->create(['role' => 'super_admin']);
        $this->validMappings($actor);
        $service = app(JournalPostingService::class);

        try {
            $service->post($actor, $this->request(201, '2026-07-23'));
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertSame('Future-dated journals cannot be posted.', $e->getMessage());
        }

        // Assertions execute after exception is caught
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_lines', 0);
        $this->assertDatabaseCount('journal_source_locks', 0);
        $this->assertSame(0, (int) DB::table('journal_posting_sequences')->sum('last_value'));
    }

    public function test_missing_mapping_is_rejected_with_exact_message(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $actor = User::factory()->create(['role' => 'super_admin']);
        // Only create SalesRetailRevenue mapping, not ArReceivable
        $this->map($actor, MappingKey::SalesRetailRevenue, AccountCategory::Pendapatan, '2026-01-01');
        $service = app(JournalPostingService::class);

        try {
            $service->post($actor, new JournalPostingRequest(
                new SourceIdentity('sale', 202, JournalType::Sale, 1),
                '2026-07-22',
                'missing',
                null,
                null,
                null,
                [
                    new JournalPostingLine(MappingKey::ArReceivable, new LineOrder(10), Money::fromDecimalString('1.00'), null),
                    new JournalPostingLine(MappingKey::SalesRetailRevenue, new LineOrder(20), null, Money::fromDecimalString('1.00')),
                ]
            ));
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertSame('No mapping exists for ar.receivable on 2026-07-22.', $e->getMessage());
        }

        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_lines', 0);
        $this->assertDatabaseCount('journal_source_locks', 0);
        $this->assertSame(0, (int) DB::table('journal_posting_sequences')->sum('last_value'));
    }

    public function test_inactive_account_is_rejected_with_exact_message(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $actor = User::factory()->create(['role' => 'super_admin']);
        [$receivable, $revenue] = $this->validMappings($actor);
        $receivable->update(['is_active' => false]);
        $service = app(JournalPostingService::class);

        try {
            $service->post($actor, $this->request(205, '2026-07-22'));
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertSame('Account for ar.receivable is inactive.', $e->getMessage());
        }

        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_lines', 0);
        $this->assertDatabaseCount('journal_source_locks', 0);
        $this->assertSame(0, (int) DB::table('journal_posting_sequences')->sum('last_value'));
        $this->assertFalse((bool) $receivable->fresh()->is_used);
        $this->assertFalse((bool) $revenue->fresh()->is_used);
    }

    public function test_non_postable_account_is_rejected_with_exact_message(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $actor = User::factory()->create(['role' => 'super_admin']);
        [$receivable, $revenue] = $this->validMappings($actor);
        $receivable->update(['is_postable' => false]);
        $service = app(JournalPostingService::class);

        try {
            $service->post($actor, $this->request(206, '2026-07-22'));
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertSame('Account for ar.receivable is not postable.', $e->getMessage());
        }

        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_lines', 0);
        $this->assertDatabaseCount('journal_source_locks', 0);
        $this->assertSame(0, (int) DB::table('journal_posting_sequences')->sum('last_value'));
        $this->assertFalse((bool) $receivable->fresh()->is_used);
        $this->assertFalse((bool) $revenue->fresh()->is_used);
    }

    public function test_unauthorized_actor_is_rejected_with_exact_message(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $actor = User::factory()->create(['role' => 'super_admin']);
        [$receivable, $revenue] = $this->validMappings($actor);
        $unauthorized = User::factory()->create(['role' => 'admin']);
        $service = app(JournalPostingService::class);

        try {
            $service->post($unauthorized, $this->request(207, '2026-07-22'));
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertSame('The actor is not authorized to post journals.', $e->getMessage());
        }

        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_lines', 0);
        $this->assertDatabaseCount('journal_source_locks', 0);
        $this->assertSame(0, (int) DB::table('journal_posting_sequences')->sum('last_value'));
        $this->assertFalse((bool) $receivable->fresh()->is_used);
        $this->assertFalse((bool) $revenue->fresh()->is_used);
    }

    public function test_unbalanced_totals_are_rejected_with_exact_message(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $actor = User::factory()->create(['role' => 'super_admin']);
        [$receivable, $revenue] = $this->validMappings($actor);
        $service = app(JournalPostingService::class);

        try {
            $service->post($actor, new JournalPostingRequest(
                new SourceIdentity('sale', 204, JournalType::Sale, 1),
                '2026-07-22',
                'unbalanced',
                null,
                null,
                null,
                [
                    new JournalPostingLine(MappingKey::ArReceivable, new LineOrder(10), Money::fromDecimalString('2.00'), null),
                    new JournalPostingLine(MappingKey::SalesRetailRevenue, new LineOrder(20), null, Money::fromDecimalString('1.00')),
                ]
            ));
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertSame('Journal debit and credit totals must balance.', $e->getMessage());
        }

        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_lines', 0);
        $this->assertDatabaseCount('journal_source_locks', 0);
        $this->assertSame(0, (int) DB::table('journal_posting_sequences')->sum('last_value'));
        $this->assertFalse((bool) $receivable->fresh()->is_used);
        $this->assertFalse((bool) $revenue->fresh()->is_used);
    }

    public function test_duplicate_line_sequence_is_rejected_with_exact_message(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $actor = User::factory()->create(['role' => 'super_admin']);
        [$receivable, $revenue] = $this->validMappings($actor);
        $service = app(JournalPostingService::class);

        try {
            $service->post($actor, new JournalPostingRequest(
                new SourceIdentity('sale', 207, JournalType::Sale, 1),
                '2026-07-22',
                'duplicate order',
                null,
                null,
                null,
                [
                    new JournalPostingLine(MappingKey::ArReceivable, new LineOrder(10), Money::fromDecimalString('1.00'), null),
                    new JournalPostingLine(MappingKey::SalesRetailRevenue, new LineOrder(10), null, Money::fromDecimalString('1.00')),
                ]
            ));
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertSame('Posting line order must be unique.', $e->getMessage());
        }

        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_lines', 0);
        $this->assertDatabaseCount('journal_source_locks', 0);
        $this->assertSame(0, (int) DB::table('journal_posting_sequences')->sum('last_value'));
        $this->assertFalse((bool) $receivable->fresh()->is_used);
        $this->assertFalse((bool) $revenue->fresh()->is_used);
    }

    public function test_malformed_date_is_rejected_with_exact_message(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $actor = User::factory()->create(['role' => 'super_admin']);
        [$receivable, $revenue] = $this->validMappings($actor);
        $service = app(JournalPostingService::class);

        try {
            $service->post($actor, $this->request(208, 'not-a-date'));
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertSame('Invalid journal date format.', $e->getMessage());
        }

        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_lines', 0);
        $this->assertDatabaseCount('journal_source_locks', 0);
        $this->assertSame(0, (int) DB::table('journal_posting_sequences')->sum('last_value'));
        $this->assertFalse((bool) $receivable->fresh()->is_used);
        $this->assertFalse((bool) $revenue->fresh()->is_used);
    }

    public function test_invalid_gudang_fk_is_rejected(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $actor = User::factory()->create(['role' => 'super_admin']);
        [$receivable, $revenue] = $this->validMappings($actor);
        $service = app(JournalPostingService::class);

        $request = new JournalPostingRequest(
            new SourceIdentity('sale', 209, JournalType::Sale, 1),
            '2026-07-22',
            'invalid gudang',
            99999, // Non-existent gudang ID
            'customer',
            209,
            [
                new JournalPostingLine(MappingKey::ArReceivable, new LineOrder(10), Money::fromDecimalString('1.00'), null),
                new JournalPostingLine(MappingKey::SalesRetailRevenue, new LineOrder(20), null, Money::fromDecimalString('1.00')),
            ]
        );

        try {
            $service->post($actor, $request);
            $this->fail('Expected QueryException was not thrown.');
        } catch (QueryException $e) {
            // Expected: foreign key constraint violation
            $this->assertStringContainsString('gudang', $e->getMessage());
        }

        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_lines', 0);
        $this->assertDatabaseCount('journal_source_locks', 0);
        $this->assertSame(0, (int) DB::table('journal_posting_sequences')->sum('last_value'));
        $this->assertFalse((bool) $receivable->fresh()->is_used);
        $this->assertFalse((bool) $revenue->fresh()->is_used);
    }

    public function test_existing_draft_source_is_returned_without_creating_duplicate(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $actor = User::factory()->create(['role' => 'super_admin']);
        [$receivable, $revenue] = $this->validMappings($actor);
        $service = app(JournalPostingService::class);

        // Create a draft journal manually
        $draft = JournalEntry::create([
            'source_type' => 'sale',
            'source_id' => 210,
            'journal_type' => 'sale',
            'source_version' => 1,
            'journal_date' => '2026-07-22',
            'journal_number' => 'JRN-20260722-999999',
            'posting_sequence' => 999999,
            'gudang_id' => null,
            'contact_type' => 'customer',
            'contact_id' => 210,
            'description' => 'draft',
            'status' => JournalStatus::Draft,
            'total_debit' => '1.00',
            'total_credit' => '1.00',
        ]);

        try {
            $service->post($actor, $this->request(210, '2026-07-22'));
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertSame('The source identity is already reserved by an unposted journal.', $e->getMessage());
        }

        // Draft remains unchanged
        $this->assertSame('JRN-20260722-999999', $draft->fresh()->journal_number);
        $this->assertSame(JournalStatus::Draft, $draft->fresh()->status);
        $this->assertDatabaseCount('journal_source_locks', 0);
        $this->assertSame(0, (int) DB::table('journal_posting_sequences')->sum('last_value'));
        $this->assertFalse((bool) $receivable->fresh()->is_used);
        $this->assertFalse((bool) $revenue->fresh()->is_used);
    }

    public function test_existing_approved_source_is_returned_without_creating_duplicate(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $actor = User::factory()->create(['role' => 'super_admin']);
        [$receivable, $revenue] = $this->validMappings($actor);
        $service = app(JournalPostingService::class);

        // Create an approved journal manually
        $approved = JournalEntry::create([
            'source_type' => 'sale',
            'source_id' => 211,
            'journal_type' => 'sale',
            'source_version' => 1,
            'journal_date' => '2026-07-22',
            'journal_number' => 'JRN-20260722-888888',
            'posting_sequence' => 888888,
            'gudang_id' => null,
            'contact_type' => 'customer',
            'contact_id' => 211,
            'description' => 'approved',
            'status' => JournalStatus::Approved,
            'total_debit' => '1.00',
            'total_credit' => '1.00',
        ]);

        try {
            $service->post($actor, $this->request(211, '2026-07-22'));
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertSame('The source identity is already reserved by an unposted journal.', $e->getMessage());
        }

        // Approved journal remains unchanged
        $this->assertSame('JRN-20260722-888888', $approved->fresh()->journal_number);
        $this->assertSame(JournalStatus::Approved, $approved->fresh()->status);
        $this->assertDatabaseCount('journal_source_locks', 0);
        $this->assertSame(0, (int) DB::table('journal_posting_sequences')->sum('last_value'));
        $this->assertFalse((bool) $receivable->fresh()->is_used);
        $this->assertFalse((bool) $revenue->fresh()->is_used);
    }

    public function test_source_lock_constraint_is_enforced_and_canonical_row_persists(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $actor = User::factory()->create(['role' => 'super_admin']);
        $this->validMappings($actor);
        $service = app(JournalPostingService::class);

        // First post creates lock row and journal
        $journal1 = $service->post($actor, $this->request(301, '2026-07-22'));
        $this->assertSame(1, $journal1->posting_sequence);
        $this->assertDatabaseCount('journal_source_locks', 1);

        // Second post with same source identity should find existing lock row
        // and return the existing journal (idempotent)
        $journal2 = $service->post($actor, $this->request(301, '2026-07-22'));
        $this->assertSame($journal1->id, $journal2->id);
        $this->assertSame(1, $journal2->posting_sequence);
        $this->assertDatabaseCount('journal_source_locks', 1); // Still only 1 lock row
        $this->assertDatabaseCount('journal_entries', 1); // Still only 1 journal
    }

    public function test_source_lock_duplicate_insert_is_handled_gracefully(): void
    {
        Carbon::setTestNow('2026-07-22 10:00:00');
        $actor = User::factory()->create(['role' => 'super_admin']);
        $this->validMappings($actor);

        // Manually insert a lock row to simulate concurrent insert
        DB::table('journal_source_locks')->insert([
            'source_type' => 'sale',
            'source_id' => 302,
            'journal_type' => 'sale',
            'source_version' => 1,
            'locked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseCount('journal_source_locks', 1);

        // Service should detect existing lock row via lockForUpdate and proceed
        // Since no journal exists yet, it will create one
        $service = app(JournalPostingService::class);
        $journal = $service->post($actor, $this->request(302, '2026-07-22'));

        $this->assertSame(JournalStatus::Posted, $journal->status);
        $this->assertSame(1, $journal->posting_sequence);
        $this->assertDatabaseCount('journal_source_locks', 1); // Canonical row persists
        $this->assertDatabaseCount('journal_entries', 1);
    }

    /** @return array{Account, Account} */
    private function validMappings(User $actor): array
    {
        return [
            $this->map($actor, MappingKey::ArReceivable, AccountCategory::Aset, '2026-01-01'),
            $this->map($actor, MappingKey::SalesRetailRevenue, AccountCategory::Pendapatan, '2026-01-01'),
        ];
    }

    private function map(User $actor, MappingKey $key, AccountCategory $category, string $from, ?string $to = null): Account
    {
        $account = Account::factory()->create(['category' => $category, 'is_active' => true, 'is_postable' => true]);
        app(AccountMappingService::class)->create($actor, $key, $account, $from, $to);

        return $account;
    }

    private function request(int $sourceId, string $date): JournalPostingRequest
    {
        $amount = Money::fromDecimalString('1000000.01');

        return new JournalPostingRequest(
            new SourceIdentity('sale', $sourceId, JournalType::Sale, 1), $date, 'Sale posting', null, 'customer', $sourceId,
            [
                new JournalPostingLine(MappingKey::ArReceivable, new LineOrder(10), $amount, null),
                new JournalPostingLine(MappingKey::SalesRetailRevenue, new LineOrder(20), null, $amount),
            ],
        );
    }

    // ========================================================================
    // REGRESSION TESTS: isLockIdentityConstraint() matcher strictness
    // ========================================================================

    private function invokeMatcher(UniqueConstraintViolationException $e): bool
    {
        $service = app(JournalPostingService::class);
        $reflection = new \ReflectionMethod($service, 'isLockIdentityConstraint');
        $reflection->setAccessible(true);

        return $reflection->invoke($service, $e);
    }

    private function makeUniqueViolation(
        string $sqlState,
        int $driverCode,
        string $message,
        string $sql = 'INSERT INTO journal_source_locks ...',
    ): UniqueConstraintViolationException {
        $pdo = new \PDOException($message);
        $pdo->errorInfo = [$sqlState, $driverCode, $message];

        // PDOException::$code is protected — use reflection to set SQLSTATE
        $ref = new \ReflectionProperty(\PDOException::class, 'code');
        $ref->setValue($pdo, $sqlState);

        return new UniqueConstraintViolationException('sqlite', $sql, [], $pdo);
    }

    // --- MySQL/MariaDB ACCEPTANCE ---

    public function test_matcher_accepts_mysql_with_correct_sqlstate_driver_code_and_exact_index(): void
    {
        $message = "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'sale-1-sale-1' for key 'journal_source_locks_identity_unique'";
        $e = $this->makeUniqueViolation('23000', 1062, $message);

        $this->assertTrue($this->invokeMatcher($e), 'MySQL with SQLSTATE 23000, driver code 1062, and exact index name must be accepted');
    }

    public function test_matcher_accepts_mariadb_with_correct_sqlstate_driver_code_and_exact_index(): void
    {
        $message = "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'sale-1-sale-1' for key 'journal_source_locks_identity_unique'";
        $e = $this->makeUniqueViolation('23000', 1062, $message);

        $this->assertTrue($this->invokeMatcher($e), 'MariaDB with SQLSTATE 23000, driver code 1062, and exact index name must be accepted');
    }

    // --- MySQL/MariaDB REJECTION ---

    public function test_matcher_rejects_mysql_with_wrong_sqlstate(): void
    {
        $message = "SQLSTATE[42000]: Syntax error or access violation: 1062 Duplicate entry 'sale-1-sale-1' for key 'journal_source_locks_identity_unique'";
        $e = $this->makeUniqueViolation('42000', 1062, $message);

        $this->assertFalse($this->invokeMatcher($e), 'MySQL with wrong SQLSTATE must be rejected even if constraint name matches');
    }

    public function test_matcher_rejects_mysql_with_wrong_driver_code(): void
    {
        $message = "SQLSTATE[23000]: Integrity constraint violation: 1061 Duplicate entry 'sale-1-sale-1' for key 'journal_source_locks_identity_unique'";
        $e = $this->makeUniqueViolation('23000', 1061, $message);

        $this->assertFalse($this->invokeMatcher($e), 'MySQL with wrong driver code (1061 instead of 1062) must be rejected');
    }

    public function test_matcher_rejects_mysql_with_unrelated_index(): void
    {
        $message = "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'sale-1-sale-1' for key 'journal_source_locks_some_other_index'";
        $e = $this->makeUniqueViolation('23000', 1062, $message);

        $this->assertFalse($this->invokeMatcher($e), 'MySQL with unrelated index name must be rejected');
    }

    public function test_matcher_rejects_mysql_index_with_identity_name_as_prefix(): void
    {
        $message = "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'sale-1-sale-1' for key 'journal_source_locks_identity_unique_backup'";
        $e = $this->makeUniqueViolation('23000', 1062, $message);

        $this->assertFalse($this->invokeMatcher($e), 'MySQL index name must match exactly, not by prefix');
    }

    public function test_matcher_rejects_mysql_with_only_table_name_fallback(): void
    {
        $message = "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'sale-1-sale-1' for key 'journal_source_locks.some_other_constraint'";
        $e = $this->makeUniqueViolation('23000', 1062, $message);

        $this->assertFalse($this->invokeMatcher($e), 'MySQL with only table name (no exact index) must be rejected - fallback is too broad');
    }

    public function test_matcher_rejects_constraint_name_only_without_sqlstate_verification(): void
    {
        $message = 'Some error mentioning journal_source_locks_identity_unique but with wrong SQLSTATE';
        $e = $this->makeUniqueViolation('HY000', 0, $message);

        $this->assertFalse($this->invokeMatcher($e), 'Constraint name in message without correct SQLSTATE must be rejected');
    }

    // --- SQLite ACCEPTANCE ---

    public function test_matcher_accepts_sqlite_with_correct_driver_code_and_exact_column_list(): void
    {
        $message = 'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: journal_source_locks.source_type, journal_source_locks.source_id, journal_source_locks.journal_type, journal_source_locks.source_version';
        $e = $this->makeUniqueViolation('23000', 19, $message);

        $this->assertTrue($this->invokeMatcher($e), 'SQLite with driver code 19 and exact column list must be accepted');
    }

    // --- SQLite REJECTION ---

    public function test_matcher_rejects_sqlite_with_partial_column_list(): void
    {
        $message = 'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: journal_source_locks.source_type, journal_source_locks.source_id, journal_source_locks.journal_type';
        $e = $this->makeUniqueViolation('23000', 19, $message);

        $this->assertFalse($this->invokeMatcher($e), 'SQLite with partial column list (missing source_version) must be rejected');
    }

    public function test_matcher_rejects_sqlite_with_version_instead_of_source_version(): void
    {
        $message = 'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: journal_source_locks.source_type, journal_source_locks.source_id, journal_source_locks.journal_type, journal_source_locks.version';
        $e = $this->makeUniqueViolation('23000', 19, $message);

        $this->assertFalse($this->invokeMatcher($e), 'SQLite with "version" instead of "source_version" must be rejected');
    }

    public function test_matcher_rejects_sqlite_with_wrong_driver_code(): void
    {
        $message = 'SQLSTATE[23000]: Integrity constraint violation: 20 UNIQUE constraint failed: journal_source_locks.source_type, journal_source_locks.source_id, journal_source_locks.journal_type, journal_source_locks.source_version';
        $e = $this->makeUniqueViolation('23000', 20, $message);

        $this->assertFalse($this->invokeMatcher($e), 'SQLite with wrong driver code (20 instead of 19) must be rejected');
    }

    public function test_matcher_rejects_sqlite_with_unrelated_unique_violation(): void
    {
        $message = 'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: journal_source_locks.locked_at, journal_source_locks.some_other_column';
        $e = $this->makeUniqueViolation('23000', 19, $message);

        $this->assertFalse($this->invokeMatcher($e), 'SQLite with unrelated columns must be rejected');
    }

    public function test_matcher_rejects_sqlite_with_superset_column_list(): void
    {
        $message = 'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: journal_source_locks.source_type, journal_source_locks.source_id, journal_source_locks.journal_type, journal_source_locks.source_version, journal_source_locks.locked_at';
        $e = $this->makeUniqueViolation('23000', 19, $message);

        $this->assertFalse($this->invokeMatcher($e), 'SQLite with superset column list (extra locked_at) must be rejected - exact list required');
    }

    public function test_matcher_rejects_sqlite_with_noisy_unrelated_columns(): void
    {
        $message = 'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: journal_source_locks.source_type, journal_source_locks.source_id, journal_source_locks.journal_type, journal_source_locks.source_version, journal_source_locks.created_at, journal_source_locks.updated_at';
        $e = $this->makeUniqueViolation('23000', 19, $message);

        $this->assertFalse($this->invokeMatcher($e), 'SQLite with noisy unrelated columns (created_at, updated_at) must be rejected - exact list required');
    }
}
