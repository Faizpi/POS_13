<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Accounting\AccountCategory;
use App\Accounting\DomainException;
use App\Accounting\IllegalTransitionException;
use App\Accounting\JournalStatus;
use App\Accounting\JournalType;
use App\Accounting\LineOrder;
use App\Accounting\Money;
use App\Accounting\SourceIdentity;
use App\Models\Account;
use App\Models\Gudang;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Services\Accounting\LedgerPersistenceService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LedgerPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_a_balanced_draft_with_dimensions_and_explicit_line_order(): void
    {
        $gudang = Gudang::query()->create(['nama_gudang' => 'Gudang Ledger', 'alamat_gudang' => 'Jl. Ledger']);
        [$debitAccount, $creditAccount] = $this->postableAccounts();

        $journal = app(LedgerPersistenceService::class)->persist(
            sourceIdentity: new SourceIdentity('sale', 101, JournalType::Sale, 1),
            journalDate: '2026-07-22',
            description: 'Draft penjualan kredit',
            gudangId: $gudang->id,
            contactType: 'customer',
            contactId: 99,
            lines: [
                $this->line($debitAccount, 10, debit: '1000000.00'),
                $this->line($creditAccount, 20, credit: '1000000.00', gudangId: $gudang->id, contactType: 'customer', contactId: 99),
            ],
        );

        $this->assertSame(JournalStatus::Draft, $journal->status);
        $this->assertNull($journal->journal_number, 'Todo 10 owns final journal number assignment.');
        $this->assertSame(0, $journal->posting_sequence, 'Todo 10 owns final posting sequence assignment.');
        $this->assertSame($gudang->id, $journal->gudang_id);
        $this->assertSame('customer', $journal->contact_type);
        $this->assertSame(99, $journal->contact_id);
        $this->assertSame('1000000.00', $journal->total_debit);
        $this->assertSame('1000000.00', $journal->total_credit);

        $lines = $journal->lines()->orderBy('line_sequence')->get();
        $this->assertSame([10, 20], $lines->pluck('line_sequence')->all());
        $this->assertSame('1000000.00', $lines[0]->debit);
        $this->assertSame('0.00', $lines[0]->credit);
        $this->assertSame('0.00', $lines[1]->debit);
        $this->assertSame('1000000.00', $lines[1]->credit);
        $this->assertSame($gudang->id, $lines[1]->gudang_id);
        $this->assertSame('customer', $lines[1]->contact_type);
        $this->assertSame(99, $lines[1]->contact_id);
        $this->assertTrue($debitAccount->fresh()->is_used);
        $this->assertTrue($creditAccount->fresh()->is_used);
    }

    public function test_invalid_line_shapes_and_unbalanced_sets_leave_no_partial_rows(): void
    {
        [$debitAccount, $creditAccount] = $this->postableAccounts();

        foreach ([
            [],
            [$this->line($debitAccount, 10, debit: '1.00')],
            [$this->line($debitAccount, 10, debit: '1.00', credit: '1.00'), $this->line($creditAccount, 20, credit: '1.00')],
            [$this->line($debitAccount, 10), $this->line($creditAccount, 20, credit: '1.00')],
            [$this->line($debitAccount, 10, debit: '-1.00'), $this->line($creditAccount, 20, credit: '1.00')],
            [$this->line($debitAccount, 10, debit: '2.00'), $this->line($creditAccount, 20, credit: '1.00')],
        ] as $lines) {
            try {
                app(LedgerPersistenceService::class)->persist(
                    new SourceIdentity('sale', random_int(1, 999999), JournalType::Sale, 1),
                    '2026-07-22',
                    'Invalid journal',
                    null,
                    null,
                    null,
                    $lines,
                );
                $this->fail('Invalid journal line set persisted.');
            } catch (DomainException) {
                $this->assertDatabaseCount('journal_entries', 0);
                $this->assertDatabaseCount('journal_lines', 0);
            }
        }
    }

    public function test_inactive_or_non_postable_account_leaves_no_partial_rows(): void
    {
        [$debitAccount, $creditAccount] = $this->postableAccounts();

        foreach (['is_active', 'is_postable'] as $column) {
            $debitAccount->update([$column => false]);

            $this->expectException(DomainException::class);

            try {
                app(LedgerPersistenceService::class)->persist(
                    new SourceIdentity('sale', $column === 'is_active' ? 1 : 2, JournalType::Sale, 1),
                    '2026-07-22',
                    'Invalid account',
                    null,
                    null,
                    null,
                    [$this->line($debitAccount, 10, debit: '1.00'), $this->line($creditAccount, 20, credit: '1.00')],
                );
            } finally {
                $this->assertDatabaseCount('journal_entries', 0);
                $this->assertDatabaseCount('journal_lines', 0);
                $debitAccount->update([$column => true]);
            }
        }
    }

    public function test_source_identity_and_line_sequence_are_database_constrained(): void
    {
        [$debitAccount, $creditAccount] = $this->postableAccounts();
        $service = app(LedgerPersistenceService::class);
        $identity = new SourceIdentity('sale', 200, JournalType::Sale, 1);

        $journal = $service->persist($identity, '2026-07-22', 'First source', null, null, null, [
            $this->line($debitAccount, 10, debit: '1.00'),
            $this->line($creditAccount, 20, credit: '1.00'),
        ]);

        $this->expectException(QueryException::class);

        try {
            JournalEntry::query()->create([
                'source_type' => $identity->sourceType,
                'source_id' => $identity->sourceId,
                'journal_type' => $identity->journalType,
                'source_version' => $identity->sourceVersion,
                'journal_date' => '2026-07-22',
                'description' => 'Duplicate source',
                'status' => JournalStatus::Draft,
                'total_debit' => '0.00',
                'total_credit' => '0.00',
                'posting_sequence' => 0,
            ]);
        } finally {
            $this->assertDatabaseCount('journal_entries', 1);

            $this->expectException(QueryException::class);
            JournalLine::query()->create([
                'journal_entry_id' => $journal->id,
                'account_id' => $debitAccount->id,
                'line_sequence' => 10,
                'debit' => '1.00',
                'credit' => '0.00',
            ]);
        }
    }

    public function test_a_database_interruption_rolls_back_header_and_model_rejects_direct_dual_sided_lines(): void
    {
        [$debitAccount, $creditAccount] = $this->postableAccounts();

        try {
            app(LedgerPersistenceService::class)->persist(
                new SourceIdentity('sale', 250, JournalType::Sale, 1),
                '2026-07-22',
                'Foreign key interruption',
                null,
                null,
                null,
                [
                    $this->line($debitAccount, 10, debit: '1.00'),
                    $this->line($creditAccount, 20, credit: '1.00', gudangId: 999_999),
                ],
            );
            $this->fail('Invalid line warehouse persisted.');
        } catch (QueryException) {
            $this->assertDatabaseCount('journal_entries', 0);
            $this->assertDatabaseCount('journal_lines', 0);
        }

        $journal = app(LedgerPersistenceService::class)->persist(
            new SourceIdentity('sale', 251, JournalType::Sale, 1),
            '2026-07-22',
            'Direct-line guard',
            null,
            null,
            null,
            [$this->line($debitAccount, 10, debit: '1.00'), $this->line($creditAccount, 20, credit: '1.00')],
        );

        $this->expectException(DomainException::class);
        JournalLine::query()->create([
            'journal_entry_id' => $journal->id,
            'account_id' => $debitAccount->id,
            'line_sequence' => 30,
            'debit' => '1.00',
            'credit' => '1.00',
        ]);
    }

    public function test_draft_is_mutable_but_posted_header_and_historical_lines_cannot_change_or_delete(): void
    {
        [$debitAccount, $creditAccount] = $this->postableAccounts();
        $journal = app(LedgerPersistenceService::class)->persist(
            new SourceIdentity('sale', 301, JournalType::Sale, 1),
            '2026-07-22',
            'Draft journal',
            null,
            null,
            null,
            [$this->line($debitAccount, 10, debit: '1.00'), $this->line($creditAccount, 20, credit: '1.00')],
        );

        $journal->update(['description' => 'Updated draft']);
        $this->assertSame('Updated draft', $journal->fresh()->description);

        $journal->update(['status' => JournalStatus::Approved]);
        $journal->update(['status' => JournalStatus::Posted]);
        $line = $journal->lines()->firstOrFail();

        foreach ([
            fn (): bool => $journal->update(['description' => 'Edited posted']),
            fn (): bool => $journal->delete(),
            fn (): bool => $line->update(['debit' => '2.00']),
            fn (): bool => $line->delete(),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Posted ledger history was mutable.');
            } catch (DomainException) {
                $this->assertDatabaseHas('journal_entries', ['id' => $journal->id, 'status' => JournalStatus::Posted->value]);
                $this->assertDatabaseHas('journal_lines', ['id' => $line->id, 'debit' => '1.00']);
            }
        }
    }

    public function test_database_guards_reject_invalid_lines_and_roll_back_query_builder_transactions(): void
    {
        [$debitAccount, $creditAccount] = $this->postableAccounts();
        $journal = app(LedgerPersistenceService::class)->persist(
            new SourceIdentity('sale', 302, JournalType::Sale, 1),
            '2026-07-22',
            'Database amount guards',
            null,
            null,
            null,
            [$this->line($debitAccount, 10, debit: '1.00'), $this->line($creditAccount, 20, credit: '1.00')],
        );

        foreach ([
            ['debit' => '0.00', 'credit' => '0.00'],
            ['debit' => '1.00', 'credit' => '1.00'],
            ['debit' => '-1.00', 'credit' => '0.00'],
        ] as $amounts) {
            try {
                DB::table('journal_lines')->insert([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $debitAccount->id,
                    'line_sequence' => random_int(30, 99_999),
                    ...$amounts,
                ]);
                $this->fail('The database accepted an invalid journal line.');
            } catch (QueryException) {
                $this->assertDatabaseCount('journal_lines', 2);
            }
        }

        try {
            DB::table('accounts')->where('id', $debitAccount->id)->update(['is_used' => false]);

            DB::transaction(function () use ($journal, $debitAccount): void {
                Account::query()->whereKey($debitAccount->id)->update(['is_used' => true]);

                DB::table('journal_lines')->insert([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $debitAccount->id,
                    'line_sequence' => 100_000,
                    'debit' => '0.00',
                    'credit' => '0.00',
                ]);
            });
            $this->fail('An invalid database line must abort its transaction.');
        } catch (QueryException) {
            $this->assertFalse($debitAccount->fresh()->is_used);
            $this->assertDatabaseCount('journal_lines', 2);
        }
    }

    public function test_database_guards_protect_posted_and_reversed_records_and_status_contract(): void
    {
        [$debitAccount, $creditAccount] = $this->postableAccounts();
        $journal = app(LedgerPersistenceService::class)->persist(
            new SourceIdentity('sale', 303, JournalType::Sale, 1),
            '2026-07-22',
            'Database historical guards',
            null,
            null,
            null,
            [$this->line($debitAccount, 10, debit: '1.00'), $this->line($creditAccount, 20, credit: '1.00')],
        );

        try {
            $journal->update(['status' => JournalStatus::Posted]);
            $this->fail('Draft journals must not transition directly to posted.');
        } catch (IllegalTransitionException) {
            $this->assertSame(JournalStatus::Draft, $journal->fresh()->status);
        }

        try {
            DB::table('journal_entries')->where('id', $journal->id)->update(['status' => JournalStatus::Posted->value]);
            $this->fail('The database must not allow a draft journal to bypass approval.');
        } catch (QueryException) {
            $this->assertSame(JournalStatus::Draft, $journal->fresh()->status);
        }

        $journal->update(['status' => JournalStatus::Approved]);
        $journal->update(['status' => JournalStatus::Posted]);

        foreach ([JournalStatus::Posted, JournalStatus::Reversed] as $status) {
            if ($status === JournalStatus::Reversed) {
                $journal->update(['status' => JournalStatus::Reversed]);
            }

            $line = $journal->lines()->firstOrFail();

            foreach ([
                fn (): int => DB::table('journal_entries')->where('id', $journal->id)->update(['description' => 'Mutated history']),
                fn (): int => DB::table('journal_entries')->where('id', $journal->id)->delete(),
                fn (): int => DB::table('journal_lines')->where('id', $line->id)->update(['debit' => '2.00']),
                fn (): int => DB::table('journal_lines')->where('id', $line->id)->delete(),
            ] as $operation) {
                try {
                    $operation();
                    $this->fail("The {$status->value} journal history was mutable through the query builder.");
                } catch (QueryException) {
                    $this->assertDatabaseHas('journal_entries', ['id' => $journal->id, 'status' => $status->value]);
                    $this->assertDatabaseHas('journal_lines', ['id' => $line->id, 'debit' => '1.00']);
                }
            }
        }
    }

    public function test_schema_exposes_additive_ledger_indexes_and_deterministic_sequence_fields(): void
    {
        $this->assertTrue(Schema::hasTable('journal_entries'));
        $this->assertTrue(Schema::hasTable('journal_lines'));

        foreach (['source_type', 'source_id', 'journal_type', 'source_version', 'journal_date', 'journal_number', 'posting_sequence', 'gudang_id', 'contact_type', 'contact_id', 'status', 'total_debit', 'total_credit'] as $column) {
            $this->assertTrue(Schema::hasColumn('journal_entries', $column), "Missing journal header column: {$column}");
        }

        foreach (['journal_entry_id', 'account_id', 'line_sequence', 'gudang_id', 'contact_type', 'contact_id', 'debit', 'credit'] as $column) {
            $this->assertTrue(Schema::hasColumn('journal_lines', $column), "Missing journal line column: {$column}");
        }
    }

    /** @return array{0: Account, 1: Account} */
    private function postableAccounts(): array
    {
        return [
            Account::factory()->create(['category' => AccountCategory::Aset, 'is_active' => true, 'is_postable' => true]),
            Account::factory()->create(['category' => AccountCategory::Pendapatan, 'is_active' => true, 'is_postable' => true]),
        ];
    }

    /** @return array<string, mixed> */
    private function line(Account $account, int $sequence, ?string $debit = null, ?string $credit = null, ?int $gudangId = null, ?string $contactType = null, ?int $contactId = null): array
    {
        return [
            'account_id' => $account->id,
            'line_order' => new LineOrder($sequence),
            'debit' => $debit === null ? null : Money::fromDecimalString($debit),
            'credit' => $credit === null ? null : Money::fromDecimalString($credit),
            'gudang_id' => $gudangId,
            'contact_type' => $contactType,
            'contact_id' => $contactId,
        ];
    }
}
