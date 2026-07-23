@php
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    $color = $this->getColor();
    $data = $this->getCachedData();
    $datasets = collect($data['datasets'] ?? []);
    $latestPeriod = collect($data['labels'] ?? [])->last();
    $type = $this->getType();
    $maxHeight = $this->getMaxHeight();
    $hasMaxHeight = filled($maxHeight) && $maxHeight !== '100%';

    $metricColors = [
        'Penjualan' => ['dot' => 'bg-blue-600', 'text' => 'text-blue-700 dark:text-blue-400'],
        'Pembelian' => ['dot' => 'bg-violet-600', 'text' => 'text-violet-700 dark:text-violet-400'],
        'Biaya' => ['dot' => 'bg-pink-500', 'text' => 'text-pink-700 dark:text-pink-400'],
    ];

    $formatCompactRupiah = static function (float|int $value): string {
        $absoluteValue = abs($value);

        if ($absoluteValue >= 1_000_000_000) {
            return 'Rp '.number_format($value / 1_000_000_000, 1, ',', '.').' M';
        }

        if ($absoluteValue >= 1_000_000) {
            return 'Rp '.number_format($value / 1_000_000, 1, ',', '.').' jt';
        }

        if ($absoluteValue >= 1_000) {
            return 'Rp '.number_format($value / 1_000, 1, ',', '.').' rb';
        }

        return 'Rp '.number_format($value, 0, ',', '.');
    };
@endphp

<x-filament-widgets::widget class="fi-wi-chart he-trend-widget">
    <x-filament::section>
        <div class="he-trend-header">
            <div>
                <div class="he-trend-title-row">
                    <h3 class="he-trend-title">Tren Transaksi</h3>
                    <span class="he-trend-period">6 bulan terakhir</span>
                </div>
                <p class="he-trend-description">Performa penjualan, pembelian, dan biaya per bulan.</p>
            </div>

            <span class="he-trend-latest">Periode terbaru: {{ $latestPeriod }}</span>
        </div>

        <dl class="he-trend-metrics">
            @foreach ($datasets as $dataset)
                @php
                    $label = $dataset['label'] ?? 'Transaksi';
                    $values = collect($dataset['data'] ?? [])->values();
                    $latestValue = (float) ($values->last() ?? 0);
                    $previousValue = (float) ($values->slice(-2, 1)->first() ?? 0);
                    $change = $previousValue != 0.0
                        ? (($latestValue - $previousValue) / abs($previousValue)) * 100
                        : null;
                    $styles = $metricColors[$label] ?? ['dot' => 'bg-gray-400', 'text' => 'text-gray-700 dark:text-gray-300'];
                @endphp

                <div class="he-trend-metric">
                    <dt class="he-trend-metric-label">
                        <span class="he-trend-dot {{ $styles['dot'] }}"></span>
                        {{ $label }}
                    </dt>
                    <dd class="he-trend-metric-value {{ $styles['text'] }}">{{ $formatCompactRupiah($latestValue) }}</dd>
                    <p class="he-trend-metric-change">
                        @if ($change !== null)
                            <span class="{{ $change >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-pink-600 dark:text-pink-400' }}">
                                {{ $change >= 0 ? '↑' : '↓' }} {{ number_format(abs($change), 1, ',', '.') }}%
                            </span>
                            <span>dari bulan lalu</span>
                        @else
                            <span>Belum ada pembanding</span>
                        @endif
                    </p>
                </div>
            @endforeach
        </dl>

        <div
            @if ($pollingInterval = $this->getPollingInterval())
                wire:poll.{{ $pollingInterval }}="updateChartData"
            @endif
            class="he-trend-chart"
        >
            <div
                x-load
                x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                wire:ignore
                data-chart-type="{{ $type }}"
                x-data="chart({
                    cachedData: @js($data),
                    options: @js($this->getOptions()),
                    type: @js($type),
                })"
                {{
                    (new ComponentAttributeBag)
                        ->color(ChartWidgetComponent::class, $color)
                        ->class([
                            'fi-wi-chart-canvas-ctn',
                            'fi-wi-chart-canvas-ctn-no-aspect-ratio' => $hasMaxHeight,
                        ])
                }}
            >
                <canvas
                    x-ref="canvas"
                    @style([
                        'width: 100%',
                        'height: 100%; max-height: 100%' => ! $hasMaxHeight,
                        "max-height: {$maxHeight}" => $hasMaxHeight,
                    ])
                ></canvas>

                <span x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
                <span x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
                <span x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
                <span x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
