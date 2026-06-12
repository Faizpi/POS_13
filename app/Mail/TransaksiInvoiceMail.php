<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TransaksiInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public $transaksi;
    public string $type;    // penjualan, pembelian, biaya, kunjungan
    public string $pdfContent;

    public function __construct($transaksi, string $type, string $pdfContent)
    {
        $this->transaksi   = $transaksi;
        $this->type        = $type;
        $this->pdfContent  = $pdfContent;
    }

    public function build(): static
    {
        $typeLabels = [
            'penjualan' => 'Penjualan',
            'pembelian' => 'Pembelian',
            'biaya'     => 'Biaya',
            'kunjungan' => 'Kunjungan',
        ];

        $label = $typeLabels[$this->type] ?? 'Transaksi';
        $nomor = $this->transaksi->nomor
            ?? $this->transaksi->custom_number
            ?? $this->transaksi->id;

        return $this->subject("Invoice {$label} #{$nomor} - Hibiscus Efsya")
            ->view('emails.transaksi-invoice')
            ->with([
                'transaksi' => $this->transaksi,
                'type'      => $this->type,
            ])
            ->attachData($this->pdfContent, "invoice-{$this->type}-{$nomor}.pdf", [
                'mime' => 'application/pdf',
            ]);
    }
}
