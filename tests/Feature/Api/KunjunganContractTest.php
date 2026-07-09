<?php

namespace Tests\Feature\Api;

use App\Models\GudangProduk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KunjunganContractTest extends TestCase
{
    use RefreshDatabase;

    /** @covers-finding B03 API promo/sample visit stock mutations */
    public function test_promo_gratis_approval_decrements_total_and_gratis_stock(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $this->transactionStock($gudang, $produk, [
            'stok' => 15,
            'stok_penjualan' => 5,
            'stok_gratis' => 7,
            'stok_sample' => 3,
        ]);
        $sales = $this->transactionUser('user', $gudang);
        $admin = $this->transactionUser('admin', $gudang);
        $kunjungan = $this->transactionKunjungan($sales, $gudang, null, [
            'tujuan' => 'Promo Gratis',
            'approver_id' => $admin->id,
        ], [
            ['produk' => $produk, 'jumlah' => 4],
        ]);

        $response = $this->postJson(
            "/api/v1/kunjungan/{$kunjungan->id}/approve",
            [],
            $this->authHeaderFor($admin),
        );

        $response->assertOk()
            ->assertJson(['message' => 'Kunjungan berhasil di-approve.'])
            ->assertJsonPath('data.status', 'Approved');

        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 11,
            'stok_penjualan' => 5,
            'stok_gratis' => 3,
            'stok_sample' => 3,
        ]);
    }

    /** @covers-finding B03 API promo/sample visit stock mutations */
    public function test_promo_sample_approval_decrements_total_and_sample_stock(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $this->transactionStock($gudang, $produk, [
            'stok' => 15,
            'stok_penjualan' => 5,
            'stok_gratis' => 3,
            'stok_sample' => 7,
        ]);
        $sales = $this->transactionUser('user', $gudang);
        $admin = $this->transactionUser('admin', $gudang);
        $kunjungan = $this->transactionKunjungan($sales, $gudang, null, [
            'tujuan' => 'Promo Sample',
            'approver_id' => $admin->id,
        ], [
            ['produk' => $produk, 'jumlah' => 4],
        ]);

        $response = $this->postJson(
            "/api/v1/kunjungan/{$kunjungan->id}/approve",
            [],
            $this->authHeaderFor($admin),
        );

        $response->assertOk()
            ->assertJson(['message' => 'Kunjungan berhasil di-approve.'])
            ->assertJsonPath('data.status', 'Approved');

        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 11,
            'stok_penjualan' => 5,
            'stok_gratis' => 3,
            'stok_sample' => 3,
        ]);
    }

    public function test_cancel_approved_promo_restores_stock_once_but_pending_cancel_does_not_mutate(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $this->transactionStock($gudang, $produk, [
            'stok' => 15,
            'stok_penjualan' => 5,
            'stok_gratis' => 7,
            'stok_sample' => 3,
        ]);
        $sales = $this->transactionUser('user', $gudang);
        $admin = $this->transactionUser('admin', $gudang);
        $superAdmin = $this->transactionUser('super_admin');
        $approved = $this->transactionKunjungan($sales, $gudang, null, [
            'tujuan' => 'Promo Gratis',
            'approver_id' => $admin->id,
        ], [
            ['produk' => $produk, 'jumlah' => 4],
        ]);
        $pending = $this->transactionKunjungan($sales, $gudang, null, [
            'tujuan' => 'Promo Gratis',
        ], [
            ['produk' => $produk, 'jumlah' => 4],
        ]);

        $this->postJson(
            "/api/v1/kunjungan/{$approved->id}/approve",
            [],
            $this->authHeaderFor($admin),
        )->assertOk();

        $this->postJson(
            "/api/v1/kunjungan/{$approved->id}/cancel",
            [],
            $this->authHeaderFor($superAdmin),
        )->assertOk();

        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 15,
            'stok_gratis' => 7,
        ]);

        $this->postJson(
            "/api/v1/kunjungan/{$pending->id}/cancel",
            [],
            $this->authHeaderFor($superAdmin),
        )->assertOk();

        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 15,
            'stok_gratis' => 7,
        ]);
    }

    public function test_insufficient_promo_stock_rejects_approval_without_partial_mutation(): void
    {
        $gudang = $this->transactionGudang();
        $firstProduk = $this->transactionProduk();
        $secondProduk = $this->transactionProduk();
        $this->transactionStock($gudang, $firstProduk, [
            'stok' => 10,
            'stok_penjualan' => 5,
            'stok_gratis' => 5,
            'stok_sample' => 0,
        ]);
        $this->transactionStock($gudang, $secondProduk, [
            'stok' => 2,
            'stok_penjualan' => 1,
            'stok_gratis' => 1,
            'stok_sample' => 0,
        ]);
        $sales = $this->transactionUser('user', $gudang);
        $admin = $this->transactionUser('admin', $gudang);
        $kunjungan = $this->transactionKunjungan($sales, $gudang, null, [
            'tujuan' => 'Promo Gratis',
            'approver_id' => $admin->id,
        ], [
            ['produk' => $firstProduk, 'jumlah' => 4],
            ['produk' => $secondProduk, 'jumlah' => 3],
        ]);

        $response = $this->postJson(
            "/api/v1/kunjungan/{$kunjungan->id}/approve",
            [],
            $this->authHeaderFor($admin),
        );

        $response->assertStatus(422)
            ->assertJsonPath('data.status', 'Pending');

        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $firstProduk->id,
            'stok' => 10,
            'stok_gratis' => 5,
        ]);
        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $secondProduk->id,
            'stok' => 2,
            'stok_gratis' => 1,
        ]);
        $this->assertDatabaseHas('kunjungans', [
            'id' => $kunjungan->id,
            'status' => 'Pending',
        ]);
    }

    public function test_super_admin_auto_approved_promo_store_decrements_stock_atomically(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $kontak = $this->transactionKontak($gudang);
        $superAdmin = $this->transactionUser('super_admin');
        $this->transactionStock($gudang, $produk, [
            'stok' => 15,
            'stok_penjualan' => 5,
            'stok_gratis' => 7,
            'stok_sample' => 3,
        ]);

        $response = $this->postJson('/api/v1/kunjungan', [
            'kontak_id' => $kontak->id,
            'gudang_id' => $gudang->id,
            'tgl_kunjungan' => now()->toDateString(),
            'tujuan' => 'Promo Gratis',
            'items' => [
                ['produk_id' => $produk->id, 'jumlah' => 4],
            ],
        ], $this->authHeaderFor($superAdmin));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'Approved');

        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 11,
            'stok_gratis' => 3,
            'stok_sample' => 3,
        ]);
    }

    public function test_non_promo_and_pemeriksaan_stock_approval_do_not_mutate_stock(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $this->transactionStock($gudang, $produk, [
            'stok' => 15,
            'stok_penjualan' => 5,
            'stok_gratis' => 7,
            'stok_sample' => 3,
        ]);
        $sales = $this->transactionUser('user', $gudang);
        $admin = $this->transactionUser('admin', $gudang);
        $pemeriksaan = $this->transactionKunjungan($sales, $gudang, null, [
            'tujuan' => 'Pemeriksaan Stock',
            'approver_id' => $admin->id,
        ], [
            ['produk' => $produk, 'jumlah' => 4],
        ]);
        $penagihan = $this->transactionKunjungan($sales, $gudang, null, [
            'tujuan' => 'Penagihan',
            'approver_id' => $admin->id,
        ], []);

        $this->postJson(
            "/api/v1/kunjungan/{$pemeriksaan->id}/approve",
            [],
            $this->authHeaderFor($admin),
        )->assertOk();
        $this->postJson(
            "/api/v1/kunjungan/{$penagihan->id}/approve",
            [],
            $this->authHeaderFor($admin),
        )->assertOk();

        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 15,
            'stok_penjualan' => 5,
            'stok_gratis' => 7,
            'stok_sample' => 3,
        ]);
    }

    public function test_uncancel_returns_pending_without_reapplying_promo_stock_then_approval_validates_stock(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $this->transactionStock($gudang, $produk, [
            'stok' => 10,
            'stok_penjualan' => 0,
            'stok_gratis' => 10,
            'stok_sample' => 0,
        ]);
        $sales = $this->transactionUser('user', $gudang);
        $admin = $this->transactionUser('admin', $gudang);
        $superAdmin = $this->transactionUser('super_admin');
        $kunjungan = $this->transactionKunjungan($sales, $gudang, null, [
            'tujuan' => 'Promo Gratis',
            'approver_id' => $admin->id,
        ], [
            ['produk' => $produk, 'jumlah' => 4],
        ]);

        $this->postJson("/api/v1/kunjungan/{$kunjungan->id}/approve", [], $this->authHeaderFor($admin))
            ->assertOk();
        $this->postJson("/api/v1/kunjungan/{$kunjungan->id}/cancel", [], $this->authHeaderFor($superAdmin))
            ->assertOk();
        $this->postJson("/api/v1/kunjungan/{$kunjungan->id}/uncancel", [], $this->authHeaderFor($superAdmin))
            ->assertOk()
            ->assertJsonPath('data.status', 'Pending');

        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 10,
            'stok_gratis' => 10,
        ]);

        GudangProduk::where('gudang_id', $gudang->id)
            ->where('produk_id', $produk->id)
            ->update(['stok' => 2, 'stok_gratis' => 2]);

        $this->postJson("/api/v1/kunjungan/{$kunjungan->id}/approve", [], $this->authHeaderFor($admin))
            ->assertStatus(422)
            ->assertJsonPath('data.status', 'Pending');

        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 2,
            'stok_gratis' => 2,
        ]);
        $this->assertDatabaseHas('kunjungans', [
            'id' => $kunjungan->id,
            'status' => 'Pending',
        ]);
    }
}
