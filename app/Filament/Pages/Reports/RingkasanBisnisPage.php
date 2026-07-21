<?php

namespace App\Filament\Pages\Reports;

use App\Exports\RingkasanBisnisExport;
use App\Filament\Concerns\ReportAccess;
use App\Models\Gudang;
use App\Services\RingkasanBisnisService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

class RingkasanBisnisPage extends Page
{
    use ReportAccess;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Ringkasan Bisnis';

    protected static ?string $title = 'Ringkasan Bisnis';

    protected static ?string $slug = 'ringkasan-bisnis';

    protected string $view = 'filament.pages.reports.ringkasan-bisnis';

    // Filter state
    public ?string $filter_from = null;

    public ?string $filter_to = null;

    public ?int $filter_gudang_id = null;

    public function mount(): void
    {
        $this->filter_from = now()->startOfMonth()->format('Y-m-d');
        $this->filter_to = now()->format('Y-m-d');
    }

    public function getData(): array
    {
        $this->validateAndResetUnauthorizedFilter();

        $service = app(RingkasanBisnisService::class);
        $allowedWarehouseIds = $this->getAllowedWarehouseIds();

        return $service->getRingkasan(
            $this->filter_from,
            $this->filter_to,
            $this->filter_gudang_id,
            $allowedWarehouseIds
        );
    }

    /**
     * Get allowed warehouse IDs based on user role.
     */
    private function getAllowedWarehouseIds(): ?array
    {
        $user = Auth::user();

        if ($user->role === 'super_admin') {
            return null; // Super admin sees all warehouses
        }

        if ($user->role === 'spectator') {
            return $user->spectatorGudangs()->pluck('gudangs.id')->toArray();
        }

        return [];
    }

    /**
     * Get warehouse options for the filter dropdown based on user role.
     */
    private function getWarehouseOptions(): array
    {
        $user = Auth::user();

        if ($user->role === 'super_admin') {
            return Gudang::orderBy('nama_gudang')->pluck('nama_gudang', 'id')->toArray();
        }

        if ($user->role === 'spectator') {
            $allowedIds = $user->spectatorGudangs()->pluck('gudangs.id')->toArray();
            if (empty($allowedIds)) {
                return [];
            }

            return Gudang::whereIn('id', $allowedIds)
                ->orderBy('nama_gudang')
                ->pluck('nama_gudang', 'id')
                ->toArray();
        }

        return [];
    }

    /**
     * Validate and reset filter_gudang_id if unauthorized.
     */
    private function validateAndResetUnauthorizedFilter(): void
    {
        $user = Auth::user();

        if ($this->filter_gudang_id === null) {
            return;
        }

        if ($user->role === 'super_admin') {
            // Super admin can access any warehouse, but validate it exists
            if (! Gudang::where('id', $this->filter_gudang_id)->exists()) {
                $this->filter_gudang_id = null;
            }

            return;
        }

        if ($user->role === 'spectator') {
            $allowedIds = $user->spectatorGudangs()->pluck('gudangs.id')->toArray();
            if (! in_array($this->filter_gudang_id, $allowedIds)) {
                // Reset unauthorized selection
                $this->filter_gudang_id = null;
            }

            return;
        }

        // Other roles should not have access
        $this->filter_gudang_id = null;
    }

    public function applyFilter(): void
    {
        // Trigger re-render — Livewire reactivity handles the rest
        Notification::make()
            ->title('Filter diterapkan')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        $user = Auth::user();

        return [
            Action::make('filter')
                ->label('Filter Periode')
                ->icon('heroicon-o-funnel')
                ->color('gray')
                ->form([
                    DatePicker::make('from')
                        ->label('Dari Tanggal')
                        ->default($this->filter_from)
                        ->required(),

                    DatePicker::make('to')
                        ->label('Sampai Tanggal')
                        ->default($this->filter_to)
                        ->required(),

                    Select::make('gudang_id')
                        ->label('Gudang (Opsional)')
                        ->options(fn () => $this->getWarehouseOptions())
                        ->placeholder('Semua Gudang')
                        ->searchable()
                        ->preload(),
                ])
                ->action(function (array $data) {
                    $this->filter_from = $data['from'];
                    $this->filter_to = $data['to'];
                    $this->filter_gudang_id = $data['gudang_id'] ?? null;
                    Notification::make()->title('Filter diterapkan')->success()->send();
                })
                ->modalSubmitActionLabel('Terapkan')
                ->modalCancelActionLabel('Batal'),

            Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn () => $user?->canExportExcel())
                ->action(function () {
                    $service = app(RingkasanBisnisService::class);
                    $allowedWarehouseIds = $this->getAllowedWarehouseIds();
                    $data = $service->getRingkasan(
                        $this->filter_from,
                        $this->filter_to,
                        $this->filter_gudang_id,
                        $allowedWarehouseIds
                    );
                    $gudangName = $this->filter_gudang_id
                        ? Gudang::find($this->filter_gudang_id)?->nama_gudang ?? 'Semua Gudang'
                        : 'Semua Gudang';
                    $filename = 'Ringkasan_Bisnis_'.($this->filter_from ?? 'all').'_'.($this->filter_to ?? 'all').'.xlsx';

                    return response()->streamDownload(function () use ($data, $gudangName) {
                        echo Excel::raw(new RingkasanBisnisExport($data, null, null, $gudangName), \Maatwebsite\Excel\Excel::XLSX);
                    }, $filename);
                }),

            Action::make('exportPdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->visible(fn () => $user?->canExportPdf())
                ->action(function () {
                    $service = app(RingkasanBisnisService::class);
                    $allowedWarehouseIds = $this->getAllowedWarehouseIds();
                    $data = $service->getRingkasan(
                        $this->filter_from,
                        $this->filter_to,
                        $this->filter_gudang_id,
                        $allowedWarehouseIds
                    );
                    $pdf = app('dompdf.wrapper');
                    $pdf->loadView('reports.ringkasan-bisnis-pdf', [
                        'data' => $data,
                        'from' => $this->filter_from,
                        'to' => $this->filter_to,
                        'gudang' => $this->filter_gudang_id
                            ? Gudang::find($this->filter_gudang_id)?->nama_gudang
                            : 'Semua Gudang',
                        'generatedBy' => Auth::user()?->name ?? 'System',
                        'generatedAt' => now()->format('d/m/Y H:i:s'),
                    ]);
                    $filename = 'Ringkasan_Bisnis_'.($this->filter_from ?? 'all').'_'.($this->filter_to ?? 'all').'.pdf';

                    return response()->streamDownload(fn () => print ($pdf->output()), $filename);
                }),
        ];
    }
}
