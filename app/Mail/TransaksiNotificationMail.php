<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TransaksiNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $transaksi;

    public string $type;               // penjualan, pembelian, biaya, kunjungan

    public string $notificationType;   // created, needs_approval, approved

    public ?string $pdfContent;

    public function __construct($transaksi, string $type, string $notificationType, ?string $pdfContent = null)
    {
        $this->transaksi = $transaksi;
        $this->type = $type;
        $this->notificationType = $notificationType;
        $this->pdfContent = $pdfContent;
    }

    public function build(): static
    {
        $typeLabels = [
            'penjualan' => 'Penjualan',
            'pembelian' => 'Pembelian',
            'biaya' => 'Biaya',
            'kunjungan' => 'Kunjungan',
        ];

        $notificationLabels = [
            'created' => 'Transaksi Baru Dibuat',
            'needs_approval' => 'Menunggu Persetujuan',
            'approved' => 'Telah Disetujui',
        ];

        $label = $typeLabels[$this->type] ?? 'Transaksi';
        $notifLabel = $notificationLabels[$this->notificationType] ?? 'Notifikasi';
        $nomor = $this->transaksi->nomor
                   ?? $this->transaksi->custom_number
                   ?? $this->transaksi->id;

        // Pakai template spesifik per tipe, fallback ke generic
        $viewName = "emails.invoice-{$this->type}";
        if (! view()->exists($viewName)) {
            $viewName = 'emails.transaksi-notification';
        }

        $mail = $this->subject("[{$notifLabel}] {$label} #{$nomor} - Hibiscus Efsya")
            ->view($viewName)
            ->with([
                'transaksi' => $this->transaksi,
                'type' => $this->type,
                'notificationType' => $this->notificationType,
            ]);

        if ($this->pdfContent) {
            $mail->attachData($this->pdfContent, "invoice-{$this->type}-{$nomor}.pdf", [
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}
