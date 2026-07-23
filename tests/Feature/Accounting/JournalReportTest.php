<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Accounting\AccountCategory;
use App\Accounting\JournalStatus;
use App\Accounting\JournalType;
use App\Accounting\LineOrder;
use App\Accounting\Money;
use App\Accounting\SourceIdentity;
use App\Models\Account;
use App\Models\Gudang;
use App\Models\JournalEntry;
use App\Services\Accounting\AccountingReportService;
use App\Services\Accounting\LedgerPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class JournalReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_posted_journals_are_grouped_by_source_with_exact_totals_and_deterministic_order(): void
    {
        $gudang = Gudang::query()->create(['nama_gudang' => 'Jakarta', 'alamat_gudang' => 'Jl. Test']);
        [$debit, $credit] = $this->accounts();
        $this->postJournal($debit, $credit, 'sale', 20, JournalType::Sale, '2026-07-22', 2, '100.10', $gudang->id);
        $this->postJournal($debit, $credit, 'sale', 10, JournalType::Sale, '2026-07-22', 1, '50.05', $gudang->id);
        $this->draft($debit, $credit);
        $this->assertSame(2, JournalEntry::query()->where('status', 'posted')->where('source_type', 'sale')->where('gudang_id', $gudang->id)->count());
        $this->assertSame(['2026-07-22', '2026-07-22'], JournalEntry::query()->where('status', 'posted')->where('source_type', 'sale')->orderBy('posting_sequence')->get()->map(fn (JournalEntry $entry): string => $entry->journal_date->format('Y-m-d'))->all());

        $this->assertCount(2, app(AccountingReportService::class)->journal()['rows']);

        $report = app(AccountingReportService::class)->journal([
            'date_from' => '2026-07-22',
            'date_to' => '2026-07-22',
            'source' => 'sale',
            'gudang_id' => $gudang->id,
        ]);

        $this->assertSame(['JRN-000001', 'JRN-000002'], $report['rows']->pluck('journal_number')->all());
        $this->assertSame('150.15', $report['total_debit']);
        $this->assertSame('150.15', $report['total_credit']);
        $this->assertSame(['sale'], $report['groups']->keys()->all());
        $this->assertSame('150.15', $report['groups']['sale']['total_debit']);
    }

    public function test_warehouse_management_view_includes_matching_and_global_lines_only(): void
    {
        $jakarta = Gudang::query()->create(['nama_gudang' => 'Jakarta', 'alamat_gudang' => 'Jl. Jakarta']);
        $bandung = Gudang::query()->create(['nama_gudang' => 'Bandung', 'alamat_gudang' => 'Jl. Bandung']);
        [$debit, $credit] = $this->accounts();
        $this->postJournal($debit, $credit, 'sale', 31, JournalType::Sale, '2026-07-22', 1, '10.00', $jakarta->id);
        $this->postJournal($debit, $credit, 'sale', 32, JournalType::Sale, '2026-07-22', 2, '20.00', null);
        $this->postJournal($debit, $credit, 'sale', 33, JournalType::Sale, '2026-07-22', 3, '30.00', $bandung->id);

        $report = app(AccountingReportService::class)->journal(['gudang_id' => $jakarta->id]);

        $this->assertTrue($report['is_management_view']);
        $this->assertSame('Matching warehouse journals and global/null journal lines are included.', $report['warehouse_treatment']);
        $this->assertSame(['JRN-000001', 'JRN-000002'], $report['rows']->pluck('journal_number')->all());
    }

    private function accounts(): array
    {
        return [
            Account::factory()->create(['category' => AccountCategory::Aset, 'is_active' => true, 'is_postable' => true]),
            Account::factory()->create(['category' => AccountCategory::Pendapatan, 'is_active' => true, 'is_postable' => true]),
        ];
    }

    private function postJournal(Account $debit, Account $credit, string $source, int $sourceId, JournalType $type, string $date, int $sequence, string $amount, ?int $gudangId): void
    {
        $journal = app(LedgerPersistenceService::class)->persist(new SourceIdentity($source, $sourceId, $type, 1), $date, 'Report fixture', $gudangId, null, null, [
            ['account_id' => $debit->id, 'line_order' => new LineOrder(10), 'debit' => Money::fromDecimalString($amount), 'credit' => null, 'gudang_id' => $gudangId],
            ['account_id' => $credit->id, 'line_order' => new LineOrder(20), 'debit' => null, 'credit' => Money::fromDecimalString($amount), 'gudang_id' => $gudangId],
        ]);
        $journal->update(['status' => JournalStatus::Approved]);
        $journal->update(['status' => JournalStatus::Posted, 'journal_number' => sprintf('JRN-%06d', $sequence), 'posting_sequence' => $sequence]);
        $this->assertDatabaseHas('journal_entries', ['id' => $journal->id, 'status' => 'posted']);
    }

    private function draft(Account $debit, Account $credit): void
    {
        app(LedgerPersistenceService::class)->persist(new SourceIdentity('sale', 99, JournalType::Sale, 1), '2026-07-22', 'Draft', null, null, null, [
            ['account_id' => $debit->id, 'line_order' => new LineOrder(10), 'debit' => Money::fromDecimalString('999.00'), 'credit' => null],
            ['account_id' => $credit->id, 'line_order' => new LineOrder(20), 'debit' => null, 'credit' => Money::fromDecimalString('999.00')],
        ]);
    }
}
