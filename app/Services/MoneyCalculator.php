<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

abstract class MoneyCalculator
{
    private const MONEY_SCALE = 2;

    private const QUANTITY_SCALE = 4;

    private const RATE_SCALE = 4;

    private const MONEY_FACTOR = 100;

    private const QUANTITY_FACTOR = 10000;

    private const RATE_FACTOR = 10000;

    private const PERCENT_DIVISOR = 1000000;

    /**
     * Calculate one line total as: quantity * unit price - percentage discount - nominal discount.
     */
    public function calculateLineTotal(
        mixed $quantity,
        mixed $unitPrice,
        mixed $discountPercentage = 0,
        mixed $discountNominal = 0,
    ): string {
        $quantityScaled = $this->quantityToScaledInt($quantity, 'kuantitas');
        $unitPriceCents = $this->moneyToCents($unitPrice, 'harga_satuan');
        $discountBasisPoints = $this->rateToBasisPoints($discountPercentage, 'diskon');
        $discountNominalCents = $this->moneyToCents($discountNominal, 'diskon_nominal');

        return $this->formatCents($this->calculateLineTotalCents(
            $quantityScaled,
            $unitPriceCents,
            $discountBasisPoints,
            $discountNominalCents,
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function calculateSubtotal(array $items, ?string $priceType = null): string
    {
        $subtotalCents = 0;

        foreach ($items as $item) {
            $subtotalCents += $this->normalizeLineItem($item, $priceType)['line_total_cents'];
        }

        return $this->formatCents($subtotalCents);
    }

    public function calculateTax(mixed $taxableAmount, mixed $taxPercentage): string
    {
        $taxableCents = $this->moneyToCents($taxableAmount, 'taxable_total');
        $taxBasisPoints = $this->rateToBasisPoints($taxPercentage, 'tax_percentage');

        return $this->formatCents($this->percentageOfCents($taxableCents, $taxBasisPoints));
    }

    public function calculateShipping(mixed $shippingCost = 0): string
    {
        return $this->formatCents($this->moneyToCents($shippingCost, 'biaya_pengiriman'));
    }

    public function calculateGrandTotal(
        mixed $subtotal,
        mixed $finalDiscount = 0,
        mixed $taxPercentage = 0,
        mixed $shippingCost = 0,
    ): string {
        return $this->calculateHeaderTotals(
            $this->moneyToCents($subtotal, 'subtotal'),
            $finalDiscount,
            $taxPercentage,
            $shippingCost,
        )['grand_total'];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     subtotal: string,
     *     diskon_akhir: string,
     *     final_discount: string,
     *     taxable_total: string,
     *     tax_percentage: string,
     *     tax_total: string,
     *     biaya_pengiriman: string,
     *     shipping: string,
     *     grand_total: string
     * }
     */
    public function calculateTotals(
        array $items,
        mixed $finalDiscount = 0,
        mixed $taxPercentage = 0,
        mixed $shippingCost = 0,
        ?string $priceType = null,
    ): array {
        $normalizedItems = [];
        $subtotalCents = 0;

        foreach ($items as $item) {
            $normalizedItem = $this->normalizeLineItem($item, $priceType);
            $subtotalCents += $normalizedItem['line_total_cents'];
            unset($normalizedItem['line_total_cents']);
            $normalizedItems[] = $normalizedItem;
        }

        return [
            'items' => $normalizedItems,
            ...$this->calculateHeaderTotals($subtotalCents, $finalDiscount, $taxPercentage, $shippingCost),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function normalizeLineItem(array $item, ?string $priceType): array
    {
        $quantityScaled = $this->quantityToScaledInt($item['kuantitas'] ?? $item['quantity'] ?? 0, 'kuantitas');
        $unitPriceCents = $this->moneyToCents($this->resolveUnitPrice($item, $priceType), 'harga_satuan');
        $discountBasisPoints = $this->rateToBasisPoints($item['diskon'] ?? $item['discount_percentage'] ?? 0, 'diskon');
        $discountNominalCents = $this->moneyToCents($item['diskon_nominal'] ?? $item['discount_nominal'] ?? 0, 'diskon_nominal');
        $grossCents = $this->multiplyMoneyByQuantity($unitPriceCents, $quantityScaled);
        $lineTotalCents = $this->calculateLineTotalCents(
            $quantityScaled,
            $unitPriceCents,
            $discountBasisPoints,
            $discountNominalCents,
        );

        return [
            'produk_id' => $item['produk_id'] ?? $item['product_id'] ?? null,
            'deskripsi' => $item['deskripsi'] ?? $item['description'] ?? null,
            'kuantitas' => $this->formatScaledDecimal($quantityScaled, self::QUANTITY_SCALE),
            'unit' => $item['unit'] ?? null,
            'harga_satuan' => $this->formatCents($unitPriceCents),
            'diskon' => $this->formatScaledDecimal($discountBasisPoints, self::RATE_SCALE),
            'diskon_nominal' => $this->formatCents($discountNominalCents),
            'gross_total' => $this->formatCents($grossCents),
            'discount_total' => $this->formatCents($grossCents - $lineTotalCents),
            'jumlah_baris' => $this->formatCents($lineTotalCents),
            'line_total' => $this->formatCents($lineTotalCents),
            'line_total_cents' => $lineTotalCents,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function resolveUnitPrice(array $item, ?string $priceType): mixed
    {
        foreach (['harga_satuan', 'unit_price', 'price', 'harga'] as $key) {
            if (array_key_exists($key, $item)) {
                return $item[$key];
            }
        }

        throw new InvalidArgumentException('Harga satuan wajib diisi.');
    }

    /**
     * @return array{
     *     subtotal: string,
     *     diskon_akhir: string,
     *     final_discount: string,
     *     taxable_total: string,
     *     tax_percentage: string,
     *     tax_total: string,
     *     biaya_pengiriman: string,
     *     shipping: string,
     *     grand_total: string
     * }
     */
    private function calculateHeaderTotals(
        int $subtotalCents,
        mixed $finalDiscount,
        mixed $taxPercentage,
        mixed $shippingCost,
    ): array {
        $finalDiscountCents = $this->moneyToCents($finalDiscount, 'diskon_akhir');

        if ($finalDiscountCents > $subtotalCents) {
            throw new InvalidArgumentException('Diskon akhir tidak boleh lebih besar dari subtotal.');
        }

        $taxBasisPoints = $this->rateToBasisPoints($taxPercentage, 'tax_percentage');
        $shippingCents = $this->moneyToCents($shippingCost, 'biaya_pengiriman');
        $taxableCents = $subtotalCents - $finalDiscountCents;
        $taxCents = $this->percentageOfCents($taxableCents, $taxBasisPoints);
        $grandTotalCents = $taxableCents + $taxCents + $shippingCents;

        return [
            'subtotal' => $this->formatCents($subtotalCents),
            'diskon_akhir' => $this->formatCents($finalDiscountCents),
            'final_discount' => $this->formatCents($finalDiscountCents),
            'taxable_total' => $this->formatCents($taxableCents),
            'tax_percentage' => $this->formatScaledDecimal($taxBasisPoints, self::RATE_SCALE),
            'tax_total' => $this->formatCents($taxCents),
            'biaya_pengiriman' => $this->formatCents($shippingCents),
            'shipping' => $this->formatCents($shippingCents),
            'grand_total' => $this->formatCents($grandTotalCents),
        ];
    }

    private function calculateLineTotalCents(
        int $quantityScaled,
        int $unitPriceCents,
        int $discountBasisPoints,
        int $discountNominalCents,
    ): int {
        $grossCents = $this->multiplyMoneyByQuantity($unitPriceCents, $quantityScaled);
        $percentageDiscountCents = $this->percentageOfCents($grossCents, $discountBasisPoints);
        $lineTotalCents = $grossCents - $percentageDiscountCents - $discountNominalCents;

        if ($lineTotalCents < 0) {
            throw new InvalidArgumentException('Diskon item tidak boleh lebih besar dari total baris.');
        }

        return $lineTotalCents;
    }

    private function multiplyMoneyByQuantity(int $unitPriceCents, int $quantityScaled): int
    {
        return $this->roundDivide($unitPriceCents * $quantityScaled, self::QUANTITY_FACTOR);
    }

    private function percentageOfCents(int $amountCents, int $rateBasisPoints): int
    {
        return $this->roundDivide($amountCents * $rateBasisPoints, self::PERCENT_DIVISOR);
    }

    private function moneyToCents(mixed $value, string $field): int
    {
        return $this->assertNonNegative(
            $this->decimalToScaledInt($value, self::MONEY_SCALE, $field),
            $field,
        );
    }

    private function quantityToScaledInt(mixed $value, string $field): int
    {
        return $this->assertNonNegative(
            $this->decimalToScaledInt($value, self::QUANTITY_SCALE, $field),
            $field,
        );
    }

    private function rateToBasisPoints(mixed $value, string $field): int
    {
        $basisPoints = $this->assertNonNegative(
            $this->decimalToScaledInt($value, self::RATE_SCALE, $field),
            $field,
        );

        if ($basisPoints > 100 * self::RATE_FACTOR) {
            throw new InvalidArgumentException("{$field} tidak boleh lebih dari 100%.");
        }

        return $basisPoints;
    }

    private function assertNonNegative(int $value, string $field): int
    {
        if ($value < 0) {
            throw new InvalidArgumentException("{$field} tidak boleh negatif.");
        }

        return $value;
    }

    private function decimalToScaledInt(mixed $value, int $scale, string $field): int
    {
        $normalized = $this->normalizeDecimalString($value, $field);
        $negative = str_starts_with($normalized, '-');

        if ($negative) {
            $normalized = substr($normalized, 1);
        }

        [$integerPart, $fractionalPart] = array_pad(explode('.', $normalized, 2), 2, '');
        $fractionalPart = str_pad($fractionalPart, $scale + 1, '0');
        $scaledFraction = substr($fractionalPart, 0, $scale);
        $roundDigit = (int) $fractionalPart[$scale];
        $factor = 10 ** $scale;
        $scaled = ((int) $integerPart * $factor) + (int) $scaledFraction;

        if ($roundDigit >= 5) {
            $scaled++;
        }

        return $negative ? -$scaled : $scaled;
    }

    private function normalizeDecimalString(mixed $value, string $field): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            throw new InvalidArgumentException("{$field} tidak boleh berupa float.");
        }

        $normalized = trim((string) $value);
        $normalized = str_replace(['Rp', 'RP', 'rp', ' ', "\xc2\xa0"], '', $normalized);

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = str_replace(',', '', $normalized);
        }

        if (! preg_match('/^-?\d+(\.\d+)?$/', $normalized)) {
            throw new InvalidArgumentException("{$field} harus berupa angka valid.");
        }

        return $normalized;
    }

    private function roundDivide(int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            throw new InvalidArgumentException('Pembagi harus lebih besar dari 0.');
        }

        if ($numerator < 0) {
            return -$this->roundDivide(abs($numerator), $denominator);
        }

        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }

    private function formatCents(int $cents): string
    {
        return $this->formatScaledDecimal($cents, self::MONEY_SCALE);
    }

    private function formatScaledDecimal(int $value, int $scale): string
    {
        $negative = $value < 0;
        $absolute = abs($value);
        $factor = 10 ** $scale;
        $integer = intdiv($absolute, $factor);
        $fraction = $absolute % $factor;

        return ($negative ? '-' : '').$integer.'.'.str_pad((string) $fraction, $scale, '0', STR_PAD_LEFT);
    }
}
