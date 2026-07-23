<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\User;

/**
 * Centralized accounting authorization matrix.
 *
 * Matrix:
 *  - super_admin: manage config, post, reverse, initiate cash op anywhere, view all reports.
 *  - admin:       view config; initiate cash op ONLY from assigned active/current warehouse;
 *                 cannot manage mappings, cannot post, cannot reverse; view warehouse-scoped reports.
 *  - spectator:   read-only warehouse-scoped views/reports; cannot mutate anything.
 *  - user/sales:  cannot access accounting config or mutate; no report access beyond existing pages.
 */
final class AccountingAuthorization
{
    /**
     * Can manage accounting config (COA + mappings).
     * Only super_admin.
     */
    public function canManageConfig(User $user): bool
    {
        return $user->role === 'super_admin';
    }

    /**
     * Can view accounting config (read-only).
     * super_admin, admin, spectator; NOT sales/user.
     */
    public function canViewConfig(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'admin', 'spectator'], true);
    }

    /**
     * Can initiate cash operation (transfer, cash-in, cash-out).
     * super_admin anywhere; admin only from assigned active/current warehouse.
     * spectator/sales denied.
     */
    public function canInitiateCashOperation(User $user, ?int $gudangId = null): bool
    {
        if ($user->role === 'super_admin') {
            return true;
        }

        if ($user->role === 'admin') {
            // If no gudang specified, use current_gudang_id fallback
            if ($gudangId === null) {
                $currentGudang = $user->getCurrentGudang();

                return $currentGudang !== null;
            }

            // Check if user has access to this gudang
            return $user->canAccessGudang($gudangId);
        }

        return false;
    }

    /**
     * Can post journal entries.
     * Only super_admin.
     */
    public function canPostJournal(User $user): bool
    {
        return $user->role === 'super_admin';
    }

    /**
     * Can reverse journal entries.
     * Only super_admin.
     */
    public function canReverseJournal(User $user): bool
    {
        return $user->role === 'super_admin';
    }

    /**
     * Can view accounting report.
     * super_admin anywhere; admin/spectator warehouse-scoped; sales denied.
     */
    public function canViewReport(User $user, ?int $gudangId = null): bool
    {
        if ($user->role === 'super_admin') {
            return true;
        }

        if (in_array($user->role, ['admin', 'spectator'], true)) {
            // If no gudang specified, use current_gudang_id fallback
            if ($gudangId === null) {
                $currentGudang = $user->getCurrentGudang();

                return $currentGudang !== null;
            }

            // Check if user has access to this gudang
            return $user->canAccessGudang($gudangId);
        }

        return false;
    }
}
