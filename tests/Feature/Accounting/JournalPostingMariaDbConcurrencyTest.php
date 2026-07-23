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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JournalPostingMariaDbConcurrencyTest extends TestCase
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
        $this->assertSame('akuntan_hibiscusefsya_test', $dbName, 'Worker DB safety check failed');

        $this->barrierDir = sys_get_temp_dir().'/journal_test_'.uniqid();
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
            ['email' => 'concurrency_test@example.com'],
            [
                'name' => 'Concurrency Test User',
                'password' => bcrypt('password'),
                'role' => 'super_admin',
            ]
        );
        $this->testUserId = $user->id;

        $assetAccount = Account::on('mariadb')->firstOrCreate(
            ['code' => '1-1100-TEST'],
            [
                'name' => 'Test Kas',
                'normal_balance' => NormalBalance::Debit,
                'category' => AccountCategory::Aset,
                'statement_classification' => StatementClassification::Neraca,
                'is_postable' => true,
                'is_active' => true,
            ]
        );

        $revenueAccount = Account::on('mariadb')->firstOrCreate(
            ['code' => '4-1100-TEST'],
            [
                'name' => 'Test Pendapatan Penjualan',
                'normal_balance' => NormalBalance::Kredit,
                'category' => AccountCategory::Pendapatan,
                'statement_classification' => StatementClassification::LabaRugi,
                'is_postable' => true,
                'is_active' => true,
            ]
        );

        $this->testAccountIds = [$assetAccount->id, $revenueAccount->id];

        $mappingService = app(AccountMappingService::class);

        $arMappingExists = DB::connection('mariadb')->table('account_mappings')
            ->where('mapping_key', MappingKey::ArReceivable->value)
            ->where('account_id', $assetAccount->id)
            ->exists();
        if (! $arMappingExists) {
            $mappingService->create($user, MappingKey::ArReceivable, $assetAccount, '2020-01-01');
        }

        $salesMappingExists = DB::connection('mariadb')->table('account_mappings')
            ->where('mapping_key', MappingKey::SalesRetailRevenue->value)
            ->where('account_id', $revenueAccount->id)
            ->exists();
        if (! $salesMappingExists) {
            $mappingService->create($user, MappingKey::SalesRetailRevenue, $revenueAccount, '2020-01-01');
        }
    }

    private function cleanupJournalData(): void
    {
        // Drop triggers temporarily to allow cleanup of posted journals
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

        // Recreate triggers
        $migration = require base_path('database/migrations/2026_07_22_110003_add_journal_database_guards.php');
        $migration->up();
    }

    private function cleanupAllAccountingData(): void
    {
        $this->cleanupJournalData();
        DB::connection('mariadb')->table('account_mappings')->delete();
        DB::connection('mariadb')->table('account_mapping_key_locks')->delete();
    }

    private function resetBarrierDir(): void
    {
        $this->cleanupBarrierDir();
        $this->barrierDir = sys_get_temp_dir().'/journal_test_'.uniqid();
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

    private function buildRequest(int $sourceId, int $sourceVersion = 1): JournalPostingRequest
    {
        return new JournalPostingRequest(
            sourceIdentity: new SourceIdentity(
                sourceType: 'sale',
                sourceId: $sourceId,
                journalType: JournalType::Sale,
                sourceVersion: $sourceVersion,
            ),
            journalDate: '2025-01-15',
            description: 'Test journal posting',
            gudangId: null,
            contactType: null,
            contactId: null,
            lines: [
                new JournalPostingLine(
                    mappingKey: MappingKey::ArReceivable,
                    lineOrder: new LineOrder(1),
                    debit: Money::fromDecimalString('100000.00'),
                    credit: null,
                ),
                new JournalPostingLine(
                    mappingKey: MappingKey::SalesRetailRevenue,
                    lineOrder: new LineOrder(2),
                    debit: null,
                    credit: Money::fromDecimalString('100000.00'),
                ),
            ],
        );
    }

    private function spawnWorker(string $workerId, int $userId, JournalPostingRequest $request): void
    {
        $requestJson = base64_encode(serialize($request));
        $workerScript = __DIR__.'/../../../tests/Support/Accounting/worker.php';
        $cmd = sprintf(
            'php %s %s %s %d %s',
            escapeshellarg($workerScript),
            escapeshellarg($this->barrierDir),
            escapeshellarg($workerId),
            $userId,
            escapeshellarg($requestJson)
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);
        if (! is_resource($process)) {
            throw new \RuntimeException("Failed to spawn worker {$workerId}");
        }

        // Set stderr to non-blocking for capture
        stream_set_blocking($pipes[2], false);

        $this->processes[$workerId] = [
            'process' => $process,
            'pipes' => $pipes,
        ];
    }

    private function waitForCheckpoint(string $workerId, string $stage, int $timeoutMs = 10000): bool
    {
        $reachedFile = "{$this->barrierDir}/{$workerId}_reached_{$stage}";
        $start = microtime(true);
        $timeoutSec = $timeoutMs / 1000;

        while (! file_exists($reachedFile)) {
            if ((microtime(true) - $start) > $timeoutSec) {
                // Capture stderr on timeout
                if (isset($this->processes[$workerId])) {
                    $pipes = $this->processes[$workerId]['pipes'];
                    stream_set_blocking($pipes[2], false);
                    $stderr = stream_get_contents($pipes[2]);
                    if (! empty($stderr)) {
                        error_log("Worker {$workerId} stderr on checkpoint timeout: {$stderr}");
                    }
                }

                return false;
            }
            usleep(50000);
        }

        return true;
    }

    private function releaseWorker(string $workerId, string $stage): void
    {
        $waitFile = "{$this->barrierDir}/{$workerId}_wait_{$stage}";
        file_put_contents($waitFile, '1');
    }

    private function instructThrow(string $workerId, string $stage): void
    {
        $throwFile = "{$this->barrierDir}/{$workerId}_throw_{$stage}";
        file_put_contents($throwFile, '1');
    }

    private function waitForWorkerResult(string $workerId, int $timeoutMs = 20000): array
    {
        $resultFile = "{$this->barrierDir}/{$workerId}_result.json";
        $start = microtime(true);
        $timeoutSec = $timeoutMs / 1000;

        while (! file_exists($resultFile)) {
            if ((microtime(true) - $start) > $timeoutSec) {
                $this->terminateWorker($workerId);
                throw new \RuntimeException("Worker {$workerId} timed out after {$timeoutMs}ms");
            }
            usleep(50000);
        }

        $resultJson = file_get_contents($resultFile);
        $result = json_decode($resultJson, true);
        if ($result === null) {
            throw new \RuntimeException("Failed to parse result for worker {$workerId}");
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
        $timeoutSec = $timeoutMs / 1000;

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

            if ((microtime(true) - $start) > $timeoutSec) {
                $this->terminateWorker($workerId);
                throw new \RuntimeException("Worker {$workerId} did not exit within {$timeoutMs}ms");
            }

            usleep(50000);
        }
    }

    private function terminateWorker(string $workerId): void
    {
        if (! isset($this->processes[$workerId])) {
            return;
        }

        $process = $this->processes[$workerId]['process'];
        $pipes = $this->processes[$workerId]['pipes'];

        $stdout = stream_get_contents($pipes[1]);
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
            error_log("Worker {$workerId} stderr: {$stderr}");
        }
    }

    private function verifyWriterBlockedByInnodb(string $blockedWorkerId, string $blockingWorkerId, int $timeoutMs = 5000): void
    {
        $blockedConnIdFile = "{$this->barrierDir}/{$blockedWorkerId}_connection_id";
        $blockingConnIdFile = "{$this->barrierDir}/{$blockingWorkerId}_connection_id";

        $start = microtime(true);
        $timeoutSec = $timeoutMs / 1000;

        while ((microtime(true) - $start) < $timeoutSec) {
            if (! file_exists($blockedConnIdFile) || ! file_exists($blockingConnIdFile)) {
                usleep(50000);

                continue;
            }

            $blockedConnId = (int) file_get_contents($blockedConnIdFile);
            $blockingConnId = (int) file_get_contents($blockingConnIdFile);

            $lockWaits = DB::connection('mariadb')->select('
                SELECT w.* FROM INFORMATION_SCHEMA.INNODB_LOCK_WAITS w
                JOIN INFORMATION_SCHEMA.INNODB_TRX t ON w.requesting_trx_id = t.trx_id
                WHERE t.trx_mysql_thread_id = ?
            ', [$blockedConnId]);

            if (! empty($lockWaits)) {
                foreach ($lockWaits as $lockWait) {
                    $blockingTrx = DB::connection('mariadb')->select('
                        SELECT * FROM INFORMATION_SCHEMA.INNODB_TRX
                        WHERE trx_id = ?
                    ', [$lockWait->blocking_trx_id]);

                    if (! empty($blockingTrx)) {
                        $blockingTrxThreadId = (int) $blockingTrx[0]->trx_mysql_thread_id;
                        if ($blockingTrxThreadId === $blockingConnId) {
                            return;
                        }
                    }
                }
            }

            usleep(100000);
        }

        $blockedConnId = file_exists($blockedConnIdFile) ? file_get_contents($blockedConnIdFile) : 'N/A';
        $blockingConnId = file_exists($blockingConnIdFile) ? file_get_contents($blockingConnIdFile) : 'N/A';
        $this->fail("Writer {$blockedWorkerId} (conn {$blockedConnId}) was not shown waiting on writer {$blockingWorkerId} (conn {$blockingConnId}) in INNODB_LOCK_WAITS within {$timeoutMs}ms");
    }

    public function test_scenario_a_both_writers_commit_same_idempotent_journal(): void
    {
        for ($run = 1; $run <= 3; $run++) {
            $this->cleanupJournalData();
            $this->resetBarrierDir();
            $this->processes = [];

            $request = $this->buildRequest(1000 + $run);

            $this->spawnWorker('writer1', $this->testUserId, $request);
            $this->spawnWorker('writer2', $this->testUserId, $request);

            $this->assertTrue(
                $this->waitForCheckpoint('writer1', 'before_source_lock'),
                "Writer1 did not reach BeforeSourceLock (run {$run})"
            );
            $this->assertTrue(
                $this->waitForCheckpoint('writer2', 'before_source_lock'),
                "Writer2 did not reach BeforeSourceLock (run {$run})"
            );

            $this->releaseWorker('writer1', 'before_source_lock');

            $this->assertTrue(
                $this->waitForCheckpoint('writer1', 'after_source_lock'),
                "Writer1 did not reach AfterSourceLock (run {$run})"
            );

            $this->releaseWorker('writer2', 'before_source_lock');

            $this->verifyWriterBlockedByInnodb('writer2', 'writer1', 5000);

            $this->releaseWorker('writer1', 'after_source_lock');

            $this->assertTrue(
                $this->waitForCheckpoint('writer1', 'after_sequence_allocated'),
                "Writer1 did not reach AfterSequenceAllocated (run {$run})"
            );

            $this->releaseWorker('writer1', 'after_sequence_allocated');
            $this->assertTrue($this->waitForCheckpoint('writer1', 'after_posting_writes'));
            $this->releaseWorker('writer1', 'after_posting_writes');

            $writer1Result = $this->waitForWorkerResult('writer1', 15000);
            $this->assertTrue($writer1Result['success'], "Writer1 should succeed (run {$run}): ".($writer1Result['error'] ?? ''));

            $this->assertTrue(
                $this->waitForCheckpoint('writer2', 'after_source_lock'),
                "Writer2 did not reach AfterSourceLock after writer1 commit (run {$run})"
            );

            $this->releaseWorker('writer2', 'after_source_lock');

            $writer2Result = $this->waitForWorkerResult('writer2', 15000);
            $this->assertTrue($writer2Result['success'], "Writer2 should succeed (run {$run}): ".($writer2Result['error'] ?? ''));

            $this->assertSame(
                $writer1Result['journal_id'],
                $writer2Result['journal_id'],
                "Both writers should return same journal_id (run {$run})"
            );

            $this->assertTrue($writer2Result['connection_usable'], "Writer2 connection should remain usable after duplicate 1062 (run {$run})");
            $this->assertSame(1, DB::connection('mariadb')->table('journal_entries')->count(), "Should have exactly one journal entry (run {$run})");
            $this->assertSame(2, DB::connection('mariadb')->table('journal_lines')->count(), "Should have exactly two journal lines (run {$run})");
            $this->assertSame(1, DB::connection('mariadb')->table('journal_source_locks')->count(), "Should have exactly one canonical lock (run {$run})");
            $this->assertSame(1, (int) DB::connection('mariadb')->table('journal_posting_sequences')->value('last_value'), "Sequence should increment exactly once (run {$run})");
        }
    }

    public function test_scenario_b_writer1_rolls_back_writer2_creates_journal(): void
    {
        for ($run = 1; $run <= 3; $run++) {
            $this->cleanupJournalData();
            $this->resetBarrierDir();
            $this->processes = [];

            $request = $this->buildRequest(2000 + $run);
            $this->spawnWorker('writer1', $this->testUserId, $request);
            $this->spawnWorker('writer2', $this->testUserId, $request);

            $this->assertTrue($this->waitForCheckpoint('writer1', 'before_source_lock'));
            $this->assertTrue($this->waitForCheckpoint('writer2', 'before_source_lock'));
            $this->releaseWorker('writer1', 'before_source_lock');
            $this->assertTrue($this->waitForCheckpoint('writer1', 'after_source_lock'));

            $this->releaseWorker('writer2', 'before_source_lock');
            $this->verifyWriterBlockedByInnodb('writer2', 'writer1', 5000);
            $this->instructThrow('writer1', 'after_source_lock');

            $writer1Result = $this->waitForWorkerResult('writer1', 15000);
            $this->assertFalse($writer1Result['success'], "Writer1 should roll back (run {$run})");
            $this->assertStringContainsString('Checkpoint instructed to throw', $writer1Result['error']);

            $this->assertTrue($this->waitForCheckpoint('writer2', 'after_source_lock'));
            $this->releaseWorker('writer2', 'after_source_lock');
            $this->assertTrue($this->waitForCheckpoint('writer2', 'after_sequence_allocated'));
            $this->releaseWorker('writer2', 'after_sequence_allocated');
            $this->assertTrue($this->waitForCheckpoint('writer2', 'after_posting_writes'));
            $this->releaseWorker('writer2', 'after_posting_writes');

            $writer2Result = $this->waitForWorkerResult('writer2', 15000);
            $this->assertTrue($writer2Result['success'], "Writer2 should create after rollback (run {$run}): ".($writer2Result['error'] ?? ''));
            $this->assertSame(1, DB::connection('mariadb')->table('journal_entries')->count());
            $this->assertSame(2, DB::connection('mariadb')->table('journal_lines')->count());
            $this->assertSame(1, DB::connection('mariadb')->table('journal_source_locks')->count());
            $this->assertSame(1, (int) DB::connection('mariadb')->table('journal_posting_sequences')->value('last_value'));
        }
    }

    public function test_scenario_c_account_baseline_and_is_used_after_failure(): void
    {
        for ($run = 1; $run <= 3; $run++) {
            $this->cleanupJournalData();
            $this->resetBarrierDir();
            $this->processes = [];

            foreach ($this->testAccountIds as $accountId) {
                $account = Account::on('mariadb')->find($accountId);
                $this->assertFalse((bool) $account->is_used, "Account {$accountId} should have is_used=false at baseline (run {$run})");
            }

            $failedRequest = $this->buildRequest(3000 + $run);
            $this->spawnWorker('writer1', $this->testUserId, $failedRequest);
            $this->assertTrue($this->waitForCheckpoint('writer1', 'before_source_lock'));
            $this->releaseWorker('writer1', 'before_source_lock');
            $this->assertTrue($this->waitForCheckpoint('writer1', 'after_source_lock'));
            $this->releaseWorker('writer1', 'after_source_lock');
            $this->assertTrue($this->waitForCheckpoint('writer1', 'after_sequence_allocated'));
            $this->releaseWorker('writer1', 'after_sequence_allocated');
            $this->assertTrue($this->waitForCheckpoint('writer1', 'after_posting_writes'));
            $this->instructThrow('writer1', 'after_posting_writes');

            $failedResult = $this->waitForWorkerResult('writer1', 15000);
            $this->assertFalse($failedResult['success'], "Posting should fail after sequence allocation (run {$run})");
            $this->assertSame(0, DB::connection('mariadb')->table('journal_entries')->count());
            $this->assertSame(0, DB::connection('mariadb')->table('journal_lines')->count());
            $this->assertSame(0, DB::connection('mariadb')->table('journal_source_locks')->count());
            $this->assertNull(DB::connection('mariadb')->table('journal_posting_sequences')->where('sequence_key', 'journal')->first());

            foreach ($this->testAccountIds as $accountId) {
                $account = Account::on('mariadb')->findOrFail($accountId);
                $this->assertFalse((bool) $account->is_used, "Account {$accountId} should remain unused after rollback (run {$run})");
            }

            $successfulRequest = $this->buildRequest(4000 + $run);
            $this->spawnWorker('writer2', $this->testUserId, $successfulRequest);
            $this->assertTrue($this->waitForCheckpoint('writer2', 'before_source_lock'));
            $this->releaseWorker('writer2', 'before_source_lock');
            $this->assertTrue($this->waitForCheckpoint('writer2', 'after_source_lock'));
            $this->releaseWorker('writer2', 'after_source_lock');
            $this->assertTrue($this->waitForCheckpoint('writer2', 'after_sequence_allocated'));
            $this->releaseWorker('writer2', 'after_sequence_allocated');
            $this->assertTrue($this->waitForCheckpoint('writer2', 'after_posting_writes'));
            $this->releaseWorker('writer2', 'after_posting_writes');

            $successfulResult = $this->waitForWorkerResult('writer2', 15000);
            $this->assertTrue($successfulResult['success'], "Subsequent posting should succeed (run {$run})");
            $this->assertSame(1, $successfulResult['posting_sequence']);
        }
    }

    public function test_scenario_d_duplicate_journal_number_throws_exact1062(): void
    {
        for ($run = 1; $run <= 3; $run++) {
            $this->cleanupJournalData();
            $this->resetBarrierDir();
            $this->processes = [];

            $request1 = $this->buildRequest(5000 + $run);
            $this->spawnWorker('writer1', $this->testUserId, $request1);

            $this->assertTrue(
                $this->waitForCheckpoint('writer1', 'before_source_lock'),
                "Writer1 did not reach BeforeSourceLock (run {$run})"
            );
            $this->releaseWorker('writer1', 'before_source_lock');

            $this->assertTrue(
                $this->waitForCheckpoint('writer1', 'after_source_lock'),
                "Writer1 did not reach AfterSourceLock (run {$run})"
            );
            $this->releaseWorker('writer1', 'after_source_lock');

            $this->assertTrue(
                $this->waitForCheckpoint('writer1', 'after_sequence_allocated'),
                "Writer1 did not reach AfterSequenceAllocated (run {$run})"
            );
            $this->releaseWorker('writer1', 'after_sequence_allocated');
            $this->assertTrue($this->waitForCheckpoint('writer1', 'after_posting_writes'));
            $this->releaseWorker('writer1', 'after_posting_writes');

            $writer1Result = $this->waitForWorkerResult('writer1', 15000);
            $this->assertTrue($writer1Result['success'], "Writer1 should succeed (run {$run}): ".($writer1Result['error'] ?? ''));

            DB::connection('mariadb')->table('journal_posting_sequences')
                ->where('sequence_key', 'journal')
                ->update(['last_value' => 0]);

            $request2 = $this->buildRequest(6000 + $run);
            $this->spawnWorker('writer2', $this->testUserId, $request2);

            $this->assertTrue(
                $this->waitForCheckpoint('writer2', 'before_source_lock'),
                "Writer2 did not reach BeforeSourceLock (run {$run})"
            );
            $this->releaseWorker('writer2', 'before_source_lock');

            $this->assertTrue(
                $this->waitForCheckpoint('writer2', 'after_source_lock'),
                "Writer2 did not reach AfterSourceLock (run {$run})"
            );
            $this->releaseWorker('writer2', 'after_source_lock');

            $this->assertTrue(
                $this->waitForCheckpoint('writer2', 'after_sequence_allocated'),
                "Writer2 did not reach AfterSequenceAllocated (run {$run})"
            );
            $this->releaseWorker('writer2', 'after_sequence_allocated');

            $writer2Result = $this->waitForWorkerResult('writer2', 15000);
            $this->assertFalse($writer2Result['success'], "Writer2 should fail with duplicate journal_number (run {$run})");
            $this->assertStringContainsString('1062', $writer2Result['error'], "Should be MySQL error 1062 (run {$run})");
            $this->assertStringContainsString('journal_entries_journal_number_unique', $writer2Result['error'], "Should reference exact index name (run {$run})");

            $journalCount = DB::connection('mariadb')->table('journal_entries')->count();
            $this->assertSame(1, $journalCount, "Should still have only the original journal (run {$run})");
        }
    }
}
