<?php

declare(strict_types=1);

namespace App\Models;

use App\Accounting\AccountCategory;
use App\Accounting\DomainException;
use App\Accounting\NormalBalance;
use App\Accounting\StatementClassification;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Chart of Accounts model.
 *
 * Persisted fields: code, name, parent_id, category, subcategory,
 * normal_balance, statement_classification, cash_flow_category,
 * cash_flow_line, is_postable, is_control_account, is_system,
 * is_active, is_used, display_order.
 *
 * Domain invariants:
 *  - code is unique (DB constraint)
 *  - no self-parent (model validation)
 *  - no cycles in parent chain (model validation)
 *  - parent category must match child category (model validation)
 *  - system accounts cannot be deleted (model validation)
 *  - used accounts cannot be deleted (model validation)
 *  - accounts with children cannot be deleted (model validation)
 *  - non-postable (heading) accounts cannot be posted to (model check)
 */
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'parent_id',
        'category',
        'subcategory',
        'normal_balance',
        'statement_classification',
        'cash_flow_category',
        'cash_flow_line',
        'is_postable',
        'is_control_account',
        'is_system',
        'is_active',
        'is_used',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'category' => AccountCategory::class,
            'normal_balance' => NormalBalance::class,
            'statement_classification' => StatementClassification::class,
            'is_postable' => 'boolean',
            'is_control_account' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'is_used' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function cashBankAccount(): HasOne
    {
        return $this->hasOne(CashBankAccount::class);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helper methods
    // ─────────────────────────────────────────────────────────────────────

    public function isPostable(): bool
    {
        return (bool) $this->is_postable;
    }

    public function isHeading(): bool
    {
        return ! $this->isPostable();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Boot: domain validation
    // ─────────────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $account): void {
            self::validateNoSelfParent($account);
            self::validateNoCycle($account);
            self::validateCompatibleParent($account);
        });

        static::deleting(function (self $account): void {
            self::guardDelete($account);
        });
    }

    private static function validateNoSelfParent(self $account): void
    {
        if ($account->parent_id !== null && $account->parent_id === $account->id) {
            throw new DomainException('Account cannot be its own parent (self-parent).');
        }
    }

    private static function validateNoCycle(self $account): void
    {
        if ($account->parent_id === null) {
            return;
        }

        $visited = [$account->id];
        $currentId = $account->parent_id;

        while ($currentId !== null) {
            if (in_array($currentId, $visited, true)) {
                throw new DomainException('Account hierarchy cycle detected.');
            }

            $visited[] = $currentId;
            $currentId = self::where('id', $currentId)->value('parent_id');
        }
    }

    private static function validateCompatibleParent(self $account): void
    {
        if ($account->parent_id === null) {
            return;
        }

        $parent = self::find($account->parent_id);

        if ($parent === null) {
            return; // FK handles this
        }

        if ($account->category !== null && $parent->category !== null && $account->category !== $parent->category) {
            throw new DomainException(sprintf(
                'Account category "%s" is not compatible with parent category "%s".',
                $account->category->value,
                $parent->category->value,
            ));
        }
    }

    public static function isControlAccountCompatible(?AccountCategory $category, ?string $subcategory, bool $isPostable): bool
    {
        if (! $isPostable) {
            return false;
        }

        return match ($category) {
            AccountCategory::Aset => in_array($subcategory, ['receivable', 'inventory', 'tax'], true),
            AccountCategory::Kewajiban => in_array($subcategory, ['payable', 'tax'], true),
            default => false,
        };
    }

    private static function guardDelete(self $account): void
    {
        if ($account->is_system) {
            throw new DomainException('Cannot delete system account.');
        }

        if ($account->is_used) {
            throw new DomainException('Cannot delete account that has been used in journal entries.');
        }

        if ($account->children()->exists()) {
            throw new DomainException('Cannot delete account that has children.');
        }
    }
}
