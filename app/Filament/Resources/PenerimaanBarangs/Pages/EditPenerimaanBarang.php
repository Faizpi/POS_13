<?php

namespace App\Filament\Resources\PenerimaanBarangs\Pages;

use App\Filament\Concerns\RenamesLampiran;
use App\Filament\Resources\PenerimaanBarangs\PenerimaanBarangResource;
use App\Models\GudangProduk;
use App\Models\PenerimaanBarang;
use App\Models\PenerimaanBarangItem;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
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
        if (!empty($record->pembelian_id)) {
            $data['pembelian_ids'] = [$record->pembelian_id];
        }

        // Load items dari DB ke format array repeater (dengan pembelian_id per item)
        $data['items'] = $record->items->map(fn ($item) => [
            'pembelian_id'  => $record->pembelian_id,
            'produk_id'     => $item->produk_id,
            'qty_diterima'  => $item->qty_diterima,
            'qty_reject'    => $item->qty_reject ?? 0,
            'tipe_stok'     => $item->tipe_stok ?? 'penjualan',
            'batch_number'  => $item->batch_number,
            'expired_date'  => $item->expired_date?->format('Y-m-d'),
            'keterangan'    => $item->keterangan,
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
        if (!empty($data['pembelian_ids'])) {
            $data['pembelian_id'] = (int) array_values((array) $data['pembelian_ids'])[0];
        }
        unset($data['pembelian_ids']);

        // Simpan items sementara di property untuk dipakai afterSave
        $this->cachedItems = $data['items'] ?? [];
        unset($data['items']);

        return $data;
    }

    /** @var array */
    private array $cachedItems = [];

    /**
     * Setelah record di-save: sync items secara manual.
     * Jika record sudah Approved, sesuaikan stok (kurangi lama, tambah baru).
     */
    protected function afterSave(): void
    {
        $this->renameLampiranFiles();

        $record = $this->getRecord();
        $record->loadMissing('items');

        $gudangId      = (int) $record->gudang_id;
        $isApproved    = $record->status === 'Approved';
        $oldItems      = $record->items->keyBy('id');

        DB::beginTransaction();
        try {
            // Jika Approved: kurangi stok lama dulu sebelum replace items
            if ($isApproved) {
                foreach ($record->items as $oldItem) {
                    if ((int) $oldItem->qty_diterima > 0) {
                        $this->adjustStok($gudangId, $oldItem->produk_id, -$oldItem->qty_diterima, $oldItem->tipe_stok ?? 'penjualan');
                    }
                }
            }

            // Hapus semua items lama
            $record->items()->delete();

            // Buat items baru
            foreach ($this->cachedItems as $item) {
                $qtyDiterima = (int) ($item['qty_diterima'] ?? 0);
                $qtyReject   = (int) ($item['qty_reject'] ?? 0);
                $tipeStok    = $item['tipe_stok'] ?? 'penjualan';

                PenerimaanBarangItem::create([
                    'penerimaan_barang_id' => $record->id,
                    'produk_id'            => $item['produk_id'],
                    'qty_diterima'         => $qtyDiterima,
                    'qty_reject'           => $qtyReject,
                    'tipe_stok'            => $tipeStok,
                    'batch_number'         => $item['batch_number'] ?? null,
                    'expired_date'         => !empty($item['expired_date']) ? $item['expired_date'] : null,
                    'keterangan'           => $item['keterangan'] ?? null,
                ]);

                // Jika Approved: tambahkan stok baru
                if ($isApproved && $qtyDiterima > 0) {
                    $this->adjustStok($gudangId, (int) $item['produk_id'], $qtyDiterima, $tipeStok);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Helper: adjust stok (positif = tambah, negatif = kurangi)
     */
    private function adjustStok(int $gudangId, int $produkId, int $qty, string $tipeStok): void
    {
        $stok = GudangProduk::firstOrCreate(
            ['gudang_id' => $gudangId, 'produk_id' => $produkId],
            ['stok' => 0, 'stok_penjualan' => 0, 'stok_gratis' => 0, 'stok_sample' => 0]
        );

        $kolom = 'stok_' . $tipeStok;
        $kolom = in_array($kolom, ['stok_penjualan', 'stok_gratis', 'stok_sample']) ? $kolom : 'stok_penjualan';

        $stok->stok         = max(0, $stok->stok + $qty);
        $stok->{$kolom}     = max(0, $stok->{$kolom} + $qty);
        $stok->save();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return PenerimaanBarangResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
