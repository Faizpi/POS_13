<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Exports\AccountingReportExport;
use App\Filament\Concerns\ReportAccess;
use App\Models\CashBankAccount;
use App\Services\Accounting\CashBankReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

final class CashBankReportPage extends Page
{
    use ReportAccess;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Mutasi & Saldo Kas/Bank';

    protected static ?string $title = 'Mutasi & Saldo Kas/Bank';

    protected static ?string $slug = 'mutasi-kas-bank';

    protected string $view = 'filament.pages.reports.cash-bank';

    public ?string $filter_from = null;

    public ?string $filter_to = null;

    public ?int $filter_cash_bank_account_id = null;

    public function mount(): void
    {
        $this->filter_from = now()->startOfMonth()->toDateString();
        $this->filter_to = now()->toDateString();
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        if ($this->filter_cash_bank_account_id === null) {
            return [
                'opening_balance' => '0.00',
                'movement_debit' => '0.00',
                'movement_credit' => '0.00',
                'ending_balance' => '0.00',
                'rows' => collect(),
            ];
        }

        return app(CashBankReportService::class)->report(
            CashBankAccount::query()->findOrFail($this->filter_cash_bank_account_id),
            ['date_from' => $this->filter_from, 'date_to' => $this->filter_to],
        );
    }

    /** @return array<int, string> */
    public function cashBankOptions(): array
    {
        return CashBankAccount::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Export Excel')
                ->visible(fn (): bool => $this->filter_cash_bank_account_id !== null)
                ->action(function () {
                    $report = $this->getData();
                    $export = new AccountingReportExport('Mutasi Kas/Bank', [
                        'rows' => $report['rows'],
                        'movement_debit' => $report['movement_debit'],
                        'movement_credit' => $report['movement_credit'],
                        'is_management_view' => false,
                        'warehouse_treatment' => 'Cash/bank account view.',
                    ]);

                    return Excel::download($export, 'mutasi-kas-bank.xlsx');
                }),
        ];
    }
}
