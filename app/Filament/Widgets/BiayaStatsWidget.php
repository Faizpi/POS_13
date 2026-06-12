<?php

namespace App\Filament\Widgets;

use App\Models\Biaya;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BiayaStatsWidget extends BaseStatsOverviewWidget
{
    protected function getColumns(): int
    {
        return 4;
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

        $now = Carbon::now();

        // Card 1: Biaya Masuk (Approved) — semua waktu
        $biayaMasuk = $scope(
            Biaya::where('jenis_biaya', 'masuk')
                ->where('status', 'Approved')
        )->sum('grand_total');

        // Card 2: Biaya Keluar (Approved) — semua waktu
        $biayaKeluar = $scope(
            Biaya::where('jenis_biaya', 'keluar')
                ->where('status', 'Approved')
        )->sum('grand_total');

        // Card 3: Total Bulan Ini (semua status, all jenis)
        $totalBulanIni = $scope(
            Biaya::whereYear('tgl_transaksi', $now->year)
                ->whereMonth('tgl_transaksi', $now->month)
        )->sum('grand_total');

        // Card 4: Pending Approval
        $totalPending = $scope(
            Biaya::where('status', 'Pending')
        )->sum('grand_total');

        $bulanLabel = $now->translatedFormat('F Y');

        return [
            Stat::make('↓ Biaya Masuk (Approved)', format_rupiah($biayaMasuk))
                ->description('Semua periode · Approved')
                ->descriptionIcon('heroicon-o-arrow-down-circle')
                ->color('success'),

            Stat::make('↑ Biaya Keluar (Approved)', format_rupiah($biayaKeluar))
                ->description('Semua periode · Approved')
                ->descriptionIcon('heroicon-o-arrow-up-circle')
                ->color('danger'),

            Stat::make('Total Bulan Ini', format_rupiah($totalBulanIni))
                ->description('Semua status · ' . $bulanLabel)
                ->descriptionIcon('heroicon-o-calendar')
                ->color('primary'),

            Stat::make('Pending Approval', format_rupiah($totalPending))
                ->description('Menunggu persetujuan')
                ->descriptionIcon('heroicon-o-clock')
                ->color($totalPending > 0 ? 'warning' : 'success'),
        ];
    }
}
