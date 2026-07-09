<?php

namespace Tests\Feature\Api;

use App\Filament\Resources\Penjualans\Pages\CreatePenjualan as CreatePenjualanPage;
use App\Models\Gudang;
use App\Models\GudangProduk;
use App\Models\Pembayaran;
use App\Models\Penjualan;
use App\Models\PenjualanItem;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class PenjualanContractTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    // === INDEX ===

    public function test_index_returns_paginated_for_super_admin(): void
    {
        $this->createPenjualan('salesa@hibiscusefsya.com');
        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->getJson('/api/v1/penjualan', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'per_page', 'total']);
    }

    public function test_user_sees_only_own_penjualan(): void
    {
        $this->createPenjualan('salesa@hibiscusefsya.com');
        $this->createPenjualan('salesb@hibiscusefsya.com');
        $token = $this->login('salesa@hibiscusefsya.com');

        $response = $this->getJson('/api/v1/penjualan', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('total'));
    }

    // === STORE ===

    public function test_store_success_creates_pending(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');
        $gudangA = Gudang::where('nama_gudang', 'Gudang A')->first();
        $produk = Produk::first();

        $response = $this->postJson('/api/v1/penjualan', [
            'pelanggan' => 'Customer Test',
            'tgl_transaksi' => '2026-06-05',
            'syarat_pembayaran' => 'Cash',
            'gudang_id' => $gudangA->id,
            'tax_percentage' => 0,
            'items' => [
                ['produk_id' => $produk->id, 'kuantitas' => 2],
            ],
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(201)
            ->assertJson(['message' => 'Penjualan berhasil dibuat.'])
            ->assertJsonPath('data.status', 'Pending');

        // Verify nomor generated
        $this->assertStringStartsWith('INV-', $response->json('data.nomor'));
    }

    public function test_store_insufficient_stock_returns_422(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');
        $gudangA = Gudang::where('nama_gudang', 'Gudang A')->first();
        $produk = Produk::first();

        $response = $this->postJson('/api/v1/penjualan', [
            'pelanggan' => 'Customer',
            'tgl_transaksi' => '2026-06-05',
            'syarat_pembayaran' => 'Cash',
            'gudang_id' => $gudangA->id,
            'tax_percentage' => 0,
            'items' => [
                ['produk_id' => $produk->id, 'kuantitas' => 9999],
            ],
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Stok tidak mencukupi.'])
            ->assertJsonStructure(['errors']);
    }

    public function test_store_spectator_forbidden(): void
    {
        $token = $this->login('spectator@hibiscusefsya.com');
        $gudangA = Gudang::where('nama_gudang', 'Gudang A')->first();
        $produk = Produk::first();

        $response = $this->postJson('/api/v1/penjualan', [
            'pelanggan' => 'Test',
            'tgl_transaksi' => '2026-06-05',
            'syarat_pembayaran' => 'Cash',
            'gudang_id' => $gudangA->id,
            'tax_percentage' => 0,
            'items' => [['produk_id' => $produk->id, 'kuantitas' => 1]],
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Spectator tidak bisa membuat transaksi.']);
    }

    public function test_store_calculates_grosir_price(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');
        $gudangA = Gudang::where('nama_gudang', 'Gudang A')->first();
        $produk = Produk::where('item_code', 'SBN-001')->first(); // harga 25000, grosir 22000

        $response = $this->postJson('/api/v1/penjualan', [
            'pelanggan' => 'Grosir Customer',
            'tgl_transaksi' => '2026-06-05',
            'syarat_pembayaran' => 'Net 30',
            'gudang_id' => $gudangA->id,
            'tipe_harga' => 'grosir',
            'tax_percentage' => 0,
            'diskon_akhir' => 0,
            'items' => [
                ['produk_id' => $produk->id, 'kuantitas' => 10, 'diskon' => 0],
            ],
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(201);
        // 10 * 22000 = 220000
        $this->assertEquals(220000, $response->json('data.grand_total'));
    }

    /** @covers-finding B04 Filament form totals not trusted */
    public function test_store_recomputes_totals_and_ignores_tampered_client_money_fields(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');
        $gudangA = Gudang::where('nama_gudang', 'Gudang A')->first();
        $produk = Produk::firstOrFail();
        $produk->update(['harga' => 10000, 'harga_grosir' => 9000]);
        GudangProduk::updateOrCreate(
            ['gudang_id' => $gudangA->id, 'produk_id' => $produk->id],
            ['stok' => 20, 'stok_penjualan' => 20, 'stok_gratis' => 0, 'stok_sample' => 0]
        );

        $response = $this->postJson('/api/v1/penjualan', [
            'pelanggan' => 'Tampered Customer',
            'tgl_transaksi' => '2026-06-05',
            'syarat_pembayaran' => 'Cash',
            'gudang_id' => $gudangA->id,
            'tipe_harga' => 'retail',
            'tax_percentage' => 10,
            'diskon_akhir' => 1000,
            'grand_total' => 1,
            'items' => [[
                'produk_id' => $produk->id,
                'kuantitas' => 2,
                'harga_satuan' => 999999,
                'diskon' => 0,
                'jumlah_baris' => 1,
            ]],
        ], ['Authorization' => "Bearer $token"]);

        $response->assertCreated()
            ->assertJsonPath('data.grand_total', '20900.00')
            ->assertJsonPath('data.items.0.harga_satuan', '10000.00')
            ->assertJsonPath('data.items.0.jumlah_baris', 20000);
    }

    /** @covers-finding B16 Tax/discount validation (penjualan) */
    public function test_store_rejects_tax_above_100(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');
        $gudangA = Gudang::where('nama_gudang', 'Gudang A')->first();
        $produk = Produk::first();

        $this->postJson('/api/v1/penjualan', [
            'pelanggan' => 'Invalid Tax Customer',
            'tgl_transaksi' => '2026-06-05',
            'syarat_pembayaran' => 'Cash',
            'gudang_id' => $gudangA->id,
            'tax_percentage' => 101,
            'items' => [
                ['produk_id' => $produk->id, 'kuantitas' => 1],
            ],
        ], ['Authorization' => "Bearer $token"])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tax_percentage']);
    }

    // === APPROVE ===

    public function test_approve_pending_to_approved(): void
    {
        $penjualan = $this->createPenjualan('salesa@hibiscusefsya.com');
        $token = $this->login('admin@hibiscusefsya.com');

        $response = $this->postJson("/api/v1/penjualan/{$penjualan->id}/approve", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Penjualan berhasil di-approve.'])
            ->assertJsonPath('data.status', 'Approved');
    }

    /** @covers-finding B01 API sales approval decrements stock */
    public function test_approve_decrements_saleable_stock_exactly_once(): void
    {
        [$penjualan, $stock] = $this->createPenjualanWithStock('salesa@hibiscusefsya.com', 10, 3);
        $token = $this->login('admin@hibiscusefsya.com');

        $this->postJson("/api/v1/penjualan/{$penjualan->id}/approve", [], ['Authorization' => "Bearer $token"])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'Approved');

        $stock->refresh();
        $this->assertSame(7, (int) $stock->stok);
        $this->assertSame(7, (int) $stock->stok_penjualan);

        $this->postJson("/api/v1/penjualan/{$penjualan->id}/approve", [], ['Authorization' => "Bearer $token"])
            ->assertStatus(422);

        $stock->refresh();
        $this->assertSame(7, (int) $stock->stok);
        $this->assertSame(7, (int) $stock->stok_penjualan);
    }

    public function test_approve_rejects_insufficient_stock_without_status_or_stock_changes(): void
    {
        [$penjualan, $stock] = $this->createPenjualanWithStock('salesa@hibiscusefsya.com', 2, 3);
        $token = $this->login('admin@hibiscusefsya.com');

        $response = $this->postJson("/api/v1/penjualan/{$penjualan->id}/approve", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Stok penjualan tidak cukup. Tersedia 2, diminta 3.');

        $this->assertSame('Pending', $penjualan->refresh()->status);
        $stock->refresh();
        $this->assertSame(2, (int) $stock->stok);
        $this->assertSame(2, (int) $stock->stok_penjualan);
    }

    public function test_approve_non_pending_fails(): void
    {
        $penjualan = $this->createPenjualan('salesa@hibiscusefsya.com');
        $penjualan->update(['status' => 'Approved']);
        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->postJson("/api/v1/penjualan/{$penjualan->id}/approve", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Hanya transaksi Pending yang bisa di-approve.']);
    }

    // === CANCEL ===

    public function test_cancel_own_transaction(): void
    {
        $penjualan = $this->createPenjualan('salesa@hibiscusefsya.com');
        $token = $this->login('salesa@hibiscusefsya.com');

        $response = $this->postJson("/api/v1/penjualan/{$penjualan->id}/cancel", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Penjualan berhasil dibatalkan.']);

        $this->assertDatabaseHas('penjualans', ['id' => $penjualan->id, 'status' => 'Canceled']);
    }

    public function test_cancel_pending_has_no_stock_effect(): void
    {
        [$penjualan, $stock] = $this->createPenjualanWithStock('salesa@hibiscusefsya.com', 10, 3);
        $token = $this->login('salesa@hibiscusefsya.com');

        $this->postJson("/api/v1/penjualan/{$penjualan->id}/cancel", [], ['Authorization' => "Bearer $token"])
            ->assertStatus(200);

        $this->assertSame('Canceled', $penjualan->refresh()->status);
        $stock->refresh();
        $this->assertSame(10, (int) $stock->stok);
        $this->assertSame(10, (int) $stock->stok_penjualan);
    }

    /** @covers-finding B02 API sales cancel restores stock */
    public function test_cancel_approved_restores_saleable_stock_exactly_once(): void
    {
        [$penjualan, $stock] = $this->createPenjualanWithStock('salesa@hibiscusefsya.com', 10, 3);
        $penjualan->update(['status' => 'Approved']);
        $stock->update(['stok' => 7, 'stok_penjualan' => 7]);
        $token = $this->login('salesa@hibiscusefsya.com');

        $this->postJson("/api/v1/penjualan/{$penjualan->id}/cancel", [], ['Authorization' => "Bearer $token"])
            ->assertStatus(200);

        $this->assertSame('Canceled', $penjualan->refresh()->status);
        $stock->refresh();
        $this->assertSame(10, (int) $stock->stok);
        $this->assertSame(10, (int) $stock->stok_penjualan);

        $this->postJson("/api/v1/penjualan/{$penjualan->id}/cancel", [], ['Authorization' => "Bearer $token"])
            ->assertStatus(422)
            ->assertJson(['message' => 'Transaksi sudah dibatalkan.']);

        $stock->refresh();
        $this->assertSame(10, (int) $stock->stok);
        $this->assertSame(10, (int) $stock->stok_penjualan);
    }

    public function test_cancel_lunas_restores_saleable_stock(): void
    {
        [$penjualan, $stock] = $this->createPenjualanWithStock('salesa@hibiscusefsya.com', 10, 3);
        $penjualan->update(['status' => 'Lunas']);
        $stock->update(['stok' => 7, 'stok_penjualan' => 7]);
        $token = $this->login('salesa@hibiscusefsya.com');

        $this->postJson("/api/v1/penjualan/{$penjualan->id}/cancel", [], ['Authorization' => "Bearer $token"])
            ->assertStatus(200);

        $this->assertSame('Canceled', $penjualan->refresh()->status);
        $stock->refresh();
        $this->assertSame(10, (int) $stock->stok);
        $this->assertSame(10, (int) $stock->stok_penjualan);
    }

    public function test_cancel_other_user_forbidden(): void
    {
        $penjualan = $this->createPenjualan('salesa@hibiscusefsya.com');
        $token = $this->login('salesb@hibiscusefsya.com');

        $response = $this->postJson("/api/v1/penjualan/{$penjualan->id}/cancel", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(403);
    }

    // === UNCANCEL ===

    public function test_uncancel_super_admin_only(): void
    {
        $penjualan = $this->createPenjualan('salesa@hibiscusefsya.com');
        $penjualan->update(['status' => 'Canceled']);
        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->postJson("/api/v1/penjualan/{$penjualan->id}/uncancel", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Transaksi berhasil di-uncancel. Status kembali ke Pending.'])
            ->assertJsonPath('data.status', 'Pending');
    }

    public function test_uncancel_non_super_admin_forbidden(): void
    {
        $penjualan = $this->createPenjualan('salesa@hibiscusefsya.com');
        $penjualan->update(['status' => 'Canceled']);
        $token = $this->login('admin@hibiscusefsya.com');

        $response = $this->postJson("/api/v1/penjualan/{$penjualan->id}/uncancel", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Hanya Super Admin yang dapat membatalkan pembatalan.']);
    }

    public function test_uncancel_returns_pending_without_reapplying_stock_then_approve_validates_stock(): void
    {
        [$penjualan, $stock] = $this->createPenjualanWithStock('salesa@hibiscusefsya.com', 10, 3);
        $penjualan->update(['status' => 'Approved']);
        $stock->update(['stok' => 7, 'stok_penjualan' => 7]);
        $salesToken = $this->login('salesa@hibiscusefsya.com');
        $superAdminToken = $this->login('superadmin@hibiscusefsya.com');
        $adminToken = $this->login('admin@hibiscusefsya.com');

        $this->postJson("/api/v1/penjualan/{$penjualan->id}/cancel", [], ['Authorization' => "Bearer $salesToken"])
            ->assertStatus(200);
        $this->postJson("/api/v1/penjualan/{$penjualan->id}/uncancel", [], ['Authorization' => "Bearer $superAdminToken"])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'Pending');

        $stock->refresh();
        $this->assertSame(10, (int) $stock->stok);
        $this->assertSame(10, (int) $stock->stok_penjualan);

        $stock->update(['stok' => 2, 'stok_penjualan' => 2]);

        $this->postJson("/api/v1/penjualan/{$penjualan->id}/approve", [], ['Authorization' => "Bearer $adminToken"])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Stok penjualan tidak cukup. Tersedia 2, diminta 3.');

        $this->assertSame('Pending', $penjualan->refresh()->status);
        $stock->refresh();
        $this->assertSame(2, (int) $stock->stok);
        $this->assertSame(2, (int) $stock->stok_penjualan);
    }

    // === MARK AS PAID ===

    public function test_mark_paid_approved_to_lunas(): void
    {
        $penjualan = $this->createPenjualan('salesa@hibiscusefsya.com');
        $penjualan->update(['status' => 'Approved', 'grand_total' => 50000]);
        $token = $this->login('admin@hibiscusefsya.com');

        $response = $this->postJson("/api/v1/penjualan/{$penjualan->id}/mark-paid", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Penjualan ditandai LUNAS dengan pembayaran cash.'])
            ->assertJsonPath('data.status', 'Lunas');

        $this->assertDatabaseHas('pembayarans', [
            'penjualan_id' => $penjualan->id,
            'type' => 'piutang',
            'metode_pembayaran' => 'Cash',
            'jumlah_bayar' => 50000,
            'status' => 'Approved',
        ]);
    }

    public function test_mark_paid_non_approved_fails(): void
    {
        $penjualan = $this->createPenjualan('salesa@hibiscusefsya.com');
        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->postJson("/api/v1/penjualan/{$penjualan->id}/mark-paid", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Hanya transaksi Approved yang bisa ditandai Lunas.']);
    }

    /** @covers-finding B10 Cash status not auto-Lunas */
    public function test_cash_sale_cannot_become_lunas_without_payment_record(): void
    {
        $penjualan = $this->createPenjualan('salesa@hibiscusefsya.com');
        $penjualan->update([
            'status' => 'Approved',
            'syarat_pembayaran' => 'Cash',
            'grand_total' => 50000,
        ]);
        $token = $this->login('admin@hibiscusefsya.com');

        $this->assertSame(0, Pembayaran::where('penjualan_id', $penjualan->id)->count());

        $response = $this->postJson("/api/v1/penjualan/{$penjualan->id}/mark-paid", [], ['Authorization' => "Bearer $token"]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'Lunas');

        $this->assertSame('Lunas', $penjualan->refresh()->status);
        $this->assertDatabaseHas('pembayarans', [
            'penjualan_id' => $penjualan->id,
            'type' => 'piutang',
            'metode_pembayaran' => 'Cash',
            'jumlah_bayar' => 50000,
            'status' => 'Approved',
        ]);
    }

    public function test_store_cash_sale_remains_pending_without_payment_record(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');
        $gudangA = Gudang::where('nama_gudang', 'Gudang A')->first();
        $produk = Produk::first();

        $response = $this->postJson('/api/v1/penjualan', [
            'pelanggan' => 'Cash Customer',
            'tgl_transaksi' => '2026-06-05',
            'syarat_pembayaran' => 'Cash',
            'gudang_id' => $gudangA->id,
            'tax_percentage' => 0,
            'diskon_akhir' => 0,
            'items' => [
                ['produk_id' => $produk->id, 'kuantitas' => 1, 'diskon' => 0],
            ],
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'Pending');

        $penjualanId = $response->json('data.id');
        $this->assertDatabaseHas('penjualans', [
            'id' => $penjualanId,
            'syarat_pembayaran' => 'Cash',
            'status' => 'Pending',
        ]);
        $this->assertSame(0, Pembayaran::where('penjualan_id', $penjualanId)->count());
    }

    /** @covers-finding B10 Cash status not auto-Lunas */
    public function test_approve_cash_sale_remains_approved_until_payment_record_is_approved(): void
    {
        [$penjualan] = $this->createPenjualanWithStock('salesa@hibiscusefsya.com', 10, 1);
        $penjualan->update([
            'syarat_pembayaran' => 'Cash',
            'grand_total' => 50000,
        ]);
        $token = $this->login('admin@hibiscusefsya.com');

        $this->postJson("/api/v1/penjualan/{$penjualan->id}/approve", [], ['Authorization' => "Bearer $token"])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'Approved');

        $this->assertSame('Approved', $penjualan->refresh()->status);
        $this->assertSame(0, Pembayaran::where('penjualan_id', $penjualan->id)->count());
    }

    // === UNMARK AS PAID ===

    public function test_unmark_paid_lunas_to_approved(): void
    {
        $penjualan = $this->createPenjualan('salesa@hibiscusefsya.com');
        $penjualan->update(['status' => 'Lunas']);
        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->postJson("/api/v1/penjualan/{$penjualan->id}/unmark-paid", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Status penjualan dikembalikan ke Approved.'])
            ->assertJsonPath('data.status', 'Approved');
    }

    public function test_unmark_paid_non_super_admin_forbidden(): void
    {
        $penjualan = $this->createPenjualan('salesa@hibiscusefsya.com');
        $penjualan->update(['status' => 'Lunas']);
        $token = $this->login('admin@hibiscusefsya.com');

        $response = $this->postJson("/api/v1/penjualan/{$penjualan->id}/unmark-paid", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Hanya Super Admin yang dapat melakukan ini.']);
    }

    // === UPDATE ===

    /** @covers-finding B11 Sales update guard */
    public function test_update_rejects_approved_sale_stock_and_money_changes_without_corruption(): void
    {
        [$penjualan, $stock] = $this->createPenjualanWithStock('salesa@hibiscusefsya.com', 10, 3);
        $penjualan->update(['status' => 'Approved', 'grand_total' => 50000]);
        $stock->update(['stok' => 7, 'stok_penjualan' => 7]);
        $originalItem = $penjualan->items()->firstOrFail();
        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->putJson("/api/v1/penjualan/{$penjualan->id}", $this->validUpdatePayload($penjualan, [
            'items' => [[
                'produk_id' => $originalItem->produk_id,
                'kuantitas' => 5,
                'diskon' => 0,
            ]],
            'diskon_akhir' => 1000,
        ]), ['Authorization' => "Bearer $token"]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Penjualan Approved/Lunas tidak dapat diedit untuk field stok atau nominal. Batalkan transaksi lalu buat penjualan pengganti.');

        $penjualan->refresh();
        $stock->refresh();
        $originalItem->refresh();
        $this->assertSame('Approved', $penjualan->status);
        $this->assertSame('50000.00', (string) $penjualan->grand_total);
        $this->assertSame(3, (int) $originalItem->kuantitas);
        $this->assertSame(7, (int) $stock->stok);
        $this->assertSame(7, (int) $stock->stok_penjualan);
    }

    public function test_update_rejects_lunas_sale_stock_and_money_changes_without_corrupting_payments(): void
    {
        [$penjualan, $stock] = $this->createPenjualanWithStock('salesa@hibiscusefsya.com', 10, 3);
        $penjualan->update(['status' => 'Lunas', 'grand_total' => 50000]);
        $stock->update(['stok' => 7, 'stok_penjualan' => 7]);
        $payment = Pembayaran::create([
            'user_id' => User::where('email', 'salesa@hibiscusefsya.com')->firstOrFail()->id,
            'approver_id' => User::where('email', 'admin@hibiscusefsya.com')->firstOrFail()->id,
            'gudang_id' => $penjualan->gudang_id,
            'type' => 'piutang',
            'penjualan_id' => $penjualan->id,
            'nomor' => 'PAY-TEST-'.rand(1000, 9999),
            'tgl_pembayaran' => now(),
            'metode_pembayaran' => 'Transfer',
            'jumlah_bayar' => 50000,
            'status' => 'Approved',
        ]);
        $originalItem = $penjualan->items()->firstOrFail();
        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->putJson("/api/v1/penjualan/{$penjualan->id}", $this->validUpdatePayload($penjualan, [
            'items' => [[
                'produk_id' => $originalItem->produk_id,
                'kuantitas' => 1,
                'diskon' => 0,
            ]],
            'syarat_pembayaran' => 'Net 30',
        ]), ['Authorization' => "Bearer $token"]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Penjualan Approved/Lunas tidak dapat diedit untuk field stok atau nominal. Batalkan transaksi lalu buat penjualan pengganti.');

        $penjualan->refresh();
        $stock->refresh();
        $payment->refresh();
        $originalItem->refresh();
        $this->assertSame('Lunas', $penjualan->status);
        $this->assertSame('50000.00', (string) $penjualan->grand_total);
        $this->assertSame('Approved', $payment->status);
        $this->assertSame('50000.00', (string) $payment->jumlah_bayar);
        $this->assertSame(3, (int) $originalItem->kuantitas);
        $this->assertSame(7, (int) $stock->stok);
        $this->assertSame(7, (int) $stock->stok_penjualan);
    }

    public function test_attachment_only_update_still_allows_owner_to_add_lampiran_on_lunas_sale(): void
    {
        $penjualan = $this->createPenjualan('salesa@hibiscusefsya.com');
        $penjualan->update(['status' => 'Lunas', 'grand_total' => 50000]);
        $token = $this->login('salesa@hibiscusefsya.com');

        $response = $this->post("/api/v1/penjualan/{$penjualan->id}", [
            '_method' => 'PUT',
            'lampiran' => [UploadedFile::fake()->image('bukti.jpg')],
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Lampiran berhasil ditambahkan.')
            ->assertJsonPath('data.status', 'Lunas')
            ->assertJsonPath('data.grand_total', '50000.00');

        $penjualan->refresh();
        $this->assertSame('Lunas', $penjualan->status);
        $this->assertSame('50000.00', (string) $penjualan->grand_total);
        $this->assertCount(1, $penjualan->lampiran_paths);

        foreach ($penjualan->lampiran_paths as $path) {
            @unlink(public_path('storage/'.$path));
        }
    }

    public function test_update_pending_sale_recomputes_totals_and_keeps_cash_pending_without_payment(): void
    {
        $penjualan = $this->createPenjualan('salesa@hibiscusefsya.com');
        $item = $penjualan->items()->firstOrFail();
        $item->produk->update(['harga' => 10000, 'harga_grosir' => 9000]);
        GudangProduk::updateOrCreate(
            ['gudang_id' => $penjualan->gudang_id, 'produk_id' => $item->produk_id],
            ['stok' => 10, 'stok_penjualan' => 10, 'stok_gratis' => 0, 'stok_sample' => 0]
        );
        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->putJson("/api/v1/penjualan/{$penjualan->id}", $this->validUpdatePayload($penjualan, [
            'syarat_pembayaran' => 'Cash',
            'tax_percentage' => 10,
            'diskon_akhir' => 1000,
            'grand_total' => 1,
            'items' => [[
                'produk_id' => $item->produk_id,
                'kuantitas' => 2,
                'harga_satuan' => 999999,
                'diskon' => 0,
                'jumlah_baris' => 1,
            ]],
        ]), ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'Pending')
            ->assertJsonPath('data.grand_total', '20900.00');

        $penjualan->refresh();
        $updatedItem = $penjualan->items()->firstOrFail();
        $this->assertSame('Pending', $penjualan->status);
        $this->assertSame('20900.00', (string) $penjualan->grand_total);
        $this->assertNull($penjualan->tgl_jatuh_tempo);
        $this->assertSame(2, (int) $updatedItem->kuantitas);
        $this->assertSame(10000.0, (float) $updatedItem->harga_satuan);
        $this->assertSame(20000.0, (float) $updatedItem->jumlah_baris);
    }

    public function test_filament_create_penjualan_recomputes_totals_before_save(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk(['harga' => 10000, 'harga_grosir' => 9000]);
        $this->transactionStock($gudang, $produk, ['stok' => 20, 'stok_penjualan' => 20, 'stok_gratis' => 0, 'stok_sample' => 0]);
        $superAdmin = $this->transactionUser('super_admin');
        $kontak = $this->transactionKontak($gudang, $superAdmin, ['nama' => 'Filament Tampered Customer']);

        Livewire::actingAs($superAdmin)
            ->test(CreatePenjualanPage::class)
            ->fillForm([
                'pelanggan' => $kontak->nama,
                'tgl_transaksi' => '2026-07-09',
                'syarat_pembayaran' => 'Cash',
                'gudang_id' => $gudang->id,
                'tipe_harga' => 'retail',
                'tax_percentage' => 10,
                'diskon_akhir' => 1000,
                'grand_total' => 1,
                'items' => [[
                    'produk_id' => $produk->id,
                    'kuantitas' => 2,
                    'harga_satuan' => 999999,
                    'unit' => 'Pcs',
                    'diskon' => 0,
                    'diskon_nominal' => 0,
                    'jumlah_baris' => 1,
                ]],
                'tag' => 'filament-test',
                'lampiran_paths' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $penjualan = Penjualan::where('pelanggan', 'Filament Tampered Customer')->firstOrFail();
        $item = $penjualan->items()->firstOrFail();
        $this->assertSame('20900.00', (string) $penjualan->grand_total);
        $this->assertSame('10000.00', (string) $item->harga_satuan);
        $this->assertSame(20000.0, (float) $item->jumlah_baris);
    }

    // === HELPERS ===

    private function createPenjualan(string $email): Penjualan
    {
        $user = User::where('email', $email)->first();
        $gudang = $user->gudang ?? Gudang::first();
        $produk = Produk::first();

        $penjualan = Penjualan::create([
            'user_id' => $user->id,
            'gudang_id' => $gudang->id,
            'nomor' => 'INV-TEST-'.rand(1000, 9999),
            'pelanggan' => 'Test Customer',
            'tgl_transaksi' => now(),
            'syarat_pembayaran' => 'Cash',
            'status' => 'Pending',
            'grand_total' => 50000,
            'tax_percentage' => 0,
            'diskon_akhir' => 0,
            'tipe_harga' => 'retail',
            'lampiran_paths' => [],
        ]);

        PenjualanItem::create([
            'penjualan_id' => $penjualan->id,
            'produk_id' => $produk->id,
            'kuantitas' => 1,
            'unit' => $produk->satuan,
            'harga_satuan' => $produk->harga,
            'jumlah_baris' => 50000,
        ]);

        return $penjualan;
    }

    private function createPenjualanWithStock(string $email, int $stockQuantity, int $saleQuantity): array
    {
        $penjualan = $this->createPenjualan($email);
        $item = $penjualan->items()->firstOrFail();
        $item->update(['kuantitas' => $saleQuantity]);

        $stock = GudangProduk::updateOrCreate(
            ['gudang_id' => $penjualan->gudang_id, 'produk_id' => $item->produk_id],
            [
                'stok' => $stockQuantity,
                'stok_penjualan' => $stockQuantity,
                'stok_gratis' => 0,
                'stok_sample' => 0,
            ]
        );

        return [$penjualan->refresh(), $stock->refresh()];
    }

    private function validUpdatePayload(Penjualan $penjualan, array $overrides = []): array
    {
        $item = $penjualan->items()->firstOrFail();

        return array_replace_recursive([
            'pelanggan' => $penjualan->pelanggan,
            'tgl_transaksi' => $penjualan->tgl_transaksi->format('Y-m-d'),
            'syarat_pembayaran' => $penjualan->syarat_pembayaran,
            'gudang_id' => $penjualan->gudang_id,
            'tipe_harga' => $penjualan->tipe_harga ?? 'retail',
            'tax_percentage' => 0,
            'diskon_akhir' => 0,
            'items' => [[
                'produk_id' => $item->produk_id,
                'kuantitas' => (int) $item->kuantitas,
                'diskon' => 0,
            ]],
        ], $overrides);
    }

    private function login(string $email): string
    {
        return $this->postJson('/api/v1/login', [
            'email' => $email, 'password' => 'password123',
        ])->json('token');
    }
}
