<?php

declare(strict_types=1);

namespace App\Filament\Resources\CashBankAccounts\Pages;

use App\Filament\Resources\CashBankAccounts\CashBankAccountResource;
use Filament\Resources\Pages\EditRecord;

class EditCashBankAccount extends EditRecord
{
    protected static string $resource = CashBankAccountResource::class;
}
