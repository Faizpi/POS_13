<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Concerns\ReportAccess;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class PerubahanModalPage extends Page
{
    use ReportAccess;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Perubahan Modal';

    protected static ?string $title = 'Perubahan Modal';

    protected static ?string $slug = 'perubahan-modal';

    protected string $view = 'filament.pages.reports.placeholder';
}
