<?php

declare(strict_types=1);

namespace App\Accounting;

final readonly class JournalPostingRequest
{
    /** @param list<JournalPostingLine> $lines */
    public function __construct(
        public SourceIdentity $sourceIdentity,
        public string $journalDate,
        public string $description,
        public ?int $gudangId,
        public ?string $contactType,
        public ?int $contactId,
        public array $lines,
    ) {
        if (trim($description) === '') {
            throw new DomainException('Journal description is required.');
        }
        if (count($lines) < 2) {
            throw new DomainException('A journal requires at least two posting lines.');
        }
        foreach ($lines as $line) {
            if (! $line instanceof JournalPostingLine) {
                throw new DomainException('Posting requests accept only typed posting lines.');
            }
        }
    }
}
