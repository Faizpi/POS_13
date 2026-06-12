<?php

namespace App\Filament\Resources\Gudangs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GudangsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->summaries(false, false)
            ->columns([
                TextColumn::make('nama_gudang')
                    ->label('Nama Gudang')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-building-storefront')
                    ->weight('bold'),

                TextColumn::make('alamat_gudang')
                    ->label('Alamat')
                    ->wrap()
                    ->limit(50)
                    ->toggleable(),

                TextColumn::make('users_count')
                    ->label('User')
                    ->counts('users')
                    ->badge()
                    ->color('info'),

                TextColumn::make('gudang_produks_count')
                    ->label('Produk')
                    ->counts('gudangProduks')
                    ->badge()
                    ->color('success'),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('nama_gudang')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada gudang')
            ->emptyStateDescription('Tambahkan gudang pertama untuk mulai mengelola stok dan transaksi.')
            ->emptyStateIcon('heroicon-o-building-storefront');
    }
}
