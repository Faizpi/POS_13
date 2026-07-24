<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema as DbSchema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pengguna')
                    ->description('Data dasar pengguna sistem.')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        FileUpload::make('avatar')
                            ->label('Foto Profil')
                            ->image()
                            ->avatar()
                            ->imageEditor()
                            ->circleCropper()
                            ->directory('avatars')
                            ->maxSize(5120)
                            ->columnSpanFull(),

                        TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Email')
                            ->required()
                            ->email()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('no_telp')
                            ->label('No. Telepon')
                            ->tel()
                            ->maxLength(20),

                        Textarea::make('alamat')
                            ->label('Alamat')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Akses & Role')
                    ->description('Tentukan role dan gudang yang bisa diakses.')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Select::make('role')
                            ->label('Role')
                            ->required()
                            ->options([
                                'super_admin' => 'Super Admin',
                                'spectator' => 'Spectator (Read-Only)',
                                'admin' => 'Admin',
                                'user' => 'User (Sales)',
                            ])
                            ->native(false)
                            ->live()
                            ->columnSpanFull(),

                        Select::make('gudang_id')
                            ->label('Gudang')
                            ->relationship('gudang', 'nama_gudang')
                            ->searchable()
                            ->preload()
                            ->required(fn (callable $get) => $get('role') === 'user')
                            ->visible(fn (callable $get) => $get('role') === 'user')
                            ->helperText('Wajib untuk role User'),

                        CheckboxList::make('gudangs')
                            ->label('Gudang yang Dikelola')
                            ->relationship('gudangs', 'nama_gudang')
                            ->bulkToggleable()
                            ->required(fn (callable $get) => in_array($get('role'), ['admin', 'spectator']))
                            ->visible(fn (callable $get) => $get('role') === 'admin')
                            ->columns(2)
                            ->helperText('Pilih satu atau lebih gudang yang akan dikelola admin ini'),

                        CheckboxList::make('spectatorGudangs')
                            ->label('Gudang yang Dapat Diakses')
                            ->relationship('spectatorGudangs', 'nama_gudang')
                            ->bulkToggleable()
                            ->required(fn (callable $get) => $get('role') === 'spectator')
                            ->visible(fn (callable $get) => $get('role') === 'spectator')
                            ->columns(2)
                            ->helperText('Spectator hanya bisa melihat (read-only) data dari gudang yang dipilih'),
                    ])
                    ->columns(1),

                Section::make('Hak Export & Notifikasi')
                    ->description('Hak akses tambahan untuk admin.')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Toggle::make('receives_transaction_email')
                            ->label('Penerima Email Transaksi')
                            ->helperText('User akan menerima email saat ada transaksi baru/butuh approval')
                            ->default(true),

                        Toggle::make('receives_transaction_whatsapp')
                            ->label('Penerima WhatsApp Transaksi')
                            ->helperText('User akan menerima notifikasi WhatsApp saat ada penjualan baru')
                            ->default(true)
                            ->visible(fn () => DbSchema::hasColumn('users', 'receives_transaction_whatsapp')),

                        Toggle::make('can_export_pdf')
                            ->label('Hak Export PDF')
                            ->visible(fn (callable $get) => $get('role') === 'admin')
                            ->default(false),

                        Toggle::make('can_export_excel')
                            ->label('Hak Export Excel')
                            ->visible(fn (callable $get) => $get('role') === 'admin')
                            ->default(false),
                    ])
                    ->columns(3),

                Section::make('Password')
                    ->description('Atur password login.')
                    ->icon('heroicon-o-key')
                    ->schema([
                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->minLength(8)
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText(fn (string $operation): string => $operation === 'edit' ? 'Kosongkan jika tidak ingin mengubah password' : 'Minimal 8 karakter'),

                        TextInput::make('password_confirmation')
                            ->label('Konfirmasi Password')
                            ->password()
                            ->revealable()
                            ->same('password')
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(false),
                    ])
                    ->columns(2),
            ]);
    }
}
