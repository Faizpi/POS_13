<?php

namespace App\Services;

use App\Models\Gudang;
use App\Models\GudangProduk;
use App\Models\Pembelian;
use App\Models\Penjualan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NeracaService
{
    /**
     * Omset pergudang: total grand_total penjualan per gudang.
     */
    public function getOmsetPerGudang(?string $from = null, ?string $to = null, ?int $gudangId = null, ?array $allowedWarehouseIds = null): Collection
    {
        $query = Penjualan::whereIn('status', ['Approved', 'Lunas'])
            ->select('gudang_id', DB::raw('SUM(grand_total) as total'))
            ->groupBy('gudang_id');

        $this->applyWarehouseScope($query, $gudangId, $allowedWarehouseIds);

        return $this->applyDateFilter($query, 'tgl_transaksi', $from, $to)
            ->with('gudang')
            ->orderBy('gudang_id')
            ->get()
            ->map(fn ($item) => [
                'gudang_id' => $item->gudang_id,
                'gudang' => $item->gudang?->nama_gudang ?? 'Tanpa Gudang',
                'total' => (float) ($item->total ?? 0),
            ]);
    }

    /**
     * Nilai pembelian gudang.
     */
    public function getNilaiPembelian(?string $from = null, ?string $to = null, ?int $gudangId = null, ?array $allowedWarehouseIds = null): Collection
    {
        $query = Pembelian::whereIn('status', ['Approved', 'Lunas'])
            ->select('gudang_id', DB::raw('SUM(grand_total) as total'))
            ->groupBy('gudang_id');

        $this->applyWarehouseScope($query, $gudangId, $allowedWarehouseIds);

        return $this->applyDateFilter($query, 'tgl_transaksi', $from, $to)
            ->with('gudang')
            ->orderBy('gudang_id')
            ->get()
            ->map(fn ($item) => [
                'gudang_id' => $item->gudang_id,
                'gudang' => $item->gudang?->nama_gudang ?? 'Tanpa Gudang',
                'total' => (float) ($item->total ?? 0),
            ]);
    }

    /**
     * Nilai penjualan retail.
     */
    public function getPenjualanRetail(?string $from = null, ?string $to = null, ?int $gudangId = null, ?array $allowedWarehouseIds = null): Collection
    {
        return $this->getPenjualanByTipeHarga('retail', $from, $to, $gudangId, $allowedWarehouseIds);
    }

    /**
     * Nilai penjualan grosir.
     */
    public function getPenjualanGrosir(?string $from = null, ?string $to = null, ?int $gudangId = null, ?array $allowedWarehouseIds = null): Collection
    {
        return $this->getPenjualanByTipeHarga('grosir', $from, $to, $gudangId, $allowedWarehouseIds);
    }

    /**
     * Jumlah produk terjual retail.
     */
    public function getJumlahProdukTerjualRetail(?string $from = null, ?string $to = null, ?int $gudangId = null, ?array $allowedWarehouseIds = null): float
    {
        return $this->getTotalQtyTerjual('retail', $from, $to, $gudangId, $allowedWarehouseIds);
    }

    /**
     * Jumlah produk terjual grosir.
     */
    public function getJumlahProdukTerjualGrosir(?string $from = null, ?string $to = null, ?int $gudangId = null, ?array $allowedWarehouseIds = null): float
    {
        return $this->getTotalQtyTerjual('grosir', $from, $to, $gudangId, $allowedWarehouseIds);
    }

    /**
     * Pembayaran belum lunas pergudang: penjualan Approved (belum Lunas).
     */
    public function getPembayaranBelumLunas(?int $gudangId = null, ?array $allowedWarehouseIds = null): Collection
    {
        $query = Penjualan::where('status', 'Approved')
            ->select('gudang_id', DB::raw('SUM(grand_total) as total'))
            ->groupBy('gudang_id');

        $this->applyWarehouseScope($query, $gudangId, $allowedWarehouseIds);

        return $query->with('gudang')
            ->orderBy('gudang_id')
            ->get()
            ->map(fn ($item) => [
                'gudang_id' => $item->gudang_id,
                'gudang' => $item->gudang?->nama_gudang ?? 'Tanpa Gudang',
                'total' => (float) ($item->total ?? 0),
            ]);
    }

    /**
     * Nilai persediaan retail per gudang: SUM(produks.harga * gudang_produk.stok_penjualan)
     * Ini adalah valuasi current stock, tidak terpengaruh filter tanggal.
     */
    public function getNilaiPersediaanRetail(?int $gudangId = null, ?array $allowedWarehouseIds = null): Collection
    {
        $query = GudangProduk::join('produks', 'gudang_produk.produk_id', '=', 'produks.id')
            ->join('gudangs', 'gudang_produk.gudang_id', '=', 'gudangs.id')
            ->select(
                'gudang_produk.gudang_id',
                'gudangs.nama_gudang',
                DB::raw('SUM(produks.harga * gudang_produk.stok_penjualan) as total_nilai')
            )
            ->where('gudang_produk.stok_penjualan', '>', 0)
            ->groupBy('gudang_produk.gudang_id', 'gudangs.nama_gudang');

        $this->applyWarehouseScope($query, $gudangId, $allowedWarehouseIds, 'gudang_produk.gudang_id');

        return $query->orderBy('gudang_produk.gudang_id')
            ->get()
            ->map(fn ($item) => [
                'gudang_id' => $item->gudang_id,
                'gudang' => $item->nama_gudang ?? 'Tanpa Gudang',
                'total' => (float) ($item->total_nilai ?? 0),
            ]);
    }

    /**
     * Nilai persediaan grosir per gudang: SUM(produks.harga_grosir * gudang_produk.stok_penjualan)
     * Ini adalah valuasi current stock, tidak terpengaruh filter tanggal.
     */
    public function getNilaiPersediaanGrosir(?int $gudangId = null, ?array $allowedWarehouseIds = null): Collection
    {
        $query = GudangProduk::join('produks', 'gudang_produk.produk_id', '=', 'produks.id')
            ->join('gudangs', 'gudang_produk.gudang_id', '=', 'gudangs.id')
            ->select(
                'gudang_produk.gudang_id',
                'gudangs.nama_gudang',
                DB::raw('SUM(produks.harga_grosir * gudang_produk.stok_penjualan) as total_nilai')
            )
            ->where('gudang_produk.stok_penjualan', '>', 0)
            ->groupBy('gudang_produk.gudang_id', 'gudangs.nama_gudang');

        $this->applyWarehouseScope($query, $gudangId, $allowedWarehouseIds, 'gudang_produk.gudang_id');

        return $query->orderBy('gudang_produk.gudang_id')
            ->get()
            ->map(fn ($item) => [
                'gudang_id' => $item->gudang_id,
                'gudang' => $item->nama_gudang ?? 'Tanpa Gudang',
                'total' => (float) ($item->total_nilai ?? 0),
            ]);
    }

    /**
     * Ringkasan Neraca lengkap (semua metrik) untuk dashboard.
     */
    public function getRingkasan(?string $from = null, ?string $to = null, ?int $gudangId = null, ?array $allowedWarehouseIds = null): array
    {
        // If allowedWarehouseIds is empty array, return empty data (no access)
        if ($allowedWarehouseIds !== null && empty($allowedWarehouseIds)) {
            return $this->getEmptyRingkasan();
        }

        $omset = $this->getOmsetPerGudang($from, $to, $gudangId, $allowedWarehouseIds);
        $pembelian = $this->getNilaiPembelian($from, $to, $gudangId, $allowedWarehouseIds);
        $retail = $this->getPenjualanRetail($from, $to, $gudangId, $allowedWarehouseIds);
        $grosir = $this->getPenjualanGrosir($from, $to, $gudangId, $allowedWarehouseIds);
        $qtyRetail = $this->getJumlahProdukTerjualRetail($from, $to, $gudangId, $allowedWarehouseIds);
        $qtyGrosir = $this->getJumlahProdukTerjualGrosir($from, $to, $gudangId, $allowedWarehouseIds);
        $belumLunas = $this->getPembayaranBelumLunas($gudangId, $allowedWarehouseIds);
        $persediaanRetail = $this->getNilaiPersediaanRetail($gudangId, $allowedWarehouseIds);
        $persediaanGrosir = $this->getNilaiPersediaanGrosir($gudangId, $allowedWarehouseIds);

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
            'persediaan_retail' => [
                'gudang' => $persediaanRetail,
                'total' => $persediaanRetail->sum('total'),
            ],
            'persediaan_grosir' => [
                'gudang' => $persediaanGrosir,
                'total' => $persediaanGrosir->sum('total'),
            ],
        ];
    }

    /**
     * Return empty ringkasan when no warehouse access.
     */
    private function getEmptyRingkasan(): array
    {
        return [
            'omset' => collect(),
            'total_omset' => 0.0,
            'pembelian' => collect(),
            'total_pembelian' => 0.0,
            'retail' => collect(),
            'total_retail' => 0.0,
            'grosir' => collect(),
            'total_grosir' => 0.0,
            'qty_retail' => 0.0,
            'qty_grosir' => 0.0,
            'belum_lunas' => collect(),
            'total_belum_lunas' => 0.0,
            'persediaan_retail' => [
                'gudang' => collect(),
                'total' => 0.0,
            ],
            'persediaan_grosir' => [
                'gudang' => collect(),
                'total' => 0.0,
            ],
        ];
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function getPenjualanByTipeHarga(string $tipeHarga, ?string $from, ?string $to, ?int $gudangId = null, ?array $allowedWarehouseIds = null): Collection
    {
        $query = Penjualan::where('tipe_harga', $tipeHarga)
            ->whereIn('status', ['Approved', 'Lunas'])
            ->select('gudang_id', DB::raw('SUM(grand_total) as total'))
            ->groupBy('gudang_id');

        $this->applyWarehouseScope($query, $gudangId, $allowedWarehouseIds);

        return $this->applyDateFilter($query, 'tgl_transaksi', $from, $to)
            ->with('gudang')
            ->orderBy('gudang_id')
            ->get()
            ->map(fn ($item) => [
                'gudang_id' => $item->gudang_id,
                'gudang' => $item->gudang?->nama_gudang ?? 'Tanpa Gudang',
                'total' => (float) ($item->total ?? 0),
            ]);
    }

    private function getTotalQtyTerjual(string $tipeHarga, ?string $from, ?string $to, ?int $gudangId = null, ?array $allowedWarehouseIds = null): float
    {
        $query = Penjualan::where('tipe_harga', $tipeHarga)
            ->whereIn('status', ['Approved', 'Lunas'])
            ->join('penjualan_items', 'penjualans.id', '=', 'penjualan_items.penjualan_id')
            ->select(DB::raw('SUM(penjualan_items.kuantitas) as total_qty'));

        $this->applyWarehouseScope($query, $gudangId, $allowedWarehouseIds, 'penjualans.gudang_id');

        $this->applyDateFilter($query, 'penjualans.tgl_transaksi', $from, $to);

        return (float) ($query->first()->total_qty ?? 0);
    }

    /**
     * Apply warehouse scope based on specific gudangId or allowedWarehouseIds.
     * Priority: gudangId (specific filter) > allowedWarehouseIds (role-based scope)
     */
    private function applyWarehouseScope($query, ?int $gudangId = null, ?array $allowedWarehouseIds = null, string $column = 'gudang_id'): void
    {
        if ($gudangId !== null) {
            // Specific gudang filter takes priority
            $query->where($column, $gudangId);
        } elseif ($allowedWarehouseIds !== null) {
            // Role-based scope (spectator restriction)
            if (empty($allowedWarehouseIds)) {
                // No access - return impossible condition
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn($column, $allowedWarehouseIds);
            }
        }
    }

    private function applyDateFilter($query, string $column, ?string $from, ?string $to)
    {
        if ($from) {
            $query->where($column, '>=', $from);
        }
        if ($to) {
            $query->where($column, '<=', $to);
        }

        return $query;
    }
}
