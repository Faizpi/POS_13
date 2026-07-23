<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Accounting\AccountCategory;
use App\Accounting\AccountCreationOptions;
use App\Accounting\DomainException;
use App\Models\Account;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class AccountCodeGenerator
{
    private const int MAX_DUPLICATE_RETRIES = 3;

    public function suggest(AccountCategory $category, Account $parent): string
    {
        $this->validateParent($category, $parent);

        return $this->nextCode($category, $parent);
    }

    public function create(
        User $actor,
        AccountCategory $category,
        Account $parent,
        string $name,
        ?string $manualCode = null,
        ?AccountCreationOptions $options = null,
    ): Account {
        $this->validateParent($category, $parent);
        $this->validateName($name);
        $options ??= AccountCreationOptions::defaultsFor($category);
        $this->validateOptions($category, $options);

        if ($manualCode !== null && $actor->role !== 'super_admin') {
            throw new DomainException('Only super administrators may override an account code.');
        }

        for ($attempt = 1; $attempt <= self::MAX_DUPLICATE_RETRIES; $attempt++) {
            try {
                return DB::transaction(function () use ($category, $manualCode, $name, $options, $parent): Account {
                    $code = $manualCode ?? $this->nextCode($category, $parent);
                    $this->validateCode($code, $category, $parent);

                    return Account::create([
                        'code' => $code,
                        'name' => trim($name),
                        'parent_id' => $parent->id,
                        'category' => $category,
                        'subcategory' => $options->subcategory,
                        'normal_balance' => $options->normalBalance,
                        'statement_classification' => $options->statementClassification,
                        'cash_flow_category' => $options->cashFlowCategory,
                        'cash_flow_line' => $options->cashFlowLine,
                        'is_postable' => $options->isPostable,
                        'is_control_account' => $options->isControlAccount,
                        'is_system' => false,
                        'is_active' => $options->isActive,
                        'is_used' => false,
                        'display_order' => $options->displayOrder,
                    ]);
                });
            } catch (QueryException $exception) {
                if ($manualCode !== null || ! $this->isDuplicateCodeViolation($exception) || $attempt === self::MAX_DUPLICATE_RETRIES) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('Account-code retry loop exited unexpectedly.');
    }

    private function nextCode(AccountCategory $category, Account $parent): string
    {
        [$prefix, $parentNumber] = $this->parseCode($parent->code);
        $upperBound = $parentNumber + 99;
        $latest = Account::query()
            ->where('parent_id', $parent->id)
            ->where('category', $category)
            ->where('code', 'like', $prefix.'-%')
            ->orderByDesc('code')
            ->value('code');

        $nextNumber = $parentNumber + 1;

        if ($latest !== null) {
            [, $latestNumber] = $this->parseCode($latest);
            $nextNumber = $latestNumber + 1;
        }

        if ($nextNumber > $upperBound) {
            throw new DomainException("Account parent {$parent->code} has no remaining child-code capacity.");
        }

        return $prefix.'-'.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    private function validateParent(AccountCategory $category, Account $parent): void
    {
        [$prefix, $number] = $this->parseCode($parent->code);

        if ((int) $prefix !== $this->categoryPrefix($category) || $number % 100 !== 0) {
            throw new DomainException("Account {$parent->code} is not a valid heading for {$category->value} child codes.");
        }

        if ($parent->category !== $category) {
            throw new DomainException('Account category is not compatible with its parent.');
        }
    }

    private function validateCode(string $code, AccountCategory $category, Account $parent): void
    {
        [$prefix, $number] = $this->parseCode($code);
        [, $parentNumber] = $this->parseCode($parent->code);

        if ((int) $prefix !== $this->categoryPrefix($category) || $number <= $parentNumber || $number > $parentNumber + 99) {
            throw new DomainException("Account code {$code} is outside the parent range.");
        }

        if ($number % 100 === 0) {
            throw new DomainException("Account code {$code} is reserved for a system heading.");
        }

        if (Account::query()->where('code', $code)->exists()) {
            throw new DomainException("Account code {$code} is already in use.");
        }
    }

    private function validateOptions(AccountCategory $category, AccountCreationOptions $options): void
    {
        if ($options->normalBalance !== $category->normalBalance()) {
            throw new DomainException("Account normal balance must match the {$category->value} category default.");
        }

        if ($options->statementClassification !== $category->statementClassification()) {
            throw new DomainException("Account statement classification must match the {$category->value} category.");
        }

        if ($options->isControlAccount && ! Account::isControlAccountCompatible($category, $options->subcategory, $options->isPostable)) {
            throw new DomainException('Control account must be a postable receivable, inventory, tax, or payable account.');
        }
    }

    /** @return array{string, int} */
    private function parseCode(string $code): array
    {
        if (preg_match('/^([1-8])-(\d{4})$/', $code, $matches) !== 1) {
            throw new DomainException("Account code {$code} has an invalid format.");
        }

        return [$matches[1], (int) $matches[2]];
    }

    private function categoryPrefix(AccountCategory $category): int
    {
        return match ($category) {
            AccountCategory::Aset => 1,
            AccountCategory::Kewajiban => 2,
            AccountCategory::Ekuitas => 3,
            AccountCategory::Pendapatan => 4,
            AccountCategory::Hpp => 5,
            AccountCategory::Beban => 6,
            AccountCategory::PendapatanLainnya => 7,
            AccountCategory::BebanLainnya => 8,
        };
    }

    private function validateName(string $name): void
    {
        if (trim($name) === '') {
            throw new DomainException('Account name is required.');
        }
    }

    private function isDuplicateCodeViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = $exception->errorInfo[1] ?? null;
        $message = strtolower($exception->errorInfo[2] ?? $exception->getMessage());

        if ($sqlState !== '23000') {
            return false;
        }

        return ($driverCode === 19 && str_contains($message, 'unique constraint failed: accounts.code'))
            || ($driverCode === 1062 && str_contains($message, 'accounts_code_unique'));
    }
}
