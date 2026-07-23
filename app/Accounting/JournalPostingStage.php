<?php

declare(strict_types=1);

namespace App\Accounting;

enum JournalPostingStage: string
{
    case BeforeSourceLock = 'before_source_lock';
    case AfterSourceLock = 'after_source_lock';
    case AfterSequenceAllocated = 'after_sequence_allocated';
    case AfterPostingWrites = 'after_posting_writes';
}
