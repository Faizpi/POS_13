<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Accounting\AccountMappingException;
use App\Accounting\MappingKey;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\AccountMappingKeyLock;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class AccountMappingService
{
    public function create(
        User $actor,
        MappingKey $key,
        Account $account,
        DateTimeInterface|string $effectiveFrom,
        DateTimeInterface|string|null $effectiveTo = null,
        bool $isProtected = false,
        ?string $changeReason = null,
        ?bool $isActive = null,
    ): AccountMapping {
        $this->assertCanManage($actor);
        [$from, $to, $active] = $this->normalizeInterval($key, $effectiveFrom, $effectiveTo, $isActive);
        $this->assertCompatibleAccount($key, $account);

        return DB::transaction(function () use ($actor, $key, $account, $from, $to, $active, $isProtected, $changeReason): AccountMapping {
            $this->acquireKeyLock($key);

            return $this->createAfterLock($actor, $key, $account, $from, $to, $active, $isProtected, $changeReason);
        });
    }

    public function replaceForEffectiveFrom(
        User $actor,
        MappingKey $key,
        Account $account,
        DateTimeInterface|string $effectiveFrom,
        DateTimeInterface|string|null $effectiveTo = null,
        bool $isProtected = false,
        ?string $changeReason = null,
        ?bool $isActive = null,
    ): AccountMapping {
        $this->assertCanManage($actor);
        [$from, $to, $active] = $this->normalizeInterval($key, $effectiveFrom, $effectiveTo, $isActive);
        $this->assertCompatibleAccount($key, $account);

        return DB::transaction(function () use ($actor, $key, $account, $from, $to, $active, $isProtected, $changeReason): AccountMapping {
            $this->acquireKeyLock($key);
            $existing = AccountMapping::query()
                ->where('mapping_key', $key->value)
                ->whereDate('effective_from', $from)
                ->lockForUpdate()
                ->first();

            if ($existing?->is_protected) {
                throw new AccountMappingException('Protected mappings cannot be replaced.');
            }

            $this->assertDoesNotOverlap($key, $from, $to, $existing?->id);

            if ($existing !== null) {
                $existing->delete();
            }

            return $this->createAfterLock($actor, $key, $account, $from, $to, $active, $isProtected, $changeReason);
        });
    }

    public function resolve(MappingKey $key, DateTimeInterface|string $date): ?Account
    {
        $on = CarbonImmutable::parse($date);

        return AccountMapping::query()
            ->with('account')
            ->where('mapping_key', $key->value)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $on)
            ->where(function ($query) use ($on): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $on);
            })
            ->first()?->account;
    }

    public function isRuntimeReady(DateTimeInterface|string $date): bool
    {
        foreach (MappingKey::runtimeRequired() as $key) {
            if ($this->resolve($key, $date) === null) {
                return false;
            }
        }

        return true;
    }

    private function assertCanManage(User $actor): void
    {
        if (! app(AccountingAuthorization::class)->canManageConfig($actor)) {
            throw new AccountMappingException('Only super admins can manage account mappings.');
        }
    }

    private function assertCompatibleAccount(MappingKey $key, Account $account): void
    {
        if (! $account->is_active) {
            throw new AccountMappingException('Account mappings require an active account.');
        }

        if (! $account->isPostable()) {
            throw new AccountMappingException('Account mappings require a postable account.');
        }

        if (! in_array($account->category, $key->compatibleCategories(), true)) {
            throw new AccountMappingException("Account category is not compatible with {$key->value}.");
        }
    }

    /** @return array{0: CarbonImmutable, 1: ?CarbonImmutable, 2: bool} */
    private function normalizeInterval(
        MappingKey $key,
        DateTimeInterface|string $effectiveFrom,
        DateTimeInterface|string|null $effectiveTo,
        ?bool $isActive,
    ): array {
        $from = CarbonImmutable::parse($effectiveFrom);
        $to = $effectiveTo === null ? null : CarbonImmutable::parse($effectiveTo);

        if ($to !== null && $from->isAfter($to)) {
            throw new AccountMappingException('Effective from date must be on or before effective to date.');
        }

        return [$from, $to, $isActive ?? $key->isRuntimeRequired()];
    }

    private function acquireKeyLock(MappingKey $key): void
    {
        try {
            AccountMappingKeyLock::query()->create([
                'mapping_key' => $key->value,
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent transaction created this key lock first; lock that same row below.
        }

        AccountMappingKeyLock::query()
            ->where('mapping_key', $key->value)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function createAfterLock(
        User $actor,
        MappingKey $key,
        Account $account,
        CarbonImmutable $from,
        ?CarbonImmutable $to,
        bool $isActive,
        bool $isProtected,
        ?string $changeReason,
    ): AccountMapping {
        $this->assertDoesNotOverlap($key, $from, $to);

        return AccountMapping::query()->create([
            'mapping_key' => $key,
            'section' => $this->sectionFor($key),
            'account_id' => $account->id,
            'effective_from' => $from,
            'effective_to' => $to,
            'is_active' => $isActive,
            'is_protected' => $isProtected,
            'changed_by' => $actor->id,
            'change_reason' => $changeReason,
        ]);
    }

    private function assertDoesNotOverlap(MappingKey $key, CarbonImmutable $from, ?CarbonImmutable $to, ?int $ignoredMappingId = null): void
    {
        $intervals = AccountMapping::query()
            ->where('mapping_key', $key->value)
            ->when($ignoredMappingId !== null, fn ($query) => $query->whereKeyNot($ignoredMappingId))
            ->lockForUpdate()
            ->get();

        foreach ($intervals as $interval) {
            if ($this->overlaps($from, $to, $interval->effective_from, $interval->effective_to)) {
                throw new AccountMappingException("Effective interval for {$key->value} overlaps an existing mapping.");
            }
        }
    }

    private function overlaps(CarbonImmutable $from, ?CarbonImmutable $to, CarbonImmutable $otherFrom, ?CarbonImmutable $otherTo): bool
    {
        return ($to === null || $otherFrom->lessThanOrEqualTo($to))
            && ($otherTo === null || $otherTo->greaterThanOrEqualTo($from));
    }

    private function sectionFor(MappingKey $key): string
    {
        return match ($key) {
            MappingKey::SalesRetailRevenue,
            MappingKey::SalesWholesaleRevenue,
            MappingKey::SalesDiscount,
            MappingKey::SalesReturn,
            MappingKey::SalesOutputTax => 'Penjualan',
            MappingKey::PurchaseInventory,
            MappingKey::PurchaseInputTax => 'Pembelian',
            MappingKey::ArReceivable,
            MappingKey::ApPayable => 'AR / AP',
            MappingKey::CashDefault,
            MappingKey::BankDefault,
            MappingKey::CashInTransit,
            MappingKey::CashRounding => 'Kas & Bank',
            MappingKey::InventoryAsset,
            MappingKey::InventoryDamageExpense => 'Persediaan',
            MappingKey::ExpenseGeneral => 'Biaya',
            MappingKey::OpeningEquity => 'Ekuitas & Lainnya',
        };
    }
}
