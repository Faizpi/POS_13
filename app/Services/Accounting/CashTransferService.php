<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Accounting\DomainException;
use App\Accounting\JournalStatus;
use App\Accounting\JournalType;
use App\Accounting\LineOrder;
use App\Accounting\MappingKey;
use App\Accounting\Money;
use App\Accounting\SourceIdentity;
use App\Models\CashBankAccount;
use App\Models\CashTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CashTransferService
{
    public function __construct(
        private AccountingAuthorization $authorization,
        private LedgerPersistenceService $ledger,
        private AccountMappingService $mappings,
        private JournalReversalService $reversal,
    ) {}

    public function transfer(
        User $actor,
        CashBankAccount $source,
        CashBankAccount $destination,
        string $amount,
        string $mode,
        ?string $memo,
    ): CashTransfer {
        if ($actor->role !== 'super_admin') {
            return $this->initiate($actor, $source, $destination, $amount, $mode, $memo);
        }

        return DB::transaction(fn (): CashTransfer => $this->approve(
            $actor,
            $this->initiate($actor, $source, $destination, $amount, $mode, $memo),
        ));
    }

    public function initiate(
        User $actor,
        CashBankAccount $source,
        CashBankAccount $destination,
        string $amount,
        string $mode,
        ?string $memo,
    ): CashTransfer {
        if (! $this->authorization->canInitiateCashOperation($actor, $source->gudang_id)) {
            throw new DomainException('The actor is not authorized to initiate this cash transfer.');
        }
        if ($source->id === $destination->id) {
            throw new DomainException('Source and destination cash accounts must differ.');
        }
        if (! in_array($mode, ['direct', 'in_transit'], true)) {
            throw new DomainException('Cash transfer mode is invalid.');
        }

        $money = Money::fromDecimalString($amount);
        if (! $money->isPositive()) {
            throw new DomainException('Cash transfer amount must be positive.');
        }

        return DB::transaction(function () use ($actor, $source, $destination, $memo, $money, $mode): CashTransfer {
            $source = CashBankAccount::query()->lockForUpdate()->findOrFail($source->id);
            $destination = CashBankAccount::query()->lockForUpdate()->findOrFail($destination->id);
            if (! $source->is_active || ! $destination->is_active) {
                throw new DomainException('Cash transfer accounts must be active.');
            }

            return CashTransfer::query()->create([
                'transfer_number' => $this->nextNumber(),
                'source_cash_bank_account_id' => $source->id,
                'destination_cash_bank_account_id' => $destination->id,
                'mode' => $mode,
                'status' => 'pending',
                'amount' => $money->toDecimalString(),
                'memo' => $memo,
                'initiated_by' => $actor->id,
                'posted_by' => null,
            ]);
        });
    }

    public function approve(User $actor, CashTransfer $transfer): CashTransfer
    {
        if (! $this->authorization->canPostJournal($actor)) {
            throw new DomainException('The actor is not authorized to post this cash transfer.');
        }

        return DB::transaction(function () use ($actor, $transfer): CashTransfer {
            $transfer = CashTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            if ($transfer->status !== 'pending') {
                throw new DomainException('Only a pending cash transfer can be posted.');
            }

            $source = CashBankAccount::query()->with('account')->lockForUpdate()->findOrFail($transfer->source_cash_bank_account_id);
            $destination = CashBankAccount::query()->with('account')->lockForUpdate()->findOrFail($transfer->destination_cash_bank_account_id);
            if (! $source->is_active || ! $destination->is_active) {
                throw new DomainException('Cash transfer accounts must be active.');
            }

            $money = Money::fromDecimalString($transfer->amount);
            $this->assertSourceBalance($source, $money);

            if ($transfer->mode === 'direct') {
                $this->postDirectJournal($transfer, $source, $destination, $money);
                $transfer->update(['status' => 'posted', 'posted_by' => $actor->id]);
            } else {
                $transitAccountId = $this->transitAccountId();
                $this->postTransitSend($transfer, $source, $transitAccountId, $money);
                $transfer->update(['status' => 'in_transit', 'posted_by' => $actor->id]);
            }

            return $transfer->fresh();
        });
    }

    public function receive(User $actor, CashTransfer $transfer): CashTransfer
    {
        if (! $this->authorization->canPostJournal($actor)) {
            throw new DomainException('The actor is not authorized to receive this cash transfer.');
        }

        return DB::transaction(function () use ($actor, $transfer): CashTransfer {
            $transfer = CashTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            if ($transfer->mode !== 'in_transit' || $transfer->status !== 'in_transit') {
                throw new DomainException('Only an in-transit transfer can be received once.');
            }

            $source = CashBankAccount::query()->with('account')->findOrFail($transfer->source_cash_bank_account_id);
            $destination = CashBankAccount::query()->with('account')->findOrFail($transfer->destination_cash_bank_account_id);
            $money = Money::fromDecimalString($transfer->amount);
            $this->postTransitReceive($transfer, $source, $destination, $this->transitAccountId(), $money);
            $transfer->update(['status' => 'posted', 'posted_by' => $actor->id]);

            return $transfer->fresh();
        });
    }

    public function cancel(User $actor, CashTransfer $transfer, string $reason): CashTransfer
    {
        if (! $this->authorization->canPostJournal($actor)) {
            throw new DomainException('The actor is not authorized to cancel this cash transfer.');
        }
        if (trim($reason) === '') {
            throw new DomainException('Cash transfer cancellation reason is required.');
        }

        return DB::transaction(function () use ($actor, $transfer, $reason): CashTransfer {
            $transfer = CashTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            if ($transfer->status === 'canceled') {
                throw new DomainException('The cash transfer has already been canceled.');
            }

            foreach ($transfer->journals()->where('status', JournalStatus::Posted->value)->get() as $journal) {
                $this->reversal->reverse($actor, $journal, $reason);
            }
            $transfer->update(['status' => 'canceled']);

            return $transfer->fresh();
        });
    }

    public function destinationStatusDescription(CashTransfer $transfer): string
    {
        $source = $transfer->sourceCashBankAccount()->firstOrFail();

        return $transfer->status === 'in_transit'
            ? "Menunggu diterima dari {$source->name}"
            : "Diterima dari {$source->name}";
    }

    private function nextNumber(): string
    {
        $key = 'cash_transfer';
        DB::table('cash_transfer_sequences')->insertOrIgnore([
            'sequence_key' => $key,
            'last_value' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sequence = DB::table('cash_transfer_sequences')->where('sequence_key', $key)->lockForUpdate()->first();
        if ($sequence === null) {
            throw new DomainException('Unable to allocate cash transfer sequence.');
        }

        $next = (int) $sequence->last_value + 1;
        DB::table('cash_transfer_sequences')->where('sequence_key', $key)->update([
            'last_value' => $next,
            'updated_at' => now(),
        ]);

        return sprintf('TRF-%s-%06d', now()->format('Ymd'), $next);
    }

    private function transitAccountId(): int
    {
        $transit = $this->mappings->resolve(MappingKey::CashInTransit, now()->toDateString());
        if ($transit === null) {
            throw new DomainException('No mapping exists for cash.in_transit.');
        }

        return $transit->id;
    }

    private function assertSourceBalance(CashBankAccount $source, Money $amount): void
    {
        $totals = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.status', JournalStatus::Posted->value)
            ->where('journal_lines.account_id', $source->account_id)
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as debit, COALESCE(SUM(journal_lines.credit), 0) as credit')
            ->first();
        $balance = Money::fromDecimalString((string) $totals->debit)
            ->subtract(Money::fromDecimalString((string) $totals->credit));

        if ($balance->lessThan($amount)) {
            throw new DomainException('Source cash account has insufficient posted balance.');
        }
    }

    private function postDirectJournal(CashTransfer $transfer, CashBankAccount $source, CashBankAccount $destination, Money $money): void
    {
        $this->post($transfer, $source, $destination, $money, 1, $destination->account_id, $source->account_id, "Diterima dari {$source->name}", "Transfer ke {$destination->name}");
    }

    private function postTransitSend(CashTransfer $transfer, CashBankAccount $source, int $transitAccountId, Money $money): void
    {
        $this->post($transfer, $source, $source, $money, 1, $transitAccountId, $source->account_id, "Menunggu diterima dari {$source->name}", "Transfer ke {$transfer->destinationCashBankAccount()->firstOrFail()->name}");
    }

    private function postTransitReceive(CashTransfer $transfer, CashBankAccount $source, CashBankAccount $destination, int $transitAccountId, Money $money): void
    {
        $this->post($transfer, $source, $destination, $money, 2, $destination->account_id, $transitAccountId, "Diterima dari {$source->name}", "Transfer {$source->name} ke {$destination->name}");
    }

    private function post(CashTransfer $transfer, CashBankAccount $source, CashBankAccount $destination, Money $money, int $version, int $debitAccountId, int $creditAccountId, string $debitDescription, string $creditDescription): void
    {
        $journal = $this->ledger->persist(
            new SourceIdentity('cash_transfer', $transfer->id, JournalType::CashTransfer, $version),
            now()->toDateString(),
            $creditDescription,
            $source->gudang_id,
            null,
            null,
            [
                ['account_id' => $debitAccountId, 'line_order' => new LineOrder(10), 'debit' => $money, 'credit' => null, 'gudang_id' => $destination->gudang_id, 'description' => $debitDescription],
                ['account_id' => $creditAccountId, 'line_order' => new LineOrder(20), 'debit' => null, 'credit' => $money, 'gudang_id' => $source->gudang_id, 'description' => $creditDescription],
            ],
        );
        $journal->update(['status' => JournalStatus::Approved]);
        $journal->update(['status' => JournalStatus::Posted]);
    }
}
