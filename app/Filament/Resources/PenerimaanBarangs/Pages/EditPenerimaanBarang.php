<?php

namespace App\Filament\Resources\PenerimaanBarangs\Pages;

use App\Filament\Concerns\RenamesLampiran;
use App\Filament\Concerns\TransactionDeleteGuard;
use App\Filament\Resources\PenerimaanBarangs\PenerimaanBarangResource;
use App\Models\PenerimaanBarang;
use App\Models\PenerimaanBarangItem;
use App\Services\InventoryMutationService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditPenerimaanBarang extends EditRecord
{
    use RenamesLampiran;

    protected static string $resource = PenerimaanBarangResource::class;

    /**
     * Konversi record ke format form:
     * - pembelian_id (single) → pembelian_ids (array)
     * - Load items dari relationship, tambahkan pembelian_id per item
     */
    protected function mutateFormDataBeforeEdit(array $data): array
    {
        $record = $this->getRecord();
        $record->loadMissing('items');

        // Konversi pembelian_id → pembelian_ids
        if (! empty($record->pembelian_id)) {
            $data['pembelian_ids'] = [$record->pembelian_id];
        }

        // Load items dari DB ke format array repeater (dengan pembelian_id per item)
        $data['items'] = $record->items->map(fn ($item) => [
            'pembelian_id' => $record->pembelian_id,
            'produk_id' => $item->produk_id,
            'qty_diterima' => $item->qty_diterima,
            'qty_reject' => $item->qty_reject ?? 0,
            'tipe_stok' => $item->tipe_stok ?? 'penjualan',
            'batch_number' => $item->batch_number,
            'expired_date' => $item->expired_date?->format('Y-m-d'),
            'keterangan' => $item->keterangan,
        ])->toArray();

        return $data;
    }

    /**
     * Konversi form → data DB sebelum save:
     * - pembelian_ids (array) → pembelian_id (single)
     * - Ambil items untuk disimpan manual di afterSave
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['pembelian_ids'])) {
            $data['pembelian_id'] = (int) array_values((array) $data['pembelian_ids'])[0];
        }
        unset($data['pembelian_ids']);

        // Simpan items sementara di property untuk dipakai afterSave
        $this->cachedItems = $data['items'] ?? [];
        unset($data['items']);

        return $data;
    }

    private array $cachedItems = [];

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            $lockedRecord = PenerimaanBarang::with('items')
                ->whereKey($record->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedRecord->update($data);
            $this->syncItemsAndStock($lockedRecord);

            return $lockedRecord->refresh()->load('items');
        });
    }

    protected function afterSave(): void
    {
        $this->renameLampiranFiles();
    }

    /**
     * Replace items and apply stock reversal/addition in the same transaction as the record update.
     */
    private function syncItemsAndStock(PenerimaanBarang $record): void
    {
        $gudangId = (int) $record->gudang_id;
        $isApproved = $record->status === 'Approved';

        if ($isApproved) {
            foreach ($record->items as $oldItem) {
                if ((int) $oldItem->qty_diterima > 0) {
                    $this->decrementStock($gudangId, (int) $oldItem->produk_id, (int) $oldItem->qty_diterima, $oldItem->tipe_stok ?? 'penjualan', $record, 'Penerimaan Edit Remove');
                }
            }
        }

        $record->items()->delete();

        foreach ($this->cachedItems as $item) {
            $qtyDiterima = (int) ($item['qty_diterima'] ?? 0);
            $qtyReject = (int) ($item['qty_reject'] ?? 0);
            $tipeStok = $item['tipe_stok'] ?? 'penjualan';

            PenerimaanBarangItem::create([
                'penerimaan_barang_id' => $record->id,
                'produk_id' => $item['produk_id'],
                'qty_diterima' => $qtyDiterima,
                'qty_reject' => $qtyReject,
                'tipe_stok' => $tipeStok,
                'batch_number' => $item['batch_number'] ?? null,
                'expired_date' => ! empty($item['expired_date']) ? $item['expired_date'] : null,
                'keterangan' => $item['keterangan'] ?? null,
            ]);

            if ($isApproved && $qtyDiterima > 0) {
                $this->incrementStock($gudangId, (int) $item['produk_id'], $qtyDiterima, $tipeStok, $record, 'Penerimaan Edit Add');
            }
        }
    }

    private function decrementStock(int $gudangId, int $produkId, int $qty, string $tipeStok, PenerimaanBarang $penerimaan, string $transactionType): void
    {
        app(InventoryMutationService::class)->decrement(
            $gudangId,
            $produkId,
            $qty,
            $tipeStok,
            [
                'transaction_type' => $transactionType,
                'transaction_id' => $penerimaan->id,
                'transaction_nomor' => $penerimaan->nomor,
            ],
        );
    }

    private function incrementStock(int $gudangId, int $produkId, int $qty, string $tipeStok, PenerimaanBarang $penerimaan, string $transactionType): void
    {
        app(InventoryMutationService::class)->increment(
            $gudangId,
            $produkId,
            $qty,
            $tipeStok,
            [
                'transaction_type' => $transactionType,
                'transaction_id' => $penerimaan->id,
                'transaction_nomor' => $penerimaan->nomor,
            ],
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => auth()->user()?->isSuperAdmin() && TransactionDeleteGuard::canDeletePenerimaanBarang($this->getRecord())),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return PenerimaanBarangResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
