<?php

namespace App\Filament\Resources\Pembelians\Pages;

use App\Filament\Concerns\RenamesLampiran;
use App\Filament\Resources\Pembelians\PembelianResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPembelian extends EditRecord
{
    use RenamesLampiran;

    protected static string $resource = PembelianResource::class;

    use \App\Filament\Concerns\ResolvesApprover;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $term = $data['syarat_pembayaran'] ?? $this->record->syarat_pembayaran;
        $isCash = ($term == 'Cash');

        if ($isCash) {
            $data['tgl_jatuh_tempo'] = null;
            $data['status'] = 'Lunas';
        } else {
            $tglJatuhTempo = \Carbon\Carbon::parse($data['tgl_transaksi'] ?? $this->record->tgl_transaksi);
            $data['status'] = 'Pending';
            
            if ($term == 'Net 7') $tglJatuhTempo->addDays(7);
            elseif ($term == 'Net 14') $tglJatuhTempo->addDays(14);
            elseif ($term == 'Net 30') $tglJatuhTempo->addDays(30);
            elseif ($term == 'Net 60') $tglJatuhTempo->addDays(60);
            
            $data['tgl_jatuh_tempo'] = $tglJatuhTempo;
        }

        // Jika status Pending, hitung ulang approver_id dan staf_penyetuju
        if ($data['status'] === 'Pending') {
            $gudangId = $data['gudang_id'] ?? $this->record->gudang_id;
            $data['approver_id'] = $this->resolveApproverId($gudangId ?: null);
            $data['staf_penyetuju'] = $this->resolveStafPenyetuju($gudangId ?: null);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('kembali_detail')
                ->label('Lihat Detail')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn() => PembelianResource::getUrl('view', ['record' => $this->getRecord()])),

            DeleteAction::make()
                ->visible(fn() => auth()->user()?->isSuperAdmin()),
        ];
    }

    protected function afterSave(): void
    {
        $this->renameLampiranFiles();
    }

    protected function getRedirectUrl(): string
    {
        return PembelianResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Data pembelian berhasil diperbarui.';
    }
}
