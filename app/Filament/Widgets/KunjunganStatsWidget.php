<?php

namespace App\Filament\Widgets;

use App\Models\Kunjungan;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class KunjunganStatsWidget extends BaseStatsOverviewWidget
{
    protected function getColumns(): int
    {
        return 5;
    }

    protected function getStats(): array
    {
        $user         = auth()->user();
        $isSuperAdmin = $user?->isSuperAdmin();
        $isAdmin      = $user?->isAdmin();
        $gudangId     = ($isAdmin && !$isSuperAdmin) ? $user?->getCurrentGudang()?->id : null;

        $scope = function ($query) use ($isSuperAdmin, $isAdmin, $gudangId, $user) {
            if ($isSuperAdmin) return $query;
            if ($isAdmin && $gudangId) return $query->where('gudang_id', $gudangId);
            return $query->where('user_id', $user->id);
        };

        $activeStatuses = ['Pending', 'Approved'];

        $totalPemeriksaanStock = $scope(
            Kunjungan::where('tujuan', 'Pemeriksaan Stock')->whereIn('status', $activeStatuses)
        )->count();

        $totalPenagihan = $scope(
            Kunjungan::where('tujuan', 'Penagihan')->whereIn('status', $activeStatuses)
        )->count();

        $totalPromoGratis = $scope(
            Kunjungan::where('tujuan', 'Promo Gratis')->whereIn('status', $activeStatuses)
        )->count();

        $totalPromoSample = $scope(
            Kunjungan::where('tujuan', 'Promo Sample')->whereIn('status', $activeStatuses)
        )->count();

        $totalCanceled = $scope(
            Kunjungan::where('status', 'Canceled')
        )->count();

        return [
            Stat::make('Pemeriksaan Stock', number_format($totalPemeriksaanStock) . ' kunjungan')
                ->description('Pending + Approved')
                ->descriptionIcon('heroicon-o-clipboard-document-check')
                ->color('info'),

            Stat::make('Penagihan', number_format($totalPenagihan) . ' kunjungan')
                ->description('Pending + Approved')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color('warning'),

            Stat::make('Promo Gratis', number_format($totalPromoGratis) . ' kunjungan')
                ->description('Pending + Approved')
                ->descriptionIcon('heroicon-o-gift')
                ->color('success'),

            Stat::make('Promo Sample', number_format($totalPromoSample) . ' kunjungan')
                ->description('Pending + Approved')
                ->descriptionIcon('heroicon-o-beaker')
                ->color('primary'),

            Stat::make('Total Canceled', number_format($totalCanceled) . ' kunjungan')
                ->description('Semua periode')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color($totalCanceled > 0 ? 'gray' : 'success'),
        ];
    }
}
