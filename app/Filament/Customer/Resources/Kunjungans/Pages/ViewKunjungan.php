<?php

namespace App\Filament\Customer\Resources\Kunjungans\Pages;

use App\Filament\Customer\Resources\Kunjungans\KunjunganResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewKunjungan extends ViewRecord
{
    protected static string $resource = KunjunganResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
