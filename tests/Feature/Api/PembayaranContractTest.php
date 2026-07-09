<?php

namespace Tests\Feature\Api;

use App\Models\Pembayaran;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\BuildsTransactionFixtures;
use Tests\TestCase;

class PembayaranContractTest extends TestCase
{
    use BuildsTransactionFixtures;
    use RefreshDatabase;

    public function test_pembayaran_hutang_fresh_schema_allows_piutang_and_hutang_payment_rows(): void
    {
        $gudang = $this->transactionGudang();
        $user = $this->transactionUser('user', $gudang);
        $penjualan = $this->transactionPenjualan($user, $gudang, [
            'status' => 'Approved',
            'grand_total' => 100000,
        ]);
        $pembelian = $this->transactionPembelian($user, $gudang, [
            'status' => 'Approved',
            'grand_total' => 150000,
        ]);

        $columns = Schema::getColumns('pembayarans');
        $penjualanColumn = collect($columns)->firstWhere('name', 'penjualan_id');

        $this->assertNotNull($penjualanColumn);
        $this->assertTrue($penjualanColumn['nullable']);

        $piutang = $this->transactionPembayaran($penjualan, $user, [
            'jumlah_bayar' => 25000,
        ]);
        $hutang = $this->transactionPembayaranHutang($pembelian, $user, [
            'jumlah_bayar' => 50000,
        ]);

        $this->assertDatabaseHas('pembayarans', [
            'id' => $piutang->id,
            'type' => 'piutang',
            'penjualan_id' => $penjualan->id,
            'pembelian_id' => null,
        ]);
        $this->assertDatabaseHas('pembayarans', [
            'id' => $hutang->id,
            'type' => 'hutang',
            'penjualan_id' => null,
            'pembelian_id' => $pembelian->id,
        ]);
    }

    public function test_payment_model_rejects_missing_type_specific_relation(): void
    {
        $gudang = $this->transactionGudang();
        $user = $this->transactionUser('user', $gudang);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Pembayaran hutang wajib memiliki pembelian_id.');

        Pembayaran::create([
            'user_id' => $user->id,
            'gudang_id' => $gudang->id,
            'type' => 'hutang',
            'nomor' => 'BAYH-INVALID-RELATION',
            'tgl_pembayaran' => now()->toDateString(),
            'metode_pembayaran' => 'Transfer',
            'jumlah_bayar' => 10000,
            'status' => 'Pending',
            'lampiran_paths' => [],
        ]);
    }

    public function test_pembayaran_hutang_store_requires_pembelian_relation(): void
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

    public function test_store_piutang_requires_penjualan_relation(): void
    {
        $gudang = $this->transactionGudang();
        $user = $this->transactionUser('user', $gudang);

        $response = $this->postJson('/api/v1/pembayaran', [
            'tgl_pembayaran' => now()->toDateString(),
            'metode_pembayaran' => 'Transfer',
            'jumlah_bayar' => 10000,
        ], $this->authHeaderFor($user));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['penjualan_id']);

