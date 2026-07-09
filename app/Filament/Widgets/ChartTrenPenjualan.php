<?php

namespace App\Filament\Widgets;

use App\Models\Biaya;
use App\Models\Pembelian;
use App\Models\Penjualan;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class ChartTrenPenjualan extends ChartWidget
{
    protected ?string $heading = 'Tren Transaksi 6 Bulan';

    protected ?string $description = 'Nilai penjualan, pembelian, dan biaya per bulan.';

    protected ?string $maxHeight = '230px';

    protected int|string|array $columnSpan = 'full';

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
            $months->push(now()->subMonths($i));
        }

        $labels = $months->map(fn ($date) => $date->format('M Y'))->toArray();

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

                    // Modern teal
                    'borderColor' => '#14B8A6',
                    'backgroundColor' => 'rgba(20, 184, 166, 0.14)',

                    'fill' => false,
                    'tension' => 0.45,
                    'borderWidth' => 2,
                    'pointRadius' => 2.5,
                    'pointBackgroundColor' => '#14B8A6',
                    'pointBorderColor' => '#ffffff',
                    'pointBorderWidth' => 1.5,
                    'pointHoverRadius' => 6,
                    'pointHoverBackgroundColor' => '#14B8A6',
                    'pointHoverBorderColor' => '#ffffff',
                    'pointHoverBorderWidth' => 2,
                ],
                [
                    'label' => 'Pembelian',
                    'data' => $pembelianData,

                    // Modern amber
                    'borderColor' => '#F59E0B',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.13)',

                    'fill' => false,
                    'tension' => 0.45,
                    'borderWidth' => 2,
                    'pointRadius' => 2.5,
                    'pointBackgroundColor' => '#F59E0B',
                    'pointBorderColor' => '#ffffff',
                    'pointBorderWidth' => 1.5,
                    'pointHoverRadius' => 6,
                    'pointHoverBackgroundColor' => '#F59E0B',
                    'pointHoverBorderColor' => '#ffffff',
                    'pointHoverBorderWidth' => 2,
                ],
                [
                    'label' => 'Biaya',
                    'data' => $biayaData,

                    // Modern rose
                    'borderColor' => '#F43F5E',
                    'backgroundColor' => 'rgba(244, 63, 94, 0.12)',

                    'fill' => false,
                    'tension' => 0.45,
                    'borderWidth' => 2,
                    'pointRadius' => 2.5,
                    'pointBackgroundColor' => '#F43F5E',
                    'pointBorderColor' => '#ffffff',
                    'pointBorderWidth' => 1.5,
                    'pointHoverRadius' => 6,
                    'pointHoverBackgroundColor' => '#F43F5E',
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
                    position: 'bottom',
                    labels: {
                        boxWidth: 10,
                        boxHeight: 10,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 20,
                        color: '#64748B',
                        font: {
                            size: 12,
                            weight: '600'
                        }
                    }
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
