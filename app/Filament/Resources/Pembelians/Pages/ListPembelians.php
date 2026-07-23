<?php

namespace App\Filament\Resources\Pembelians\Pages;

use App\Filament\Resources\Pembelians\PembelianResource;
use App\Filament\Widgets\PembelianStatsWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPembelians extends ListRecords
{
    protected static string $resource = PembelianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Permintaan Baru')
                ->visible(fn () => ! auth()->user()?->isSpectator()),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PembelianStatsWidget::class,
        ];
    }

    public function getTabs(): array
    {
        $query = PembelianResource::getEloquentQuery();

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
