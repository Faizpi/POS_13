<?php

declare(strict_types=1);

namespace Tests\Unit\Accounting;

use App\Accounting\Exceptions\DuplicateSourceIdentityException;
use App\Accounting\Exceptions\MalformedMoneyException;
use App\Accounting\Exceptions\NegativeLedgerAmountException;
use App\Accounting\IdempotencyKey;
use App\Accounting\JournalType;
use App\Accounting\LineOrder;
use App\Accounting\Money;
use App\Accounting\SourceIdentity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MoneyAndSourceIdentityTest extends TestCase
{
    // ────────────────────────────────────────────────────────────────────
    // Money: Construction & parsing
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function money_parses_decimal_string_exactly(): void
    {
        $m = Money::fromDecimalString('0.01');

        self::assertSame('0.01', $m->toDecimalString());
        self::assertSame(1, $m->toMinorUnits());
    }

    #[Test]
    public function money_parses_zero(): void
    {
        $m = Money::fromDecimalString('0.00');

        self::assertSame('0.00', $m->toDecimalString());
        self::assertSame(0, $m->toMinorUnits());
    }

    #[Test]
    public function money_parses_large_decimal_value_within_bounds(): void
    {
        // DECIMAL(20,2) max = 999999999999999999.99 (18 digits before decimal, 2 after)
        $maxValue = '999999999999999999.99';
        $m = Money::fromDecimalString($maxValue);

        self::assertSame($maxValue, $m->toDecimalString());
        self::assertSame('99999999999999999999', (string) $m->toMinorUnits());
    }

    #[Test]
    public function money_parses_negative_amount(): void
    {
        $m = Money::fromDecimalString('-123.45');

        self::assertSame('-123.45', $m->toDecimalString());
        self::assertSame(-12345, $m->toMinorUnits());
    }

    #[Test]
    public function money_constructs_from_minor_units(): void
    {
        $m = Money::fromMinorUnits(1);

        self::assertSame('0.01', $m->toDecimalString());
        self::assertSame(1, $m->toMinorUnits());
    }

    #[Test]
    public function money_constructs_from_large_minor_units(): void
    {
        $m = Money::fromMinorUnits('99999999999999999999');

        self::assertSame('999999999999999999.99', $m->toDecimalString());
    }

    // ────────────────────────────────────────────────────────────────────
    // Money: Round-trip exactness
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function money_round_trips_decimal_string_exactly(): void
    {
        $values = [
            '0.01',
            '0.10',
            '1.00',
            '123456789.99',
            '999999999999999999.99',
            '-0.01',
            '-999999999999999999.99',
        ];

        foreach ($values as $v) {
            $m = Money::fromDecimalString($v);
            self::assertSame($v, $m->toDecimalString(), "Round-trip failed for {$v}");
        }
    }

    #[Test]
    public function money_round_trips_minor_units_exactly(): void
    {
        $minorValues = [0, 1, 10, 100, -1, '99999999999999999999', '-99999999999999999999'];

        foreach ($minorValues as $cents) {
            $m = Money::fromMinorUnits($cents);
            $expected = is_string($cents) ? $cents : (string) $cents;
            self::assertSame($expected, (string) $m->toMinorUnits(), "Round-trip failed for minor units {$cents}");
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // Money: Rejection of malformed / out-of-bounds input
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('malformedMoneyProvider')]
    public function money_rejects_malformed_input(string $input): void
    {
        $this->expectException(MalformedMoneyException::class);

        Money::fromDecimalString($input);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedMoneyProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'NaN' => ['NaN'];
        yield 'Infinity' => ['Infinity'];
        yield '-Infinity' => ['-Infinity'];
        yield 'letters' => ['abc'];
        yield 'mixed' => ['12.3x'];
        yield 'double dot' => ['1.2.3'];
        yield 'three decimal places' => ['1.234'];
        yield 'no leading zero' => ['.5'];
        yield 'trailing dot' => ['123.'];
        yield 'leading plus' => ['+1.00'];
        yield 'whitespace' => [' 1.00'];
        yield 'comma decimal' => ['1,00'];
        yield 'scientific notation' => ['1e2'];
    }

    #[Test]
    public function money_rejects_value_exceeding_decimal_20_2_max(): void
    {
        $this->expectException(MalformedMoneyException::class);

        // 19 digits before decimal = exceeds DECIMAL(20,2)
        Money::fromDecimalString('1000000000000000000.00');
    }

    #[Test]
    public function money_rejects_value_below_decimal_20_2_min(): void
    {
        $this->expectException(MalformedMoneyException::class);

        Money::fromDecimalString('-1000000000000000000.00');
    }

    // ────────────────────────────────────────────────────────────────────
    // Money: Arithmetic (exact, no floats)
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function money_adds_exactly(): void
    {
        $a = Money::fromDecimalString('0.01');
        $b = Money::fromDecimalString('0.02');

        $sum = $a->add($b);

        self::assertSame('0.03', $sum->toDecimalString());
        self::assertSame(3, $sum->toMinorUnits());
    }

    #[Test]
    public function money_subtracts_exactly(): void
    {
        $a = Money::fromDecimalString('1.00');
        $b = Money::fromDecimalString('0.01');

        $diff = $a->subtract($b);

        self::assertSame('0.99', $diff->toDecimalString());
        self::assertSame(99, $diff->toMinorUnits());
    }

    #[Test]
    public function money_negates(): void
    {
        $m = Money::fromDecimalString('123.45');
        $neg = $m->negate();

        self::assertSame('-123.45', $neg->toDecimalString());
        self::assertSame(-12345, $neg->toMinorUnits());

        // Double negate returns to original
        self::assertSame('123.45', $neg->negate()->toDecimalString());
    }

    #[Test]
    public function money_absolute_value(): void
    {
        $neg = Money::fromDecimalString('-99.99');
        self::assertSame('99.99', $neg->abs()->toDecimalString());

        $pos = Money::fromDecimalString('99.99');
        self::assertSame('99.99', $pos->abs()->toDecimalString());

        $zero = Money::fromDecimalString('0.00');
        self::assertSame('0.00', $zero->abs()->toDecimalString());
    }

    #[Test]
    public function money_multiplies_by_integer_factor(): void
    {
        $m = Money::fromDecimalString('0.01');
        $result = $m->multiplyByInt(100);

        self::assertSame('1.00', $result->toDecimalString());
    }

    #[Test]
    public function money_multiply_by_negative_integer(): void
    {
        $m = Money::fromDecimalString('5.00');
        $result = $m->multiplyByInt(-3);

        self::assertSame('-15.00', $result->toDecimalString());
    }

    #[Test]
    public function money_multiply_by_zero(): void
    {
        $m = Money::fromDecimalString('999.99');
        $result = $m->multiplyByInt(0);

        self::assertSame('0.00', $result->toDecimalString());
    }

    #[Test]
    public function money_addition_does_not_overflow_decimal_20_2(): void
    {
        $this->expectException(MalformedMoneyException::class);

        $a = Money::fromDecimalString('999999999999999999.99');
        $b = Money::fromDecimalString('0.01');

        $a->add($b); // exceeds DECIMAL(20,2) max
    }

    #[Test]
    public function money_subtraction_does_not_underflow_decimal_20_2(): void
    {
        $this->expectException(MalformedMoneyException::class);

        $a = Money::fromDecimalString('-999999999999999999.99');
        $b = Money::fromDecimalString('0.01');

        $a->subtract($b); // exceeds DECIMAL(20,2) min
    }

    // ────────────────────────────────────────────────────────────────────
    // Money: Comparison
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function money_equality(): void
    {
        $a = Money::fromDecimalString('1.00');
        $b = Money::fromDecimalString('1.00');
        $c = Money::fromDecimalString('1.01');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    #[Test]
    public function money_is_zero(): void
    {
        self::assertTrue(Money::fromDecimalString('0.00')->isZero());
        self::assertFalse(Money::fromDecimalString('0.01')->isZero());
        self::assertFalse(Money::fromDecimalString('-0.01')->isZero());
    }

    #[Test]
    public function money_is_positive_and_negative(): void
    {
        $pos = Money::fromDecimalString('1.00');
        $neg = Money::fromDecimalString('-1.00');
        $zero = Money::fromDecimalString('0.00');

        self::assertTrue($pos->isPositive());
        self::assertFalse($pos->isNegative());

        self::assertTrue($neg->isNegative());
        self::assertFalse($neg->isPositive());

        self::assertFalse($zero->isPositive());
        self::assertFalse($zero->isNegative());
    }

    #[Test]
    public function money_comparison_operators(): void
    {
        $a = Money::fromDecimalString('1.00');
        $b = Money::fromDecimalString('2.00');

        self::assertTrue($a->lessThan($b));
        self::assertFalse($b->lessThan($a));
        self::assertTrue($b->greaterThan($a));
        self::assertTrue($a->lessThanOrEqual($a));
        self::assertTrue($a->greaterThanOrEqual($a));
    }

    // ────────────────────────────────────────────────────────────────────
    // Money: Immutability
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function money_is_immutable(): void
    {
        $a = Money::fromDecimalString('1.00');
        $b = Money::fromDecimalString('2.00');
        $sum = $a->add($b);

        self::assertSame('1.00', $a->toDecimalString());
        self::assertSame('2.00', $b->toDecimalString());
        self::assertSame('3.00', $sum->toDecimalString());
    }

    // ────────────────────────────────────────────────────────────────────
    // Money: Ledger-specific validation (non-negative for balances)
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function money_rejects_negative_for_ledger_balance(): void
    {
        $this->expectException(NegativeLedgerAmountException::class);

        $m = Money::fromDecimalString('-0.01');
        $m->assertNonNegative('ledger balance');
    }

    #[Test]
    public function money_allows_zero_and_positive_for_ledger_balance(): void
    {
        Money::fromDecimalString('0.00')->assertNonNegative('balance');
        Money::fromDecimalString('100.00')->assertNonNegative('balance');

        // No exception = pass
        self::assertTrue(true);
    }

    // ────────────────────────────────────────────────────────────────────
    // JournalType enum
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function journal_type_is_backed_enum(): void
    {
        $cases = JournalType::cases();
        self::assertNotEmpty($cases);

        // At minimum we expect standard journal types for this domain
        self::assertSame('sale', JournalType::Sale->value);
        self::assertSame('purchase', JournalType::Purchase->value);
        self::assertSame('ar_payment', JournalType::ArPayment->value);
        self::assertSame('ap_payment', JournalType::ApPayment->value);
        self::assertSame('cash_transfer', JournalType::CashTransfer->value);
        self::assertSame('reversal', JournalType::Reversal->value);
    }

    // ────────────────────────────────────────────────────────────────────
    // SourceIdentity: Construction & equality
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function source_identity_constructs_from_tuple(): void
    {
        $id = new SourceIdentity(
            sourceType: 'sale',
            sourceId: 42,
            journalType: JournalType::Sale,
            sourceVersion: 1,
        );

        self::assertSame('sale', $id->sourceType);
        self::assertSame(42, $id->sourceId);
        self::assertSame(JournalType::Sale, $id->journalType);
        self::assertSame(1, $id->sourceVersion);
    }

    #[Test]
    public function source_identity_equality_same_tuple(): void
    {
        $a = new SourceIdentity('sale', 42, JournalType::Sale, 1);
        $b = new SourceIdentity('sale', 42, JournalType::Sale, 1);

        self::assertTrue($a->equals($b));
        self::assertTrue($b->equals($a));
    }

    #[Test]
    public function source_identity_inequality_different_source_type(): void
    {
        $a = new SourceIdentity('sale', 42, JournalType::Sale, 1);
        $b = new SourceIdentity('purchase', 42, JournalType::Sale, 1);

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function source_identity_inequality_different_source_id(): void
    {
        $a = new SourceIdentity('sale', 42, JournalType::Sale, 1);
        $b = new SourceIdentity('sale', 43, JournalType::Sale, 1);

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function source_identity_inequality_different_journal_type(): void
    {
        $a = new SourceIdentity('sale', 42, JournalType::Sale, 1);
        $b = new SourceIdentity('sale', 42, JournalType::Reversal, 1);

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function source_identity_inequality_different_version(): void
    {
        $a = new SourceIdentity('sale', 42, JournalType::Sale, 1);
        $b = new SourceIdentity('sale', 42, JournalType::Sale, 2);

        self::assertFalse($a->equals($b));
    }

    // ────────────────────────────────────────────────────────────────────
    // SourceIdentity: Duplicate detection
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function source_identity_set_detects_duplicate(): void
    {
        $a = new SourceIdentity('sale', 42, JournalType::Sale, 1);
        $b = new SourceIdentity('sale', 42, JournalType::Sale, 1);

        $this->expectException(DuplicateSourceIdentityException::class);

        $set = new \App\Accounting\SourceIdentitySet();
        $set->add($a);
        $set->add($b); // duplicate
    }

    #[Test]
    public function source_identity_set_allows_distinct(): void
    {
        $a = new SourceIdentity('sale', 42, JournalType::Sale, 1);
        $b = new SourceIdentity('purchase', 42, JournalType::Purchase, 1);
        $c = new SourceIdentity('sale', 43, JournalType::Sale, 1);

        $set = new \App\Accounting\SourceIdentitySet();
        $set->add($a);
        $set->add($b);
        $set->add($c);

        self::assertSame(3, $set->count());
    }

    #[Test]
    public function source_identity_set_contains(): void
    {
        $a = new SourceIdentity('sale', 42, JournalType::Sale, 1);
        $b = new SourceIdentity('sale', 42, JournalType::Sale, 1);

        $set = new \App\Accounting\SourceIdentitySet();
        $set->add($a);

        self::assertTrue($set->contains($b));
    }

    // ────────────────────────────────────────────────────────────────────
    // SourceIdentity: Validation
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function source_identity_rejects_empty_source_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SourceIdentity('', 42, JournalType::Sale, 1);
    }

    #[Test]
    public function source_identity_rejects_non_positive_source_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SourceIdentity('sale', 0, JournalType::Sale, 1);
    }

    #[Test]
    public function source_identity_rejects_non_positive_version(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SourceIdentity('sale', 42, JournalType::Sale, 0);
    }

    // ────────────────────────────────────────────────────────────────────
    // IdempotencyKey: Deterministic from source identity
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function idempotency_key_is_deterministic(): void
    {
        $a = new SourceIdentity('sale', 42, JournalType::Sale, 1);
        $b = new SourceIdentity('sale', 42, JournalType::Sale, 1);

        $keyA = IdempotencyKey::fromSourceIdentity($a);
        $keyB = IdempotencyKey::fromSourceIdentity($b);

        self::assertSame($keyA->value(), $keyB->value());
    }

    #[Test]
    public function idempotency_key_differs_for_different_sources(): void
    {
        $a = new SourceIdentity('sale', 42, JournalType::Sale, 1);
        $b = new SourceIdentity('sale', 43, JournalType::Sale, 1);

        $keyA = IdempotencyKey::fromSourceIdentity($a);
        $keyB = IdempotencyKey::fromSourceIdentity($b);

        self::assertNotSame($keyA->value(), $keyB->value());
    }

    #[Test]
    public function idempotency_key_differs_for_different_versions(): void
    {
        $a = new SourceIdentity('sale', 42, JournalType::Sale, 1);
        $b = new SourceIdentity('sale', 42, JournalType::Sale, 2);

        $keyA = IdempotencyKey::fromSourceIdentity($a);
        $keyB = IdempotencyKey::fromSourceIdentity($b);

        self::assertNotSame($keyA->value(), $keyB->value());
    }

    #[Test]
    public function idempotency_key_value_is_non_empty_string(): void
    {
        $source = new SourceIdentity('sale', 42, JournalType::Sale, 1);
        $key = IdempotencyKey::fromSourceIdentity($source);

        self::assertNotEmpty($key->value());
        self::assertIsString($key->value());
    }

    // ────────────────────────────────────────────────────────────────────
    // LineOrder: Explicit deterministic sequence
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function line_order_constructs_from_positive_integer(): void
    {
        $order = new LineOrder(1);

        self::assertSame(1, $order->value);
    }

    #[Test]
    public function line_order_rejects_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LineOrder(0);
    }

    #[Test]
    public function line_order_rejects_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LineOrder(-1);
    }

    #[Test]
    public function line_order_sorts_deterministically(): void
    {
        $orders = [
            new LineOrder(3),
            new LineOrder(1),
            new LineOrder(2),
        ];

        usort($orders, static fn (LineOrder $a, LineOrder $b): int => $a->compareTo($b));

        self::assertSame([1, 2, 3], array_map(fn (LineOrder $o): int => $o->value, $orders));
    }

    #[Test]
    public function line_order_equality(): void
    {
        $a = new LineOrder(5);
        $b = new LineOrder(5);
        $c = new LineOrder(6);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    #[Test]
    public function line_order_comparison(): void
    {
        $a = new LineOrder(1);
        $b = new LineOrder(2);

        self::assertTrue($a->lessThan($b));
        self::assertFalse($b->lessThan($a));
        self::assertSame(-1, $a->compareTo($b));
        self::assertSame(1, $b->compareTo($a));
        self::assertSame(0, $a->compareTo(new LineOrder(1)));
    }

    // ────────────────────────────────────────────────────────────────────
    // Integration: Money arithmetic consistency with minor units
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function money_arithmetic_is_consistent_between_decimal_and_minor_units(): void
    {
        // Verify that decimal-string arithmetic and minor-unit arithmetic
        // produce identical results - the key invariant of exact money.
        $a = Money::fromDecimalString('123456789.12');
        $b = Money::fromDecimalString('0.89');

        $sumDecimal = Money::fromDecimalString(
            $a->toDecimalString()
        )->add(Money::fromDecimalString($b->toDecimalString()));

        $sumMinor = Money::fromMinorUnits(
            $a->toMinorUnits() + $b->toMinorUnits()
        );

        self::assertSame($sumDecimal->toDecimalString(), $sumMinor->toDecimalString());
        self::assertSame($sumDecimal->toMinorUnits(), $sumMinor->toMinorUnits());
    }

    #[Test]
    public function money_does_not_use_float_internally(): void
    {
        // Classic float trap: 0.1 + 0.2 != 0.3 in IEEE 754
        // Our Money must produce exactly 0.30
        $a = Money::fromDecimalString('0.10');
        $b = Money::fromDecimalString('0.20');
        $sum = $a->add($b);

        self::assertSame('0.30', $sum->toDecimalString());
        self::assertSame(30, $sum->toMinorUnits());
    }
}
