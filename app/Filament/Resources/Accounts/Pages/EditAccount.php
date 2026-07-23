<?php

declare(strict_types=1);

namespace App\Filament\Resources\Accounts\Pages;

use App\Accounting\DomainException;
use App\Filament\Resources\Accounts\AccountResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class EditAccount extends EditRecord
{
    protected static string $resource = AccountResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['data.parent_id' => $exception->getMessage()]);
        }
    }
}
