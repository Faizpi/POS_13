<?php

declare(strict_types=1);

namespace App\Accounting;

enum StatementClassification: string
{
    case Neraca = 'neraca';
    case LabaRugi = 'laba_rugi';
}
