<?php

namespace App\Filament\Customer\Resources\Kunjungans\Pages;

use App\Filament\Customer\Resources\Kunjungans\KunjunganResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditKunjungan extends EditRecord
{
    protected static string $resource = KunjunganResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
