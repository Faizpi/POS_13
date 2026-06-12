<?php

namespace App\Filament\Resources\Biayas\Pages;

use App\Filament\Concerns\ResolvesApprover;
use App\Filament\Concerns\RenamesLampiran;
use App\Filament\Resources\Biayas\BiayaResource;
use App\Models\Biaya;
use App\Services\InvoiceEmailService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;

class CreateBiaya extends CreateRecord
{
    use ResolvesApprover, RenamesLampiran;

    protected static string $resource = BiayaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        // Generate nomor urut harian
        $countToday = Biaya::where('user_id', auth()->id())
            ->whereDate('created_at', Carbon::today())
            ->count();
        $noUrut = $countToday + 1;
        $now    = Carbon::now();

        $data['user_id']        = auth()->id();
        // Super admin: langsung Approved, lainnya: Pending
        $data['status']         = $user->isSuperAdmin() ? 'Approved' : 'Pending';
        $data['no_urut_harian'] = $noUrut;
        $data['nomor']          = Biaya::generateNomor(auth()->id(), $noUrut, $now);

        // Set tag (nama user)
        if (empty($data['tag'])) {
            $data['tag'] = $user->name;
        }

        // Set gudang dari user jika tidak diisi
        if (empty($data['gudang_id'])) {
            $data['gudang_id'] = $user?->getCurrentGudang()?->id;
        }

        // Set approver_id
        $gudangId            = (int) ($data['gudang_id'] ?? 0);
        $data['approver_id'] = $this->resolveApproverId($gudangId ?: null);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->renameLampiranFiles();

        try {
            InvoiceEmailService::sendCreatedNotification($this->getRecord(), 'biaya');
        } catch (\Throwable $e) {
            \Log::warning('Email notifikasi biaya gagal: ' . $e->getMessage());
        }
    }

    protected function getRedirectUrl(): string
    {
        return BiayaResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    public function getFormAttributes(): array
    {
        return [
            'x-init' => 'setTimeout(() => { if (window.posAutoFillKoordinat) posAutoFillKoordinat(); }, 1500)',
        ];
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Biaya berhasil diajukan dan menunggu approval.';
    }
}
