<?php

namespace App\Filament\Resources\Pembelians\Pages;

use App\Filament\Concerns\ResolvesApprover;
use App\Filament\Concerns\RenamesLampiran;
use App\Filament\Resources\Pembelians\PembelianResource;
use App\Models\Pembelian;
use App\Services\InvoiceEmailService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;

class CreatePembelian extends CreateRecord
{
    use ResolvesApprover, RenamesLampiran;

    protected static string $resource = PembelianResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        // Generate nomor urut harian
        $countToday = Pembelian::where('user_id', auth()->id())
            ->whereDate('created_at', Carbon::today())
            ->count();
        $noUrut = $countToday + 1;
        $now    = Carbon::now();

        $data['user_id']        = auth()->id();
        $data['status']         = 'Pending';
        $data['no_urut_harian'] = $noUrut;
        $data['nomor']          = Pembelian::generateNomor(auth()->id(), $noUrut, $now);

        // Set approver_id dan staf_penyetuju
        $gudangId = (int) ($data['gudang_id'] ?? 0);
        $data['approver_id']    = $this->resolveApproverId($gudangId ?: null);
        $data['staf_penyetuju'] = $this->resolveStafPenyetuju($gudangId ?: null);

        // Set tag (nama sales)
        if (empty($data['tag'])) {
            $data['tag'] = $user->name;
        }

        // Set jatuh tempo berdasarkan syarat pembayaran
        $term = $data['syarat_pembayaran'] ?? 'Cash';
        if ($term === 'Cash') {
            $data['tgl_jatuh_tempo'] = null;
        } else {
            $tglJatuhTempo = \Carbon\Carbon::parse($data['tgl_transaksi'] ?? $now);
            $days = ['Net 7' => 7, 'Net 14' => 14, 'Net 30' => 30, 'Net 60' => 60];
            if (isset($days[$term])) {
                $tglJatuhTempo->addDays($days[$term]);
            }
            $data['tgl_jatuh_tempo'] = $tglJatuhTempo;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->renameLampiranFiles();

        try {
            InvoiceEmailService::sendCreatedNotification($this->getRecord(), 'pembelian');
        } catch (\Throwable $e) {
            \Log::warning('Email notifikasi pembelian gagal: ' . $e->getMessage());
        }
    }

    protected function getRedirectUrl(): string
    {
        return PembelianResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    public function getFormAttributes(): array
    {
        return [
            'x-init' => 'setTimeout(() => { if (window.posAutoFillKoordinat) posAutoFillKoordinat(); }, 1500)',
        ];
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Permintaan pembelian berhasil diajukan.';
    }
}
