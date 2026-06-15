<?php

namespace App\Filament\Resources\Pembayarans;

use App\Filament\Concerns\ScopeByRole;
use App\Filament\Resources\Pembayarans\Pages\CreatePembayaran;
use App\Filament\Resources\Pembayarans\Pages\EditPembayaran;
use App\Filament\Resources\Pembayarans\Pages\ListPembayarans;
use App\Filament\Resources\Pembayarans\Pages\ViewPembayaran;
use App\Filament\Resources\Pembayarans\Schemas\PembayaranForm;
use App\Filament\Resources\Pembayarans\Tables\PembayaransTable;
use App\Models\Pembayaran;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PembayaranResource extends Resource
{
    use ScopeByRole;

    protected static ?string $model = Pembayaran::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Piutang';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Pembayaran Piutang';

    protected static ?string $pluralModelLabel = 'Pembayaran Piutang';

    public static function getNavigationBadge(): ?string
    {
        $count = static::applyRoleScope(Pembayaran::query())->where('status', 'Pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return PembayaranForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PembayaransTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        // Pembayaran Resource ini khusus untuk PIUTANG
        return static::applyRoleScope(parent::getEloquentQuery()->where('type', 'piutang'));
    }

    public static function canCreate(): bool
    {
        return !auth()->user()?->isSpectator();
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        // SuperAdmin bisa edit semua
        if ($user->isSuperAdmin()) return true;

        // Admin hanya bisa edit jika pembayaran ada di gudangnya
        if ($user->role === 'admin') {
            return $user->canAccessGudang($record->gudang_id);
        }

        return false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPembayarans::route('/'),
            'create' => CreatePembayaran::route('/create'),
            'view' => ViewPembayaran::route('/{record}'),
            'edit' => EditPembayaran::route('/{record}/edit'),
        ];
    }
}
