<x-filament-panels::page>
    @php
        $chartData = $this->getChartData();
        $summaryTempo = $this->getListTempoAll();
        $listTempo = $this->getListTempo();
        $totalHutang = $summaryTempo->where('status', 'Approved')->sum('sisa');
        $totalLunas = $summaryTempo->where('status', 'Lunas')->sum('grand_total');
        $aging = $this->getAgingSummary();
    @endphp

    <div class="he-finance-filter flex items-center gap-2 p-3 mb-2 text-sm text-gray-500 dark:text-gray-400">
        Periode: <strong>{{ $this->filter_from ? \Carbon\Carbon::parse($this->filter_from)->format('d/m/Y') : 'Semua' }} — {{ $this->filter_to ? \Carbon\Carbon::parse($this->filter_to)->format('d/m/Y') : 'Semua' }}</strong>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <x-filament::section class="he-finance-section">
            <p class="text-sm text-gray-500 dark:text-gray-400">Total Hutang Belum Lunas</p>
            <p class="text-2xl font-bold text-danger-600">Rp {{ number_format($totalHutang, 0, ',', '.') }}</p>
        </x-filament::section>
        <x-filament::section class="he-finance-section">
            <p class="text-sm text-gray-500 dark:text-gray-400">Total Hutang Lunas</p>
            <p class="text-2xl font-bold text-success-600">Rp {{ number_format($totalLunas, 0, ',', '.') }}</p>
        </x-filament::section>
        <x-filament::section class="he-finance-section">
            <p class="text-sm text-gray-500 dark:text-gray-400">Total Transaksi Tempo</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $listTempo->total() }} transaksi</p>
        </x-filament::section>
    </div>

    <x-filament::section heading="Aging Hutang (Posted Ledger)" class="he-finance-section mb-6">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
            @foreach (['current' => 'Current', '1-30' => '1–30', '31-60' => '31–60', '61-90' => '61–90', '90+' => '90+'] as $key => $label)
                <div><p class="text-xs text-gray-500">{{ $label }}</p><p class="font-semibold tabular-nums text-gray-950 dark:text-white">Rp {{ number_format($aging['buckets'][$key], 0, ',', '.') }}</p></div>
            @endforeach
        </div>
    </x-filament::section>

    {{-- Chart --}}
    <x-filament::section heading="Graph Total Pembelian Monthly" class="he-finance-section">
        <div class="h-64">
            @if(count($chartData['labels']) > 0)
                <canvas id="hutangChart" x-data="{}" x-init="
                    new Chart($el, {
                        type: 'bar',
                        data: {
                            labels: {{ json_encode($chartData['labels']) }},
                            datasets: [
                                {
                                    label: 'Total (Rp)',
                                    data: {{ json_encode($chartData['totals']) }},
                                    backgroundColor: 'rgba(245, 158, 11, 0.6)',
                                    borderColor: 'rgb(245, 158, 11)',
                                    borderWidth: 1,
                                    yAxisID: 'y',
                                },
                                {
                                    label: 'Jumlah Transaksi',
                                    data: {{ json_encode($chartData['counts']) }},
                                    backgroundColor: 'rgba(239, 68, 68, 0.6)',
                                    borderColor: 'rgb(239, 68, 68)',
                                    borderWidth: 1,
                                    yAxisID: 'y1',
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: { beginAtZero: true, position: 'left', title: { display: true, text: 'Total (Rp)' } },
                                y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Jumlah' } }
                            }
                        }
                    })
                "></canvas>
            @else
                <div class="flex items-center justify-center h-full text-gray-400">Tidak ada data untuk ditampilkan</div>
            @endif
        </div>
    </x-filament::section>

    {{-- List Tempo --}}
    <x-filament::section heading="List Tempo Hutang — Belum & Sudah Terbayar" class="he-finance-section mt-4">
        <div class="overflow-x-auto">
            <table class="he-finance-table w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="text-left py-2 px-3 font-medium">Supplier</th>
                        <th class="text-left py-2 px-3 font-medium">Nomor</th>
                        <th class="text-left py-2 px-3 font-medium">Gudang</th>
                        <th class="text-left py-2 px-3 font-medium">Jatuh Tempo</th>
                        <th class="text-right py-2 px-3 font-medium">Total</th>
                        <th class="text-right py-2 px-3 font-medium">Debit</th>
                        <th class="text-right py-2 px-3 font-medium">Kredit</th>
                        <th class="text-right py-2 px-3 font-medium">Saldo</th>
                        <th class="text-left py-2 px-3 font-medium">Jurnal</th>
                        <th class="text-right py-2 px-3 font-medium">Sudah Bayar</th>
                        <th class="text-right py-2 px-3 font-medium">Sisa</th>
                        <th class="text-center py-2 px-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($listTempo as $item)
                    <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="py-2 px-3 font-medium">{{ $item['supplier'] }}</td>
                        <td class="py-2 px-3 font-mono text-xs">{{ $item['nomor'] }}</td>
                        <td class="py-2 px-3">{{ $item['gudang'] ?? '—' }}</td>
                        <td class="py-2 px-3 @if($item['jatuh_tempo_lewat']) text-danger-600 font-bold @endif">{{ $item['tgl_jatuh_tempo'] ?? '—' }}</td>
                        <td class="py-2 px-3 text-right">Rp {{ number_format($item['grand_total'], 0, ',', '.') }}</td>
                        <td class="py-2 px-3 text-right text-success-600">Rp {{ number_format($item['debit'], 0, ',', '.') }}</td>
                        <td class="py-2 px-3 text-right">Rp {{ number_format($item['credit'], 0, ',', '.') }}</td>
                        <td class="py-2 px-3 text-right font-medium">Rp {{ number_format($item['saldo'], 0, ',', '.') }}</td>
                        <td class="py-2 px-3 font-mono text-xs">{{ $item['journal_number'] ?? '—' }}</td>
                        <td class="py-2 px-3 text-right text-success-600">Rp {{ number_format($item['sudah_bayar'], 0, ',', '.') }}</td>
                        <td class="py-2 px-3 text-right @if($item['sisa'] > 0) text-danger-600 font-bold @endif">Rp {{ number_format($item['sisa'], 0, ',', '.') }}</td>
                        <td class="py-2 px-3 text-center">
                            @if($item['status'] === 'Lunas')
                                <x-filament::badge color="success">Lunas</x-filament::badge>
                            @else
                                <x-filament::badge color="warning">Belum Lunas</x-filament::badge>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="he-finance-empty py-8 text-center">Belum ada data hutang.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($listTempo->hasPages())
            <div class="he-finance-pagination mt-4 pt-4">
                <x-filament::pagination
                    :paginator="$listTempo"
                    :page-options="[10, 25, 50, 100]"
                    current-page-option-property="perPage"
                />
            </div>
        @endif
    </x-filament::section>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @endpush
</x-filament-panels::page>
