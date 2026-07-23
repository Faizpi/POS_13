<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Exports\AccountingReportExport;
use App\Filament\Concerns\ReportAccess;
use App\Models\Account;
use App\Models\Gudang;
use App\Services\Accounting\AccountingReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

final class BukuBesarPage extends Page
{
    use ReportAccess;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Buku Besar';

    protected static ?string $title = 'Buku Besar';

    protected static ?string $slug = 'buku-besar';

    protected string $view = 'filament.pages.reports.buku-besar';

    public ?string $filter_from = null;

    public ?string $filter_to = null;

    public ?int $filter_account_id = null;

    public ?string $filter_source = null;

    public ?int $filter_gudang_id = null;

    public function mount(): void
    {
        $this->filter_from = now()->startOfMonth()->toDateString();
        $this->filter_to = now()->toDateString();
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        $this->normalizeWarehouseFilter();
        if ($this->filter_account_id === null) {
            return ['rows' => collect(), 'opening_balance' => '0.00', 'movement_debit' => '0.00', 'movement_credit' => '0.00', 'ending_balance' => '0.00', 'is_management_view' => $this->filter_gudang_id !== null, 'warehouse_treatment' => 'Matching warehouse journal lines and global/null journal lines are included.'];
        }

        return app(AccountingReportService::class)->generalLedger($this->filter_account_id, $this->filters());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Export Excel')
                ->visible(fn (): bool => $this->filter_account_id !== null)
                ->action(function () {
                    $report = $this->getData();

                    return Excel::download(new AccountingReportExport('Buku Besar', $report), 'buku-besar.xlsx');
                }),
            Action::make('exportPdf')
                ->label('Export PDF')
                ->visible(fn (): bool => $this->filter_account_id !== null)
                ->action(function () {
                    $report = $this->getData();
                    $export = new AccountingReportExport('Buku Besar', $report);
                    $pdf = app('dompdf.wrapper')->loadView('reports.accounting-report-pdf', [
                        'rows' => $export->rows(),
                        'metadata' => $export->metadata(),
                    ]);

                    return response()->streamDownload(fn () => print ($pdf->output()), 'buku-besar.pdf');
                }),
        ];
    }

    /** @return array<int, string> */
    public function accountOptions(): array
    {
        return Account::query()->where('is_active', true)->orderBy('code')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    public function warehouseOptions(): array
    {
        $query = Gudang::query()->orderBy('nama_gudang');
        $allowed = $this->allowedWarehouseIds();
        if ($allowed !== null) {
            $query->whereIn('id', $allowed);
        }

        return $query->pluck('nama_gudang', 'id')->all();
    }

    /** @return array<string, int|string|null> */
    private function filters(): array
    {
        return ['date_from' => $this->filter_from, 'date_to' => $this->filter_to, 'source' => $this->filter_source, 'gudang_id' => $this->filter_gudang_id];
    }

    private function normalizeWarehouseFilter(): void
    {
        $allowed = $this->allowedWarehouseIds();
        if ($this->filter_gudang_id !== null && $allowed !== null && ! in_array($this->filter_gudang_id, $allowed, true)) {
            $this->filter_gudang_id = null;
        }
    }

    /** @return list<int>|null */
    private function allowedWarehouseIds(): ?array
    {
        $user = Auth::user();
        if ($user?->role === 'super_admin') {
            return null;
        }

        return $user?->spectatorGudangs()->pluck('gudangs.id')->map(fn (mixed $id): int => (int) $id)->all() ?? [];
    }
}
