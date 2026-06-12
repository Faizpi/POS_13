<?php

namespace App\Services;

use App\Mail\TransaksiInvoiceMail;
use App\Mail\TransaksiNotificationMail;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class InvoiceEmailService
{
    /**
     * Kirim email invoice ke creator.
     */
    public static function sendInvoice($transaksi, string $type, ?string $toEmail = null): bool
    {
        try {
            $relations = self::relationsFor($type);
            $transaksi->load($relations);

            $pdf = Pdf::loadView("public.invoice-{$type}-pdf", [$type => $transaksi]);
            $pdf->setPaper('a4', 'portrait');
            $pdfContent = $pdf->output();

            $email = $toEmail ?? $transaksi->user?->email;
            if (!$email) return false;

            Mail::to($email)->send(new TransaksiInvoiceMail($transaksi, $type, $pdfContent));
            return true;
        } catch (\Throwable $e) {
            Log::error("InvoiceEmailService::sendInvoice [{$type}#{$transaksi->id}]: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Notifikasi async setelah transaksi dibuat.
     * Dikirim ke: creator (created) + approvers (needs_approval)
     */
    public static function sendCreatedNotification($transaksi, string $type): void
    {
        $id    = $transaksi->id;
        $class = get_class($transaksi);

        dispatch(function () use ($id, $class, $type): void {
            try {
                $transaksi = $class::find($id);
                if (!$transaksi) return;

                $transaksi->load(self::relationsFor($type));

                $pdf = Pdf::loadView("public.invoice-{$type}-pdf", [$type => $transaksi]);
                $pdf->setPaper('a4', 'portrait');
                $pdfContent = $pdf->output();

                $nomor     = $transaksi->nomor ?? $transaksi->custom_number ?? $transaksi->id;
                $gudangId  = $transaksi->gudang_id;

                // 1. Creator
                $creatorEmail = $transaksi->user?->email;
                if ($creatorEmail) {
                    Mail::to($creatorEmail)->send(new TransaksiNotificationMail($transaksi, $type, 'created', $pdfContent));
                    Log::info("Email created [{$type}#{$nomor}] → {$creatorEmail}");
                }

                // 2. Approvers (admin gudang + super_admin yang aktif terima email)
                foreach (self::getApproverEmails($gudangId) as $email) {
                    if ($email === $creatorEmail) continue;
                    Mail::to($email)->send(new TransaksiNotificationMail($transaksi, $type, 'needs_approval', $pdfContent));
                    Log::info("Email needs_approval [{$type}#{$nomor}] → {$email}");
                }
            } catch (\Throwable $e) {
                Log::error("InvoiceEmailService::sendCreatedNotification [{$type}#{$id}]: " . $e->getMessage());
            }
        })->afterResponse();
    }

    /**
     * Notifikasi async setelah transaksi diapprove.
     * Dikirim ke: creator (approved)
     */
    public static function sendApprovedNotification($transaksi, string $type): void
    {
        $id    = $transaksi->id;
        $class = get_class($transaksi);

        dispatch(function () use ($id, $class, $type): void {
            try {
                $transaksi = $class::find($id);
                if (!$transaksi) return;

                $transaksi->load(self::relationsFor($type, true));

                $pdf = Pdf::loadView("public.invoice-{$type}-pdf", [$type => $transaksi]);
                $pdf->setPaper('a4', 'portrait');
                $pdfContent = $pdf->output();

                $nomor        = $transaksi->nomor ?? $transaksi->custom_number ?? $transaksi->id;
                $creatorEmail = $transaksi->user?->email;

                if ($creatorEmail) {
                    Mail::to($creatorEmail)->send(new TransaksiNotificationMail($transaksi, $type, 'approved', $pdfContent));
                    Log::info("Email approved [{$type}#{$nomor}] → {$creatorEmail}");
                }
            } catch (\Throwable $e) {
                Log::error("InvoiceEmailService::sendApprovedNotification [{$type}#{$id}]: " . $e->getMessage());
            }
        })->afterResponse();
    }

    /**
     * Ambil email semua penerima approval (admin gudang + super_admin aktif).
     */
    public static function getApproverEmails(?int $gudangId = null): array
    {
        $emails = User::where('role', 'super_admin')
            ->where('receives_transaction_email', true)
            ->pluck('email')
            ->toArray();

        if ($gudangId) {
            $adminEmails = User::where('role', 'admin')
                ->where('receives_transaction_email', true)
                ->where(function ($q) use ($gudangId) {
                    $q->where('gudang_id', $gudangId)
                      ->orWhereHas('gudangs', fn($s) => $s->where('gudangs.id', $gudangId));
                })
                ->pluck('email')
                ->toArray();

            $emails = array_merge($emails, $adminEmails);
        }

        return array_values(array_unique(array_filter($emails)));
    }

    /** Eager-load relations per tipe transaksi. */
    private static function relationsFor(string $type, bool $withApprover = false): array
    {
        $base = match($type) {
            'penjualan' => ['items.produk', 'user', 'gudang'],
            'pembelian' => ['items.produk', 'user', 'gudang'],
            'kunjungan' => ['items.produk', 'user', 'gudang', 'kontak'],
            default     => ['items', 'user', 'gudang'],
        };

        return $withApprover ? array_merge($base, ['approver']) : $base;
    }
}
