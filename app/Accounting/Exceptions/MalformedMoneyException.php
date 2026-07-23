<?php

declare(strict_types=1);

namespace App\Accounting\Exceptions;

use InvalidArgumentException;

final class MalformedMoneyException extends InvalidArgumentException
{
    public static function invalidFormat(string $input): self
    {
        return new self(sprintf('Invalid money format: "%s". Expected decimal string with up to 2 decimal places within DECIMAL(20,2) bounds.', $input));
    }

    public static function overflow(string $result): self
    {
        return new self(sprintf('Money arithmetic result "%s" exceeds DECIMAL(20,2) bounds.', $result));
    }
}
