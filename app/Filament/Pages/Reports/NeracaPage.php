<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Concerns\ReportAccess;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class NeracaPage extends Page
{
    use ReportAccess;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Neraca';

    protected static ?string $title = 'Neraca';

    protected static ?string $slug = 'neraca';

    protected string $view = 'filament.pages.reports.placeholder';
}
