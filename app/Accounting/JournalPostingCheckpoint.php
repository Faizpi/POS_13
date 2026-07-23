<?php

declare(strict_types=1);

namespace App\Accounting;

interface JournalPostingCheckpoint
{
    public function reached(JournalPostingStage $stage): void;
}
