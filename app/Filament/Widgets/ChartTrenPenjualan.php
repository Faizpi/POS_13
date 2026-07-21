<?php

namespace App\Filament\Widgets;

use App\Models\Biaya;
use App\Models\Pembelian;
use App\Models\Penjualan;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

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

        // Build month labels for last 6 months
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push(now()->subMonthsNoOverflow($i));
        }

        $labels = $months->map(fn ($date) => $date->translatedFormat('M Y'))->toArray();

        $penjualanData = [];
        $pembelianData = [];
        $biayaData = [];

        foreach ($months as $month) {
            $penjualanQuery = Penjualan::whereYear('tgl_transaksi', $month->year)
                ->whereMonth('tgl_transaksi', $month->month);

            $pembelianQuery = Pembelian::whereYear('tgl_transaksi', $month->year)
                ->whereMonth('tgl_transaksi', $month->month);

            $biayaQuery = Biaya::whereYear('tgl_transaksi', $month->year)
                ->whereMonth('tgl_transaksi', $month->month);

            // Apply role-based filtering
            if (! $isSuperAdmin) {
                $gudangId = null;

                if ($user?->current_gudang_id) {
                    $gudangId = $user->current_gudang_id;
                } elseif ($user?->role === 'admin' || $user?->role === 'spectator') {
                    $fallbackGudang = $user?->getCurrentGudang();
                    $gudangId = $fallbackGudang ? $fallbackGudang->id : null;
                }

                if ($gudangId) {
                    $penjualanQuery->where('gudang_id', $gudangId);
                    $pembelianQuery->where('gudang_id', $gudangId);
                    $biayaQuery->where('gudang_id', $gudangId);
                } else {
                    $penjualanData[] = 0;
                    $pembelianData[] = 0;
                    $biayaData[] = 0;

                    continue;
                }
            }

            $penjualanData[] = (float) $penjualanQuery->sum('grand_total');
            $pembelianData[] = (float) $pembelianQuery->sum('grand_total');
            $biayaData[] = (float) $biayaQuery->sum('grand_total');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Penjualan',
                    'data' => $penjualanData,

                    // Teal – Penjualan
                    'borderColor' => '#0F9F8F',

                    'fill' => 'origin',
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
                    'data' => $pembelianData,

                    // Amber – Pembelian
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
                    'data' => $biayaData,

                    // Rose – Biaya
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
            'labels' => $labels,
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
            datasets: {
                line: {
                    backgroundColor: (context) => {
                        const { chart, datasetIndex } = context;
                        const { ctx, chartArea } = chart;

                        // Only the primary series (Penjualan, index 0) gets a gradient fill.
                        // Pembelian and Biaya are line-only (fill=false), so return transparent.
                        if (datasetIndex !== 0) {
                            return 'transparent';
                        }

                        if (! chartArea) {
                            return 'rgba(15, 159, 143, 0.22)';
                        }

                        const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                        gradient.addColorStop(0, 'rgba(15, 159, 143, 0.22)');
                        gradient.addColorStop(0.45, 'rgba(15, 159, 143, 0.08)');
                        gradient.addColorStop(1, 'rgba(15, 159, 143, 0)');

                        return gradient;
                    }
                }
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
