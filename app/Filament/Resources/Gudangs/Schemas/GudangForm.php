<?php

namespace App\Filament\Resources\Gudangs\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GudangForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Gudang')
                    ->description('Data master gudang untuk pengelolaan stok dan transaksi.')
                    ->icon('heroicon-o-building-storefront')
                    ->schema([
                        TextInput::make('nama_gudang')
                            ->label('Nama Gudang')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Contoh: Gudang Pusat'),

                        Textarea::make('alamat_gudang')
                            ->label('Alamat Gudang')
                            ->rows(3)
                            ->placeholder('Alamat lengkap gudang'),
                    ])
                    ->columns(1),
            ]);
    }
}
