<?php

namespace App\Filament\Resources\Penjualans\Pages;

use App\Filament\Concerns\RenamesLampiran;
use App\Filament\Resources\Penjualans\PenjualanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPenjualan extends EditRecord
{
    use RenamesLampiran;

    protected static string $resource = PenjualanResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // 1. Validasi stok untuk setiap item
        if (!empty($data['items'])) {
            $gudangId = (int) ($data['gudang_id'] ?? $this->record->gudang_id);
            foreach ($data['items'] as $item) {
                $produkId = $item['produk_id'] ?? null;
                $qty      = (float) ($item['kuantitas'] ?? 0);
                if (!$produkId) continue;

                $stokGudang  = \App\Models\GudangProduk::where('gudang_id', $gudangId)
                    ->where('produk_id', $produkId)
                    ->first();
                $stokTersedia = $stokGudang?->stok_penjualan ?? 0;

                if ($stokTersedia < $qty) {
                    $namaProduk = $stokGudang?->produk?->nama_produk ?? "ID: {$produkId}";
                    \Filament\Notifications\Notification::make()
                        ->title("Stok tidak cukup: {$namaProduk}")
                        ->body("Tersedia: {$stokTersedia}, Diminta: {$qty}")
                        ->danger()
                        ->persistent()
                        ->send();

                    $this->halt();
                }
            }
        }

        // 2. Tentukan status berdasarkan syarat pembayaran
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

        // 3. Jika status Pending, hitung ulang approver_id (mimik legacy update)
        if ($data['status'] === 'Pending') {
            $user = auth()->user();
            $gudangId = $data['gudang_id'] ?? $this->record->gudang_id;
            
            if ($user->role === 'user') {
                $adminGudang = \App\Models\User::where('role', 'admin')
                    ->where(function ($q) use ($gudangId) {
                        $q->where('gudang_id', $gudangId)
                            ->orWhereHas('gudangs', function ($sub) use ($gudangId) {
                                $sub->where('gudangs.id', $gudangId);
                            });
                    })
                    ->first();
                
                $data['approver_id'] = $adminGudang ? $adminGudang->id : 
                    (\App\Models\User::where('role', 'super_admin')->first()->id ?? null);
            } elseif ($user->role === 'admin') {
                $data['approver_id'] = \App\Models\User::where('role', 'super_admin')->first()->id ?? null;
            }
            // For super_admin, approver_id is handled by the form (disabled field keeps existing value)
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
                ->url(fn() => PenjualanResource::getUrl('view', ['record' => $this->getRecord()])),

            DeleteAction::make()
                ->visible(fn() => auth()->user()?->isSuperAdmin()),
        ];
    }

    /**
     * Setelah simpan: redirect kembali ke halaman view (detail).
     */
    protected function afterSave(): void
    {
        $this->renameLampiranFiles();
    }

    protected function getRedirectUrl(): string
    {
        return PenjualanResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Data penjualan berhasil diperbarui.';
    }
}
