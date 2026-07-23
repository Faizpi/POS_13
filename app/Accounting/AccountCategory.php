<?php

declare(strict_types=1);

namespace App\Accounting;

enum AccountCategory: string
{
    case Aset = 'aset';
    case Kewajiban = 'kewajiban';
    case Ekuitas = 'ekuitas';
    case Pendapatan = 'pendapatan';
    case Hpp = 'hpp';
    case Beban = 'beban';
    case PendapatanLainnya = 'pendapatan_lainnya';
    case BebanLainnya = 'beban_lainnya';

    public function normalBalance(): NormalBalance
    {
        return match ($this) {
            self::Aset,
            self::Hpp,
            self::Beban,
            self::BebanLainnya => NormalBalance::Debit,

            self::Kewajiban,
            self::Ekuitas,
            self::Pendapatan,
            self::PendapatanLainnya => NormalBalance::Kredit,
        };
    }

    public function statementClassification(): StatementClassification
    {
        return match ($this) {
            self::Aset,
            self::Kewajiban,
            self::Ekuitas => StatementClassification::Neraca,

            self::Pendapatan,
            self::Hpp,
            self::Beban,
            self::PendapatanLainnya,
            self::BebanLainnya => StatementClassification::LabaRugi,
        };
    }
}
