<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class FixMissingTablesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_repairs_a_missing_job_batches_table_without_recreating_existing_token_table(): void
    {
        $this->assertTrue(Schema::hasTable('personal_access_tokens'));

        Schema::dropIfExists('job_batches');
        $this->assertFalse(Schema::hasTable('job_batches'));

        $this->artisan('fix-tables')
            ->expectsOutputToContain('job_batches')
            ->assertSuccessful();

        $this->assertTrue(Schema::hasTable('personal_access_tokens'));
        $this->assertTrue(Schema::hasTable('job_batches'));
    }
}
