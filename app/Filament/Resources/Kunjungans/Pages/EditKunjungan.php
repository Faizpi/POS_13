<?php

namespace App\Filament\Resources\Kunjungans\Pages;

use App\Filament\Concerns\RenamesLampiran;
use App\Filament\Concerns\TransactionDeleteGuard;
use App\Filament\Resources\Kunjungans\KunjunganResource;
use App\Models\GudangProduk;
use App\Models\Produk;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditKunjungan extends EditRecord
{
    use RenamesLampiran;

    protected static string $resource = KunjunganResource::class;

    protected function beforeSave(): void
    {
        $data = $this->form->getState();
        $tujuan = $data['tujuan'] ?? '';

        // Only super_admin can edit (consistent with legacy)
        if (! auth()->user()->isSuperAdmin()) {
            Notification::make()
                ->danger()
                ->title('Anda tidak memiliki akses untuk mengedit data kunjungan.')
                ->send();
            $this->halt();

            return;
        }

        if (in_array($tujuan, ['Pemeriksaan Stock', 'Promo Gratis', 'Promo Sample'])) {
            if (empty($data['items']) || count($data['items']) === 0) {
                Notification::make()
                    ->danger()
                    ->title('Produk wajib diisi untuk kunjungan tipe ini.')
                    ->send();
                $this->halt();
            }
        }

        if (in_array($tujuan, ['Promo Gratis', 'Promo Sample']) && ! empty($data['items'])) {
            $gudangId = $this->record->gudang_id ?? auth()->user()?->getCurrentGudang()?->id;

            if ($gudangId) {
                $stokField = $tujuan === 'Promo Gratis' ? 'stok_gratis' : 'stok_sample';
                $stokLabel = $tujuan === 'Promo Gratis' ? 'stok gratis' : 'stok sample';

                foreach ($data['items'] as $item) {
                    $produkId = $item['produk_id'] ?? null;
                    if ($produkId) {
                        $qty = $item['jumlah'] ?? 1;
                        $stokAvailable = GudangProduk::where('gudang_id', $gudangId)
                            ->where('produk_id', $produkId)
                            ->value($stokField) ?? 0;

                        if ($qty > $stokAvailable) {
                            $namaProduk = Produk::find($produkId)->nama_produk ?? 'Produk';
                            Notification::make()
                                ->danger()
                                ->title("Qty {$namaProduk} ({$qty}) melebihi {$stokLabel} yang tersedia ({$stokAvailable}).")
                                ->send();
                            $this->halt();
                        }
                    }
                }
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => auth()->user()?->isSuperAdmin() && TransactionDeleteGuard::canDeleteKunjungan($this->getRecord())),
        ];
    }

    protected function afterSave(): void
    {
        $this->renameLampiranFiles();
    }
}
