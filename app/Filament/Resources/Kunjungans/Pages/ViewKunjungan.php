<?php

namespace App\Filament\Resources\Kunjungans\Pages;

use App\Filament\Resources\Kunjungans\KunjunganResource;
use App\Models\GudangProduk;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Illuminate\Support\Facades\DB;

class ViewKunjungan extends ViewRecord
{
    protected static string $resource = KunjunganResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Info Kunjungan')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        // Left
                        TextEntry::make('tujuan')
                            ->label('Tujuan Kunjungan')
                            ->badge()
                            ->color(fn($state): string => match ($state) {
                                'Pemeriksaan Stock' => 'info',
                                'Penagihan' => 'warning',
                                'Promo Gratis' => 'success',
                                'Promo Sample' => 'purple',
                                default => 'gray',
                            }),
                        TextEntry::make('user.name')->label('Pembuat'),
                        TextEntry::make('approver.name')->label('Approver')->placeholder('—'),
                        TextEntry::make('gudang.nama_gudang')->label('Gudang')->badge()->color('info'),
                        TextEntry::make('tgl_kunjungan')->label('Tgl Kunjungan')->date('d F Y'),
                        TextEntry::make('created_at')->label('Dibuat')->dateTime('d M Y, H:i'),
                        // Right
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'Pending' => 'warning',
                                'Approved' => 'primary',
                                'Canceled' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('nomor')->label('No. Kunjungan')->weight('bold'),
                        TextEntry::make('kontak.kode_kontak')
                            ->label('Kode Kontak')
                            ->badge()
                            ->color('secondary')
                            ->placeholder('—'),
                        TextEntry::make('sales_nama')->label('Pelanggan')->placeholder('—'),
                        TextEntry::make('sales_no_telepon')->label('No. Telepon')->placeholder('—')
                            ->formatStateUsing(fn($state) => \receipt_format_phone($state)),
                        TextEntry::make('sales_alamat')->label('Alamat')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('koordinat')
                            ->label('Koordinat')
                            ->placeholder('—')
                            ->url(fn($record) => $record->koordinat
                                ? 'https://www.google.com/maps?q=' . urlencode($record->koordinat)
                                : null, true),
                    ])
                    ->columns(3),

                Section::make('Produk Terkait')
                    ->icon('heroicon-o-archive-box')
                    ->visible(fn(): bool => $this->getRecord()->items->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('produk.item_code')->label('Kode')->placeholder('—'),
                                TextEntry::make('produk.nama_produk')->label('Nama Produk')->weight('bold'),
                                TextEntry::make('jumlah')->label('Qty'),
                                TextEntry::make('batch_number')->label('Batch')->placeholder('—'),
                                TextEntry::make('expired_date')->label('Expired')->date('d/m/Y')->placeholder('—'),
                            ])
                            ->columns(5),
                    ]),

                Section::make('Catatan & Lampiran')
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
                ->modalHeading('Setujui Kunjungan?')
                ->modalDescription('Status akan berubah menjadi "Approved".')
                ->visible(function () use ($record, $user) {
                    if ($record->status !== 'Pending') return false;
                    if ($user->isSuperAdmin()) return true;
                    if ($user->role !== 'admin') return false;
                    
                    // Admin can approve if assigned as approver or has access to the gudang
                    return $record->approver_id == $user->id || 
                           ($record->gudang_id && method_exists($user, 'canAccessGudang') && $user->canAccessGudang($record->gudang_id));
                })
                ->action(function () use ($record, $user) {
                    $isPromo = in_array($record->tujuan, ['Promo Gratis', 'Promo Sample']);

                    if ($isPromo && $record->items->isNotEmpty()) {
                        // Tentukan kolom stok yang akan dikurangi
                        $stokColumn = $record->tujuan === 'Promo Gratis' ? 'stok_gratis' : 'stok_sample';
                        $gudangId   = $record->gudang_id;

                        if (!$gudangId) {
                            Notification::make()->title('Kunjungan tidak terhubung ke gudang.')->danger()->send();
                            return;
                        }

                        DB::beginTransaction();
                        try {
                            foreach ($record->items as $item) {
                                $stok = GudangProduk::where('gudang_id', $gudangId)
                                    ->where('produk_id', $item->produk_id)
                                    ->lockForUpdate()
                                    ->first();

                                $stokTersedia = $stok?->{$stokColumn} ?? 0;
                                $namaProduk   = $item->produk?->nama_produk ?? 'ID: ' . $item->produk_id;

                                if ($stokTersedia < $item->jumlah) {
                                    throw new \Exception(
                                        "{$namaProduk}: stok tersedia {$stokTersedia}, dibutuhkan {$item->jumlah}."
                                    );
                                }

                                // Kurangi stok total dan kolom spesifik (gratis/sample)
                                $stok->decrement('stok', $item->jumlah);
                                $stok->decrement($stokColumn, $item->jumlah);
                            }

                            $record->update([
                                'status'      => 'Approved',
                                'approver_id' => $user->id,
                            ]);

                            DB::commit();
                            Notification::make()
                                ->title('Kunjungan di-approve. Stok ' . str_replace('stok_', '', $stokColumn) . ' telah dikurangi.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            DB::rollBack();
                            Notification::make()->title('Gagal approve: ' . $e->getMessage())->danger()->send();
                        }
                    } else {
                        // Tujuan selain Promo: langsung approve tanpa ubah stok
                        $record->update([
                            'status'      => 'Approved',
                            'approver_id' => $user->id,
                        ]);
                        Notification::make()->title('Kunjungan berhasil di-approve.')->success()->send();
                    }
                }),

            // ===== CANCEL =====
            Action::make('cancel')
                ->label('Cancel')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Batalkan Kunjungan?')
                ->modalDescription('Kunjungan yang dibatalkan tidak dapat diproses kembali tanpa Super Admin.')
                ->visible(function () use ($record, $user) {
                    if ($record->status === 'Canceled') return false;
                    if ($user->isSuperAdmin()) return true;
                    
                    // Admin can only cancel if Pending and gudang matches
                    if ($user->role === 'admin' && $record->status === 'Pending') {
                        return $record->gudang_id && method_exists($user, 'canAccessGudang') && $user->canAccessGudang($record->gudang_id);
                    }
                    
                    return false;
                })
                ->action(function () use ($record) {
                    $isPromo = in_array($record->tujuan, ['Promo Gratis', 'Promo Sample']);

                    DB::beginTransaction();
                    try {
                        // Kembalikan stok jika kunjungan Promo sudah Approved
                        if ($isPromo && $record->status === 'Approved' && $record->gudang_id && $record->items->isNotEmpty()) {
                            $stokColumn = $record->tujuan === 'Promo Gratis' ? 'stok_gratis' : 'stok_sample';

                            foreach ($record->items as $item) {
                                $stok = GudangProduk::where('gudang_id', $record->gudang_id)
                                    ->where('produk_id', $item->produk_id)
                                    ->lockForUpdate()
                                    ->first();

                                if ($stok) {
                                    $stok->increment('stok', $item->jumlah);
                                    $stok->increment($stokColumn, $item->jumlah);
                                }
                            }
                        }

                        $record->update(['status' => 'Canceled']);
                        DB::commit();
                        Notification::make()->title('Kunjungan dibatalkan.' . ($isPromo && $record->status === 'Approved' ? ' Stok telah dikembalikan.' : ''))->success()->send();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Notification::make()->title('Gagal cancel: ' . $e->getMessage())->danger()->send();
                    }
                }),

            // ===== UNCANCEL =====
            Action::make('uncancel')
                ->label('Batalkan Pembatalan')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn() => $record->status === 'Canceled' && $user->isSuperAdmin())
                ->action(function () use ($record, $user) {
                    // Resolve approver_id based on gudang logic (like legacy)
                    $approverId = $user->id; // fallback
                    $gudangId = $record->gudang_id;
                    if ($gudangId) {
                        $adminGudang = \App\Models\User::where('role', 'admin')
                            ->where(function ($q) use ($gudangId) {
                                $q->where('gudang_id', $gudangId)
                                    ->orWhere('current_gudang_id', $gudangId)
                                    ->orWhereHas('gudangs', fn($sub) => $sub->where('gudangs.id', $gudangId));
                            })
                            ->first();
                        if ($adminGudang) $approverId = $adminGudang->id;
                    }
                    
                    $record->update([
                        'status' => 'Pending',
                        'approver_id' => $approverId,
                    ]);
                    Notification::make()->title('Status kembali ke Pending.')->success()->send();
                }),

            // ===== BLUETOOTH PRINT =====
            Action::make('bluetoothPrint')
                ->label('Print Bluetooth')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->extraAttributes([
                    'onclick' => "window.printViaBluetooth(this, 'kunjungan', '" . route('bluetooth.kunjungan', $record->id) . "', { printLogo: false }); return false;",
                ]),

            Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn() => route('kunjungan.print', $record))
                ->openUrlInNewTab(),

            // ===== QR CODE =====
            Action::make('qrCode')
                ->label('QR Code')
                ->icon('heroicon-o-qr-code')
                ->color('success')
                ->modalHeading('QR Code Kunjungan')
                ->modalDescription(fn() => 'Scan QR Code untuk melihat detail kunjungan.')
                ->modalContent(fn() => view('filament.modals.qr-code', [
                    'url' => url("invoice/kunjungan/{$record->uuid}"),
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
