<x-filament-panels::page>
    @php
        $chartData = $this->getChartData();
        $summaryToko = $this->getListTokoAll();
        $listToko = $this->getListToko();
        $totalPiutang = $summaryToko->where('status', 'Approved')->sum('sisa');
        $totalLunas = $summaryToko->where('status', 'Lunas')->sum('grand_total');
    @endphp

    {{-- Info bar --}}
    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500 dark:text-gray-400 mb-2">
        <span>
            Periode:
            <strong class="text-gray-700 dark:text-gray-200">
                {{ $this->filter_from ? \Carbon\Carbon::parse($this->filter_from)->format('d/m/Y') : 'Semua' }}
                — {{ $this->filter_to ? \Carbon\Carbon::parse($this->filter_to)->format('d/m/Y') : 'Semua' }}
            </strong>
        </span>
    </div>

    {{-- Summary cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <x-filament::section>
            <p class="text-sm text-gray-500">Total Piutang Belum Lunas</p>
            <p class="text-2xl font-bold text-danger-600">Rp {{ number_format($totalPiutang, 0, ',', '.') }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500">Total Piutang Lunas</p>
            <p class="text-2xl font-bold text-success-600">Rp {{ number_format($totalLunas, 0, ',', '.') }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500">Total Transaksi Tempo</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $listToko->total() }} transaksi</p>
        </x-filament::section>
    </div>

    {{-- Chart Graph --}}
    <x-filament::section heading="Graph Total Tempo Monthly">
        <div class="h-64">
            @if(count($chartData['labels']) > 0)
                <canvas id="piutangChart" x-data="{}" x-init="
                    new Chart($el, {
                        type: 'bar',
                        data: {
                            labels: {{ json_encode($chartData['labels']) }},
                            datasets: [
                                {
                                    label: 'Total (Rp)',
                                    data: {{ json_encode($chartData['totals']) }},
                                    backgroundColor: 'rgba(59, 130, 246, 0.6)',
                                    borderColor: 'rgb(59, 130, 246)',
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

    {{-- List Toko Tempo --}}
    @if(in_array(auth()->user()?->role, ['spectator', 'super_admin']))
    <x-filament::section heading="List Toko — Tempo Belum & Sudah Terbayar" class="mt-4">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="text-left py-2 px-3 font-medium">Pelanggan</th>
                        <th class="text-left py-2 px-3 font-medium">Nomor</th>
                        <th class="text-left py-2 px-3 font-medium">Gudang</th>
                        <th class="text-left py-2 px-3 font-medium">Jatuh Tempo</th>
                        <th class="text-right py-2 px-3 font-medium">Total</th>
                        <th class="text-right py-2 px-3 font-medium">Sudah Bayar</th>
                        <th class="text-right py-2 px-3 font-medium">Sisa</th>
                        <th class="text-center py-2 px-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($listToko as $item)
                    <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="py-2 px-3 font-medium">{{ $item['pelanggan'] ?? '—' }}</td>
                        <td class="py-2 px-3 font-mono text-xs">{{ $item['nomor'] }}</td>
                        <td class="py-2 px-3">{{ $item['gudang'] ?? '—' }}</td>
                        <td class="py-2 px-3 @if($item['jatuh_tempo_lewat']) text-danger-600 font-bold @endif">
                            {{ $item['tgl_jatuh_tempo'] ?? '—' }}
                        </td>
                        <td class="py-2 px-3 text-right">Rp {{ number_format($item['grand_total'], 0, ',', '.') }}</td>
                        <td class="py-2 px-3 text-right text-success-600">Rp {{ number_format($item['sudah_bayar'], 0, ',', '.') }}</td>
                        <td class="py-2 px-3 text-right @if($item['sisa'] > 0) text-danger-600 font-bold @endif">
                            Rp {{ number_format($item['sisa'], 0, ',', '.') }}
                        </td>
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
                        <td colspan="8" class="py-8 text-center text-gray-500">Belum ada data piutang.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($listToko->hasPages())
            <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                {{ $listToko->links() }}
            </div>
        @endif
    </x-filament::section>
    @endif

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @endpush
</x-filament-panels::page>
