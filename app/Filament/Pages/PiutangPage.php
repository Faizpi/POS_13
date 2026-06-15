<?php

namespace App\Filament\Pages;

use App\Models\Gudang;
use App\Models\Penjualan;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use BackedEnum;
use UnitEnum;

class PiutangPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox';

    protected static string|UnitEnum|null $navigationGroup = 'Piutang';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Dashboard Piutang';

    protected static ?string $title = 'Dashboard Piutang';

    protected string $view = 'filament.pages.piutang';

    public ?string $filter_from = null;
    public ?string $filter_to = null;
    public ?int $filter_gudang_id = null;

    public function mount(): void
    {
        $this->filter_from = now()->startOfYear()->format('Y-m-d');
        $this->filter_to = now()->format('Y-m-d');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return in_array($user?->role, ['user', 'admin', 'spectator', 'super_admin']);
    }

    public function getChartData(): array
    {
        $user = Auth::user();

        $query = Penjualan::select(
            DB::raw("DATE_FORMAT(tgl_jatuh_tempo, '%Y-%m') as bulan"),
            DB::raw('SUM(grand_total) as total'),
            DB::raw('COUNT(*) as jumlah')
        )
        ->whereNotNull('tgl_jatuh_tempo')
        ->whereIn('status', ['Approved', 'Lunas']);

        if ($user->role === 'user') {
            $query->where('user_id', $user->id);
        } elseif (in_array($user->role, ['admin', 'spectator'])) {
            if ($user->current_gudang_id) {
                $query->where('gudang_id', $user->current_gudang_id);
            }
        }

        if ($this->filter_gudang_id) {
            $query->where('gudang_id', $this->filter_gudang_id);
        }

        if ($this->filter_from) {
            $query->where('tgl_jatuh_tempo', '>=', $this->filter_from);
        }
        if ($this->filter_to) {
            $query->where('tgl_jatuh_tempo', '<=', $this->filter_to);
        }

        $rows = $query->groupBy('bulan')->orderBy('bulan')->get();

        return [
            'labels' => $rows->pluck('bulan')->map(fn($b) => \Carbon\Carbon::parse($b . '-01')->format('M Y'))->toArray(),
            'totals' => $rows->pluck('total')->map(fn($v) => (float) $v)->toArray(),
            'counts' => $rows->pluck('jumlah')->toArray(),
        ];
    }

    public function getListToko(): \Illuminate\Support\Collection
    {
        $user = Auth::user();

        if (!in_array($user->role, ['spectator', 'super_admin'])) {
            return collect();
        }

        $query = Penjualan::with(['gudang'])
            ->whereIn('status', ['Approved', 'Lunas'])
            ->whereNotNull('tgl_jatuh_tempo');

        if ($this->filter_gudang_id) {
            $query->where('gudang_id', $this->filter_gudang_id);
        }
        if ($this->filter_from) {
            $query->where('tgl_jatuh_tempo', '>=', $this->filter_from);
        }
        if ($this->filter_to) {
            $query->where('tgl_jatuh_tempo', '<=', $this->filter_to);
        }

        return $query->orderBy('tgl_jatuh_tempo')->get()->map(function ($p) {
            $totalBayar = $p->pembayarans()->where('status', 'Approved')->sum('jumlah_bayar');
            $sisa = max(0, $p->grand_total - $totalBayar);
            return [
                'nomor' => $p->custom_number,
                'pelanggan' => $p->pelanggan,
                'gudang' => $p->gudang?->nama_gudang,
                'tgl_jatuh_tempo' => $p->tgl_jatuh_tempo?->format('d/m/Y'),
                'jatuh_tempo_lewat' => $p->tgl_jatuh_tempo?->isPast() && $p->status === 'Approved',
                'grand_total' => $p->grand_total,
                'sudah_bayar' => $totalBayar,
                'sisa' => $sisa,
                'status' => $p->status,
            ];
        });
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
                    DatePicker::make('from')->label('Dari Tanggal')->default($this->filter_from)->required(),
                    DatePicker::make('to')->label('Sampai Tanggal')->default($this->filter_to)->required(),
                    Select::make('gudang_id')
                        ->label('Gudang (Opsional)')
                        ->options(fn() => Gudang::pluck('nama_gudang', 'id'))
                        ->placeholder('Semua Gudang')
                        ->searchable()->preload(),
                ])
                ->action(function (array $data) {
                    $this->filter_from = $data['from'];
                    $this->filter_to = $data['to'];
                    $this->filter_gudang_id = $data['gudang_id'] ?? null;
                })
                ->modalSubmitActionLabel('Terapkan')
                ->modalCancelActionLabel('Batal'),

            Action::make('exportPdf')
                ->label('Export PDF Harian')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->visible(fn() => $user?->canExportPdf())
                ->action(function () use ($user) {
                    $pdf = app('dompdf.wrapper');
                    $pdf->loadView('reports.piutang-pdf', [
                        'list' => $this->getListToko(),
                        'from' => $this->filter_from,
                        'to' => $this->filter_to,
                        'generatedBy' => $user->name,
                    ]);
                    $filename = 'Piutang_' . now()->format('Ymd') . '.pdf';
                    return response()->streamDownload(fn() => print($pdf->output()), $filename);
                }),
        ];
    }
}
