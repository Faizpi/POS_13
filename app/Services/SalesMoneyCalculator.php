<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class SalesMoneyCalculator extends MoneyCalculator
{
    /**
     * @param  array<string, mixed>  $item
     */
    protected function resolveUnitPrice(array $item, ?string $priceType): mixed
    {
        foreach (['harga_satuan', 'unit_price', 'price'] as $key) {
            if (array_key_exists($key, $item)) {
                return $item[$key];
            }
        }

        if ($priceType === 'grosir' && array_key_exists('harga_grosir', $item) && $item['harga_grosir'] !== null && $item['harga_grosir'] !== '') {
            return $item['harga_grosir'];
        }

        if (array_key_exists('harga_retail', $item)) {
            return $item['harga_retail'];
        }

        if (array_key_exists('harga', $item)) {
            return $item['harga'];
        }

        throw new InvalidArgumentException('Harga satuan penjualan wajib diisi.');
    }
}
