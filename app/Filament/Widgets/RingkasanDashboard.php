<?php

namespace App\Filament\Widgets;

use App\Models\Biaya;
use App\Models\Pembayaran;
use App\Models\Penjualan;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class RingkasanDashboard extends Widget
{
    protected string $view = 'filament.widgets.ringkasan-dashboard';

    protected int|string|array $columnSpan = 'full';

    public string $selectedMonth;

    public function mount(): void
    {
        $this->selectedMonth = now()->format('Y-m');
    }

    public function previousMonth(): void
    {
        $this->selectedMonth = $this->selectedPeriod()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->selectedMonth = $this->selectedPeriod()->addMonth()->format('Y-m');
    }

    /**
     * @return array{label: string, penjualan: array{total: float, count: int}, biaya: array{total: float, count: int}, pembayaran: array{total: float, count: int}, jatuhTempo: float}
     */
    public function dailyMetrics(): array
    {
        $today = today();
        $user = auth()->user();
        $cacheKey = 'widget_ringkasan_daily:'.$user->id.':'.($user->current_gudang_id ?? 'all').':'.$today->format('Y-m-d');

        return Cache::remember($cacheKey, 300, fn () => $this->metricsFor($today, $today, 'Hari ini'));
    }

    /**
     * @return array{label: string, penjualan: array{total: float, count: int}, biaya: array{total: float, count: int}, pembayaran: array{total: float, count: int}, jatuhTempo: float}
     */
    public function monthlyMetrics(): array
    {
        $period = $this->selectedPeriod();
        $user = auth()->user();
        $cacheKey = 'widget_ringkasan_monthly:'.$user->id.':'.($user->current_gudang_id ?? 'all').':'.$period->format('Y-m');

        return Cache::remember($cacheKey, 300, fn () => $this->metricsFor(
            $period->copy()->startOfMonth(),
            $period->copy()->endOfMonth(),
            $period->translatedFormat('F Y'),
        ));
    }

    private function selectedPeriod(): Carbon
    {
        return Carbon::createFromFormat('Y-m', $this->selectedMonth)->startOfMonth();
    }

    /**
     * @return array{label: string, penjualan: array{total: float, count: int}, biaya: array{total: float, count: int}, pembayaran: array{total: float, count: int}, jatuhTempo: float}
     */
    private function metricsFor(Carbon $start, Carbon $end, string $label): array
    {
        $penjualan = $this->applyScope(
            Penjualan::query()->whereBetween('tgl_transaksi', [$start, $end]),
        );
        $biaya = $this->applyScope(
            Biaya::query()
                ->whereBetween('tgl_transaksi', [$start, $end])
                ->where('status', 'Approved'),
        );
        $pembayaran = $this->applyScope(
            Pembayaran::query()->whereBetween('tgl_pembayaran', [$start, $end]),
        );

        return [
            'label' => $label,
            'penjualan' => $this->totals($penjualan, 'grand_total'),
            'biaya' => $this->totals($biaya, 'grand_total'),
            'pembayaran' => $this->totals($pembayaran, 'jumlah_bayar'),
            'jatuhTempo' => $this->outstandingDueTotal($start, $end),
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @return array{total: float, count: int}
     */
    private function totals(Builder $query, string $amountColumn): array
    {
        return [
            'total' => (float) $query->sum($amountColumn),
            'count' => $query->count(),
        ];
    }

    private function outstandingDueTotal(Carbon $start, Carbon $end): float
    {
        // Use SQL aggregation instead of loading all records into PHP
        $row = $this->applyScope(
            Penjualan::query()
                ->where('status', 'Approved')
                ->whereNotNull('tgl_jatuh_tempo')
                ->whereBetween('tgl_jatuh_tempo', [$start, $end])
                ->withSum([
                    'pembayarans as approved_payments_total' => fn (Builder $query): Builder => $query
                        ->where('status', 'Approved'),
                ], 'jumlah_bayar'),
        )->selectRaw('COALESCE(SUM(GREATEST(grand_total - COALESCE(approved_payments_total, 0), 0)), 0) as total')->first();

        return (float) ($row->total ?? 0);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function applyScope(Builder $query): Builder
    {
        $user = auth()->user();

        if ($user?->isSuperAdmin()) {
            return $query;
        }

        if ($user?->isAdmin()) {
            $gudangId = $user->getCurrentGudang()?->id;

            return $gudangId ? $query->where('gudang_id', $gudangId) : $query->whereKey([]);
        }

        return $user?->id ? $query->where('user_id', $user->id) : $query->whereKey([]);
    }
}
