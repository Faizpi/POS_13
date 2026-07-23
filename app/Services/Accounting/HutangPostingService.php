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
use App\Models\Pembelian;
use App\Models\User;

final readonly class HutangPostingService
{
    public function __construct(private JournalPostingService $posting, private JournalReversalService $reversal) {}

    public function postPurchase(User $actor, Pembelian $purchase): ?JournalEntry
    {
        if ($purchase->syarat_pembayaran === 'Cash') {
            return null;
        }
        $latest = $this->latest('purchase', $purchase->id, JournalType::Purchase);
        if ($latest !== null && ! $latest->reversalJournal()->exists()) {
            return $latest->load('lines');
        }
        $amount = Money::fromDecimalString((string) $purchase->grand_total);

        return $this->posting->post($this->postingActor($actor), new JournalPostingRequest(
            new SourceIdentity('purchase', $purchase->id, JournalType::Purchase, ((int) $latest?->source_version) + 1),
            $this->date($purchase->tgl_transaksi), "Credit purchase {$purchase->nomor}", $purchase->gudang_id, 'supplier', $purchase->kontak_id,
            [new JournalPostingLine(MappingKey::PurchaseInventory, new LineOrder(10), $amount, null), new JournalPostingLine(MappingKey::ApPayable, new LineOrder(20), null, $amount)],
        ));
    }

    public function postPayment(User $actor, Pembayaran $payment): ?JournalEntry
    {
        if ($payment->type !== 'hutang' || $payment->pembelian_id === null || $this->latest('purchase', $payment->pembelian_id, JournalType::Purchase) === null) {
            return null;
        }
        $amount = Money::fromDecimalString((string) $payment->jumlah_bayar);
        $cash = strcasecmp((string) $payment->metode_pembayaran, 'Cash') === 0 ? MappingKey::CashDefault : MappingKey::BankDefault;

        return $this->posting->post($this->postingActor($actor), new JournalPostingRequest(
            new SourceIdentity('payment', $payment->id, JournalType::ApPayment, 1), $this->date($payment->tgl_pembayaran), "AP payment {$payment->nomor}", $payment->gudang_id, 'supplier', $payment->pembelian_id,
            [new JournalPostingLine(MappingKey::ApPayable, new LineOrder(10), $amount, null), new JournalPostingLine($cash, new LineOrder(20), null, $amount)],
        ));
    }

    public function reversePurchase(User $actor, Pembelian $purchase, string $reason): JournalEntry
    {
        $journal = $this->latest('purchase', $purchase->id, JournalType::Purchase);
        if ($journal === null) {
            throw new DomainException('No posted credit-purchase journal exists for this purchase.');
        }

        return $this->reversal->reverse($this->postingActor($actor), $journal, $reason);
    }

    public function reversePayment(User $actor, Pembayaran $payment, string $reason): ?JournalEntry
    {
        $journal = $this->latest('payment', $payment->id, JournalType::ApPayment);

        return $journal === null ? null : $this->reversal->reverse($this->postingActor($actor), $journal, $reason);
    }

    private function latest(string $type, int $id, JournalType $journalType): ?JournalEntry
    {
        return JournalEntry::query()->where('source_type', $type)->where('source_id', $id)->where('journal_type', $journalType->value)->where('status', 'posted')->latest('source_version')->first();
    }

    private function postingActor(User $actor): User
    {
        return $actor->role === 'super_admin' ? $actor : User::query()->where('role', 'super_admin')->firstOrFail();
    }

    private function date(mixed $date): string
    {
        return $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date;
    }
}
