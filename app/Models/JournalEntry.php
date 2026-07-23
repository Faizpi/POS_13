<?php

declare(strict_types=1);

namespace App\Models;

use App\Accounting\DomainException;
use App\Accounting\JournalStatus;
use App\Accounting\JournalType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'original_journal_entry_id', 'source_type', 'source_id', 'journal_type', 'source_version', 'journal_date',
        'journal_number', 'posting_sequence', 'gudang_id', 'contact_type', 'contact_id',
        'description', 'reversal_reason', 'reversed_by', 'status', 'total_debit', 'total_credit',
    ];

    protected function casts(): array
    {
        return [
            'original_journal_entry_id' => 'integer',
            'source_id' => 'integer',
            'journal_type' => JournalType::class,
            'source_version' => 'integer',
            'journal_date' => 'immutable_date',
            'posting_sequence' => 'integer',
            'gudang_id' => 'integer',
            'contact_id' => 'integer',
            'reversed_by' => 'integer',
            'status' => JournalStatus::class,
            'total_debit' => 'decimal:2',
            'total_credit' => 'decimal:2',
        ];
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function originalJournal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_journal_entry_id');
    }

    public function reversalJournal(): HasOne
    {
        return $this->hasOne(self::class, 'original_journal_entry_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function (self $journal): void {
            $currentStatus = JournalStatus::from($journal->getRawOriginal('status'));
            $nextStatus = $journal->status;

            if ($journal->isDirty('status')) {
                $currentStatus->transitionTo($nextStatus);
            }

            if (in_array($currentStatus, [JournalStatus::Posted, JournalStatus::Reversed], true)
                && array_diff(array_keys($journal->getDirty()), ['status', 'updated_at']) !== []) {
                throw new DomainException('Posted or reversed journals are immutable.');
            }
        });

        static::deleting(function (self $journal): void {
            if (in_array($journal->getRawOriginal('status'), [JournalStatus::Posted->value, JournalStatus::Reversed->value], true)) {
                throw new DomainException('Posted or reversed journals cannot be deleted.');
            }
        });
    }
}
