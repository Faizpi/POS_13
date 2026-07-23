<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Exports\AccountingReportExport;
use App\Filament\Concerns\ReportAccess;
use App\Services\Accounting\AccountingReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

final class NeracaSaldoPage extends Page
{
    use ReportAccess;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Neraca Saldo';

    protected static ?string $title = 'Neraca Saldo';

    protected static ?string $slug = 'neraca-saldo';

    protected string $view = 'filament.pages.reports.neraca-saldo';

    public ?string $filter_from = null;

    public ?string $filter_to = null;

    public function mount(): void
    {
        $this->filter_from = now()->startOfMonth()->toDateString();
        $this->filter_to = now()->toDateString();
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        return app(AccountingReportService::class)->trialBalance([
            'date_from' => $this->filter_from,
            'date_to' => $this->filter_to,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')->label('Export Excel')->action(function () {
                $report = $this->getData();
                $export = new AccountingReportExport('Neraca Saldo', [
                    'rows' => $report['rows'],
                    'movement_debit' => $report['totals']['movement_debit'],
                    'movement_credit' => $report['totals']['movement_credit'],
                    'is_management_view' => false,
                    'warehouse_treatment' => 'Consolidated view only.',
                ]);

                return Excel::download($export, 'neraca-saldo.xlsx');
            }),
        ];
    }
}
