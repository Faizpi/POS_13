<?php

namespace App\Services;

use App\Models\Kontak;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class WhatsappNotificationService
{
    /**
     * Kirim notifikasi WA saat penjualan baru dibuat.
     * - Customer: nomor dari penjualan / kontak
     * - Admin: semua super_admin + admin gudang yang punya no_telp
     */
    public static function sendPenjualanCreated($penjualan): void
    {
        $id = $penjualan->id;

        dispatch(function () use ($id): void {
            try {
                $penjualan = \App\Models\Penjualan::with(["items.produk", "user", "gudang"])
                    ->find($id);

                if (!$penjualan) return;

                $fonnte  = new FonnteService();
                $nomor   = $penjualan->nomor ?? $id;
                $uuid    = $penjualan->uuid ?? null;
                $tgl     = $penjualan->tgl_transaksi
                    ? \Carbon\Carbon::parse($penjualan->tgl_transaksi)->format("d/m/Y")
                    : "-";
                $sales   = $penjualan->user->name ?? "-";
                $gudang  = $penjualan->gudang->nama_gudang ?? "-";
                $total   = "Rp " . number_format($penjualan->grand_total ?? 0, 0, ",", ".");

                // URL public invoice (UUID-based, no auth needed)
                $invoiceUrl  = $uuid
                    ? url("/invoice/penjualan/{$uuid}")
                    : null;

                // URL customer portal
                $portalUrl = url("/customer");

                // Rincian item
                $itemLines = "";
                foreach (($penjualan->items ?? []) as $item) {
                    $qty    = $item->kuantitas;
                    $nama   = $item->nama_produk ?? $item->produk->nama_produk ?? "-";
                    $satuan = $item->unit ?? $item->satuan ?? "pcs";
                    $harga  = "Rp " . number_format($item->harga_satuan ?? 0, 0, ",", ".");
                    $sub    = "Rp " . number_format($item->total ?? 0, 0, ",", ".");
                    $itemLines .= "\n  - {$nama} {$qty} {$satuan} x {$harga} = {$sub}";
                }

                // Pesan untuk Customer
                $msgCustomer = "Halo *{$penjualan->pelanggan}*!\n\n"
                    . "Terima kasih telah berbelanja di *Hibiscus Efsya*.\n"
                    . "Berikut ringkasan pesanan Anda:\n\n"
                    . "No. Invoice : {$nomor}\n"
                    . "Tanggal     : {$tgl}\n"
                    . "Gudang      : {$gudang}\n"
                    . "Sales       : {$sales}\n"
                    . "Pembayaran  : {$penjualan->syarat_pembayaran}\n"
                    . "\n*Detail Pesanan:*{$itemLines}\n"
                    . "\n*Total: {$total}*\n";

                if ($invoiceUrl) {
                    $msgCustomer .= "\n*Invoice Anda:*\n{$invoiceUrl}\n";
                }

                $msgCustomer .= "\n*Cek riwayat transaksi Anda di portal:*\n{$portalUrl}\n"
                    . "\nTerima kasih atas kepercayaan Anda!";

                // Pesan untuk Admin
                $msgAdmin = "*Penjualan Baru Masuk!*\n\n"
                    . "No. Invoice : {$nomor}\n"
                    . "Tanggal     : {$tgl}\n"
                    . "Pelanggan   : {$penjualan->pelanggan}\n"
                    . "Gudang      : {$gudang}\n"
                    . "Sales       : {$sales}\n"
                    . "Pembayaran  : {$penjualan->syarat_pembayaran}\n"
                    . "\n*Detail:*{$itemLines}\n"
                    . "\n*Grand Total: {$total}*\n"
                    . "\nStatus: *Pending* - menunggu approval.";

                if ($invoiceUrl) {
                    $msgAdmin .= "\n\n*Lihat Invoice:*\n{$invoiceUrl}";
                }

                // 1. Kirim ke customer
                $customerPhone = self::resolveCustomerPhone($penjualan);
                if ($customerPhone) {
                    $fonnte->send($customerPhone, $msgCustomer);
                    Log::info("WA customer [{$nomor}] -> {$customerPhone}");
                } else {
                    Log::info("WA customer [{$nomor}]: nomor tidak ditemukan, skip.");
                }

                // 2. Kirim ke admin (super_admin + admin gudang yang punya no_telp)
                $adminPhones = self::getAdminPhones($penjualan->gudang_id);
                foreach ($adminPhones as $phone) {
                    $fonnte->send($phone, $msgAdmin);
                    Log::info("WA admin [{$nomor}] -> {$phone}");
                }

            } catch (\Throwable $e) {
                Log::error("WhatsappNotificationService::sendPenjualanCreated [#{$id}]: " . $e->getMessage());
            }
        })->afterResponse();
    }

    /**
     * Resolve nomor HP customer dari penjualan atau tabel kontak.
     */
    private static function resolveCustomerPhone($penjualan): ?string
    {
        if (!empty($penjualan->no_telepon)) {
            return $penjualan->no_telepon;
        }

        if (!empty($penjualan->pelanggan)) {
            $kontak = Kontak::where("nama", $penjualan->pelanggan)->first();
            if ($kontak && !empty($kontak->no_telp)) {
                return $kontak->no_telp;
            }
        }

        return null;
    }

    /**
     * Ambil nomor HP semua admin yang relevan (super_admin + admin gudang).
     *
     * @return array<string>
     */
    private static function getAdminPhones(?int $gudangId = null): array
    {
        $phones = User::where("role", "super_admin")
            ->whereNotNull("no_telp")
            ->where("no_telp", "!=", "")
            ->pluck("no_telp")
            ->toArray();

        if ($gudangId) {
            $adminPhones = User::where("role", "admin")
                ->whereNotNull("no_telp")
                ->where("no_telp", "!=", "")
                ->where(function ($q) use ($gudangId) {
                    $q->where("gudang_id", $gudangId)
                      ->orWhereHas("gudangs", fn($s) => $s->where("gudangs.id", $gudangId));
                })
                ->pluck("no_telp")
                ->toArray();

            $phones = array_merge($phones, $adminPhones);
        }

        return array_values(array_unique(array_filter($phones)));
    }
}
