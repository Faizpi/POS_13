<?php

declare(strict_types=1);

namespace App\Models;

use App\Accounting\AccountCategory;
use App\Accounting\CashAccountType;
use App\Accounting\DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashBankAccount extends Model
{
    use HasFactory;

    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'name',
        'type',
        'account_id',
        'gudang_id',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CashAccountType::class,
            'is_active' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $cashBankAccount): void {
            $cashBankAccount->validateCompatibleAccount();
            $cashBankAccount->validateActiveGudang();
        });
    }

    private function validateCompatibleAccount(): void
    {
        $account = Account::query()->find($this->account_id);

        if ($account === null) {
            return;
        }

        if (! $account->is_active) {
            throw new DomainException('Cash/bank account must use an active COA account.');
        }

        if (! $account->isPostable()) {
            throw new DomainException('Cash/bank account must use a postable COA account.');
        }

        $type = $this->type instanceof CashAccountType
            ? $this->type
            : CashAccountType::from((string) $this->type);

        if ($account->category !== AccountCategory::Aset || $account->subcategory !== $type->value) {
            throw new DomainException('Cash/bank account must use a compatible Aset/Kas-Bank COA account.');
        }
    }

    private function validateActiveGudang(): void
    {
        if ($this->gudang_id === null) {
            return;
        }

        $gudang = Gudang::query()->find($this->gudang_id);

        if ($gudang !== null && ! $gudang->is_active) {
            throw new DomainException('Cash/bank account cannot use an inactive warehouse.');
        }
    }
}
