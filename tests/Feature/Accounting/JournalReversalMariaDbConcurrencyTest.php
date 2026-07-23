<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Accounting\AccountCategory;
use App\Accounting\JournalPostingLine;
use App\Accounting\JournalPostingRequest;
use App\Accounting\JournalType;
use App\Accounting\LineOrder;
use App\Accounting\MappingKey;
use App\Accounting\Money;
use App\Accounting\NormalBalance;
use App\Accounting\SourceIdentity;
use App\Accounting\StatementClassification;
use App\Models\Account;
use App\Models\User;
use App\Services\Accounting\AccountMappingService;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JournalReversalMariaDbConcurrencyTest extends TestCase
{
    private string $barrierDir;

    private array $processes = [];

    private array $testAccountIds = [];

    private int $testUserId = 0;

    protected function setUp(): void
    {
        parent::setUp();

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

        $dbName = DB::connection('mariadb')->selectOne('SELECT DATABASE() as db')->db;
        $this->assertSame('akuntan_hibiscusefsya_test', $dbName, 'Reversal concurrency DB safety check failed');

        $this->barrierDir = sys_get_temp_dir().'/journal_reversal_test_'.uniqid();
        mkdir($this->barrierDir, 0777, true);
        $this->seedTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupAllProcesses();
        $this->cleanupBarrierDir();
        $this->cleanupJournalData();
        parent::tearDown();
    }

    private function seedTestData(): void
    {
        $user = User::on('mariadb')->firstOrCreate(
            ['email' => 'reversal_concurrency_test@example.com'],
            [
                'name' => 'Reversal Concurrency Test User',
                'password' => bcrypt('password'),
                'role' => 'super_admin',
            ]
        );
        $this->testUserId = $user->id;

        $assetAccount = Account::on('mariadb')->firstOrCreate(
            ['code' => '1-1100-RVSL'],
            [
                'name' => 'Test Kas Reversal',
                'normal_balance' => NormalBalance::Debit,
                'category' => AccountCategory::Aset,
                'statement_classification' => StatementClassification::Neraca,
                'is_postable' => true,
                'is_active' => true,
            ]
        );

        $revenueAccount = Account::on('mariadb')->firstOrCreate(
            ['code' => '4-1100-RVSL'],
            [
                'name' => 'Test Pendapatan Reversal',
                'normal_balance' => NormalBalance::Kredit,
                'category' => AccountCategory::Pendapatan,
                'statement_classification' => StatementClassification::LabaRugi,
                'is_postable' => true,
                'is_active' => true,
            ]
        );

        $mappingService = app(AccountMappingService::class);

        // The disposable DB is reusable across accounting tests, so a mapping for
        // these keys may already exist (pointing at another test's account). Reuse
        // the already-mapped account to avoid an effective-interval overlap; only
        // create a mapping when the key is entirely unmapped.
        $assetAccount = $this->resolveMappedAccount($mappingService, $user, MappingKey::ArReceivable, $assetAccount);
        $revenueAccount = $this->resolveMappedAccount($mappingService, $user, MappingKey::SalesRetailRevenue, $revenueAccount);

        $this->testAccountIds = [$assetAccount->id, $revenueAccount->id];
    }

    private function resolveMappedAccount(AccountMappingService $mappingService, User $user, MappingKey $key, Account $fallback): Account
    {
        $existingAccountId = DB::connection('mariadb')->table('account_mappings')
            ->where('mapping_key', $key->value)
            ->value('account_id');

        if ($existingAccountId !== null) {
            return Account::on('mariadb')->findOrFail($existingAccountId);
        }

        $mappingService->create($user, $key, $fallback, '2020-01-01');

        return $fallback;
    }

    private function cleanupJournalData(): void
    {
        $triggers = [
            'journal_entries_status_insert_guard',
            'journal_entries_status_update_guard',
            'journal_entries_historical_update_guard',
            'journal_entries_historical_delete_guard',
            'journal_lines_historical_insert_guard',
            'journal_lines_historical_update_guard',
            'journal_lines_historical_delete_guard',
            'journal_lines_amount_insert_guard',
            'journal_lines_amount_update_guard',
        ];

        foreach ($triggers as $trigger) {
            DB::connection('mariadb')->unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }

        DB::connection('mariadb')->table('journal_lines')->delete();
        DB::connection('mariadb')->table('journal_source_locks')->delete();
        DB::connection('mariadb')->table('journal_entries')->delete();
        DB::connection('mariadb')->table('journal_posting_sequences')->delete();

        if (! empty($this->testAccountIds)) {
            Account::on('mariadb')->whereIn('id', $this->testAccountIds)->update(['is_used' => false]);
        }

        $migration = require base_path('database/migrations/2026_07_22_110003_add_journal_database_guards.php');
        $migration->up();
    }

    private function resetBarrierDir(): void
    {
        $this->cleanupBarrierDir();
        $this->barrierDir = sys_get_temp_dir().'/journal_reversal_test_'.uniqid();
        mkdir($this->barrierDir, 0777, true);
    }

    private function cleanupBarrierDir(): void
    {
        if (! is_dir($this->barrierDir)) {
            return;
        }
        $files = glob($this->barrierDir.'/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->barrierDir);
    }

    private function cleanupAllProcesses(): void
    {
        foreach (array_keys($this->processes) as $workerId) {
            $this->terminateWorker($workerId);
        }
        $this->processes = [];
    }

    private function postOriginal(int $sourceId): int
    {
        $amount = Money::fromDecimalString('100000.00');
        $request = new JournalPostingRequest(
            sourceIdentity: new SourceIdentity('sale', $sourceId, JournalType::Sale, 1),
            journalDate: '2025-01-15',
            description: 'Original sale to reverse',
            gudangId: null,
            contactType: null,
            contactId: null,
            lines: [
                new JournalPostingLine(MappingKey::ArReceivable, new LineOrder(1), $amount, null),
                new JournalPostingLine(MappingKey::SalesRetailRevenue, new LineOrder(2), null, $amount),
            ],
        );

        $user = User::on('mariadb')->findOrFail($this->testUserId);

        return app(JournalPostingService::class)->post($user, $request)->id;
    }

    private function spawnReversalWorker(string $workerId, int $journalId, string $reason): void
    {
        $workerScript = __DIR__.'/../../../tests/Support/Accounting/reversal_worker.php';
        $cmd = sprintf(
            'php %s %s %s %d %d %s',
            escapeshellarg($workerScript),
            escapeshellarg($this->barrierDir),
            escapeshellarg($workerId),
            $this->testUserId,
            $journalId,
            escapeshellarg($reason)
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);
        if (! is_resource($process)) {
            throw new \RuntimeException("Failed to spawn reversal worker {$workerId}");
        }

        stream_set_blocking($pipes[2], false);

        $this->processes[$workerId] = [
            'process' => $process,
            'pipes' => $pipes,
        ];
    }

    private function waitForReady(string $workerId, int $timeoutMs = 10000): bool
    {
        $readyFile = "{$this->barrierDir}/{$workerId}_ready";
        $start = microtime(true);
        while (! file_exists($readyFile)) {
            if ((microtime(true) - $start) * 1000 > $timeoutMs) {
                return false;
            }
            usleep(2000);
        }

        return true;
    }

    private function releaseStartBarrier(): void
    {
        file_put_contents("{$this->barrierDir}/start", '1');
    }

    private function waitForWorkerResult(string $workerId, int $timeoutMs = 20000): array
    {
        $resultFile = "{$this->barrierDir}/{$workerId}_result.json";
        $start = microtime(true);
        while (! file_exists($resultFile)) {
            if ((microtime(true) - $start) * 1000 > $timeoutMs) {
                $this->terminateWorker($workerId);
                throw new \RuntimeException("Reversal worker {$workerId} timed out after {$timeoutMs}ms");
            }
            usleep(20000);
        }

        $result = json_decode(file_get_contents($resultFile), true);
        if ($result === null) {
            throw new \RuntimeException("Failed to parse result for reversal worker {$workerId}");
        }

        $this->waitForProcessExit($workerId, 5000);

        return $result;
    }

    private function waitForProcessExit(string $workerId, int $timeoutMs): void
    {
        if (! isset($this->processes[$workerId])) {
            return;
        }

        $process = $this->processes[$workerId]['process'];
        $pipes = $this->processes[$workerId]['pipes'];
        $start = microtime(true);

        while (true) {
            $status = proc_get_status($process);
            if (! $status['running']) {
                stream_get_contents($pipes[1]);
                stream_get_contents($pipes[2]);
                fclose($pipes[0]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                unset($this->processes[$workerId]);

                return;
            }

            if ((microtime(true) - $start) * 1000 > $timeoutMs) {
                $this->terminateWorker($workerId);
                throw new \RuntimeException("Reversal worker {$workerId} did not exit within {$timeoutMs}ms");
            }

            usleep(20000);
        }
    }

    private function terminateWorker(string $workerId): void
    {
        if (! isset($this->processes[$workerId])) {
            return;
        }

        $process = $this->processes[$workerId]['process'];
        $pipes = $this->processes[$workerId]['pipes'];

        stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $status = proc_get_status($process);
        if ($status['running']) {
            proc_terminate($process, 9);
        }

        proc_close($process);
        unset($this->processes[$workerId]);

        if (! empty($stderr)) {
            error_log("Reversal worker {$workerId} stderr: {$stderr}");
        }
    }

    public function test_concurrent_reversals_of_same_original_produce_exactly_one_reversal(): void
    {
        for ($run = 1; $run <= 3; $run++) {
            $this->cleanupJournalData();
            $this->resetBarrierDir();
            $this->processes = [];

            $originalId = $this->postOriginal(7000 + $run);

            $this->spawnReversalWorker('rev1', $originalId, 'Concurrent cancel A');
            $this->spawnReversalWorker('rev2', $originalId, 'Concurrent cancel B');

            $this->assertTrue($this->waitForReady('rev1'), "rev1 did not signal ready (run {$run})");
            $this->assertTrue($this->waitForReady('rev2'), "rev2 did not signal ready (run {$run})");

            // Release both workers to race on the same original journal simultaneously.
            $this->releaseStartBarrier();

            $result1 = $this->waitForWorkerResult('rev1', 20000);
            $result2 = $this->waitForWorkerResult('rev2', 20000);

            $successes = array_filter([$result1, $result2], static fn (array $r): bool => $r['success'] === true);
            $failures = array_filter([$result1, $result2], static fn (array $r): bool => $r['success'] === false);

            $this->assertCount(1, $successes, "Exactly one reversal must succeed (run {$run}): ".json_encode([$result1, $result2]));
            $this->assertCount(1, $failures, "Exactly one reversal must be rejected (run {$run}): ".json_encode([$result1, $result2]));

            $failure = array_values($failures)[0];
            $this->assertStringContainsString('already been reversed', $failure['error'], "Loser must be rejected as duplicate (run {$run})");

            // Database proof: exactly one reversal row links to the original.
            $reversalCount = DB::connection('mariadb')->table('journal_entries')
                ->where('original_journal_entry_id', $originalId)
                ->count();
            $this->assertSame(1, $reversalCount, "Exactly one reversal row must exist for the original (run {$run})");

            $totalEntries = DB::connection('mariadb')->table('journal_entries')->count();
            $this->assertSame(2, $totalEntries, "Only original + one reversal must exist (run {$run})");

            $totalLines = DB::connection('mariadb')->table('journal_lines')->count();
            $this->assertSame(4, $totalLines, "Original two lines + reversal two lines only (run {$run})");
        }
    }
}
