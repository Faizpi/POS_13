<?php

namespace App\Filament\Resources\PenerimaanBarangs\Pages;

use App\Filament\Resources\Pembelians\PembelianResource;
use App\Filament\Resources\PenerimaanBarangs\PenerimaanBarangResource;
use App\Models\GudangProduk;
use App\Models\PenerimaanBarang;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Support\Enums\IconPosition;
use Illuminate\Support\Facades\DB;

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
                                    ->color(fn(string $state): string => match($state) {
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
                                    ->url(fn(PenerimaanBarang $record): ?string => $record->pembelian
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
                                    ->color(fn($state) => match($state) {
                                        'gratis' => 'success',
                                        'sample' => 'warning',
                                        default => 'primary',
                                    })
                                    ->formatStateUsing(fn($state) => ucfirst($state)),
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
                    ->visible(fn() => !empty($this->getRecord()->lampiran_paths))
                    ->schema([
                        TextEntry::make('lampiran_display')
                            ->label('')
                            ->html()
                            ->state(function ($record) {
                                $paths = collect($record->lampiran_paths ?? []);

                                $html = '<div class="grid grid-cols-2 md:grid-cols-3 gap-4">';
                                foreach ($paths as $path) {
                                    $url = asset('storage/' . $path);
                                    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);

                                    $html .= '<div class="flex flex-col items-center justify-center p-4 rounded-lg shadow-sm">';
                                    if ($isImage) {
                                        $html .= '<a href="javascript:void(0)" onclick="window.previewLampiran(\'' . $url . '\')" class="block w-full h-32 mb-2 bg-gray-100 rounded flex items-center justify-center overflow-hidden hover:opacity-75 transition">';
                                        $html .= '<img src="' . $url . '" class="max-w-full max-h-full object-contain" alt="Lampiran" loading="lazy">';
                                        $html .= '</a>';
                                    } else {
                                        $html .= '<a href="' . $url . '" target="_blank" class="block w-full h-32 mb-2 bg-gray-100 rounded flex flex-col items-center justify-center text-primary-600 hover:text-primary-800 hover:bg-gray-200 transition">';
                                        $html .= '<svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>';
                                        $html .= '<span class="text-xs mt-2 uppercase font-semibold">' . $ext . '</span>';
                                        $html .= '</a>';
                                    }
                                    $html .= '<span class="text-xs text-center truncate w-full" title="' . basename($path) . '">' . basename($path) . '</span>';
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
                            ->visible(fn($record) => $record->status === 'Approved')
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
                ->visible(fn() => $record->status === 'Pending' && $user->isAdmin())
                ->action(function () use ($record, $user) {
                    if ($user->role === 'admin') {
                        $cg = $user?->getCurrentGudang();
                        if (!$cg || (int) $record->gudang_id !== (int) $cg->id) {
                            Notification::make()->title('Hanya bisa approve penerimaan di gudang aktif.')->danger()->send();
                            return;
                        }
                    }

                    DB::transaction(function () use ($record, $user): void {
                        $record->loadMissing('items');
                        $record->update([
                            'status' => 'Approved',
                            'approver_id' => $user->id,
                        ]);

                        foreach ($record->items as $item) {
                            if ((int) $item->qty_diterima > 0) {
                                $this->tambahStok($record->gudang_id, $item->produk_id, (int) $item->qty_diterima, $item->tipe_stok ?? 'penjualan');
                            }
                        }
                    });
                    
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
                        if (!$cg || (int) $record->gudang_id !== (int) $cg->id) {
                            Notification::make()->title('Hanya bisa cancel penerimaan di gudang aktif.')->danger()->send();
                            return;
                        }
                    }

                    DB::transaction(function () use ($record): void {
                        $record->loadMissing('items');

                        if ($record->status === 'Approved') {
                            foreach ($record->items as $item) {
                                if ((int) $item->qty_diterima > 0) {
                                    $this->kurangiStok($record->gudang_id, $item->produk_id, (int) $item->qty_diterima, $item->tipe_stok ?? 'penjualan');
                                }
                            }
                        }

                        $record->update(['status' => 'Canceled']);
                    });
                    
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
                ->visible(fn() => $record->status === 'Canceled' && $user->isSuperAdmin())
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
                ->url(fn() => route('penerimaan-barang.print', $this->getRecord()->id))
                ->openUrlInNewTab(),

            // ===== QR CODE =====
            Action::make('qrCode')
                ->label('QR Code')
                ->icon('heroicon-o-qr-code')
                ->color('success')
                ->modalHeading('QR Code Penerimaan Barang')
                ->modalDescription('Scan QR Code untuk melihat penerimaan barang publik.')
                ->modalContent(fn() => view('filament.modals.qr-code', [
                    'url' => url("invoice/penerimaan-barang/{$this->getRecord()->uuid}"),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup'),

            // ===== EDIT (super_admin only) =====
            EditAction::make()->visible(fn() => $user->isSuperAdmin()),

            // ===== DELETE =====
            DeleteAction::make()->visible(fn() => $user->isSuperAdmin()),
        ];
    }

    private function tambahStok(int $gudangId, int $produkId, int $qty, string $tipeStok = 'penjualan'): void
    {
        $stok = GudangProduk::firstOrCreate(
            ['gudang_id' => $gudangId, 'produk_id' => $produkId],
            ['stok' => 0, 'stok_penjualan' => 0, 'stok_gratis' => 0, 'stok_sample' => 0]
        );

        $stok->stok += $qty;
        $column = $this->stockColumn($tipeStok);
        $stok->{$column} += $qty;
        $stok->save();
    }

    private function kurangiStok(int $gudangId, int $produkId, int $qty, string $tipeStok = 'penjualan'): void
    {
        $stok = GudangProduk::where('gudang_id', $gudangId)
            ->where('produk_id', $produkId)
            ->first();

        if (!$stok) {
            return;
        }

        $stok->stok = max(0, $stok->stok - $qty);
        $column = $this->stockColumn($tipeStok);
        $stok->{$column} = max(0, $stok->{$column} - $qty);
        $stok->save();
    }

    private function stockColumn(string $tipeStok): string
    {
        $column = 'stok_' . $tipeStok;

        return in_array($column, ['stok_penjualan', 'stok_gratis', 'stok_sample'], true)
            ? $column
            : 'stok_penjualan';
    }
}
