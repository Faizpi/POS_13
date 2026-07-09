<?php

namespace App\Filament\Resources\PenerimaanBarangs\Pages;

use App\Filament\Concerns\RenamesLampiran;
use App\Filament\Concerns\ResolvesApprover;
use App\Filament\Resources\PenerimaanBarangs\PenerimaanBarangResource;
use App\Models\GudangProduk;
use App\Models\Pembelian;
use App\Models\PenerimaanBarang;
use App\Models\PenerimaanBarangItem;
use App\Models\StokLog;
use App\Services\InventoryMutationService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePenerimaanBarang extends CreateRecord
{
    use RenamesLampiran, ResolvesApprover;

    protected static string $resource = PenerimaanBarangResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Generate nomor urut harian
        $countToday = PenerimaanBarang::where('user_id', auth()->id())
            ->whereDate('created_at', Carbon::today())
            ->count();
        $noUrut = $countToday + 1;
        $now = Carbon::now();

        $user = auth()->user();

        $data['user_id'] = auth()->id();
        $data['status'] = $user->isSuperAdmin() ? 'Approved' : 'Pending';
        $data['no_urut_harian'] = $noUrut;
        $data['nomor'] = PenerimaanBarang::generateNomor(auth()->id(), $noUrut, $now);

        // Set approver_id berdasarkan gudang yang dipilih
        $gudangId = (int) ($data['gudang_id'] ?? 0);
        $data['approver_id'] = $user->isSuperAdmin() ? $user->id : $this->resolveApproverId($gudangId ?: null);

        return $data;
    }

    /**
     * Gap 4 fix: Override handleRecordCreation untuk support MULTIPLE PO sekaligus.
     * Items di-group per pembelian_id, lalu dibuat PenerimaanBarang terpisah per PO.
     * Nomor diberi suffix -A, -B, dst jika lebih dari satu PO.
     * Stok langsung ditambahkan jika super_admin (status Approved).
     */
    protected function handleRecordCreation(array $data): Model
    {
        $pembelianIds = (array) ($data['pembelian_ids'] ?? []);
        $allItems = (array) ($data['items'] ?? []);
        $lampiranPaths = $data['lampiran_paths'] ?? [];
        $status = $data['status'];
        $gudangId = (int) ($data['gudang_id'] ?? 0);

        unset($data['pembelian_ids'], $data['items'], $data['lampiran_paths']);

        // Group items by pembelian_id (setiap item punya hidden field pembelian_id)
        $itemsByPembelian = [];
        foreach ($allItems as $item) {
            $pembelianId = $item['pembelian_id'] ?? null;
            if (! $pembelianId) {
                continue;
            }
            $itemsByPembelian[$pembelianId][] = $item;
        }

        // Jika tidak ada grouping (fallback: satu PO saja), assign semua ke PO pertama
        if (empty($itemsByPembelian) && ! empty($pembelianIds)) {
            $itemsByPembelian[$pembelianIds[0]] = $allItems;
        }

        $firstRecord = null;
        $indexPenerimaan = 0;

        DB::beginTransaction();
        try {
            foreach ($itemsByPembelian as $pembelianId => $pembelianItems) {
                // Filter item yang ada qty_diterima > 0 atau qty_reject > 0
                $validItems = array_filter($pembelianItems, fn ($item) => ((int) ($item['qty_diterima'] ?? 0)) > 0 || ((int) ($item['qty_reject'] ?? 0)) > 0
                );

                if (empty($validItems)) {
                    $indexPenerimaan++;

                    continue;
                }

                $pembelian = $this->lockPembelianWithItems((int) $pembelianId);
                $this->validateItemsDoNotExceedRemaining($pembelian, $validItems);

                // Nomor: suffix -A, -B dst jika multi-PO
                $nomorPenerimaan = count($itemsByPembelian) > 1
                    ? $data['nomor'].'-'.chr(65 + $indexPenerimaan)
                    : $data['nomor'];

                $penerimaan = PenerimaanBarang::create(array_merge($data, [
                    'pembelian_id' => $pembelianId,
                    'nomor' => $nomorPenerimaan,
                    'no_urut_harian' => ($data['no_urut_harian'] ?? 1) + $indexPenerimaan,
                    // Lampiran hanya di record pertama
                    'lampiran_paths' => $indexPenerimaan === 0 ? $lampiranPaths : [],
                ]));

                foreach ($validItems as $item) {
                    $qtyDiterima = (int) ($item['qty_diterima'] ?? 0);
                    $qtyReject = (int) ($item['qty_reject'] ?? 0);
                    $tipeStok = $item['tipe_stok'] ?? 'penjualan';

                    PenerimaanBarangItem::create([
                        'penerimaan_barang_id' => $penerimaan->id,
                        'produk_id' => $item['produk_id'],
                        'qty_diterima' => $qtyDiterima,
                        'qty_reject' => $qtyReject,
                        'tipe_stok' => $tipeStok,
                        'batch_number' => $item['batch_number'] ?? null,
                        'expired_date' => ! empty($item['expired_date']) ? $item['expired_date'] : null,
                        'keterangan' => $item['keterangan'] ?? null,
                    ]);

                    // Jika langsung Approved (super_admin), tambahkan stok sekarang
                    if ($status === 'Approved' && $qtyDiterima > 0) {
                        $this->tambahStok($gudangId, (int) $item['produk_id'], $qtyDiterima, $tipeStok, $penerimaan, 'Penerimaan Approve');
                    }
                }

                if ($firstRecord === null) {
                    $firstRecord = $penerimaan;
                }

                $indexPenerimaan++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        // Fallback: jika tidak ada record yang dibuat (misal semua qty = 0)
        if ($firstRecord === null) {
            $firstRecord = PenerimaanBarang::create(array_merge($data, [
                'pembelian_id' => $pembelianIds[0] ?? null,
                'lampiran_paths' => $lampiranPaths,
            ]));
        }

        return $firstRecord;
    }

    protected function afterCreate(): void
    {
        $this->renameLampiranFiles();
    }

    protected function getRedirectUrl(): string
    {
        return PenerimaanBarangResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    private function lockPembelianWithItems(int $pembelianId): Pembelian
    {
        $pembelian = Pembelian::query()
            ->whereKey($pembelianId)
            ->lockForUpdate()
            ->firstOrFail();

        $items = $pembelian->items()
            ->with('produk:id,nama_produk')
            ->lockForUpdate()
            ->get();

        $pembelian->setRelation('items', $items);

        return $pembelian;
    }

    /**
     * @param  iterable<int, array<string, mixed>|object>  $items
     */
    private function validateItemsDoNotExceedRemaining(Pembelian $pembelian, iterable $items): void
    {
        $orderedQuantities = [];
        $productNames = [];

        foreach ($pembelian->items as $purchaseItem) {
            $produkId = (int) $purchaseItem->produk_id;
            $orderedQuantities[$produkId] = ($orderedQuantities[$produkId] ?? 0) + (int) ($purchaseItem->kuantitas ?? $purchaseItem->jumlah ?? 0);
            $productNames[$produkId] = $purchaseItem->produk?->nama_produk ?? "ID {$produkId}";
        }

        $approvedQuantities = $this->approvedReceivedQuantities((int) $pembelian->id);
        $requestedQuantities = [];
        $firstIndexes = [];

        foreach ($items as $index => $item) {
            $qtyDiterima = (int) $this->itemValue($item, 'qty_diterima', 0);
            if ($qtyDiterima <= 0) {
                continue;
            }

            $produkId = (int) $this->itemValue($item, 'produk_id', 0);
            $requestedQuantities[$produkId] = ($requestedQuantities[$produkId] ?? 0) + $qtyDiterima;
            $firstIndexes[$produkId] ??= is_int($index) ? $index : 0;
        }

        foreach ($requestedQuantities as $produkId => $requestedQuantity) {
            $orderedQuantity = $orderedQuantities[$produkId] ?? 0;
            $approvedQuantity = $approvedQuantities[$produkId] ?? 0;
            $remainingQuantity = max(0, $orderedQuantity - $approvedQuantity);

            if ($requestedQuantity > $remainingQuantity) {
                $productName = $productNames[$produkId] ?? "ID {$produkId}";
                $index = $firstIndexes[$produkId] ?? 0;

                throw ValidationException::withMessages([
                    "items.{$index}.qty_diterima" => "Qty diterima melebihi sisa PO. Produk {$productName}: sisa {$remainingQuantity}, diminta {$requestedQuantity}.",
                ]);
            }
        }
    }

    /**
     * @return array<int, int>
     */
    private function approvedReceivedQuantities(int $pembelianId): array
    {
        $approvedReceiptIds = PenerimaanBarang::query()
            ->where('pembelian_id', $pembelianId)
            ->where('status', 'Approved')
            ->lockForUpdate()
            ->pluck('id');

        if ($approvedReceiptIds->isEmpty()) {
            return [];
        }

        return PenerimaanBarangItem::query()
            ->select('produk_id', DB::raw('SUM(qty_diterima) as total_qty_diterima'))
            ->whereIn('penerimaan_barang_id', $approvedReceiptIds)
            ->groupBy('produk_id')
            ->lockForUpdate()
            ->pluck('total_qty_diterima', 'produk_id')
            ->map(fn ($quantity): int => (int) $quantity)
            ->all();
    }

    /**
     * @param  array<string, mixed>|object  $item
     */
    private function itemValue(array|object $item, string $key, mixed $default = null): mixed
    {
        if (is_array($item)) {
            return $item[$key] ?? $default;
        }

        return $item->{$key} ?? $default;
    }

    private function tambahStok(int $gudangId, int $produkId, int $qty, string $tipeStok = 'penjualan', ?PenerimaanBarang $penerimaan = null, string $transactionType = 'Penerimaan Approve'): void
    {
        app(InventoryMutationService::class)->increment(
            $gudangId,
            $produkId,
            $qty,
            $tipeStok,
            $penerimaan ? [
                'transaction_type' => $transactionType,
                'transaction_id' => $penerimaan->id,
                'transaction_nomor' => $penerimaan->nomor,
            ] : null,
        );
    }

    private function logStokMutation(GudangProduk $stock, int $stokSebelum, int $stokSesudah, ?PenerimaanBarang $penerimaan, string $transactionType): void
    {
        if (! $penerimaan) {
            return;
        }

        $stock->loadMissing(['produk', 'gudang']);
        $user = auth()->user();

        StokLog::create([
            'gudang_produk_id' => $stock->id,
            'produk_id' => $stock->produk_id,
            'gudang_id' => $stock->gudang_id,
            'user_id' => $user->id,
            'produk_nama' => $stock->produk?->nama_produk ?? '',
            'gudang_nama' => $stock->gudang?->nama_gudang ?? '',
            'user_nama' => $user->name,
            'stok_sebelum' => $stokSebelum,
            'stok_sesudah' => $stokSesudah,
            'selisih' => $stokSesudah - $stokSebelum,
            'keterangan' => "{$transactionType} #{$penerimaan->id} ({$penerimaan->nomor})",
        ]);
    }

    private function stockColumn(string $tipeStok): string
    {
        $column = 'stok_'.$tipeStok;

        return in_array($column, ['stok_penjualan', 'stok_gratis', 'stok_sample'], true)
            ? $column
            : 'stok_penjualan';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        $record = $this->getRecord();

        return $record->status === 'Approved'
            ? 'Penerimaan barang disetujui dan stok telah ditambahkan.'
            : 'Penerimaan barang berhasil diajukan dan menunggu approval.';
    }
}
