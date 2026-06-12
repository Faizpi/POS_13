<?php

namespace App\Filament\Concerns;

use App\Models\User;

/**
 * Trait untuk menentukan approver_id berdasarkan role user dan gudang_id.
 * Digunakan di semua halaman Create transaksi.
 */
trait ResolvesApprover
{
    /**
     * Tentukan approver_id berdasarkan role user yang sedang login dan gudang yang dipilih.
     *
     * @param  int|null  $gudangId
     * @return int|null
     */
    protected function resolveApproverId(?int $gudangId): ?int
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            // Super admin: cari admin gudang, kalau tidak ada jadikan diri sendiri
            if ($gudangId) {
                $adminGudang = User::where('role', 'admin')
                    ->where(function ($q) use ($gudangId) {
                        $q->where('gudang_id', $gudangId)
                            ->orWhere('current_gudang_id', $gudangId)
                            ->orWhereHas('gudangs', fn($sub) => $sub->where('gudangs.id', $gudangId));
                    })
                    ->first();
                if ($adminGudang) return $adminGudang->id;
            }
            return $user->id;
        }

        if ($user->role === 'admin') {
            // Admin: approver adalah super_admin
            $superAdmin = User::where('role', 'super_admin')->first();
            return $superAdmin?->id;
        }

        if ($user->role === 'user') {
            // Sales: cari admin yang mengelola gudang ini
            if ($gudangId) {
                $adminGudang = User::where('role', 'admin')
                    ->where(function ($q) use ($gudangId) {
                        $q->where('gudang_id', $gudangId)
                            ->orWhereHas('gudangs', fn($sub) => $sub->where('gudangs.id', $gudangId));
                    })
                    ->first();
                if ($adminGudang) return $adminGudang->id;
            }
            // Fallback ke super_admin
            $superAdmin = User::where('role', 'super_admin')->first();
            return $superAdmin?->id;
        }

        return null;
    }

    /**
     * Tentukan nama staf penyetuju (untuk field staf_penyetuju di Pembelian).
     */
    protected function resolveStafPenyetuju(?int $gudangId): ?string
    {
        $approverId = $this->resolveApproverId($gudangId);
        if (!$approverId) return null;
        return User::find($approverId)?->name;
    }
}
