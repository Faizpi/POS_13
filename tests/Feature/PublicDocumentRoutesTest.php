<?php

namespace Tests\Feature;

use App\Models\Biaya;
use App\Models\BiayaItem;
use App\Models\Kontak;
use App\Models\Pembelian;
use App\Models\PembelianItem;
use App\Models\Penjualan;
use App\Models\PenjualanItem;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicDocumentRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_public_invoice_and_struk_render_penjualan_document(): void
    {
        $penjualan = $this->makePenjualan();

        $this->get(route('public.invoice.penjualan', $penjualan->uuid))
            ->assertOk()
            ->assertSee('Invoice Penjualan')
            ->assertSee('INV-DOC-001')
            ->assertSee('+62 811-1111-1111')
            ->assertDontSee('Toggle Theme')
            ->assertDontSee('toggleTheme');

        $this->get(route('public.struk.show', ['type' => 'penjualan', 'uuid' => $penjualan->uuid]))
            ->assertOk()
            ->assertSee('HIBISCUS EFSYA')
            ->assertSee('Periksa Invoice & Ambil Promo !', false)
            ->assertSee('size: auto', false)
            ->assertSee('margin: 0 auto', false)
            ->assertSee('INV-DOC-001')
            ->assertSee('+62 811-1111-1111')
            ->assertSee('+62 851-9555-0202');
    }

    public function test_public_invoice_pdf_download_renders_penjualan_document(): void
    {
        $penjualan = $this->makePenjualan();

        $response = $this->get(route('public.invoice.penjualan.download', $penjualan->uuid));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_authenticated_print_and_master_card_routes_render(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->firstOrFail();
        $penjualan = $this->makePenjualan();
        $pembelian = $this->makePembelian();
        $biaya = $this->makeBiaya();
        $produk = Produk::firstOrFail();
        $kontak = Kontak::firstOrFail();

        $this->actingAs($user)->get(route('penjualan.print', $penjualan))
            ->assertOk()
            ->assertSee('Invoice Penjualan')
            ->assertSee('receipt-paper')
            ->assertSee('size: auto', false)
            ->assertSee('margin: 0 auto', false)
            ->assertSee('INV-DOC-001')
            ->assertSee('+62 811-1111-1111')
            ->assertSee('+62 851-9555-0202');

        $this->actingAs($user)->get(route('pembelian.print', $pembelian))
            ->assertOk()
            ->assertSee('INVOICE PEMBELIAN')
            ->assertSee('PO-DOC-001')
            ->assertSee('Rp20.000,00');

        $this->actingAs($user)->get(route('biaya.print', $biaya))
            ->assertOk()
            ->assertSee('STRUK BIAYA')
            ->assertSee('COST-DOC-001')
            ->assertSee('Rp10.000,00');

        $this->actingAs($user)->get(route('produk.print', $produk))
            ->assertOk()
            ->assertSee('Kartu Produk')
            ->assertSee($produk->item_code);

        $this->actingAs($user)->get(route('kontak.print', $kontak))
            ->assertOk()
            ->assertSee('Kartu Kontak')
            ->assertSee($kontak->kode_kontak);
    }

    private function makePenjualan(): Penjualan
    {
        $user = User::where('email', 'salesa@hibiscusefsya.com')->firstOrFail();
        $produk = Produk::firstOrFail();

        $penjualan = Penjualan::create([
            'user_id' => $user->id,
            'gudang_id' => $user->gudang_id,
            'nomor' => 'INV-DOC-001',
            'tipe_harga' => 'retail',
            'pelanggan' => 'Toko Melati',
            'no_telepon' => '081111111111',
            'tgl_transaksi' => now()->toDateString(),
            'syarat_pembayaran' => 'Cash',
            'status' => 'Approved',
            'diskon_akhir' => 0,
            'tax_percentage' => 0,
            'grand_total' => 25000,
        ]);

        PenjualanItem::create([
            'penjualan_id' => $penjualan->id,
            'produk_id' => $produk->id,
            'kuantitas' => 1,
            'unit' => 'Pcs',
            'harga_satuan' => 25000,
            'diskon' => 0,
            'diskon_nominal' => 0,
            'jumlah_baris' => 25000,
        ]);

        return $penjualan->refresh();
    }

    private function makePembelian(): Pembelian
    {
        $user = User::where('email', 'salesa@hibiscusefsya.com')->firstOrFail();
        $produk = Produk::firstOrFail();

        $pembelian = Pembelian::create([
            'user_id' => $user->id,
            'gudang_id' => $user->gudang_id,
            'nomor' => 'PO-DOC-001',
            'tgl_transaksi' => now()->toDateString(),
            'tgl_jatuh_tempo' => now()->addDays(7)->toDateString(),
            'syarat_pembayaran' => 'Cash',
            'urgensi' => 'Sedang',
            'tahun_anggaran' => now()->year,
            'status' => 'Approved',
            'diskon_akhir' => 0,
            'tax_percentage' => 0,
            'grand_total' => 20000,
        ]);

        PembelianItem::create([
            'pembelian_id' => $pembelian->id,
            'produk_id' => $produk->id,
            'kuantitas' => 1,
            'unit' => 'Pcs',
            'harga_satuan' => 20000,
            'diskon' => 0,
            'jumlah_baris' => 20000,
        ]);

        return $pembelian->refresh();
    }

    private function makeBiaya(): Biaya
    {
        $user = User::where('email', 'salesa@hibiscusefsya.com')->firstOrFail();

        $biaya = Biaya::create([
            'user_id' => $user->id,
            'gudang_id' => $user->gudang_id,
            'nomor' => 'COST-DOC-001',
            'jenis_biaya' => 'keluar',
            'tgl_transaksi' => now()->toDateString(),
            'cara_pembayaran' => 'Cash',
            'bayar_dari' => 'Kas',
            'penerima' => 'Operasional',
            'status' => 'Approved',
            'tax_percentage' => 0,
            'grand_total' => 10000,
        ]);

        BiayaItem::create([
            'biaya_id' => $biaya->id,
            'kategori' => 'Operasional',
            'deskripsi' => 'Transport',
            'jumlah' => 10000,
        ]);

        return $biaya->refresh();
    }
}
