<?php

namespace App\Filament\Customer\Resources\Kunjungans;

use App\Filament\Customer\Resources\Kunjungans\Pages\CreateKunjungan;
use App\Filament\Customer\Resources\Kunjungans\Pages\EditKunjungan;
use App\Filament\Customer\Resources\Kunjungans\Pages\ListKunjungans;
use App\Filament\Customer\Resources\Kunjungans\Pages\ViewKunjungan;
use App\Filament\Customer\Resources\Kunjungans\Schemas\KunjunganForm;
use App\Filament\Customer\Resources\Kunjungans\Schemas\KunjunganInfolist;
use App\Filament\Customer\Resources\Kunjungans\Tables\KunjungansTable;
use App\Models\Kunjungan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class KunjunganResource extends Resource
{
    protected static ?string $model = Kunjungan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'nomor';

    public static function form(Schema $schema): Schema
    {
        return KunjunganForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return KunjunganInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KunjungansTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
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
