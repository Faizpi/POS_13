<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Concerns\ReportAccess;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class ArusKasPage extends Page
{
    use ReportAccess;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Arus Kas';

    protected static ?string $title = 'Arus Kas';

    protected static ?string $slug = 'arus-kas';

    protected string $view = 'filament.pages.reports.placeholder';
}
