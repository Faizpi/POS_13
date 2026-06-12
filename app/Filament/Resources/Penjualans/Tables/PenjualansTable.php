<?php

namespace App\Filament\Resources\Penjualans\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PenjualansTable
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
                    ->copyable()
                    ->copyMessage('Nomor invoice disalin'),

                TextColumn::make('user.name')
                    ->label('Pembuat')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('approver.name')
                    ->label('Approver')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('pelanggan')
                    ->label('Pelanggan')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('gudang.nama_gudang')
                    ->label('Gudang')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

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
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'Pending' => 'heroicon-o-clock',
                        'Approved' => 'heroicon-o-check-circle',
                        'Lunas' => 'heroicon-o-banknotes',
                        'Canceled' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    }),

                TextColumn::make('tgl_jatuh_tempo')
                    ->label('Jatuh Tempo')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->color(fn ($record) => $record->status === 'Approved' && $record->tgl_jatuh_tempo && $record->tgl_jatuh_tempo->isPast() ? 'danger' : null)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Pending' => 'Pending',
                        'Approved' => 'Approved',
                        'Lunas' => 'Lunas',
                        'Rejected' => 'Rejected',
                        'Canceled' => 'Canceled',
                    ]),

                SelectFilter::make('user_id')
                    ->label('Sales')
                    ->options(fn () => User::where('role', 'user')->pluck('name', 'id'))
                    ->visible(fn () => in_array(auth()->user()?->role, ['super_admin', 'admin', 'spectator']))
                    ->searchable()
                    ->preload(),

                Filter::make('jatuh_tempo_lewat')
                    ->label('Jatuh Tempo Lewat')
                    ->query(fn (Builder $query) => $query->where('status', 'Approved')->whereDate('tgl_jatuh_tempo', '<', now())),
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
            ->emptyStateHeading('Belum ada penjualan')
            ->emptyStateDescription('Buat penagihan baru untuk mulai mencatat penjualan.')
            ->emptyStateIcon('heroicon-o-document-currency-dollar');
    }
}
