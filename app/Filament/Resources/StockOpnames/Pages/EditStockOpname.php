<?php

namespace App\Filament\Resources\StockOpnames\Pages;

use App\Filament\Resources\StockOpnames\StockOpnameResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditStockOpname extends EditRecord
{
    protected static string $resource = StockOpnameResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Block editing of Applied records
        if (($data['status'] ?? '') === 'Applied') {
            Notification::make()
                ->title('Stock opname ini sudah di-apply dan tidak dapat diubah.')
                ->warning()
                ->send();
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        if ($record->status === 'Applied') {
            Notification::make()
                ->title('Stock opname yang sudah Applied tidak dapat diubah.')
                ->danger()
                ->send();

            $this->halt();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn () => auth()->user()?->isSuperAdmin()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return StockOpnameResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
