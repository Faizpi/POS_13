<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Accounting\DomainException;
use App\Accounting\JournalPostingLine;
use App\Accounting\JournalPostingRequest;
use App\Accounting\JournalType;
use App\Accounting\LineOrder;
use App\Accounting\MappingKey;
use App\Accounting\Money;
use App\Accounting\SourceIdentity;
use App\Models\JournalEntry;
use App\Models\Pembayaran;
use App\Models\Penjualan;
use App\Models\User;

final readonly class PiutangPostingService
{
    public function __construct(
        private JournalPostingService $posting,
        private JournalReversalService $reversal,
    ) {}

    public function postSale(User $actor, Penjualan $sale): ?JournalEntry
    {
        if ($sale->syarat_pembayaran === 'Cash') {
            return null;
        }

        $latest = JournalEntry::query()
            ->where('source_type', 'sale')
            ->where('source_id', $sale->id)
            ->where('journal_type', JournalType::Sale->value)
            ->latest('source_version')
            ->first();
        if ($latest !== null && ! $latest->reversalJournal()->exists()) {
            return $latest->load(['lines' => fn ($query) => $query->orderBy('line_sequence')]);
        }

        return $this->posting->post($this->postingActor($actor), new JournalPostingRequest(
            new SourceIdentity('sale', $sale->id, JournalType::Sale, $this->nextSaleVersion($sale->id)),
            $this->date($sale->tgl_transaksi),
            "Credit sale {$sale->nomor}",
            $sale->gudang_id,
            'customer',
            $sale->id,
            [
                new JournalPostingLine(MappingKey::ArReceivable, new LineOrder(10), $this->money($sale->grand_total), null),
                new JournalPostingLine($this->revenueKey($sale), new LineOrder(20), null, $this->money($sale->grand_total)),
            ],
        ));
    }

    public function postPayment(User $actor, Pembayaran $payment): ?JournalEntry
    {
        if ($payment->type !== 'piutang' || $payment->penjualan_id === null) {
            throw new DomainException('Only piutang payments can be posted to AR.');
        }

        $saleJournal = JournalEntry::query()
            ->where('source_type', 'sale')
            ->where('source_id', $payment->penjualan_id)
            ->where('journal_type', JournalType::Sale->value)
            ->where('status', 'posted')
            ->latest('source_version')
            ->first();
        if ($saleJournal === null) {
            return null;
        }

        $cashKey = strcasecmp((string) $payment->metode_pembayaran, 'Cash') === 0
            ? MappingKey::CashDefault
            : MappingKey::BankDefault;

        return $this->posting->post($this->postingActor($actor), new JournalPostingRequest(
            new SourceIdentity('payment', $payment->id, JournalType::ArPayment, 1),
            $this->date($payment->tgl_pembayaran),
            "AR payment {$payment->nomor}",
            $payment->gudang_id,
            'customer',
            $payment->penjualan_id,
            [
                new JournalPostingLine($cashKey, new LineOrder(10), $this->money($payment->jumlah_bayar), null),
                new JournalPostingLine(MappingKey::ArReceivable, new LineOrder(20), null, $this->money($payment->jumlah_bayar)),
            ],
        ));
    }

    public function reversePayment(User $actor, Pembayaran $payment, string $reason): ?JournalEntry
    {
        $journal = JournalEntry::query()
            ->where('source_type', 'payment')
            ->where('source_id', $payment->id)
            ->where('journal_type', JournalType::ArPayment->value)
            ->where('status', 'posted')
            ->first();
        if ($journal === null) {
            return null;
        }

        return $this->reversal->reverse($this->postingActor($actor), $journal, $reason);
    }

    public function reverseSale(User $actor, Penjualan $sale, string $reason): JournalEntry
    {
        $journal = JournalEntry::query()
            ->where('source_type', 'sale')
            ->where('source_id', $sale->id)
            ->where('journal_type', JournalType::Sale->value)
            ->where('status', 'posted')
            ->latest('source_version')
            ->first();
        if ($journal === null) {
            throw new DomainException('No posted credit-sale journal exists for this sale.');
        }

        $reversal = JournalEntry::query()
            ->where('original_journal_entry_id', $journal->id)
            ->exists();
        if ($reversal) {
            throw new DomainException('The sale journal has already been reversed.');
        }

        return $this->reversal->reverse($this->postingActor($actor), $journal, $reason);
    }

    private function postingActor(User $actor): User
    {
        if ($actor->role === 'super_admin') {
            return $actor;
        }

        return User::query()->where('role', 'super_admin')->firstOrFail();
    }

    private function nextSaleVersion(int $saleId): int
    {
        $latest = JournalEntry::query()
            ->where('source_type', 'sale')
            ->where('source_id', $saleId)
            ->where('journal_type', JournalType::Sale->value)
            ->max('source_version');

        return ((int) $latest) + 1;
    }

    private function revenueKey(Penjualan $sale): MappingKey
    {
        return $sale->tipe_harga === 'grosir' ? MappingKey::SalesWholesaleRevenue : MappingKey::SalesRetailRevenue;
    }

    private function money(mixed $amount): Money
    {
        return Money::fromDecimalString((string) $amount);
    }

    private function date(mixed $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        return (string) $date;
    }
}
