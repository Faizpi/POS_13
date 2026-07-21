<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Concerns\ReportAccess;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class JurnalPage extends Page
{
    use ReportAccess;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Jurnal';

    protected static ?string $title = 'Jurnal';

    protected static ?string $slug = 'jurnal';

    protected string $view = 'filament.pages.reports.placeholder';
}
