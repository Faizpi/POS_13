<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $widget = app(\App\Filament\Widgets\MenungguApproval::class);
    // Render the widget view or at least call getTableRecords()
    $records = $widget->getTableRecords();
    echo "Records loaded successfully. Count: " . count($records) . "\n";
    // Now let's try to get the table 
    $table = $widget->getTable();
    // Simulate getting records from the table
    $tableRecords = $table->getRecords();
    echo "Table records fetched. Count: " . count($tableRecords) . "\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
