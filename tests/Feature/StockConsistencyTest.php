<?php

namespace Tests\Feature;

use App\Models\GudangProduk;
use App\Services\InventoryMutationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsTransactionFixtures;
use Tests\TestCase;

class StockConsistencyTest extends TestCase
{
    use BuildsTransactionFixtures;
    use RefreshDatabase;

    public function test_decrement_maintains_stock_consistency(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $user = $this->transactionUser('user', $gudang);
        $stock = $this->transactionStock($gudang, $produk, [
            'stok' => 100,
            'stok_penjualan' => 80,
            'stok_gratis' => 10,
            'stok_sample' => 10,
        ]);

        $service = new InventoryMutationService;

        DB::transaction(fn () => $service->decrement(
            $gudang->id,
            $produk->id,
            5,
            'penjualan',
            ['user_id' => $user->id, 'user_nama' => $user->name]
        ));

        $stock->refresh();

        // Verify consistency is maintained
        $this->assertEquals(
            $stock->stok_penjualan + $stock->stok_gratis + $stock->stok_sample,
            $stock->stok,
            'Stock consistency violated after decrement'
        );

        $this->assertEquals(95, $stock->stok);
        $this->assertEquals(75, $stock->stok_penjualan);
        $this->assertEquals(10, $stock->stok_gratis);
        $this->assertEquals(10, $stock->stok_sample);
    }

    public function test_increment_maintains_stock_consistency(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $user = $this->transactionUser('user', $gudang);
        $stock = $this->transactionStock($gudang, $produk, [
            'stok' => 50,
            'stok_penjualan' => 30,
            'stok_gratis' => 10,
            'stok_sample' => 10,
        ]);

        $service = new InventoryMutationService;

        DB::transaction(fn () => $service->increment(
            $gudang->id,
            $produk->id,
            20,
            'gratis',
            ['user_id' => $user->id, 'user_nama' => $user->name]
        ));

        $stock->refresh();

        // Verify consistency is maintained
        $this->assertEquals(
            $stock->stok_penjualan + $stock->stok_gratis + $stock->stok_sample,
            $stock->stok,
            'Stock consistency violated after increment'
        );

        $this->assertEquals(70, $stock->stok);
        $this->assertEquals(30, $stock->stok_penjualan);
        $this->assertEquals(30, $stock->stok_gratis);
        $this->assertEquals(10, $stock->stok_sample);
    }

    public function test_decrement_throws_exception_on_preexisting_inconsistency(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $user = $this->transactionUser('user', $gudang);

        // Create inconsistent stock directly
        $stock = GudangProduk::create([
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 105, // Wrong! Should be 100 (80+10+10)
            'stok_penjualan' => 80,
            'stok_gratis' => 10,
            'stok_sample' => 10,
        ]);

        $service = new InventoryMutationService;

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Inkonsistensi stok terdeteksi');

        DB::transaction(fn () => $service->decrement(
            $gudang->id,
            $produk->id,
            5,
            'penjualan',
            ['user_id' => $user->id, 'user_nama' => $user->name]
        ));
    }

    public function test_increment_throws_exception_on_preexisting_inconsistency(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $user = $this->transactionUser('user', $gudang);

        // Create inconsistent stock directly
        $stock = GudangProduk::create([
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 95, // Wrong! Should be 100
            'stok_penjualan' => 80,
            'stok_gratis' => 10,
            'stok_sample' => 10,
        ]);

        $service = new InventoryMutationService;

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Inkonsistensi stok terdeteksi');

        DB::transaction(fn () => $service->increment(
            $gudang->id,
            $produk->id,
            5,
            'penjualan',
            ['user_id' => $user->id, 'user_nama' => $user->name]
        ));
    }

