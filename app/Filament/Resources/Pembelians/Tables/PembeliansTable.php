<?php

namespace App\Filament\Resources\Pembelians\Tables;

use App\Filament\Concerns\TransactionDeleteGuard;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PembeliansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->summaries(false, false)
            ->columns([
                TextColumn::make('tgl_transaksi')
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

                TextColumn::make('user.name')
                    ->label('Pembuat')
                    ->searchable(),

                TextColumn::make('approver.name')
                    ->label('Approver')
                    ->placeholder('—'),

                TextColumn::make('gudang.nama_gudang')
                    ->label('Gudang')
                    ->badge()
                    ->color('info'),

                TextColumn::make('kontak.nama')
                    ->label('Supplier')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('urgensi')
                    ->label('Urgensi')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Tinggi' => 'danger',
                        'Sedang' => 'warning',
                        'Rendah' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('grand_total')
                    ->label('Total')
                    ->money('IDR')
                    ->sortable()
                    ->alignRight()
                    ->weight('bold'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Pending' => 'warning',
                        'Approved' => 'primary',
                        'Lunas' => 'success',
                        'Rejected' => 'danger',
                        'Canceled' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'Pending' => 'Pending',
                    'Approved' => 'Approved',
                    'Lunas' => 'Lunas',
                    'Rejected' => 'Rejected',
                    'Canceled' => 'Canceled',
                ]),
                SelectFilter::make('urgensi')->options([
                    'Rendah' => 'Rendah',
                    'Sedang' => 'Sedang',
                    'Tinggi' => 'Tinggi',
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn () => auth()->user()?->isSuperAdmin()),
                DeleteAction::make()
                    ->visible(fn ($record): bool => auth()->user()?->isSuperAdmin() && TransactionDeleteGuard::canDeletePembelian($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => false),
                ]),
            ])
            ->emptyStateHeading('Belum ada permintaan pembelian')
            ->emptyStateIcon('heroicon-o-shopping-bag');
    }
}
