<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

trait UsesMariaDbTestConnection
{
    protected function setUpMariaDbTestConnection(): void
    {
        config([
            'database.connections.mariadb_test' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => 'akuntan_hibiscusefsya_test',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ],
        ]);

        DB::setDefaultConnection('mariadb_test');
        DB::purge('mariadb_test');
        DB::reconnect('mariadb_test');

        $database = DB::connection('mariadb_test')->getDatabaseName();
        if ($database !== 'akuntan_hibiscusefsya_test') {
            throw new \RuntimeException("Database safety check failed: expected 'akuntan_hibiscusefsya_test', got '{$database}'");
        }
    }

    protected function cleanupMariaDbTestData(): void
    {
        DB::connection('mariadb_test')->transaction(function () {
            DB::connection('mariadb_test')->table('journal_lines')->truncate();
            DB::connection('mariadb_test')->table('journal_entries')->truncate();
            DB::connection('mariadb_test')->table('journal_source_locks')->truncate();
            DB::connection('mariadb_test')->table('journal_posting_sequences')->truncate();
            DB::connection('mariadb_test')->table('account_mappings')->truncate();
            DB::connection('mariadb_test')->table('account_mapping_key_locks')->truncate();
            DB::connection('mariadb_test')->table('accounts')->truncate();
        });
    }
}
