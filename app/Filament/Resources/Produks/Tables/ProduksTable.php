<?php

namespace App\Filament\Resources\Produks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProduksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->summaries(false, false)
            ->columns([
                TextColumn::make('item_code')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Item code disalin')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('nama_produk')
                    ->label('Nama Produk')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->wrap(),

                TextColumn::make('satuan')
                    ->label('Satuan')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Pcs' => 'info',
                        'Lusin' => 'warning',
                        'Karton' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('harga')
                    ->label('Harga Retail')
                    ->money('IDR')
                    ->sortable()
                    ->alignRight(),

                TextColumn::make('harga_grosir')
                    ->label('Harga Grosir')
                    ->money('IDR')
                    ->sortable()
                    ->alignRight()
                    ->toggleable(),

                TextColumn::make('stok_di_gudang_count')
                    ->label('Tersedia di')
                    ->counts('stokDiGudang')
                    ->suffix(' gudang')
                    ->badge()
                    ->color('success'),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('satuan')
                    ->options([
                        'Pcs' => 'Pcs',
                        'Lusin' => 'Lusin',
                        'Karton' => 'Karton',
                    ]),
            ])
            ->defaultSort('nama_produk')
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
            ->emptyStateHeading('Belum ada produk')
            ->emptyStateDescription('Tambahkan produk pertama untuk mulai mengelola katalog.')
            ->emptyStateIcon('heroicon-o-cube');
    }
}
