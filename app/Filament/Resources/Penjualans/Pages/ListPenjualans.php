<?php

namespace App\Filament\Resources\Penjualans\Pages;

use App\Filament\Resources\Penjualans\PenjualanResource;
use App\Filament\Widgets\PenjualanStatsWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPenjualans extends ListRecords
{
    protected static string $resource = PenjualanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Penagihan Baru')
                ->visible(fn () => ! auth()->user()?->isSpectator()),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PenjualanStatsWidget::class,
        ];
    }

    public function getTabs(): array
    {
        $baseQuery = PenjualanResource::getEloquentQuery();

        // Single GROUP BY query instead of 6 separate COUNT queries
        $statusCounts = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $total = array_sum($statusCounts);

        return [
            'semua' => Tab::make('Semua')
                ->badge($total),

            'pending' => Tab::make('Pending')
                ->badge($statusCounts['Pending'] ?? 0)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'Pending')),

            'approved' => Tab::make('Approved')
                ->badge($statusCounts['Approved'] ?? 0)
                ->badgeColor('primary')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'Approved')),

            'lunas' => Tab::make('Lunas')
                ->badge($statusCounts['Lunas'] ?? 0)
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'Lunas')),

            'rejected' => Tab::make('Rejected')
                ->badge($statusCounts['Rejected'] ?? 0)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'Rejected')),

            'canceled' => Tab::make('Canceled')
                ->badge($statusCounts['Canceled'] ?? 0)
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'Canceled')),
        ];
    }
}
