<?php

namespace App\Filament\Resources\Kontaks\Pages;

use App\Filament\Resources\Kontaks\KontakResource;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EditKontak extends EditRecord
{
    protected static string $resource = KontakResource::class;

    /**
     * Gap A1 fix: User dan admin hanya bisa edit PIN saja (sesuai legacy).
     * Hanya super_admin yang boleh edit data penuh.
     */
    public function form(Schema $schema): Schema
    {
        $user = auth()->user();

        if (! $user?->isSuperAdmin()) {
            // User dan admin: hanya PIN yang bisa diedit
            return $schema->components([
                Section::make('Edit PIN Customer')
                    ->description('Anda hanya memiliki akses untuk mengubah PIN customer.')
                    ->icon('heroicon-o-key')
                    ->schema([
                        TextInput::make('pin')
                            ->label('PIN Customer (6 digit)')
                            ->password()
                            ->revealable()
                            ->maxLength(6)
                            ->minLength(6)
                            ->placeholder('Masukkan PIN baru'),
                    ]),
            ]);
        }

        // Super admin: form lengkap (dari KontakForm)
        return parent::form($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => auth()->user()?->isSuperAdmin()),
        ];
    }
}
