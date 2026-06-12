<?php

namespace App\Http\Controllers;

use App\Models\Biaya;
use App\Models\Kunjungan;
use App\Models\Pembelian;
use App\Models\Penjualan;
use Carbon\Carbon;

class BluetoothPrintController extends Controller
{
    public function penjualanJson($id)
    {
        $p = Penjualan::with(['items.produk', 'user', 'gudang', 'approver'])->findOrFail($id);

        $items = $p->items->map(fn($i) => [
            'nama' => $i->produk?->nama_produk . ($i->produk?->item_code ? ' (' . $i->produk->item_code . ')' : ''),
            'nama_produk' => $i->produk?->nama_produk,
            'item_code' => $i->produk?->item_code,
            'qty' => $i->kuantitas,
            'kuantitas' => $i->kuantitas,
            'unit' => $i->unit ?? $i->produk?->satuan ?? 'Pcs',
            'satuan' => $i->produk?->satuan ?? 'Pcs',
            'harga' => $i->harga_satuan,
            'harga_satuan' => $i->harga_satuan,
            'diskon' => $i->diskon,
            'diskon_nominal' => $i->diskon_nominal ?? 0,
            'batch' => $i->batch_number,
            'batch_number' => $i->batch_number,
            'exp' => $i->expired_date?->format('Y-m-d'),
            'expired_date' => $i->expired_date?->format('Y-m-d'),
            'jumlah' => $i->jumlah_baris,
            'deskripsi' => $i->deskripsi,
        ])->values();

        $subtotal = $p->items->sum('jumlah_baris');
        $kenaPajak = max(0, $subtotal - ($p->diskon_akhir ?? 0));
        $pajak = $kenaPajak * ($p->tax_percentage / 100);

        // Resolve nomor telepon pelanggan with fallback
        $noTelepon = '';
        if (!empty($p->no_telepon)) {
            $noTelepon = $p->no_telepon;
        } elseif (!empty($p->pelanggan)) {
            $kontak = \App\Models\Kontak::where('nama', $p->pelanggan)->first();
            if ($kontak && !empty($kontak->no_telp)) {
                $noTelepon = $kontak->no_telp;
            }
        }

        return response()->json([
            'nomor' => $p->nomor ?? $p->custom_number ?? ('INV-' . $p->id),
            'tanggal' => ($p->tgl_transaksi ?? $p->created_at)?->format('d/m/Y') . ' | ' . $p->created_at->format('H:i'),
            'jatuh_tempo' => $p->tgl_jatuh_tempo?->format('d/m/Y') ?? '-',
            'pembayaran' => $p->syarat_pembayaran ?? '-',
            'pelanggan' => $p->pelanggan ?? '-',
            'no_telepon' => $noTelepon,
            'alamat_penagihan' => $p->alamat_penagihan,
            'tipe_harga' => $p->tipe_harga,
            'no_referensi' => $p->no_referensi,
            'tag' => $p->tag,
            'koordinat' => $p->koordinat,
            'memo' => $p->memo,
            'sales' => $p->user?->name ?? '-',
            'sales_no_telp' => $p->user?->no_telp ?? '',
            'approver' => ($p->status != 'Pending' && $p->approver) ? $p->approver->name : '-',
            'gudang' => $p->gudang?->nama_gudang ?? '-',
            'status' => $p->status,
            'items' => $items,
            'subtotal' => $subtotal,
            'diskon_akhir' => $p->diskon_akhir ?? 0,
            'tax_percentage' => $p->tax_percentage ?? 0,
            'pajak' => $pajak,
            'grand_total' => $p->grand_total,
            'invoice_url' => $p->uuid ? url("invoice/penjualan/{$p->uuid}") : null,
        ]);
    }

