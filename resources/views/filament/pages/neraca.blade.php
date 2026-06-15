<x-filament-panels::page>
    @php $data = $this->getData(); @endphp

    {{-- Filter info bar --}}
    <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-2">
        <x-filament::icon icon="heroicon-o-funnel" class="w-4 h-4" />
        <span>
            Periode:
            <strong class="text-gray-700 dark:text-gray-200">
                {{ $this->filter_from ? \Carbon\Carbon::parse($this->filter_from)->format('d/m/Y') : 'Semua' }}
                —
                {{ $this->filter_to ? \Carbon\Carbon::parse($this->filter_to)->format('d/m/Y') : 'Semua' }}
            </strong>
            @if($this->filter_gudang_id)
                · Gudang: <strong class="text-gray-700 dark:text-gray-200">{{ \App\Models\Gudang::find($this->filter_gudang_id)?->nama_gudang }}</strong>
            @else
                · <span>Semua Gudang</span>
            @endif
        </span>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">

        {{-- OMSET PERGUDANG --}}
        <x-filament::section>
            <div class="flex items-start gap-3">
                <div class="rounded-lg bg-primary-50 dark:bg-primary-950 p-2">
                    <x-filament::icon icon="heroicon-o-banknotes" class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div class="flex-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Omset Pergudang</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        Rp {{ number_format($data['total_omset'], 0, ',', '.') }}
                    </p>
                    @if($data['omset']->count() > 1)
                        <div class="mt-2 space-y-1">
                            @foreach($data['omset'] as $item)
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span>{{ $item['gudang'] }}</span>
                                    <span>Rp {{ number_format($item['total'], 0, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </x-filament::section>

        {{-- NILAI PEMBELIAN --}}
        <x-filament::section>
            <div class="flex items-start gap-3">
                <div class="rounded-lg bg-warning-50 dark:bg-warning-950 p-2">
                    <x-filament::icon icon="heroicon-o-shopping-bag" class="w-6 h-6 text-warning-600 dark:text-warning-400" />
                </div>
                <div class="flex-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Nilai Pembelian Gudang</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        Rp {{ number_format($data['total_pembelian'], 0, ',', '.') }}
                    </p>
                    @if($data['pembelian']->count() > 1)
                        <div class="mt-2 space-y-1">
                            @foreach($data['pembelian'] as $item)
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span>{{ $item['gudang'] }}</span>
                                    <span>Rp {{ number_format($item['total'], 0, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </x-filament::section>

        {{-- PEMBAYARAN BELUM LUNAS --}}
        <x-filament::section>
            <div class="flex items-start gap-3">
                <div class="rounded-lg bg-danger-50 dark:bg-danger-950 p-2">
                    <x-filament::icon icon="heroicon-o-exclamation-circle" class="w-6 h-6 text-danger-600 dark:text-danger-400" />
                </div>
                <div class="flex-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Pembayaran Belum Lunas</p>
                    <p class="text-2xl font-bold text-danger-600 dark:text-danger-400">
                        Rp {{ number_format($data['total_belum_lunas'], 0, ',', '.') }}
                    </p>
                    @if($data['belum_lunas']->count() > 1)
                        <div class="mt-2 space-y-1">
                            @foreach($data['belum_lunas'] as $item)
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span>{{ $item['gudang'] }}</span>
                                    <span>Rp {{ number_format($item['total'], 0, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </x-filament::section>

        {{-- PENJUALAN RETAIL --}}
        <x-filament::section>
            <div class="flex items-start gap-3">
                <div class="rounded-lg bg-success-50 dark:bg-success-950 p-2">
                    <x-filament::icon icon="heroicon-o-shopping-cart" class="w-6 h-6 text-success-600 dark:text-success-400" />
                </div>
                <div class="flex-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Nilai Penjualan Gudang Retail</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        Rp {{ number_format($data['total_retail'], 0, ',', '.') }}
                    </p>
                    <p class="text-xs text-gray-400 mt-1">{{ number_format($data['qty_retail'], 0, ',', '.') }} unit terjual</p>
                </div>
            </div>
        </x-filament::section>

        {{-- PENJUALAN GROSIR --}}
        <x-filament::section>
            <div class="flex items-start gap-3">
                <div class="rounded-lg bg-info-50 dark:bg-info-950 p-2">
                    <x-filament::icon icon="heroicon-o-cube" class="w-6 h-6 text-info-600 dark:text-info-400" />
                </div>
                <div class="flex-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Nilai Penjualan Gudang Grosir</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        Rp {{ number_format($data['total_grosir'], 0, ',', '.') }}
                    </p>
                    <p class="text-xs text-gray-400 mt-1">{{ number_format($data['qty_grosir'], 0, ',', '.') }} unit terjual</p>
                </div>
            </div>
        </x-filament::section>

        {{-- RINGKASAN MARGIN --}}
        <x-filament::section>
            <div class="flex items-start gap-3">
                <div class="rounded-lg bg-gray-100 dark:bg-gray-800 p-2">
                    <x-filament::icon icon="heroicon-o-calculator" class="w-6 h-6 text-gray-600 dark:text-gray-400" />
                </div>
                <div class="flex-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Jumlah Produk Terjual Retail</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ number_format($data['qty_retail'], 0, ',', '.') }} unit
                    </p>
                    <p class="text-xs text-gray-400 mt-1">Grosir: {{ number_format($data['qty_grosir'], 0, ',', '.') }} unit</p>
                </div>
            </div>
        </x-filament::section>

    </div>

</x-filament-panels::page>
