<?php

namespace App\Filament\Resources\PenerimaanBarangs\Pages;

use App\Filament\Resources\PenerimaanBarangs\PenerimaanBarangResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPenerimaanBarangs extends ListRecords
{
    protected static string $resource = PenerimaanBarangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Penerimaan')
                ->visible(fn () => ! auth()->user()?->isSpectator()),
        ];
    }

    public function getTabs(): array
    {
        $query = PenerimaanBarangResource::getEloquentQuery();

        return [
            'semua' => Tab::make('Semua')->badge($query->count()),
            'pending' => Tab::make('Pending')->badge($query->clone()->where('status', 'Pending')->count())->badgeColor('warning')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'Pending')),
            'approved' => Tab::make('Approved')->badge($query->clone()->where('status', 'Approved')->count())->badgeColor('primary')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'Approved')),
            'rejected' => Tab::make('Rejected')->badge($query->clone()->where('status', 'Rejected')->count())->badgeColor('danger')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'Rejected')),
            'canceled' => Tab::make('Canceled')->badge($query->clone()->where('status', 'Canceled')->count())->badgeColor('gray')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'Canceled')),
        ];
    }
}
