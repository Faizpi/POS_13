<?php

namespace App\Filament\Widgets;

use App\Models\Biaya;
use App\Models\Pembayaran;
use App\Models\Penjualan;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class StatsHariIni extends BaseStatsOverviewWidget
{
    protected ?string $heading = 'Aktivitas Hari Ini';

    protected function getColumns(): int|array
    {
        return 4;
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user?->isSuperAdmin();
        $isAdmin = $user?->isAdmin();
        $isUser = ! $isSuperAdmin && ! $isAdmin;

        $gudangId = $isAdmin ? $user?->getCurrentGudang()?->id : null;
        $userId = $isUser ? $user->id : null;

        $scope = function ($query, ?string $gudangColumn = 'gudang_id', ?string $userColumn = 'user_id') use ($isSuperAdmin, $isAdmin, $gudangId, $userId) {
            if ($isSuperAdmin) {
                return $query;
            }
            if ($isAdmin && $gudangId && $gudangColumn) {
                return $query->where($gudangColumn, $gudangId);
            }
            if ($userId && $userColumn) {
                return $query->where($userColumn, $userId);
            }

            return $query->whereRaw('1=0');
        };

        $cacheKey = 'widget_stats_hari_ini:'.$user->id.':'.($gudangId ?? $userId ?? 'all').':'.today()->format('Y-m-d');

        return Cache::remember($cacheKey, 300, function () use ($scope) {
            // Queries — use selectRaw to get sum + count in single query
            $penjualanRow = $scope(Penjualan::whereDate('tgl_transaksi', today()))
                ->selectRaw('COALESCE(SUM(grand_total),0) as total, COUNT(*) as cnt')->first();
            $pembayaranRow = $scope(Pembayaran::whereDate('tgl_pembayaran', today()))
                ->selectRaw('COALESCE(SUM(jumlah_bayar),0) as total, COUNT(*) as cnt')->first();
            $biayaMasukRow = $scope(Biaya::whereDate('tgl_transaksi', today())->where('jenis_biaya', 'masuk'))
                ->selectRaw('COALESCE(SUM(grand_total),0) as total, COUNT(*) as cnt')->first();
            $biayaKeluarRow = $scope(Biaya::whereDate('tgl_transaksi', today())->where('jenis_biaya', 'keluar'))
                ->selectRaw('COALESCE(SUM(grand_total),0) as total, COUNT(*) as cnt')->first();

            return [
                Stat::make('Penjualan', format_rupiah($penjualanRow->total))
                    ->description(number_format($penjualanRow->cnt).' transaksi hari ini')
                    ->descriptionIcon('heroicon-o-shopping-cart')
                    ->color('success'),

                Stat::make('Pembayaran Diterima', format_rupiah($pembayaranRow->total))
                    ->description(number_format($pembayaranRow->cnt).' transaksi hari ini')
                    ->descriptionIcon('heroicon-o-banknotes')
                    ->color('info'),

                Stat::make('Biaya Masuk', format_rupiah($biayaMasukRow->total))
                    ->description(number_format($biayaMasukRow->cnt).' transaksi hari ini')
                    ->descriptionIcon('heroicon-o-arrow-down-circle')
                    ->color('success'),

                Stat::make('Biaya Keluar', format_rupiah($biayaKeluarRow->total))
                    ->description(number_format($biayaKeluarRow->cnt).' transaksi hari ini')
                    ->descriptionIcon('heroicon-o-arrow-up-circle')
                    ->color('danger'),
            ];
        });
    }
}
