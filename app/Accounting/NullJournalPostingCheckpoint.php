<?php

declare(strict_types=1);

namespace App\Accounting;

final class NullJournalPostingCheckpoint implements JournalPostingCheckpoint
{
    public function reached(JournalPostingStage $stage): void
    {
        // No-op: production default implementation
    }
}
