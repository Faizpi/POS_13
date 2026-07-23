<?php

namespace App\Filament\Widgets;

use App\Models\Biaya;
use App\Models\Pembelian;
use App\Models\Penjualan;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class StatsBulanIni extends BaseStatsOverviewWidget
{
    protected ?string $heading = 'Performa Bulan Ini';

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

        $now = Carbon::now();
        $bulanLabel = $now->translatedFormat('F Y');

        $cacheKey = 'widget_stats_bulan_ini:'.$user->id.':'.($gudangId ?? $userId ?? 'all').':'.$now->format('Y-m');

        return Cache::remember($cacheKey, 300, function () use ($scope, $now, $bulanLabel) {
            $bulanQuery = fn ($q) => $q->whereYear('tgl_transaksi', $now->year)->whereMonth('tgl_transaksi', $now->month);

            // Single query per stat with sum + count
            $penjualanRow = $scope($bulanQuery(Penjualan::query()))
                ->selectRaw('COALESCE(SUM(grand_total),0) as total, COUNT(*) as cnt')->first();
            $pembelianRow = $scope($bulanQuery(Pembelian::query()))
                ->selectRaw('COALESCE(SUM(grand_total),0) as total, COUNT(*) as cnt')->first();
            $biayaMasukRow = $scope($bulanQuery(Biaya::query())->where('jenis_biaya', 'masuk')->where('status', 'Approved'))
                ->selectRaw('COALESCE(SUM(grand_total),0) as total, COUNT(*) as cnt')->first();
            $biayaKeluarRow = $scope($bulanQuery(Biaya::query())->where('jenis_biaya', 'keluar')->where('status', 'Approved'))
                ->selectRaw('COALESCE(SUM(grand_total),0) as total, COUNT(*) as cnt')->first();

            return [
                Stat::make('Penjualan', format_rupiah($penjualanRow->total))
                    ->description(number_format($penjualanRow->cnt).' transaksi · '.$bulanLabel)
                    ->descriptionIcon('heroicon-o-arrow-trending-up')
                    ->color('primary'),

                Stat::make('Pembelian', format_rupiah($pembelianRow->total))
                    ->description(number_format($pembelianRow->cnt).' transaksi · '.$bulanLabel)
                    ->descriptionIcon('heroicon-o-arrow-trending-down')
                    ->color('warning'),

                Stat::make('Biaya Masuk', format_rupiah($biayaMasukRow->total))
                    ->description(number_format($biayaMasukRow->cnt).' transaksi · '.$bulanLabel)
                    ->descriptionIcon('heroicon-o-arrow-down-circle')
                    ->color('success'),

                Stat::make('Biaya Keluar', format_rupiah($biayaKeluarRow->total))
                    ->description(number_format($biayaKeluarRow->cnt).' transaksi · '.$bulanLabel)
                    ->descriptionIcon('heroicon-o-arrow-up-circle')
                    ->color('danger'),
            ];
        });
    }
}
