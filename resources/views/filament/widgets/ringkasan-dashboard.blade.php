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
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-6">
        @foreach ($panels as $panel)
            <section class="he-dashboard-summary-card h-full rounded-xl p-5 shadow-sm sm:p-6">
                <div class="mb-6 flex items-start justify-between gap-4">
                    <div class="flex min-w-0 items-center gap-3 text-gray-950 dark:text-white">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400">
                            <x-filament::icon :icon="$panel['icon']" class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold leading-5 tracking-tight">{{ $panel['title'] }}</h3>
                            <p class="mt-1 truncate text-sm font-normal leading-5 text-gray-500 dark:text-gray-400">{{ $panel['subtitle'] }}</p>
                        </div>
                    </div>

                    @if ($panel['title'] === 'Bulanan')
                        <div class="flex shrink-0 items-center gap-1" aria-label="Navigasi bulan">
                            <x-filament::icon-button icon="heroicon-m-chevron-left" label="Bulan sebelumnya" size="sm" color="gray" wire:click="previousMonth" />
                            <x-filament::icon-button icon="heroicon-m-chevron-right" label="Bulan berikutnya" size="sm" color="gray" wire:click="nextMonth" />
                        </div>
                    @endif
                </div>

                <dl class="grid grid-cols-1 gap-6">
                    @foreach ([
                        ['label' => 'Penjualan', 'icon' => 'heroicon-o-shopping-cart', 'color' => 'text-blue-600 dark:text-blue-400', 'data' => $panel['metrics']['penjualan']],
                        ['label' => 'Biaya', 'icon' => 'heroicon-o-receipt-percent', 'color' => 'text-pink-600 dark:text-pink-400', 'data' => $panel['metrics']['biaya']],
                        ['label' => 'Pembayaran', 'icon' => 'heroicon-o-banknotes', 'color' => 'text-indigo-600 dark:text-indigo-400', 'data' => $panel['metrics']['pembayaran']],
                    ] as $metric)
                        <div>
                            <div class="flex items-center gap-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                                <x-filament::icon :icon="$metric['icon']" class="size-4 {{ $metric['color'] }}" />
                                <dt>{{ $metric['label'] }}</dt>
                            </div>
                            <dd class="mt-2.5 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ format_rupiah($metric['data']['total']) }}</dd>
                            <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">{{ number_format($metric['data']['count']) }} transaksi</p>
                        </div>
                    @endforeach

                    <div>
                        <div class="flex items-center gap-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-o-clock" class="size-4 text-gray-400 dark:text-gray-500" />
                            <dt>Total Jatuh Tempo</dt>
                        </div>
                        <dd class="mt-2.5 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ format_rupiah($panel['metrics']['jatuhTempo']) }}</dd>
                        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">Sisa tagihan penjualan pada periode ini.</p>
                    </div>
                </dl>
            </section>
        @endforeach
    </div>
</x-filament-widgets::widget>
