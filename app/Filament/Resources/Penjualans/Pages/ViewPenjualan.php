<?php

namespace App\Filament\Resources\Penjualans\Pages;

use App\Filament\Concerns\ResolvesApprover;
use App\Filament\Resources\Penjualans\PenjualanResource;
use App\Models\GudangProduk;
use App\Models\Penjualan;
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

class ViewPenjualan extends ViewRecord
{
    use ResolvesApprover;
    protected static string $resource = PenjualanResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Info Utama')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        TextEntry::make('nomor')->label('Nomor Invoice')->weight('bold'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn(string $state): string => match($state) {
                                'Pending' => 'warning',
                                'Approved' => 'primary',
                                'Lunas' => 'success',
                                'Canceled' => 'gray',
                                default => 'gray',
                            }),
                        TextEntry::make('user.name')->label('Sales'),
                        TextEntry::make('approver.name')->label('Approver')->placeholder('—'),
                        TextEntry::make('pelanggan')->label('Pelanggan'),
                        TextEntry::make('no_telepon')->label('No. Telepon')->placeholder('—')
                            ->formatStateUsing(fn($state) => \receipt_format_phone($state)),
                        TextEntry::make('alamat_penagihan')->label('Alamat Penagihan')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('tgl_transaksi')->label('Tgl Transaksi')->date('d F Y'),
                        TextEntry::make('tgl_jatuh_tempo')->label('Jatuh Tempo')->date('d F Y')->placeholder('—'),
                        TextEntry::make('syarat_pembayaran')->label('Syarat Pembayaran'),
                        TextEntry::make('tipe_harga')
                            ->label('Tipe Harga')
                            ->badge()
                            ->formatStateUsing(fn($state) => ucfirst($state))
                            ->color(fn($state) => $state === 'grosir' ? 'info' : 'primary'),
                        TextEntry::make('gudang.nama_gudang')->label('Gudang'),
                        TextEntry::make('no_referensi')->label('No. Referensi')->placeholder('—'),
                    ])
                    ->columns(3),

                Section::make('Item Penjualan')
                    ->icon('heroicon-o-shopping-cart')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('produk.nama_produk')->label('Produk')->weight('bold'),
                                TextEntry::make('produk.item_code')->label('SKU')->placeholder('—'),
                                TextEntry::make('kuantitas')->label('Qty')->suffix(fn($record) => ' ' . $record->unit),
                                TextEntry::make('harga_satuan')->label('Harga')->money('IDR'),
                                TextEntry::make('diskon')->label('Disc')->suffix('%'),
                                TextEntry::make('diskon_nominal')->label('Disc Rp')->money('IDR'),
                                TextEntry::make('batch_number')->label('Batch')->placeholder('—'),
                                TextEntry::make('expired_date')->label('Exp')->date('d/m/Y')->placeholder('—'),
                                TextEntry::make('jumlah_baris')->label('Total')->money('IDR')->weight('bold'),
                            ])
                            ->columns(5),
                    ]),

                Section::make('Total & Pajak')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        TextEntry::make('subtotal')
                            ->label('Subtotal')
                            ->state(fn($record) => $record->items->sum('jumlah_baris'))
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

                Section::make('Catatan & Lampiran')
                    ->icon('heroicon-o-paper-clip')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('memo')->placeholder('Tidak ada memo')->columnSpanFull(),
                        TextEntry::make('koordinat')
                            ->label('Koordinat')
                            ->placeholder('—')
                            ->url(fn($record) => $record->koordinat ? 'https://www.google.com/maps?q=' . urlencode($record->koordinat) : null, true),
                    ]),

                Section::make('Lampiran')
                    ->icon('heroicon-o-photo')
                    ->collapsible()
                    ->visible(fn($record) => !empty($record->lampiran_paths) || !empty($record->lampiran_path))
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

                                    $html .= '<div class="flex flex-col items-center justify-center p-4 rounded-lg shadow-sm">';
                                    if ($isImage) {
                                        $html .= '<a href="' . $url . '" class="block w-full h-32 mb-2 bg-gray-100 rounded flex items-center justify-center overflow-hidden hover:opacity-75 transition" onclick="event.preventDefault();previewLampiran(this.href)">';
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
                ->modalHeading('Setujui Penjualan?')
                ->modalDescription('Status akan berubah menjadi "Approved".')
                ->visible(fn() => $record->status === 'Pending' && in_array($user->role, ['admin', 'super_admin']))
                ->action(function () use ($record, $user) {
                    if ($user->role === 'admin') {
                        $cg = $user?->getCurrentGudang();
                        if (!$cg || $cg->id !== $record->gudang_id) {
                            Notification::make()->title('Hanya bisa approve transaksi di gudang aktif.')->danger()->send();
                            return;
                        }
                    }

                    $gudangId = $record->gudang_id;
                    if (!$gudangId) {
                        Notification::make()->title('Transaksi tidak terhubung ke gudang.')->danger()->send();
                        return;
                    }

                    DB::beginTransaction();
                    try {
                        foreach ($record->items as $item) {
                            $stok = GudangProduk::where('gudang_id', $gudangId)
                                ->where('produk_id', $item->produk_id)
                                ->lockForUpdate()
                                ->first();

                            if (!$stok || $stok->stok_penjualan < $item->kuantitas) {
                                $nama = $item->produk?->nama_produk ?? 'ID: ' . $item->produk_id;
                                throw new \Exception("Stok penjualan tidak cukup: {$nama}");
                            }

                            $stok->decrement('stok', $item->kuantitas);
                            $stok->decrement('stok_penjualan', $item->kuantitas);
                        }

                        $record->update(['status' => 'Approved', 'approver_id' => $user->id]);
                        DB::commit();
                        
                        // Send email notification to creator
                        \App\Services\InvoiceEmailService::sendApprovedNotification($record, 'penjualan');

                        Notification::make()->title('Penjualan berhasil di-approve. Stok telah dikurangi.')->success()->send();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Notification::make()->title('Gagal approve: ' . $e->getMessage())->danger()->send();
                    }
                }),

            // ===== MARK AS PAID =====
            Action::make('markAsPaid')
                ->label('Tandai Lunas')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Tandai Lunas?')
                ->visible(fn() => $record->status === 'Approved' && in_array($user->role, ['admin', 'super_admin']))
                ->action(function () use ($record, $user) {
                    if ($user->role === 'admin') {
                        $cg = $user?->getCurrentGudang();
                        if (!$cg || $cg->id !== $record->gudang_id) {
                            Notification::make()->title('Hanya bisa ubah transaksi di gudang aktif.')->danger()->send();
                            return;
                        }
                    }
                    $record->update(['status' => 'Lunas']);
                    Notification::make()->title('Penjualan ditandai LUNAS.')->success()->send();
                }),

            // ===== UNMARK PAID (super_admin only) =====
            Action::make('unmarkAsPaid')
                ->label('Buka Lunas')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn() => $record->status === 'Lunas' && $user->isSuperAdmin())
                ->action(function () use ($record) {
                    $record->update(['status' => 'Approved']);
                    Notification::make()->title('Status dikembalikan ke Approved.')->success()->send();
                }),

            // ===== CANCEL =====
            // Gap D1 fix: User TIDAK bisa cancel penjualan (sesuai legacy).
            // Hanya admin (Pending saja) dan super_admin yang bisa cancel.
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

                    DB::beginTransaction();
                    try {
                        // Kembalikan stok jika sudah Approved atau Lunas
                        if (in_array($record->status, ['Approved', 'Lunas'])) {
                            foreach ($record->items as $item) {
                                $stok = GudangProduk::where('gudang_id', $record->gudang_id)
                                    ->where('produk_id', $item->produk_id)
                                    ->lockForUpdate()
                                    ->first();

                                if ($stok) {
                                    $stok->increment('stok', $item->kuantitas);
                                    $stok->increment('stok_penjualan', $item->kuantitas);
                                } else {
                                    GudangProduk::create([
                                        'gudang_id' => $record->gudang_id,
                                        'produk_id' => $item->produk_id,
                                        'stok' => $item->kuantitas,
                                        'stok_penjualan' => $item->kuantitas,
                                    ]);
                                }
                            }
                        }

                        $record->update(['status' => 'Canceled']);
                        DB::commit();
                        Notification::make()->title('Penjualan dibatalkan. Stok dikembalikan.')->success()->send();
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
                ->action(function () use ($record) {
                    // Cari approver baru berdasarkan gudang
                    $gudangId   = $record->gudang_id;
                    $adminGudang = User::where('role', 'admin')
                        ->where(function ($q) use ($gudangId) {
                            $q->where('gudang_id', $gudangId)
                                ->orWhereHas('gudangs', fn($sub) => $sub->where('gudangs.id', $gudangId));
                        })
                        ->first();

                    $approverId = $adminGudang?->id ?? auth()->id(); // fallback ke super admin yg uncancel

                    $record->update(['status' => 'Pending', 'approver_id' => $approverId]);
                    Notification::make()->title('Status kembali ke Pending. Perlu di-approve ulang.')->success()->send();
                }),

            // ===== BLUETOOTH PRINT =====
            Action::make('bluetoothPrint')
                ->label('Print Bluetooth')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->extraAttributes([
                    'onclick' => "window.printViaBluetooth(this, 'penjualan', '" . route('bluetooth.penjualan', $record->id) . "', { printLogo: false }); return false;",
                ]),

            Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn() => route('penjualan.print', $record))
                ->openUrlInNewTab(),

            // ===== QR CODE =====
            Action::make('qrCode')
                ->label('QR Code')
                ->icon('heroicon-o-qr-code')
                ->color('success')
                ->modalHeading('QR Code Invoice')
                ->modalDescription(fn() => 'Scan QR Code untuk melihat invoice publik.')
                ->modalContent(fn() => view('filament.modals.qr-code', [
                    'url' => url("invoice/penjualan/{$record->uuid}"),
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
