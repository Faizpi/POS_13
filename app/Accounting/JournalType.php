<?php

declare(strict_types=1);

namespace App\Accounting;

enum JournalType: string
{
    case Sale = 'sale';
    case Purchase = 'purchase';
    case ArPayment = 'ar_payment';
    case ApPayment = 'ap_payment';
    case CashTransfer = 'cash_transfer';
    case Reversal = 'reversal';
}