    public function pembelianJson($id)
    {
        $p = Pembelian::with(['items.produk', 'user', 'gudang', 'approver'])->findOrFail($id);

        $items = $p->items->map(fn($i) => [
            'nama' => $i->produk?->nama_produk ?? $i->deskripsi ?? '-',
            'nama_produk' => $i->produk?->nama_produk,
            'qty' => $i->kuantitas,
            'kuantitas' => $i->kuantitas,
            'unit' => $i->unit ?? $i->produk?->satuan ?? 'Pcs',
            'satuan' => $i->produk?->satuan ?? 'Pcs',
            'harga' => $i->harga_satuan,
            'harga_satuan' => $i->harga_satuan,
            'diskon' => $i->diskon,
            'batch_number' => $i->batch_number ?? null,
            'expired_date' => null,
            'jumlah' => $i->jumlah_baris,
            'deskripsi' => $i->deskripsi,
        ])->values();

        $subtotal = $items->sum('jumlah');
        $pajak = $subtotal > 0 && $p->tax_percentage > 0
            ? round(($subtotal - ($p->diskon_akhir ?? 0)) * ($p->tax_percentage / 100), 2) : 0;

        return response()->json([
            'nomor' => $p->nomor,
            'tanggal' => $p->tgl_transaksi?->format('d/m/Y'),
            'jatuh_tempo' => $p->tgl_jatuh_tempo?->format('d/m/Y') ?? '-',
            'pembayaran' => $p->syarat_pembayaran,
            'vendor' => '-',
            'urgensi' => $p->urgensi,
            'tahun_anggaran' => $p->tahun_anggaran,
            'staf_penyetuju' => $p->staf_penyetuju,
            'memo' => $p->memo,
            'sales' => $p->user?->name,
            'approver' => $p->approver?->name ?? '-',
            'gudang' => $p->gudang?->nama_gudang,
            'status' => $p->status,
            'items' => $items,
            'subtotal' => $subtotal,
            'diskon_akhir' => $p->diskon_akhir ?? 0,
            'tax_percentage' => $p->tax_percentage ?? 0,
            'pajak' => $pajak,
            'grand_total' => $p->grand_total,
            'invoice_url' => $p->uuid ? url("invoice/pembelian/{$p->uuid}") : null,
        ]);
    }

    public function biayaJson($id)
    {
        $b = Biaya::with(['items', 'user', 'gudang', 'approver'])->findOrFail($id);

        $items = $b->items->map(fn($i) => [
            'kategori' => $i->kategori,
            'deskripsi' => $i->deskripsi,
            'jumlah' => $i->jumlah,
        ])->values();

        $subtotal = $items->sum('jumlah');
        $pajak = $subtotal > 0 && $b->tax_percentage > 0
            ? round($subtotal * ($b->tax_percentage / 100), 2) : 0;

        return response()->json([
            'nomor' => $b->nomor,
            'tanggal' => $b->tgl_transaksi?->format('d/m/Y'),
            'jenis_biaya' => $b->jenis_biaya,
            'cara_pembayaran' => $b->cara_pembayaran,
            'bayar_dari' => $b->bayar_dari,
            'penerima' => $b->penerima,
            'alamat_penagihan' => $b->alamat_penagihan,
            'tag' => $b->tag,
            'koordinat' => $b->koordinat,
            'memo' => $b->memo,
            'gudang' => $b->gudang?->nama_gudang,
            'sales' => $b->user?->name,
            'approver' => $b->approver?->name ?? '-',
            'status' => $b->status,
            'items' => $items,
            'subtotal' => $subtotal,
            'tax_percentage' => $b->tax_percentage ?? 0,
            'pajak' => $pajak,
            'grand_total' => $b->grand_total,
            'invoice_url' => $b->uuid ? url("invoice/biaya/{$b->uuid}") : null,
        ]);
    }

    public function kunjunganJson($id)
    {
        $k = Kunjungan::with(['items.produk', 'user', 'gudang', 'approver', 'kontak'])->findOrFail($id);

        $items = $k->items->map(fn($i) => [
            'nama' => $i->produk?->nama_produk ?? '-',
            'nama_produk' => $i->produk?->nama_produk,
            'kode' => $i->produk?->item_code ?? '-',
            'qty' => $i->jumlah,
            'kuantitas' => $i->jumlah,
            'unit' => $i->produk?->satuan ?? 'Pcs',
            'satuan' => $i->produk?->satuan ?? 'Pcs',
            'batch' => $i->batch_number,
            'batch_number' => $i->batch_number,
            'exp' => $i->expired_date?->format('Y-m-d'),
            'expired_date' => $i->expired_date?->format('Y-m-d'),
            'keterangan' => $i->keterangan,
        ])->values();

        return response()->json([
            'nomor' => $k->nomor,
            'tanggal' => $k->tgl_kunjungan?->format('d/m/Y'),
            'waktu' => $k->created_at?->format('H:i'),
            'tujuan' => $k->tujuan,
            'sales_nama' => $k->sales_nama,
            'sales_no_telepon' => $k->sales_no_telepon,
            'sales_alamat' => $k->sales_alamat,
            'pembuat' => $k->user?->name,
            'approver' => $k->approver?->name ?? '-',
            'gudang' => $k->gudang?->nama_gudang,
            'status' => $k->status,
            'koordinat' => $k->koordinat,
            'memo' => $k->memo,
            'items' => $items,
            'invoice_url' => $k->uuid ? url("invoice/kunjungan/{$k->uuid}") : null,
        ]);
    }
}
