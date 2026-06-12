<?php

namespace App\Filament\Resources\Kontaks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class KontaksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->summaries(false, false)
            ->columns([
                TextColumn::make('kode_kontak')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('no_telp')
                    ->label('No. Telp')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->icon('heroicon-o-phone')
                    ->formatStateUsing(fn($state) => \receipt_format_phone($state)),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('—')
                    ->icon('heroicon-o-envelope')
                    ->toggleable(),

                TextColumn::make('gudang.nama_gudang')
                    ->label('Gudang')
                    ->placeholder('—')
                    ->badge()
                    ->color('info'),

                TextColumn::make('diskon_persen')
                    ->label('Diskon')
                    ->suffix('%')
                    ->alignRight()
                    ->toggleable(),

                TextColumn::make('creator.name')
                    ->label('Dibuat oleh')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('gudang_id')
                    ->label('Gudang')
                    ->relationship('gudang', 'nama_gudang')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('nama')
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
            ->emptyStateHeading('Belum ada kontak')
            ->emptyStateDescription('Tambahkan kontak (customer atau supplier) untuk mulai transaksi.')
            ->emptyStateIcon('heroicon-o-identification');
    }
}