        $this->assertSame(0, Pembayaran::count());
    }

    public function test_store_rejects_payment_for_pending_and_canceled_sales(): void
    {
        $gudang = $this->transactionGudang();
        $user = $this->transactionUser('user', $gudang);

        foreach (['Pending', 'Canceled'] as $status) {
            $penjualan = $this->transactionPenjualan($user, $gudang, [
                'status' => $status,
                'grand_total' => 100000,
            ]);

            $response = $this->postJson('/api/v1/pembayaran', [
                'penjualan_id' => $penjualan->id,
                'tgl_pembayaran' => now()->toDateString(),
                'metode_pembayaran' => 'Transfer',
                'jumlah_bayar' => 10000,
            ], $this->authHeaderFor($user));

            $response->assertStatus(422)
                ->assertJson(['message' => 'Pembayaran hanya dapat dibuat untuk penjualan yang sudah Approved dan belum lunas.']);
        }

        $this->assertSame(0, Pembayaran::count());
    }

    /** @covers-finding B05 Payment overpayment guard (piutang) */
    public function test_store_rejects_payment_above_remaining_balance(): void
    {
        $gudang = $this->transactionGudang();
        $user = $this->transactionUser('user', $gudang);
        $penjualan = $this->transactionPenjualan($user, $gudang, [
            'status' => 'Approved',
            'grand_total' => 100000,
        ]);
        $this->transactionPembayaran($penjualan, $user, [
            'jumlah_bayar' => 60000,
            'status' => 'Approved',
        ]);

        $response = $this->postJson('/api/v1/pembayaran', [
            'penjualan_id' => $penjualan->id,
            'tgl_pembayaran' => now()->toDateString(),
            'metode_pembayaran' => 'Transfer',
            'jumlah_bayar' => 40001,
        ], $this->authHeaderFor($user));

        $response->assertStatus(422)
            ->assertJson(['message' => 'Jumlah bayar melebihi sisa tagihan.']);

        $this->assertSame(1, Pembayaran::count());
    }

    public function test_approve_exact_remaining_payment_marks_sale_lunas(): void
    {
        $gudang = $this->transactionGudang();
        $user = $this->transactionUser('user', $gudang);
        $admin = $this->transactionUser('admin', $gudang);
        $penjualan = $this->transactionPenjualan($user, $gudang, [
            'status' => 'Approved',
            'grand_total' => 100000,
        ]);
        $this->transactionPembayaran($penjualan, $user, [
            'jumlah_bayar' => 60000,
            'status' => 'Approved',
        ]);
        $pembayaran = $this->transactionPembayaran($penjualan, $user, [
            'jumlah_bayar' => 40000,
            'status' => 'Pending',
        ]);

        $response = $this->postJson("/api/v1/pembayaran/{$pembayaran->id}/approve", [], $this->authHeaderFor($admin));

        $response->assertOk()
            ->assertJson(['message' => 'Pembayaran berhasil di-approve.'])
            ->assertJsonPath('data.status', 'Approved');

        $this->assertDatabaseHas('penjualans', [
            'id' => $penjualan->id,
            'status' => 'Lunas',
        ]);
    }

    public function test_approve_rejects_pending_payment_that_would_overpay_after_other_approval(): void
    {
        $gudang = $this->transactionGudang();
        $user = $this->transactionUser('user', $gudang);
        $admin = $this->transactionUser('admin', $gudang);
        $penjualan = $this->transactionPenjualan($user, $gudang, [
            'status' => 'Approved',
            'grand_total' => 100000,
        ]);
        $pembayaran = $this->transactionPembayaran($penjualan, $user, [
            'jumlah_bayar' => 80000,
            'status' => 'Pending',
        ]);
        $this->transactionPembayaran($penjualan, $user, [
            'jumlah_bayar' => 30000,
            'status' => 'Approved',
        ]);

        $response = $this->postJson("/api/v1/pembayaran/{$pembayaran->id}/approve", [], $this->authHeaderFor($admin));

        $response->assertStatus(422)
            ->assertJson(['message' => 'Jumlah bayar melebihi sisa tagihan.']);

        $this->assertDatabaseHas('pembayarans', [
            'id' => $pembayaran->id,
            'status' => 'Pending',
            'approver_id' => null,
        ]);
        $this->assertDatabaseHas('penjualans', [
            'id' => $penjualan->id,
            'status' => 'Approved',
        ]);
    }

    public function test_cancel_approved_payment_keeps_sale_lunas_when_remaining_approved_payments_cover_total(): void
    {
        $gudang = $this->transactionGudang();
        $user = $this->transactionUser('user', $gudang);
        $superAdmin = $this->transactionUser('super_admin', $gudang);
        $penjualan = $this->transactionPenjualan($user, $gudang, [
            'status' => 'Lunas',
            'grand_total' => 100000,
        ]);
        $pembayaran = $this->transactionPembayaran($penjualan, $user, [
            'jumlah_bayar' => 25000,
            'status' => 'Approved',
        ]);
        $this->transactionPembayaran($penjualan, $user, [
            'jumlah_bayar' => 100000,
            'status' => 'Approved',
        ]);

        $response = $this->postJson("/api/v1/pembayaran/{$pembayaran->id}/cancel", [], $this->authHeaderFor($superAdmin));

        $response->assertOk()
            ->assertJson(['message' => 'Pembayaran berhasil dibatalkan.']);

        $this->assertDatabaseHas('pembayarans', [
            'id' => $pembayaran->id,
            'status' => 'Canceled',
        ]);
        $this->assertDatabaseHas('penjualans', [
            'id' => $penjualan->id,
            'status' => 'Lunas',
        ]);
    }

    public function test_cancel_approved_payment_reverts_lunas_sale_to_approved_when_remaining_payments_do_not_cover_total(): void
    {
        $gudang = $this->transactionGudang();
        $user = $this->transactionUser('user', $gudang);
        $superAdmin = $this->transactionUser('super_admin', $gudang);
        $penjualan = $this->transactionPenjualan($user, $gudang, [
            'status' => 'Lunas',
            'grand_total' => 100000,
        ]);
        $pembayaran = $this->transactionPembayaran($penjualan, $user, [
            'jumlah_bayar' => 40000,
            'status' => 'Approved',
        ]);
        $this->transactionPembayaran($penjualan, $user, [
            'jumlah_bayar' => 60000,
            'status' => 'Approved',
        ]);

        $response = $this->postJson("/api/v1/pembayaran/{$pembayaran->id}/cancel", [], $this->authHeaderFor($superAdmin));

        $response->assertOk()
            ->assertJson(['message' => 'Pembayaran berhasil dibatalkan.']);

        $this->assertDatabaseHas('pembayarans', [
            'id' => $pembayaran->id,
            'status' => 'Canceled',
        ]);
        $this->assertDatabaseHas('penjualans', [
            'id' => $penjualan->id,
            'status' => 'Approved',
        ]);
    }

    public function test_uncancel_returns_payment_to_pending_without_treating_it_as_approved_money(): void
    {
        $gudang = $this->transactionGudang();
        $user = $this->transactionUser('user', $gudang);
        $superAdmin = $this->transactionUser('super_admin', $gudang);
        $penjualan = $this->transactionPenjualan($user, $gudang, [
            'status' => 'Lunas',
            'grand_total' => 100000,
        ]);
        $pembayaran = $this->transactionPembayaran($penjualan, $user, [
            'jumlah_bayar' => 100000,
            'status' => 'Canceled',
            'approver_id' => $superAdmin->id,
        ]);

        $response = $this->postJson("/api/v1/pembayaran/{$pembayaran->id}/uncancel", [], $this->authHeaderFor($superAdmin));

        $response->assertOk()
            ->assertJsonPath('data.status', 'Pending');

        $this->assertDatabaseHas('pembayarans', [
            'id' => $pembayaran->id,
            'status' => 'Pending',
            'approver_id' => null,
        ]);
        $this->assertDatabaseHas('penjualans', [
            'id' => $penjualan->id,
            'status' => 'Lunas',
        ]);
    }
}
