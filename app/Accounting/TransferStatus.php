<?php

declare(strict_types=1);

namespace App\Accounting;

enum TransferStatus: string
{
    case Direct = 'direct';
    case InTransit = 'in_transit';
}
