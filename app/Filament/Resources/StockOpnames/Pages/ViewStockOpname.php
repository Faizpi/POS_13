<?php

namespace App\Filament\Resources\StockOpnames\Pages;

use App\Filament\Resources\StockOpnames\StockOpnameResource;
use App\Models\GudangProduk;
use App\Models\StokLog;
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

class ViewStockOpname extends ViewRecord
{
    protected static string $resource = StockOpnameResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Opname')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        TextEntry::make('nomor')
                            ->label('Nomor')
                            ->weight('bold'),

                        TextEntry::make('tgl_opname')
                            ->label('Tanggal Opname')
                            ->date('d F Y'),

                        TextEntry::make('gudang.nama_gudang')
                            ->label('Gudang'),

                        TextEntry::make('user.name')
                            ->label('Pembuat'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Draft' => 'gray',
                                'Submitted' => 'warning',
                                'Applied' => 'success',
                                default => 'gray',
                            }),

                        TextEntry::make('created_at')
                            ->label('Dibuat')
                            ->dateTime('d M Y, H:i'),

                        TextEntry::make('memo')
                            ->label('Memo')
                            ->placeholder('Tidak ada memo')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make('Item Opname')
                    ->icon('heroicon-o-list-bullet')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('produk.nama_produk')
                                    ->label('Produk')
                                    ->weight('bold'),

                                TextEntry::make('batch_number')
                                    ->label('No Batch')
                                    ->placeholder('—'),

                                TextEntry::make('expired_date')
                                    ->label('Exp Date')
                                    ->date('d/m/Y')
                                    ->placeholder('—'),

                                TextEntry::make('qty_system')
                                    ->label('Qty System'),

                                TextEntry::make('qty_aktual')
                                    ->label('Qty Aktual')
                                    ->weight('bold'),

                                TextEntry::make('selisih')
                                    ->label('Selisih')
                                    ->badge()
                                    ->color(fn ($state) => $state == 0 ? 'success' : ($state > 0 ? 'info' : 'danger')),

                                TextEntry::make('keterangan')
                                    ->label('Keterangan')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ])
                            ->columns(3),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $record = $this->getRecord();

        return [
            Action::make('submit')
                ->label('Submit')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Submit Stock Opname?')
                ->modalDescription('Status akan berubah menjadi Submitted dan siap untuk di-apply oleh Super Admin.')
                ->visible(fn () => $record->status === 'Draft' && in_array($user->role, ['admin', 'super_admin']))
                ->action(function () use ($record) {
                    $record->update(['status' => 'Submitted']);
                    Notification::make()->title('Stock opname berhasil di-submit.')->success()->send();
                }),

            Action::make('apply')
                ->label('Apply')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Apply Stock Opname?')
                ->modalDescription('Stok di gudang akan diupdate sesuai qty aktual. Tindakan ini tidak dapat dibatalkan.')
                ->visible(fn () => $record->status === 'Submitted' && $user->isSuperAdmin())
                ->action(function () use ($record, $user) {
                    DB::transaction(function () use ($record, $user) {
                        foreach ($record->items as $item) {
                            $gudangProduk = GudangProduk::firstOrNew([
                                'gudang_id' => $record->gudang_id,
                                'produk_id' => $item->produk_id,
                            ]);

                            $stokSebelum = ($gudangProduk->stok_penjualan ?? 0)
                                + ($gudangProduk->stok_gratis ?? 0)
                                + ($gudangProduk->stok_sample ?? 0);

                            $stokSesudah = (float) $item->qty_aktual;
                            $selisih = $stokSesudah - $stokSebelum;

                            // Update stok (set stok_penjualan = qty_aktual, zero-out others)
                            $gudangProduk->stok_penjualan = $stokSesudah;
                            $gudangProduk->stok_gratis = 0;
                            $gudangProduk->stok_sample = 0;
                            $gudangProduk->stok = $stokSesudah;
                            $gudangProduk->save();

                            // Create StokLog
                            StokLog::create([
                                'gudang_produk_id' => $gudangProduk->id,
                                'produk_id' => $item->produk_id,
                                'gudang_id' => $record->gudang_id,
                                'user_id' => $user->id,
                                'produk_nama' => $item->produk?->nama_produk,
                                'gudang_nama' => $record->gudang?->nama_gudang,
                                'user_nama' => $user->name,
                                'stok_sebelum' => $stokSebelum,
                                'stok_sesudah' => $stokSesudah,
                                'selisih' => $selisih,
                                'keterangan' => 'Stock Opname: '.$record->nomor,
                            ]);
                        }

                        $record->update([
                            'status' => 'Applied',
                            'approver_id' => $user->id,
                        ]);
                    });

                    Notification::make()->title('Stock opname berhasil di-apply. Stok gudang telah diperbarui.')->success()->send();
                }),

            EditAction::make()->visible(fn () => $user->isSuperAdmin()),

            DeleteAction::make()->visible(fn () => $user->isSuperAdmin()),
        ];
    }
}
