<?php

declare(strict_types=1);

namespace App\Filament\Pages\Accounting;

use App\Accounting\DomainException;
use App\Models\CashBankAccount;
use App\Services\Accounting\AccountingAuthorization;
use App\Services\Accounting\CashTransferService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class TransferKasPage extends Page
{
    public ?int $sourceCashBankAccountId = null;

    public ?int $destinationCashBankAccountId = null;

    public string $amount = '';

    public string $mode = 'direct';

    public ?string $memo = null;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user !== null && app(AccountingAuthorization::class)->canInitiateCashOperation($user);
    }

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static string|UnitEnum|null $navigationGroup = 'Akuntansi';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Transfer Kas';

    protected static ?string $title = 'Transfer Kas';

    protected static ?string $slug = 'transfer-kas';

    protected string $view = 'filament.pages.accounting.transfer-kas';

    public function initiateTransfer(): void
    {
        $this->validate([
            'sourceCashBankAccountId' => ['required', 'integer', 'exists:cash_bank_accounts,id'],
            'destinationCashBankAccountId' => ['required', 'integer', 'different:sourceCashBankAccountId', 'exists:cash_bank_accounts,id'],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'mode' => ['required', 'in:direct,in_transit'],
            'memo' => ['nullable', 'string', 'max:1000'],
        ]);

        $actor = Auth::user();
        if ($actor === null) {
            abort(403);
        }

        try {
            $transfer = app(CashTransferService::class)->initiate(
                $actor,
                CashBankAccount::query()->findOrFail($this->sourceCashBankAccountId),
                CashBankAccount::query()->findOrFail($this->destinationCashBankAccountId),
                $this->amount,
                $this->mode,
                $this->memo,
            );
        } catch (DomainException $exception) {
            $this->addError('sourceCashBankAccountId', $exception->getMessage());

            return;
        }

        $this->reset(['sourceCashBankAccountId', 'destinationCashBankAccountId', 'amount', 'memo']);
        $this->mode = 'direct';
        Notification::make()
            ->title("Transfer {$transfer->transfer_number} menunggu persetujuan.")
            ->success()
            ->send();
    }

    /** @return array<int, string> */
    public function availableCashAccounts(): array
    {
        $actor = Auth::user();
        if ($actor === null) {
            return [];
        }

        return CashBankAccount::query()
            ->where('is_active', true)
            ->when(! $actor->isSuperAdmin(), function ($query) use ($actor): void {
                $currentGudang = $actor->getCurrentGudang();
                $query->where('gudang_id', $currentGudang?->id);
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
