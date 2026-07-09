<?php

namespace App\Http\Controllers;

use App\Models\Biaya;
use App\Models\Kontak;
use App\Models\Kunjungan;
use App\Models\Pembayaran;
use App\Models\Pembelian;
use App\Models\PenerimaanBarang;
use App\Models\Penjualan;
use App\Models\Produk;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicDocumentController extends Controller
{
    public function invoicePenjualan(string $uuid)
    {
        $record = $this->loadTransaction('penjualan', Penjualan::where('uuid', $uuid)->firstOrFail());
        // Template spesifik butuh $penjualan + $noTelepon; template generic pakai documentData
        if (view()->exists('public.invoice-penjualan')) {
            $noTelepon = '';
            if (! empty($record->no_telepon)) {
                $noTelepon = $record->no_telepon;
            } elseif (! empty($record->pelanggan)) {
                $kontak = Kontak::where('nama', $record->pelanggan)->first();
                $noTelepon = $kontak?->no_telp ?? '';
            }

            return view('public.invoice-penjualan', ['penjualan' => $record, 'noTelepon' => $noTelepon]);
        }

        return $this->invoice('penjualan', $record);
    }

    public function invoicePembelian(string $uuid)
    {
        $record = $this->loadTransaction('pembelian', Pembelian::where('uuid', $uuid)->firstOrFail());
        if (view()->exists('public.invoice-pembelian')) {
            return view('public.invoice-pembelian', ['pembelian' => $record]);
        }

        return $this->invoice('pembelian', $record);
    }

    public function invoiceBiaya(string $uuid)
    {
        $record = $this->loadTransaction('biaya', Biaya::where('uuid', $uuid)->firstOrFail());
        if (view()->exists('public.invoice-biaya')) {
            return view('public.invoice-biaya', ['biaya' => $record]);
        }

        return $this->invoice('biaya', $record);
    }

    public function invoiceKunjungan(string $uuid)
    {
        $record = $this->loadTransaction('kunjungan', Kunjungan::where('uuid', $uuid)->firstOrFail());
        if (view()->exists('public.invoice-kunjungan')) {
            return view('public.invoice-kunjungan', ['kunjungan' => $record]);
        }

        return $this->invoice('kunjungan', $record);
    }

    public function invoicePembayaran(string $uuid)
    {
        $record = $this->loadTransaction('pembayaran', Pembayaran::where('uuid', $uuid)->firstOrFail());
        if (view()->exists('public.invoice-pembayaran')) {
            return view('public.invoice-pembayaran', ['pembayaran' => $record]);
        }

        return $this->invoice('pembayaran', $record);
    }

    public function invoicePenerimaanBarang(string $uuid)
    {
        $record = $this->loadTransaction('penerimaan-barang', PenerimaanBarang::where('uuid', $uuid)->firstOrFail());
        if (view()->exists('public.invoice-penerimaan')) {
            return view('public.invoice-penerimaan', ['penerimaan' => $record]);
        }

        return $this->invoice('penerimaan-barang', $record);
    }

    public function publicStruk(string $type, string $uuid)
    {
        $type = $this->normalizeType($type);

        return $this->printable($type, $this->findTransactionByUuid($type, $uuid));
    }

    public function printPenjualan(Penjualan $penjualan)
    {
        return $this->printable('penjualan', $this->loadTransaction('penjualan', $penjualan));
    }

    public function printPembelian(Pembelian $pembelian)
    {
        return $this->printable('pembelian', $this->loadTransaction('pembelian', $pembelian));
    }

    public function printBiaya(Biaya $biaya)
    {
        return $this->printable('biaya', $this->loadTransaction('biaya', $biaya));
    }

    public function printKunjungan(Kunjungan $kunjungan)
    {
        return $this->printable('kunjungan', $this->loadTransaction('kunjungan', $kunjungan));
    }

    public function printPembayaran(Pembayaran $pembayaran)
    {
        return $this->printable('pembayaran', $this->loadTransaction('pembayaran', $pembayaran));
    }

    public function printPenerimaanBarang(PenerimaanBarang $penerimaanBarang)
    {
        return $this->printable('penerimaan-barang', $this->loadTransaction('penerimaan-barang', $penerimaanBarang));
    }

    public function printProduk(Produk $produk)
    {
        $produk->load('stokDiGudang.gudang');

        return view('print.master-card', [
            'type' => 'produk',
            'title' => 'Kartu Produk',
            'record' => $produk,
        ]);
    }

    public function downloadProduk(Produk $produk)
    {
        $produk->load('stokDiGudang.gudang');

        return Pdf::loadView('print.master-card', [
            'type' => 'produk',
            'title' => 'Kartu Produk',
            'record' => $produk,
            'pdf' => true,
        ])->download($this->filename('produk', $produk->item_code ?: $produk->id));
    }

    public function printKontak(Kontak $kontak)
    {
        $kontak->load('gudang');

        return view('print.master-card', [
            'type' => 'kontak',
            'title' => 'Kartu Kontak',
            'record' => $kontak,
        ]);
    }

    public function downloadKontak(Kontak $kontak)
    {
        $kontak->load('gudang');

        return Pdf::loadView('print.master-card', [
            'type' => 'kontak',
            'title' => 'Kartu Kontak',
            'record' => $kontak,
            'pdf' => true,
        ])->download($this->filename('kontak', $kontak->kode_kontak ?: $kontak->id));
    }

    // ========================================================================
    // PUBLIC INVOICE PDF DOWNLOADS (UUID-based, no auth)
    // Filename format matches legacy: INV/PR/EXP/VST/PAY/GRN-{nomor}.pdf
    // ========================================================================

    public function downloadPenjualan(string $uuid)
    {
        $record = $this->loadTransaction('penjualan', Penjualan::where('uuid', $uuid)->firstOrFail());
        $nomor = $record->nomor ?? $record->custom_number ?? ('INV-'.$record->id);
        $view = view()->exists('public.invoice-penjualan-pdf') ? 'public.invoice-penjualan-pdf' : 'public.invoice';
        $data = view()->exists('public.invoice-penjualan-pdf')
            ? ['penjualan' => $record]
            : $this->documentData('penjualan', $record);

        return Pdf::loadView($view, $data)->setPaper('a4', 'portrait')
            ->download('INV-'.Str::slug($nomor).'.pdf');
    }

    public function downloadPembelian(string $uuid)
    {
        $record = $this->loadTransaction('pembelian', Pembelian::where('uuid', $uuid)->firstOrFail());
        $nomor = $record->nomor ?? $record->custom_number ?? ('PR-'.$record->id);

        return $this->downloadWithTemplate('pembelian', 'PR', $nomor, $record, ['pembelian' => $record]);
    }

    public function downloadBiaya(string $uuid)
    {
        $record = $this->loadTransaction('biaya', Biaya::where('uuid', $uuid)->firstOrFail());
        $nomor = $record->nomor ?? $record->custom_number ?? ('EXP-'.$record->id);

        return $this->downloadWithTemplate('biaya', 'EXP', $nomor, $record, ['biaya' => $record]);
    }

    public function downloadKunjungan(string $uuid)
    {
        $record = $this->loadTransaction('kunjungan', Kunjungan::where('uuid', $uuid)->firstOrFail());
        $nomor = $record->nomor ?? $record->custom_number ?? ('VST-'.$record->id);

        return $this->downloadWithTemplate('kunjungan', 'VST', $nomor, $record, ['kunjungan' => $record]);
    }

    public function downloadPembayaran(string $uuid)
    {
        $record = $this->loadTransaction('pembayaran', Pembayaran::where('uuid', $uuid)->firstOrFail());
        $nomor = $record->nomor ?? $record->custom_number ?? ('PAY-'.$record->id);

        return $this->downloadWithTemplate('pembayaran', 'PAY', $nomor, $record, ['pembayaran' => $record]);
    }

    public function downloadPenerimaanBarang(string $uuid)
    {
        $record = $this->loadTransaction('penerimaan-barang', PenerimaanBarang::where('uuid', $uuid)->firstOrFail());
        $nomor = $record->nomor ?? $record->custom_number ?? ('GRN-'.$record->id);

        return $this->downloadWithTemplate('penerimaan', 'GRN', $nomor, $record, ['penerimaan' => $record]);
    }

    /**
     * Helper: download PDF using specific template if it exists, fallback to generic.
     */
    private function downloadWithTemplate(string $type, string $prefix, string $nomor, Model $record, array $specificData): BinaryFileResponse|Response
    {
        $pdfView = "public.invoice-{$type}-pdf";
        if (view()->exists($pdfView)) {
            $pdf = Pdf::loadView($pdfView, $specificData)->setPaper('a4', 'portrait');
        } else {
            $pdf = Pdf::loadView('public.invoice', $this->documentData($type === 'penerimaan' ? 'penerimaan-barang' : $type, $record))->setPaper('a4', 'portrait');
        }

        return $pdf->download("{$prefix}-".Str::slug($nomor).'.pdf');
    }

    // ========================================================================
    // DELETE LAMPIRAN (Authenticated, super_admin)
    // ========================================================================

    public function deleteLampiranPenjualan(Penjualan $penjualan, int $index)
    {
        return $this->deleteLampiran($penjualan, $index);
    }

    public function deleteLampiranPembelian(Pembelian $pembelian, int $index)
    {
        return $this->deleteLampiran($pembelian, $index);
    }

    public function deleteLampiranBiaya(Biaya $biaya, int $index)
    {
        return $this->deleteLampiran($biaya, $index);
    }

    public function deleteLampiranKunjungan(Kunjungan $kunjungan, int $index)
    {
        return $this->deleteLampiran($kunjungan, $index);
    }

    public function deleteLampiranPembayaran(Pembayaran $pembayaran, int $index)
    {
        return $this->deleteLampiran($pembayaran, $index);
    }

    public function deleteLampiranPenerimaanBarang(PenerimaanBarang $penerimaanBarang, int $index)
    {
        return $this->deleteLampiran($penerimaanBarang, $index);
    }

    private function deleteLampiran(Model $record, int $index)
    {
        if (! auth()->user()?->isSuperAdmin()) {
            abort(403, 'Hanya Super Admin yang dapat menghapus lampiran.');
        }

        $paths = $record->lampiran_paths ?? [];

        if (! isset($paths[$index])) {
            abort(404, 'Lampiran tidak ditemukan.');
        }

        $path = $paths[$index];

        // Delete physical file
        $fullPath = public_path('storage/'.$path);
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }

        // Remove from array and re-index
        array_splice($paths, $index, 1);
        $record->update(['lampiran_paths' => array_values($paths)]);

        return back()->with('success', 'Lampiran berhasil dihapus.');
    }

    private function invoice(string $type, Model $record)
    {
        // Gunakan template spesifik per tipe jika ada, fallback ke generic
        $view = view()->exists("public.invoice-{$type}") ? "public.invoice-{$type}" : 'public.invoice';

        return view($view, $this->documentData($type, $record));
    }

    private function printable(string $type, Model $record)
    {
        return view('print.transaction', $this->documentData($type, $record));
    }

    private function documentData(string $type, Model $record): array
    {
        return [
            'type' => $type,
            'title' => $this->title($type),
            'receiptTitle' => $this->receiptTitle($type),
            'receiptBody' => $this->receiptBody($type, $record),
            'receiptDashLine' => str_repeat('- ', 16),
            'record' => $record,
            'meta' => $this->meta($type, $record),
            'rows' => $this->rows($type, $record),
            'totals' => $this->totals($type, $record),
            'notes' => $this->notes($type, $record),
        ];
    }

    private function receiptTitle(string $type): string
    {
        return match ($type) {
            'penjualan' => 'INVOICE PENJUALAN',
            'pembelian' => 'INVOICE PEMBELIAN',
            'biaya' => 'STRUK BIAYA',
            'kunjungan' => 'STRUK KUNJUNGAN',
            'pembayaran' => 'BUKTI PEMBAYARAN',
            'penerimaan-barang' => 'PENERIMAAN BARANG',
            default => 'STRUK',
        };
    }

    private function receiptBody(string $type, Model $record): string
    {
        $lines = match ($type) {
            'penjualan' => $this->penjualanReceiptLines($record),
            'pembelian' => $this->pembelianReceiptLines($record),
            'biaya' => $this->biayaReceiptLines($record),
            'kunjungan' => $this->kunjunganReceiptLines($record),
            default => $this->genericReceiptLines($type, $record),
        };

        return $this->renderReceiptLines($lines);
    }

    private function penjualanReceiptLines(Model $record): array
    {
        $subtotal = (float) $record->items->sum('jumlah_baris');
        $taxPercentage = (float) ($record->tax_percentage ?? 0);
        $diskonAkhir = (float) ($record->diskon_akhir ?? 0);
        $pajak = max(0, $subtotal - $diskonAkhir) * ($taxPercentage / 100);

        $lines = [
            $this->receiptTwoColumn('Nomor', $record->nomor ?? $record->custom_number ?? '-'),
            $this->receiptTwoColumn('Tanggal', trim(($record->tgl_transaksi?->format('d/m/Y') ?? '-').' | '.($record->created_at?->format('H:i') ?? ''))),
            $this->receiptTwoColumn('Jatuh Tempo', $record->tgl_jatuh_tempo?->format('d/m/Y') ?? '-'),
            $this->receiptTwoColumn('Pembayaran', $record->syarat_pembayaran ?? '-'),
            $this->receiptTwoColumn('Pelanggan', $this->receiptLimit($record->pelanggan)),
            $this->receiptTwoColumn('No. Telepon', $this->receiptPhone($this->penjualanPhone($record)) ?: 'N/A'),
            $this->receiptTwoColumn('Sales', $this->receiptLimit($record->user?->name)),
            $this->receiptTwoColumn('No. Telp Sales', $this->receiptPhone($record->user?->no_telp) ?: 'N/A'),
        ];

        if ($this->filled($record->no_referensi)) {
            $lines[] = $this->receiptTwoColumn('No. Ref', $record->no_referensi);
        }
        if ($this->filled($record->memo)) {
            $lines[] = $this->receiptTwoColumn('Memo', $record->memo);
        }

        $lines[] = '---HR---';

        foreach ($record->items as $item) {
            $productName = trim((string) ($item->produk?->nama_produk ?? $item->deskripsi ?? '-'));
            if ($item->produk?->item_code) {
                $productName .= ' ('.$item->produk->item_code.')';
            }

            $lines[] = $this->receiptWrap($productName);
            $lines[] = $this->receiptTwoColumn('Batch', ($item->batch_number ?: 'N/A').' - '.($item->expired_date?->format('d/m/Y') ?? 'N/A'));
            $lines[] = $this->receiptTwoColumn('Qty', $this->receiptQty($item->kuantitas).' '.($item->unit ?: $item->produk?->satuan ?: 'Pcs').' x '.$this->receiptMoney($item->harga_satuan));

            if ((float) $item->diskon > 0) {
                $lines[] = $this->receiptTwoColumn('Diskon', $this->receiptQty($item->diskon).'%');
            }
            if ((float) ($item->diskon_nominal ?? 0) > 0) {
                $lines[] = $this->receiptTwoColumn('Disc Rp', '- '.$this->receiptMoney($item->diskon_nominal));
            }
            if ($this->filled($item->deskripsi)) {
                $lines[] = $this->receiptTwoColumn('Ket', $item->deskripsi);
            }

            $lines[] = $this->receiptTwoColumn('Jumlah', $this->receiptMoney($item->jumlah_baris));
            $lines[] = null;
        }

        $this->removeTrailingBlank($lines);
        $lines[] = '---HR---';
        $lines[] = $this->receiptTwoColumn('Subtotal', $this->receiptMoney($subtotal));
        if ($diskonAkhir > 0) {
            $lines[] = $this->receiptTwoColumn('Diskon', '- '.$this->receiptMoney($diskonAkhir));
        }
        if ($pajak > 0) {
            $lines[] = $this->receiptTwoColumn('Pajak ('.$this->receiptQty($taxPercentage).'%)', $this->receiptMoney($pajak));
        }
        $lines[] = '---HR---';
        $lines[] = $this->receiptTwoColumn('GRAND TOTAL', $this->receiptMoney($record->grand_total ?? 0));

        return $lines;
    }

    private function pembelianReceiptLines(Model $record): array
    {
        $subtotal = (float) $record->items->sum('jumlah_baris');
        $taxPercentage = (float) ($record->tax_percentage ?? 0);
        $diskonAkhir = (float) ($record->diskon_akhir ?? 0);
        $pajak = $subtotal > 0 && $taxPercentage > 0
            ? round(max(0, $subtotal - $diskonAkhir) * ($taxPercentage / 100), 2)
            : 0;

        $lines = [
            $this->receiptTwoColumn('Nomor', $record->nomor ?? $record->custom_number ?? '-'),
            $this->receiptTwoColumn('Tanggal', $record->tgl_transaksi?->format('d/m/Y') ?? '-'),
            $this->receiptTwoColumn('Jatuh Tempo', $record->tgl_jatuh_tempo?->format('d/m/Y') ?? '-'),
            $this->receiptTwoColumn('Pembayaran', $record->syarat_pembayaran ?? '-'),
        ];

        if ($this->filled($record->urgensi)) {
            $lines[] = $this->receiptTwoColumn('Urgensi', $record->urgensi);
        }

        $lines[] = $this->receiptTwoColumn('Vendor', '-');
        $lines[] = $this->receiptTwoColumn('Dibuat oleh', $this->receiptLimit($record->user?->name));

        if ($this->filled($record->tahun_anggaran)) {
            $lines[] = $this->receiptTwoColumn('Thn Anggaran', $record->tahun_anggaran);
        }
        if ($this->filled($record->staf_penyetuju)) {
            $lines[] = $this->receiptTwoColumn('Staf Penyetuju', $record->staf_penyetuju);
        }
        if ($this->filled($record->memo)) {
            $lines[] = $this->receiptTwoColumn('Memo', $record->memo);
        }

        $lines[] = '---HR---';

        foreach ($record->items as $item) {
            $lines[] = $this->receiptWrap($item->produk?->nama_produk ?? $item->deskripsi ?? '-');
            $lines[] = $this->receiptTwoColumn('Batch', ($item->batch_number ?: 'N/A').' - '.($item->expired_date?->format('d/m/Y') ?? 'N/A'));
            $lines[] = $this->receiptTwoColumn('Qty', $this->receiptQty($item->kuantitas).' '.($item->unit ?: $item->produk?->satuan ?: 'Pcs').' x '.$this->receiptMoney($item->harga_satuan));

            if ((float) $item->diskon > 0) {
                $lines[] = $this->receiptTwoColumn('Diskon', $this->receiptQty($item->diskon).'%');
            }
            if ($this->filled($item->deskripsi)) {
                $lines[] = $this->receiptTwoColumn('Ket', $item->deskripsi);
            }

            $lines[] = $this->receiptTwoColumn('Jumlah', $this->receiptMoney($item->jumlah_baris));
            $lines[] = null;
        }

        $this->removeTrailingBlank($lines);
        $lines[] = '---HR---';
        $lines[] = $this->receiptTwoColumn('Subtotal', $this->receiptMoney($subtotal));
        if ($diskonAkhir > 0) {
            $lines[] = $this->receiptTwoColumn('Diskon', '- '.$this->receiptMoney($diskonAkhir));
        }
        if ($pajak > 0) {
            $lines[] = $this->receiptTwoColumn('Pajak ('.$this->receiptQty($taxPercentage).'%)', $this->receiptMoney($pajak));
        }
        $lines[] = '---HR---';
        $lines[] = $this->receiptTwoColumn('GRAND TOTAL', $this->receiptMoney($record->grand_total ?? 0));

        return $lines;
    }

    private function biayaReceiptLines(Model $record): array
    {
        $subtotal = (float) $record->items->sum('jumlah');
        $taxPercentage = (float) ($record->tax_percentage ?? 0);
        $pajak = $subtotal > 0 && $taxPercentage > 0
            ? round($subtotal * ($taxPercentage / 100), 2)
            : 0;

        $lines = [
            $this->receiptTwoColumn('Nomor', $record->nomor ?? $record->custom_number ?? '-'),
            $this->receiptTwoColumn('Tanggal', $record->tgl_transaksi?->format('d/m/Y') ?? '-'),
            $this->receiptTwoColumn('Jenis Biaya', $record->jenis_biaya ?? '-'),
            $this->receiptTwoColumn('Bayar Dari', $record->bayar_dari ?? '-'),
        ];

        if ($this->filled($record->cara_pembayaran)) {
            $lines[] = $this->receiptTwoColumn('Cara Bayar', $record->cara_pembayaran);
        }

        $lines[] = $this->receiptTwoColumn('Penerima', $record->penerima ?? '-');

        if ($this->filled($record->alamat_penagihan)) {
            $lines[] = $this->receiptTwoColumn('Alamat', $record->alamat_penagihan);
        }

        $lines[] = $this->receiptTwoColumn('Dibuat oleh', $this->receiptLimit($record->user?->name));

        if ($this->filled($record->tag)) {
            $lines[] = $this->receiptTwoColumn('Tag', $record->tag);
        }
        if ($this->filled($record->koordinat)) {
            $lines[] = $this->receiptTwoColumn('Koordinat', $record->koordinat);
        }
        if ($this->filled($record->memo)) {
            $lines[] = $this->receiptTwoColumn('Memo', $record->memo);
        }

        $lines[] = '---HR---';

        foreach ($record->items as $item) {
            $lines[] = $this->receiptWrap($item->kategori ?? '-');
            if ($this->filled($item->deskripsi)) {
                $lines[] = $this->receiptTwoColumn('Deskripsi', $item->deskripsi);
            }
            $lines[] = $this->receiptTwoColumn('Jumlah', $this->receiptMoney($item->jumlah));
            $lines[] = null;
        }

        $this->removeTrailingBlank($lines);
        $lines[] = '---HR---';
        if ($subtotal > 0) {
            $lines[] = $this->receiptTwoColumn('Subtotal', $this->receiptMoney($subtotal));
        }
        if ($pajak > 0) {
            $lines[] = $this->receiptTwoColumn('Pajak ('.$this->receiptQty($taxPercentage).'%)', $this->receiptMoney($pajak));
        }
        $lines[] = '---HR---';
        $lines[] = $this->receiptTwoColumn('GRAND TOTAL', $this->receiptMoney($record->grand_total ?? 0));

        return $lines;
    }

    private function kunjunganReceiptLines(Model $record): array
    {
        $lines = [
            $this->receiptTwoColumn('Nomor', $record->nomor ?? '-'),
            $this->receiptTwoColumn('Tanggal', $record->tgl_kunjungan?->format('d/m/Y') ?? '-'),
            $this->receiptTwoColumn('Tujuan', $record->tujuan ?? '-'),
        ];

        if ($this->filled($record->user?->name)) {
            $lines[] = $this->receiptTwoColumn('Pembuat', $this->receiptLimit($record->user?->name));
        }
        if ($this->filled($record->sales_nama)) {
            $lines[] = $this->receiptTwoColumn('Pelanggan', $this->receiptLimit($record->sales_nama));
        }
        if ($this->filled($record->sales_no_telepon)) {
            $lines[] = $this->receiptTwoColumn('No. Telepon', $this->receiptPhone($record->sales_no_telepon));
        }
        if ($this->filled($record->sales_alamat)) {
            $lines[] = $this->receiptTwoColumn('Alamat', $record->sales_alamat);
        }
        if ($this->filled($record->koordinat)) {
            $lines[] = $this->receiptTwoColumn('Koordinat', $record->koordinat);
        }
        if ($this->filled($record->memo)) {
            $lines[] = $this->receiptTwoColumn('Memo', $record->memo);
        }

        $lines[] = '---HR---';

        foreach ($record->items as $item) {
            $lines[] = $this->receiptWrap($item->produk?->nama_produk ?? '-');
            $lines[] = $this->receiptTwoColumn('Qty', $this->receiptQty($item->jumlah).' '.($item->produk?->satuan ?: 'Pcs'));
            if ($this->filled($item->tipe_stok)) {
                $lines[] = $this->receiptTwoColumn('Tipe', $item->tipe_stok);
            }
            $lines[] = $this->receiptTwoColumn('Batch', $item->batch_number ?: 'N/A');
            $lines[] = $this->receiptTwoColumn('Exp', $item->expired_date?->format('d/m/Y') ?? 'N/A');
            if ($this->filled($item->keterangan)) {
                $lines[] = $this->receiptTwoColumn('Ket', $item->keterangan);
            }
            $lines[] = null;
        }

        $this->removeTrailingBlank($lines);

        return $lines;
    }

    private function genericReceiptLines(string $type, Model $record): array
    {
        $lines = [];

        foreach ($this->meta($type, $record) as $label => $value) {
            $lines[] = $this->receiptTwoColumn($label, $value ?: '-');
        }

        $rows = $this->rows($type, $record);
        if ($rows) {
            $lines[] = '---HR---';
            foreach ($rows as $row) {
                foreach ($row as $label => $value) {
                    $lines[] = $this->receiptTwoColumn($label, $value ?: '-');
                }
                $lines[] = null;
            }
            $this->removeTrailingBlank($lines);
        }

        $totals = $this->totals($type, $record);
        if ($totals) {
            $lines[] = '---HR---';
            foreach ($totals as $label => $value) {
                $lines[] = $this->receiptTwoColumn($label, $value ?: '-');
            }
        }

        return $lines;
    }

    private function renderReceiptLines(array $lines): string
    {
        $output = '';

        foreach ($lines as $line) {
            if ($line === null) {
                $output .= "\n";

                continue;
            }

            if ($line === '---HR---') {
                $output .= str_repeat('-', 32)."\n";

                continue;
            }

            $output .= (string) $line."\n";
        }

        return rtrim($output);
    }

    private function receiptTwoColumn(mixed $left, mixed $right): string
    {
        $leftText = $this->stringValue($left);
        $rightText = $this->stringValue($right) ?: '-';
        $rightWidth = max(1, 32 - $this->textLength($leftText) - 1);
        $chunks = $this->wrapChunks($rightText, $rightWidth);
        $rows = [];

        foreach ($chunks as $index => $chunk) {
            if ($index === 0) {
                $available = 32 - $this->textLength($leftText) - $this->textLength($chunk);
                $rows[] = $leftText.str_repeat(' ', max(1, $available)).$chunk;

                continue;
            }

            $rows[] = $this->receiptRightOnlyLine($chunk);
        }

        return implode("\n", $rows);
    }

    private function receiptRightOnlyLine(mixed $value): string
    {
        $text = $this->stringValue($value);

        return str_repeat(' ', max(0, 32 - $this->textLength($text))).$text;
    }

    private function receiptWrap(mixed $value, int $width = 32): string
    {
        return implode("\n", $this->wrapChunks($this->stringValue($value), $width));
    }

    private function wrapChunks(mixed $value, int $width): array
    {
        $text = $this->stringValue($value);
        if ($text === '') {
            return [''];
        }
        if ($this->textLength($text) <= $width) {
            return [$text];
        }

        $words = preg_split('/\s+/', $text) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            if ($current === '') {
                $current = $word;
            } elseif ($this->textLength($current.' '.$word) <= $width) {
                $current .= ' '.$word;
            } else {
                $lines[] = $current;
                $current = $word;
            }

            while ($this->textLength($current) > $width) {
                $lines[] = $this->textSlice($current, 0, $width);
                $current = $this->textSlice($current, $width);
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private function receiptLimit(mixed $value, int $max = 20): string
    {
        $text = $this->stringValue($value);
        if ($this->textLength($text) <= $max) {
            return $text;
        }
        if ($max <= 3) {
            return $this->textSlice($text, 0, $max);
        }

        return $this->textSlice($text, 0, $max - 3).'...';
    }

    private function penjualanPhone(Model $record): string
    {
        if ($this->filled($record->no_telepon)) {
            return (string) $record->no_telepon;
        }

        if (! $this->filled($record->pelanggan)) {
            return '';
        }

        return (string) (Kontak::where('nama', $record->pelanggan)->value('no_telp') ?? '');
    }

    private function receiptPhone(mixed $value): string
    {
        $raw = $this->stringValue($value);
        if ($raw === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $raw) ?: '';
        if ($digits === '') {
            return $raw;
        }

        if (str_starts_with($digits, '620')) {
            $digits = '62'.substr($digits, 3);
        }

        if (str_starts_with($digits, '62')) {
            return '+62 '.$this->phoneGroups(substr($digits, 2));
        }
        if (str_starts_with($digits, '0')) {
            return '+62 '.$this->phoneGroups(substr($digits, 1));
        }
        if (str_starts_with($digits, '8') && strlen($digits) >= 9) {
            return '+62 '.$this->phoneGroups($digits);
        }
        if (str_starts_with($raw, '+')) {
            return '+'.$this->phoneGroups($digits);
        }

        return $this->phoneGroups($digits);
    }

    private function phoneGroups(string $digits): string
    {
        if (strlen($digits) >= 10) {
            return substr($digits, 0, 3).'-'.substr($digits, 3, 4).'-'.substr($digits, 7);
        }

        return implode('-', str_split($digits, 4));
    }

    private function receiptMoney(mixed $value): string
    {
        return format_rupiah($value);
    }

    private function receiptQty(mixed $value): string
    {
        $number = (float) $value;

        return floor($number) === $number
            ? (string) (int) $number
            : rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }

    private function filled(mixed $value): bool
    {
        return $this->stringValue($value) !== '';
    }

    private function textLength(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }

    private function textSlice(string $text, int $start, ?int $length = null): string
    {
        if (function_exists('mb_substr')) {
            return $length === null ? mb_substr($text, $start) : mb_substr($text, $start, $length);
        }

        return $length === null ? substr($text, $start) : substr($text, $start, $length);
    }

    private function removeTrailingBlank(array &$lines): void
    {
        if ($lines && end($lines) === null) {
            array_pop($lines);
        }
    }

    private function findTransactionByUuid(string $type, string $uuid): Model
    {
        $type = $this->normalizeType($type);

        return match ($type) {
            'penjualan' => $this->loadTransaction('penjualan', Penjualan::where('uuid', $uuid)->firstOrFail()),
            'pembelian' => $this->loadTransaction('pembelian', Pembelian::where('uuid', $uuid)->firstOrFail()),
            'biaya' => $this->loadTransaction('biaya', Biaya::where('uuid', $uuid)->firstOrFail()),
            'kunjungan' => $this->loadTransaction('kunjungan', Kunjungan::where('uuid', $uuid)->firstOrFail()),
            'pembayaran' => $this->loadTransaction('pembayaran', Pembayaran::where('uuid', $uuid)->firstOrFail()),
            'penerimaan-barang' => $this->loadTransaction('penerimaan-barang', PenerimaanBarang::where('uuid', $uuid)->firstOrFail()),
        };
    }

    private function loadTransaction(string $type, Model $record): Model
    {
        return match ($type) {
            'penjualan' => $record->load(['items.produk', 'user', 'approver', 'gudang', 'pembayarans']),
            'pembelian' => $record->load(['items.produk', 'user', 'approver', 'gudang', 'penerimaanBarangs']),
            'biaya' => $record->load(['items', 'user', 'approver', 'gudang']),
            'kunjungan' => $record->load(['items.produk', 'user', 'approver', 'gudang', 'kontak']),
            'pembayaran' => $record->load(['penjualan.items.produk', 'user', 'approver', 'gudang']),
            'penerimaan-barang' => $record->load(['items.produk', 'user', 'approver', 'gudang', 'pembelian']),
        };
    }

    private function normalizeType(string $type): string
    {
        $type = Str::of($type)->lower()->replace('_', '-')->toString();

        return match ($type) {
            'penerimaan', 'penerimaan-barangs' => 'penerimaan-barang',
            default => $type,
        };
    }

    private function title(string $type): string
    {
        return match ($type) {
            'penjualan' => 'Invoice Penjualan',
            'pembelian' => 'Invoice Pembelian',
            'biaya' => 'Invoice Biaya',
            'kunjungan' => 'Detail Kunjungan',
            'pembayaran' => 'Bukti Pembayaran',
            'penerimaan-barang' => 'Penerimaan Barang',
        };
    }

    private function meta(string $type, Model $record): array
    {
        $base = [
            'Nomor' => $record->nomor ?? '-',
            'Status' => $record->status ?? '-',
            'Gudang' => $record->gudang?->nama_gudang ?? '-',
            'Dibuat oleh' => $record->user?->name ?? '-',
            'Approver' => $record->approver?->name ?? '-',
            'Dibuat' => $record->created_at?->format('d/m/Y H:i') ?? '-',
        ];

        return match ($type) {
            'penjualan' => $base + [
                'Tanggal' => $record->tgl_transaksi?->format('d/m/Y') ?? '-',
                'Jatuh tempo' => $record->tgl_jatuh_tempo?->format('d/m/Y') ?? '-',
                'Pelanggan' => $record->pelanggan ?? '-',
                'No telepon' => $record->no_telepon ?? '-',
                'Tipe harga' => ucfirst((string) ($record->tipe_harga ?? '-')),
                'Syarat pembayaran' => $record->syarat_pembayaran ?? '-',
                'No referensi' => $record->no_referensi ?? '-',
                'Tag' => $record->tag ?? '-',
            ],
            'pembelian' => $base + [
                'Tanggal' => $record->tgl_transaksi?->format('d/m/Y') ?? '-',
                'Jatuh tempo' => $record->tgl_jatuh_tempo?->format('d/m/Y') ?? '-',
                'Staf penyetuju' => $record->staf_penyetuju ?? '-',
                'Email penyetuju' => $record->email_penyetuju ?? '-',
                'Urgensi' => $record->urgensi ?? '-',
                'Tahun anggaran' => $record->tahun_anggaran ?? '-',
                'Syarat pembayaran' => $record->syarat_pembayaran ?? '-',
            ],
            'biaya' => $base + [
                'Tanggal' => $record->tgl_transaksi?->format('d/m/Y') ?? '-',
                'Jenis biaya' => ucfirst((string) $record->jenis_biaya),
                'Penerima' => $record->penerima ?? '-',
                'Bayar dari' => $record->bayar_dari ?? '-',
                'Cara pembayaran' => $record->cara_pembayaran ?? '-',
                'Tag' => $record->tag ?? '-',
            ],
            'kunjungan' => $base + [
                'Tanggal' => $record->tgl_kunjungan?->format('d/m/Y') ?? '-',
                'Tujuan' => $record->tujuan ?? '-',
                'Kode kontak' => $record->kontak?->kode_kontak ?? '-',
                'Pelanggan' => $record->sales_nama ?? '-',
                'No telepon' => $record->sales_no_telepon ?? '-',
            ],
            'pembayaran' => $base + [
                'Tanggal' => $record->tgl_pembayaran?->format('d/m/Y') ?? '-',
                'Metode' => $record->metode_pembayaran ?? '-',
                'Invoice penjualan' => $record->penjualan?->nomor ?? '-',
                'Pelanggan' => $record->penjualan?->pelanggan ?? '-',
            ],
            'penerimaan-barang' => $base + [
                'Tanggal' => $record->tgl_penerimaan?->format('d/m/Y') ?? '-',
                'No surat jalan' => $record->no_surat_jalan ?? '-',
                'Invoice pembelian' => $record->pembelian?->nomor ?? '-',
            ],
        };
    }

    private function rows(string $type, Model $record): array
    {
        return match ($type) {
            'penjualan' => $record->items->map(fn ($item) => [
                'Produk' => $item->produk?->nama_produk ?? $item->deskripsi ?? '-',
                'Kode' => $item->produk?->item_code ?? '-',
                'Qty' => $this->qty($item->kuantitas, $item->unit ?: $item->produk?->satuan),
                'Harga' => $this->money($item->harga_satuan),
                'Diskon' => $this->discount($item->diskon, $item->diskon_nominal ?? 0),
                'Batch/Exp' => trim(($item->batch_number ?? '-').' / '.($item->expired_date?->format('d/m/Y') ?? '-')),
                'Total' => $this->money($item->jumlah_baris),
            ])->all(),
            'pembelian' => $record->items->map(fn ($item) => [
                'Produk' => $item->produk?->nama_produk ?? $item->deskripsi ?? '-',
                'Kode' => $item->produk?->item_code ?? '-',
                'Qty' => $this->qty($item->kuantitas, $item->unit ?: $item->produk?->satuan),
                'Harga' => $this->money($item->harga_satuan),
                'Diskon' => $this->discount($item->diskon, 0),
                'Total' => $this->money($item->jumlah_baris),
            ])->all(),
            'biaya' => $record->items->map(fn ($item) => [
                'Kategori' => $item->kategori ?? '-',
                'Deskripsi' => $item->deskripsi ?? '-',
                'Jumlah' => $this->money($item->jumlah),
            ])->all(),
            'kunjungan' => $record->items->map(fn ($item) => [
                'Produk' => $item->produk?->nama_produk ?? '-',
                'Kode' => $item->produk?->item_code ?? '-',
                'Qty' => $this->qty($item->jumlah, $item->produk?->satuan),
                'Batch' => $item->batch_number ?? '-',
                'Expired' => $item->expired_date?->format('d/m/Y') ?? '-',
                'Keterangan' => $item->keterangan ?? '-',
            ])->all(),
            'pembayaran' => [[
                'Invoice' => $record->penjualan?->nomor ?? '-',
                'Pelanggan' => $record->penjualan?->pelanggan ?? '-',
                'Total Invoice' => $this->money($record->penjualan?->grand_total ?? 0),
                'Jumlah Bayar' => $this->money($record->jumlah_bayar),
                'Sisa Hutang' => $this->money(max(0, (float) ($record->penjualan?->grand_total ?? 0) - (float) $record->jumlah_bayar)),
            ]],
            'penerimaan-barang' => $record->items->map(fn ($item) => [
                'Produk' => $item->produk?->nama_produk ?? '-',
                'Kode' => $item->produk?->item_code ?? '-',
                'Tipe Stok' => ucfirst((string) $item->tipe_stok),
                'Batch' => $item->batch_number ?? '-',
                'Expired' => $item->expired_date?->format('d/m/Y') ?? '-',
                'Qty Diterima' => $item->qty_diterima,
                'Qty Reject' => $item->qty_reject,
            ])->all(),
        };
    }

    private function totals(string $type, Model $record): array
    {
        return match ($type) {
            'penjualan', 'pembelian' => [
                'Subtotal' => $this->money($record->items->sum('jumlah_baris')),
                'Diskon akhir' => $this->money($record->diskon_akhir ?? 0),
                'Pajak' => $this->percent($record->tax_percentage ?? 0),
                'Grand total' => $this->money($record->grand_total ?? 0),
            ],
            'biaya' => [
                'Subtotal' => $this->money($record->items->sum('jumlah')),
                'Pajak' => $this->percent($record->tax_percentage ?? 0),
                'Grand total' => $this->money($record->grand_total ?? 0),
            ],
            'pembayaran' => [
                'Jumlah bayar' => $this->money($record->jumlah_bayar ?? 0),
            ],
            'penerimaan-barang' => [
                'Total qty diterima' => number_format((float) $record->items->sum('qty_diterima'), 0, ',', '.'),
                'Total qty reject' => number_format((float) $record->items->sum('qty_reject'), 0, ',', '.'),
            ],
            default => [],
        };
    }

    private function notes(string $type, Model $record): ?string
    {
        return match ($type) {
            'pembayaran', 'penerimaan-barang' => $record->keterangan,
            default => $record->memo,
        };
    }

    private function qty(mixed $value, ?string $unit): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',').' '.($unit ?: 'Pcs');
    }

    private function money(mixed $value): string
    {
        return format_rupiah($value);
    }

    private function percent(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',').'%';
    }

    private function discount(mixed $percent, mixed $nominal): string
    {
        $parts = [];
        if ((float) $percent > 0) {
            $parts[] = $this->percent($percent);
        }
        if ((float) $nominal > 0) {
            $parts[] = $this->money($nominal);
        }

        return $parts ? implode(' + ', $parts) : '-';
    }

    private function filename(string $prefix, string|int $identifier): string
    {
        return Str::slug($prefix.'-'.$identifier).'.pdf';
    }
}
