<?php

declare(strict_types=1);

namespace App\Filament\Resources\CashBankAccounts\Schemas;

use App\Accounting\AccountCategory;
use App\Accounting\CashAccountType;
use App\Models\Account;
use App\Models\CashBankAccount;
use App\Models\Gudang;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class CashBankAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Master Kas & Bank')
                ->schema([
                    TextInput::make('name')
                        ->label('Nama')
                        ->required()
                        ->maxLength(255),

                    Select::make('type')
                        ->label('Jenis')
                        ->options([
                            CashAccountType::Kas->value => 'Kas',
                            CashAccountType::Bank->value => 'Bank',
                        ])
                        ->required()
                        ->live(),

                    Select::make('account_id')
                        ->label('Akun COA')
                        ->options(fn (Get $get): array => Account::query()
                            ->where('category', AccountCategory::Aset->value)
                            ->where('subcategory', $get('type'))
                            ->where('is_active', true)
                            ->where('is_postable', true)
                            ->orderBy('code')
                            ->get()
                            ->mapWithKeys(fn (Account $account): array => [$account->id => "{$account->code} — {$account->name}"])
                            ->all())
                        ->required()
                        ->searchable()
                        ->preload()
                        ->rules([
                            fn (?CashBankAccount $record) => Rule::unique('cash_bank_accounts', 'account_id')->ignore($record),
                        ]),

                    Select::make('gudang_id')
                        ->label('Gudang')
                        ->options(fn (): array => Gudang::query()
                            ->where('is_active', true)
                            ->orderBy('nama_gudang')
                            ->pluck('nama_gudang', 'id')
                            ->all())
                        ->searchable()
                        ->preload(),

                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true)
                        ->required(),
                ])
                ->columns(['default' => 2]),

            Section::make('Informasi Bank')
                ->schema([
                    TextInput::make('bank_name')
                        ->label('Nama Bank')
                        ->maxLength(255),
                    TextInput::make('bank_account_number')
                        ->label('Nomor Rekening')
                        ->maxLength(255),
                    TextInput::make('bank_account_holder')
                        ->label('Atas Nama')
                        ->maxLength(255),
                ])
                ->columns(['default' => 3])
                ->visible(fn (Get $get): bool => $get('type') === CashAccountType::Bank->value),
        ]);
    }
}
