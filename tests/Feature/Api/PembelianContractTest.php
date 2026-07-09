<?php

namespace Tests\Feature\Api;

use App\Filament\Resources\Pembelians\Pages\CreatePembelian as CreatePembelianPage;
use App\Models\Pembelian;
use App\Models\PembelianItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PembelianContractTest extends TestCase
{
    use RefreshDatabase;

    /** @covers-finding B04 Filament form totals not trusted (pembelian) */
    public function test_store_recomputes_totals_and_ignores_tampered_client_money_fields(): void
    {
        $gudang = $this->transactionGudang();
        $creator = $this->transactionUser('user', $gudang);
        $produk = $this->transactionProduk(['harga' => 10000]);

        $response = $this->postJson('/api/v1/pembelian', [
            'tgl_transaksi' => '2026-07-09',
            'syarat_pembayaran' => 'Net 14',
            'urgensi' => 'Normal',
            'gudang_id' => $gudang->id,
            'tax_percentage' => 10,
            'diskon_akhir' => 1000,
            'biaya_pengiriman' => 5000,
            'grand_total' => 1,
            'items' => [[
                'produk_id' => $produk->id,
                'deskripsi' => 'Tampered line',
                'kuantitas' => 2,
                'unit' => 'Pcs',
                'harga_satuan' => 10000,
                'diskon' => 10,
                'jumlah_baris' => 1,
            ]],
        ], $this->authHeaderFor($creator));

        $response->assertCreated()
            ->assertJsonPath('data.grand_total', '23700.00')
            ->assertJsonPath('data.items.0.jumlah_baris', 18000);
    }

    /** @covers-finding B16 Tax/discount validation (pembelian) */
    public function test_store_rejects_tax_above_100(): void
    {
        $gudang = $this->transactionGudang();
        $creator = $this->transactionUser('user', $gudang);
        $produk = $this->transactionProduk(['harga' => 10000]);

        $this->postJson('/api/v1/pembelian', [
            'tgl_transaksi' => '2026-07-09',
            'syarat_pembayaran' => 'Net 14',
            'urgensi' => 'Normal',
            'gudang_id' => $gudang->id,
            'tax_percentage' => 101,
            'items' => [
                ['produk_id' => $produk->id, 'kuantitas' => 1, 'harga_satuan' => 10000],
            ],
        ], $this->authHeaderFor($creator))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tax_percentage']);
    }

    /** @covers-finding B08 Pembelian update stub fixed */
    public function test_update_persists_header_items_and_recomputed_total(): void
    {
        $gudang = $this->transactionGudang();
        $creator = $this->transactionUser('user', $gudang);
        $superAdmin = $this->transactionUser('super_admin');
        $oldProduk = $this->transactionProduk(['harga' => 15000]);
        $newProdukA = $this->transactionProduk(['harga' => 10000]);
        $newProdukB = $this->transactionProduk(['harga' => 30000]);
        $pembelian = $this->transactionPembelian($creator, $gudang, [
            'grand_total' => 30000,
            'tax_percentage' => 0,
            'diskon_akhir' => 0,
            'biaya_pengiriman' => 0,
        ], [
            ['produk' => $oldProduk, 'kuantitas' => 2, 'harga_satuan' => 15000],
        ]);

        $response = $this->putJson("/api/v1/pembelian/{$pembelian->id}", [
            'tgl_transaksi' => '2026-07-09',
            'syarat_pembayaran' => 'Net 14',
            'urgensi' => 'Urgent',
            'gudang_id' => $gudang->id,
            'tax_percentage' => 10,
            'diskon_akhir' => 1000,
            'biaya_pengiriman' => 5000,
            'grand_total' => 1,
            'tahun_anggaran' => '2026',
            'tag' => 'restock',
            'koordinat' => '-6.2,106.8',
            'memo' => 'Updated via API contract test',
            'items' => [
                [
                    'produk_id' => $newProdukA->id,
                    'deskripsi' => 'Updated line A',
                    'kuantitas' => 2,
                    'unit' => 'Pcs',
                    'harga_satuan' => 10000,
                    'diskon' => 10,
                    'jumlah_baris' => 1,
                ],
                [
                    'produk_id' => $newProdukB->id,
                    'deskripsi' => 'Updated line B',
                    'kuantitas' => 1,
                    'unit' => 'Box',
                    'harga_satuan' => 30000,
                    'diskon' => 0,
                ],
            ],
        ], $this->authHeaderFor($superAdmin));

        $response->assertOk()
            ->assertJsonPath('data.id', $pembelian->id)
            ->assertJsonPath('data.syarat_pembayaran', 'Net 14')
            ->assertJsonPath('data.grand_total', '56700.00');

        $pembelian->refresh()->load('items');
        $this->assertSame('Net 14', $pembelian->syarat_pembayaran);
        $this->assertSame('Urgent', $pembelian->urgensi);
        $this->assertSame('2026', $pembelian->tahun_anggaran);
        $this->assertSame('restock', $pembelian->tag);
        $this->assertSame('-6.2,106.8', $pembelian->koordinat);
        $this->assertSame('Updated via API contract test', $pembelian->memo);
        $this->assertSame('10.00', $pembelian->tax_percentage);
        $this->assertSame('1000.00', $pembelian->diskon_akhir);
        $this->assertSame('5000.00', $pembelian->biaya_pengiriman);
        $this->assertSame('56700.00', $pembelian->grand_total);

        $this->assertCount(2, $pembelian->items);
        $this->assertDatabaseMissing('pembelian_items', [
            'pembelian_id' => $pembelian->id,
            'produk_id' => $oldProduk->id,
        ]);
        $this->assertDatabaseHas('pembelian_items', [
            'pembelian_id' => $pembelian->id,
            'produk_id' => $newProdukA->id,
            'kuantitas' => 2,
            'harga_satuan' => 10000,
            'diskon' => 10,
            'jumlah_baris' => 18000,
        ]);
        $this->assertDatabaseHas('pembelian_items', [
            'pembelian_id' => $pembelian->id,
            'produk_id' => $newProdukB->id,
            'kuantitas' => 1,
            'harga_satuan' => 30000,
            'diskon' => 0,
            'jumlah_baris' => 30000,
        ]);
    }

    public function test_update_rejects_item_discount_above_100_without_persisting(): void
    {
        $this->assertInvalidUpdateItemDiscountIsRejected(101);
    }

    public function test_update_rejects_negative_item_discount_without_persisting(): void
    {
        $this->assertInvalidUpdateItemDiscountIsRejected(-1);
    }

    public function test_update_rejects_final_discount_greater_than_subtotal_without_persisting(): void
    {
        $gudang = $this->transactionGudang();
        $creator = $this->transactionUser('user', $gudang);
        $superAdmin = $this->transactionUser('super_admin');
        $produk = $this->transactionProduk(['harga' => 10000]);
        $pembelian = $this->transactionPembelian($creator, $gudang, [
            'grand_total' => 10000,
            'diskon_akhir' => 0,
        ], [
            ['produk' => $produk, 'kuantitas' => 1, 'harga_satuan' => 10000],
        ]);

        $response = $this->putJson("/api/v1/pembelian/{$pembelian->id}", [
            'tgl_transaksi' => '2026-07-09',
            'syarat_pembayaran' => 'Net 30',
            'urgensi' => 'Normal',
            'gudang_id' => $gudang->id,
            'tax_percentage' => 0,
            'diskon_akhir' => 10001,
            'items' => [
                ['produk_id' => $produk->id, 'kuantitas' => 1, 'harga_satuan' => 10000, 'diskon' => 0],
            ],
        ], $this->authHeaderFor($superAdmin));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['diskon_akhir']);

        $this->assertSame('10000.00', $pembelian->refresh()->grand_total);
        $this->assertSame('0.00', $pembelian->diskon_akhir);
    }

    public function test_full_update_requires_super_admin(): void
    {
        $gudang = $this->transactionGudang();
        $creator = $this->transactionUser('user', $gudang);
        $produk = $this->transactionProduk(['harga' => 10000]);
        $pembelian = $this->transactionPembelian($creator, $gudang, [], [
            ['produk' => $produk, 'kuantitas' => 1, 'harga_satuan' => 10000],
        ]);

        $response = $this->putJson("/api/v1/pembelian/{$pembelian->id}", [
            'tgl_transaksi' => '2026-07-09',
            'syarat_pembayaran' => 'Net 30',
            'urgensi' => 'Normal',
            'gudang_id' => $gudang->id,
            'tax_percentage' => 0,
            'items' => [
                ['produk_id' => $produk->id, 'kuantitas' => 1, 'harga_satuan' => 10000, 'diskon' => 0],
            ],
        ], $this->authHeaderFor($creator));

        $response->assertForbidden()
            ->assertJsonPath('message', 'Hanya Super Admin yang dapat mengubah data pembelian.');
    }

    public function test_filament_create_pembelian_recomputes_totals_before_save(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk(['harga' => 10000]);
        $superAdmin = $this->transactionUser('super_admin');

        Livewire::actingAs($superAdmin)
            ->test(CreatePembelianPage::class)
            ->fillForm([
                'tgl_transaksi' => '2026-07-09',
                'syarat_pembayaran' => 'Net 14',
                'gudang_id' => $gudang->id,
                'tipe_harga' => 'retail',
                'urgensi' => 'Sedang',
                'tax_percentage' => 10,
                'diskon_akhir' => 1000,
                'biaya_pengiriman' => 5000,
                'grand_total' => 1,
                'items' => [[
                    'produk_id' => $produk->id,
                    'kuantitas' => 2,
                    'unit' => 'Pcs',
                    'harga_satuan' => 10000,
                    'diskon' => 10,
                    'jumlah_baris' => 1,
                    'deskripsi' => 'Filament tampered purchase line',
                ]],
                'tag' => 'filament-purchase-test',
                'lampiran_paths' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $pembelian = Pembelian::where('tag', 'filament-purchase-test')->firstOrFail();
        $item = $pembelian->items()->firstOrFail();
        $this->assertSame('23700.00', (string) $pembelian->grand_total);
        $this->assertSame(18000.0, (float) $item->jumlah_baris);
    }

    private function assertInvalidUpdateItemDiscountIsRejected(int $discount): void
    {
        $gudang = $this->transactionGudang();
        $creator = $this->transactionUser('user', $gudang);
        $superAdmin = $this->transactionUser('super_admin');
        $produk = $this->transactionProduk(['harga' => 10000]);
        $pembelian = $this->transactionPembelian($creator, $gudang, [
            'grand_total' => 10000,
        ], [
            ['produk' => $produk, 'kuantitas' => 1, 'harga_satuan' => 10000, 'diskon' => 0],
        ]);
        $originalItemIds = $pembelian->items->pluck('id')->all();

        $response = $this->putJson("/api/v1/pembelian/{$pembelian->id}", [
            'tgl_transaksi' => '2026-07-09',
            'syarat_pembayaran' => 'Net 30',
            'urgensi' => 'Normal',
            'gudang_id' => $gudang->id,
            'tax_percentage' => 0,
            'diskon_akhir' => 0,
            'items' => [
                ['produk_id' => $produk->id, 'kuantitas' => 1, 'harga_satuan' => 10000, 'diskon' => $discount],
            ],
        ], $this->authHeaderFor($superAdmin));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.diskon']);

        $this->assertSame('10000.00', $pembelian->refresh()->grand_total);
        $this->assertSame($originalItemIds, PembelianItem::where('pembelian_id', $pembelian->id)->pluck('id')->all());
    }
}
