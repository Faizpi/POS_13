<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Concerns\ReportAccess;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class BukuBesarPage extends Page
{
    use ReportAccess;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Buku Besar';

    protected static ?string $title = 'Buku Besar';

    protected static ?string $slug = 'buku-besar';

    protected string $view = 'filament.pages.reports.placeholder';
}
