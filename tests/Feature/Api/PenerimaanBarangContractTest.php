<?php

namespace Tests\Feature\Api;

use App\Filament\Resources\PenerimaanBarangs\Pages\EditPenerimaanBarang;
use App\Models\GudangProduk;
use App\Models\PenerimaanBarang;
use App\Models\StokLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Throwable;

class PenerimaanBarangContractTest extends TestCase
{
    use RefreshDatabase;

    /** @covers-finding B14 Penerimaan stock locked */
    public function test_super_admin_auto_approved_store_creates_one_stock_log_for_committed_stock_addition(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $superAdmin = $this->transactionUser('super_admin');
        $pembelian = $this->transactionPembelian($superAdmin, $gudang, [
            'status' => 'Approved',
        ], [
            ['produk' => $produk, 'kuantitas' => 5, 'harga_satuan' => 15000],
        ]);

        $response = $this->postJson('/api/v1/penerimaan-barang', [
            'gudang_id' => $gudang->id,
            'pembelian_id' => $pembelian->id,
            'tgl_penerimaan' => now()->toDateString(),
            'no_surat_jalan' => 'SJ-TEST-001',
            'items' => [
                [
                    'produk_id' => $produk->id,
                    'qty_diterima' => 3,
                    'qty_reject' => 0,
                    'tipe_stok' => 'penjualan',
                ],
            ],
        ], $this->authHeaderFor($superAdmin));

        $response->assertCreated()
            ->assertJson(['message' => 'Penerimaan barang berhasil dibuat.'])
            ->assertJsonPath('data.status', 'Approved');

        $penerimaanId = $response->json('data.id');
        $penerimaanNomor = $response->json('data.nomor');

        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 3,
            'stok_penjualan' => 3,
            'stok_gratis' => 0,
            'stok_sample' => 0,
        ]);

        $this->assertSame(
            1,
            StokLog::query()
                ->where('gudang_id', $gudang->id)
                ->where('produk_id', $produk->id)
                ->count(),
        );

        $this->assertDatabaseHas('stok_logs', [
            'produk_id' => $produk->id,
            'gudang_id' => $gudang->id,
            'user_id' => $superAdmin->id,
            'stok_sebelum' => 0,
            'stok_sesudah' => 3,
            'selisih' => 3,
            'keterangan' => "Penerimaan Approve #{$penerimaanId} ({$penerimaanNomor})",
        ]);
    }

    public function test_rejected_store_does_not_create_receiving_stock_or_stock_log(): void
    {
        $requestGudang = $this->transactionGudang();
        $purchaseGudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $superAdmin = $this->transactionUser('super_admin');
        $pembelian = $this->transactionPembelian($superAdmin, $purchaseGudang, [
            'status' => 'Approved',
        ], [
            ['produk' => $produk, 'kuantitas' => 5, 'harga_satuan' => 15000],
        ]);

        $response = $this->postJson('/api/v1/penerimaan-barang', [
            'gudang_id' => $requestGudang->id,
            'pembelian_id' => $pembelian->id,
            'tgl_penerimaan' => now()->toDateString(),
            'items' => [
                [
                    'produk_id' => $produk->id,
                    'qty_diterima' => 3,
                    'qty_reject' => 0,
                    'tipe_stok' => 'penjualan',
                ],
            ],
        ], $this->authHeaderFor($superAdmin));

        $response->assertStatus(422)
            ->assertJson(['message' => 'Pembelian tidak valid untuk gudang yang dipilih.']);

        $this->assertSame(0, PenerimaanBarang::count());
        $this->assertSame(0, StokLog::count());
        $this->assertFalse(
            GudangProduk::query()
                ->where('gudang_id', $requestGudang->id)
                ->where('produk_id', $produk->id)
                ->exists(),
        );
    }

    /** @covers-finding B13 Penerimaan over-receive prevented */
    public function test_api_store_rejects_qty_diterima_beyond_remaining_purchase_quantity(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk(['nama_produk' => 'Test']);
        $superAdmin = $this->transactionUser('super_admin');
        $pembelian = $this->transactionPembelian($superAdmin, $gudang, [
            'status' => 'Approved',
        ], [
            ['produk' => $produk, 'kuantitas' => 5, 'harga_satuan' => 15000],
        ]);

        $this->transactionPenerimaanBarang($pembelian, $superAdmin, $gudang, [
            'status' => 'Approved',
        ], [
            ['produk' => $produk, 'qty_diterima' => 4, 'tipe_stok' => 'penjualan'],
        ]);

        $response = $this->postJson('/api/v1/penerimaan-barang', [
            'gudang_id' => $gudang->id,
            'pembelian_id' => $pembelian->id,
            'tgl_penerimaan' => now()->toDateString(),
            'items' => [
                [
                    'produk_id' => $produk->id,
                    'qty_diterima' => 2,
                    'qty_reject' => 0,
                    'tipe_stok' => 'penjualan',
                ],
            ],
        ], $this->authHeaderFor($superAdmin));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.qty_diterima']);
        $this->assertStringContainsString(
            'Qty diterima melebihi sisa PO. Produk Test: sisa 1, diminta 2.',
            $response->getContent(),
        );

        $this->assertSame(1, PenerimaanBarang::count());
        $this->assertSame(0, StokLog::count());
        $this->assertFalse(
            GudangProduk::query()
                ->where('gudang_id', $gudang->id)
                ->where('produk_id', $produk->id)
                ->exists(),
        );
    }

    public function test_sequential_pending_receipts_cannot_both_approve_beyond_purchase_remaining_quantity(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk(['nama_produk' => 'Sequential Test Produk']);
        $creator = $this->transactionUser('user', $gudang);
        $admin = $this->transactionUser('admin', $gudang);
        $pembelian = $this->transactionPembelian($creator, $gudang, [
            'status' => 'Approved',
        ], [
            ['produk' => $produk, 'kuantitas' => 5, 'harga_satuan' => 15000],
        ]);

        $firstReceipt = $this->transactionPenerimaanBarang($pembelian, $creator, $gudang, [
            'status' => 'Pending',
        ], [
            ['produk' => $produk, 'qty_diterima' => 3, 'tipe_stok' => 'sample'],
        ]);
        $secondReceipt = $this->transactionPenerimaanBarang($pembelian, $creator, $gudang, [
            'status' => 'Pending',
        ], [
            ['produk' => $produk, 'qty_diterima' => 3, 'tipe_stok' => 'sample'],
        ]);

        $this->postJson("/api/v1/penerimaan-barang/{$firstReceipt->id}/approve", [], $this->authHeaderFor($admin))
            ->assertOk();

        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 3,
            'stok_penjualan' => 0,
            'stok_gratis' => 0,
            'stok_sample' => 3,
        ]);
        $this->assertSame(1, StokLog::query()->where('produk_id', $produk->id)->count());

        $response = $this->postJson("/api/v1/penerimaan-barang/{$secondReceipt->id}/approve", [], $this->authHeaderFor($admin));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.qty_diterima']);
        $this->assertStringContainsString(
            'Qty diterima melebihi sisa PO. Produk Sequential Test Produk: sisa 2, diminta 3.',
            $response->getContent(),
        );

        $this->assertDatabaseHas('penerimaan_barangs', [
            'id' => $secondReceipt->id,
            'status' => 'Pending',
        ]);
        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 3,
            'stok_sample' => 3,
        ]);
        $this->assertSame(1, StokLog::query()->where('produk_id', $produk->id)->count());
    }

    public function test_cancel_approved_receipt_subtracts_exact_received_stock(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $superAdmin = $this->transactionUser('super_admin');
        $pembelian = $this->transactionPembelian($superAdmin, $gudang, [
            'status' => 'Approved',
        ], [
            ['produk' => $produk, 'kuantitas' => 10, 'harga_satuan' => 15000],
        ]);
        $penerimaan = $this->transactionPenerimaanBarang($pembelian, $superAdmin, $gudang, [
            'status' => 'Approved',
        ], [
            ['produk' => $produk, 'qty_diterima' => 5, 'tipe_stok' => 'penjualan'],
        ]);
        $this->transactionStock($gudang, $produk, [
            'stok' => 8,
            'stok_penjualan' => 8,
            'stok_gratis' => 0,
            'stok_sample' => 0,
        ]);

        $response = $this->postJson("/api/v1/penerimaan-barang/{$penerimaan->id}/cancel", [], $this->authHeaderFor($superAdmin));

        $response->assertOk()
            ->assertJson(['message' => 'Penerimaan barang berhasil dibatalkan.']);
        $this->assertDatabaseHas('penerimaan_barangs', [
            'id' => $penerimaan->id,
            'status' => 'Canceled',
        ]);
        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 3,
            'stok_penjualan' => 3,
            'stok_gratis' => 0,
            'stok_sample' => 0,
        ]);
        $this->assertDatabaseHas('stok_logs', [
            'produk_id' => $produk->id,
            'gudang_id' => $gudang->id,
            'stok_sebelum' => 8,
            'stok_sesudah' => 3,
            'selisih' => -5,
        ]);
    }

    /** @covers-finding B15 Penerimaan cancel/edit reversal */
    public function test_cancel_approved_receipt_with_insufficient_current_stock_is_rejected_without_mutation(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $superAdmin = $this->transactionUser('super_admin');
        $pembelian = $this->transactionPembelian($superAdmin, $gudang, [
            'status' => 'Approved',
        ], [
            ['produk' => $produk, 'kuantitas' => 10, 'harga_satuan' => 15000],
        ]);
        $penerimaan = $this->transactionPenerimaanBarang($pembelian, $superAdmin, $gudang, [
            'status' => 'Approved',
        ], [
            ['produk' => $produk, 'qty_diterima' => 5, 'tipe_stok' => 'penjualan'],
        ]);
        $this->transactionStock($gudang, $produk, [
            'stok' => 3,
            'stok_penjualan' => 3,
            'stok_gratis' => 0,
            'stok_sample' => 0,
        ]);

        $response = $this->postJson("/api/v1/penerimaan-barang/{$penerimaan->id}/cancel", [], $this->authHeaderFor($superAdmin));

        $response->assertStatus(422)
            ->assertJson(['message' => 'Stok penjualan tidak cukup. Tersedia 3, diminta 5.']);
        $this->assertDatabaseHas('penerimaan_barangs', [
            'id' => $penerimaan->id,
            'status' => 'Approved',
        ]);
        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 3,
            'stok_penjualan' => 3,
            'stok_gratis' => 0,
            'stok_sample' => 0,
        ]);
        $this->assertSame(0, StokLog::query()->where('produk_id', $produk->id)->count());
    }

    public function test_cancel_pending_receipt_has_no_stock_effect(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $admin = $this->transactionUser('admin', $gudang);
        $pembelian = $this->transactionPembelian($admin, $gudang, [
            'status' => 'Approved',
        ], [
            ['produk' => $produk, 'kuantitas' => 10, 'harga_satuan' => 15000],
        ]);
        $penerimaan = $this->transactionPenerimaanBarang($pembelian, $admin, $gudang, [
            'status' => 'Pending',
        ], [
            ['produk' => $produk, 'qty_diterima' => 5, 'tipe_stok' => 'penjualan'],
        ]);
        $this->transactionStock($gudang, $produk, [
            'stok' => 2,
            'stok_penjualan' => 2,
            'stok_gratis' => 0,
            'stok_sample' => 0,
        ]);

        $this->postJson("/api/v1/penerimaan-barang/{$penerimaan->id}/cancel", [], $this->authHeaderFor($admin))
            ->assertOk();

        $this->assertDatabaseHas('penerimaan_barangs', [
            'id' => $penerimaan->id,
            'status' => 'Canceled',
        ]);
        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 2,
            'stok_penjualan' => 2,
        ]);
        $this->assertSame(0, StokLog::query()->where('produk_id', $produk->id)->count());
    }

    public function test_uncancel_returns_pending_without_reapplying_stock_then_approve_validates_remaining_po(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk(['nama_produk' => 'Uncancel Receipt Produk']);
        $superAdmin = $this->transactionUser('super_admin');
        $pembelian = $this->transactionPembelian($superAdmin, $gudang, [
            'status' => 'Approved',
        ], [
            ['produk' => $produk, 'kuantitas' => 5, 'harga_satuan' => 15000],
        ]);
        $uncanceledReceipt = $this->transactionPenerimaanBarang($pembelian, $superAdmin, $gudang, [
            'status' => 'Approved',
        ], [
            ['produk' => $produk, 'qty_diterima' => 5, 'tipe_stok' => 'penjualan'],
        ]);
        $this->transactionStock($gudang, $produk, [
            'stok' => 5,
            'stok_penjualan' => 5,
            'stok_gratis' => 0,
            'stok_sample' => 0,
        ]);

        $this->postJson("/api/v1/penerimaan-barang/{$uncanceledReceipt->id}/cancel", [], $this->authHeaderFor($superAdmin))
            ->assertOk();
        $this->postJson("/api/v1/penerimaan-barang/{$uncanceledReceipt->id}/uncancel", [], $this->authHeaderFor($superAdmin))
            ->assertOk()
            ->assertJsonPath('data.status', 'Pending');

        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 0,
            'stok_penjualan' => 0,
        ]);

        $this->transactionPenerimaanBarang($pembelian, $superAdmin, $gudang, [
            'status' => 'Approved',
        ], [
            ['produk' => $produk, 'qty_diterima' => 5, 'tipe_stok' => 'penjualan'],
        ]);
        GudangProduk::where('gudang_id', $gudang->id)
            ->where('produk_id', $produk->id)
            ->update(['stok' => 5, 'stok_penjualan' => 5]);

        $response = $this->postJson("/api/v1/penerimaan-barang/{$uncanceledReceipt->id}/approve", [], $this->authHeaderFor($superAdmin));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.qty_diterima']);
        $this->assertStringContainsString(
            'Qty diterima melebihi sisa PO. Produk Uncancel Receipt Produk: sisa 0, diminta 5.',
            $response->getContent(),
        );
        $this->assertDatabaseHas('penerimaan_barangs', [
            'id' => $uncanceledReceipt->id,
            'status' => 'Pending',
        ]);
        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 5,
            'stok_penjualan' => 5,
        ]);
    }

    public function test_filament_approved_edit_applies_exact_stock_delta_atomically(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $superAdmin = $this->transactionUser('super_admin');
        $pembelian = $this->transactionPembelian($superAdmin, $gudang, [
            'status' => 'Approved',
        ], [
            ['produk' => $produk, 'kuantitas' => 10, 'harga_satuan' => 15000],
        ]);
        $penerimaan = $this->transactionPenerimaanBarang($pembelian, $superAdmin, $gudang, [
            'status' => 'Approved',
        ], [
            ['produk' => $produk, 'qty_diterima' => 5, 'tipe_stok' => 'penjualan'],
        ]);
        $this->transactionStock($gudang, $produk, [
            'stok' => 8,
            'stok_penjualan' => 8,
            'stok_gratis' => 0,
            'stok_sample' => 0,
        ]);

        Livewire::actingAs($superAdmin)
            ->test(EditPenerimaanBarang::class, ['record' => $penerimaan->getRouteKey()])
            ->fillForm([
                'gudang_id' => $gudang->id,
                'pembelian_ids' => [$pembelian->id],
                'tgl_penerimaan' => $penerimaan->tgl_penerimaan->toDateString(),
                'no_surat_jalan' => $penerimaan->no_surat_jalan,
                'items' => [
                    [
                        'pembelian_id' => $pembelian->id,
                        'produk_id' => $produk->id,
                        'qty_diterima' => 2,
                        'qty_reject' => 0,
                        'tipe_stok' => 'penjualan',
                        'batch_number' => null,
                        'expired_date' => null,
                        'keterangan' => null,
                    ],
                ],
                'keterangan' => 'Edited atomically',
                'lampiran_paths' => [],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 5,
            'stok_penjualan' => 5,
            'stok_gratis' => 0,
            'stok_sample' => 0,
        ]);
        $this->assertDatabaseHas('penerimaan_barang_items', [
            'penerimaan_barang_id' => $penerimaan->id,
            'produk_id' => $produk->id,
            'qty_diterima' => 2,
            'tipe_stok' => 'penjualan',
        ]);
    }

    public function test_filament_approved_edit_with_insufficient_stock_rolls_back_record_items_and_stock(): void
    {
        $gudang = $this->transactionGudang();
        $produk = $this->transactionProduk();
        $superAdmin = $this->transactionUser('super_admin');
        $pembelian = $this->transactionPembelian($superAdmin, $gudang, [
            'status' => 'Approved',
        ], [
            ['produk' => $produk, 'kuantitas' => 10, 'harga_satuan' => 15000],
        ]);
        $penerimaan = $this->transactionPenerimaanBarang($pembelian, $superAdmin, $gudang, [
            'status' => 'Approved',
            'keterangan' => 'Original note',
        ], [
            ['produk' => $produk, 'qty_diterima' => 5, 'tipe_stok' => 'penjualan'],
        ]);
        $this->transactionStock($gudang, $produk, [
            'stok' => 3,
            'stok_penjualan' => 3,
            'stok_gratis' => 0,
            'stok_sample' => 0,
        ]);

        $failed = false;

        try {
            Livewire::actingAs($superAdmin)
                ->test(EditPenerimaanBarang::class, ['record' => $penerimaan->getRouteKey()])
                ->fillForm([
                    'gudang_id' => $gudang->id,
                    'pembelian_ids' => [$pembelian->id],
                    'tgl_penerimaan' => $penerimaan->tgl_penerimaan->toDateString(),
                    'no_surat_jalan' => 'SJ-EDIT-ROLLBACK',
                    'items' => [
                        [
                            'pembelian_id' => $pembelian->id,
                            'produk_id' => $produk->id,
                            'qty_diterima' => 2,
                            'qty_reject' => 0,
                            'tipe_stok' => 'penjualan',
                            'batch_number' => null,
                            'expired_date' => null,
                            'keterangan' => null,
                        ],
                    ],
                    'keterangan' => 'Should roll back',
                    'lampiran_paths' => [],
                ])
                ->call('save');
        } catch (Throwable $e) {
            $failed = true;
            $this->assertStringContainsString('Stok penjualan tidak cukup. Tersedia 3, diminta 5.', $e->getMessage());
        }

        $this->assertTrue($failed, 'Approved edit with impossible reversal must fail.');
        $this->assertDatabaseHas('penerimaan_barangs', [
            'id' => $penerimaan->id,
            'status' => 'Approved',
            'no_surat_jalan' => $penerimaan->no_surat_jalan,
            'keterangan' => 'Original note',
        ]);
        $this->assertDatabaseHas('penerimaan_barang_items', [
            'penerimaan_barang_id' => $penerimaan->id,
            'produk_id' => $produk->id,
            'qty_diterima' => 5,
            'tipe_stok' => 'penjualan',
        ]);
        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 3,
            'stok_penjualan' => 3,
            'stok_gratis' => 0,
            'stok_sample' => 0,
        ]);
        $this->assertSame(0, StokLog::query()->where('produk_id', $produk->id)->count());
    }
}
