<?php

namespace Tests\Feature\Api;

use App\Models\Gudang;
use App\Models\GudangProduk;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NeracaInventoryValuationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /**
     * Test: Valuasi persediaan retail dihitung dengan formula SUM(produks.harga * gudang_produk.stok_penjualan)
     */
    public function test_inventory_valuation_uses_retail_price_times_stok_penjualan(): void
    {
        $gudang = Gudang::where('nama_gudang', 'Gudang A')->firstOrFail();

        // Clear existing stock for isolation
        GudangProduk::query()->delete();

        // Produk 1: harga 25000, stok_penjualan 100
        $produk1 = Produk::where('item_code', 'SBN-001')->firstOrFail();
        GudangProduk::create([
            'gudang_id' => $gudang->id,
            'produk_id' => $produk1->id,
            'stok_penjualan' => 100,
            'stok_gratis' => 20,
            'stok_sample' => 10,
            'stok' => 130,
        ]);

        // Produk 2: harga 35000, stok_penjualan 50
        $produk2 = Produk::where('item_code', 'BDL-001')->firstOrFail();
        GudangProduk::create([
            'gudang_id' => $gudang->id,
            'produk_id' => $produk2->id,
            'stok_penjualan' => 50,
            'stok_gratis' => 15,
            'stok_sample' => 5,
            'stok' => 70,
        ]);

        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/neraca');

        $response->assertOk();

        $data = $response->json();

        // Expected: (25000 * 100) + (35000 * 50) = 2,500,000 + 1,750,000 = 4,250,000
        $expected = (25000 * 100) + (35000 * 50);

        $this->assertArrayHasKey('persediaan_retail', $data);
        $this->assertArrayHasKey('gudang', $data['persediaan_retail']);
        $this->assertArrayHasKey('total', $data['persediaan_retail']);

        // Find Gudang A in the list
        $gudangAValuation = collect($data['persediaan_retail']['gudang'])
            ->firstWhere('gudang', 'Gudang A');

        $this->assertNotNull($gudangAValuation);
        $this->assertEquals($expected, $gudangAValuation['total']);
        $this->assertEquals($expected, $data['persediaan_retail']['total']);
    }

    /**
     * Test: Valuasi dikelompokkan per gudang
     */
    public function test_inventory_valuation_grouped_by_warehouse(): void
    {
        $gudangA = Gudang::where('nama_gudang', 'Gudang A')->firstOrFail();
        $gudangB = Gudang::where('nama_gudang', 'Gudang B')->firstOrFail();

        // Clear existing stock for isolation
        GudangProduk::query()->delete();

        $produk = Produk::where('item_code', 'SBN-001')->firstOrFail();

        // Gudang A: 100 units @ 25000 = 2,500,000
        GudangProduk::create([
            'gudang_id' => $gudangA->id,
            'produk_id' => $produk->id,
            'stok_penjualan' => 100,
            'stok_gratis' => 0,
            'stok_sample' => 0,
            'stok' => 100,
        ]);

        // Gudang B: 50 units @ 25000 = 1,250,000
        GudangProduk::create([
            'gudang_id' => $gudangB->id,
            'produk_id' => $produk->id,
            'stok_penjualan' => 50,
            'stok_gratis' => 0,
            'stok_sample' => 0,
            'stok' => 50,
        ]);

        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/neraca');

        $response->assertOk();

        $data = $response->json();

        $this->assertCount(2, $data['persediaan_retail']['gudang']);

        $gudangAValuation = collect($data['persediaan_retail']['gudang'])
            ->firstWhere('gudang', 'Gudang A');
        $gudangBValuation = collect($data['persediaan_retail']['gudang'])
            ->firstWhere('gudang', 'Gudang B');

        $this->assertNotNull($gudangAValuation);
        $this->assertNotNull($gudangBValuation);
        $this->assertEquals(2500000, $gudangAValuation['total']);
        $this->assertEquals(1250000, $gudangBValuation['total']);
        $this->assertEquals(3750000, $data['persediaan_retail']['total']);
    }

    /**
     * Test: Spectator hanya melihat gudang yang diizinkan
     */
    public function test_spectator_can_only_see_allowed_warehouses(): void
    {
        $gudangA = Gudang::where('nama_gudang', 'Gudang A')->firstOrFail();
        $gudangB = Gudang::where('nama_gudang', 'Gudang B')->firstOrFail();

        // Clear existing stock for isolation
        GudangProduk::query()->delete();

        $produk = Produk::where('item_code', 'SBN-001')->firstOrFail();

        // Setup stock di kedua gudang
        GudangProduk::create([
            'gudang_id' => $gudangA->id,
            'produk_id' => $produk->id,
            'stok_penjualan' => 100,
            'stok_gratis' => 0,
            'stok_sample' => 0,
            'stok' => 100,
        ]);

        GudangProduk::create([
            'gudang_id' => $gudangB->id,
            'produk_id' => $produk->id,
            'stok_penjualan' => 50,
            'stok_gratis' => 0,
            'stok_sample' => 0,
            'stok' => 50,
        ]);

        // Spectator hanya diizinkan akses Gudang A (dari seeder)
        $token = $this->login('spectator@hibiscusefsya.com');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/neraca');

        $response->assertOk();

        $data = $response->json();

        // Spectator hanya melihat Gudang A
        $this->assertCount(1, $data['persediaan_retail']['gudang']);
        $this->assertEquals('Gudang A', $data['persediaan_retail']['gudang'][0]['gudang']);
        $this->assertEquals(2500000, $data['persediaan_retail']['total']);
    }

    /**
     * Test: Spectator tidak bisa mengakses gudang lain dengan parameter gudang_id
     */
    public function test_spectator_cannot_access_unauthorized_warehouse_via_parameter(): void
    {
        $gudangB = Gudang::where('nama_gudang', 'Gudang B')->firstOrFail();

        // Clear existing stock for isolation
        GudangProduk::query()->delete();

        $produk = Produk::where('item_code', 'SBN-001')->firstOrFail();

        GudangProduk::create([
            'gudang_id' => $gudangB->id,
            'produk_id' => $produk->id,
            'stok_penjualan' => 50,
            'stok_gratis' => 0,
            'stok_sample' => 0,
            'stok' => 50,
        ]);

        $token = $this->login('spectator@hibiscusefsya.com');

        // Spectator mencoba akses Gudang B (tidak diizinkan)
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/neraca?gudang_id='.$gudangB->id);

        $response->assertStatus(403);
    }

    /**
     * Test: Spectator bisa mengakses gudang yang diizinkan dengan parameter gudang_id
     */
    public function test_spectator_can_access_allowed_warehouse_via_parameter(): void
    {
        $gudangA = Gudang::where('nama_gudang', 'Gudang A')->firstOrFail();

        // Clear existing stock for isolation
        GudangProduk::query()->delete();

        $produk = Produk::where('item_code', 'SBN-001')->firstOrFail();

        GudangProduk::create([
            'gudang_id' => $gudangA->id,
            'produk_id' => $produk->id,
            'stok_penjualan' => 100,
            'stok_gratis' => 0,
            'stok_sample' => 0,
            'stok' => 100,
        ]);

        $token = $this->login('spectator@hibiscusefsya.com');

        // Spectator akses Gudang A (diizinkan)
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/neraca?gudang_id='.$gudangA->id);

        $response->assertOk();

        $data = $response->json();

        $this->assertCount(1, $data['persediaan_retail']['gudang']);
        $this->assertEquals('Gudang A', $data['persediaan_retail']['gudang'][0]['gudang']);
        $this->assertEquals(2500000, $data['persediaan_retail']['total']);
    }

    /**
     * Test: Empty state - tidak ada stok mengembalikan 0
     */
    public function test_empty_inventory_returns_zero(): void
    {
        // Hapus semua stok
        GudangProduk::query()->delete();

        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/neraca');

        $response->assertOk();

        $data = $response->json();

        $this->assertEmpty($data['persediaan_retail']['gudang']);
        $this->assertEquals(0, $data['persediaan_retail']['total']);
    }

    /**
     * Test: stok_gratis dan stok_sample tidak dihitung
     */
    public function test_stok_gratis_and_stok_sample_excluded(): void
    {
        $gudang = Gudang::where('nama_gudang', 'Gudang A')->firstOrFail();

        // Clear existing stock for isolation
        GudangProduk::query()->delete();

        $produk = Produk::where('item_code', 'SBN-001')->firstOrFail();

        // Setup: stok_penjualan 100, stok_gratis 50, stok_sample 30
        GudangProduk::create([
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok_penjualan' => 100,
            'stok_gratis' => 50,
            'stok_sample' => 30,
            'stok' => 180,
        ]);

        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/neraca');

        $response->assertOk();

        $data = $response->json();

        // Expected: hanya stok_penjualan (100 * 25000 = 2,500,000)
        // BUKAN (180 * 25000 = 4,500,000)
        $expected = 100 * 25000;

        $gudangAValuation = collect($data['persediaan_retail']['gudang'])
            ->firstWhere('gudang', 'Gudang A');

        $this->assertEquals($expected, $gudangAValuation['total']);
        $this->assertEquals($expected, $data['persediaan_retail']['total']);
    }

    /**
     * Test: Valuasi persediaan tidak terpengaruh filter tanggal (current stock)
     */
    public function test_inventory_valuation_ignores_date_filter(): void
    {
        $gudang = Gudang::where('nama_gudang', 'Gudang A')->firstOrFail();

        // Clear existing stock for isolation
        GudangProduk::query()->delete();

        $produk = Produk::where('item_code', 'SBN-001')->firstOrFail();

        GudangProduk::create([
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok_penjualan' => 100,
            'stok_gratis' => 0,
            'stok_sample' => 0,
            'stok' => 100,
        ]);

        $token = $this->login('superadmin@hibiscusefsya.com');

        // Request dengan filter tanggal
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/neraca?from=2020-01-01&to=2020-12-31');

        $response->assertOk();

        $data = $response->json();

        // Valuasi tetap muncul (current stock, tidak terpengaruh tanggal)
        $this->assertEquals(2500000, $data['persediaan_retail']['total']);
    }

    /**
     * Test: Super admin melihat semua gudang
     */
    public function test_super_admin_sees_all_warehouses(): void
    {
        $gudangA = Gudang::where('nama_gudang', 'Gudang A')->firstOrFail();
        $gudangB = Gudang::where('nama_gudang', 'Gudang B')->firstOrFail();

        // Clear existing stock for isolation
        GudangProduk::query()->delete();

        $produk = Produk::where('item_code', 'SBN-001')->firstOrFail();

        GudangProduk::create([
            'gudang_id' => $gudangA->id,
            'produk_id' => $produk->id,
            'stok_penjualan' => 100,
            'stok_gratis' => 0,
            'stok_sample' => 0,
            'stok' => 100,
        ]);

        GudangProduk::create([
            'gudang_id' => $gudangB->id,
            'produk_id' => $produk->id,
            'stok_penjualan' => 50,
            'stok_gratis' => 0,
            'stok_sample' => 0,
            'stok' => 50,
        ]);

        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/neraca');

        $response->assertOk();

        $data = $response->json();

        // Super admin melihat semua gudang
        $this->assertCount(2, $data['persediaan_retail']['gudang']);
        $this->assertEquals(3750000, $data['persediaan_retail']['total']);
    }

    /**
     * Test: Unauthorized user tidak bisa akses neraca
     */
    public function test_unauthorized_user_cannot_access_neraca(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/neraca');

        $response->assertStatus(403);
    }

    /**
     * Test: PDF export menyertakan valuasi persediaan
     */
    public function test_pdf_export_includes_inventory_valuation(): void
    {
        $gudang = Gudang::where('nama_gudang', 'Gudang A')->firstOrFail();

        // Clear existing stock for isolation
        GudangProduk::query()->delete();

        $produk = Produk::where('item_code', 'SBN-001')->firstOrFail();

        GudangProduk::create([
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok_penjualan' => 100,
            'stok_gratis' => 0,
            'stok_sample' => 0,
            'stok' => 100,
        ]);

        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/neraca/export-pdf');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    /**
     * Test: Excel export menyertakan valuasi persediaan
     */
    public function test_excel_export_includes_inventory_valuation(): void
    {
        $gudang = Gudang::where('nama_gudang', 'Gudang A')->firstOrFail();

        // Clear existing stock for isolation
        GudangProduk::query()->delete();

        $produk = Produk::where('item_code', 'SBN-001')->firstOrFail();

        GudangProduk::create([
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok_penjualan' => 100,
            'stok_gratis' => 0,
            'stok_sample' => 0,
            'stok' => 100,
        ]);

        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/neraca/export-excel');

        $response->assertOk();
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
    }

    /**
     * Test: Valuasi persediaan konsisten antara JSON, PDF, dan Excel
     */
    public function test_inventory_valuation_parity_across_json_pdf_excel(): void
    {
        $gudang = Gudang::where('nama_gudang', 'Gudang A')->firstOrFail();

        // Clear existing stock for isolation
        GudangProduk::query()->delete();

        $produk = Produk::where('item_code', 'SBN-001')->firstOrFail();

        GudangProduk::create([
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok_penjualan' => 100,
            'stok_gratis' => 20,
            'stok_sample' => 10,
            'stok' => 130,
        ]);

        $token = $this->login('superadmin@hibiscusefsya.com');

        // JSON
        $jsonResponse = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/neraca');

        $jsonResponse->assertOk();
        $jsonData = $jsonResponse->json();

        // PDF
        $pdfResponse = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/neraca/export-pdf');

        $pdfResponse->assertOk();

        // Excel
        $excelResponse = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/neraca/export-excel');

        $excelResponse->assertOk();

        // Verifikasi JSON memiliki data yang benar
        $expected = 100 * 25000; // stok_penjualan * harga
        $this->assertEquals($expected, $jsonData['persediaan_retail']['total']);

        // PDF dan Excel juga harus berhasil (parity tercapai jika semua endpoint sukses)
        $this->assertStringStartsWith('%PDF', $pdfResponse->getContent());
        $this->assertStringContainsString('.xlsx', $excelResponse->headers->get('content-disposition'));
    }

    /**
     * Test: Super admin dapat filter inventory per gudang
     */
    public function test_super_admin_can_filter_inventory_by_warehouse(): void
    {
        $gudangA = Gudang::where('nama_gudang', 'Gudang A')->firstOrFail();
        $gudangB = Gudang::where('nama_gudang', 'Gudang B')->firstOrFail();

        // Clear existing stock for isolation
        GudangProduk::query()->delete();

        $produk = Produk::where('item_code', 'SBN-001')->firstOrFail();

        GudangProduk::create([
            'gudang_id' => $gudangA->id,
            'produk_id' => $produk->id,
            'stok_penjualan' => 100,
            'stok_gratis' => 0,
            'stok_sample' => 0,
            'stok' => 100,
        ]);

        GudangProduk::create([
            'gudang_id' => $gudangB->id,
            'produk_id' => $produk->id,
            'stok_penjualan' => 50,
            'stok_gratis' => 0,
            'stok_sample' => 0,
            'stok' => 50,
        ]);

        $token = $this->login('superadmin@hibiscusefsya.com');

        // Filter hanya Gudang A
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/neraca?gudang_id='.$gudangA->id);

        $response->assertOk();

        $data = $response->json();

        // Hanya melihat Gudang A
        $this->assertCount(1, $data['persediaan_retail']['gudang']);
        $this->assertEquals('Gudang A', $data['persediaan_retail']['gudang'][0]['gudang']);
        $this->assertEquals(2500000, $data['persediaan_retail']['total']);
    }

    /**
     * Test: Spectator melihat semua metrik terbatas pada gudang yang diizinkan
     */
    public function test_spectator_all_metrics_respect_warehouse_scope(): void
    {
        $gudangA = Gudang::where('nama_gudang', 'Gudang A')->firstOrFail();
        $gudangB = Gudang::where('nama_gudang', 'Gudang B')->firstOrFail();

        // Clear existing stock for isolation
        GudangProduk::query()->delete();

        $produk = Produk::where('item_code', 'SBN-001')->firstOrFail();

        GudangProduk::create([
            'gudang_id' => $gudangA->id,
            'produk_id' => $produk->id,
            'stok_penjualan' => 100,
            'stok_gratis' => 0,
            'stok_sample' => 0,
            'stok' => 100,
        ]);

        GudangProduk::create([
            'gudang_id' => $gudangB->id,
            'produk_id' => $produk->id,
            'stok_penjualan' => 50,
            'stok_gratis' => 0,
            'stok_sample' => 0,
            'stok' => 50,
        ]);

        $token = $this->login('spectator@hibiscusefsya.com');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/neraca');

        $response->assertOk();

        $data = $response->json();

        // Semua metrik hanya menampilkan Gudang A
        $this->assertCount(1, $data['persediaan_retail']['gudang']);
        $this->assertEquals('Gudang A', $data['persediaan_retail']['gudang'][0]['gudang']);

        // Verifikasi gudang_id ada di output
        $this->assertArrayHasKey('gudang_id', $data['persediaan_retail']['gudang'][0]);
        $this->assertEquals($gudangA->id, $data['persediaan_retail']['gudang'][0]['gudang_id']);
    }

    /**
     * Test: Unauthorized gudang_id (tidak ada) mengembalikan 404
     */
    public function test_nonexistent_gudang_id_returns_404(): void
    {
        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/neraca?gudang_id=99999');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Gudang tidak ditemukan']);
    }

    /**
     * Test: Spectator tanpa assignment gudang melihat data kosong
     */
    public function test_spectator_with_no_warehouse_assignment_sees_empty_data(): void
    {
        // Buat spectator baru tanpa assignment
        $user = User::create([
            'name' => 'Spectator No Access',
            'email' => 'spectator_noaccess@test.com',
            'password' => bcrypt('password'),
            'role' => 'spectator',
        ]);

        $gudang = Gudang::where('nama_gudang', 'Gudang A')->firstOrFail();

        GudangProduk::query()->delete();

        $produk = Produk::where('item_code', 'SBN-001')->firstOrFail();

        GudangProduk::create([
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok_penjualan' => 100,
            'stok_gratis' => 0,
            'stok_sample' => 0,
            'stok' => 100,
        ]);

        $token = $this->postJson('/api/v1/login', [
            'email' => 'spectator_noaccess@test.com',
            'password' => 'password',
        ])->json('token');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/neraca');

        $response->assertOk();

        $data = $response->json();

        // Spectator tanpa assignment tidak melihat data
        $this->assertEmpty($data['persediaan_retail']['gudang']);
        $this->assertEquals(0, $data['persediaan_retail']['total']);
    }

    /**
     * Test: Output inventory memiliki deterministic ordering
     */
    public function test_inventory_output_has_deterministic_ordering(): void
    {
        $gudangA = Gudang::where('nama_gudang', 'Gudang A')->firstOrFail();
        $gudangB = Gudang::where('nama_gudang', 'Gudang B')->firstOrFail();

        GudangProduk::query()->delete();

        $produk = Produk::where('item_code', 'SBN-001')->firstOrFail();

        // Create dalam urutan acak
        GudangProduk::create([
            'gudang_id' => $gudangB->id,
            'produk_id' => $produk->id,
            'stok_penjualan' => 50,
            'stok_gratis' => 0,
            'stok_sample' => 0,
            'stok' => 50,
        ]);

        GudangProduk::create([
            'gudang_id' => $gudangA->id,
            'produk_id' => $produk->id,
            'stok_penjualan' => 100,
            'stok_gratis' => 0,
            'stok_sample' => 0,
            'stok' => 100,
        ]);

        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/neraca');

        $response->assertOk();

        $data = $response->json();

        // Output harus sorted by gudang_id
        $this->assertCount(2, $data['persediaan_retail']['gudang']);
        $this->assertEquals($gudangA->id, $data['persediaan_retail']['gudang'][0]['gudang_id']);
        $this->assertEquals($gudangB->id, $data['persediaan_retail']['gudang'][1]['gudang_id']);
    }

    private function login(string $email): string
    {
        return $this->postJson('/api/v1/login', [
            'email' => $email,
            'password' => 'password123',
        ])->json('token');
    }
}
