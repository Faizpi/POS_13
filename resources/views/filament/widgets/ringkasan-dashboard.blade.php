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
    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($panels as $panel)
            <section class="h-full rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-2 text-sm font-medium text-gray-950 dark:text-white">
                        <x-filament::icon :icon="$panel['icon']" class="size-5 shrink-0 text-gray-400 dark:text-gray-500" />
                        <div class="min-w-0">
                            <h3>{{ $panel['title'] }}</h3>
                            <p class="truncate text-xs font-normal text-gray-500 dark:text-gray-400">{{ $panel['subtitle'] }}</p>
                        </div>
                    </div>

                    @if ($panel['title'] === 'Bulanan')
                        <div class="flex shrink-0 items-center gap-1" aria-label="Navigasi bulan">
                            <x-filament::icon-button icon="heroicon-m-chevron-left" label="Bulan sebelumnya" size="sm" color="gray" wire:click="previousMonth" />
                            <x-filament::icon-button icon="heroicon-m-chevron-right" label="Bulan berikutnya" size="sm" color="gray" wire:click="nextMonth" />
                        </div>
                    @endif
                </div>

                <dl class="grid gap-x-5 gap-y-4 sm:grid-cols-2">
                    @foreach ([
                        ['label' => 'Penjualan', 'icon' => 'heroicon-o-shopping-cart', 'color' => 'text-primary-600 dark:text-primary-400', 'data' => $panel['metrics']['penjualan']],
                        ['label' => 'Biaya', 'icon' => 'heroicon-o-receipt-percent', 'color' => 'text-amber-600 dark:text-amber-400', 'data' => $panel['metrics']['biaya']],
                        ['label' => 'Pembayaran', 'icon' => 'heroicon-o-banknotes', 'color' => 'text-emerald-600 dark:text-emerald-400', 'data' => $panel['metrics']['pembayaran']],
                    ] as $metric)
                        <div>
                            <div class="flex items-center gap-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                                <x-filament::icon :icon="$metric['icon']" class="size-4 {{ $metric['color'] }}" />
                                <dt>{{ $metric['label'] }}</dt>
                            </div>
                            <dd class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ format_rupiah($metric['data']['total']) }}</dd>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ number_format($metric['data']['count']) }} transaksi</p>
                        </div>
                    @endforeach

                    <div>
                        <div class="flex items-center gap-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-o-clock" class="size-4 text-gray-400 dark:text-gray-500" />
                            <dt>Total Jatuh Tempo</dt>
                        </div>
                        <dd class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ format_rupiah($panel['metrics']['jatuhTempo']) }}</dd>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Sisa tagihan penjualan pada periode ini.</p>
                    </div>
                </dl>
            </section>
        @endforeach
    </div>
</x-filament-widgets::widget>
