<?php

namespace App\Filament\Resources\Kunjungans;

use App\Filament\Concerns\ScopeByRole;
use App\Filament\Resources\Kunjungans\Pages\CreateKunjungan;
use App\Filament\Resources\Kunjungans\Pages\EditKunjungan;
use App\Filament\Resources\Kunjungans\Pages\ListKunjungans;
use App\Filament\Resources\Kunjungans\Pages\ViewKunjungan;
use App\Filament\Resources\Kunjungans\Schemas\KunjunganForm;
use App\Filament\Resources\Kunjungans\Tables\KunjungansTable;
use App\Models\Kunjungan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class KunjunganResource extends Resource
{
    use ScopeByRole;

    protected static ?string $model = Kunjungan::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|UnitEnum|null $navigationGroup = 'Kunjungan';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Buat Kunjungan';

    protected static ?string $pluralModelLabel = 'Buat Kunjungan';

    protected static ?string $recordTitleAttribute = 'nomor';

    public static function getNavigationBadge(): ?string
    {
        $count = static::applyRoleScope(Kunjungan::query())->where('status', 'Pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return KunjunganForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KunjungansTable::configure($table);
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
            'index' => ListKunjungans::route('/'),
            'create' => CreateKunjungan::route('/create'),
            'view' => ViewKunjungan::route('/{record}'),
            'edit' => EditKunjungan::route('/{record}/edit'),
        ];
    }
}
