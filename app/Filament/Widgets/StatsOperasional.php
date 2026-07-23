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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

        $cacheKey = 'widget_stats_operasional:'.$user->id.':'.$user->role.':'.($gudangId ?? $userId ?? 'all').':'.$now->format('Y-m');

        return Cache::remember($cacheKey, 300, function () use ($scope, $isUser, $userId, $gudangId, $isAdmin, $now, $bulanLabel) {
            // 1. Menunggu Approval (Pending) — single GROUP BY query per model
            $pendingModels = [
                Penjualan::class => 'gudang_id',
                Pembelian::class => 'gudang_id',
                Biaya::class => 'gudang_id',
                Kunjungan::class => 'gudang_id',
                Pembayaran::class => 'gudang_id',
            ];

            $menungguApproval = 0;
            foreach ($pendingModels as $model => $gudangCol) {
                $q = $model::where('status', 'Pending');
                if ($isUser && $userId) {
                    $menungguApproval += $model::where('status', 'Pending')->where('user_id', $userId)->count();
                } else {
                    $menungguApproval += (int) $scope($model::where('status', 'Pending'), $gudangCol, 'user_id')->count();
                }
            }

            // 2. Transaksi Batal (Canceled) — current month, single query per model
            $canceled = $scope(Penjualan::query()->whereYear('tgl_transaksi', $now->year)->whereMonth('tgl_transaksi', $now->month)->where('status', 'Canceled'))->count()
                + $scope(Pembelian::query()->whereYear('tgl_transaksi', $now->year)->whereMonth('tgl_transaksi', $now->month)->where('status', 'Canceled'))->count()
                + $scope(Biaya::query()->whereYear('tgl_transaksi', $now->year)->whereMonth('tgl_transaksi', $now->month)->where('status', 'Canceled'))->count()
                + $scope(Kunjungan::whereYear('tgl_kunjungan', $now->year)->whereMonth('tgl_kunjungan', $now->month)->where('status', 'Canceled'), 'gudang_id', 'user_id')->count();

            // 3. Kunjungan Sales
            $kunjunganCount = $scope(Kunjungan::whereYear('tgl_kunjungan', $now->year)->whereMonth('tgl_kunjungan', $now->month), 'gudang_id', 'user_id')->count();

            // 4. Total Produk
            if ($isUser && $userId) {
                $gudangUser = auth()->user()?->getCurrentGudang();
                $totalProduk = $gudangUser ? GudangProduk::where('gudang_id', $gudangUser->id)->count() : 0;
                $produkLabel = 'Produk di Gudang';
            } elseif ($isAdmin && $gudangId) {
                $totalProduk = GudangProduk::where('gudang_id', $gudangId)->count();
                $produkLabel = 'Produk di Gudang';
            } else {
                $totalProduk = Produk::count();
                $produkLabel = 'Total Produk';
            }

            // 5. Canceled monthly chart — single GROUP BY query per model
            $lastSixMonths = collect(range(5, 0))->map(fn ($month) => $now->copy()->subMonths($month));
            $startDate = $lastSixMonths->first()->copy()->startOfMonth();

            $canceledMonthlyChart = $lastSixMonths->map(function ($date) use ($scope) {
                return $scope(Penjualan::whereYear('tgl_transaksi', $date->year)->whereMonth('tgl_transaksi', $date->month)->where('status', 'Canceled'))->count()
                     + $scope(Pembelian::whereYear('tgl_transaksi', $date->year)->whereMonth('tgl_transaksi', $date->month)->where('status', 'Canceled'))->count()
                     + $scope(Biaya::whereYear('tgl_transaksi', $date->year)->whereMonth('tgl_transaksi', $date->month)->where('status', 'Canceled'))->count()
                     + $scope(Kunjungan::whereYear('tgl_kunjungan', $date->year)->whereMonth('tgl_kunjungan', $date->month)->where('status', 'Canceled'), 'gudang_id', 'user_id')->count();
            })->all();

            return [
                Stat::make('Menunggu Approval', number_format($menungguApproval))
                    ->description('Pending di semua modul')
                    ->descriptionIcon('heroicon-o-clock')
                    ->color($menungguApproval > 0 ? 'warning' : 'success'),

                Stat::make('Transaksi Batal', number_format($canceled))
                    ->description('Semua modul · '.$bulanLabel)
                    ->descriptionIcon('heroicon-o-x-circle')
                    ->color($canceled > 0 ? 'danger' : 'gray'),

                Stat::make('Kunjungan Sales', number_format($kunjunganCount).' kali')
                    ->description('Bulan '.$bulanLabel)
                    ->descriptionIcon('heroicon-o-map-pin')
                    ->color('gray'),

                Stat::make($produkLabel, number_format($totalProduk))
                    ->description('Produk terdaftar')
                    ->descriptionIcon('heroicon-o-cube')
                    ->color('gray'),
            ];
        });
    }
}
