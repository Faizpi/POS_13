<?php

namespace App\Http\Controllers;

use App\Models\Penjualan;
use App\Models\Pembelian;
use App\Models\Biaya;
use Illuminate\Http\Request;

class PrintController extends Controller
{
    // ========================================================================
    // KONFIGURASI GLOBAL (32 KARAKTER)
    // ========================================================================
    const W = 32;

    // Command Printer
    const ESC_RESET = "\x1B\x40";
    const ESC_BOLD_ON = "\x1B\x45\x01";
    const ESC_BOLD_OFF = "\x1B\x45\x00";
    const ESC_ALIGN_LEFT = "\x1B\x61\x00";
    const ESC_ALIGN_CENTER = "\x1B\x61\x01";

    const DASH = "--------------------------------\r\n";
    const DOUBLE = "================================\r\n";

    private function rp($val)
    {
        return format_rupiah($val);
    }

    /**
     * Format Baris Info (Label : Value)
     */
    private function fmtRow($label, $value)
    {
        $val = trim((string) ($value ?? '-'));
        if ($val === '')
            $val = '-';

        $colLabel = 12;
        $colVal = 18; 

        $lines = explode("\n", wordwrap($val, $colVal, "\n", true));
        $out = "";

        foreach ($lines as $i => $line) {
            if ($i === 0) {
                $out .= str_pad(substr($label, 0, $colLabel), $colLabel, " ") . ": " . $line . "\r\n";
            } else {
                $out .= str_repeat(" ", $colLabel + 2) . $line . "\r\n";
            }
        }
        return $out;
    }

    /**
     * Format Kiri ... Kanan (Align Justify)
     */
    private function fmtJustify($left, $right)
    {
        $lenLeft = strlen($left);
        $lenRight = strlen($right);

        $spaces = self::W - $lenLeft - $lenRight;

        if ($spaces < 1) {
            $spaces = 1;
            $maxLeft = self::W - $lenRight - 1;
            if ($maxLeft > 0) {
                $left = substr($left, 0, $maxLeft);
            }
        }

        return $left . str_repeat(" ", $spaces) . $right . "\r\n";
    }

    private function divHeader($title)
    {
        $out = self::ESC_RESET; 
        $out .= self::ESC_ALIGN_CENTER;
        $out .= self::ESC_BOLD_ON . "HIBISCUS EFSYA" . self::ESC_BOLD_OFF . "\r\n";
        $out .= strtoupper($title) . "\r\n";
        $out .= self::ESC_ALIGN_LEFT; 
        $out .= "\r\n";
        return $out;
    }

    private function divInfo($dataMap)
    {
        $out = "";
        foreach ($dataMap as $k => $v)
            $out .= $this->fmtRow($k, $v);
        return $out;
    }

    private function divItems($items)
    {
        $out = self::DASH;

        foreach ($items as $item) {
            $out .= self::ESC_BOLD_OFF;

            $nama = $item->produk?->nama_produk . ($item->produk?->item_code ? " (" . $item->produk->item_code . ")" : "");
            if (!$item->produk && $item->deskripsi) {
                $nama = $item->deskripsi;
            }

            $out .= self::ESC_BOLD_ON . $nama . self::ESC_BOLD_OFF . "\r\n";

            $out .= self::ESC_BOLD_OFF; 
            $qtyStr = "Qty " . $item->kuantitas . " " . ($item->unit ?? $item->produk?->satuan ?? 'Pcs');
            $out .= $this->fmtJustify($qtyStr, "");

            $out .= $this->fmtJustify("Harga", $this->rp($item->harga_satuan));

            if ($item->diskon > 0) {
                $out .= $this->fmtJustify("Disc", ($item->diskon + 0) . "%");
            }
            if (($item->diskon_nominal ?? 0) > 0) {
                $out .= $this->fmtJustify("Disc Rp", "- " . $this->rp($item->diskon_nominal));
            }

            if ($item->batch_number) {
                $out .= $this->fmtJustify("Batch", $item->batch_number);
            }

            if ($item->expired_date) {
                $out .= $this->fmtJustify("Exp", $item->expired_date->format('d/m/Y'));
            }

            $out .= $this->fmtJustify("Jumlah", $this->rp($item->jumlah_baris));
        }
        return $out;
    }

    private function divSubtotal($subtotal, $disc, $taxPct)
    {
        $out = self::DASH;
        $out .= $this->fmtJustify("Subtotal", $this->rp($subtotal));

        if ($disc > 0) {
            $out .= $this->fmtJustify("Diskon Akhir", "- " . $this->rp($disc));
        }

        if ($taxPct > 0) {
            $tax = max(0, $subtotal - $disc) * ($taxPct / 100);
            $out .= $this->fmtJustify("Pajak (" . ($taxPct + 0) . "%)", $this->rp($tax));
        }
        return $out;
    }

