<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Accounting\AccountCategory;
use App\Accounting\DomainException;
use App\Accounting\MappingKey;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\AccountMappingKeyLock;
use App\Models\User;
use App\Services\Accounting\AccountMappingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AccountMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_an_audited_effective_dated_mapping_with_a_stable_key(): void
    {
        $actor = User::factory()->superAdmin()->create();
        $account = $this->account(AccountCategory::Pendapatan);

        $mapping = app(AccountMappingService::class)->create(
            actor: $actor,
            key: MappingKey::SalesRetailRevenue,
            account: $account,
            effectiveFrom: CarbonImmutable::parse('2026-07-01'),
            effectiveTo: CarbonImmutable::parse('2026-07-31'),
            isProtected: true,
            changeReason: 'Konfigurasi awal penjualan.',
        );

        $this->assertSame(MappingKey::SalesRetailRevenue, $mapping->mapping_key);
        $this->assertSame($account->id, $mapping->account_id);
        $this->assertSame($actor->id, $mapping->changed_by);
        $this->assertTrue($mapping->is_active);
        $this->assertTrue($mapping->is_protected);
        $this->assertSame('2026-07-01', $mapping->effective_from->toDateString());
        $this->assertSame('2026-07-31', $mapping->effective_to?->toDateString());
        $this->assertNotNull($mapping->created_at);
        $this->assertNotNull($mapping->updated_at);
    }

    public function test_it_allows_adjacent_intervals_but_rejects_any_overlap_atomically(): void
    {
        $actor = User::factory()->superAdmin()->create();
        $service = app(AccountMappingService::class);
        $first = $this->account(AccountCategory::Pendapatan);
        $second = $this->account(AccountCategory::Pendapatan);

        $service->create($actor, MappingKey::SalesRetailRevenue, $first, '2026-07-01', '2026-07-31');
        $service->create($actor, MappingKey::SalesRetailRevenue, $second, '2026-08-01');

        try {
            $service->create($actor, MappingKey::SalesRetailRevenue, $second, '2026-07-31', '2026-08-05');
            $this->fail('Overlapping effective intervals must be rejected.');
        } catch (DomainException) {
            $this->assertSame(2, AccountMapping::query()->count());
        }
    }

    public function test_it_rejects_a_reversed_effective_interval_without_persisting_a_row(): void
    {
        $this->expectException(DomainException::class);

        app(AccountMappingService::class)->create(
            User::factory()->superAdmin()->create(),
            MappingKey::SalesRetailRevenue,
            $this->account(AccountCategory::Pendapatan),
            '2026-08-01',
            '2026-07-31',
        );
    }

    public function test_it_rejects_inactive_non_postable_and_category_incompatible_accounts(): void
    {
        $actor = User::factory()->superAdmin()->create();
        $service = app(AccountMappingService::class);

        foreach ([
            $this->account(AccountCategory::Pendapatan, ['is_active' => false]),
            $this->account(AccountCategory::Pendapatan, ['is_postable' => false]),
            $this->account(AccountCategory::Aset),
        ] as $account) {
            try {
                $service->create($actor, MappingKey::SalesRetailRevenue, $account, '2026-07-01');
                $this->fail('An incompatible mapping account must be rejected.');
            } catch (DomainException) {
                $this->assertDatabaseCount('account_mappings', 0);
            }
        }
    }

    public function test_model_guards_reject_incompatible_accounts_and_admin_mutations(): void
    {
        $admin = User::factory()->admin()->create();

        try {
            app(AccountMappingService::class)->create(
                $admin,
                MappingKey::SalesRetailRevenue,
                $this->account(AccountCategory::Pendapatan),
                '2026-07-01',
            );
            $this->fail('Admin mutation must be denied by the mapping service.');
        } catch (DomainException) {
            $this->assertDatabaseCount('account_mappings', 0);
        }

        $this->expectException(DomainException::class);

        AccountMapping::query()->create([
            'mapping_key' => MappingKey::SalesRetailRevenue,
            'section' => 'Penjualan',
            'account_id' => $this->account(AccountCategory::Aset)->id,
            'effective_from' => '2026-07-01',
            'is_active' => true,
            'is_protected' => false,
            'changed_by' => User::factory()->superAdmin()->create()->id,
        ]);
    }

    public function test_only_runtime_keys_are_required_for_readiness_and_prepared_tax_keys_remain_inactive(): void
    {
        $actor = User::factory()->superAdmin()->create();
        $service = app(AccountMappingService::class);

        $this->assertFalse($service->isRuntimeReady('2026-07-01'));

        foreach (MappingKey::runtimeRequired() as $key) {
            $service->create($actor, $key, $this->account($key->compatibleCategories()[0]), '2026-07-01');
        }

        $this->assertTrue($service->isRuntimeReady('2026-07-01'));
        $this->assertFalse(MappingKey::SalesOutputTax->isRuntimeRequired());

        $prepared = $service->create(
            $actor,
            MappingKey::SalesOutputTax,
            $this->account(AccountCategory::Kewajiban),
            '2026-07-01',
            null,
            false,
        );

        $this->assertFalse($prepared->is_active);

        $this->expectException(DomainException::class);
        $prepared->update(['is_active' => true]);
    }

    public function test_protected_mappings_cannot_be_deleted_or_have_their_key_mutated(): void
    {
        $actor = User::factory()->superAdmin()->create();
        $mapping = app(AccountMappingService::class)->create(
            $actor,
            MappingKey::SalesRetailRevenue,
            $this->account(AccountCategory::Pendapatan),
            '2026-07-01',
            null,
            true,
        );

        foreach ([
            fn (): bool => $mapping->update(['mapping_key' => MappingKey::SalesDiscount]),
            fn (): ?bool => $mapping->delete(),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Protected mapping state must remain immutable.');
            } catch (DomainException) {
                $this->assertDatabaseHas('account_mappings', ['id' => $mapping->id]);
            }
        }
    }

    public function test_resolve_reads_only_the_mapping_effective_on_the_requested_date(): void
    {
        $actor = User::factory()->superAdmin()->create();
        $service = app(AccountMappingService::class);
        $july = $this->account(AccountCategory::Pendapatan);
        $august = $this->account(AccountCategory::Pendapatan);

        $service->create($actor, MappingKey::SalesRetailRevenue, $july, '2026-07-01', '2026-07-31');
        $service->create($actor, MappingKey::SalesRetailRevenue, $august, '2026-08-01');

        $this->assertTrue($service->resolve(MappingKey::SalesRetailRevenue, '2026-07-31')->is($july));
        $this->assertTrue($service->resolve(MappingKey::SalesRetailRevenue, '2026-08-01')->is($august));
    }

    public function test_failed_replacement_keeps_the_original_mapping_row_intact(): void
    {
        $actor = User::factory()->superAdmin()->create();
        $service = app(AccountMappingService::class);
        $originalAccount = $this->account(AccountCategory::Pendapatan);
        $replacementAccount = $this->account(AccountCategory::Pendapatan);
        $original = $service->create(
            $actor,
            MappingKey::SalesRetailRevenue,
            $originalAccount,
            '2026-07-01',
            '2026-07-31',
            false,
            'Konfigurasi Juli.',
        );
        $service->create(
            $actor,
            MappingKey::SalesRetailRevenue,
            $this->account(AccountCategory::Pendapatan),
            '2026-08-01',
            null,
            false,
            'Konfigurasi Agustus.',
        );

        try {
            $service->replaceForEffectiveFrom(
                $actor,
                MappingKey::SalesRetailRevenue,
                $replacementAccount,
                '2026-07-01',
                null,
                false,
                'Perubahan tidak valid.',
            );
            $this->fail('Overlapping replacement must be rejected.');
        } catch (DomainException) {
            $this->assertDatabaseHas('account_mappings', [
                'id' => $original->id,
                'account_id' => $originalAccount->id,
                'change_reason' => 'Konfigurasi Juli.',
            ]);
            $original->refresh();
            $this->assertSame('2026-07-01', $original->effective_from->toDateString());
            $this->assertSame('2026-07-31', $original->effective_to?->toDateString());
            $this->assertDatabaseCount('account_mappings', 2);
        }
    }

    public function test_first_interval_acquires_a_unique_key_lock_before_writing_the_mapping(): void
    {
        $actor = User::factory()->superAdmin()->create();
        $key = MappingKey::SalesRetailRevenue;

        $this->assertDatabaseMissing('account_mapping_key_locks', ['mapping_key' => $key->value]);

        $mapping = app(AccountMappingService::class)->create(
            $actor,
            $key,
            $this->account(AccountCategory::Pendapatan),
            '2026-07-01',
        );

        $lock = AccountMappingKeyLock::query()->where('mapping_key', $key->value)->firstOrFail();

        $this->assertDatabaseHas('account_mapping_key_locks', [
            'id' => $lock->id,
            'mapping_key' => $key->value,
        ]);
        $this->assertSame($key, $mapping->mapping_key);
    }

    public function test_key_lock_uniqueness_serializes_first_insert_contenders_for_the_same_stable_key(): void
    {
        $key = MappingKey::SalesRetailRevenue;

        AccountMappingKeyLock::query()->create(['mapping_key' => $key->value]);

        $this->expectException(UniqueConstraintViolationException::class);

        AccountMappingKeyLock::query()->create(['mapping_key' => $key->value]);
    }

    /** @param array<string, mixed> $attributes */
    private function account(AccountCategory $category, array $attributes = []): Account
    {
        return Account::factory()->create([
            'category' => $category,
            'normal_balance' => $category->normalBalance(),
            'statement_classification' => $category->statementClassification(),
            'is_active' => true,
            'is_postable' => true,
            ...$attributes,
        ]);
    }
}
