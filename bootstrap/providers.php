<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AppPanelProvider;
use App\Providers\Filament\CustomerPanelProvider;

return [
    AppServiceProvider::class,
    AppPanelProvider::class,
    CustomerPanelProvider::class,
];
