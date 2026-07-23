<?php

declare(strict_types=1);

namespace App\Accounting;

use InvalidArgumentException;

final class SourceIdentity
{
    public function __construct(
        public readonly string $sourceType,
        public readonly int $sourceId,
        public readonly JournalType $journalType,
        public readonly int $sourceVersion,
    ) {
        if ($sourceType === '') {
            throw new InvalidArgumentException('sourceType must not be empty.');
        }
        if ($sourceId <= 0) {
            throw new InvalidArgumentException('sourceId must be positive.');
        }
        if ($sourceVersion <= 0) {
            throw new InvalidArgumentException('sourceVersion must be positive.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->sourceType === $other->sourceType
            && $this->sourceId === $other->sourceId
            && $this->journalType === $other->journalType
            && $this->sourceVersion === $other->sourceVersion;
    }

    public function toKey(): string
    {
        return sprintf(
            '%s:%d:%s:%d',
            $this->sourceType,
            $this->sourceId,
            $this->journalType->value,
            $this->sourceVersion,
        );
    }
}
