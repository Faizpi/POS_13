<?php

namespace App\Filament\Resources\PembayaranHutangs;

use App\Filament\Concerns\ScopeByRole;
use App\Filament\Resources\PembayaranHutangs\Pages\CreatePembayaranHutang;
use App\Filament\Resources\PembayaranHutangs\Pages\EditPembayaranHutang;
use App\Filament\Resources\PembayaranHutangs\Pages\ListPembayaranHutangs;
use App\Filament\Resources\PembayaranHutangs\Schemas\PembayaranHutangForm;
use App\Filament\Resources\PembayaranHutangs\Tables\PembayaranHutangsTable;
use App\Models\Pembayaran;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PembayaranHutangResource extends Resource
{
    use ScopeByRole;

    protected static ?string $model = Pembayaran::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|UnitEnum|null $navigationGroup = 'Hutang';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Pembayaran Hutang';

    protected static ?string $pluralModelLabel = 'Pembayaran Hutang';

    protected static ?string $recordTitleAttribute = 'nomor';

    public static function getEloquentQuery(): Builder
    {
        // Filter khusus type=hutang
        return static::applyRoleScope(parent::getEloquentQuery()->where('type', 'hutang'));
    }

    public static function form(Schema $schema): Schema
    {
        return PembayaranHutangForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PembayaranHutangsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPembayaranHutangs::route('/'),
            'create' => CreatePembayaranHutang::route('/create'),
            'edit' => EditPembayaranHutang::route('/{record}/edit'),
        ];
    }
}
