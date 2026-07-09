<?php

namespace App\Filament\Resources\PembayaranHutangs\Tables;

use App\Filament\Concerns\TransactionDeleteGuard;
use App\Services\PaymentSettlementService;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PembayaranHutangsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->summaries(false, false)
            ->columns([
                TextColumn::make('tgl_pembayaran')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('nomor')
                    ->label('Nomor')
                    ->searchable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('pembelian.nomor')
                    ->label('Invoice Pembelian')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('pembelian.kontak.nama')
                    ->label('Supplier')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('gudang.nama_gudang')
                    ->label('Gudang')
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('metode_pembayaran')
                    ->label('Metode')
                    ->badge()
                    ->color('info'),

                TextColumn::make('jumlah_bayar')
                    ->label('Jumlah')
                    ->money('IDR')
                    ->sortable()
                    ->alignRight()
                    ->weight('bold'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Pending' => 'warning',
                        'Approved' => 'primary',
                        'Rejected' => 'danger',
                        'Canceled' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'Pending' => 'Pending',
                    'Approved' => 'Approved',
                    'Rejected' => 'Rejected',
                    'Canceled' => 'Canceled',
                ]),
                SelectFilter::make('metode_pembayaran')->options([
                    'Cash' => 'Cash',
                    'Transfer' => 'Transfer Bank',
                    'Cheque' => 'Cheque',
                    'QRIS' => 'QRIS',
                    'Debit' => 'Debit',
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => $record->status === 'Pending' && in_array(auth()->user()?->role, ['admin', 'super_admin'], true))
                    ->action(function ($record): void {
                        $user = auth()->user();
                        if ($user?->role === 'admin') {
                            $currentGudang = $user->getCurrentGudang();
                            if (! $currentGudang || (int) $record->gudang_id !== (int) $currentGudang->id) {
                                Notification::make()->title('Hanya bisa approve pembayaran hutang di gudang aktif.')->danger()->send();

                                return;
                            }
                        }

                        try {
                            app(PaymentSettlementService::class)->approveHutangPayment($record, $user->id);
                            Notification::make()->title('Pembayaran hutang berhasil di-approve.')->success()->send();
                        } catch (DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        } catch (\Exception) {
                            Notification::make()->title('Gagal approve pembayaran hutang.')->danger()->send();
                        }
                    }),
                Action::make('cancel')
                    ->label('Batalkan')
                    ->icon('heroicon-o-no-symbol')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(function ($record): bool {
                        $user = auth()->user();
                        if (! $user || $record->status === 'Canceled') {
                            return false;
                        }
                        if ($user->isSuperAdmin()) {
                            return true;
                        }
                        if ($record->status !== 'Pending') {
                            return false;
                        }

                        return in_array($user->role, ['admin', 'super_admin'], true);
                    })
                    ->action(function ($record): void {
                        $user = auth()->user();
                        if ($user?->role === 'admin') {
                            $currentGudang = $user->getCurrentGudang();
                            if (! $currentGudang || (int) $record->gudang_id !== (int) $currentGudang->id) {
                                Notification::make()->title('Hanya bisa cancel pembayaran hutang di gudang aktif.')->danger()->send();

                                return;
                            }
                        }

                        try {
                            app(PaymentSettlementService::class)->cancelHutangPayment($record);
                            Notification::make()->title('Pembayaran hutang dibatalkan.')->success()->send();
                        } catch (DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        } catch (\Exception) {
                            Notification::make()->title('Gagal membatalkan pembayaran hutang.')->danger()->send();
                        }
                    }),
                Action::make('uncancel')
                    ->label('Batalkan Pembatalan')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => $record->status === 'Canceled' && auth()->user()?->isSuperAdmin())
                    ->action(function ($record): void {
                        try {
                            app(PaymentSettlementService::class)->uncancelHutangPayment($record);
                            Notification::make()->title('Status pembayaran hutang kembali ke Pending.')->success()->send();
                        } catch (DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        } catch (\Exception) {
                            Notification::make()->title('Gagal membatalkan pembatalan pembayaran hutang.')->danger()->send();
                        }
                    }),
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
            ->emptyStateHeading('Belum ada pembayaran hutang')
            ->emptyStateDescription('Catat pembayaran untuk pembelian yang belum lunas.')
            ->emptyStateIcon('heroicon-o-credit-card');
    }
}
