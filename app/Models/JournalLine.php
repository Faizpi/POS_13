<?php

declare(strict_types=1);

namespace App\Models;

use App\Accounting\DomainException;
use App\Accounting\JournalStatus;
use App\Accounting\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class JournalLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id', 'account_id', 'line_sequence', 'gudang_id', 'contact_type',
        'contact_id', 'debit', 'credit', 'description',
    ];

    protected function casts(): array
    {
        return [
            'journal_entry_id' => 'integer',
            'account_id' => 'integer',
            'line_sequence' => 'integer',
            'gudang_id' => 'integer',
            'contact_id' => 'integer',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
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

        static::saving(function (self $line): void {
            $line->validateAmounts();
        });

        static::updating(function (self $line): void {
            $line->guardHistoricalMutation();
        });

        static::deleting(function (self $line): void {
            $line->guardHistoricalMutation();
        });
    }

    private function guardHistoricalMutation(): void
    {
        $status = DB::table('journal_entries')->where('id', $this->journal_entry_id)->value('status');

        if (in_array($status, [JournalStatus::Posted->value, JournalStatus::Reversed->value], true)) {
            throw new DomainException('Historical journal lines are immutable.');
        }
    }

    private function validateAmounts(): void
    {
        $debit = Money::fromDecimalString((string) $this->debit);
        $credit = Money::fromDecimalString((string) $this->credit);

        if ((! $debit->isPositive() && ! $credit->isPositive()) || ($debit->isPositive() && $credit->isPositive())) {
            throw new DomainException('Each journal line must have exactly one positive debit or credit amount.');
        }
    }
}
