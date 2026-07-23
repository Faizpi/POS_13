<?php

declare(strict_types=1);

namespace App\Accounting\Exceptions;

use DomainException;

final class NegativeLedgerAmountException extends DomainException
{
    public static function forContext(string $amount, string $context): self
    {
        return new self(sprintf('Ledger amount "%s" must be non-negative for %s.', $amount, $context));
    }
}
