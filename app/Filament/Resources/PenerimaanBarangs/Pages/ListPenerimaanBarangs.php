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

        $statusCounts = (clone $query)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $total = array_sum($statusCounts);

        return [
            'semua' => Tab::make('Semua')->badge($total),
            'pending' => Tab::make('Pending')->badge($statusCounts['Pending'] ?? 0)->badgeColor('warning')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'Pending')),
            'approved' => Tab::make('Approved')->badge($statusCounts['Approved'] ?? 0)->badgeColor('primary')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'Approved')),
            'rejected' => Tab::make('Rejected')->badge($statusCounts['Rejected'] ?? 0)->badgeColor('danger')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'Rejected')),
            'canceled' => Tab::make('Canceled')->badge($statusCounts['Canceled'] ?? 0)->badgeColor('gray')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'Canceled')),
        ];
    }
}
