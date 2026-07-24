<?php

namespace App\Filament\Resources\Produks\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProdukForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Produk')
                    ->description('Master data produk untuk transaksi penjualan dan pembelian.')
                    ->icon('heroicon-o-cube')
                    ->schema([
                        TextInput::make('nama_produk')
                            ->label('Nama Produk')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Contoh: Sabun Hibiscus 100ml'),

                        TextInput::make('item_code')
                            ->label('Item Code (SKU)')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('Contoh: SBN-001')
                            ->helperText('Biarkan kosong jika tidak ada SKU'),

                        Select::make('satuan')
                            ->label('Satuan')
                            ->required()
                            ->options([
                                'Pcs' => 'Pcs',
                                'Lusin' => 'Lusin',
                                'Karton' => 'Karton',
                            ])
                            ->default('Pcs')
                            ->native(false),
                    ])
                    ->columns(['default' => 2]),

                Section::make('Harga')
                    ->description('Harga retail dan grosir produk.')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        TextInput::make('harga')
                            ->label('Harga Retail')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->minValue(0),

                        TextInput::make('harga_grosir')
                            ->label('Harga Grosir')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Berlaku jika tipe harga "Grosir" dipilih saat transaksi'),
                    ])
                    ->columns(['default' => 2]),

                Section::make('Deskripsi')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Textarea::make('deskripsi')
                            ->label('Deskripsi Produk')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
