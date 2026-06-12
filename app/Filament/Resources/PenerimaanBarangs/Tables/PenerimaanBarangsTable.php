<?php

namespace App\Filament\Resources\PenerimaanBarangs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PenerimaanBarangsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->summaries(false, false)
            ->columns([
                TextColumn::make('tgl_penerimaan')->label('Tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('nomor')->label('Nomor')->searchable()->weight('bold'),
                TextColumn::make('pembelian.nomor')->label('Pembelian')->placeholder('—'),
                TextColumn::make('gudang.nama_gudang')->label('Gudang')->badge()->color('info'),
                TextColumn::make('user.name')->label('Pembuat'),
                TextColumn::make('items_count')->label('Total Items')->counts('items')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Pending' => 'warning',
                        'Approved' => 'primary',
                        'Rejected' => 'danger',
                        'Canceled' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'Pending' => 'Pending',
                    'Approved' => 'Approved',
                    'Rejected' => 'Rejected',
                    'Canceled' => 'Canceled',
                ]),
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
            ->emptyStateHeading('Belum ada penerimaan barang')
            ->emptyStateIcon('heroicon-o-truck');
    }
}
