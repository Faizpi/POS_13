<?php

namespace App\Filament\Resources\Pembayarans\Tables;

use App\Filament\Concerns\TransactionDeleteGuard;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PembayaransTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->summaries(false, false)
            ->columns([
                TextColumn::make('tgl_pembayaran')->label('Tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('nomor')->label('Nomor')->searchable()->weight('bold'),
                TextColumn::make('penjualan.nomor')->label('Invoice')->placeholder('—'),
                TextColumn::make('penjualan.pelanggan')->label('Pelanggan'),
                TextColumn::make('metode_pembayaran')->label('Metode')->badge()->color('info'),
                TextColumn::make('jumlah_bayar')->label('Jumlah')->money('IDR')->alignRight()->weight('bold'),
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
                DeleteAction::make()
                    ->visible(fn ($record): bool => auth()->user()?->isSuperAdmin() && TransactionDeleteGuard::canDeletePembayaran($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => false),
                ]),
            ])
            ->emptyStateHeading('Belum ada pembayaran')
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
