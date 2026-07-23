<?php

declare(strict_types=1);

namespace App\Filament\Resources\CashBankAccounts\Pages;

use App\Filament\Resources\CashBankAccounts\CashBankAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCashBankAccounts extends ListRecords
{
    protected static string $resource = CashBankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
