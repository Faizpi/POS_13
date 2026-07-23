<?php

declare(strict_types=1);

namespace App\Accounting;

enum NormalBalance: string
{
    case Debit = 'debit';
    case Kredit = 'kredit';
}
