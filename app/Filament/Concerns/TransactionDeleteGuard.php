<?php

namespace App\Filament\Concerns;

/**
 * Centralized guard for transaction delete actions.
 *
 * Only records with a side-effect-free status (Pending, Rejected, Canceled)
 * may be hard-deleted. Approved/Lunas records have stock or money side effects
 * and must be canceled first.
 */
final class TransactionDeleteGuard
{
    /** @var list<string> Statuses where hard delete is safe (no stock/money side effects) */
    private const DELETABLE_STATUSES = ['Pending', 'Rejected', 'Canceled'];

    public static function canDeletePenjualan(object $record): bool
    {
        return self::hasSideEffectFreeStatus($record);
    }

    public static function canDeletePembelian(object $record): bool
    {
        return self::hasSideEffectFreeStatus($record);
    }

    public static function canDeletePembayaran(object $record): bool
    {
        return self::hasSideEffectFreeStatus($record);
    }

    public static function canDeletePenerimaanBarang(object $record): bool
    {
        return self::hasSideEffectFreeStatus($record);
    }

    public static function canDeleteKunjungan(object $record): bool
    {
        return self::hasSideEffectFreeStatus($record);
    }

    private static function hasSideEffectFreeStatus(object $record): bool
    {
        return in_array((string) $record->status, self::DELETABLE_STATUSES, true);
    }
}
