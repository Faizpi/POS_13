<?php

namespace App\Filament\Resources\StockOpnames\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockOpnamesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->summaries(false, false)
            ->columns([
                TextColumn::make('tgl_opname')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->description(fn ($record) => $record->created_at?->format('H:i'))
                    ->sortable(),

                TextColumn::make('nomor')
                    ->label('Nomor')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('gudang.nama_gudang')
                    ->label('Gudang')
                    ->badge()
                    ->color('info'),

                TextColumn::make('user.name')
                    ->label('Pembuat')
                    ->searchable(),

                TextColumn::make('items_count')
                    ->label('Jumlah Item')
                    ->counts('items')
                    ->suffix(' item')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Draft' => 'gray',
                        'Submitted' => 'warning',
                        'Applied' => 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Submitted' => 'Submitted',
                        'Applied' => 'Applied',
                    ]),

                SelectFilter::make('gudang_id')
                    ->label('Gudang')
                    ->relationship('gudang', 'nama_gudang'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn () => auth()->user()?->isSuperAdmin()),
                DeleteAction::make()->visible(fn () => auth()->user()?->isSuperAdmin()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => auth()->user()?->isSuperAdmin()),
                ]),
            ])
            ->emptyStateHeading('Belum ada stock opname')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }
}
