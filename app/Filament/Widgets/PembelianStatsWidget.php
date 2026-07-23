<?php

namespace App\Filament\Widgets;

use App\Models\Pembelian;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PembelianStatsWidget extends BaseStatsOverviewWidget
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

        // Card 1: Pending Approval (nominal)
        $fakturPending = $scope(
            Pembelian::where('status', 'Pending')
        )->sum('grand_total');

        // Card 2: Total Aktif (Pending + Approved nominal)
        $fakturBelumDibayar = $scope(
            Pembelian::whereIn('status', ['Pending', 'Approved'])
        )->sum('grand_total');

        // Card 3: Jatuh Tempo Lewat (Approved + tgl_jatuh_tempo sudah lewat, nominal)
        $fakturTelatBayar = $scope(
            Pembelian::where('status', 'Approved')
                ->whereNotNull('tgl_jatuh_tempo')
                ->where('tgl_jatuh_tempo', '<', today())
        )->sum('grand_total');

        // Card 4: Canceled (count)
        $fakturCanceled = $scope(
            Pembelian::where('status', 'Canceled')
        )->count();

        return [
            Stat::make('Pending Approval', format_rupiah($fakturPending))
                ->description('Menunggu persetujuan')
                ->descriptionIcon('heroicon-o-clock')
                ->color($fakturPending > 0 ? 'gray' : 'primary'),

            Stat::make('Total (Pending/Approved)', format_rupiah($fakturBelumDibayar))
                ->description('Belum dilunasi')
                ->descriptionIcon('heroicon-o-document-text')
                ->color('primary'),

            Stat::make('Jatuh Tempo Lewat', format_rupiah($fakturTelatBayar))
                ->description('Approved & telat bayar')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($fakturTelatBayar > 0 ? 'danger' : 'primary'),

            Stat::make('Dibatalkan (Canceled)', number_format($fakturCanceled).' Transaksi')
                ->description('Semua periode')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color('gray'),
        ];
    }
}
