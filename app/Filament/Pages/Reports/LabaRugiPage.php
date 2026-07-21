<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Concerns\ReportAccess;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class LabaRugiPage extends Page
{
    use ReportAccess;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Laba Rugi';

    protected static ?string $title = 'Laba Rugi';

    protected static ?string $slug = 'laba-rugi';

    protected string $view = 'filament.pages.reports.placeholder';
}
