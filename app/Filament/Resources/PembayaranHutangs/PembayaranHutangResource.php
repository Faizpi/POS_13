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
        return static::applyRoleScope(parent::getEloquentQuery()->where('type', 'hutang'))
            ->with(['pembelian:id,nomor', 'pembelian.kontak:id,nama', 'user:id,name', 'gudang:id,nama_gudang']);
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

        // Excel: Sales ✅ VIEW only (no edit), Admin ✅ APRV+EDIT+DEL, Spectator ❌, SuperAdmin ✅ ALL
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Sales dan Spectator tidak bisa edit
        if ($user->isSpectator() || $user->role === 'user') {
            return false;
        }

        // Admin bisa edit
        if ($user->role === 'admin') {
            return true;
        }

        return false;
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        // Excel: Sales ❌, Admin ✅ DEL, Spectator ❌, SuperAdmin ✅
        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($user->role === 'admin') {
            return true;
        }

        return false;
    }
}
