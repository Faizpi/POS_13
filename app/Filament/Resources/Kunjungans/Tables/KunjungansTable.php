<?php

namespace App\Filament\Resources\Kunjungans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class KunjungansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->summaries(false, false)
            ->columns([
                TextColumn::make('tgl_kunjungan')->label('Tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('nomor')->label('Nomor')->searchable()->weight('bold'),
                TextColumn::make('tujuan')
                    ->label('Tujuan')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Pemeriksaan Stock' => 'info',
                        'Penagihan' => 'warning',
                        'Penawaran' => 'primary',
                        'Promo Gratis' => 'success',
                        'Promo Sample' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('user.name')->label('Pembuat'),
                TextColumn::make('kontak.nama')->label('Kontak'),
                TextColumn::make('gudang.nama_gudang')->label('Gudang')->badge()->color('info'),
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
                SelectFilter::make('tujuan')->options([
                    'Pemeriksaan Stock' => 'Pemeriksaan Stock',
                    'Penagihan' => 'Penagihan',
                    'Penawaran' => 'Penawaran',
                    'Promo Gratis' => 'Promo Gratis',
                    'Promo Sample' => 'Promo Sample',
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
            ->emptyStateHeading('Belum ada kunjungan')
            ->emptyStateIcon('heroicon-o-map-pin');
    }
}
