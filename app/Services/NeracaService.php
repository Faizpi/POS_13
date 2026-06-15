<?php

namespace App\Services;

use App\Models\Gudang;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NeracaService
{
    /**
     * Omset pergudang: total grand_total penjualan per gudang.
     */
    public function getOmsetPerGudang(?string $from = null, ?string $to = null, ?int $gudangId = null): Collection
    {
        $query = Penjualan::whereIn('status', ['Approved', 'Lunas'])
            ->select('gudang_id', DB::raw('SUM(grand_total) as total'))
            ->groupBy('gudang_id');

        return $this->applyDateFilter($query, 'tgl_transaksi', $from, $to, $gudangId)
            ->with('gudang')
            ->get()
            ->map(fn($item) => [
                'gudang' => $item->gudang?->nama_gudang ?? 'Tanpa Gudang',
                'total' => (float) ($item->total ?? 0),
            ]);
    }

    /**
     * Nilai pembelian gudang.
     */
    public function getNilaiPembelian(?string $from = null, ?string $to = null, ?int $gudangId = null): Collection
    {
        $query = Pembelian::whereIn('status', ['Approved', 'Lunas'])
            ->select('gudang_id', DB::raw('SUM(grand_total) as total'))
            ->groupBy('gudang_id');

        return $this->applyDateFilter($query, 'tgl_transaksi', $from, $to, $gudangId)
            ->with('gudang')
            ->get()
            ->map(fn($item) => [
                'gudang' => $item->gudang?->nama_gudang ?? 'Tanpa Gudang',
                'total' => (float) ($item->total ?? 0),
            ]);
    }

    /**
     * Nilai penjualan retail.
     */
    public function getPenjualanRetail(?string $from = null, ?string $to = null, ?int $gudangId = null): Collection
    {
        return $this->getPenjualanByTipeHarga('retail', $from, $to, $gudangId);
    }

    /**
     * Nilai penjualan grosir.
     */
    public function getPenjualanGrosir(?string $from = null, ?string $to = null, ?int $gudangId = null): Collection
    {
        return $this->getPenjualanByTipeHarga('grosir', $from, $to, $gudangId);
    }

    /**
     * Jumlah produk terjual retail.
     */
    public function getJumlahProdukTerjualRetail(?string $from = null, ?string $to = null, ?int $gudangId = null): float
    {
        return $this->getTotalQtyTerjual('retail', $from, $to, $gudangId);
    }

    /**
     * Jumlah produk terjual grosir.
     */
    public function getJumlahProdukTerjualGrosir(?string $from = null, ?string $to = null, ?int $gudangId = null): float
    {
        return $this->getTotalQtyTerjual('grosir', $from, $to, $gudangId);
    }

    /**
     * Pembayaran belum lunas pergudang: penjualan Approved (belum Lunas).
     */
    public function getPembayaranBelumLunas(?int $gudangId = null): Collection
    {
        $query = Penjualan::where('status', 'Approved')
            ->select('gudang_id', DB::raw('SUM(grand_total) as total'))
            ->groupBy('gudang_id');

        if ($gudangId) {
            $query->where('gudang_id', $gudangId);
        }

        return $query->with('gudang')
            ->get()
            ->map(fn($item) => [
                'gudang' => $item->gudang?->nama_gudang ?? 'Tanpa Gudang',
                'total' => (float) ($item->total ?? 0),
            ]);
    }

    /**
     * Ringkasan Neraca lengkap (semua metrik) untuk dashboard.
     */
    public function getRingkasan(?string $from = null, ?string $to = null, ?int $gudangId = null): array
    {
        $omset = $this->getOmsetPerGudang($from, $to, $gudangId);
        $pembelian = $this->getNilaiPembelian($from, $to, $gudangId);
        $retail = $this->getPenjualanRetail($from, $to, $gudangId);
        $grosir = $this->getPenjualanGrosir($from, $to, $gudangId);
        $qtyRetail = $this->getJumlahProdukTerjualRetail($from, $to, $gudangId);
        $qtyGrosir = $this->getJumlahProdukTerjualGrosir($from, $to, $gudangId);
        $belumLunas = $this->getPembayaranBelumLunas($gudangId);

        return [
            'omset' => $omset,
            'total_omset' => $omset->sum('total'),
            'pembelian' => $pembelian,
            'total_pembelian' => $pembelian->sum('total'),
            'retail' => $retail,
            'total_retail' => $retail->sum('total'),
            'grosir' => $grosir,
            'total_grosir' => $grosir->sum('total'),
            'qty_retail' => $qtyRetail,
            'qty_grosir' => $qtyGrosir,
            'belum_lunas' => $belumLunas,
            'total_belum_lunas' => $belumLunas->sum('total'),
        ];
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function getPenjualanByTipeHarga(string $tipeHarga, ?string $from, ?string $to, ?int $gudangId): Collection
    {
        $query = Penjualan::where('tipe_harga', $tipeHarga)
            ->whereIn('status', ['Approved', 'Lunas'])
            ->select('gudang_id', DB::raw('SUM(grand_total) as total'))
            ->groupBy('gudang_id');

        return $this->applyDateFilter($query, 'tgl_transaksi', $from, $to, $gudangId)
            ->with('gudang')
            ->get()
            ->map(fn($item) => [
                'gudang' => $item->gudang?->nama_gudang ?? 'Tanpa Gudang',
                'total' => (float) ($item->total ?? 0),
            ]);
    }

    private function getTotalQtyTerjual(string $tipeHarga, ?string $from, ?string $to, ?int $gudangId): float
    {
        $query = Penjualan::where('tipe_harga', $tipeHarga)
            ->whereIn('status', ['Approved', 'Lunas'])
            ->join('penjualan_items', 'penjualans.id', '=', 'penjualan_items.penjualan_id')
            ->select(DB::raw('SUM(penjualan_items.kuantitas) as total_qty'));

        if ($gudangId) {
            $query->where('penjualans.gudang_id', $gudangId);
        }

        $this->applyDateFilter($query, 'penjualans.tgl_transaksi', $from, $to);

        return (float) ($query->first()->total_qty ?? 0);
    }

    private function applyDateFilter($query, string $column, ?string $from, ?string $to, ?int $gudangId = null)
    {
        if ($from) {
            $query->where($column, '>=', $from);
        }
        if ($to) {
            $query->where($column, '<=', $to);
        }
        if ($gudangId) {
            $query->where('gudang_id', $gudangId);
        }
        return $query;
    }
}
