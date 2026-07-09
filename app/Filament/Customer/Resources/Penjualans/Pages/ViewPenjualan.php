<?php

namespace App\Filament\Customer\Resources\Penjualans\Pages;

use App\Filament\Customer\Resources\Penjualans\PenjualanResource;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewPenjualan extends ViewRecord
{
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
                            ->color(fn (string $state): string => match ($state) {
                                'Pending' => 'warning',
                                'Approved' => 'primary',
                                'Lunas' => 'success',
                                'Canceled' => 'gray',
                                default => 'gray',
                            }),
                        TextEntry::make('tgl_transaksi')->label('Tgl Transaksi')->date('d F Y'),
                        TextEntry::make('tgl_jatuh_tempo')->label('Jatuh Tempo')->date('d F Y')->placeholder('—'),
                        TextEntry::make('syarat_pembayaran')->label('Syarat Pembayaran'),
                        TextEntry::make('gudang.nama_gudang')->label('Gudang'),
                    ])
                    ->columns(3),

                Section::make('Item Penjualan')
                    ->icon('heroicon-o-shopping-cart')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('produk.nama_produk')->label('Produk')->weight('bold'),
                                TextEntry::make('kuantitas')->label('Qty')->suffix(fn ($record) => ' '.$record->unit),
                                TextEntry::make('harga_satuan')->label('Harga')->money('IDR'),
                                TextEntry::make('diskon')->label('Disc')->suffix('%'),
                                TextEntry::make('jumlah_baris')->label('Total')->money('IDR')->weight('bold'),
                            ])
                            ->columns(5),
                    ]),

                Section::make('Total & Pajak')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        TextEntry::make('subtotal')
                            ->label('Subtotal')
                            ->state(fn ($record) => $record->items->sum('jumlah_baris'))
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
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print Invoice')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn () => route('penjualan.print', $this->getRecord()))
                ->openUrlInNewTab(),
        ];
    }
}
