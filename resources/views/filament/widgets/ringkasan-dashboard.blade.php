@php
    $daily = $this->dailyMetrics();
    $monthly = $this->monthlyMetrics();

    $panels = [
        [
            'title' => 'Harian',
            'subtitle' => $daily['label'],
            'icon' => 'heroicon-o-calendar-days',
            'metrics' => $daily,
        ],
        [
            'title' => 'Bulanan',
            'subtitle' => $monthly['label'],
            'icon' => 'heroicon-o-calendar',
            'metrics' => $monthly,
        ],
    ];
@endphp

<x-filament-widgets::widget>
    <div class="grid gap-4 xl:grid-cols-2">
        @foreach ($panels as $panel)
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-white/10">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                            <x-filament::icon :icon="$panel['icon']" class="size-5" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-slate-950 dark:text-white">{{ $panel['title'] }}</h3>
                            <p class="truncate text-xs text-slate-500 dark:text-gray-400">{{ $panel['subtitle'] }}</p>
                        </div>
                    </div>

                    @if ($panel['title'] === 'Bulanan')
                        <div class="flex shrink-0 items-center gap-1" aria-label="Navigasi bulan">
                            <x-filament::icon-button
                                icon="heroicon-m-chevron-left"
                                label="Bulan sebelumnya"
                                size="sm"
                                color="gray"
                                wire:click="previousMonth"
                            />
                            <x-filament::icon-button
                                icon="heroicon-m-chevron-right"
                                label="Bulan berikutnya"
                                size="sm"
                                color="gray"
                                wire:click="nextMonth"
                            />
                        </div>
                    @endif
                </div>

                <dl class="grid divide-y divide-slate-100 dark:divide-white/10 sm:grid-cols-2 sm:divide-x sm:divide-y-0">
                    @foreach ([
                        ['label' => 'Penjualan', 'icon' => 'heroicon-o-shopping-cart', 'color' => 'text-primary-600 dark:text-primary-400', 'data' => $panel['metrics']['penjualan']],
                        ['label' => 'Biaya', 'icon' => 'heroicon-o-receipt-percent', 'color' => 'text-amber-600 dark:text-amber-400', 'data' => $panel['metrics']['biaya']],
                        ['label' => 'Pembayaran', 'icon' => 'heroicon-o-banknotes', 'color' => 'text-emerald-600 dark:text-emerald-400', 'data' => $panel['metrics']['pembayaran']],
                    ] as $metric)
                        <div class="px-5 py-4">
                            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-gray-400">
                                <x-filament::icon :icon="$metric['icon']" class="size-4 {{ $metric['color'] }}" />
                                <dt>{{ $metric['label'] }}</dt>
                            </div>
                            <dd class="mt-2 text-lg font-semibold tracking-tight text-slate-950 dark:text-white">{{ format_rupiah($metric['data']['total']) }}</dd>
                            <p class="mt-1 text-xs text-slate-500 dark:text-gray-400">{{ number_format($metric['data']['count']) }} transaksi</p>
                        </div>
                    @endforeach

                    <div class="bg-primary-50/60 px-5 py-4 sm:col-span-2 dark:bg-primary-500/5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2 text-xs font-semibold text-primary-700 dark:text-primary-300">
                                    <x-filament::icon icon="heroicon-o-clock" class="size-4" />
                                    <dt>Total Jatuh Tempo</dt>
                                </div>
                                <p class="mt-1 text-xs text-slate-500 dark:text-gray-400">Sisa tagihan penjualan yang jatuh tempo pada periode ini.</p>
                            </div>
                            <dd class="shrink-0 text-lg font-bold tracking-tight text-primary-700 dark:text-primary-300">{{ format_rupiah($panel['metrics']['jatuhTempo']) }}</dd>
                        </div>
                    </div>
                </dl>
            </section>
        @endforeach
    </div>
</x-filament-widgets::widget>
