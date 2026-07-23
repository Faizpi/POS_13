<?php

namespace App\Filament\Widgets;

use App\Models\Gudang;
use App\Models\Pembelian;
use App\Models\Penjualan;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class ChartTransaksiGudang extends ChartWidget
{
    protected ?string $heading = 'Transaksi per Gudang';

    protected static ?int $sort = 3;

    protected ?string $description = 'Jumlah penjualan dan pembelian per gudang.';

    protected ?string $maxHeight = '200px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $user = auth()->user();

        $cacheKey = 'widget_chart_gudang:'.$user->id.':'.$user->role.':'.now()->format('Y-m-d');

        $data = Cache::remember($cacheKey, 300, function () use ($user) {
            if ($user?->isSuperAdmin()) {
                $gudangs = Gudang::pluck('nama_gudang', 'id');
            } elseif ($user?->isAdmin()) {
                $gudangs = collect($user->gudangs()->pluck('nama_gudang', 'gudangs.id')->toArray());
                if ($user->gudang_id && ! $gudangs->has($user->gudang_id)) {
                    $mainGudang = Gudang::find($user->gudang_id);
                    if ($mainGudang) {
                        $gudangs->put($mainGudang->id, $mainGudang->nama_gudang);
                    }
                }
            } elseif ($user?->isSpectator()) {
                $gudangs = collect($user->spectatorGudangs()->pluck('nama_gudang', 'gudangs.id')->toArray());
            } else {
                $gudangs = collect();
            }

            $gudangIds = $gudangs->keys()->toArray();

            // Single GROUP BY query per model instead of N×2 queries
            $penjualanMap = Penjualan::whereIn('gudang_id', $gudangIds)
                ->selectRaw('gudang_id, COUNT(*) as cnt')
                ->groupBy('gudang_id')
                ->pluck('cnt', 'gudang_id')
                ->toArray();

            $pembelianMap = Pembelian::whereIn('gudang_id', $gudangIds)
                ->selectRaw('gudang_id, COUNT(*) as cnt')
                ->groupBy('gudang_id')
                ->pluck('cnt', 'gudang_id')
                ->toArray();

            $penjualanData = [];
            $pembelianData = [];

            foreach ($gudangIds as $id) {
                $penjualanData[] = (int) ($penjualanMap[$id] ?? 0);
                $pembelianData[] = (int) ($pembelianMap[$id] ?? 0);
            }

            return [
                'labels' => $gudangs->values()->toArray(),
                'penjualanData' => $penjualanData,
                'pembelianData' => $pembelianData,
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Penjualan',
                    'data' => $data['penjualanData'],
                    'backgroundColor' => 'rgba(15, 159, 143, 0.85)',
                    'hoverBackgroundColor' => '#0D8A7C',
                    'borderColor' => '#0F9F8F',
                    'borderWidth' => 0,
                    'borderRadius' => 10,
                    'borderSkipped' => false,
                    'barThickness' => 16,
                ],
                [
                    'label' => 'Pembelian',
                    'data' => $data['pembelianData'],
                    'backgroundColor' => 'rgba(217, 139, 22, 0.85)',
                    'hoverBackgroundColor' => '#C07A10',
                    'borderColor' => '#D98B16',
                    'borderWidth' => 0,
                    'borderRadius' => 10,
                    'borderSkipped' => false,
                    'barThickness' => 16,
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            animation: { duration: 600, easing: 'easeInOutQuart' },
            layout: { padding: { top: 8, right: 12, bottom: 0, left: 0 } },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 10,
                        boxHeight: 10,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 20,
                        font: { size: 12, weight: '600' },
                    },
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.92)',
                    titleColor: '#e2e8f0',
                    bodyColor: '#cbd5e1',
                    borderColor: 'rgba(148,163,184,0.2)',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 10,
                    displayColors: true,
                    boxWidth: 8,
                    boxHeight: 8,
                    usePointStyle: true,
                    callbacks: {
                        label: (context) => `  ${context.dataset.label}: ${Number(context.parsed.y || 0).toLocaleString('id-ID')} transaksi`,
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                        maxRotation: 0,
                        color: '#94a3b8',
                        font: { size: 10 },
                    },
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        drawTicks: false,
                        color: 'rgba(148,163,184,0.12)',
                    },
                    border: { display: false },
                    ticks: {
                        precision: 0,
                        padding: 10,
                        color: '#94a3b8',
                        font: { size: 11 },
                    },
                },
            },
        }
        JS);
    }
}
