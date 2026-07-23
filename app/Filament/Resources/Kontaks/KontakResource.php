<?php

namespace App\Filament\Resources\Kontaks;

use App\Filament\Concerns\ScopeByRole;
use App\Filament\Resources\Kontaks\Pages\CreateKontak;
use App\Filament\Resources\Kontaks\Pages\EditKontak;
use App\Filament\Resources\Kontaks\Pages\ListKontaks;
use App\Filament\Resources\Kontaks\Pages\ViewKontak;
use App\Filament\Resources\Kontaks\Schemas\KontakForm;
use App\Filament\Resources\Kontaks\Tables\KontaksTable;
use App\Models\Kontak;
use App\Models\Penjualan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class KontakResource extends Resource
{
    use ScopeByRole;

    protected static ?string $model = Kontak::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Kontak';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Kontak';

    protected static ?string $pluralModelLabel = 'Kontak';

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return KontakForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KontaksTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        if (! $user) {
            return $query->whereRaw('1=0');
        }

        if (in_array($user->role, ['super_admin', 'spectator'])) {
            return $query;
        }

        if ($user->role === 'admin') {
            $gudangIds = $user->gudangs->pluck('id')->toArray();
            if ($user->current_gudang_id) {
                $gudangIds[] = $user->current_gudang_id;
            }
            if ($user->gudang_id) {
                $gudangIds[] = $user->gudang_id;
            }
            $gudangIds = array_unique($gudangIds);

            return $query->where(fn ($q) => $q->whereIn('gudang_id', $gudangIds)->orWhereNull('gudang_id'));
        }

        // user/sales
        return $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhere(function ($sub) use ($user) {
                    $sub->whereNull('created_by')
                        ->whereIn('nama', Penjualan::where('user_id', $user->id)
                            ->whereNotNull('pelanggan')
                            ->pluck('pelanggan'));
                });
        });
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

        // Excel: Sales ❌, Admin ✅ EDIT, Spectator ❌, SuperAdmin ✅
        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($user->isSpectator()) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }

        // Sales: tidak bisa edit kontak (sesuai Excel) — edit no_telp adalah PEMBAHARUAN terpisah
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

    public static function getPages(): array
    {
        return [
            'index' => ListKontaks::route('/'),
            'create' => CreateKontak::route('/create'),
            'view' => ViewKontak::route('/{record}'),
            'edit' => EditKontak::route('/{record}/edit'),
        ];
    }
}
