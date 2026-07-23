<?php

declare(strict_types=1);

namespace App\Accounting;

final readonly class JournalPostingLine
{
    public function __construct(
        public MappingKey $mappingKey,
        public LineOrder $lineOrder,
        public ?Money $debit,
        public ?Money $credit,
        public ?int $gudangId = null,
        public ?string $contactType = null,
        public ?int $contactId = null,
        public ?string $description = null,
    ) {
        if (($debit === null) === ($credit === null)) {
            throw new DomainException('A posting line must contain exactly one debit or credit amount.');
        }

        if (($debit !== null && ! $debit->isPositive()) || ($credit !== null && ! $credit->isPositive())) {
            throw new DomainException('Posting line amounts must be positive.');
        }
    }
}
