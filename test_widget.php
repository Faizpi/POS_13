<?php

use App\Filament\Widgets\MenungguApproval;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

try {
    $widget = app(MenungguApproval::class);
    // Render the widget view or at least call getTableRecords()
    $records = $widget->getTableRecords();
    echo 'Records loaded successfully. Count: '.count($records)."\n";
    // Now let's try to get the table
    $table = $widget->getTable();
    // Simulate getting records from the table
    $tableRecords = $table->getRecords();
    echo 'Table records fetched. Count: '.count($tableRecords)."\n";
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n".$e->getTraceAsString();
}
