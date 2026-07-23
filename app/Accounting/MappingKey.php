<?php

declare(strict_types=1);

namespace App\Accounting;

enum MappingKey: string
{
    case SalesRetailRevenue = 'sales.retail_revenue';
    case SalesWholesaleRevenue = 'sales.wholesale_revenue';
    case SalesDiscount = 'sales.discount';
    case SalesReturn = 'sales.return';
    case SalesOutputTax = 'sales.output_tax';
    case PurchaseInventory = 'purchase.inventory';
    case PurchaseInputTax = 'purchase.input_tax';
    case ArReceivable = 'ar.receivable';
    case ApPayable = 'ap.payable';
    case InventoryAsset = 'inventory.asset';
    case InventoryDamageExpense = 'inventory.damage_expense';
    case OpeningEquity = 'opening.equity';
    case CashDefault = 'cash.default';
    case BankDefault = 'bank.default';
    case CashInTransit = 'cash.in_transit';
    case CashRounding = 'cash.rounding';
    case ExpenseGeneral = 'expense.general';

    /**
     * @return list<AccountCategory>
     */
    public function compatibleCategories(): array
    {
        return match ($this) {
            self::ArReceivable,
            self::PurchaseInventory,
            self::PurchaseInputTax,
            self::InventoryAsset,
            self::CashDefault,
            self::BankDefault,
            self::CashInTransit => [AccountCategory::Aset],

            self::ApPayable,
            self::SalesOutputTax => [AccountCategory::Kewajiban],

            self::OpeningEquity => [AccountCategory::Ekuitas],

            self::SalesRetailRevenue,
            self::SalesWholesaleRevenue => [AccountCategory::Pendapatan],

            self::SalesDiscount,
            self::SalesReturn,
            self::CashRounding => [AccountCategory::Pendapatan],

            self::InventoryDamageExpense,
            self::ExpenseGeneral => [AccountCategory::Beban],
        };
    }

    /**
     * @throws UnknownMappingKeyException
     */
    public static function fromString(string $value): self
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return $case;
            }
        }

        throw new UnknownMappingKeyException($value);
    }

    public function isRuntimeRequired(): bool
    {
        return in_array($this, self::runtimeRequired(), true);
    }

    /** @return list<self> */
    public static function runtimeRequired(): array
    {
        return [
            self::SalesRetailRevenue,
            self::SalesDiscount,
            self::ArReceivable,
            self::PurchaseInventory,
            self::ApPayable,
            self::CashInTransit,
            self::CashDefault,
            self::BankDefault,
            self::CashRounding,
        ];
    }

    public function formStateKey(): string
    {
        return str_replace('.', '_', $this->value);
    }
}
