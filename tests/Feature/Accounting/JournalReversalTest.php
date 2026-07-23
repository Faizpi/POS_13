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
use App\Services\Accounting\JournalReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class JournalReversalTest extends TestCase
{
    use RefreshDatabase;

    public function test_reversal_creates_a_new_equal_and_opposite_posted_journal_with_traceable_linkage(): void
    {
        [$actor, $original] = $this->postedJournal(501, 1);
        $originalSnapshot = $original->fresh('lines')->toArray();

        $reversal = app(JournalReversalService::class)->reverse($actor, $original, 'Customer cancellation');

        $this->assertNotSame($original->id, $reversal->id);
        $this->assertSame(JournalStatus::Posted, $reversal->status);
        $this->assertSame(JournalType::Reversal, $reversal->journal_type);
        $this->assertSame($original->id, $reversal->original_journal_entry_id);
        $this->assertSame('Customer cancellation', $reversal->reversal_reason);
        $this->assertSame($actor->id, $reversal->reversed_by);
        $this->assertSame($original->total_credit, $reversal->total_debit);
        $this->assertSame($original->total_debit, $reversal->total_credit);
        $this->assertSame(
            $original->lines->map(fn ($line): array => [$line->account_id, $line->line_sequence, $line->credit, $line->debit])->all(),
            $reversal->lines->map(fn ($line): array => [$line->account_id, $line->line_sequence, $line->debit, $line->credit])->all(),
        );
        $this->assertSame('0.00', $this->netBalance($original, $reversal));
        $this->assertSame($reversal->id, $original->fresh()->reversalJournal?->id);
        $this->assertSame($original->id, $reversal->fresh()->originalJournal?->id);
        $this->assertSame($originalSnapshot, $original->fresh('lines')->toArray());
        $this->assertSame(JournalStatus::Posted, $original->fresh()->status);
    }

    public function test_reason_and_authorized_actor_are_required_without_partial_writes(): void
    {
        [$actor, $original] = $this->postedJournal(502, 1);
        $unauthorized = User::factory()->create(['role' => 'admin']);

        foreach ([[$actor, '   ', 'Reversal reason is required.'], [$unauthorized, 'Denied', 'The actor is not authorized to reverse journals.']] as [$candidate, $reason, $message]) {
            try {
                app(JournalReversalService::class)->reverse($candidate, $original, $reason);
                $this->fail('Expected DomainException was not thrown.');
            } catch (DomainException $exception) {
                $this->assertSame($message, $exception->getMessage());
            }
        }

        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('journal_lines', 2);
        $this->assertNull($original->fresh()->reversalJournal);
    }

    public function test_only_posted_non_reversal_journals_can_be_reversed(): void
    {
        [$actor, $original] = $this->postedJournal(503, 1);
        $reversal = app(JournalReversalService::class)->reverse($actor, $original, 'Cancel');

        try {
            app(JournalReversalService::class)->reverse($actor, $reversal, 'Reverse reversal');
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $exception) {
            $this->assertSame('Reversal journals cannot be reversed.', $exception->getMessage());
        }

        $this->assertDatabaseCount('journal_entries', 2);
        $this->assertDatabaseCount('journal_lines', 4);
    }

    public function test_second_reversal_is_rejected_and_canonical_reversal_remains_unchanged(): void
    {
        [$actor, $original] = $this->postedJournal(504, 1);
        $canonical = app(JournalReversalService::class)->reverse($actor, $original, 'First reason');

        try {
            app(JournalReversalService::class)->reverse($actor, $original->fresh(), 'Second reason');
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $exception) {
            $this->assertSame('The journal has already been reversed.', $exception->getMessage());
        }

        $this->assertDatabaseCount('journal_entries', 2);
        $this->assertDatabaseCount('journal_lines', 4);
        $this->assertSame($canonical->id, $original->fresh()->reversalJournal?->id);
        $this->assertSame('First reason', $canonical->fresh()->reversal_reason);
    }

    public function test_original_and_reversal_are_immutable_and_cannot_be_deleted(): void
    {
        [$actor, $original] = $this->postedJournal(505, 1);
        $reversal = app(JournalReversalService::class)->reverse($actor, $original, 'Cancel');

        foreach ([$original->fresh(), $reversal->fresh()] as $journal) {
            try {
                $journal->update(['description' => 'mutated']);
                $this->fail('Expected immutable update to fail.');
            } catch (\Throwable) {
                $this->assertNotSame('mutated', $journal->fresh()->description);
            }

            try {
                $journal->delete();
                $this->fail('Expected immutable delete to fail.');
            } catch (\Throwable) {
                $this->assertDatabaseHas('journal_entries', ['id' => $journal->id]);
            }
        }
    }

    public function test_reapproved_source_posts_a_higher_version_without_overwriting_old_lineage(): void
    {
        [$actor, $original] = $this->postedJournal(506, 1);
        $reversal = app(JournalReversalService::class)->reverse($actor, $original, 'Cancel before correction');
        $reposted = app(JournalPostingService::class)->post($actor, $this->request(506, 2));

        $this->assertSame(1, $original->source_version);
        $this->assertSame(2, $reposted->source_version);
        $this->assertSame($original->source_type, $reposted->source_type);
        $this->assertSame($original->source_id, $reposted->source_id);
        $this->assertSame($original->journal_type, $reposted->journal_type);
        $this->assertSame($reversal->id, $original->fresh()->reversalJournal?->id);
        $this->assertNull($reposted->reversalJournal);
        $this->assertDatabaseCount('journal_entries', 3);
    }

    public function test_failure_after_reversal_writes_rolls_back_header_lines_linkage_and_sequence(): void
    {
        [$actor, $original] = $this->postedJournal(507, 1);
        $sequenceBefore = (int) DB::table('journal_posting_sequences')->where('sequence_key', 'journal')->value('last_value');

        try {
            app(JournalReversalService::class)->reverse($actor, $original, 'Rollback probe', static function (): void {
                throw new \RuntimeException('interrupt reversal');
            });
            $this->fail('Expected interruption was not thrown.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('interrupt reversal', $exception->getMessage());
        }

        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('journal_lines', 2);
        $this->assertNull($original->fresh()->reversalJournal);
        $this->assertSame($sequenceBefore, (int) DB::table('journal_posting_sequences')->where('sequence_key', 'journal')->value('last_value'));
    }

    /** @return array{User, JournalEntry} */
    private function postedJournal(int $sourceId, int $version): array
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $this->map($actor, MappingKey::ArReceivable, AccountCategory::Aset);
        $this->map($actor, MappingKey::SalesRetailRevenue, AccountCategory::Pendapatan);

        return [$actor, app(JournalPostingService::class)->post($actor, $this->request($sourceId, $version))];
    }

    private function request(int $sourceId, int $version): JournalPostingRequest
    {
        $amount = Money::fromDecimalString('1000.01');

        return new JournalPostingRequest(
            new SourceIdentity('sale', $sourceId, JournalType::Sale, $version),
            '2026-07-22',
            'Sale posting',
            null,
            'customer',
            $sourceId,
            [
                new JournalPostingLine(MappingKey::ArReceivable, new LineOrder(10), $amount, null, description: 'Receivable'),
                new JournalPostingLine(MappingKey::SalesRetailRevenue, new LineOrder(20), null, $amount, description: 'Revenue'),
            ],
        );
    }

    private function map(User $actor, MappingKey $key, AccountCategory $category): Account
    {
        $account = Account::factory()->create(['category' => $category, 'is_active' => true, 'is_postable' => true]);
        app(AccountMappingService::class)->create($actor, $key, $account, '2026-01-01');

        return $account;
    }

    private function netBalance(JournalEntry $original, JournalEntry $reversal): string
    {
        $debit = Money::fromDecimalString($original->total_debit)->add(Money::fromDecimalString($reversal->total_debit));
        $credit = Money::fromDecimalString($original->total_credit)->add(Money::fromDecimalString($reversal->total_credit));

        return $debit->subtract($credit)->toDecimalString();
    }
}
