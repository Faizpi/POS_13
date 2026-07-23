<?php

declare(strict_types=1);

namespace App\Accounting\Exceptions;

use DomainException;

final class DuplicateSourceIdentityException extends DomainException
{
    public static function forIdentity(string $sourceType, int $sourceId, string $journalType, int $version): self
    {
        return new self(sprintf(
            'Duplicate source identity: (source_type=%s, source_id=%d, journal_type=%s, source_version=%d).',
            $sourceType,
            $sourceId,
            $journalType,
            $version,
        ));
    }
}
