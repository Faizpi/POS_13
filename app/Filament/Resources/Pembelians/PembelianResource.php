<?php

namespace App\Filament\Resources\Pembelians;

use App\Filament\Resources\Pembelians\RelationManagers\PembayaransRelationManager;
use App\Filament\Concerns\ScopeByRole;
use App\Filament\Resources\Pembelians\Pages\CreatePembelian;
use App\Filament\Resources\Pembelians\Pages\EditPembelian;
use App\Filament\Resources\Pembelians\Pages\ListPembelians;
use App\Filament\Resources\Pembelians\Pages\ViewPembelian;
use App\Filament\Resources\Pembelians\Schemas\PembelianForm;
use App\Filament\Resources\Pembelians\Tables\PembeliansTable;
use App\Models\Pembelian;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PembelianResource extends Resource
{
    use ScopeByRole;

    protected static ?string $model = Pembelian::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string|UnitEnum|null $navigationGroup = 'Hutang';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Buat Pembelian';

    protected static ?string $pluralModelLabel = 'Buat Pembelian';

    protected static ?string $recordTitleAttribute = 'nomor';

    public static function getNavigationBadge(): ?string
    {
        $count = static::applyRoleScope(Pembelian::query())->where('status', 'Pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return PembelianForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PembeliansTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return static::applyRoleScope(parent::getEloquentQuery());
    }

    public static function canCreate(): bool
    {
        return !auth()->user()?->isSpectator();
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function getRelations(): array
    {
        return [
            PembayaransRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPembelians::route('/'),
            'create' => CreatePembelian::route('/create'),
            'view' => ViewPembelian::route('/{record}'),
            'edit' => EditPembelian::route('/{record}/edit'),
        ];
    }
}
