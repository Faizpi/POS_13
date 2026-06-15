<?php

namespace App\Filament\Resources\StockOpnames;

use App\Filament\Concerns\ScopeByRole;
use App\Filament\Resources\StockOpnames\Pages\CreateStockOpname;
use App\Filament\Resources\StockOpnames\Pages\EditStockOpname;
use App\Filament\Resources\StockOpnames\Pages\ListStockOpnames;
use App\Filament\Resources\StockOpnames\Pages\ViewStockOpname;
use App\Filament\Resources\StockOpnames\Schemas\StockOpnameForm;
use App\Filament\Resources\StockOpnames\Tables\StockOpnamesTable;
use App\Models\StockOpname;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class StockOpnameResource extends Resource
{
    use ScopeByRole;

    protected static ?string $model = StockOpname::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Gudang';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Stock Opname';

    protected static ?string $pluralModelLabel = 'Stock Opname';

    protected static ?string $recordTitleAttribute = 'nomor';

    public static function getNavigationBadge(): ?string
    {
        $count = static::applyRoleScope(StockOpname::query())->where('status', 'Submitted')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return StockOpnameForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockOpnamesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return static::applyRoleScope(parent::getEloquentQuery());
    }

    public static function canCreate(): bool
    {
        $role = auth()->user()?->role;
        return in_array($role, ['super_admin', 'admin']);
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListStockOpnames::route('/'),
            'create' => CreateStockOpname::route('/create'),
            'view'   => ViewStockOpname::route('/{record}'),
            'edit'   => EditStockOpname::route('/{record}/edit'),
        ];
    }
}
