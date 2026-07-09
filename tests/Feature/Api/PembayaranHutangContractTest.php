<?php

namespace Tests\Feature\Api;

use App\Models\Pembayaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\BuildsTransactionFixtures;
use Tests\TestCase;

class PembayaranHutangContractTest extends TestCase
{
    use BuildsTransactionFixtures;
    use RefreshDatabase;

    /** @covers-finding B07 Hutang payment schema */
    public function test_fresh_schema_allows_hutang_payment_without_penjualan_id(): void
    {
        $gudang = $this->transactionGudang();
        $user = $this->transactionUser('user', $gudang);
        $pembelian = $this->transactionPembelian($user, $gudang, [
            'status' => 'Approved',
            'grand_total' => 150000,
        ]);

        $columns = Schema::getColumns('pembayarans');
        $penjualanColumn = collect($columns)->firstWhere('name', 'penjualan_id');

        $this->assertNotNull($penjualanColumn);
        $this->assertTrue($penjualanColumn['nullable']);

        $hutang = $this->transactionPembayaranHutang($pembelian, $user, [
            'jumlah_bayar' => 50000,
        ]);

        $this->assertDatabaseHas('pembayarans', [
            'id' => $hutang->id,
            'type' => 'hutang',
            'penjualan_id' => null,
            'pembelian_id' => $pembelian->id,
        ]);
    }

    public function test_store_requires_pembelian_relation(): void
    {
        $gudang = $this->transactionGudang();
        $user = $this->transactionUser('user', $gudang);

        $response = $this->postJson('/api/v1/pembayaran-hutang', [
            'tgl_pembayaran' => now()->toDateString(),
            'metode_pembayaran' => 'Transfer',
            'jumlah_bayar' => 10000,
        ], $this->authHeaderFor($user));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pembelian_id']);

        $this->assertSame(0, Pembayaran::count());
    }

    public function test_approve_exact_remaining_hutang_payment_marks_purchase_lunas(): void
    {
        $gudang = $this->transactionGudang();
        $user = $this->transactionUser('user', $gudang);
        $admin = $this->transactionUser('admin', $gudang);
        $pembelian = $this->transactionPembelian($user, $gudang, [
            'status' => 'Approved',
            'grand_total' => 150000,
        ]);
        $this->transactionPembayaranHutang($pembelian, $user, [
            'jumlah_bayar' => 100000,
            'status' => 'Approved',
        ]);
        $pembayaran = $this->transactionPembayaranHutang($pembelian, $user, [
            'jumlah_bayar' => 50000,
            'status' => 'Pending',
        ]);

        $response = $this->postJson("/api/v1/pembayaran/{$pembayaran->id}/approve", [], $this->authHeaderFor($admin));

        $response->assertOk()
            ->assertJson(['message' => 'Pembayaran berhasil di-approve.'])
            ->assertJsonPath('data.status', 'Approved');

        $this->assertDatabaseHas('pembayarans', [
            'id' => $pembayaran->id,
            'type' => 'hutang',
            'status' => 'Approved',
            'approver_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('pembelians', [
            'id' => $pembelian->id,
            'status' => 'Lunas',
        ]);
    }

    public function test_cancel_approved_hutang_payment_reverts_lunas_purchase_to_approved_when_remaining_payments_do_not_cover_total(): void
    {
        $gudang = $this->transactionGudang();
        $user = $this->transactionUser('user', $gudang);
        $superAdmin = $this->transactionUser('super_admin', $gudang);
        $pembelian = $this->transactionPembelian($user, $gudang, [
            'status' => 'Lunas',
            'grand_total' => 150000,
        ]);
        $pembayaran = $this->transactionPembayaranHutang($pembelian, $user, [
            'jumlah_bayar' => 50000,
            'status' => 'Approved',
        ]);
        $this->transactionPembayaranHutang($pembelian, $user, [
            'jumlah_bayar' => 100000,
            'status' => 'Approved',
        ]);

        $response = $this->postJson("/api/v1/pembayaran/{$pembayaran->id}/cancel", [], $this->authHeaderFor($superAdmin));

        $response->assertOk()
            ->assertJson(['message' => 'Pembayaran berhasil dibatalkan.']);

        $this->assertDatabaseHas('pembayarans', [
            'id' => $pembayaran->id,
            'type' => 'hutang',
            'status' => 'Canceled',
        ]);
        $this->assertDatabaseHas('pembelians', [
            'id' => $pembelian->id,
            'status' => 'Approved',
        ]);
    }

