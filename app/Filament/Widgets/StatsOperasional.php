<?php

namespace App\Filament\Widgets;

use App\Models\Biaya;
use App\Models\GudangProduk;
use App\Models\Kunjungan;
use App\Models\Pembayaran;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\Produk;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOperasional extends BaseStatsOverviewWidget
{
    protected ?string $heading = 'Informasi Operasional';

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
        $bulanQuery = fn ($q) => $q->whereYear('tgl_transaksi', $now->year)->whereMonth('tgl_transaksi', $now->month);

        // 1. Menunggu Approval (Pending)
        $pendingBreakdown = $isUser ? [
            Penjualan::where('user_id', $userId)->where('status', 'Pending')->count(),
            Pembelian::where('user_id', $userId)->where('status', 'Pending')->count(),
            Biaya::where('user_id', $userId)->where('status', 'Pending')->count(),
            Kunjungan::where('user_id', $userId)->where('status', 'Pending')->count(),
            Pembayaran::where('user_id', $userId)->where('status', 'Pending')->count(),
        ] : [
            $scope(Penjualan::where('status', 'Pending'))->count(),
            $scope(Pembelian::where('status', 'Pending'))->count(),
            $scope(Biaya::where('status', 'Pending'))->count(),
            $scope(Kunjungan::where('status', 'Pending'), 'gudang_id', 'user_id')->count(),
            $scope(Pembayaran::where('status', 'Pending'))->count(),
        ];
        $menungguApproval = array_sum($pendingBreakdown);

        // 2. Transaksi Batal (Canceled)
        $canceled = $scope($bulanQuery(Penjualan::query())->where('status', 'Canceled'))->count()
            + $scope($bulanQuery(Pembelian::query())->where('status', 'Canceled'))->count()
            + $scope($bulanQuery(Biaya::query())->where('status', 'Canceled'))->count()
            + $scope(Kunjungan::whereYear('tgl_kunjungan', $now->year)->whereMonth('tgl_kunjungan', $now->month)->where('status', 'Canceled'), 'gudang_id', 'user_id')->count();

        // 3. Kunjungan Sales
        $kunjungan = $scope(Kunjungan::whereYear('tgl_kunjungan', $now->year)->whereMonth('tgl_kunjungan', $now->month), 'gudang_id', 'user_id');

        // 4. Total Produk
        if ($isUser && $userId) {
            $gudangUser = $user?->getCurrentGudang();
            $totalProduk = $gudangUser ? GudangProduk::where('gudang_id', $gudangUser->id)->count() : 0;
            $produkLabel = 'Produk di Gudang';
        } elseif ($isAdmin && $gudangId) {
            $totalProduk = GudangProduk::where('gudang_id', $gudangId)->count();
            $produkLabel = 'Produk di Gudang';
        } else {
            $totalProduk = Produk::count();
            $produkLabel = 'Total Produk';
        }

        $lastSixMonths = collect(range(5, 0))->map(fn ($month) => $now->copy()->subMonths($month));

        $canceledMonthlyChart = $lastSixMonths->map(function ($date) use ($scope): int {
            return $scope(Penjualan::whereYear('tgl_transaksi', $date->year)->whereMonth('tgl_transaksi', $date->month)->where('status', 'Canceled'))->count()
                 + $scope(Pembelian::whereYear('tgl_transaksi', $date->year)->whereMonth('tgl_transaksi', $date->month)->where('status', 'Canceled'))->count()
                 + $scope(Biaya::whereYear('tgl_transaksi', $date->year)->whereMonth('tgl_transaksi', $date->month)->where('status', 'Canceled'))->count()
                 + $scope(Kunjungan::whereYear('tgl_kunjungan', $date->year)->whereMonth('tgl_kunjungan', $date->month)->where('status', 'Canceled'), 'gudang_id', 'user_id')->count();
        })->all();

        $monthlyCount = function (string $modelClass, string $dateColumn, ?string $gudangColumn = 'gudang_id', ?string $userColumn = 'user_id') use ($lastSixMonths, $scope): array {
            return $lastSixMonths->map(fn ($date) => (int) $scope($modelClass::whereYear($dateColumn, $date->year)->whereMonth($dateColumn, $date->month), $gudangColumn, $userColumn)->count())->all();
        };

        return [
            Stat::make('Menunggu Approval', number_format($menungguApproval))
                ->description('Pending di semua modul')
                ->descriptionIcon('heroicon-o-clock')
                ->color($menungguApproval > 0 ? 'gray' : 'primary'),

            Stat::make('Transaksi Batal', number_format($canceled))
                ->description('Semua modul · '.$bulanLabel)
                ->descriptionIcon('heroicon-o-x-circle')
                ->color('gray'),

            Stat::make('Kunjungan Sales', number_format($kunjungan->count()).' kali')
                ->description('Bulan '.$bulanLabel)
                ->descriptionIcon('heroicon-o-map-pin')
                ->color('info'),

            Stat::make($produkLabel, number_format($totalProduk))
                ->description('Produk terdaftar')
                ->descriptionIcon('heroicon-o-cube')
                ->color('primary'),
        ];
    }
}
