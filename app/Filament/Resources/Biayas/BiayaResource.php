<?php

namespace App\Filament\Resources\Biayas;

use App\Filament\Concerns\ScopeByRole;
use App\Filament\Resources\Biayas\Pages\CreateBiaya;
use App\Filament\Resources\Biayas\Pages\EditBiaya;
use App\Filament\Resources\Biayas\Pages\ListBiayas;
use App\Filament\Resources\Biayas\Pages\ViewBiaya;
use App\Filament\Resources\Biayas\Schemas\BiayaForm;
use App\Filament\Resources\Biayas\Tables\BiayasTable;
use App\Models\Biaya;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class BiayaResource extends Resource
{
    use ScopeByRole;

    protected static ?string $model = Biaya::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wallet';

    protected static string|UnitEnum|null $navigationGroup = 'Biaya';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Buat Biaya';

    protected static ?string $pluralModelLabel = 'Buat Biaya';

    protected static ?string $recordTitleAttribute = 'nomor';

    public static function getNavigationBadge(): ?string
    {
        $count = static::applyRoleScope(Biaya::query())->where('status', 'Pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return BiayaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BiayasTable::configure($table);
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

    public static function getPages(): array
    {
        return [
            'index' => ListBiayas::route('/'),
            'create' => CreateBiaya::route('/create'),
            'edit' => EditBiaya::route('/{record}/edit'),
            'view' => ViewBiaya::route('/{record}/view'),
        ];
    }
}
