<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Illuminate\Support\Facades\Auth;

trait ReportAccess
{
    public static function canAccess(): bool
    {
        return in_array(Auth::user()?->role, ['super_admin', 'spectator'], true);
    }
}
