<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Accounting\AccountCategory;
use App\Accounting\JournalStatus;
use App\Accounting\JournalType;
use App\Accounting\LineOrder;
use App\Accounting\Money;
use App\Accounting\SourceIdentity;
use App\Exports\AccountingReportExport;
use App\Models\Account;
use App\Services\Accounting\AccountingReportService;
use App\Services\Accounting\LedgerPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AccountingReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_excel_and_pdf_export_payloads_use_the_same_report_service_totals_and_metadata(): void
    {
        $debit = Account::factory()->create(['category' => AccountCategory::Aset, 'is_active' => true, 'is_postable' => true]);
        $credit = Account::factory()->create(['category' => AccountCategory::Pendapatan, 'is_active' => true, 'is_postable' => true]);
        $journal = app(LedgerPersistenceService::class)->persist(new SourceIdentity('sale', 1, JournalType::Sale, 1), '2026-07-22', 'Export fixture', null, null, null, [
            ['account_id' => $debit->id, 'line_order' => new LineOrder(10), 'debit' => Money::fromDecimalString('42.25'), 'credit' => null],
            ['account_id' => $credit->id, 'line_order' => new LineOrder(20), 'debit' => null, 'credit' => Money::fromDecimalString('42.25')],
        ]);
        $journal->update(['status' => JournalStatus::Approved]);
        $journal->update(['status' => JournalStatus::Posted, 'journal_number' => 'JRN-000001', 'posting_sequence' => 1]);

        $report = app(AccountingReportService::class)->journal(['date_from' => '2026-07-22', 'date_to' => '2026-07-22']);
        $export = new AccountingReportExport('Laporan Jurnal', $report);

        $this->assertSame('42.25', $export->metadata()['total_debit']);
        $this->assertSame('42.25', $export->metadata()['total_credit']);
        $this->assertSame($report['rows']->all(), $export->rows()->all());
        $this->assertSame([['2026-07-22', 'JRN-000001', 'sale', '1', '42.25', '42.25', '']], $export->collection()->all());
        $this->assertStringContainsString('42.25', view('reports.accounting-report-pdf', [
            'rows' => $export->rows(),
            'metadata' => $export->metadata(),
        ])->render());
    }
}
