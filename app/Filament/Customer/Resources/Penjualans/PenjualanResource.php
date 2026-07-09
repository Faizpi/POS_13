<?php

namespace App\Filament\Customer\Resources\Penjualans;

use App\Filament\Customer\Resources\Penjualans\Pages\ListPenjualans;
use App\Filament\Customer\Resources\Penjualans\Pages\ViewPenjualan;
use App\Filament\Customer\Resources\Penjualans\Schemas\PenjualanForm;
use App\Filament\Customer\Resources\Penjualans\Schemas\PenjualanInfolist;
use App\Filament\Customer\Resources\Penjualans\Tables\PenjualansTable;
use App\Models\Penjualan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PenjualanResource extends Resource
{
    protected static ?string $model = Penjualan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'nomor';

    public static function form(Schema $schema): Schema
    {
        return PenjualanForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PenjualanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PenjualansTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $customerNama = session('customer_nama') ?? auth()->user()?->nama;

        return parent::getEloquentQuery()->where('pelanggan', $customerNama);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPenjualans::route('/'),
            'view' => ViewPenjualan::route('/{record}'),
        ];
    }
}
