<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait untuk apply role-based query scoping di Filament Resources transaksi.
 *
 * - super_admin: lihat semua
 * - admin/spectator: filtered by current_gudang_id
 * - user: hanya milik sendiri
 */
trait ScopeByRole
{
    public static function applyRoleScope(Builder $query, ?string $gudangColumn = 'gudang_id', ?string $userColumn = 'user_id'): Builder
    {
        $user = auth()->user();
        if (! $user) {
            return $query->whereRaw('1=0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        if (in_array($user->role, ['admin', 'spectator'])) {
            $currentGudangId = $user->current_gudang_id;

            // Jika tidak ada current_gudang_id, coba ambil fallback seperti di User model
            if (! $currentGudangId) {
                $fallbackGudang = $user?->getCurrentGudang();
                $currentGudangId = $fallbackGudang ? $fallbackGudang->id : null;
            }

            if (! $currentGudangId || ! $gudangColumn) {
                return $query->whereRaw('1=0');
            }

            return $query->where($gudangColumn, $currentGudangId);
        }

        // role 'user' / sales
        if ($user->role === 'user') {
            if ($userColumn) {
                return $query->where($userColumn, $user->id);
            }

            if ($gudangColumn && $user->gudang_id) {
                return $query->where($gudangColumn, $user->gudang_id);
            }
        }

        return $query->whereRaw('1=0');
    }
}
