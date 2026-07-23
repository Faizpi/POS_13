<?php

declare(strict_types=1);

namespace App\Filament\Resources\Accounts\Pages;

use App\Accounting\AccountCategory;
use App\Accounting\AccountCreationOptions;
use App\Accounting\DomainException;
use App\Accounting\NormalBalance;
use App\Accounting\StatementClassification;
use App\Filament\Resources\Accounts\AccountResource;
use App\Models\Account;
use App\Services\Accounting\AccountCodeGenerator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class CreateAccount extends CreateRecord
{
    protected static string $resource = AccountResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $category = AccountCategory::from($data['category']);
        $parent = Account::query()->findOrFail($data['parent_id']);
        $generator = app(AccountCodeGenerator::class);
        $suggestedCode = $generator->suggest($category, $parent);
        $manualCode = $data['code'] === $suggestedCode ? null : $data['code'];

        try {
            return $generator->create(
                auth()->user(),
                $category,
                $parent,
                $data['name'],
                $manualCode,
                new AccountCreationOptions(
                    subcategory: self::nullableString($data['subcategory'] ?? null),
                    normalBalance: NormalBalance::from($data['normal_balance']),
                    statementClassification: StatementClassification::from($data['statement_classification']),
                    cashFlowCategory: self::nullableString($data['cash_flow_category'] ?? null),
                    cashFlowLine: self::nullableString($data['cash_flow_line'] ?? null),
                    isPostable: (bool) $data['is_postable'],
                    isControlAccount: (bool) $data['is_control_account'],
                    isActive: (bool) $data['is_active'],
                    displayOrder: (int) $data['display_order'],
                ),
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['data.code' => $exception->getMessage()]);
        }
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
