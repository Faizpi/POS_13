<?php

declare(strict_types=1);

namespace App\Accounting;

use App\Accounting\Exceptions\MalformedMoneyException;
use App\Accounting\Exceptions\NegativeLedgerAmountException;

final class Money
{
    private const string DECIMAL_PATTERN = '/^-?\d+(\.\d{1,2})?$/';

    private const string MAX_VALUE = '999999999999999999.99';

    private const string MIN_VALUE = '-999999999999999999.99';

    private const string MAX_MINOR = '99999999999999999999';

    private const string MIN_MINOR = '-99999999999999999999';

    private function __construct(
        private readonly string $decimalString,
        private readonly string $minorUnitsString,
    ) {}

    public static function fromDecimalString(string $value): self
    {
        if (! preg_match(self::DECIMAL_PATTERN, $value)) {
            throw MalformedMoneyException::invalidFormat($value);
        }

        // Normalize to 2 decimal places
        $normalized = bcadd($value, '0', 2);

        // Check bounds
        if (bccomp($normalized, self::MAX_VALUE, 2) > 0) {
            throw MalformedMoneyException::overflow($normalized);
        }
        if (bccomp($normalized, self::MIN_VALUE, 2) < 0) {
            throw MalformedMoneyException::overflow($normalized);
        }

        // Convert to minor units (cents) as string
        $minorUnits = bcmul($normalized, '100', 0);

        return new self($normalized, $minorUnits);
    }

    public static function fromMinorUnits(string|int $cents): self
    {
        $centsString = (string) $cents;

        // Validate it's a valid integer string
        if (! preg_match('/^-?\d+$/', $centsString)) {
            throw MalformedMoneyException::invalidFormat($centsString);
        }

        // Check bounds
        if (bccomp($centsString, self::MAX_MINOR, 0) > 0) {
            throw MalformedMoneyException::overflow($centsString);
        }
        if (bccomp($centsString, self::MIN_MINOR, 0) < 0) {
            throw MalformedMoneyException::overflow($centsString);
        }

        $decimal = bcdiv($centsString, '100', 2);

        return new self($decimal, $centsString);
    }

    public function toDecimalString(): string
    {
        return $this->decimalString;
    }

    public function toMinorUnits(): string|int
    {
        // Return int if within safe range, otherwise string
        if (bccomp($this->minorUnitsString, (string) PHP_INT_MAX, 0) <= 0
            && bccomp($this->minorUnitsString, (string) PHP_INT_MIN, 0) >= 0) {
            return (int) $this->minorUnitsString;
        }

        return $this->minorUnitsString;
    }

    public function add(self $other): self
    {
        return self::fromMinorUnits(bcadd($this->minorUnitsString, $other->minorUnitsString, 0));
    }

    public function subtract(self $other): self
    {
        return self::fromMinorUnits(bcsub($this->minorUnitsString, $other->minorUnitsString, 0));
    }

    public function negate(): self
    {
        return self::fromMinorUnits(bcmul($this->minorUnitsString, '-1', 0));
    }

    public function abs(): self
    {
        if ($this->isNegative()) {
            return $this->negate();
        }

        return $this;
    }

    public function multiplyByInt(int $factor): self
    {
        return self::fromMinorUnits(bcmul($this->minorUnitsString, (string) $factor, 0));
    }

    public function equals(self $other): bool
    {
        return bccomp($this->minorUnitsString, $other->minorUnitsString, 0) === 0;
    }

    public function isZero(): bool
    {
        return bccomp($this->minorUnitsString, '0', 0) === 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->minorUnitsString, '0', 0) > 0;
    }

    public function isNegative(): bool
    {
        return bccomp($this->minorUnitsString, '0', 0) < 0;
    }

    public function lessThan(self $other): bool
    {
        return bccomp($this->minorUnitsString, $other->minorUnitsString, 0) < 0;
    }

    public function greaterThan(self $other): bool
    {
        return bccomp($this->minorUnitsString, $other->minorUnitsString, 0) > 0;
    }

    public function lessThanOrEqual(self $other): bool
    {
        return bccomp($this->minorUnitsString, $other->minorUnitsString, 0) <= 0;
    }

    public function greaterThanOrEqual(self $other): bool
    {
        return bccomp($this->minorUnitsString, $other->minorUnitsString, 0) >= 0;
    }

    public function assertNonNegative(string $context): void
    {
        if ($this->isNegative()) {
            throw NegativeLedgerAmountException::forContext($this->decimalString, $context);
        }
    }
}
