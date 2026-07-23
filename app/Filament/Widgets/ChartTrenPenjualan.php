<?php

namespace App\Filament\Widgets;

use App\Models\Biaya;
use App\Models\Pembelian;
use App\Models\Penjualan;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ChartTrenPenjualan extends ChartWidget
{
    protected string $view = 'filament.widgets.chart-tren-penjualan';

    protected ?string $maxHeight = '220px';

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user?->isSuperAdmin();

        $gudangId = null;
        if (! $isSuperAdmin) {
            if ($user?->current_gudang_id) {
                $gudangId = $user->current_gudang_id;
            } elseif ($user?->role === 'admin' || $user?->role === 'spectator') {
                $fallbackGudang = $user?->getCurrentGudang();
                $gudangId = $fallbackGudang ? $fallbackGudang->id : null;
            }
        }

        $cacheKey = 'widget_chart_tren:'.$user->id.':'.($gudangId ?? 'all').':'.now()->format('Y-m-d');

        $data = Cache::remember($cacheKey, 300, function () use ($gudangId) {
            // Build month labels for last 6 months
            $months = collect();
            for ($i = 5; $i >= 0; $i--) {
                $months->push(now()->subMonthsNoOverflow($i));
            }

            $labels = $months->map(fn ($date) => $date->translatedFormat('M Y'))->toArray();
            $startDate = $months->first()->copy()->startOfMonth();

            $queryModifier = function ($query) use ($gudangId, $startDate) {
                $query->whereDate('tgl_transaksi', '>=', $startDate)
                    ->selectRaw('YEAR(tgl_transaksi) as y, MONTH(tgl_transaksi) as m, SUM(grand_total) as total')
                    ->groupBy('y', 'm');
                if ($gudangId) {
                    $query->where('gudang_id', $gudangId);
                }

                return $query;
            };

            $penjualanMap = $queryModifier(Penjualan::query())->pluck('total', 'm')->toArray();
            $pembelianMap = $queryModifier(Pembelian::query())->pluck('total', 'm')->toArray();
            $biayaMap = $queryModifier(Biaya::query())->pluck('total', 'm')->toArray();

            $penjualanData = [];
            $pembelianData = [];
            $biayaData = [];

            foreach ($months as $month) {
                $penjualanData[] = (float) ($penjualanMap[$month->month] ?? 0);
                $pembelianData[] = (float) ($pembelianMap[$month->month] ?? 0);
                $biayaData[] = (float) ($biayaMap[$month->month] ?? 0);
            }

            return [
                'labels' => $labels,
                'penjualanData' => $penjualanData,
                'pembelianData' => $pembelianData,
                'biayaData' => $biayaData,
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Penjualan',
                    'data' => $data['penjualanData'],
                    'borderColor' => '#0F9F8F',
                    'fill' => false,
                    'tension' => 0.4,
                    'borderWidth' => 2.5,
                    'pointRadius' => 2.5,
                    'pointBackgroundColor' => '#0F9F8F',
                    'pointBorderColor' => '#ffffff',
                    'pointBorderWidth' => 1.5,
                    'pointHoverRadius' => 6,
                    'pointHoverBackgroundColor' => '#0D8A7C',
                    'pointHoverBorderColor' => '#ffffff',
                    'pointHoverBorderWidth' => 2,
                ],
                [
                    'label' => 'Pembelian',
                    'data' => $data['pembelianData'],
                    'borderColor' => '#D98B16',
                    'fill' => false,
                    'tension' => 0.4,
                    'borderWidth' => 2,
                    'pointRadius' => 2,
                    'pointBackgroundColor' => '#D98B16',
                    'pointBorderColor' => '#ffffff',
                    'pointBorderWidth' => 1.5,
                    'pointHoverRadius' => 5,
                    'pointHoverBackgroundColor' => '#C07A10',
                    'pointHoverBorderColor' => '#ffffff',
                    'pointHoverBorderWidth' => 2,
                ],
                [
                    'label' => 'Biaya',
                    'data' => $data['biayaData'],
                    'borderColor' => '#E54865',
                    'fill' => false,
                    'tension' => 0.4,
                    'borderWidth' => 2.25,
                    'pointRadius' => 2,
                    'pointBackgroundColor' => '#E54865',
                    'pointBorderColor' => '#ffffff',
                    'pointBorderWidth' => 1.5,
                    'pointHoverRadius' => 5,
                    'pointHoverBackgroundColor' => '#D03D58',
                    'pointHoverBorderColor' => '#ffffff',
                    'pointHoverBorderWidth' => 2,
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            animation: {
                duration: 600,
                easing: 'easeInOutQuart'
            },
            interaction: {
                intersect: false,
                mode: 'index'
            },
            layout: {
                padding: {
                    top: 8,
                    right: 12,
                    bottom: 0,
                    left: 0
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.94)',
                    titleColor: '#F8FAFC',
                    bodyColor: '#CBD5E1',
                    borderColor: 'rgba(148, 163, 184, 0.2)',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 10,
                    displayColors: true,
                    boxWidth: 8,
                    boxHeight: 8,
                    usePointStyle: true,
                    callbacks: {
                        label: (context) => {
                            return `  ${context.dataset.label}: Rp ${Number(context.parsed.y || 0).toLocaleString('id-ID')}`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    border: {
                        display: false
                    },
                    ticks: {
                        maxRotation: 0,
                        autoSkip: true,
                        color: '#94A3B8',
                        font: {
                            size: 11
                        },
                        callback: function (value) {
                            return String(this.getLabelForValue(value)).split(' ')[0];
                        }
                    },
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        drawTicks: false,
                        color: 'rgba(148, 163, 184, 0.10)'
                    },
                    border: {
                        display: false
                    },
                    ticks: {
                        padding: 10,
                        color: '#94A3B8',
                        font: {
                            size: 11
                        },
                        callback: (value) => {
                            return `Rp ${Number(value).toLocaleString('id-ID', {
                                notation: 'compact',
                                maximumFractionDigits: 1
                            })}`;
                        },
                    },
                },
            },
        }
        JS);
    }
}
