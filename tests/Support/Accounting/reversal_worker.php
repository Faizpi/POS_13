<?php

declare(strict_types=1);

namespace Tests\Support\Accounting;

use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\JournalReversalService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';

$app = require_once __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$barrierDir = $argv[1] ?? throw new \RuntimeException('Missing barrierDir argument');
$workerId = $argv[2] ?? throw new \RuntimeException('Missing workerId argument');
$userId = (int) ($argv[3] ?? throw new \RuntimeException('Missing userId argument'));
$journalId = (int) ($argv[4] ?? throw new \RuntimeException('Missing journalId argument'));
$reason = $argv[5] ?? throw new \RuntimeException('Missing reason argument');

// Override default connection BEFORE resolving any models/services
config([
    'database.default' => 'mariadb',
    'database.connections.mariadb' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'akuntan_hibiscusefsya_test',
        'username' => 'root',
        'password' => '',
        'unix_socket' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ],
]);
DB::purge('mariadb');
DB::reconnect('mariadb');
DB::setDefaultConnection('mariadb');

// Safety assertion: verify we're on the disposable test database
$dbName = DB::connection('mariadb')->selectOne('SELECT DATABASE() as db')->db;
if ($dbName !== 'akuntan_hibiscusefsya_test') {
    throw new \RuntimeException("Worker DB safety check failed: expected 'akuntan_hibiscusefsya_test', got '{$dbName}'");
}

$user = User::on('mariadb')->findOrFail($userId);
$journal = JournalEntry::on('mariadb')->findOrFail($journalId);

// Signal readiness, then wait on the shared start barrier so both workers race together.
file_put_contents("{$barrierDir}/{$workerId}_ready", '1');
$startFile = "{$barrierDir}/start";
$deadline = microtime(true) + 10;
while (! file_exists($startFile)) {
    if (microtime(true) > $deadline) {
        throw new \RuntimeException("Worker {$workerId} timed out waiting for start barrier");
    }
    usleep(2000);
}

try {
    $reversal = $app->make(JournalReversalService::class)->reverse($user, $journal, $reason);
    $result = [
        'success' => true,
        'reversal_id' => $reversal->id,
        'original_journal_entry_id' => $reversal->original_journal_entry_id,
        'status' => $reversal->status->value,
    ];
} catch (\Throwable $e) {
    $result = [
        'success' => false,
        'error' => $e->getMessage(),
        'class' => get_class($e),
    ];
}

file_put_contents("{$barrierDir}/{$workerId}_result.json", json_encode($result, JSON_PRETTY_PRINT));
