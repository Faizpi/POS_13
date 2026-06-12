<x-filament-panels::page>
    @php $data = $this->getData(); @endphp

    {{-- Accordion per gudang --}}
    <div class="space-y-4">
        @forelse($data['gudangs'] as $gudang)
            <x-filament::section collapsible :collapsed="false">
                <x-slot name="heading">
                    <div class="flex items-center justify-between w-full">
                        <span class="font-semibold">{{ $gudang->nama_gudang }}</span>
                        <x-filament::badge color="gray">
                            {{ $gudang->gudangProduks->count() }} produk ·
                            Total: {{ number_format($gudang->gudangProduks->sum('stok')) }}
                        </x-filament::badge>
                    </div>
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left py-2 px-3 font-medium text-gray-600 dark:text-gray-400">Produk</th>
                                <th class="text-left py-2 px-3 font-medium text-gray-600 dark:text-gray-400">Item Code</th>
                                <th class="text-right py-2 px-3 font-medium text-gray-600 dark:text-gray-400">Penjualan</th>
                                <th class="text-right py-2 px-3 font-medium text-gray-600 dark:text-gray-400">Gratis</th>
                                <th class="text-right py-2 px-3 font-medium text-gray-600 dark:text-gray-400">Sample</th>
                                <th class="text-right py-2 px-3 font-medium text-gray-600 dark:text-gray-400 font-semibold">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($gudang->gudangProduks as $stok)
                                @if($stok->produk)
                                    <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="py-2 px-3 font-medium">{{ $stok->produk->nama_produk }}</td>
                                        <td class="py-2 px-3 text-gray-500 font-mono text-xs">{{ $stok->produk->item_code ?? '—' }}</td>
                                        <td class="py-2 px-3 text-right">{{ number_format($stok->stok_penjualan ?? 0) }}</td>
                                        <td class="py-2 px-3 text-right text-green-600">{{ number_format($stok->stok_gratis ?? 0) }}</td>
                                        <td class="py-2 px-3 text-right text-yellow-600">{{ number_format($stok->stok_sample ?? 0) }}</td>
                                        <td class="py-2 px-3 text-right font-bold">{{ number_format($stok->stok) }}</td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="6" class="py-4 text-center text-gray-500">Belum ada stok di gudang ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($gudang->gudangProduks->count() > 0)
                        <tfoot>
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                <td colspan="2" class="py-2 px-3 font-semibold text-right">Total:</td>
                                <td class="py-2 px-3 text-right font-semibold">{{ number_format($gudang->gudangProduks->sum('stok_penjualan')) }}</td>
                                <td class="py-2 px-3 text-right font-semibold text-green-600">{{ number_format($gudang->gudangProduks->sum('stok_gratis')) }}</td>
                                <td class="py-2 px-3 text-right font-semibold text-yellow-600">{{ number_format($gudang->gudangProduks->sum('stok_sample')) }}</td>
                                <td class="py-2 px-3 text-right font-bold text-primary-600">{{ number_format($gudang->gudangProduks->sum('stok')) }}</td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </x-filament::section>
        @empty
            <x-filament::section>
                <div class="text-center py-8 text-gray-500">
                    <svg class="mx-auto h-12 w-12 mb-3 opacity-40 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                    </svg>
                    <p>Belum ada data stok tersedia.</p>
                </div>
            </x-filament::section>
        @endforelse
    </div>
</x-filament-panels::page>
