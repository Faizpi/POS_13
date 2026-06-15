<?php

namespace App\Filament\Resources\PembayaranHutangs\Pages;

use App\Filament\Resources\PembayaranHutangs\PembayaranHutangResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPembayaranHutang extends EditRecord
{
    protected static string $resource = PembayaranHutangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
