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

        return [
            'semua' => Tab::make('Semua')
                ->badge($baseQuery->count()),

            'pending' => Tab::make('Pending')
                ->badge($baseQuery->clone()->where('status', 'Pending')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'Pending')),

            'approved' => Tab::make('Approved')
                ->badge($baseQuery->clone()->where('status', 'Approved')->count())
                ->badgeColor('primary')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'Approved')),

            'lunas' => Tab::make('Lunas')
                ->badge($baseQuery->clone()->where('status', 'Lunas')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'Lunas')),

            'rejected' => Tab::make('Rejected')
                ->badge($baseQuery->clone()->where('status', 'Rejected')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'Rejected')),

            'canceled' => Tab::make('Canceled')
                ->badge($baseQuery->clone()->where('status', 'Canceled')->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'Canceled')),
        ];
    }
}
