<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Pembayaran;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\User;
use App\Services\Accounting\HutangPostingService;
use App\Services\Accounting\PiutangPostingService;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PaymentSettlementService
{
    public function __construct(
        private readonly PiutangPostingService $piutangPostingService,
        private readonly HutangPostingService $hutangPostingService,
    ) {}

    public const INVALID_SALE_STATUS_MESSAGE = 'Pembayaran hanya dapat dibuat untuk penjualan yang sudah Approved dan belum lunas.';

    public const INVALID_PURCHASE_STATUS_MESSAGE = 'Pembayaran hutang hanya dapat dibuat untuk pembelian yang sudah Approved dan belum lunas.';

    public const OVERPAYMENT_MESSAGE = 'Jumlah bayar melebihi sisa tagihan.';

    public function assertPiutangPaymentCanBeCreated(Penjualan $penjualan, mixed $jumlahBayar): void
    {
        if ($penjualan->status !== 'Approved') {
            throw new DomainException(self::INVALID_SALE_STATUS_MESSAGE);
        }

        $this->assertPiutangAmountDoesNotExceedRemaining($penjualan, $jumlahBayar);
    }

    public function assertHutangPaymentCanBeCreated(Pembelian $pembelian, mixed $jumlahBayar): void
    {
        if ($pembelian->status !== 'Approved') {
            throw new DomainException(self::INVALID_PURCHASE_STATUS_MESSAGE);
        }

        $this->assertHutangAmountDoesNotExceedRemaining($pembelian, $jumlahBayar);
    }

    public function approvePayment(Pembayaran $pembayaran, int $approverId): Pembayaran
    {
        if ($pembayaran->type === 'hutang') {
            return $this->approveHutangPayment($pembayaran, $approverId);
        }

        return $this->approvePiutangPayment($pembayaran, $approverId);
    }

    public function cancelPayment(Pembayaran $pembayaran): Pembayaran
    {
        if ($pembayaran->type === 'hutang') {
            return $this->cancelHutangPayment($pembayaran);
        }

        return $this->cancelPiutangPayment($pembayaran);
    }

    public function uncancelPayment(Pembayaran $pembayaran): Pembayaran
    {
        if ($pembayaran->type === 'hutang') {
            return $this->uncancelHutangPayment($pembayaran);
        }

        return $this->uncancelPiutangPayment($pembayaran);
    }

    public function approvePiutangPayment(Pembayaran $pembayaran, int $approverId): Pembayaran
    {
        return DB::transaction(function () use ($pembayaran, $approverId): Pembayaran {
            [$lockedPayment, $penjualan] = $this->lockPiutangPaymentAndSale($pembayaran);

            if ($lockedPayment->status === 'Canceled') {
                throw new DomainException('Transaksi sudah dibatalkan, tidak bisa di-approve.');
            }

            if ($lockedPayment->status === 'Approved') {
                throw new DomainException('Transaksi sudah disetujui.');
            }

            if (! in_array($penjualan->status, ['Approved', 'Lunas'], true)) {
                throw new DomainException(self::INVALID_SALE_STATUS_MESSAGE);
            }

            $this->lockRelatedPiutangPayments($penjualan);
            $this->assertPiutangAmountDoesNotExceedRemaining($penjualan, $lockedPayment->jumlah_bayar, $lockedPayment->id);

            $lockedPayment->update([
                'status' => 'Approved',
                'approver_id' => $approverId,
            ]);

            $actor = User::query()->findOrFail($approverId);
            $this->piutangPostingService->postPayment($actor, $lockedPayment->refresh());
            $this->recomputePenjualanStatus($penjualan);

            return $lockedPayment->refresh();
        });
    }

    public function cancelPiutangPayment(Pembayaran $pembayaran): Pembayaran
    {
        return DB::transaction(function () use ($pembayaran): Pembayaran {
            [$lockedPayment, $penjualan] = $this->lockPiutangPaymentAndSale($pembayaran);

            if ($lockedPayment->status === 'Canceled') {
                throw new DomainException('Transaksi sudah dibatalkan.');
            }

            $wasApproved = $lockedPayment->status === 'Approved';
            $this->lockRelatedPiutangPayments($penjualan);

            if ($wasApproved) {
                $actor = $lockedPayment->approver_id !== null
                    ? User::query()->findOrFail($lockedPayment->approver_id)
                    : User::query()->where('role', 'super_admin')->firstOrFail();
                $this->piutangPostingService->reversePayment($actor, $lockedPayment, 'AR payment canceled');
            }

            $lockedPayment->update(['status' => 'Canceled']);

            if ($wasApproved) {
                $this->recomputePenjualanStatus($penjualan);
            }

            return $lockedPayment->refresh();
        });
    }

    public function uncancelPiutangPayment(Pembayaran $pembayaran): Pembayaran
    {
        return DB::transaction(function () use ($pembayaran): Pembayaran {
            [$lockedPayment, $penjualan] = $this->lockPiutangPaymentAndSale($pembayaran);

            if ($lockedPayment->status !== 'Canceled') {
                throw new DomainException('Transaksi ini tidak dalam status Canceled.');
            }

            $this->lockRelatedPiutangPayments($penjualan);
            $lockedPayment->update([
                'status' => 'Pending',
                'approver_id' => null,
            ]);

            return $lockedPayment->refresh();
        });
    }

    public function approveHutangPayment(Pembayaran $pembayaran, int $approverId): Pembayaran
    {
        return DB::transaction(function () use ($pembayaran, $approverId): Pembayaran {
            [$lockedPayment, $pembelian] = $this->lockHutangPaymentAndPurchase($pembayaran);

            if ($lockedPayment->status === 'Canceled') {
                throw new DomainException('Transaksi sudah dibatalkan, tidak bisa di-approve.');
            }

            if ($lockedPayment->status === 'Approved') {
                throw new DomainException('Transaksi sudah disetujui.');
            }

            if (! in_array($pembelian->status, ['Approved', 'Lunas'], true)) {
                throw new DomainException(self::INVALID_PURCHASE_STATUS_MESSAGE);
            }

            $this->lockRelatedHutangPayments($pembelian);
            $this->assertHutangAmountDoesNotExceedRemaining($pembelian, $lockedPayment->jumlah_bayar, $lockedPayment->id);

            $lockedPayment->update([
                'status' => 'Approved',
                'approver_id' => $approverId,
            ]);

            $actor = User::query()->findOrFail($approverId);
            $this->hutangPostingService->postPayment($actor, $lockedPayment->refresh());
            $this->recomputePembelianStatus($pembelian);

            return $lockedPayment->refresh();
        });
    }

    public function cancelHutangPayment(Pembayaran $pembayaran): Pembayaran
    {
        return DB::transaction(function () use ($pembayaran): Pembayaran {
            [$lockedPayment, $pembelian] = $this->lockHutangPaymentAndPurchase($pembayaran);

            if ($lockedPayment->status === 'Canceled') {
                throw new DomainException('Transaksi sudah dibatalkan.');
            }

            $this->lockRelatedHutangPayments($pembelian);

            $wasApproved = $lockedPayment->status === 'Approved';
            if ($wasApproved) {
                $actor = $lockedPayment->approver_id !== null
                    ? User::query()->findOrFail($lockedPayment->approver_id)
                    : User::query()->where('role', 'super_admin')->firstOrFail();
                $this->hutangPostingService->reversePayment($actor, $lockedPayment, 'AP payment canceled');
            }

            $lockedPayment->update(['status' => 'Canceled']);
            $this->recomputePembelianStatus($pembelian);

            return $lockedPayment->refresh();
        });
    }

    public function uncancelHutangPayment(Pembayaran $pembayaran): Pembayaran
    {
        return DB::transaction(function () use ($pembayaran): Pembayaran {
            [$lockedPayment, $pembelian] = $this->lockHutangPaymentAndPurchase($pembayaran);

            if ($lockedPayment->status !== 'Canceled') {
                throw new DomainException('Transaksi ini tidak dalam status Canceled.');
            }

            $this->lockRelatedHutangPayments($pembelian);
            $lockedPayment->update([
                'status' => 'Pending',
                'approver_id' => null,
            ]);

            $this->recomputePembelianStatus($pembelian);

            return $lockedPayment->refresh();
        });
    }

    public function remainingBalance(Penjualan $penjualan, ?int $excludingPaymentId = null): float
    {
        $remainingCents = $this->remainingPiutangBalanceCents($penjualan, $excludingPaymentId);

        return round($remainingCents / 100, 2);
    }

    public function remainingPayableBalance(Pembelian $pembelian, ?int $excludingPaymentId = null): float
    {
        $remainingCents = $this->remainingHutangBalanceCents($pembelian, $excludingPaymentId);

        return round($remainingCents / 100, 2);
    }

    private function assertPiutangAmountDoesNotExceedRemaining(
        Penjualan $penjualan,
        mixed $jumlahBayar,
        ?int $excludingPaymentId = null
    ): void {
        if ($this->moneyToCents($jumlahBayar) > $this->remainingPiutangBalanceCents($penjualan, $excludingPaymentId)) {
            throw new DomainException(self::OVERPAYMENT_MESSAGE);
        }
    }

    private function assertHutangAmountDoesNotExceedRemaining(
        Pembelian $pembelian,
        mixed $jumlahBayar,
        ?int $excludingPaymentId = null
    ): void {
        if ($this->moneyToCents($jumlahBayar) > $this->remainingHutangBalanceCents($pembelian, $excludingPaymentId)) {
            throw new DomainException(self::OVERPAYMENT_MESSAGE);
        }
    }

    private function lockPiutangPaymentAndSale(Pembayaran $pembayaran): array
    {
        $lockedPayment = Pembayaran::query()
            ->whereKey($pembayaran->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($lockedPayment->penjualan_id === null || $lockedPayment->type === 'hutang') {
            throw new DomainException('Endpoint ini hanya mendukung pembayaran piutang.');
        }

        $penjualan = Penjualan::query()
            ->whereKey($lockedPayment->penjualan_id)
            ->lockForUpdate()
            ->firstOrFail();

        return [$lockedPayment, $penjualan];
    }

    private function lockHutangPaymentAndPurchase(Pembayaran $pembayaran): array
    {
        $lockedPayment = Pembayaran::query()
            ->whereKey($pembayaran->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($lockedPayment->pembelian_id === null || $lockedPayment->type !== 'hutang') {
            throw new DomainException('Endpoint ini hanya mendukung pembayaran hutang.');
        }

        $pembelian = Pembelian::query()
            ->whereKey($lockedPayment->pembelian_id)
            ->lockForUpdate()
            ->firstOrFail();

        return [$lockedPayment, $pembelian];
    }

    private function lockRelatedPiutangPayments(Penjualan $penjualan): void
    {
        Pembayaran::query()
            ->where('penjualan_id', $penjualan->id)
            ->where('type', 'piutang')
            ->lockForUpdate()
            ->get(['id']);
    }

    private function lockRelatedHutangPayments(Pembelian $pembelian): void
    {
        Pembayaran::query()
            ->where('pembelian_id', $pembelian->id)
            ->where('type', 'hutang')
            ->lockForUpdate()
            ->get(['id']);
    }

    private function recomputePenjualanStatus(Penjualan $penjualan): void
    {
        $approvedCents = $this->approvedPiutangPaymentTotalCents($penjualan);
        $grandTotalCents = $this->moneyToCents($penjualan->grand_total);
        $targetStatus = $approvedCents >= $grandTotalCents ? 'Lunas' : 'Approved';

        if ($penjualan->status !== $targetStatus) {
            $penjualan->update(['status' => $targetStatus]);
        }
    }

    private function recomputePembelianStatus(Pembelian $pembelian): void
    {
        $approvedCents = $this->approvedHutangPaymentTotalCents($pembelian);
        $grandTotalCents = $this->moneyToCents($pembelian->grand_total);
        $targetStatus = $approvedCents >= $grandTotalCents ? 'Lunas' : 'Approved';

        if ($pembelian->status !== $targetStatus) {
            $pembelian->update(['status' => $targetStatus]);
        }
    }

    private function remainingPiutangBalanceCents(Penjualan $penjualan, ?int $excludingPaymentId = null): int
    {
        return max(0, $this->moneyToCents($penjualan->grand_total) - $this->approvedPiutangPaymentTotalCents($penjualan, $excludingPaymentId));
    }

    private function remainingHutangBalanceCents(Pembelian $pembelian, ?int $excludingPaymentId = null): int
    {
        return max(0, $this->moneyToCents($pembelian->grand_total) - $this->approvedHutangPaymentTotalCents($pembelian, $excludingPaymentId));
    }

    private function approvedPiutangPaymentTotalCents(Penjualan $penjualan, ?int $excludingPaymentId = null): int
    {
        $query = Pembayaran::query()
            ->where('penjualan_id', $penjualan->id)
            ->where('type', 'piutang')
            ->where('status', 'Approved');

        if ($excludingPaymentId !== null) {
            $query->whereKeyNot($excludingPaymentId);
        }

        return $this->moneyToCents($query->sum('jumlah_bayar'));
    }

    private function approvedHutangPaymentTotalCents(Pembelian $pembelian, ?int $excludingPaymentId = null): int
    {
        $query = Pembayaran::query()
            ->where('pembelian_id', $pembelian->id)
            ->where('type', 'hutang')
            ->where('status', 'Approved');

        if ($excludingPaymentId !== null) {
            $query->whereKeyNot($excludingPaymentId);
        }

        return $this->moneyToCents($query->sum('jumlah_bayar'));
    }

    private function moneyToCents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
