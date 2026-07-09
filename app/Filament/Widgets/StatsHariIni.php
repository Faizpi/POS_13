<?php

namespace App\Filament\Widgets;

use App\Models\Biaya;
use App\Models\Pembayaran;
use App\Models\Penjualan;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

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

        // Queries
        $penjualan = $scope(Penjualan::whereDate('tgl_transaksi', today()));
        $pembayaran = $scope(Pembayaran::whereDate('tgl_pembayaran', today()));
        $biayaMasuk = $scope(Biaya::whereDate('tgl_transaksi', today())->where('jenis_biaya', 'masuk'));
        $biayaKeluar = $scope(Biaya::whereDate('tgl_transaksi', today())->where('jenis_biaya', 'keluar'));

        $lastSevenDays = collect(range(6, 0))->map(fn ($day) => today()->subDays($day));
        $dailySum = function ($queryBuilder) use ($lastSevenDays, $scope): array {
            return $lastSevenDays->map(function ($date) use ($queryBuilder, $scope) {
                // Clone the query builder so we don't modify the original
                $q = clone $queryBuilder;

                return (float) $scope($q->whereDate($q->getModel() instanceof Biaya ? 'tgl_transaksi' : ($q->getModel() instanceof Pembayaran ? 'tgl_pembayaran' : 'tgl_transaksi'), $date))->sum($q->getModel() instanceof Pembayaran ? 'jumlah_bayar' : 'grand_total');
            })->all();
        };

        return [
            Stat::make('Penjualan', format_rupiah($penjualan->sum('grand_total')))
                ->description(number_format($penjualan->count()).' transaksi hari ini')
                ->descriptionIcon('heroicon-o-shopping-cart')
                ->color('success')
                ->chart($dailySum(new Penjualan)),

            Stat::make('Pembayaran Diterima', format_rupiah($pembayaran->sum('jumlah_bayar')))
                ->description(number_format($pembayaran->count()).' transaksi hari ini')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('info')
                ->chart($dailySum(new Pembayaran)),

            Stat::make('Biaya Masuk', format_rupiah($biayaMasuk->sum('grand_total')))
                ->description(number_format($biayaMasuk->count()).' transaksi hari ini')
                ->descriptionIcon('heroicon-o-arrow-down-circle')
                ->color('success')
                ->chart($dailySum(Biaya::where('jenis_biaya', 'masuk'))),

            Stat::make('Biaya Keluar', format_rupiah($biayaKeluar->sum('grand_total')))
                ->description(number_format($biayaKeluar->count()).' transaksi hari ini')
                ->descriptionIcon('heroicon-o-arrow-up-circle')
                ->color('danger')
                ->chart($dailySum(Biaya::where('jenis_biaya', 'keluar'))),
        ];
    }
}
