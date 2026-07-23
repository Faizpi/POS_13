<?php

namespace App\Filament\Widgets;

use App\Models\Penjualan;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class PenjualanStatsWidget extends BaseStatsOverviewWidget
{
    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user?->isSuperAdmin();
        $isAdmin = $user?->isAdmin();
        $gudangId = ($isAdmin && ! $isSuperAdmin) ? $user?->getCurrentGudang()?->id : null;

        $scope = function ($query) use ($isSuperAdmin, $isAdmin, $gudangId, $user) {
            if ($isSuperAdmin) {
                return $query;
            }
            if ($isAdmin && $gudangId) {
                return $query->where('gudang_id', $gudangId);
            }

            return $query->where('user_id', $user->id);
        };

        $now = Carbon::now();

        $cacheKey = 'widget_penjualan_stats:'.$user->id.':'.($gudangId ?? 'all').':'.$now->format('Y-m-d');

        return Cache::remember($cacheKey, 300, function () use ($scope, $now) {
            // Combine sum + count into single queries where possible
            $belumDibayarRow = $scope(
                Penjualan::whereIn('status', ['Pending', 'Approved'])
            )->selectRaw('COALESCE(SUM(grand_total),0) as total')->first();

            $telatBayarRow = $scope(
                Penjualan::where('status', 'Approved')
                    ->whereNotNull('tgl_jatuh_tempo')
                    ->where('tgl_jatuh_tempo', '<', today())
            )->selectRaw('COALESCE(SUM(grand_total),0) as total, COUNT(*) as cnt')->first();

            $pelunasanRow = $scope(
                Penjualan::where('status', 'Lunas')
                    ->where('updated_at', '>=', $now->copy()->subDays(30))
            )->selectRaw('COALESCE(SUM(grand_total),0) as total')->first();

            $totalCanceled = $scope(
                Penjualan::where('status', 'Canceled')
            )->count();

            return [
                Stat::make('Belum Dibayar', format_rupiah($belumDibayarRow->total))
                    ->description('Pending + Approved')
                    ->descriptionIcon('heroicon-o-banknotes')
                    ->color('warning'),

                Stat::make('Telat Bayar', number_format($telatBayarRow->cnt).' invoice')
                    ->description(format_rupiah($telatBayarRow->total))
                    ->descriptionIcon('heroicon-o-exclamation-triangle')
                    ->color($telatBayarRow->cnt > 0 ? 'danger' : 'success'),

                Stat::make('Pelunasan 30 Hari', format_rupiah($pelunasanRow->total))
                    ->description('Lunas dalam 30 hari terakhir')
                    ->descriptionIcon('heroicon-o-check-badge')
                    ->color('success'),

                Stat::make('Total Canceled', number_format($totalCanceled).' transaksi')
                    ->description('Semua periode')
                    ->descriptionIcon('heroicon-o-x-circle')
                    ->color($totalCanceled > 0 ? 'gray' : 'success'),
            ];
        });
    }
}
