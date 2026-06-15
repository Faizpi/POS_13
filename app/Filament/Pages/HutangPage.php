<?php

namespace App\Filament\Pages;

use App\Models\Gudang;
use App\Models\Pembelian;
use App\Models\Pembayaran;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use BackedEnum;
use UnitEnum;

class HutangPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Hutang';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Dashboard Hutang';

    protected static ?string $title = 'Dashboard Hutang';

    protected string $view = 'filament.pages.hutang';

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
        return in_array($user?->role, ['spectator', 'super_admin']);
    }

    public function getChartData(): array
    {
        $query = Pembelian::select(
            DB::raw("DATE_FORMAT(tgl_jatuh_tempo, '%Y-%m') as bulan"),
            DB::raw('SUM(grand_total) as total'),
            DB::raw('COUNT(*) as jumlah')
        )
        ->whereNotNull('tgl_jatuh_tempo')
        ->whereIn('status', ['Approved', 'Lunas']);

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

    public function getListTempo(): \Illuminate\Support\Collection
    {
        $query = Pembelian::with(['gudang', 'kontak'])
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
                'supplier' => $p->kontak?->nama ?? '—',
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
                    $pdf->loadView('reports.hutang-pdf', [
                        'list' => $this->getListTempo(),
                        'from' => $this->filter_from,
                        'to' => $this->filter_to,
                        'generatedBy' => $user->name,
                    ]);
                    $filename = 'Hutang_' . now()->format('Ymd') . '.pdf';
                    return response()->streamDownload(fn() => print($pdf->output()), $filename);
                }),
        ];
    }
}
