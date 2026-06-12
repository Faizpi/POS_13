<?php

namespace App\Filament\Resources\Biayas\Pages;

use App\Filament\Resources\Biayas\BiayaResource;
use App\Models\Biaya;
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

class ViewBiaya extends ViewRecord
{
    protected static string $resource = BiayaResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Info Utama')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        TextEntry::make('jenis_biaya')
                            ->label('Jenis Biaya')
                            ->badge()
                            ->formatStateUsing(fn($state) => $state === 'masuk' ? 'Biaya Masuk' : 'Biaya Keluar')
                            ->color(fn($state) => $state === 'masuk' ? 'success' : 'danger'),
                        TextEntry::make('user.name')->label('Pembuat'),
                        TextEntry::make('gudang.nama_gudang')->label('Gudang')->badge()->color('info'),
                        TextEntry::make('approver.name')->label('Approver')->placeholder('—'),
                        TextEntry::make('penerima')->label('Penerima')->placeholder('—'),
                        TextEntry::make('tgl_transaksi')->label('Tgl Transaksi')->date('d F Y'),
                        TextEntry::make('created_at')->label('Dibuat')->dateTime('d M Y, H:i'),
                        TextEntry::make('updated_at')
                            ->label('Diupdate')
                            ->dateTime('d M Y, H:i')
                            ->visible(fn($record) => $record->updated_at != $record->created_at),
                        TextEntry::make('bayar_dari')->label('Bayar Dari')->placeholder('—'),
                        TextEntry::make('cara_pembayaran')->label('Cara Pembayaran')->placeholder('—'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn(string $state): string => match($state) {
                                'Pending' => 'warning',
                                'Approved' => 'primary',
                                'Canceled' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('subtotal')
                            ->label('Subtotal')
                            ->state(fn($record) => $record->items->sum('jumlah'))
                            ->money('IDR'),
                        TextEntry::make('tax_percentage')->label('Pajak')->suffix('%'),
                        TextEntry::make('grand_total')
                            ->label('Grand Total')
                            ->money('IDR')
                            ->size('lg')
                            ->weight('bold')
                            ->color('primary'),
                        TextEntry::make('tag')->label('Tag')->placeholder('—'),
                        TextEntry::make('koordinat')
                            ->label('Koordinat')
                            ->placeholder('—')
                            ->url(fn($record) => $record->koordinat ? 'https://www.google.com/maps?q=' . urlencode($record->koordinat) : null, true),
                    ])
                    ->columns(3),

                Section::make('Rincian Biaya')
                    ->icon('heroicon-o-list-bullet')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('kategori')->label('Akun Biaya')->weight('bold'),
                                TextEntry::make('deskripsi')->label('Deskripsi')->placeholder('—'),
                                TextEntry::make('jumlah')->label('Jumlah')->money('IDR'),
                            ])
                            ->columns(3),
                    ]),

                Section::make('Catatan')
                    ->icon('heroicon-o-paper-clip')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('memo')->placeholder('Tidak ada memo')->columnSpanFull(),
                    ]),

                Section::make('Lampiran')
                    ->icon('heroicon-o-photo')
                    ->collapsible()
                    ->visible(fn() => !empty($this->getRecord()->lampiran_paths) || !empty($this->getRecord()->lampiran_path))
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
                                    $url = asset('storage/' . $path);
                                    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);

                                    $html .= '<div class="flex flex-col items-center justify-center p-4 border rounded-lg shadow-sm">';
                                    if ($isImage) {
                                        $html .= '<a href="' . $url . '" target="_blank" class="block w-full h-32 mb-2 bg-gray-100 rounded flex items-center justify-center overflow-hidden hover:opacity-75 transition">';
                                        $html .= '<img src="' . $url . '" class="max-w-full max-h-full object-contain" alt="Lampiran">';
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
                ->modalHeading('Setujui Biaya?')
                ->modalDescription('Status akan berubah menjadi "Approved".')
                ->visible(fn() => $record->status === 'Pending' && in_array($user->role, ['admin', 'super_admin']))
                ->action(function () use ($record, $user) {
                    if ($user->role === 'admin') {
                        // Cek apakah admin adalah approver_id ATAU gudang match
                        $canApprove = false;
                        if ($record->approver_id == $user->id) {
                            $canApprove = true;
                        } else {
                            $cg = $user?->getCurrentGudang();
                            if ($cg && $cg->id == $record->gudang_id) {
                                $canApprove = true;
                            }
                        }
                        if (!$canApprove) {
                            Notification::make()->title('Hanya bisa approve transaksi di gudang aktif atau yang ditunjuk.')->danger()->send();
                            return;
                        }
                    }
                    $record->update(['status' => 'Approved', 'approver_id' => $user->id]);
                    Notification::make()->title('Biaya berhasil di-approve.')->success()->send();
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
                    if ($record->status === 'Canceled') return false;
                    if ($user->isSuperAdmin()) return true;
                    if ($record->status !== 'Pending') return false;
                    // Gap B1 fix: User TIDAK bisa cancel biaya (sesuai legacy)
                    return $user->role === 'admin';
                })
                ->action(function () use ($record, $user) {
                    if ($user->role === 'admin') {
                        $cg = $user?->getCurrentGudang();
                        if (!$cg || $cg->id !== $record->gudang_id) {
                            Notification::make()->title('Hanya bisa cancel di gudang aktif.')->danger()->send();
                            return;
                        }
                    }
                    $record->update(['status' => 'Canceled']);
                    Notification::make()->title('Biaya dibatalkan.')->success()->send();
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
                        'approver_id' => $user->id
                    ]);
                    Notification::make()->title('Status kembali ke Pending. Perlu di-approve ulang.')->success()->send();
                }),

            // ===== BLUETOOTH PRINT =====
            Action::make('bluetoothPrint')
                ->label('Print Bluetooth')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->extraAttributes([
                    'onclick' => "window.printViaBluetooth(this, 'biaya', '" . route('bluetooth.biaya', $record->id) . "', { printLogo: false }); return false;",
                ]),

            Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn() => route('biaya.print', $record))
                ->openUrlInNewTab(),

            // ===== QR CODE =====
            Action::make('qrCode')
                ->label('QR Code')
                ->icon('heroicon-o-qr-code')
                ->color('success')
                ->modalHeading('QR Code Invoice')
                ->modalDescription(fn() => 'Scan QR Code untuk melihat invoice publik.')
                ->modalContent(fn() => view('filament.modals.qr-code', [
                    'url' => url("invoice/biaya/{$record->uuid}"),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup'),

            // ===== EDIT (super_admin only) =====
            EditAction::make()->visible(fn() => $user->isSuperAdmin()),

            // ===== DELETE =====
            DeleteAction::make()->visible(fn() => $user->isSuperAdmin()),
        ];
    }
}
