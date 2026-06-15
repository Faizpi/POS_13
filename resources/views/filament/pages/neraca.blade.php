<x-filament-panels::page class="fi-dashboard-page">
    @php $data = $this->getData(); @endphp

    {{-- Filter info bar --}}
    <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-2 bg-white dark:bg-gray-900 p-3 rounded-xl shadow-sm border border-gray-200 dark:border-white/10">
        <x-filament::icon icon="heroicon-o-funnel" class="w-5 h-5 text-primary-500" />
        <span>
            Periode:
            <strong class="text-gray-800 dark:text-gray-200 font-semibold">
                {{ $this->filter_from ? \Carbon\Carbon::parse($this->filter_from)->format('d/m/Y') : 'Semua' }}
                —
                {{ $this->filter_to ? \Carbon\Carbon::parse($this->filter_to)->format('d/m/Y') : 'Semua' }}
            </strong>
            @if($this->filter_gudang_id)
                <span class="mx-2 text-gray-300 dark:text-gray-600">|</span> Gudang: <strong class="text-gray-800 dark:text-gray-200">{{ \App\Models\Gudang::find($this->filter_gudang_id)?->nama_gudang }}</strong>
            @else
                <span class="mx-2 text-gray-300 dark:text-gray-600">|</span> <span class="font-medium text-gray-700 dark:text-gray-300">Semua Gudang</span>
            @endif
        </span>
    </div>

    {{-- Stats Cards (Mirip Dashboard Utama) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6 mb-6">

        {{-- OMSET PERGUDANG --}}
        <div class="fi-wi-stats-overview-stat relative flex flex-col p-6 overflow-hidden bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="flex items-center gap-4 fi-wi-stats-overview-stat-content">
                <div class="p-3 rounded-full bg-primary-50 dark:bg-primary-900/50 text-primary-600 dark:text-primary-400">
                    <x-filament::icon icon="heroicon-o-banknotes" class="w-7 h-7" />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 tracking-wider">OMSET PERGUDANG</h3>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        Rp {{ number_format($data['total_omset'], 0, ',', '.') }}
                    </p>
                </div>
            </div>
            @if($data['omset']->count() > 1)
                <div class="mt-5 pt-4 border-t border-gray-100 dark:border-white/5 space-y-2">
                    @foreach($data['omset'] as $item)
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-gray-500 dark:text-gray-400">{{ $item['gudang'] }}</span>
                            <span class="font-semibold text-gray-700 dark:text-gray-300">Rp {{ number_format($item['total'], 0, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- NILAI PEMBELIAN --}}
        <div class="fi-wi-stats-overview-stat relative flex flex-col p-6 overflow-hidden bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="flex items-center gap-4 fi-wi-stats-overview-stat-content">
                <div class="p-3 rounded-full bg-warning-50 dark:bg-warning-900/50 text-warning-600 dark:text-warning-400">
                    <x-filament::icon icon="heroicon-o-shopping-bag" class="w-7 h-7" />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 tracking-wider">NILAI PEMBELIAN</h3>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        Rp {{ number_format($data['total_pembelian'], 0, ',', '.') }}
                    </p>
                </div>
            </div>
            @if($data['pembelian']->count() > 1)
                <div class="mt-5 pt-4 border-t border-gray-100 dark:border-white/5 space-y-2">
                    @foreach($data['pembelian'] as $item)
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-gray-500 dark:text-gray-400">{{ $item['gudang'] }}</span>
                            <span class="font-semibold text-gray-700 dark:text-gray-300">Rp {{ number_format($item['total'], 0, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- PEMBAYARAN BELUM LUNAS --}}
        <div class="fi-wi-stats-overview-stat relative flex flex-col p-6 overflow-hidden bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="flex items-center gap-4 fi-wi-stats-overview-stat-content">
                <div class="p-3 rounded-full bg-danger-50 dark:bg-danger-900/50 text-danger-600 dark:text-danger-400">
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-7 h-7" />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 tracking-wider">BELUM LUNAS (PIUTANG)</h3>
                    <p class="text-2xl font-bold text-danger-600 dark:text-danger-400 mt-1">
                        Rp {{ number_format($data['total_belum_lunas'], 0, ',', '.') }}
                    </p>
                </div>
            </div>
            @if($data['belum_lunas']->count() > 1)
                <div class="mt-5 pt-4 border-t border-gray-100 dark:border-white/5 space-y-2">
                    @foreach($data['belum_lunas'] as $item)
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-gray-500 dark:text-gray-400">{{ $item['gudang'] }}</span>
                            <span class="font-semibold text-danger-600 dark:text-danger-400">Rp {{ number_format($item['total'], 0, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- PENJUALAN RETAIL --}}
        <div class="fi-wi-stats-overview-stat relative flex flex-col p-6 overflow-hidden bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="flex items-center gap-4 fi-wi-stats-overview-stat-content">
                <div class="p-3 rounded-full bg-success-50 dark:bg-success-900/50 text-success-600 dark:text-success-400">
                    <x-filament::icon icon="heroicon-o-shopping-cart" class="w-7 h-7" />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 tracking-wider">PENJUALAN RETAIL</h3>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        Rp {{ number_format($data['total_retail'], 0, ',', '.') }}
                    </p>
                    <p class="text-sm font-medium text-success-600 dark:text-success-400 mt-1.5 bg-success-50 dark:bg-success-900/30 inline-flex items-center px-2 py-0.5 rounded">
                        {{ number_format($data['qty_retail'], 0, ',', '.') }} unit terjual
                    </p>
                </div>
            </div>
        </div>

        {{-- PENJUALAN GROSIR --}}
        <div class="fi-wi-stats-overview-stat relative flex flex-col p-6 overflow-hidden bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="flex items-center gap-4 fi-wi-stats-overview-stat-content">
                <div class="p-3 rounded-full bg-info-50 dark:bg-info-900/50 text-info-600 dark:text-info-400">
                    <x-filament::icon icon="heroicon-o-cube" class="w-7 h-7" />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 tracking-wider">PENJUALAN GROSIR</h3>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        Rp {{ number_format($data['total_grosir'], 0, ',', '.') }}
                    </p>
                    <p class="text-sm font-medium text-info-600 dark:text-info-400 mt-1.5 bg-info-50 dark:bg-info-900/30 inline-flex items-center px-2 py-0.5 rounded">
                        {{ number_format($data['qty_grosir'], 0, ',', '.') }} unit terjual
                    </p>
                </div>
            </div>
        </div>

        {{-- RINGKASAN QTY TOTAL --}}
        <div class="fi-wi-stats-overview-stat relative flex flex-col p-6 overflow-hidden bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="flex items-center gap-4 fi-wi-stats-overview-stat-content">
                <div class="p-3 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                    <x-filament::icon icon="heroicon-o-calculator" class="w-7 h-7" />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 tracking-wider">TOTAL PRODUK TERJUAL</h3>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ number_format($data['qty_retail'] + $data['qty_grosir'], 0, ',', '.') }} <span class="text-sm font-medium text-gray-500">unit</span>
                    </p>
                    <div class="flex items-center gap-2 mt-1.5">
                        <span class="text-xs font-semibold text-success-600 dark:text-success-400 bg-success-50 dark:bg-success-900/30 px-2 py-0.5 rounded">Retail: {{ number_format($data['qty_retail'], 0, ',', '.') }}</span>
                        <span class="text-xs font-semibold text-info-600 dark:text-info-400 bg-info-50 dark:bg-info-900/30 px-2 py-0.5 rounded">Grosir: {{ number_format($data['qty_grosir'], 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

</x-filament-panels::page>