    public function test_uncancel_hutang_payment_returns_pending_and_recomputes_purchase_to_approved(): void
    {
        $gudang = $this->transactionGudang();
        $user = $this->transactionUser('user', $gudang);
        $superAdmin = $this->transactionUser('super_admin', $gudang);
        $pembelian = $this->transactionPembelian($user, $gudang, [
            'status' => 'Lunas',
            'grand_total' => 150000,
        ]);
        $pembayaran = $this->transactionPembayaranHutang($pembelian, $user, [
            'jumlah_bayar' => 150000,
            'status' => 'Canceled',
            'approver_id' => $superAdmin->id,
        ]);

        $response = $this->postJson("/api/v1/pembayaran/{$pembayaran->id}/uncancel", [], $this->authHeaderFor($superAdmin));

        $response->assertOk()
            ->assertJsonPath('data.status', 'Pending');

        $this->assertDatabaseHas('pembayarans', [
            'id' => $pembayaran->id,
            'type' => 'hutang',
            'status' => 'Pending',
            'approver_id' => null,
        ]);
        $this->assertDatabaseHas('pembelians', [
            'id' => $pembelian->id,
            'status' => 'Approved',
        ]);
    }

    public function test_store_rejects_hutang_payment_above_remaining_purchase_balance(): void
    {
        $gudang = $this->transactionGudang();
        $user = $this->transactionUser('user', $gudang);
        $pembelian = $this->transactionPembelian($user, $gudang, [
            'status' => 'Approved',
            'grand_total' => 150000,
        ]);
        $this->transactionPembayaranHutang($pembelian, $user, [
            'jumlah_bayar' => 100000,
            'status' => 'Approved',
        ]);

        $response = $this->postJson('/api/v1/pembayaran-hutang', [
            'pembelian_id' => $pembelian->id,
            'tgl_pembayaran' => now()->toDateString(),
            'metode_pembayaran' => 'Transfer',
            'jumlah_bayar' => 50001,
        ], $this->authHeaderFor($user));

        $response->assertStatus(422)
            ->assertJson(['message' => 'Jumlah bayar melebihi sisa tagihan.']);

        $this->assertSame(1, Pembayaran::count());
    }

    /** @covers-finding B05 Payment overpayment guard (hutang) */
    public function test_approve_rejects_hutang_payment_that_would_overpay_purchase(): void
    {
        $gudang = $this->transactionGudang();
        $user = $this->transactionUser('user', $gudang);
        $admin = $this->transactionUser('admin', $gudang);
        $pembelian = $this->transactionPembelian($user, $gudang, [
            'status' => 'Approved',
            'grand_total' => 150000,
        ]);
        $this->transactionPembayaranHutang($pembelian, $user, [
            'jumlah_bayar' => 125000,
            'status' => 'Approved',
        ]);
        $pembayaran = $this->transactionPembayaranHutang($pembelian, $user, [
            'jumlah_bayar' => 25001,
            'status' => 'Pending',
        ]);

        $response = $this->postJson("/api/v1/pembayaran/{$pembayaran->id}/approve", [], $this->authHeaderFor($admin));

        $response->assertStatus(422)
            ->assertJson(['message' => 'Jumlah bayar melebihi sisa tagihan.']);

        $this->assertDatabaseHas('pembayarans', [
            'id' => $pembayaran->id,
            'type' => 'hutang',
            'status' => 'Pending',
            'approver_id' => null,
        ]);
        $this->assertDatabaseHas('pembelians', [
            'id' => $pembelian->id,
            'status' => 'Approved',
        ]);
    }
}
