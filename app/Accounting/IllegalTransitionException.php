<?php

declare(strict_types=1);

namespace App\Accounting;

final class IllegalTransitionException extends DomainException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct(sprintf(
            'Illegal journal status transition from %s to %s',
            $from,
            $to,
        ));
    }
}
