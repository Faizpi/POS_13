<?php

namespace App\Filament\Customer\Resources\Penjualans\Pages;

use App\Filament\Customer\Resources\Penjualans\PenjualanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPenjualans extends ListRecords
{
    protected static string $resource = PenjualanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
