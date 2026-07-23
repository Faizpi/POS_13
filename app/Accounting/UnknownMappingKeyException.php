<?php

declare(strict_types=1);

namespace App\Accounting;

final class UnknownMappingKeyException extends DomainException
{
    public function __construct(string $key)
    {
        parent::__construct(sprintf('Unknown mapping key: %s', $key));
    }
}
