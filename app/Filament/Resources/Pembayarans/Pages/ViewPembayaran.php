<?php

namespace App\Filament\Resources\Pembayarans\Pages;

use App\Filament\Concerns\TransactionDeleteGuard;
use App\Filament\Resources\Pembayarans\PembayaranResource;
use App\Filament\Resources\Penjualans\PenjualanResource;
use App\Models\Pembayaran;
use App\Services\PaymentSettlementService;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewPembayaran extends ViewRecord
{
    protected static string $resource = PembayaranResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pembayaran')
                    ->icon('heroicon-o-banknotes')
                    ->columns(['default' => 2])
                    ->schema([
                        // Left column
                        TextEntry::make('nomor')
                            ->label('Nomor')
                            ->weight('bold')
                            ->size('lg')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('tgl_pembayaran')
                            ->label('Tanggal')
                            ->date('d M Y'),
                        TextEntry::make('metode_pembayaran')
                            ->label('Metode Pembayaran'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Pending' => 'warning',
                                'Approved' => 'primary',
                                default => 'gray',
                            }),
                        // Right column
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
                    ]),

                Section::make('Referensi Invoice Penjualan')
                    ->icon('heroicon-o-document-text')
                    ->columns(['default' => 2])
                    ->schema([
                        TextEntry::make('penjualan.nomor')
                            ->label('Invoice')
                            ->url(fn (Pembayaran $record): ?string => $record->penjualan_id
                                ? PenjualanResource::getUrl('view', ['record' => $record->penjualan_id])
                                : null),
                        TextEntry::make('penjualan.pelanggan')
                            ->label('Pelanggan'),
                        TextEntry::make('penjualan.grand_total')
                            ->label('Total Invoice')
                            ->money('IDR'),
                        TextEntry::make('sisa_hutang')
                            ->label('Sisa Hutang')
                            ->state(fn (Pembayaran $record): float => max(0, (float) ($record->penjualan?->grand_total ?? 0) -
                                (float) Pembayaran::where('penjualan_id', $record->penjualan_id)
                                    ->where('status', 'Approved')
                                    ->sum('jumlah_bayar'))
                            )
                            ->money('IDR')
                            ->color('danger')
                            ->weight('bold'),
                        TextEntry::make('jumlah_bayar')
                            ->label('Jumlah Bayar')
                            ->money('IDR')
                            ->size('lg')
                            ->weight('bold')
                            ->color('success'),
                        TextEntry::make('keterangan')
                            ->label('Keterangan')
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
            ]);
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $record = $this->getRecord();

        return [
            // ===== EDIT LAMPIRAN =====
            EditAction::make()
                ->label('Edit Lampiran')
                ->icon('heroicon-o-paper-clip')
                ->color('gray')
                ->visible(function () use ($user, $record): bool {
                    if ($user->isSuperAdmin()) {
                        return true;
                    }
                    if ($user->role === 'admin') {
                        return $user->canAccessGudang($record->gudang_id);
                    }

                    return false;
                }),

            // ===== APPROVE =====
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $record->status === 'Pending' && in_array($user->role, ['admin', 'super_admin']))
                ->action(function () use ($record, $user): void {
                    if ($user->role === 'admin') {
                        $cg = $user?->getCurrentGudang();
                        if (! $cg || (int) $record->gudang_id !== (int) $cg->id) {
                            Notification::make()->title('Hanya bisa approve pembayaran di gudang aktif.')->danger()->send();

                            return;
                        }
                    }

                    try {
                        app(PaymentSettlementService::class)->approvePiutangPayment($record, $user->id);
                        $record->refresh();

                        Notification::make()->title('Pembayaran berhasil di-approve.')->success()->send();
                    } catch (DomainException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    } catch (\Exception) {
                        Notification::make()->title('Gagal approve pembayaran.')->danger()->send();
                    }
                }),

            // ===== CANCEL =====
            Action::make('cancel')
                ->label('Batalkan')
                ->icon('heroicon-o-no-symbol')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(function () use ($record, $user): bool {
                    if ($record->status === 'Canceled') {
                        return false;
                    }
                    if ($user->isSuperAdmin()) {
                        return true;
                    }
                    if ($record->status !== 'Pending') {
                        return false;
                    }

                    return in_array($user->role, ['admin', 'super_admin']);
                })
                ->action(function () use ($record, $user): void {
                    if ($user->role === 'admin') {
                        $cg = $user?->getCurrentGudang();
                        if (! $cg || (int) $record->gudang_id !== (int) $cg->id) {
                            Notification::make()->title('Hanya bisa cancel pembayaran di gudang aktif.')->danger()->send();

                            return;
                        }
                    }

                    try {
                        app(PaymentSettlementService::class)->cancelPiutangPayment($record);
                        $record->refresh();

                        Notification::make()->title('Pembayaran dibatalkan.')->success()->send();
                    } catch (DomainException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    } catch (\Exception) {
                        Notification::make()->title('Gagal membatalkan pembayaran.')->danger()->send();
                    }
                }),

            // ===== UNCANCEL =====
            Action::make('uncancel')
                ->label('Batalkan Pembatalan')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn (): bool => $record->status === 'Canceled' && $user->isSuperAdmin())
                ->action(function () use ($record): void {
                    try {
                        app(PaymentSettlementService::class)->uncancelPiutangPayment($record);
                        $record->refresh();

                        Notification::make()
                            ->title('Status kembali ke Pending.')
                            ->success()
                            ->send();
                    } catch (DomainException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    } catch (\Exception) {
                        Notification::make()->title('Gagal membatalkan pembatalan pembayaran.')->danger()->send();
                    }
                }),

            // ===== PRINT =====
            Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn (): string => route('pembayaran.print', $record->id))
                ->openUrlInNewTab(),

            // ===== QR CODE =====
            Action::make('qrCode')
                ->label('QR Code')
                ->icon('heroicon-o-qr-code')
                ->color('success')
                ->modalHeading('QR Code Pembayaran')
                ->modalDescription(fn (): string => 'Scan QR Code untuk melihat pembayaran.')
                ->modalContent(fn () => view('filament.modals.qr-code', [
                    'url' => url("invoice/pembayaran/{$record->uuid}"),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup'),

            // ===== DELETE =====
            DeleteAction::make()
                ->label('Hapus')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $user->isSuperAdmin() && TransactionDeleteGuard::canDeletePembayaran($record)),
        ];
    }
}