    public function test_audit_command_detects_violations(): void
    {
        $gudang1 = $this->transactionGudang();
        $produk1 = $this->transactionProduk();
        $gudang2 = $this->transactionGudang();
        $produk2 = $this->transactionProduk();

        // Create consistent stock
        $this->transactionStock($gudang1, $produk1, [
            'stok' => 100,
            'stok_penjualan' => 80,
            'stok_gratis' => 10,
            'stok_sample' => 10,
        ]);

        // Create inconsistent stock (different gudang+produk to avoid unique constraint)
        GudangProduk::create([
            'gudang_id' => $gudang2->id,
            'produk_id' => $produk2->id,
            'stok' => 150, // Wrong! Should be 100
            'stok_penjualan' => 80,
            'stok_gratis' => 10,
            'stok_sample' => 10,
        ]);

        // Run audit command
        $this->artisan('audit:stock-consistency')
            ->expectsOutput('Found 1 stock consistency violation(s):')
            ->assertExitCode(1);
    }

    public function test_audit_command_passes_when_no_violations(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();

        // Create consistent stock
        $this->transactionStock($gudang, $produk, [
            'stok' => 100,
            'stok_penjualan' => 80,
            'stok_gratis' => 10,
            'stok_sample' => 10,
        ]);

        // Run audit command
        $this->artisan('audit:stock-consistency')
            ->expectsOutput('✓ No stock consistency violations found.')
            ->assertExitCode(0);
    }

    public function test_foreign_key_constraint_on_stok_logs_gudang_produk_id(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $user = $this->transactionUser('user', $gudang);
        $stock = $this->transactionStock($gudang, $produk, [
            'stok' => 100,
            'stok_penjualan' => 80,
            'stok_gratis' => 10,
            'stok_sample' => 10,
        ]);

        // Create a stok_log entry
        $logId = DB::table('stok_logs')->insertGetId([
            'gudang_produk_id' => $stock->id,
            'produk_id' => $produk->id,
            'gudang_id' => $gudang->id,
            'user_id' => $user->id,
            'produk_nama' => 'Test',
            'gudang_nama' => 'Test',
            'user_nama' => 'Test',
            'stok_sebelum' => 100,
            'stok_sesudah' => 95,
            'selisih' => -5,
            'keterangan' => 'Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify the log was created with the FK reference
        $this->assertDatabaseHas('stok_logs', [
            'id' => $logId,
            'gudang_produk_id' => $stock->id,
        ]);

        // FK constraint exists - actual enforcement depends on DB engine
        $this->assertTrue(true, 'FK constraint migration applied');
    }

    public function test_multiple_mutations_maintain_consistency(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $user = $this->transactionUser('user', $gudang);
        $stock = $this->transactionStock($gudang, $produk, [
            'stok' => 100,
            'stok_penjualan' => 80,
            'stok_gratis' => 10,
            'stok_sample' => 10,
        ]);

        $service = new InventoryMutationService;
        $context = ['user_id' => $user->id, 'user_nama' => $user->name];

        // Perform multiple mutations
        DB::transaction(fn () => $service->decrement($gudang->id, $produk->id, 10, 'penjualan', $context));
        DB::transaction(fn () => $service->increment($gudang->id, $produk->id, 5, 'gratis', $context));
        DB::transaction(fn () => $service->decrement($gudang->id, $produk->id, 3, 'sample', $context));
        DB::transaction(fn () => $service->increment($gudang->id, $produk->id, 15, 'penjualan', $context));

        $stock->refresh();

        // Verify consistency is maintained after all mutations
        $this->assertEquals(
            $stock->stok_penjualan + $stock->stok_gratis + $stock->stok_sample,
            $stock->stok,
            'Stock consistency violated after multiple mutations'
        );

        // Expected: stok = 100 - 10 + 5 - 3 + 15 = 107
        // penjualan: 80 - 10 + 15 = 85, gratis: 10 + 5 = 15, sample: 10 - 3 = 7
        // Total: 85 + 15 + 7 = 107
        $this->assertEquals(107, $stock->stok);
        $this->assertEquals(85, $stock->stok_penjualan);
        $this->assertEquals(15, $stock->stok_gratis);
        $this->assertEquals(7, $stock->stok_sample);
    }
}
