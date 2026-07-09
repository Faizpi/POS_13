<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class PurchaseMoneyCalculator extends MoneyCalculator
{
    /**
     * @param  array<string, mixed>  $item
     */
    protected function resolveUnitPrice(array $item, ?string $priceType): mixed
    {
        foreach (['harga_satuan', 'unit_price', 'price', 'harga_beli', 'harga'] as $key) {
            if (array_key_exists($key, $item)) {
                return $item[$key];
            }
        }

        throw new InvalidArgumentException('Harga satuan pembelian wajib diisi.');
    }
}
