<?php

namespace App\Filament\Resources\Biayas\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BiayasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->summaries(false, false)
            ->columns([
                TextColumn::make('tgl_transaksi')->label('Tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('nomor')->label('Nomor')->searchable()->sortable()->weight('bold'),
                TextColumn::make('jenis_biaya')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'masuk' ? 'Masuk' : 'Keluar')
                    ->color(fn ($state) => $state === 'masuk' ? 'success' : 'danger')
                    ->icon(fn ($state) => $state === 'masuk' ? 'heroicon-o-arrow-down-tray' : 'heroicon-o-arrow-up-tray'),
                TextColumn::make('user.name')->label('Pembuat'),
                TextColumn::make('penerima')->label('Penerima')->placeholder('—'),
                TextColumn::make('grand_total')->label('Total')->money('IDR')->sortable()->alignRight()->weight('bold'),
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
                SelectFilter::make('jenis_biaya')->label('Jenis')->options([
                    'masuk' => 'Masuk',
                    'keluar' => 'Keluar',
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
            ->emptyStateHeading('Belum ada biaya')
            ->emptyStateIcon('heroicon-o-wallet');
    }
}
