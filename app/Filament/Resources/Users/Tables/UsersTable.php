<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->summaries(false, false)
            ->columns([
                ImageColumn::make('avatar')
                    ->label('')
                    ->circular()
                    ->disk('public')
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name='.urlencode($record->name).'&color=fff&background=random'),

                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->email),

                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'super_admin' => 'Super Admin',
                        'admin' => 'Admin',
                        'spectator' => 'Spectator',
                        'user' => 'User',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'admin' => 'success',
                        'spectator' => 'info',
                        'user' => 'primary',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'super_admin' => 'heroicon-o-star',
                        'admin' => 'heroicon-o-briefcase',
                        'spectator' => 'heroicon-o-eye',
                        'user' => 'heroicon-o-user',
                        default => 'heroicon-o-user',
                    }),

                TextColumn::make('gudang.nama_gudang')
                    ->label('Gudang')
                    ->placeholder('—')
                    ->badge()
                    ->color('info'),

                TextColumn::make('gudangs.nama_gudang')
                    ->label('Gudang Multi')
                    ->badge()
                    ->color('success')
                    ->separator(',')
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->placeholder('—')
                    ->toggleable(),

                IconColumn::make('receives_transaction_email')
                    ->label('Email')
                    ->boolean()
                    ->alignCenter(),

                IconColumn::make('can_export_pdf')
                    ->label('Exp PDF')
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(),

                IconColumn::make('can_export_excel')
                    ->label('Exp Excel')
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'super_admin' => 'Super Admin',
                        'admin' => 'Admin',
                        'spectator' => 'Spectator',
                        'user' => 'User',
                    ]),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn ($record) => auth()->id() !== $record->id),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada pengguna')
            ->emptyStateDescription('Tambahkan pengguna pertama untuk mulai menggunakan sistem.')
            ->emptyStateIcon('heroicon-o-users');
    }
}
