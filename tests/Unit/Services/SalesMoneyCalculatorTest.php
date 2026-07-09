<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\SalesMoneyCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SalesMoneyCalculatorTest extends TestCase
{
    /** @covers-finding B17 Money calculation precision */
    public function test_line_total_applies_quantity_percent_discount_and_nominal_discount(): void
    {
        $calculator = new SalesMoneyCalculator;

        $this->assertSame('17500.13', $calculator->calculateLineTotal('2', '10000.10', '10', '500.05'));
    }

    public function test_totals_include_subtotal_final_discount_tax_shipping_and_grand_total(): void
    {
        $calculator = new SalesMoneyCalculator;

        $totals = $calculator->calculateTotals([
            [
                'produk_id' => 1,
                'kuantitas' => '2',
                'harga_satuan' => '10000.10',
                'diskon' => '10',
                'diskon_nominal' => '500.05',
            ],
            [
                'produk_id' => 2,
                'kuantitas' => '1.5',
                'harga_satuan' => '2000',
            ],
        ], finalDiscount: '500.13', taxPercentage: '11', shippingCost: '15000.50');

        $this->assertSame('17500.13', $totals['items'][0]['jumlah_baris']);
        $this->assertSame('3000.00', $totals['items'][1]['jumlah_baris']);
        $this->assertSame('20500.13', $totals['subtotal']);
        $this->assertSame('500.13', $totals['diskon_akhir']);
        $this->assertSame('20000.00', $totals['taxable_total']);
        $this->assertSame('2200.00', $totals['tax_total']);
        $this->assertSame('15000.50', $totals['biaya_pengiriman']);
        $this->assertSame('37200.50', $totals['grand_total']);
    }

    public function test_sales_calculator_can_resolve_retail_and_grosir_prices(): void
    {
        $calculator = new SalesMoneyCalculator;

        $retailTotals = $calculator->calculateTotals([
            ['produk_id' => 1, 'kuantitas' => 2, 'harga_retail' => '1500.00', 'harga_grosir' => '1200.00'],
        ], priceType: 'retail');
        $grosirTotals = $calculator->calculateTotals([
            ['produk_id' => 1, 'kuantitas' => 2, 'harga_retail' => '1500.00', 'harga_grosir' => '1200.00'],
        ], priceType: 'grosir');

        $this->assertSame('3000.00', $retailTotals['grand_total']);
        $this->assertSame('2400.00', $grosirTotals['grand_total']);
    }

    public function test_zero_quantity_and_zero_price_return_zero_totals(): void
    {
        $calculator = new SalesMoneyCalculator;

        $totals = $calculator->calculateTotals([
            ['produk_id' => 1, 'kuantitas' => '0', 'harga_satuan' => '9999.99'],
            ['produk_id' => 2, 'kuantitas' => '5', 'harga_satuan' => '0'],
        ]);

        $this->assertSame('0.00', $totals['subtotal']);
        $this->assertSame('0.00', $totals['grand_total']);
    }

    public function test_negative_item_discount_is_rejected(): void
    {
        $calculator = new SalesMoneyCalculator;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('diskon tidak boleh negatif');

        $calculator->calculateLineTotal(1, 100, -1);
    }

    public function test_negative_nominal_discount_is_rejected(): void
    {
        $calculator = new SalesMoneyCalculator;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('diskon_nominal tidak boleh negatif');

        $calculator->calculateLineTotal(1, 100, 0, -1);
    }

    public function test_tax_rounding_uses_half_up_cents_without_float_drift(): void
    {
        $calculator = new SalesMoneyCalculator;

        $this->assertSame('0.01', $calculator->calculateTax('0.05', '10'));
        $this->assertSame('0.30', $calculator->calculateSubtotal([
            ['kuantitas' => 3, 'harga_satuan' => '0.10'],
        ]));
    }

    public function test_final_discount_cannot_exceed_subtotal(): void
    {
        $calculator = new SalesMoneyCalculator;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Diskon akhir tidak boleh lebih besar dari subtotal');

        $calculator->calculateGrandTotal('100.00', '100.01');
    }

    public function test_float_inputs_are_rejected_at_parser_level(): void
    {
        $calculator = new SalesMoneyCalculator;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('harga_satuan tidak boleh berupa float');

        $calculator->calculateLineTotal('1', 100.10);
    }
}
