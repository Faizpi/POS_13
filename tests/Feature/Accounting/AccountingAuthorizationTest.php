<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Gudang;
use App\Models\User;
use App\Services\Accounting\AccountingAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Centralized accounting authorization matrix.
 *
 * Matrix (plan Todo 2):
 *  - super_admin: manage config, post, reverse, initiate cash op anywhere, view all reports.
 *  - admin:       view config; initiate cash op ONLY from assigned active/current warehouse;
 *                 cannot manage mappings, cannot post, cannot reverse; view warehouse-scoped reports.
 *  - spectator:   read-only warehouse-scoped views/reports; cannot mutate anything.
 *  - user/sales:  cannot access accounting config or mutate; no report access beyond existing pages.
 *
 * Cross-warehouse: admin/spectator assigned to gudang A must be DENIED on gudang B.
 */
class AccountingAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Gudang $gudangA;
    private Gudang $gudangB;
    private AccountingAuthorization $authz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gudangA = Gudang::create([
            'nama_gudang' => 'Gudang A',
            'alamat_gudang' => 'Jl. A',
        ]);
        $this->gudangB = Gudang::create([
            'nama_gudang' => 'Gudang B',
            'alamat_gudang' => 'Jl. B',
        ]);

        $this->authz = app(AccountingAuthorization::class);
    }

    // ---------------------------------------------------------------
    // 1. manageAccountingConfig (COA + mappings) — super_admin only
    // ---------------------------------------------------------------

    public function test_super_admin_can_manage_accounting_config(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->assertTrue($this->authz->canManageConfig($user));
    }

    public function test_admin_cannot_manage_accounting_config(): void
    {
        $user = User::factory()->admin($this->gudangA)->create();

        $this->assertFalse($this->authz->canManageConfig($user));
    }

    public function test_spectator_cannot_manage_accounting_config(): void
    {
        $user = User::factory()->spectator($this->gudangA)->create();

        $this->assertFalse($this->authz->canManageConfig($user));
    }

    public function test_sales_user_cannot_manage_accounting_config(): void
    {
        $user = User::factory()->sales($this->gudangA)->create();

        $this->assertFalse($this->authz->canManageConfig($user));
    }

    // ---------------------------------------------------------------
    // 2. viewAccountingConfig — super_admin, admin, spectator; NOT sales
    // ---------------------------------------------------------------

    public function test_super_admin_can_view_accounting_config(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->assertTrue($this->authz->canViewConfig($user));
    }

    public function test_admin_can_view_accounting_config(): void
    {
        $user = User::factory()->admin($this->gudangA)->create();

        $this->assertTrue($this->authz->canViewConfig($user));
    }

    public function test_spectator_can_view_accounting_config(): void
    {
        $user = User::factory()->spectator($this->gudangA)->create();

        $this->assertTrue($this->authz->canViewConfig($user));
    }

    public function test_sales_user_cannot_view_accounting_config(): void
    {
        $user = User::factory()->sales($this->gudangA)->create();

        $this->assertFalse($this->authz->canViewConfig($user));
    }

    // ---------------------------------------------------------------
    // 3. initiateCashOperation — super_admin anywhere; admin only from
    //    assigned active/current warehouse; spectator/sales denied.
    // ---------------------------------------------------------------

    public function test_super_admin_can_initiate_cash_operation_any_warehouse(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->assertTrue($this->authz->canInitiateCashOperation($user, $this->gudangA->id));
        $this->assertTrue($this->authz->canInitiateCashOperation($user, $this->gudangB->id));
        $this->assertTrue($this->authz->canInitiateCashOperation($user));
    }

    public function test_admin_can_initiate_cash_operation_only_from_assigned_warehouse(): void
    {
        $user = User::factory()->admin($this->gudangA)->create();

        // Same warehouse → allowed
        $this->assertTrue($this->authz->canInitiateCashOperation($user, $this->gudangA->id));

        // Different warehouse → denied (cross-warehouse)
        $this->assertFalse($this->authz->canInitiateCashOperation($user, $this->gudangB->id));

        // No warehouse specified → allowed (uses current_gudang_id fallback)
        $this->assertTrue($this->authz->canInitiateCashOperation($user));
    }

    public function test_admin_without_current_warehouse_cannot_initiate_cash_operation(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'gudang_id' => null,
            'current_gudang_id' => null,
        ]);

        $this->assertFalse($this->authz->canInitiateCashOperation($user, $this->gudangA->id));
        $this->assertFalse($this->authz->canInitiateCashOperation($user));
    }

    public function test_spectator_cannot_initiate_cash_operation(): void
    {
        $user = User::factory()->spectator($this->gudangA)->create();

        $this->assertFalse($this->authz->canInitiateCashOperation($user, $this->gudangA->id));
        $this->assertFalse($this->authz->canInitiateCashOperation($user));
    }

    public function test_sales_user_cannot_initiate_cash_operation(): void
    {
        $user = User::factory()->sales($this->gudangA)->create();

        $this->assertFalse($this->authz->canInitiateCashOperation($user, $this->gudangA->id));
        $this->assertFalse($this->authz->canInitiateCashOperation($user));
    }

    // ---------------------------------------------------------------
    // 4. postJournal — super_admin only; admin/spectator/sales denied.
    // ---------------------------------------------------------------

    public function test_super_admin_can_post_journal(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->assertTrue($this->authz->canPostJournal($user));
    }

    public function test_admin_cannot_post_journal(): void
    {
        $user = User::factory()->admin($this->gudangA)->create();

        $this->assertFalse($this->authz->canPostJournal($user));
    }

    public function test_spectator_cannot_post_journal(): void
    {
        $user = User::factory()->spectator($this->gudangA)->create();

        $this->assertFalse($this->authz->canPostJournal($user));
    }

    public function test_sales_user_cannot_post_journal(): void
    {
        $user = User::factory()->sales($this->gudangA)->create();

        $this->assertFalse($this->authz->canPostJournal($user));
    }

    // ---------------------------------------------------------------
    // 5. reverseJournal — super_admin only; all others denied.
    // ---------------------------------------------------------------

    public function test_super_admin_can_reverse_journal(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->assertTrue($this->authz->canReverseJournal($user));
    }

    public function test_admin_cannot_reverse_journal(): void
    {
        $user = User::factory()->admin($this->gudangA)->create();

        $this->assertFalse($this->authz->canReverseJournal($user));
    }

    public function test_spectator_cannot_reverse_journal(): void
    {
        $user = User::factory()->spectator($this->gudangA)->create();

        $this->assertFalse($this->authz->canReverseJournal($user));
    }

    public function test_sales_user_cannot_reverse_journal(): void
    {
        $user = User::factory()->sales($this->gudangA)->create();

        $this->assertFalse($this->authz->canReverseJournal($user));
    }

    // ---------------------------------------------------------------
    // 6. viewAccountingReport — super_admin anywhere; admin/spectator
    //    warehouse-scoped; sales denied.
    // ---------------------------------------------------------------

    public function test_super_admin_can_view_accounting_report_any_warehouse(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->assertTrue($this->authz->canViewReport($user, $this->gudangA->id));
        $this->assertTrue($this->authz->canViewReport($user, $this->gudangB->id));
        $this->assertTrue($this->authz->canViewReport($user));
    }

    public function test_admin_can_view_report_only_from_assigned_warehouse(): void
    {
        $user = User::factory()->admin($this->gudangA)->create();

        $this->assertTrue($this->authz->canViewReport($user, $this->gudangA->id));
        $this->assertFalse($this->authz->canViewReport($user, $this->gudangB->id));
        $this->assertTrue($this->authz->canViewReport($user));
    }

    public function test_spectator_can_view_report_only_from_assigned_warehouse(): void
    {
        $user = User::factory()->spectator($this->gudangA)->create();

        $this->assertTrue($this->authz->canViewReport($user, $this->gudangA->id));
        $this->assertFalse($this->authz->canViewReport($user, $this->gudangB->id));
        $this->assertTrue($this->authz->canViewReport($user));
    }

    public function test_sales_user_cannot_view_accounting_report(): void
    {
        $user = User::factory()->sales($this->gudangA)->create();

        $this->assertFalse($this->authz->canViewReport($user, $this->gudangA->id));
        $this->assertFalse($this->authz->canViewReport($user));
    }

    // ---------------------------------------------------------------
    // 7. Unauthenticated guest is denied for everything
    // ---------------------------------------------------------------

    public function test_unauthenticated_user_is_denied_all_accounting_capabilities(): void
    {
        // Build a User model without persisting as "auth" — pass null-equivalent
        // by creating a User instance but not actingAs().
        $user = User::factory()->superAdmin()->make();

        // When no user is authenticated, all capabilities must be false.
        // We pass a User but the authz should use auth()->user() as the gate context.
        // Simpler: call with no actingAs, so auth()->user() is null.
        // The methods accept User, so we test by explicitly passing null via the
        // "forUser(null)" path if available. Instead, we verify by asserting
        // that the methods require a non-null user by calling them with a
        // non-authenticated context: we bind a fresh auth state.
        auth()->logout();

        // We cannot pass null (role column is NOT NULL), so we verify that
        // a user with an unrecognized role string is denied all capabilities.
        $guest = User::factory()->create([
            'role' => 'guest',
            'gudang_id' => null,
            'current_gudang_id' => null,
        ]);

        $this->assertFalse($this->authz->canManageConfig($guest));
        $this->assertFalse($this->authz->canViewConfig($guest));
        $this->assertFalse($this->authz->canInitiateCashOperation($guest));
        $this->assertFalse($this->authz->canPostJournal($guest));
        $this->assertFalse($this->authz->canReverseJournal($guest));
        $this->assertFalse($this->authz->canViewReport($guest));
    }

    // ---------------------------------------------------------------
    // 8. Cross-warehouse hardening: admin with multiple assignments
    //    can only operate on assigned warehouses.
    // ---------------------------------------------------------------

    public function test_admin_with_multiple_warehouse_assignments_can_operate_on_any_assigned(): void
    {
        $user = User::factory()->admin($this->gudangA)->create();
        // Also assign gudangB
        $user->gudangs()->attach($this->gudangB->id);

        $this->assertTrue($this->authz->canInitiateCashOperation($user, $this->gudangA->id));
        $this->assertTrue($this->authz->canInitiateCashOperation($user, $this->gudangB->id));

        // Create a third gudang NOT assigned
        $gudangC = Gudang::create(['nama_gudang' => 'Gudang C', 'alamat_gudang' => 'Jl. C']);
        $this->assertFalse($this->authz->canInitiateCashOperation($user, $gudangC->id));
    }
}
