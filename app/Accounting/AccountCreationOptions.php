<?php

declare(strict_types=1);

namespace App\Accounting;

final readonly class AccountCreationOptions
{
    public function __construct(
        public StatementClassification $statementClassification,
        public NormalBalance $normalBalance,
        public ?string $subcategory = null,
        public ?string $cashFlowCategory = null,
        public ?string $cashFlowLine = null,
        public bool $isPostable = true,
        public bool $isControlAccount = false,
        public bool $isActive = true,
        public int $displayOrder = 0,
    ) {
        if ($this->displayOrder < 0) {
            throw new DomainException('Account display order cannot be negative.');
        }
    }

    public static function defaultsFor(AccountCategory $category): self
    {
        return new self(
            normalBalance: $category->normalBalance(),
            statementClassification: $category->statementClassification(),
        );
    }
}
