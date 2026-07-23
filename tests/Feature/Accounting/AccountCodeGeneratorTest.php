<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Accounting\AccountCategory;
use App\Accounting\AccountCreationOptions;
use App\Accounting\DomainException;
use App\Accounting\NormalBalance;
use App\Accounting\StatementClassification;
use App\Models\Account;
use App\Models\User;
use App\Services\Accounting\AccountCodeGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class AccountCodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggests_the_sequential_next_child_code_within_its_parent_range(): void
    {
        $parent = $this->assetCashParent();
        $this->createChild($parent, '1-1101');
        $this->createChild($parent, '1-1102');
        $this->createChild($parent, '1-1104');

        $generator = app(AccountCodeGenerator::class);

        $this->assertSame('1-1105', $generator->suggest(AccountCategory::Aset, $parent));
    }

    public function test_suggestion_is_isolated_by_category_and_parent(): void
    {
        $assetCashParent = $this->assetCashParent();
        $assetReceivableParent = Account::factory()->heading()->create([
            'code' => '1-1200',
            'category' => AccountCategory::Aset,
        ]);
        $liabilityParent = Account::factory()->heading()->create([
            'code' => '2-1100',
            'category' => AccountCategory::Kewajiban,
        ]);

        $this->createChild($assetCashParent, '1-1198');
        $this->createChild($assetReceivableParent, '1-1204');
        $this->createChild($liabilityParent, '2-1109');

        $generator = app(AccountCodeGenerator::class);

        $this->assertSame('1-1199', $generator->suggest(AccountCategory::Aset, $assetCashParent));
        $this->assertSame('1-1205', $generator->suggest(AccountCategory::Aset, $assetReceivableParent));
        $this->assertSame('2-1110', $generator->suggest(AccountCategory::Kewajiban, $liabilityParent));
    }

    public function test_authorized_user_can_create_an_account_with_a_valid_manual_code(): void
    {
        $parent = $this->assetCashParent();
        $actor = User::factory()->create(['role' => 'super_admin']);

        $account = app(AccountCodeGenerator::class)->create(
            $actor,
            AccountCategory::Aset,
            $parent,
            'Kas Operasional',
            '1-1117',
        );

        $this->assertSame('1-1117', $account->code);
        $this->assertSame($parent->id, $account->parent_id);
        $this->assertSame(AccountCategory::Aset, $account->category);
        $this->assertDatabaseHas('accounts', ['code' => '1-1117', 'name' => 'Kas Operasional']);
    }

    public function test_invalid_manual_codes_are_rejected_without_creating_partial_rows(): void
    {
        $parent = $this->assetCashParent();
        $actor = User::factory()->create(['role' => 'super_admin']);
        $this->createChild($parent, '1-1107');
        $generator = app(AccountCodeGenerator::class);
        $initialCount = Account::count();

        foreach (['2-1108', '1-1100', '1-1107'] as $code) {
            try {
                $generator->create($actor, AccountCategory::Aset, $parent, 'Invalid '.$code, $code);
                $this->fail("Manual code {$code} should be rejected.");
            } catch (DomainException) {
                $this->assertSame($initialCount, Account::count());
            }
        }
    }

    public function test_unauthorized_user_cannot_override_the_suggested_code(): void
    {
        $parent = $this->assetCashParent();
        $actor = User::factory()->create(['role' => 'admin']);

        $this->expectException(DomainException::class);

        app(AccountCodeGenerator::class)->create(
            $actor,
            AccountCategory::Aset,
            $parent,
            'Kas Tanpa Otorisasi',
            '1-1117',
        );
    }

    public function test_retries_an_insert_time_sqlite_duplicate_without_leaving_a_partial_row(): void
    {
        $parent = $this->assetCashParent();
        $actor = User::factory()->create(['role' => 'super_admin']);
        $generator = app(AccountCodeGenerator::class);
        $creatingAttempts = 0;
        $collisionInjected = false;

        $this->assertSame('1-1101', $generator->suggest(AccountCategory::Aset, $parent));

        Event::listen('eloquent.creating: '.Account::class, function (Account $account) use (&$creatingAttempts, &$collisionInjected, $parent): void {
            $creatingAttempts++;

            if ($collisionInjected || $account->name !== 'Retried account') {
                return;
            }

            $collisionInjected = true;

            Account::withoutEvents(fn (): Account => $this->createChild($parent, $account->code));
        });

        try {
            $account = $generator->create(
                $actor,
                AccountCategory::Aset,
                $parent,
                'Retried account',
            );
        } finally {
            Event::forget('eloquent.creating: '.Account::class);
        }

        $this->assertSame('1-1101', $account->code);
        $this->assertSame(2, $creatingAttempts);
        $this->assertDatabaseCount('accounts', 2);
        $this->assertSame(1, Account::query()->where('parent_id', $parent->id)->count());
        $this->assertDatabaseHas('accounts', ['code' => '1-1101', 'name' => 'Retried account']);
    }

    public function test_duplicate_matcher_recognizes_sqlite_and_mysql_accounts_code_unique_metadata_only(): void
    {
        $generator = app(AccountCodeGenerator::class);

        $this->assertTrue($this->isDuplicateCodeViolation($generator, [
            '23000',
            19,
            'UNIQUE constraint failed: accounts.code',
        ], 'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: accounts.code'));
        $this->assertTrue($this->isDuplicateCodeViolation($generator, [
            '23000',
            1062,
            "Duplicate entry '1-1101' for key 'accounts_code_unique'",
        ], "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1-1101' for key 'accounts_code_unique'"));
        $this->assertFalse($this->isDuplicateCodeViolation($generator, [
            '23000',
            1062,
            "Duplicate entry '1-1101' for key 'other_unique'",
        ], "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1-1101' for key 'other_unique'"));
    }

    public function test_invalid_typed_creation_options_are_rejected_without_partial_rows(): void
    {
        $parent = $this->assetCashParent();
        $actor = User::factory()->create(['role' => 'super_admin']);
        $generator = app(AccountCodeGenerator::class);
        $initialCount = Account::count();

        $invalidOptions = [
            new AccountCreationOptions(
                normalBalance: NormalBalance::Kredit,
                statementClassification: StatementClassification::Neraca,
            ),
            new AccountCreationOptions(
                normalBalance: NormalBalance::Debit,
                statementClassification: StatementClassification::LabaRugi,
            ),
            new AccountCreationOptions(
                subcategory: 'kas',
                normalBalance: NormalBalance::Debit,
                statementClassification: StatementClassification::Neraca,
                isPostable: false,
                isControlAccount: true,
            ),
        ];

        foreach ($invalidOptions as $options) {
            try {
                $generator->create($actor, AccountCategory::Aset, $parent, 'Invalid options', null, $options);
                $this->fail('Invalid typed creation options must be rejected.');
            } catch (DomainException) {
                $this->assertSame($initialCount, Account::count());
            }
        }
    }

    private function assetCashParent(): Account
    {
        return Account::factory()->heading()->create([
            'code' => '1-1100',
            'category' => AccountCategory::Aset,
        ]);
    }

    private function createChild(Account $parent, string $code): Account
    {
        return Account::factory()->withParent($parent)->create(['code' => $code]);
    }

    /** @param array{string, int, string} $errorInfo */
    private function isDuplicateCodeViolation(AccountCodeGenerator $generator, array $errorInfo, string $message): bool
    {
        $exception = new QueryException('sqlite', 'insert into "accounts"', [], new \PDOException($message));
        $exception->errorInfo = $errorInfo;
        $matcher = new \ReflectionMethod($generator, 'isDuplicateCodeViolation');

        return $matcher->invoke($generator, $exception);
    }
}
