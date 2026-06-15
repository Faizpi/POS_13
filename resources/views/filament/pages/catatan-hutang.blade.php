<x-filament-panels::page>
    @php $catatan = $this->getCatatanHutang(); @endphp

    <div class="flex items-center text-sm text-gray-500 mb-4">
        Total <strong class="text-gray-700 dark:text-gray-200 mx-1">{{ count($catatan) }}</strong> kontak memiliki hutang
    </div>

    <div class="space-y-3">
        @forelse($catatan as $item)
        <x-filament::section collapsible :collapsed="count($catatan) > 5">
            <x-slot name="heading">
                <div class="flex items-center justify-between w-full">
                    <span class="font-semibold">{{ $item['kontak']->nama }}</span>
                    <span class="text-sm">
                        <x-filament::badge color="{{ $item['total_sisa'] > 0 ? 'danger' : 'success' }}">
                            Rp {{ number_format($item['total_sisa'], 0, ',', '.') }}
                        </x-filament::badge>
                    </span>
                </div>
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left py-2 px-3 font-medium">No Transaksi</th>
                            <th class="text-left py-2 px-3 font-medium">Gudang</th>
                            <th class="text-left py-2 px-3 font-medium">Jatuh Tempo</th>
                            <th class="text-right py-2 px-3 font-medium">Total</th>
                            <th class="text-right py-2 px-3 font-medium">Sudah Bayar</th>
                            <th class="text-right py-2 px-3 font-medium">Sisa</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($item['items'] as $trx)
                        <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="py-2 px-3 font-mono text-xs">{{ $trx['nomor'] }}</td>
                            <td class="py-2 px-3">{{ $trx['gudang'] }}</td>
                            <td class="py-2 px-3 @if($trx['jatuh_tempo_lewat'] && $trx['sisa'] > 0) text-danger-600 font-bold @endif">
                                {{ $trx['tgl_jatuh_tempo'] }}
                            </td>
                            <td class="py-2 px-3 text-right">Rp {{ number_format($trx['grand_total'], 0, ',', '.') }}</td>
                            <td class="py-2 px-3 text-right text-success-600">Rp {{ number_format($trx['sudah_bayar'], 0, ',', '.') }}</td>
                            <td class="py-2 px-3 text-right @if($trx['sisa'] > 0) text-danger-600 font-bold @endif">
                                Rp {{ number_format($trx['sisa'], 0, ',', '.') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50 dark:bg-gray-800 font-semibold">
                            <td colspan="3" class="py-2 px-3 text-right">Total {{ $item['kontak']->nama }}:</td>
                            <td class="py-2 px-3 text-right">Rp {{ number_format($item['total_hutang'], 0, ',', '.') }}</td>
                            <td class="py-2 px-3 text-right text-success-600">Rp {{ number_format($item['total_hutang'] - $item['total_sisa'], 0, ',', '.') }}</td>
                            <td class="py-2 px-3 text-right text-danger-600 font-bold">Rp {{ number_format($item['total_sisa'], 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>
        @empty
        <x-filament::section>
            <div class="text-center py-8 text-gray-500">
                <p>Belum ada catatan hutang.</p>
                <p class="text-xs mt-1">Hutang akan muncul ketika ada pembelian dengan status Approved.</p>
            </div>
        </x-filament::section>
        @endforelse
    </div>
</x-filament-panels::page>
