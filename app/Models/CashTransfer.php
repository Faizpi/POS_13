<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_number', 'source_cash_bank_account_id', 'destination_cash_bank_account_id',
        'mode', 'status', 'amount', 'memo', 'initiated_by', 'posted_by',
    ];

    protected function casts(): array
    {
        return [
            'source_cash_bank_account_id' => 'integer',
            'destination_cash_bank_account_id' => 'integer',
            'initiated_by' => 'integer',
            'posted_by' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function sourceCashBankAccount(): BelongsTo
    {
        return $this->belongsTo(CashBankAccount::class, 'source_cash_bank_account_id');
    }

    public function destinationCashBankAccount(): BelongsTo
    {
        return $this->belongsTo(CashBankAccount::class, 'destination_cash_bank_account_id');
    }

    public function journals(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'source_id')
            ->where('source_type', 'cash_transfer')
            ->where('journal_type', 'cash_transfer');
    }
}
