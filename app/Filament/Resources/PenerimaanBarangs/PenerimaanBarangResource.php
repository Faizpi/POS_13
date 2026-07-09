<?php

namespace App\Filament\Resources\PenerimaanBarangs;

use App\Filament\Concerns\ScopeByRole;
use App\Filament\Resources\PenerimaanBarangs\Pages\CreatePenerimaanBarang;
use App\Filament\Resources\PenerimaanBarangs\Pages\EditPenerimaanBarang;
use App\Filament\Resources\PenerimaanBarangs\Pages\ListPenerimaanBarangs;
use App\Filament\Resources\PenerimaanBarangs\Pages\ViewPenerimaanBarang;
use App\Filament\Resources\PenerimaanBarangs\Schemas\PenerimaanBarangForm;
use App\Filament\Resources\PenerimaanBarangs\Tables\PenerimaanBarangsTable;
use App\Models\PenerimaanBarang;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PenerimaanBarangResource extends Resource
{
    use ScopeByRole;

    protected static ?string $model = PenerimaanBarang::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|UnitEnum|null $navigationGroup = 'Hutang';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Penerimaan Barang';

    protected static ?string $pluralModelLabel = 'Penerimaan Barang';

    public static function getNavigationBadge(): ?string
    {
        $count = static::applyRoleScope(PenerimaanBarang::query())->where('status', 'Pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return PenerimaanBarangForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PenerimaanBarangsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return static::applyRoleScope(parent::getEloquentQuery());
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        // Excel: Sales ✅ ADD, Admin ✅ ADD, Spectator ❌, SuperAdmin ✅
        return ! $user->isSpectator();
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        // Excel: Sales ❌ (VIEW only), Admin ✅ EDIT+APRV, Spectator ❌, SuperAdmin ✅
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Sales dan Spectator tidak bisa edit
        if ($user->isSpectator() || $user->role === 'user') {
            return false;
        }

        return false;
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        // Excel: Sales ❌, Admin ❌, Spectator ❌, SuperAdmin ✅
        return $user->isSuperAdmin() ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPenerimaanBarangs::route('/'),
            'create' => CreatePenerimaanBarang::route('/create'),
            'view' => ViewPenerimaanBarang::route('/{record}'),
            'edit' => EditPenerimaanBarang::route('/{record}/edit'),
        ];
    }
}
