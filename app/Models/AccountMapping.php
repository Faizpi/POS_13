<?php

declare(strict_types=1);

namespace App\Models;

use App\Accounting\AccountMappingException;
use App\Accounting\MappingKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AccountMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'mapping_key',
        'section',
        'account_id',
        'effective_from',
        'effective_to',
        'is_active',
        'is_protected',
        'changed_by',
        'change_reason',
    ];

    protected function casts(): array
    {
        return [
            'mapping_key' => MappingKey::class,
            'effective_from' => 'immutable_date',
            'effective_to' => 'immutable_date',
            'is_active' => 'boolean',
            'is_protected' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    protected static function boot(): void
    {
        parent::boot();

        self::saving(function (self $mapping): void {
            if ($mapping->exists && $mapping->is_protected && $mapping->isDirty('mapping_key')) {
                throw new AccountMappingException('Protected mappings cannot have their stable key changed.');
            }

            if ($mapping->effective_to !== null && $mapping->effective_from->isAfter($mapping->effective_to)) {
                throw new AccountMappingException('Effective from date must be on or before effective to date.');
            }

            $account = Account::query()->find($mapping->account_id);

            if ($account === null || ! $account->is_active || ! $account->isPostable()) {
                throw new AccountMappingException('Account mappings require an active postable account.');
            }

            if (! in_array($account->category, $mapping->mapping_key->compatibleCategories(), true)) {
                throw new AccountMappingException('Account category is not compatible with this mapping key.');
            }

            if ($mapping->is_active && ! $mapping->mapping_key->isRuntimeRequired()) {
                throw new AccountMappingException('Prepared mapping keys cannot be activated in this accounting core release.');
            }
        });

        self::deleting(function (self $mapping): void {
            if ($mapping->is_protected) {
                throw new AccountMappingException('Protected mappings cannot be deleted.');
            }
        });
    }
}
