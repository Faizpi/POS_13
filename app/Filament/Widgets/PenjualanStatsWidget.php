<?php

namespace App\Filament\Widgets;

use App\Models\Penjualan;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

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

        // Total Belum Dibayar (Pending + Approved)
        $totalBelumDibayar = $scope(
            Penjualan::whereIn('status', ['Pending', 'Approved'])
        )->sum('grand_total');

        // Total Telat Bayar (Approved + tgl_jatuh_tempo sudah lewat)
        $totalTelatBayar = $scope(
            Penjualan::where('status', 'Approved')
                ->whereNotNull('tgl_jatuh_tempo')
                ->where('tgl_jatuh_tempo', '<', today())
        )->count();

        $nominalTelatBayar = $scope(
            Penjualan::where('status', 'Approved')
                ->whereNotNull('tgl_jatuh_tempo')
                ->where('tgl_jatuh_tempo', '<', today())
        )->sum('grand_total');

        // Pelunasan 30 hari terakhir (Lunas)
        $pelunasan30Hari = $scope(
            Penjualan::where('status', 'Lunas')
                ->where('updated_at', '>=', $now->copy()->subDays(30))
        )->sum('grand_total');

        // Total Canceled
        $totalCanceled = $scope(
            Penjualan::where('status', 'Canceled')
        )->count();

        return [
            Stat::make('Belum Dibayar', format_rupiah($totalBelumDibayar))
                ->description('Pending + Approved')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('warning'),

            Stat::make('Telat Bayar', number_format($totalTelatBayar).' invoice')
                ->description(format_rupiah($nominalTelatBayar))
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($totalTelatBayar > 0 ? 'danger' : 'success'),

            Stat::make('Pelunasan 30 Hari', format_rupiah($pelunasan30Hari))
                ->description('Lunas dalam 30 hari terakhir')
                ->descriptionIcon('heroicon-o-check-badge')
                ->color('success'),

            Stat::make('Total Canceled', number_format($totalCanceled).' transaksi')
                ->description('Semua periode')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color($totalCanceled > 0 ? 'gray' : 'success'),
        ];
    }
}
