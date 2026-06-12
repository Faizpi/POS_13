<?php

namespace App\Filament\Resources\Biayas\Pages;

use App\Filament\Concerns\RenamesLampiran;
use App\Filament\Resources\Biayas\BiayaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBiaya extends EditRecord
{
    use RenamesLampiran;

    protected static string $resource = BiayaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->renameLampiranFiles();
    }
}
