<?php

namespace App\Filament\Widgets;

use App\Models\Biaya;
use App\Models\Kunjungan;
use App\Models\Pembayaran;
use App\Models\Pembelian;
use App\Models\Penjualan;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class ChartKomposisiStatus extends ChartWidget
{
    protected ?string $heading = 'Komposisi Status Transaksi';

    protected ?string $description = 'Distribusi status dari seluruh modul transaksi.';

    protected ?string $maxHeight = '200px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user?->isSuperAdmin();
        $gudangId = null;

        if ($user && ! $isSuperAdmin) {
            $gudangId = $user?->getCurrentGudang()?->id;
        }

        // Closure to apply role-based scoping
        $scoped = function ($query) use ($isSuperAdmin, $gudangId) {
            if ($isSuperAdmin) {
                return $query;
            }
            if ($gudangId) {
                return $query->where('gudang_id', $gudangId);
            }

            return $query->whereRaw('1=0');
        };

        $statuses = [
            'Pending' => 0,
            'Approved' => 0,
            'Canceled' => 0,
        ];

        foreach ($statuses as $status => $count) {
            $statuses[$status] += (int) $scoped(Penjualan::where('status', $status))->count();
            $statuses[$status] += (int) $scoped(Pembelian::where('status', $status))->count();
            $statuses[$status] += (int) $scoped(Biaya::where('status', $status))->count();
            $statuses[$status] += (int) $scoped(Kunjungan::where('status', $status))->count();
            $statuses[$status] += (int) $scoped(Pembayaran::where('status', $status))->count();
        }

        return [
            'datasets' => [
                [
                    'data' => array_values($statuses),
                    'backgroundColor' => [
                        '#f59e0b',   // Pending  – amber
                        '#6366f1',   // Approved – indigo-violet
                        '#f43f5e',   // Canceled – rose
                    ],
                    'hoverBackgroundColor' => [
                        '#fbbf24',
                        '#818cf8',
                        '#fb7185',
                    ],
                    'borderWidth' => 0,
                    'hoverOffset' => 12,
                    'spacing' => 3,
                    'borderRadius' => 6,
                ],
            ],
            'labels' => array_keys($statuses),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            animation: { duration: 700, easing: 'easeInOutQuart' },
            cutout: '70%',
            layout: { padding: 12 },
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
                        color: '#64748b',
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
                        label: (context) => {
                            const label = context.label || '';
                            const value = Number(context.parsed || 0).toLocaleString('id-ID');
                            return `  ${label}: ${value} transaksi`;
                        },
                    },
                },
            },
        }
        JS);
    }
}
