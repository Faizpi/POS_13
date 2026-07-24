<?php

namespace App\Filament\Resources\Produks\Pages;

use App\Filament\Resources\Produks\ProdukResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ViewProduk extends ViewRecord
{
    protected static string $resource = ProdukResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Produk')
                    ->icon('heroicon-o-cube')
                    ->columns(['default' => 2])
                    ->schema([
                        TextEntry::make('item_code')
                            ->label('Kode Produk')
                            ->weight('bold')
                            ->badge()
                            ->color('primary'),
                        TextEntry::make('nama_produk')
                            ->label('Nama Produk')
                            ->weight('bold'),
                        TextEntry::make('harga')
                            ->label('Harga Retail')
                            ->money('IDR')
                            ->color('success')
                            ->weight('bold'),
                        TextEntry::make('harga_grosir')
                            ->label('Harga Grosir')
                            ->money('IDR')
                            ->color('primary')
                            ->weight('bold'),
                        TextEntry::make('satuan')
                            ->label('Satuan')
                            ->badge()
                            ->color('info'),
                        TextEntry::make('deskripsi')
                            ->label('Deskripsi')
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label('Dibuat')
                            ->dateTime('d M Y, H:i'),
                        TextEntry::make('updated_at')
                            ->label('Diupdate')
                            ->dateTime('d M Y, H:i'),
                    ]),

                Section::make('Stok per Gudang')
                    ->icon('heroicon-o-building-storefront')
                    ->schema([
                        RepeatableEntry::make('stokDiGudang')
                            ->label('')
                            ->columns(['default' => 2])
                            ->schema([
                                TextEntry::make('gudang.nama_gudang')
                                    ->label('Gudang')
                                    ->weight('bold'),
                                TextEntry::make('stok')
                                    ->label('Stok')
                                    ->badge()
                                    ->color(fn ($state) => $state > 10 ? 'success' : ($state > 0 ? 'warning' : 'danger'))
                                    ->weight('bold'),
                            ]),
                        TextEntry::make('totalStok')
                            ->label('Total Stok')
                            ->state(fn ($record) => $record->stokDiGudang->sum('stok'))
                            ->weight('bold')
                            ->size('lg'),
                    ]),

                Section::make('Barcode & QR Code')
                    ->icon('heroicon-o-qr-code')
                    ->schema([
                        View::make('filament.infolist.produk-barcode'),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();

        return [
            EditAction::make()->visible(fn () => $user->isSuperAdmin()),

            Action::make('downloadPdf')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->url(fn ($record) => route('produk.download', $record->id))
                ->openUrlInNewTab()
                ->visible(fn () => $user->isSuperAdmin()),

            Action::make('print')
                ->label('Print')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn ($record) => route('produk.print', $record->id))
                ->openUrlInNewTab()
                ->visible(fn () => $user->isSuperAdmin()),
        ];
    }
}
