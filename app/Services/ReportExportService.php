<?php

namespace App\Services;

use App\Models\Biaya;
use App\Models\Kontak;
use App\Models\Kunjungan;
use App\Models\Pembayaran;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\User;
use Illuminate\Support\Collection;

class ReportExportService
{
    public function buildExportData(
        User $user,
        string $type,
        string $dateFrom,
        string $dateTo,
        ?string $status = null,
        ?int $gudangId = null,
        ?int $salesId = null,
        ?string $biayaJenis = null,
        ?string $tujuanFilter = null,
    ): Collection {
        $status = $status ?: 'all';
        $accessibleGudangIds = $user->role === 'admin'
            ? $user->gudangs()->pluck('gudangs.id')
            : collect();

        $kontakQuery = Kontak::whereNotNull('no_telp')
            ->where('no_telp', '!=', '');

        if ($user->role === 'admin') {
            $kontakQuery->whereIn('gudang_id', $accessibleGudangIds);
        }

        if ($gudangId) {
            $kontakQuery->where('gudang_id', $gudangId);
        }

        $kontakPhoneMap = $kontakQuery->pluck('no_telp', 'nama')->toArray();

        $applyCommonFilters = function ($query, string $dateColumn) use ($user, $dateFrom, $dateTo, $status, $gudangId, $salesId, $accessibleGudangIds) {
            $query->whereBetween($dateColumn, [$dateFrom, $dateTo]);

            if ($user->role === 'admin') {
                $query->whereIn('gudang_id', $accessibleGudangIds);
            }

            if ($gudangId) {
                $query->where('gudang_id', $gudangId);
            }

            if ($status !== 'all') {
                $query->where('status', $status);
            }

            if ($salesId) {
                $query->where('user_id', $salesId);
            }

            return $query;
        };

        $datasets = collect();

        if (in_array($type, ['all', 'penjualan'], true)) {
            $penjualans = $applyCommonFilters(
                Penjualan::with(['user', 'gudang', 'approver', 'items.produk']),
                'tgl_transaksi'
            )->get()->each(function (Penjualan $item) use ($kontakPhoneMap) {
                $item->type = 'Penjualan';
                $item->number = $item->custom_number;
                $item->display_contact_name = $item->pelanggan ?: '-';
                $item->no_telp_kontak = $item->no_telepon ?: ($kontakPhoneMap[$item->pelanggan] ?? '-');
            });

            $datasets = $datasets->concat($penjualans);
        }

        if (in_array($type, ['all', 'pembelian'], true)) {
            $pembelians = $applyCommonFilters(
                Pembelian::with(['user', 'gudang', 'approver', 'items.produk']),
                'tgl_transaksi'
            )->get()->each(function (Pembelian $item) {
                $item->type = 'Pembelian';
                $item->number = $item->custom_number;
                $item->display_contact_name = '-';
                $item->no_telp_kontak = '-';
            });

            $datasets = $datasets->concat($pembelians);
        }

        if (in_array($type, ['all', 'biaya'], true)) {
            $query = $applyCommonFilters(
                Biaya::with(['user', 'gudang', 'approver', 'items']),
                'tgl_transaksi'
            );

            if ($type === 'biaya' && $biayaJenis) {
                $query->where('jenis_biaya', $biayaJenis);
            }

            $biayas = $query->get()->each(function (Biaya $item) use ($kontakPhoneMap) {
                $item->type = 'Biaya';
                $item->number = $item->custom_number;
                $item->display_contact_name = $item->penerima ?: '-';
                $item->no_telp_kontak = $kontakPhoneMap[$item->penerima] ?? '-';
            });

            $datasets = $datasets->concat($biayas);
        }

        if (in_array($type, ['all', 'kunjungan'], true)) {
            $query = $applyCommonFilters(
                Kunjungan::with(['user', 'gudang', 'approver', 'items.produk', 'kontak']),
                'tgl_kunjungan'
            );

            if ($tujuanFilter && $tujuanFilter !== 'all') {
                $query->where('tujuan', $tujuanFilter);
            }

            $kunjungans = $query->get()->each(function (Kunjungan $item) {
                $item->type = 'Kunjungan';
                $item->number = $item->custom_number;
                $item->display_contact_name = $item->kontak?->nama ?: '-';
                $item->no_telp_kontak = $item->kontak?->no_telp ?: '-';
            });

            $datasets = $datasets->concat($kunjungans);
        }

        if (in_array($type, ['all', 'pembayaran', 'pembayaran_piutang', 'pembayaran_hutang'], true)) {
            $pembayaranQuery = Pembayaran::with([
                'user',
                'gudang',
                'approver',
                'penjualan.gudang',
                'pembelian.gudang',
                'pembelian.kontak',
            ]);

            if ($type === 'pembayaran_piutang') {
                $pembayaranQuery->where('type', 'piutang');
            } elseif ($type === 'pembayaran_hutang') {
                $pembayaranQuery->where('type', 'hutang');
            }

            $pembayarans = $applyCommonFilters($pembayaranQuery, 'tgl_pembayaran')
                ->get()
                ->each(function (Pembayaran $item) {
                    $originalType = $item->type;
                    $item->type = 'Pembayaran';
                    $item->pembayaran_kind = $originalType;
                    $item->number = $item->custom_number;

                    if ($originalType === 'hutang') {
                        $item->display_contact_name = $item->pembelian?->kontak?->nama ?: '-';
                        $item->no_telp_kontak = $item->pembelian?->kontak?->no_telp ?: '-';
                    } else {
                        $item->display_contact_name = $item->penjualan?->pelanggan ?: '-';
                        $item->no_telp_kontak = $item->penjualan?->no_telepon ?: '-';
                    }
                });

            $datasets = $datasets->concat($pembayarans);
        }

        return $datasets->sortBy('created_at')->values();
    }
}
