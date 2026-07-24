<?php

namespace App\Filament\Resources\PenerimaanBarangs\Pages;

use App\Filament\Concerns\TransactionDeleteGuard;
use App\Filament\Resources\Pembelians\PembelianResource;
use App\Filament\Resources\PenerimaanBarangs\PenerimaanBarangResource;
use App\Models\Pembelian;
use App\Models\PenerimaanBarang;
use App\Models\PenerimaanBarangItem;
use App\Services\InventoryMutationService;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ViewPenerimaanBarang extends ViewRecord
{
    protected static string $resource = PenerimaanBarangResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Penerimaan')
                    ->icon('heroicon-o-truck')
                    ->schema([
                        Grid::make()
                            ->schema([
                                TextEntry::make('nomor')
                                    ->label('Nomor')
                                    ->weight('bold')
                                    ->size('lg')
                                    ->badge()
                                    ->color('dark'),
                                TextEntry::make('tgl_penerimaan')
                                    ->label('Tanggal')
                                    ->date('d M Y'),
                                TextEntry::make('no_surat_jalan')
                                    ->label('No. Surat Jalan')
                                    ->placeholder('—'),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'Pending' => 'warning',
                                        'Approved' => 'primary',
                                        'Canceled' => 'danger',
                                        default => 'gray',
                                    }),
                            ])
                            ->columns(2),
                        Grid::make()
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label('Dibuat oleh'),
                                TextEntry::make('approver.name')
                                    ->label('Approver')
                                    ->placeholder('—'),
                                TextEntry::make('gudang.nama_gudang')
                                    ->label('Gudang')
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('created_at')
                                    ->label('Dibuat')
                                    ->dateTime('d M Y, H:i'),
                            ])
                            ->columns(2),
                    ])
                    ->columns(2),

                Section::make('Referensi Invoice Pembelian')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Grid::make()
                            ->schema([
                                TextEntry::make('pembelian.nomor')
                                    ->label('Nomor Invoice')
                                    ->url(fn (PenerimaanBarang $record): ?string => $record->pembelian
                                        ? PembelianResource::getUrl('view', ['record' => $record->pembelian_id])
                                        : null)
                                    ->openUrlInNewTab(),
                                TextEntry::make('pembelian.nama_supplier')
                                    ->label('Supplier')
                                    ->placeholder('—'),
                            ])
                            ->columns(2),
                    ]),

                Section::make('Detail Barang Diterima')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('produk.item_code')
                                    ->label('Kode')
                                    ->placeholder('—'),
                                TextEntry::make('produk.nama_produk')
                                    ->label('Nama Produk')
                                    ->weight('bold'),
                                TextEntry::make('tipe_stok')
                                    ->label('Tipe Stok')
                                    ->badge()
                                    ->color(fn ($state) => match ($state) {
                                        'gratis' => 'success',
                                        'sample' => 'warning',
                                        default => 'primary',
                                    })
                                    ->formatStateUsing(fn ($state) => ucfirst($state)),
                                TextEntry::make('batch_number')
                                    ->label('Batch')
                                    ->placeholder('—'),
                                TextEntry::make('expired_date')
                                    ->label('Expired')
                                    ->date('d/m/Y')
                                    ->placeholder('—'),
                                TextEntry::make('qty_diterima')
                                    ->label('Qty Diterima')
                                    ->weight('bold'),
                                TextEntry::make('qty_reject')
                                    ->label('Qty Reject')
                                    ->color('danger')
                                    ->placeholder('0'),
                                TextEntry::make('keterangan')
                                    ->label('Keterangan')
                                    ->placeholder('—'),
                            ])
                            ->columns(5),
                    ]),

                Section::make('Keterangan')
                    ->icon('heroicon-o-chat-bubble-left')
                    ->schema([
                        TextEntry::make('keterangan')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Lampiran')
                    ->icon('heroicon-o-photo')
                    ->collapsible()
                    ->visible(fn () => ! empty($this->getRecord()->lampiran_paths))
                    ->schema([
                        TextEntry::make('lampiran_display')
                            ->label('')
                            ->html()
                            ->state(function ($record) {
                                $paths = collect($record->lampiran_paths ?? []);

                                $html = '<div class="grid grid-cols-2 md:grid-cols-3 gap-4">';
                                foreach ($paths as $path) {
                                    $url = asset('storage/'.$path);
                                    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);

                                    $html .= '<div class="flex flex-col items-center justify-center p-4 rounded-lg shadow-sm">';
                                    if ($isImage) {
                                        $html .= '<a href="'.$url.'" target="_blank" class="block w-full h-32 mb-2 bg-gray-100 rounded flex items-center justify-center overflow-hidden hover:opacity-75 transition">';
                                        $html .= '<img src="'.$url.'" class="max-w-full max-h-full object-contain" alt="Lampiran" loading="lazy">';
                                        $html .= '</a>';
                                    } else {
                                        $html .= '<a href="'.$url.'" target="_blank" class="block w-full h-32 mb-2 bg-gray-100 rounded flex flex-col items-center justify-center text-primary-600 hover:text-primary-800 hover:bg-gray-200 transition">';
                                        $html .= '<svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>';
                                        $html .= '<span class="text-xs mt-2 uppercase font-semibold">'.$ext.'</span>';
                                        $html .= '</a>';
                                    }
                                    $html .= '<span class="text-xs text-center truncate w-full" title="'.basename($path).'">'.basename($path).'</span>';
                                    $html .= '</div>';
                                }
                                $html .= '</div>';

                                return $html;
                            })
                            ->columnSpanFull(),
                    ]),

                // Special status info for approved status
                Section::make()
                    ->schema([
                        TextEntry::make('status_info')
                            ->label('')
                            ->state('Stok telah ditambahkan ke gudang')
                            ->visible(fn ($record) => $record->status === 'Approved')
                            ->icon('heroicon-o-check-circle')
                            ->color('success'),
                    ])
                    ->columns(1),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $record = $this->getRecord();

        return [
            // ===== APPROVE =====
            Action::make('approve')
                ->label('Approve & Tambah Stok')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Setujui Penerimaan Barang?')
                ->modalDescription('Stok akan ditambahkan ke gudang.')
                ->visible(fn () => $record->status === 'Pending' && $user->isAdmin())
                ->action(function () use ($record, $user) {
                    if ($user->role === 'admin') {
                        $cg = $user?->getCurrentGudang();
                        if (! $cg || (int) $record->gudang_id !== (int) $cg->id) {
                            Notification::make()->title('Hanya bisa approve penerimaan di gudang aktif.')->danger()->send();

                            return;
                        }
                    }

                    try {
                        DB::transaction(function () use ($record, $user): void {
                            $lockedRecord = PenerimaanBarang::with('items')
                                ->whereKey($record->id)
                                ->lockForUpdate()
                                ->firstOrFail();

                            if ($lockedRecord->status !== 'Pending') {
                                throw new DomainException('Hanya transaksi Pending yang bisa di-approve.');
                            }

                            $pembelian = $this->lockPembelianWithItems((int) $lockedRecord->pembelian_id);
                            $this->validateItemsDoNotExceedRemaining($pembelian, $lockedRecord->items->all());

                            $lockedRecord->update([
                                'status' => 'Approved',
                                'approver_id' => $user->id,
                            ]);

                            foreach ($lockedRecord->items as $item) {
                                if ((int) $item->qty_diterima > 0) {
                                    $this->tambahStok($lockedRecord->gudang_id, $item->produk_id, (int) $item->qty_diterima, $item->tipe_stok ?? 'penjualan', $lockedRecord, 'Penerimaan Approve');
                                }
                            }
                        });
                    } catch (ValidationException|DomainException|InvalidArgumentException $e) {
                        Notification::make()->title('Gagal approve: '.$e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()
                        ->title('Penerimaan barang berhasil di-approve dan stok ditambahkan.')
                        ->success()
                        ->send();
                }),

            // ===== CANCEL =====
            Action::make('cancel')
                ->label('Batalkan')
                ->icon('heroicon-o-no-symbol')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Batalkan Penerimaan Barang?')
                ->modalDescription('Yakin ingin membatalkan? Jika sudah approved, stok akan dikurangi.')
                ->visible(function () use ($record, $user) {
                    if ($record->status === 'Canceled') {
                        return false;
                    }
                    if ($user->isSuperAdmin()) {
                        return true;
                    }

                    return $record->status === 'Pending' && $user->isAdmin();
                })
                ->action(function () use ($record, $user) {
                    if ($user->role === 'admin') {
                        $cg = $user?->getCurrentGudang();
                        if (! $cg || (int) $record->gudang_id !== (int) $cg->id) {
                            Notification::make()->title('Hanya bisa cancel penerimaan di gudang aktif.')->danger()->send();

                            return;
                        }
                    }

                    try {
                        DB::transaction(function () use ($record): void {
                            $lockedRecord = PenerimaanBarang::with('items')
                                ->whereKey($record->id)
                                ->lockForUpdate()
                                ->firstOrFail();

                            if ($lockedRecord->status === 'Canceled') {
                                throw new DomainException('Transaksi sudah dibatalkan.');
                            }

                            if ($lockedRecord->status === 'Approved') {
                                foreach ($lockedRecord->items as $item) {
                                    if ((int) $item->qty_diterima > 0) {
                                        $this->kurangiStok($lockedRecord->gudang_id, $item->produk_id, (int) $item->qty_diterima, $item->tipe_stok ?? 'penjualan', $lockedRecord, 'Penerimaan Cancel');
                                    }
                                }
                            }

                            $lockedRecord->update(['status' => 'Canceled']);
                        });
                    } catch (DomainException|InvalidArgumentException $e) {
                        Notification::make()->title('Gagal cancel: '.$e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()
                        ->title('Penerimaan barang dibatalkan.')
                        ->success()
                        ->send();
                }),

            // ===== UNCANCEL =====
            Action::make('uncancel')
                ->label('Batalkan Pembatalan')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn () => $record->status === 'Canceled' && $user->isSuperAdmin())
                ->action(function () use ($record, $user) {
                    $record->update([
                        'status' => 'Pending',
                        'approver_id' => $user->id,
                    ]);

                    Notification::make()
                        ->title('Pembatalan penerimaan barang dibatalkan. Status kembali Pending.')
                        ->success()
                        ->send();
                }),

            // ===== PRINT =====
            Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn () => route('penerimaan-barang.print', $this->getRecord()->id))
                ->openUrlInNewTab(),

            // ===== QR CODE =====
            Action::make('qrCode')
                ->label('QR Code')
                ->icon('heroicon-o-qr-code')
                ->color('success')
                ->modalHeading('QR Code Penerimaan Barang')
                ->modalDescription('Scan QR Code untuk melihat penerimaan barang publik.')
                ->modalContent(fn () => view('filament.modals.qr-code', [
                    'url' => url("invoice/penerimaan-barang/{$this->getRecord()->uuid}"),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup'),

            // ===== EDIT (super_admin only) =====
            EditAction::make()->visible(fn () => $user->isSuperAdmin()),

            // ===== DELETE =====
            DeleteAction::make()->visible(fn (): bool => $user->isSuperAdmin() && TransactionDeleteGuard::canDeletePenerimaanBarang($record)),
        ];
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

    private function kurangiStok(int $gudangId, int $produkId, int $qty, string $tipeStok = 'penjualan', ?PenerimaanBarang $penerimaan = null, string $transactionType = 'Penerimaan Cancel'): void
    {
        app(InventoryMutationService::class)->decrement(
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
}
