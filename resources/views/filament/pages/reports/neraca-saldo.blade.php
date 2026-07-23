<x-filament-panels::page>
    @php($data = $this->getData())
    @php($isBalanced = bccomp($data['totals']['ending_debit'], $data['totals']['ending_credit'], 2) === 0)

    <div class="space-y-6">
        <x-filament::section
            heading="Periode Laporan"
            description="Neraca Saldo menampilkan saldo consolidated dari seluruh gudang."
            icon="heroicon-o-calendar-days"
            collapsible
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <label for="trial-from" class="text-sm font-medium text-gray-950 dark:text-white">Dari tanggal</label>
                    <x-filament::input.wrapper>
                        <x-filament::input id="trial-from" wire:model.live="filter_from" type="date" />
                    </x-filament::input.wrapper>
                </div>
                <div class="space-y-2">
                    <label for="trial-to" class="text-sm font-medium text-gray-950 dark:text-white">Sampai tanggal</label>
                    <x-filament::input.wrapper>
                        <x-filament::input id="trial-to" wire:model.live="filter_to" type="date" />
                    </x-filament::input.wrapper>
                </div>
            </div>
        </x-filament::section>

        <div class="flex flex-col gap-3 rounded-xl bg-primary-50 p-4 ring-1 ring-inset ring-primary-200 sm:flex-row sm:items-center sm:justify-between dark:bg-primary-400/10 dark:ring-primary-400/20">
            <div class="flex gap-3 text-sm text-primary-900 dark:text-primary-100">
                <x-filament::icon icon="heroicon-o-building-office-2" class="mt-0.5 size-5 shrink-0" />
                <div>
                    <p class="font-semibold">Consolidated view</p>
                    <p class="mt-1 leading-5">Neraca Saldo per gudang tidak tersedia karena gudang bukan entitas yang harus seimbang secara mandiri.</p>
                </div>
            </div>
            <span @class([
                'inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold',
                'bg-success-100 text-success-700 dark:bg-success-400/10 dark:text-success-300' => $isBalanced,
                'bg-danger-100 text-danger-700 dark:bg-danger-400/10 dark:text-danger-300' => ! $isBalanced,
            ])>
                <span @class(['size-1.5 rounded-full', 'bg-success-500' => $isBalanced, 'bg-danger-500' => ! $isBalanced])></span>
                {{ $isBalanced ? 'Debit = Kredit' : 'Tidak seimbang' }}
            </span>
        </div>

        <x-filament::section heading="Saldo per Akun" icon="heroicon-o-scale">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[76rem] text-sm">
                    <thead class="text-xs font-semibold text-gray-600 dark:text-gray-300">
                        <tr class="bg-gray-50 dark:bg-white/5">
                            <th rowspan="2" class="sticky left-0 z-10 min-w-64 bg-gray-50 px-4 py-3 text-left align-bottom dark:bg-gray-800">Akun</th>
                            <th colspan="2" class="border-b border-gray-100 px-4 py-2 text-center dark:border-white/5">Saldo awal</th>
                            <th colspan="2" class="border-b border-gray-100 px-4 py-2 text-center dark:border-white/5">Mutasi</th>
                            <th colspan="2" class="border-b border-gray-100 px-4 py-2 text-center dark:border-white/5">Saldo akhir</th>
                        </tr>
                        <tr class="bg-gray-50 dark:bg-white/5">
                            <th class="px-4 py-2 text-right">Debit</th>
                            <th class="px-4 py-2 text-right">Kredit</th>
                            <th class="px-4 py-2 text-right">Debit</th>
                            <th class="px-4 py-2 text-right">Kredit</th>
                            <th class="px-4 py-2 text-right">Debit</th>
                            <th class="px-4 py-2 text-right">Kredit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($data['rows'] as $row)
                            <tr class="text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5">
                                <td class="sticky left-0 z-10 bg-white px-4 py-3 dark:bg-gray-900">
                                    <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $row['account_code'] }}</span>
                                    <span class="ml-2 font-medium text-gray-950 dark:text-white">{{ $row['account_name'] }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $row['opening_debit'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $row['opening_credit'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $row['movement_debit'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $row['movement_credit'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-gray-950 dark:text-white">{{ $row['ending_debit'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-gray-950 dark:text-white">{{ $row['ending_credit'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center">
                                    <x-filament::icon icon="heroicon-o-scale" class="mx-auto size-8 text-gray-400" />
                                    <p class="mt-3 font-medium text-gray-950 dark:text-white">Belum ada saldo untuk periode ini</p>
                                    <p class="mt-1 text-gray-500 dark:text-gray-400">Jurnal posted akan dirangkum per akun di sini.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-50 font-semibold text-gray-950 dark:bg-white/5 dark:text-white">
                        <tr>
                            <td class="sticky left-0 z-10 bg-gray-50 px-4 py-3 dark:bg-gray-800">Total</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $data['totals']['opening_debit'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $data['totals']['opening_credit'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $data['totals']['movement_debit'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $data['totals']['movement_credit'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $data['totals']['ending_debit'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $data['totals']['ending_credit'] }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
