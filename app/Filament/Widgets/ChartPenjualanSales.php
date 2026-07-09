<?php

namespace App\Filament\Widgets;

use App\Models\Penjualan;
use App\Models\User;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ChartPenjualanSales extends ChartWidget
{
    protected ?string $heading = 'Penjualan per Sales';

    protected static ?int $sort = 4;

    protected ?string $description = 'Top 10 sales berdasarkan nilai penjualan periode terpilih.';

    protected ?string $maxHeight = '230px';

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = 'month';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getFilters(): ?array
    {
        return [
            'today' => 'Hari Ini',
            'week' => 'Minggu Ini',
            'month' => 'Bulan Ini',
            'year' => 'Tahun Ini',
        ];
    }

    protected function getData(): array
    {
        // Determine date range based on filter
        $startDate = match ($this->filter) {
            'today' => today(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };

        $endDate = match ($this->filter) {
            'today' => today(),
            'week' => now()->endOfWeek(),
            'month' => now()->endOfMonth(),
            'year' => now()->endOfYear(),
            default => now()->endOfMonth(),
        };

        $user = auth()->user();
        $gudangId = null;

        if (! $user?->isSuperAdmin()) {
            $gudangId = $user?->current_gudang_id ?: $user?->getCurrentGudang()?->id;

            if (! $gudangId) {
                return $this->emptyDataset();
            }
        }

        $data = User::query()
            ->select('users.id', 'users.name', DB::raw('COALESCE(SUM(penjualans.grand_total), 0) as total'))
            ->where('users.role', 'user')
            ->when($gudangId, fn ($query) => $query->where('users.gudang_id', $gudangId))
            ->leftJoin('penjualans', function ($join) use ($startDate, $endDate, $gudangId): void {
                $join->on('penjualans.user_id', '=', 'users.id')
                    ->whereBetween('penjualans.tgl_transaksi', [$startDate->toDateString(), $endDate->toDateString()]);

                if ($gudangId) {
                    $join->where('penjualans.gudang_id', $gudangId);
                }
            })
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->orderBy('users.name')
            ->limit(10)
            ->get();

        if ($data->isEmpty()) {
            $data = Penjualan::query()
                ->select('user_id', DB::raw('COALESCE(SUM(grand_total), 0) as total'))
                ->with('user:id,name')
                ->whereBetween('tgl_transaksi', [$startDate->toDateString(), $endDate->toDateString()])
                ->when($gudangId, fn ($query) => $query->where('gudang_id', $gudangId))
                ->groupBy('user_id')
                ->orderByDesc('total')
                ->limit(10)
                ->get()
                ->map(fn (Penjualan $item): object => (object) [
                    'name' => $item->user?->name ?? 'Tanpa Sales',
                    'total' => $item->total,
                ]);
        }

        $labels = $data->map(fn ($item) => $item->name ?? 'Tanpa Sales')->toArray();
        $values = $data->map(fn ($item) => (float) $item->total)->toArray();

        // Build gradient-like color array from indigo→violet based on rank
        $count = count($values);
        $colors = [];
        for ($i = 0; $i < $count; $i++) {
            $ratio = $count > 1 ? $i / ($count - 1) : 0;
            $opacity = round(0.95 - $ratio * 0.25, 2);
            $colors[] = $i % 2 === 0
                ? "rgba(99, 102, 241, {$opacity})"
                : "rgba(139, 92, 246, {$opacity})";
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Penjualan',
                    'data' => $values,
                    'backgroundColor' => $colors ?: '#6366f1',
                    'hoverBackgroundColor' => '#818cf8',
                    'borderRadius' => 10,
                    'borderSkipped' => false,
                    'barThickness' => 18,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            indexAxis: 'y',
            animation: { duration: 600, easing: 'easeInOutQuart' },
            layout: { padding: { top: 4, right: 12, bottom: 0, left: 0 } },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.92)',
                    titleColor: '#e2e8f0',
                    bodyColor: '#cbd5e1',
                    borderColor: 'rgba(148,163,184,0.2)',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 10,
                    displayColors: false,
                    callbacks: {
                        label: (context) => `  Rp ${Number(context.parsed.x || 0).toLocaleString('id-ID')}`,
                    },
                },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: {
                        drawTicks: false,
                        color: 'rgba(148,163,184,0.12)',
                    },
                    border: { display: false },
                    ticks: {
                        padding: 10,
                        color: '#94a3b8',
                        font: { size: 11 },
                        callback: (value) => `Rp ${Number(value).toLocaleString('id-ID', { notation: 'compact', maximumFractionDigits: 1 })}`,
                    },
                },
                y: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                        autoSkip: false,
                        color: '#475569',
                        font: { size: 11, weight: '600' },
                    },
                },
            },
        }
        JS);
    }

    private function emptyDataset(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Total Penjualan',
                    'data' => [],
                    'backgroundColor' => '#6366f1',
                    'borderRadius' => 10,
                    'borderSkipped' => false,
                    'barThickness' => 18,
                ],
            ],
            'labels' => [],
        ];
    }
}
