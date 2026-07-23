<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AccountingIntegrityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_integrity_command_reports_a_healthy_empty_ledger(): void
    {
        $this->artisan('accounting:check')
            ->expectsOutputToContain('Accounting integrity check passed')
            ->assertExitCode(0);
    }
}
