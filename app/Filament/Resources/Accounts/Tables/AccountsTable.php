<?php

declare(strict_types=1);

namespace App\Filament\Resources\Accounts\Tables;

use App\Accounting\AccountCategory;
use App\Models\Account;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class AccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable()->copyable()->weight('bold'),
                TextColumn::make('name')->label('Nama')->searchable()->sortable()->wrap(),
                TextColumn::make('category')->label('Kategori')->badge(),
                TextColumn::make('subcategory')->label('Subkategori')->placeholder('—')->toggleable(),
                TextColumn::make('parent.code')->label('Induk')->formatStateUsing(fn (?string $state, $record): string => $state === null ? '—' : "{$state} — {$record->parent->name}"),
                TextColumn::make('normal_balance')->label('Saldo Normal')->badge(),
                IconColumn::make('is_postable')->label('Postable')->boolean(),
                IconColumn::make('is_control_account')->label('Kontrol')->boolean(),
                IconColumn::make('is_system')->label('Sistem')->boolean(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')->label('Kategori')->options([
                    AccountCategory::Aset->value => 'Aset',
                    AccountCategory::Kewajiban->value => 'Kewajiban',
                    AccountCategory::Ekuitas->value => 'Ekuitas',
                    AccountCategory::Pendapatan->value => 'Pendapatan',
                    AccountCategory::Hpp->value => 'HPP',
                    AccountCategory::Beban->value => 'Beban',
                    AccountCategory::PendapatanLainnya->value => 'Pendapatan Lainnya',
                    AccountCategory::BebanLainnya->value => 'Beban Lainnya',
                ]),
                SelectFilter::make('subcategory')->label('Subkategori')->options(fn (): array => Account::query()->whereNotNull('subcategory')->distinct()->orderBy('subcategory')->pluck('subcategory', 'subcategory')->all()),
                SelectFilter::make('is_active')->label('Status')->options(['1' => 'Aktif', '0' => 'Nonaktif']),
                SelectFilter::make('is_postable')->label('Postable')->options(['1' => 'Ya', '0' => 'Tidak']),
                SelectFilter::make('is_control_account')->label('Akun Kontrol')->options(['1' => 'Ya', '0' => 'Tidak']),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('code')
            ->emptyStateHeading('Belum ada akun');
    }
}
