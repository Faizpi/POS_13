<?php

namespace App\Providers;

use App\Accounting\JournalPostingCheckpoint;
use App\Accounting\NullJournalPostingCheckpoint;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(JournalPostingCheckpoint::class, NullJournalPostingCheckpoint::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
