<?php

declare(strict_types=1);

namespace App\Filament\Resources\CashBankAccounts;

use App\Filament\Resources\CashBankAccounts\Pages\CreateCashBankAccount;
use App\Filament\Resources\CashBankAccounts\Pages\EditCashBankAccount;
use App\Filament\Resources\CashBankAccounts\Pages\ListCashBankAccounts;
use App\Filament\Resources\CashBankAccounts\Schemas\CashBankAccountForm;
use App\Filament\Resources\CashBankAccounts\Tables\CashBankAccountsTable;
use App\Models\CashBankAccount;
use App\Services\Accounting\AccountingAuthorization;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CashBankAccountResource extends Resource
{
    protected static ?string $model = CashBankAccount::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Akuntansi';

    protected static ?string $navigationLabel = 'Kas & Bank';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'kas-bank';

    protected static ?string $modelLabel = 'Kas / Bank';

    protected static ?string $pluralModelLabel = 'Kas & Bank';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CashBankAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CashBankAccountsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['account:id,code,name', 'gudang:id,nama_gudang']);
        $user = auth()->user();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        if (! in_array($user->role, ['admin', 'spectator'], true)) {
            return $query->whereRaw('1 = 0');
        }

        $currentGudang = $user->getCurrentGudang();

        return $query->where(function (Builder $query) use ($user, $currentGudang): void {
            $query->whereNull('gudang_id');

            if ($currentGudang !== null && $user->canAccessGudang($currentGudang->id)) {
                $query->orWhere('gudang_id', $currentGudang->id);
            }
        });
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null && app(AccountingAuthorization::class)->canViewConfig($user);
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user !== null && app(AccountingAuthorization::class)->canManageConfig($user);
    }

    public static function canEdit($record): bool
    {
        return self::canCreate();
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCashBankAccounts::route('/'),
            'create' => CreateCashBankAccount::route('/create'),
            'edit' => EditCashBankAccount::route('/{record}/edit'),
        ];
    }
}
