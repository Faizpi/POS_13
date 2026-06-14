<?php

namespace App\Filament\Resources\Penjualans\Pages;

use App\Filament\Concerns\ResolvesApprover;
use App\Filament\Concerns\RenamesLampiran;
use App\Filament\Resources\Penjualans\PenjualanResource;
use App\Models\GudangProduk;
use App\Models\Penjualan;
use App\Services\InvoiceEmailService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePenjualan extends CreateRecord
{
    use ResolvesApprover, RenamesLampiran;

    protected static string $resource = PenjualanResource::class;

    /**
     * Validasi stok sebelum create — dijalankan sebelum record disimpan.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        // 1. Generate nomor urut harian
        $countToday = Penjualan::where('user_id', auth()->id())
            ->whereDate('created_at', Carbon::today())
            ->count();
        $noUrut = $countToday + 1;
        $now    = Carbon::now();

        $data['user_id']        = auth()->id();
        $data['status']         = 'Pending'; // Default, akan di-update berdasarkan syarat_pembayaran
        $data['no_urut_harian'] = $noUrut;
        $data['nomor']          = Penjualan::generateNomor(auth()->id(), $noUrut, $now);

        // 2. Set approver_id
        $gudangId = (int) ($data['gudang_id'] ?? 0);
        $data['approver_id'] = $this->resolveApproverId($gudangId ?: null);

        // 3. Set tag (nama sales)
        if (empty($data['tag'])) {
            $data['tag'] = $user->name;
        }

        // 4. Set tanggal jatuh tempo berdasarkan syarat pembayaran (Cash = null, Net X = hitung tanggal)
        $term = $data['syarat_pembayaran'] ?? 'Cash'; // Default ke Cash jika tidak diisi
        $isCash = ($term == 'Cash');

        if ($isCash) {
            $data['tgl_jatuh_tempo'] = null;
        } else {
            $tglJatuhTempo = Carbon::parse($data['tgl_transaksi'] ?? $now);
            if ($term == 'Net 7') $tglJatuhTempo->addDays(7);
            elseif ($term == 'Net 14') $tglJatuhTempo->addDays(14);
            elseif ($term == 'Net 30') $tglJatuhTempo->addDays(30);
            elseif ($term == 'Net 60') $tglJatuhTempo->addDays(60);
            $data['tgl_jatuh_tempo'] = $tglJatuhTempo;
        }

        // 5. Validasi stok untuk setiap item
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $produkId = $item['produk_id'] ?? null;
                $qty      = (float) ($item['kuantitas'] ?? 0);
                if (!$produkId) continue;

                $stokGudang  = GudangProduk::where('gudang_id', $gudangId)
                    ->where('produk_id', $produkId)
                    ->first();
                $stokTersedia = $stokGudang?->stok_penjualan ?? 0;

                if ($stokTersedia < $qty) {
                    $namaProduk = $stokGudang?->produk?->nama_produk ?? "ID: {$produkId}";
                    Notification::make()
                        ->title("Stok tidak cukup: {$namaProduk}")
                        ->body("Tersedia: {$stokTersedia}, Diminta: {$qty}")
                        ->danger()
                        ->persistent()
                        ->send();

                    $this->halt();
                }
            }
        }

        return $data;
    }

    /**
     * Setelah record tersimpan: kirim email notifikasi.
     */
    protected function afterCreate(): void
    {
        $this->renameLampiranFiles();

        try {
            InvoiceEmailService::sendCreatedNotification($this->getRecord(), 'penjualan');
        } catch (\Throwable $e) {
            \Log::warning('Email notifikasi penjualan gagal: ' . $e->getMessage());
        }

        try {
            \App\Services\WhatsappNotificationService::sendPenjualanCreated($this->getRecord());
        } catch (\Throwable $e) {
            \Log::warning('WA notifikasi penjualan gagal: ' . $e->getMessage());
        }
    }

    /**
     * Redirect ke halaman view (detail) setelah create — bukan ke index.
     */
    protected function getRedirectUrl(): string
    {
        return PenjualanResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    public function getFormAttributes(): array
    {
        return [
            'x-init' => 'setTimeout(() => { if (window.posAutoFillKoordinat) posAutoFillKoordinat(); }, 1500)',
        ];
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Penjualan berhasil diajukan dan menunggu approval.';
    }
}
