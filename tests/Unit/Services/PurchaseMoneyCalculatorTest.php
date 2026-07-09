<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PurchaseMoneyCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PurchaseMoneyCalculatorTest extends TestCase
{
    /** @covers-finding B17 Money calculation precision */
    public function test_line_total_applies_quantity_percent_discount_and_nominal_discount(): void
    {
        $calculator = new PurchaseMoneyCalculator;

        $this->assertSame('450.00', $calculator->calculateLineTotal('5', '100.00', '5', '25.00'));
    }

    public function test_purchase_totals_include_item_discount_final_discount_tax_shipping_and_grand_total(): void
    {
        $calculator = new PurchaseMoneyCalculator;

        $totals = $calculator->calculateTotals([
            [
                'produk_id' => 10,
                'deskripsi' => 'Bahan baku',
                'kuantitas' => '5',
                'unit' => 'pcs',
                'harga_satuan' => '100.00',
                'diskon' => '5',
                'diskon_nominal' => '25.00',
            ],
            [
                'produk_id' => 11,
                'kuantitas' => '2.25',
                'harga_beli' => '20.00',
            ],
        ], finalDiscount: '45.00', taxPercentage: '10', shippingCost: '12.34');

        $this->assertSame('450.00', $totals['items'][0]['jumlah_baris']);
        $this->assertSame('45.00', $totals['items'][1]['jumlah_baris']);
        $this->assertSame('495.00', $totals['subtotal']);
        $this->assertSame('45.00', $totals['diskon_akhir']);
        $this->assertSame('450.00', $totals['taxable_total']);
        $this->assertSame('45.00', $totals['tax_total']);
        $this->assertSame('12.34', $totals['shipping']);
        $this->assertSame('507.34', $totals['grand_total']);
    }

    public function test_zero_quantity_and_zero_price_are_supported(): void
    {
        $calculator = new PurchaseMoneyCalculator;

        $totals = $calculator->calculateTotals([
            ['produk_id' => 1, 'kuantitas' => '0', 'harga_satuan' => '100.00'],
            ['produk_id' => 2, 'kuantitas' => '10', 'harga_satuan' => '0.00'],
        ], shippingCost: '0');

        $this->assertSame('0.00', $totals['subtotal']);
        $this->assertSame('0.00', $totals['grand_total']);
    }

    public function test_negative_final_discount_is_rejected(): void
    {
        $calculator = new PurchaseMoneyCalculator;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('diskon_akhir tidak boleh negatif');

        $calculator->calculateTotals([
            ['produk_id' => 1, 'kuantitas' => 1, 'harga_satuan' => 100],
        ], finalDiscount: '-0.01');
    }

    public function test_discount_percentage_above_one_hundred_is_rejected(): void
    {
        $calculator = new PurchaseMoneyCalculator;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('diskon tidak boleh lebih dari 100%');

        $calculator->calculateLineTotal(1, 100, '100.0001');
    }

    public function test_tax_rounding_is_deterministic_to_two_decimals(): void
    {
        $calculator = new PurchaseMoneyCalculator;

        $totals = $calculator->calculateTotals([
            ['produk_id' => 1, 'kuantitas' => 1, 'harga_satuan' => '0.05'],
        ], taxPercentage: '10');

        $this->assertSame('0.05', $totals['subtotal']);
        $this->assertSame('0.01', $totals['tax_total']);
        $this->assertSame('0.06', $totals['grand_total']);
    }

    public function test_invalid_money_input_is_rejected_instead_of_silently_becoming_zero(): void
    {
        $calculator = new PurchaseMoneyCalculator;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('harga_satuan harus berupa angka valid');

        $calculator->calculateLineTotal(1, 'not-money');
    }

    public function test_float_inputs_are_rejected_at_parser_level(): void
    {
        $calculator = new PurchaseMoneyCalculator;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('harga_satuan tidak boleh berupa float');

        $calculator->calculateLineTotal('1', 100.10);
    }
}
