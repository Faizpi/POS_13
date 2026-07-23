<?php

declare(strict_types=1);

namespace App\Accounting;

use App\Accounting\Exceptions\DuplicateSourceIdentityException;

final class SourceIdentitySet
{
    /** @var array<string, true> */
    private array $keys = [];

    public function add(SourceIdentity $identity): void
    {
        $key = $identity->toKey();

        if (isset($this->keys[$key])) {
            throw DuplicateSourceIdentityException::forIdentity(
                $identity->sourceType,
                $identity->sourceId,
                $identity->journalType->value,
                $identity->sourceVersion,
            );
        }

        $this->keys[$key] = true;
    }

    public function contains(SourceIdentity $identity): bool
    {
        return isset($this->keys[$identity->toKey()]);
    }

    public function count(): int
    {
        return count($this->keys);
    }
}