    private function divGrandTotal($val)
    {
        $out = self::DASH;
        $out .= self::ESC_BOLD_ON;
        $out .= $this->fmtJustify("GRAND TOTAL", $this->rp($val));
        $out .= self::ESC_BOLD_OFF;
        return $out;
    }

    private function divSeparator()
    {
        return "\r\n" . self::DOUBLE . "\r\n";
    }

    private function divFooter()
    {
        $out = self::ESC_ALIGN_CENTER;
        $out .= "marketing@hibiscusefsya.com\r\n";
        $out .= "-- Terima Kasih --\r\n";
        $out .= "\r\n\r\n\r\n\r\n\r\n"; 
        return $out;
    }

    public function penjualanRichText($id)
    {
        $data = Penjualan::with(['items.produk', 'user', 'gudang'])->findOrFail($id);

        $p = "";
        $p .= $this->divHeader("INVOICE PENJUALAN");

        $p .= $this->divInfo([
            "Nomor" => $data->nomor ?? $data->custom_number ?? ("INV-" . $data->id),
            "Tanggal" => ($data->tgl_transaksi ?? $data->created_at)->format('d/m/Y') . " | " . $data->created_at->format('H:i'),
            "Jatuh Tempo" => $data->tgl_jatuh_tempo ? $data->tgl_jatuh_tempo->format('d/m/Y') : '-',
            "Pembayaran" => $data->syarat_pembayaran,
            "Pelanggan" => $data->pelanggan,
            "Ref" => $data->no_referensi,
            "Sales" => optional($data->user)->name,
            "Disetujui" => ($data->status != 'Pending' && $data->approver) ? $data->approver->name : '-',
            "Gudang" => optional($data->gudang)->nama_gudang,
            "Status" => $data->status ?: '-'
        ]);

        $p .= $this->divItems($data->items);
        $p .= $this->divSubtotal(collect($data->items)->sum('jumlah_baris'), $data->diskon_akhir, $data->tax_percentage);
        $p .= $this->divGrandTotal($data->grand_total);
        $p .= $this->divSeparator();
        $p .= $this->divFooter();

        return response($p)->header('Content-Type', 'text/plain; charset=utf-8');
    }

    public function pembelianRichText($id)
    {
        $data = Pembelian::with(['items.produk', 'user', 'gudang'])->findOrFail($id);

        $p = $this->divHeader("PERMINTAAN PEMBELIAN");
        $p .= $this->divInfo([
            "Nomor" => $data->nomor ?? $data->custom_number ?? ("PR-" . $data->id),
            "Tanggal" => ($data->tgl_transaksi ?? $data->created_at)->format('d/m/Y'),
            "Vendor" => $data->staf_penyetuju,
            "Sales" => optional($data->user)->name,
            "Gudang" => optional($data->gudang)->nama_gudang,
            "Status" => $data->status
        ]);

        $p .= $this->divItems($data->items);
        $p .= self::DASH;
        $p .= $this->divGrandTotal($data->grand_total);
        $p .= $this->divSeparator();
        $p .= $this->divFooter();

        return response($p)->header('Content-Type', 'text/plain; charset=utf-8');
    }

    public function biayaRichText($id)
    {
        $data = Biaya::with(['items', 'user', 'gudang'])->findOrFail($id);

        $p = $this->divHeader("BUKTI PENGELUARAN");
        $p .= $this->divInfo([
            "Nomor" => $data->nomor ?? $data->custom_number ?? ("EXP-" . $data->id),
            "Tanggal" => ($data->tgl_transaksi ?? $data->created_at)->format('d/m/Y'),
            "Gudang" => optional($data->gudang)->nama_gudang ?? '-',
            "Penerima" => $data->penerima,
            "Sales" => optional($data->user)->name,
            "Status" => $data->status
        ]);

        $p .= self::DASH;
        foreach ($data->items as $item) {
            $p .= self::ESC_BOLD_ON . $item->kategori . self::ESC_BOLD_OFF . "\r\n";
            if ($item->deskripsi)
                $p .= "Ket: " . $item->deskripsi . "\r\n";
            $p .= $this->fmtJustify("Jumlah", $this->rp($item->jumlah));
        }

        $p .= self::DASH;
        $p .= $this->divGrandTotal($data->grand_total);
        $p .= $this->divSeparator();
        $p .= $this->divFooter();

        return response($p)->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
