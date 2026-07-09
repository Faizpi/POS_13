<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GudangProduk;
use App\Models\StokLog;
use DomainException;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

final class InventoryMutationService
{
    private const STOCK_COLUMNS = [
        'penjualan' => 'stok_penjualan',
        'saleable' => 'stok_penjualan',
        'sales' => 'stok_penjualan',
        'sale' => 'stok_penjualan',
        'receipt' => 'stok_penjualan',
        'penerimaan' => 'stok_penjualan',
        'gratis' => 'stok_gratis',
        'promo' => 'stok_gratis',
        'free' => 'stok_gratis',
        'sample' => 'stok_sample',
    ];

    /**
     * Assert that gudang_produk.stok == stok_penjualan + stok_gratis + stok_sample
     *
     * @throws DomainException
     */
    private function assertConsistency(GudangProduk $stock): void
    {
        $expected = (int) $stock->stok_penjualan + (int) $stock->stok_gratis + (int) $stock->stok_sample;
        $actual = (int) $stock->stok;

        if ($actual !== $expected) {
            throw new DomainException(
                "Inkonsistensi stok terdeteksi pada gudang_produk #{$stock->id}: ".
                "stok={$actual}, tetapi stok_penjualan={$stock->stok_penjualan} + ".
                "stok_gratis={$stock->stok_gratis} + stok_sample={$stock->stok_sample} = {$expected}"
            );
        }
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    public function decrement(int $gudangId, int $produkId, int $quantity, string $stockType, ?array $context = null): GudangProduk
    {
        $this->assertPositiveQuantity($quantity);

        $column = $this->columnFor($stockType);
        $stock = $this->lockedStockRow($gudangId, $produkId);

        if (! $stock instanceof GudangProduk) {
            throw new DomainException("Baris stok tidak ditemukan untuk gudang {$gudangId} dan produk {$produkId}.");
        }

        // Validate consistency BEFORE mutation
        $this->assertConsistency($stock);

        $availableSubtypeStock = (int) $stock->{$column};
        if ($availableSubtypeStock < $quantity) {
            throw new DomainException($this->insufficientStockMessage($stockType, $availableSubtypeStock, $quantity));
        }

        $availableTotalStock = (int) $stock->stok;
        if ($availableTotalStock < $quantity) {
            throw new DomainException("Stok total tidak cukup. Tersedia {$availableTotalStock}, diminta {$quantity}.");
        }

        $stokSebelum = (int) $stock->stok;

        $stock->stok = $availableTotalStock - $quantity;
        $stock->{$column} = $availableSubtypeStock - $quantity;
        $stock->save();

        // Validate consistency AFTER mutation
        $this->assertConsistency($stock);

        $this->logMutation($stock, $stokSebelum, (int) $stock->stok, $context);

        return $stock->refresh();
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    public function increment(int $gudangId, int $produkId, int $quantity, string $stockType, ?array $context = null): GudangProduk
    {
        $this->assertPositiveQuantity($quantity);

        $column = $this->columnFor($stockType);
        $stock = $this->lockedStockRow($gudangId, $produkId);

        if (! $stock instanceof GudangProduk) {
            $stock = GudangProduk::create([
                'gudang_id' => $gudangId,
                'produk_id' => $produkId,
                'stok' => 0,
                'stok_penjualan' => 0,
                'stok_gratis' => 0,
                'stok_sample' => 0,
            ]);
        }

        // Validate consistency BEFORE mutation
        $this->assertConsistency($stock);

        $stokSebelum = (int) $stock->stok;

        $stock->stok = $stokSebelum + $quantity;
        $stock->{$column} = (int) $stock->{$column} + $quantity;
        $stock->save();

        // Validate consistency AFTER mutation
        $this->assertConsistency($stock);

        $this->logMutation($stock, $stokSebelum, (int) $stock->stok, $context);

        return $stock->refresh();
    }

    private function lockedStockRow(int $gudangId, int $produkId): ?GudangProduk
    {
        return GudangProduk::query()
            ->where('gudang_id', $gudangId)
            ->where('produk_id', $produkId)
            ->lockForUpdate()
            ->first();
    }

    private function columnFor(string $stockType): string
    {
        $normalizedType = strtolower(trim($stockType));

        if (! array_key_exists($normalizedType, self::STOCK_COLUMNS)) {
            throw new InvalidArgumentException("Tipe stok tidak valid: {$stockType}.");
        }

        return self::STOCK_COLUMNS[$normalizedType];
    }

    private function assertPositiveQuantity(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Jumlah mutasi stok harus lebih besar dari 0.');
        }
    }

    private function insufficientStockMessage(string $stockType, int $available, int $requested): string
    {
        $label = match ($this->columnFor($stockType)) {
            'stok_penjualan' => 'penjualan',
            'stok_gratis' => 'gratis',
            'stok_sample' => 'sample',
        };

        return "Stok {$label} tidak cukup. Tersedia {$available}, diminta {$requested}.";
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    private function logMutation(GudangProduk $stock, int $stokSebelum, int $stokSesudah, ?array $context): void
    {
        if ($context === null || $context === []) {
            return;
        }

        $stock->loadMissing(['produk', 'gudang']);
        $user = Auth::user();
        $userId = $context['user_id'] ?? $user?->id;

        if ($userId === null) {
            throw new InvalidArgumentException('Konteks mutasi stok harus menyertakan user_id atau user terautentikasi.');
        }

        StokLog::create([
            'gudang_produk_id' => $stock->id,
            'produk_id' => $stock->produk_id,
            'gudang_id' => $stock->gudang_id,
            'user_id' => $userId,
            'produk_nama' => $stock->produk?->nama_produk ?? '',
            'gudang_nama' => $stock->gudang?->nama_gudang ?? '',
            'user_nama' => $context['user_nama'] ?? $user?->name ?? '',
            'stok_sebelum' => $stokSebelum,
            'stok_sesudah' => $stokSesudah,
            'selisih' => $stokSesudah - $stokSebelum,
            'keterangan' => $this->buildKeterangan($context),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function buildKeterangan(array $context): string
    {
        if (isset($context['keterangan'])) {
            return (string) $context['keterangan'];
        }

        $type = (string) ($context['transaction_type'] ?? $context['tipe'] ?? 'Mutasi');
        $id = $context['transaction_id'] ?? $context['id'] ?? null;
        $nomor = $context['transaction_nomor'] ?? $context['nomor'] ?? null;

        $result = $type;
        if ($id !== null) {
            $result .= " #{$id}";
        }
        if ($nomor !== null) {
            $result .= " ({$nomor})";
        }

        return $result;
    }
}
