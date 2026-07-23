<?php

declare(strict_types=1);

namespace App\Filament\Resources\CashBankAccounts\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CashBankAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable()->weight('bold'),
                TextColumn::make('type')->label('Jenis')->badge()->formatStateUsing(fn ($state): string => ucfirst($state->value)),
                TextColumn::make('account.code')->label('Kode COA')->sortable(),
                TextColumn::make('account.name')->label('Akun COA')->searchable(),
                TextColumn::make('gudang.nama_gudang')->label('Gudang')->placeholder('Global')->sortable(),
                TextColumn::make('bank_name')->label('Bank')->placeholder('—')->toggleable(),
                TextColumn::make('bank_account_number')->label('No. Rekening')->placeholder('—')->toggleable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Jenis')->options(['kas' => 'Kas', 'bank' => 'Bank']),
                SelectFilter::make('is_active')->label('Status')->options([1 => 'Aktif', 0 => 'Nonaktif']),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('name')
            ->emptyStateHeading('Belum ada master kas atau bank');
    }
}
