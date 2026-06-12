<?php

namespace Tests\Feature;

use App\Models\Biaya;
use App\Models\BiayaItem;
use App\Models\Kontak;
use App\Models\Kunjungan;
use App\Models\KunjunganItem;
use App\Models\Pembayaran;
use App\Models\Pembelian;
use App\Models\PembelianItem;
use App\Models\PenerimaanBarang;
use App\Models\PenerimaanBarangItem;
use App\Models\Penjualan;
use App\Models\PenjualanItem;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentDetailPagesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_super_admin_can_render_custom_detail_pages(): void
    {
        $records = $this->makeRecords();
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->firstOrFail();

        $paths = [
            "/app/penjualans/{$records['penjualan']->id}",
            "/app/pembelians/{$records['pembelian']->id}",
            "/app/biayas/{$records['biaya']->id}/view",
            "/app/kunjungans/{$records['kunjungan']->id}",
            "/app/pembayarans/{$records['pembayaran']->id}",
            "/app/penerimaan-barangs/{$records['penerimaan']->id}",
            "/app/produks/{$records['produk']->id}",
            "/app/kontaks/{$records['kontak']->id}",
        ];

        foreach ($paths as $path) {
            $this->actingAs($user)->get($path)->assertOk();
        }
    }

    private function makeRecords(): array
    {
        $user = User::where('email', 'salesa@hibiscusefsya.com')->firstOrFail();
        $admin = User::where('email', 'admin@hibiscusefsya.com')->firstOrFail();
        $produk = Produk::firstOrFail();
        $kontak = Kontak::firstOrFail();
        $gudangId = $user->gudang_id;

        $penjualan = Penjualan::create([
            'user_id' => $user->id,
            'approver_id' => $admin->id,
            'gudang_id' => $gudangId,
            'nomor' => 'INV-DETAIL-001',
            'tipe_harga' => 'retail',
            'pelanggan' => $kontak->nama,
            'no_telepon' => $kontak->no_telp,
            'tgl_transaksi' => now()->toDateString(),
            'tgl_jatuh_tempo' => now()->addDays(7)->toDateString(),
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

        $pembelian = Pembelian::create([
            'user_id' => $user->id,
            'approver_id' => $admin->id,
            'gudang_id' => $gudangId,
            'nomor' => 'PO-DETAIL-001',
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

        $biaya = Biaya::create([
            'user_id' => $user->id,
            'approver_id' => $admin->id,
            'gudang_id' => $gudangId,
            'nomor' => 'COST-DETAIL-001',
            'jenis_biaya' => 'keluar',
            'tgl_transaksi' => now()->toDateString(),
            'cara_pembayaran' => 'Cash',
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

        $kunjungan = Kunjungan::create([
            'user_id' => $user->id,
            'approver_id' => $admin->id,
            'gudang_id' => $gudangId,
            'kontak_id' => $kontak->id,
            'nomor' => 'VISIT-DETAIL-001',
            'sales_nama' => $kontak->nama,
            'sales_no_telepon' => $kontak->no_telp,
            'sales_alamat' => $kontak->alamat,
            'tgl_kunjungan' => now()->toDateString(),
            'tujuan' => 'Penagihan',
            'status' => 'Approved',
        ]);

        KunjunganItem::create([
            'kunjungan_id' => $kunjungan->id,
            'produk_id' => $produk->id,
            'jumlah' => 1,
        ]);

        $pembayaran = Pembayaran::create([
            'user_id' => $user->id,
            'approver_id' => $admin->id,
            'gudang_id' => $gudangId,
            'penjualan_id' => $penjualan->id,
            'nomor' => 'PAY-DETAIL-001',
            'tgl_pembayaran' => now()->toDateString(),
            'metode_pembayaran' => 'Cash',
            'jumlah_bayar' => 25000,
            'status' => 'Approved',
        ]);

        $penerimaan = PenerimaanBarang::create([
            'user_id' => $user->id,
            'approver_id' => $admin->id,
            'gudang_id' => $gudangId,
            'pembelian_id' => $pembelian->id,
            'nomor' => 'RCV-DETAIL-001',
            'tgl_penerimaan' => now()->toDateString(),
            'no_surat_jalan' => 'SJ-001',
            'status' => 'Approved',
        ]);

        PenerimaanBarangItem::create([
            'penerimaan_barang_id' => $penerimaan->id,
            'produk_id' => $produk->id,
            'qty_diterima' => 1,
            'qty_reject' => 0,
            'tipe_stok' => 'penjualan',
        ]);

        return compact('penjualan', 'pembelian', 'biaya', 'kunjungan', 'pembayaran', 'penerimaan', 'produk', 'kontak');
    }
}
