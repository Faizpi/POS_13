<?php

namespace App\Filament\Resources\Pembelians\Pages;

use App\Filament\Concerns\TransactionDeleteGuard;
use App\Filament\Resources\Pembelians\PembelianResource;
use App\Models\User;
use App\Services\Accounting\HutangPostingService;
use App\Services\InvoiceEmailService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class ViewPembelian extends ViewRecord
{
    protected static string $resource = PembelianResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Info Utama')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        TextEntry::make('user.name')->label('Pembuat'),
                        TextEntry::make('approver.name')->label('Staf Penyetuju')->placeholder('—'),
                        TextEntry::make('gudang.nama_gudang')->label('Gudang'),
                        TextEntry::make('kontak.nama')->label('Supplier')->placeholder('—'),
                        TextEntry::make('urgensi')
                            ->label('Urgensi')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Tinggi' => 'danger',
                                'Sedang' => 'warning',
                                'Rendah' => 'success',
                                default => 'gray',
                            }),
                        TextEntry::make('tahun_anggaran')->label('Tahun Anggaran')->placeholder('—'),
                        TextEntry::make('tgl_transaksi')->label('Tanggal')->date('d F Y'),
                        TextEntry::make('tgl_jatuh_tempo')->label('Jatuh Tempo')->date('d F Y')->placeholder('—'),
                        TextEntry::make('created_at')->label('Dibuat')->dateTime('d M Y, H:i'),
                        TextEntry::make('syarat_pembayaran')->label('Syarat Bayar'),
                        TextEntry::make('tipe_harga')->label('Tipe Harga')->badge()->color(fn ($state) => $state === 'grosir' ? 'info' : 'success'),
                        TextEntry::make('no_referensi')->label('No Referensi')->placeholder('—'),
                        TextEntry::make('no_resi')->label('Nomor Resi')->placeholder('—'),
                        TextEntry::make('biaya_pengiriman')->label('Biaya Pengiriman')->money('IDR')->placeholder('—'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Pending' => 'warning',
                                'Approved' => 'primary',
                                'Lunas' => 'success',
                                'Canceled' => 'gray',
                                default => 'gray',
                            }),
                        TextEntry::make('nomor')->label('Nomor')->weight('bold'),
                        TextEntry::make('tag')->label('Tag (Sales)')->placeholder('—'),
                        TextEntry::make('koordinat')
                            ->label('Koordinat')
                            ->placeholder('—')
                            ->url(fn ($record) => $record->koordinat ? 'https://www.google.com/maps?q='.urlencode($record->koordinat) : null, true),
                    ])
                    ->columns(3),

                Section::make('Item Pembelian')
                    ->icon('heroicon-o-shopping-cart')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('produk.nama_produk')->label('Produk')->weight('bold'),
                                TextEntry::make('produk.item_code')->label('Kode')->placeholder('—'),
                                TextEntry::make('kuantitas')->label('Qty')->suffix(fn ($record) => ' '.$record->unit),
                                TextEntry::make('harga_satuan')->label('Harga')->money('IDR'),
                                TextEntry::make('diskon')->label('Disc')->suffix('%'),
                                TextEntry::make('batch_number')->label('Batch')->placeholder('—'),
                                TextEntry::make('expired_date')->label('Exp')->date('d/m/Y')->placeholder('—'),
                                TextEntry::make('deskripsi')->label('Deskripsi')->placeholder('—')->columnSpanFull(),
                                TextEntry::make('jumlah_baris')
                                    ->label('Total')
                                    ->money('IDR')
                                    ->weight('bold'),
                            ])
                            ->columns(5),
                    ]),

                Section::make('Total & Pajak')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        TextEntry::make('subtotal')
                            ->label('Subtotal')
                            ->state(fn ($record) => $record->items->sum(fn ($i) => ($i->kuantitas * $i->harga_satuan) * (1 - ($i->diskon / 100))))
                            ->money('IDR'),
                        TextEntry::make('diskon_akhir')->label('Diskon Akhir')->money('IDR'),
                        TextEntry::make('tax_percentage')->label('Pajak')->suffix('%'),
                        TextEntry::make('grand_total')
                            ->label('Grand Total')
                            ->money('IDR')
                            ->size('lg')
                            ->weight('bold')
                            ->color('primary'),
                    ])
                    ->columns(4),

                Section::make('Catatan')
                    ->icon('heroicon-o-paper-clip')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('memo')->placeholder('Tidak ada memo')->columnSpanFull(),
                        TextEntry::make('updated_at')
                            ->label('Diupdate')
                            ->dateTime('d M Y, H:i')
                            ->visible(fn ($record) => $record->updated_at && $record->updated_at->ne($record->created_at)),
                    ]),

                Section::make('Lampiran')
                    ->icon('heroicon-o-photo')
                    ->collapsible()
                    ->visible(fn () => ! empty($this->getRecord()->lampiran_paths) || ! empty($this->getRecord()->lampiran_path))
                    ->schema([
                        TextEntry::make('lampiran_display')
                            ->label('')
                            ->html()
                            ->state(function ($record) {
                                $paths = collect($record->lampiran_paths ?? []);
                                if ($record->lampiran_path) {
                                    $paths = $paths->prepend($record->lampiran_path);
                                }

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
            ]);
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $record = $this->getRecord();

        return [
            // ===== APPROVE =====
            Action::make('approve')
                ->label('Setujui')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Setujui Pembelian?')
                ->modalDescription('Status akan berubah menjadi "Approved".')
                ->visible(fn () => $record->status === 'Pending' && in_array($user->role, ['admin', 'super_admin']))
                ->action(function () use ($record, $user) {
                    if ($user->role === 'admin') {
                        $cg = $user?->getCurrentGudang();
                        if (! $cg || $cg->id !== $record->gudang_id) {
                            Notification::make()->title('Hanya bisa approve transaksi di gudang aktif.')->danger()->send();

                            return;
                        }
                    }
                    DB::transaction(function () use ($record, $user): void {
                        $lockedPurchase = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
                        if ($lockedPurchase->status !== 'Pending') {
                            throw new \DomainException('Hanya transaksi Pending yang bisa di-approve.');
                        }

                        $lockedPurchase->update(['status' => 'Approved', 'approver_id' => $user->id]);
                        app(HutangPostingService::class)->postPurchase($user, $lockedPurchase->refresh());
                    });

                    // Send email notification to creator
                    InvoiceEmailService::sendApprovedNotification($record, 'pembelian');

                    Notification::make()->title('Pembelian berhasil di-approve.')->success()->send();
                }),

            // ===== CANCEL =====
            Action::make('cancel')
                ->label('Cancel')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Batalkan Transaksi?')
                ->modalDescription('Transaksi yang dibatalkan tidak dapat diproses kembali tanpa Super Admin.')
                ->visible(function () use ($record, $user) {
                    if ($record->status === 'Canceled') {
                        return false;
                    }
                    if ($user->isSuperAdmin()) {
                        return true;
                    }
                    if ($record->status !== 'Pending') {
                        return false;
                    }
                    if ($user->role === 'user') {
                        return $record->user_id === $user->id;
                    }

                    return $user->role === 'admin';
                })
                ->action(function () use ($record, $user) {
                    if ($user->role === 'admin') {
                        $cg = $user?->getCurrentGudang();
                        if (! $cg || $cg->id !== $record->gudang_id) {
                            Notification::make()->title('Hanya bisa cancel di gudang aktif.')->danger()->send();

                            return;
                        }
                    }
                    DB::transaction(function () use ($record, $user): void {
                        $lockedPurchase = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
                        if ($lockedPurchase->status !== 'Canceled' && $lockedPurchase->syarat_pembayaran !== 'Cash') {
                            app(HutangPostingService::class)->reversePurchase($user, $lockedPurchase, 'Purchase canceled');
                        }
                        $lockedPurchase->update(['status' => 'Canceled']);
                    });
                    Notification::make()->title('Pembelian dibatalkan.')->success()->send();
                }),

            // ===== UNCANCEL =====
            Action::make('uncancel')
                ->label('Batalkan Pembatalan')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn () => $record->status === 'Canceled' && $user->isSuperAdmin())
                ->action(function () use ($record) {
                    $gudangId = $record->gudang_id;
                    $adminGudang = User::where('role', 'admin')
                        ->where(function ($q) use ($gudangId) {
                            $q->where('gudang_id', $gudangId)
                                ->orWhereHas('gudangs', fn ($sub) => $sub->where('gudangs.id', $gudangId));
                        })
                        ->first();
                    $approverId = $adminGudang?->id ?? auth()->id();

                    $record->update(['status' => 'Pending', 'approver_id' => $approverId]);
                    Notification::make()->title('Status kembali ke Pending. Perlu di-approve ulang.')->success()->send();
                }),

            // ===== BLUETOOTH PRINT =====
            Action::make('bluetoothPrint')
                ->label('Print Bluetooth')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->extraAttributes([
                    'onclick' => "window.printViaBluetooth(this, 'pembelian', '".route('bluetooth.pembelian', $record->id)."', { printLogo: false }); return false;",
                ]),

            Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn () => route('pembelian.print', $record))
                ->openUrlInNewTab(),

            // ===== QR CODE =====
            Action::make('qrCode')
                ->label('QR Code')
                ->icon('heroicon-o-qr-code')
                ->color('success')
                ->modalHeading('QR Code Dokumen')
                ->modalDescription(fn () => 'Scan QR Code untuk melihat dokumen publik.')
                ->modalContent(fn () => view('filament.modals.qr-code', [
                    'url' => url("invoice/pembelian/{$record->uuid}"),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup'),

            // ===== EDIT (super_admin only) =====
            EditAction::make()->visible(fn () => $user->isSuperAdmin()),

            // ===== DELETE =====
            DeleteAction::make()->visible(fn (): bool => $user->isSuperAdmin() && TransactionDeleteGuard::canDeletePembelian($record)),
        ];
    }
}
