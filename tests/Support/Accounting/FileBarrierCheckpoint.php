<?php

declare(strict_types=1);

namespace Tests\Support\Accounting;

use App\Accounting\JournalPostingCheckpoint;
use App\Accounting\JournalPostingStage;

final class FileBarrierCheckpoint implements JournalPostingCheckpoint
{
    public function __construct(
        private string $barrierDir,
        private string $workerId,
    ) {}

    public function reached(JournalPostingStage $stage): void
    {
        $stageName = $stage->value;
        $reachedFile = "{$this->barrierDir}/{$this->workerId}_reached_{$stageName}";
        file_put_contents($reachedFile, (string) time());

        $waitFile = "{$this->barrierDir}/{$this->workerId}_wait_{$stageName}";
        $throwFile = "{$this->barrierDir}/{$this->workerId}_throw_{$stageName}";

        $maxWaitMs = 10000;
        $pollIntervalMs = 50;
        $elapsed = 0;

        while ($elapsed < $maxWaitMs) {
            if (file_exists($throwFile)) {
                throw new \RuntimeException("Checkpoint instructed to throw at {$stageName}");
            }
            if (file_exists($waitFile)) {
                return;
            }
            usleep($pollIntervalMs * 1000);
            $elapsed += $pollIntervalMs;
        }

        throw new \RuntimeException("Checkpoint timeout waiting for instruction at {$stageName}");
    }
}
