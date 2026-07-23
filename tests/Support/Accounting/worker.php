<?php

declare(strict_types=1);

namespace Tests\Support\Accounting;

use App\Accounting\JournalPostingCheckpoint;
use App\Accounting\JournalPostingRequest;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';

$app = require_once __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$barrierDir = $argv[1] ?? throw new \RuntimeException('Missing barrierDir argument');
$workerId = $argv[2] ?? throw new \RuntimeException('Missing workerId argument');
$userId = (int) ($argv[3] ?? throw new \RuntimeException('Missing userId argument'));
$requestJson = $argv[4] ?? throw new \RuntimeException('Missing requestJson argument');

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

// Safety assertion: verify we're on the test database
$dbName = DB::connection('mariadb')->selectOne('SELECT DATABASE() as db')->db;
if ($dbName !== 'akuntan_hibiscusefsya_test') {
    throw new \RuntimeException("Worker DB safety check failed: expected 'akuntan_hibiscusefsya_test', got '{$dbName}'");
}

// Emit CONNECTION_ID marker immediately
$connectionId = (int) DB::connection('mariadb')->selectOne('SELECT CONNECTION_ID() as id')->id;
$connectionIdFile = "{$barrierDir}/{$workerId}_connection_id";
file_put_contents($connectionIdFile, (string) $connectionId);

$app->bind(JournalPostingCheckpoint::class, fn () => new FileBarrierCheckpoint($barrierDir, $workerId));

$user = User::on('mariadb')->findOrFail($userId);
$request = unserialize(base64_decode($requestJson));

if (! $request instanceof JournalPostingRequest) {
    throw new \RuntimeException('Invalid request deserialization');
}

try {
    $service = $app->make(JournalPostingService::class);
    $journal = $service->post($user, $request);

    $connectionUsable = (int) DB::connection('mariadb')->selectOne('SELECT 1 AS value')->value === 1;
    $result = [
        'success' => true,
        'journal_id' => $journal->id,
        'journal_number' => $journal->journal_number,
        'posting_sequence' => $journal->posting_sequence,
        'status' => $journal->status->value,
        'connection_id' => $connectionId,
        'connection_usable' => $connectionUsable,
    ];
} catch (\Throwable $e) {
    $result = [
        'success' => false,
        'error' => $e->getMessage(),
        'class' => get_class($e),
        'connection_id' => $connectionId,
    ];
}

$resultFile = "{$barrierDir}/{$workerId}_result.json";
file_put_contents($resultFile, json_encode($result, JSON_PRETTY_PRINT));
