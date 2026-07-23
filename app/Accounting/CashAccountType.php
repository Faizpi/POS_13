<?php

declare(strict_types=1);

namespace App\Accounting;

enum CashAccountType: string
{
    case Kas = 'kas';
    case Bank = 'bank';
}
