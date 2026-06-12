<?php

namespace App\Filament\Widgets;

use App\Models\Biaya;
use App\Models\Pembelian;
use App\Models\Penjualan;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

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
            if ($isSuperAdmin) return $query;
            if ($isAdmin && $gudangId && $gudangColumn) return $query->where($gudangColumn, $gudangId);
            if ($userId && $userColumn) return $query->where($userColumn, $userId);
            return $query->whereRaw('1=0');
        };

        $now = Carbon::now();
        $bulanLabel = $now->translatedFormat('F Y');

        $bulanQuery = fn($q) => $q->whereYear('tgl_transaksi', $now->year)->whereMonth('tgl_transaksi', $now->month);

        $penjualan = $scope($bulanQuery(Penjualan::query()));
        $pembelian = $scope($bulanQuery(Pembelian::query()));
        $biayaMasuk = $scope($bulanQuery(Biaya::query())->where('jenis_biaya', 'masuk')->where('status', 'Approved'));
        $biayaKeluar = $scope($bulanQuery(Biaya::query())->where('jenis_biaya', 'keluar')->where('status', 'Approved'));

        $lastSixMonths = collect(range(5, 0))->map(fn ($month) => $now->copy()->subMonths($month));
        $monthlySum = function ($queryBuilder) use ($lastSixMonths, $scope): array {
            return $lastSixMonths->map(function ($date) use ($queryBuilder, $scope) {
                $q = clone $queryBuilder;
                return (float) $scope($q->whereYear('tgl_transaksi', $date->year)->whereMonth('tgl_transaksi', $date->month))->sum('grand_total');
            })->all();
        };

        return [
            Stat::make('Penjualan', format_rupiah($penjualan->sum('grand_total')))
                ->description(number_format($penjualan->count()) . ' transaksi · ' . $bulanLabel)
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color('primary')
                ->chart($monthlySum(new Penjualan)),

            Stat::make('Pembelian', format_rupiah($pembelian->sum('grand_total')))
                ->description(number_format($pembelian->count()) . ' transaksi · ' . $bulanLabel)
                ->descriptionIcon('heroicon-o-arrow-trending-down')
                ->color('warning')
                ->chart($monthlySum(new Pembelian)),

            Stat::make('Biaya Masuk', format_rupiah($biayaMasuk->sum('grand_total')))
                ->description(number_format($biayaMasuk->count()) . ' transaksi · ' . $bulanLabel)
                ->descriptionIcon('heroicon-o-arrow-down-circle')
                ->color('success')
                ->chart($monthlySum(Biaya::where('jenis_biaya', 'masuk')->where('status', 'Approved'))),

            Stat::make('Biaya Keluar', format_rupiah($biayaKeluar->sum('grand_total')))
                ->description(number_format($biayaKeluar->count()) . ' transaksi · ' . $bulanLabel)
                ->descriptionIcon('heroicon-o-arrow-up-circle')
                ->color('danger')
                ->chart($monthlySum(Biaya::where('jenis_biaya', 'keluar')->where('status', 'Approved'))),
        ];
    }
}
