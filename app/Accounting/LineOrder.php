<?php

declare(strict_types=1);

namespace App\Accounting;

use InvalidArgumentException;

final class LineOrder
{
    public function __construct(
        public readonly int $value,
    ) {
        if ($value <= 0) {
            throw new InvalidArgumentException('LineOrder must be a positive integer.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function lessThan(self $other): bool
    {
        return $this->value < $other->value;
    }

    public function compareTo(self $other): int
    {
        return $this->value <=> $other->value;
    }
}
