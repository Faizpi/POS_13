<?php

namespace App\Filament\Resources\PembayaranHutangs\Pages;

use App\Filament\Resources\PembayaranHutangs\PembayaranHutangResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPembayaranHutangs extends ListRecords
{
    protected static string $resource = PembayaranHutangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
