<?php

namespace App\Filament\Resources\Kontaks\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class KontakForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Kontak')
                    ->description('Data customer atau supplier untuk transaksi.')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        TextInput::make('kode_kontak')
                            ->label('Kode Kontak')
                            ->maxLength(50)
                            ->placeholder('Auto-generate jika kosong')
                            ->helperText('Contoh: KT00001, CUST-001')
                            ->disabled(fn() => !in_array(auth()->user()?->role, ['admin', 'super_admin'])),

                        TextInput::make('nama')
                            ->label('Nama Kontak')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2)
                            ->disabled(fn() => !in_array(auth()->user()?->role, ['admin', 'super_admin'])),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255)
                            ->disabled(fn() => !in_array(auth()->user()?->role, ['admin', 'super_admin'])),

                        TextInput::make('no_telp')
                            ->label('No. Telepon')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('628xxxxxxxxxx')
                            ->helperText('Format: 628xxxxxxxxxx (login customer portal)'),

                        TextInput::make('pin')
                            ->label('PIN Customer (6 digit)')
                            ->password()
                            ->revealable()
                            ->maxLength(6)
                            ->minLength(6)
                            ->placeholder('Untuk login portal customer')
                            ->disabled(fn() => !in_array(auth()->user()?->role, ['admin', 'super_admin'])),
                    ])
                    ->columns(3),

                Section::make('Detail Tambahan')
                    ->schema([
                        Textarea::make('alamat')
                            ->label('Alamat')
                            ->rows(3)
                            ->columnSpanFull()
                            ->disabled(fn() => !in_array(auth()->user()?->role, ['admin', 'super_admin'])),

                        TextInput::make('diskon_persen')
                            ->label('Diskon Bawaan (%)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->helperText('Diskon otomatis untuk customer ini')
                            ->disabled(fn() => !in_array(auth()->user()?->role, ['admin', 'super_admin'])),

                        Select::make('gudang_id')
                            ->label('Gudang')
                            ->relationship('gudang', 'nama_gudang')
                            ->searchable()
                            ->preload()
                            ->placeholder('Pilih gudang')
                            ->default(fn() => auth()->user()?->getCurrentGudang()?->id)
                            ->visible(fn() => auth()->user()?->isSuperAdmin())
                            ->disabled(fn() => !auth()->user()?->isSuperAdmin()),
                    ])
                    ->columns(2),
            ]);
    }
}
