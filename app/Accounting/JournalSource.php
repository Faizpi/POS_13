<?php

declare(strict_types=1);

namespace App\Accounting;

enum JournalSource: string
{
    case Sale = 'sale';
    case Purchase = 'purchase';
    case Payment = 'payment';
    case Transfer = 'transfer';
    case Manual = 'manual';
    case OpeningBalance = 'opening_balance';
}
