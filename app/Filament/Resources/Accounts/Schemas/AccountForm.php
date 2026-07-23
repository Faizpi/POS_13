<?php

declare(strict_types=1);

namespace App\Filament\Resources\Accounts\Schemas;

use App\Accounting\AccountCategory;
use App\Accounting\DomainException;
use App\Accounting\NormalBalance;
use App\Accounting\StatementClassification;
use App\Models\Account;
use App\Services\Accounting\AccountCodeGenerator;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

final class AccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Struktur Akun')
                ->description('Pilih kategori, subkategori, lalu akun induk sebelum mengisi detail akun.')
                ->schema([
                    Select::make('category')
                        ->label('Kategori')
                        ->options(self::categoryOptions())
                        ->required()
                        ->live()
                        ->disabled(fn (?Account $record): bool => self::isLocked($record))
                        ->afterStateUpdated(function (?string $state, Set $set): void {
                            $category = $state === null ? null : AccountCategory::tryFrom($state);
                            $set('subcategory', null);
                            $set('parent_id', null);
                            $set('code', null);
                            $set('normal_balance', $category?->normalBalance()->value);
                            $set('statement_classification', $category?->statementClassification()->value);
                        }),

                    Select::make('subcategory')
                        ->label('Subkategori')
                        ->options(fn (Get $get): array => self::subcategoryOptions($get('category')))
                        ->searchable()
                        ->live()
                        ->disabled(fn (?Account $record): bool => self::isLocked($record)),

                    Select::make('parent_id')
                        ->label('Akun Induk')
                        ->relationship(
                            name: 'parent',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query): Builder => $query
                                ->where('is_postable', false)
                                ->orderBy('code'),
                        )
                        ->getOptionLabelFromRecordUsing(fn (Account $account): string => "{$account->code} — {$account->name}")
                        ->required(fn (?Account $record): bool => $record === null)
                        ->searchable()
                        ->preload()
                        ->live()
                        ->disabled(fn (?Account $record): bool => self::isLocked($record))
                        ->rules([self::parentRule()])
                        ->afterStateUpdated(function (Get $get, Set $set): void {
                            $category = AccountCategory::tryFrom((string) $get('category'));
                            $parent = Account::query()->find($get('parent_id'));

                            if ($category === null || $parent === null) {
                                $set('code', null);

                                return;
                            }

                            $set('code', self::suggestedCode($category, $parent));
                        }),

                    TextInput::make('code')
                        ->label('Kode Akun')
                        ->required()
                        ->maxLength(20)
                        ->helperText('Kode disarankan dari induk. Super admin dapat menggantinya bila tetap valid.')
                        ->disabled(fn (?Account $record): bool => $record !== null),

                    TextInput::make('name')
                        ->label('Nama Akun')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull()
                        ->disabled(fn (?Account $record): bool => self::isLocked($record)),
                ])
                ->columns(2),

            Section::make('Klasifikasi dan Opsi')
                ->schema([
                    Select::make('normal_balance')
                        ->label('Saldo Normal')
                        ->options([
                            NormalBalance::Debit->value => 'Debit',
                            NormalBalance::Kredit->value => 'Kredit',
                        ])
                        ->required()
                        ->disabled(fn (?Account $record): bool => self::isLocked($record)),

                    Select::make('statement_classification')
                        ->label('Klasifikasi Laporan')
                        ->options([
                            StatementClassification::Neraca->value => 'Neraca',
                            StatementClassification::LabaRugi->value => 'Laba Rugi',
                        ])
                        ->required()
                        ->disabled(fn (?Account $record): bool => self::isLocked($record)),

                    TextInput::make('cash_flow_category')
                        ->label('Kategori Arus Kas')
                        ->maxLength(100)
                        ->disabled(fn (?Account $record): bool => self::isLocked($record)),

                    TextInput::make('cash_flow_line')
                        ->label('Baris Arus Kas')
                        ->maxLength(255)
                        ->disabled(fn (?Account $record): bool => self::isLocked($record)),

                    Toggle::make('is_postable')
                        ->label('Dapat Diposting')
                        ->default(true)
                        ->required()
                        ->live()
                        ->disabled(fn (?Account $record): bool => self::isLocked($record)),

                    Toggle::make('is_control_account')
                        ->label('Akun Kontrol')
                        ->default(false)
                        ->required()
                        ->rules([self::controlAccountRule()])
                        ->disabled(fn (?Account $record): bool => self::isLocked($record)),

                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true)
                        ->required()
                        ->disabled(fn (?Account $record): bool => $record?->is_system ?? false),

                    Toggle::make('is_system')
                        ->label('Akun Sistem')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn (?Account $record): bool => $record !== null),

                    TextInput::make('display_order')
                        ->label('Urutan Tampil')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->required()
                        ->disabled(fn (?Account $record): bool => self::isLocked($record)),
                ])
                ->columns(2),
        ]);
    }

    /** @return array<string, string> */
    private static function categoryOptions(): array
    {
        return [
            AccountCategory::Aset->value => 'Aset',
            AccountCategory::Kewajiban->value => 'Kewajiban',
            AccountCategory::Ekuitas->value => 'Ekuitas',
            AccountCategory::Pendapatan->value => 'Pendapatan',
            AccountCategory::Hpp->value => 'HPP',
            AccountCategory::Beban->value => 'Beban',
            AccountCategory::PendapatanLainnya->value => 'Pendapatan Lainnya',
            AccountCategory::BebanLainnya->value => 'Beban Lainnya',
        ];
    }

    /** @return array<string, string> */
    public static function subcategoryOptions(?string $category): array
    {
        return match (AccountCategory::tryFrom((string) $category)) {
            AccountCategory::Aset => [
                'kas' => 'Kas', 'bank' => 'Bank', 'kas_in_transit' => 'Kas dalam Perjalanan',
                'receivable' => 'Piutang', 'allowance' => 'Cadangan', 'inventory' => 'Persediaan',
                'tax' => 'Pajak', 'prepayment' => 'Uang Muka', 'prepaid_expense' => 'Beban Dibayar di Muka',
            ],
            AccountCategory::Kewajiban => [
                'payable' => 'Utang', 'tax' => 'Pajak', 'accrued_expense' => 'Beban Akrual',
                'customer_advance' => 'Uang Muka Pelanggan', 'owner_loan' => 'Utang Pemilik',
            ],
            default => [],
        };
    }

    public static function suggestedCode(AccountCategory $category, Account $parent): ?string
    {
        try {
            return app(AccountCodeGenerator::class)->suggest($category, $parent);
        } catch (DomainException) {
            return null;
        }
    }

    /** @return list<int> */
    private static function descendantIds(Account $account): array
    {
        $descendantIds = [];
        $pendingIds = [$account->id];

        while ($pendingIds !== []) {
            $children = Account::query()->whereIn('parent_id', $pendingIds)->pluck('id')->all();
            $descendantIds = [...$descendantIds, ...$children];
            $pendingIds = $children;
        }

        return $descendantIds;
    }

    private static function parentRule(): \Closure
    {
        return function (Get $get, ?Account $record): \Closure {
            return function (string $attribute, mixed $value, \Closure $fail) use ($get, $record): void {
                $parent = Account::query()->find($value);
                $category = AccountCategory::tryFrom((string) $get('category'));

                if ($parent === null || $category === null || $parent->category !== $category) {
                    $fail('Akun induk harus memiliki kategori yang sama.');

                    return;
                }

                if ($record !== null && in_array($parent->id, self::descendantIds($record), true)) {
                    $fail('Akun induk tidak boleh merupakan turunan akun ini.');
                }
            };
        };
    }

    private static function controlAccountRule(): \Closure
    {
        return function (Get $get): \Closure {
            return function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                if (! $value) {
                    return;
                }

                $category = AccountCategory::tryFrom((string) $get('category'));

                if (! Account::isControlAccountCompatible($category, $get('subcategory'), (bool) $get('is_postable'))) {
                    $fail('Akun kontrol harus postable dan memakai subkategori piutang, persediaan, pajak, atau utang.');
                }
            };
        };
    }

    private static function isLocked(?Account $record): bool
    {
        return $record?->is_system || $record?->is_used || $record?->is_control_account;
    }
}
