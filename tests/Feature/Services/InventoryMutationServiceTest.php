<?php

namespace Tests\Feature\Services;

use App\Services\InventoryMutationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Support\BuildsTransactionFixtures;
use Tests\TestCase;

class InventoryMutationServiceTest extends TestCase
{
    use BuildsTransactionFixtures;
    use RefreshDatabase;

    public function test_decrement_rejects_insufficient_subtype_stock_without_partial_mutation(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $stock = $this->transactionStock($gudang, $produk, [
            'stok' => 13,
            'stok_penjualan' => 3,
            'stok_gratis' => 4,
            'stok_sample' => 6,
        ]);

        $service = new InventoryMutationService;

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Stok penjualan tidak cukup');

        try {
            DB::transaction(fn () => $service->decrement($gudang->id, $produk->id, 5, 'penjualan'));
        } finally {
            $this->assertDatabaseHas('gudang_produk', [
                'id' => $stock->id,
                'stok' => 13,
                'stok_penjualan' => 3,
                'stok_gratis' => 4,
                'stok_sample' => 6,
            ]);
        }
    }

    public function test_decrement_rejects_missing_stock_row_without_creating_stock(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $service = new InventoryMutationService;

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Baris stok tidak ditemukan');

        try {
            DB::transaction(fn () => $service->decrement($gudang->id, $produk->id, 1, 'gratis'));
        } finally {
            $this->assertDatabaseMissing('gudang_produk', [
                'gudang_id' => $gudang->id,
                'produk_id' => $produk->id,
            ]);
        }
    }

    public function test_decrement_and_increment_mutate_total_and_requested_subtype_only(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $this->transactionStock($gudang, $produk, [
            'stok' => 20,
            'stok_penjualan' => 10,
            'stok_gratis' => 7,
            'stok_sample' => 3,
        ]);

        $service = new InventoryMutationService;

        DB::transaction(fn () => $service->decrement($gudang->id, $produk->id, 2, 'sample'));
        DB::transaction(fn () => $service->increment($gudang->id, $produk->id, 1, 'sample'));

        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 19,
            'stok_penjualan' => 10,
            'stok_gratis' => 7,
            'stok_sample' => 2,
        ]);
    }

    public function test_increment_creates_missing_stock_row_for_receipt_stock(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $service = new InventoryMutationService;

        DB::transaction(fn () => $service->increment($gudang->id, $produk->id, 8, 'receipt'));

        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 8,
            'stok_penjualan' => 8,
            'stok_gratis' => 0,
            'stok_sample' => 0,
        ]);
    }

    public function test_committed_decrement_creates_one_stock_log_with_transaction_context(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $user = $this->transactionUser('admin', $gudang);
        $stock = $this->transactionStock($gudang, $produk, [
            'stok' => 20,
            'stok_penjualan' => 20,
            'stok_gratis' => 0,
            'stok_sample' => 0,
        ]);

        $this->actingAs($user);
        $service = new InventoryMutationService;

        DB::transaction(fn () => $service->decrement($gudang->id, $produk->id, 4, 'penjualan', [
            'transaction_type' => 'Penjualan Approve',
            'transaction_id' => 123,
            'transaction_nomor' => 'INV-123',
        ]));

        $this->assertDatabaseCount('stok_logs', 1);
        $this->assertDatabaseHas('stok_logs', [
            'gudang_produk_id' => $stock->id,
            'produk_id' => $produk->id,
            'gudang_id' => $gudang->id,
            'user_id' => $user->id,
            'produk_nama' => $produk->nama_produk,
            'gudang_nama' => $gudang->nama_gudang,
            'user_nama' => $user->name,
            'stok_sebelum' => 20,
            'stok_sesudah' => 16,
            'selisih' => -4,
            'keterangan' => 'Penjualan Approve #123 (INV-123)',
        ]);
    }

    public function test_rolled_back_increment_creates_no_stock_log(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $user = $this->transactionUser('admin', $gudang);
        $this->transactionStock($gudang, $produk, [
            'stok' => 10,
            'stok_penjualan' => 10,
            'stok_gratis' => 0,
            'stok_sample' => 0,
        ]);

        $this->actingAs($user);
        $service = new InventoryMutationService;

        try {
            DB::transaction(function () use ($service, $gudang, $produk): void {
                $service->increment($gudang->id, $produk->id, 3, 'receipt', [
                    'transaction_type' => 'Penerimaan Approve',
                    'transaction_id' => 456,
                    'transaction_nomor' => 'RCV-456',
                ]);

                throw new DomainException('force rollback');
            });
        } catch (DomainException $e) {
            $this->assertSame('force rollback', $e->getMessage());
        }

        $this->assertDatabaseCount('stok_logs', 0);
        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 10,
            'stok_penjualan' => 10,
        ]);
    }

    public function test_rejected_decrement_creates_no_stock_log(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $user = $this->transactionUser('admin', $gudang);
        $this->transactionStock($gudang, $produk, [
            'stok' => 2,
            'stok_penjualan' => 2,
            'stok_gratis' => 0,
            'stok_sample' => 0,
        ]);

        $this->actingAs($user);
        $service = new InventoryMutationService;

        try {
            DB::transaction(fn () => $service->decrement($gudang->id, $produk->id, 5, 'penjualan', [
                'transaction_type' => 'Penjualan Approve',
                'transaction_id' => 789,
                'transaction_nomor' => 'INV-789',
            ]));
        } catch (DomainException $e) {
            $this->assertStringContainsString('Stok penjualan tidak cukup', $e->getMessage());
        }

        $this->assertDatabaseCount('stok_logs', 0);
    }

    public function test_invalid_stock_type_is_rejected_before_mutation(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $stock = $this->transactionStock($gudang, $produk, [
            'stok' => 10,
            'stok_penjualan' => 10,
            'stok_gratis' => 0,
            'stok_sample' => 0,
        ]);

        $service = new InventoryMutationService;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tipe stok tidak valid');

        try {
            DB::transaction(fn () => $service->decrement($gudang->id, $produk->id, 1, 'retur'));
        } finally {
            $stock->refresh();

            $this->assertSame(10, $stock->stok);
            $this->assertSame(10, $stock->stok_penjualan);
        }
    }
}
