<x-filament-panels::page>
    @php($data = $this->getData())

    <div class="space-y-6">
        <x-filament::section
            heading="Filter Buku Besar"
            description="Pilih akun untuk menelusuri saldo awal, mutasi, dan saldo berjalan."
            icon="heroicon-o-funnel"
            collapsible
        >
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="space-y-2">
                    <label for="ledger-from" class="text-sm font-medium text-gray-950 dark:text-white">Dari tanggal</label>
                    <x-filament::input.wrapper>
                        <x-filament::input id="ledger-from" wire:model.live="filter_from" type="date" />
                    </x-filament::input.wrapper>
                </div>
                <div class="space-y-2">
                    <label for="ledger-to" class="text-sm font-medium text-gray-950 dark:text-white">Sampai tanggal</label>
                    <x-filament::input.wrapper>
                        <x-filament::input id="ledger-to" wire:model.live="filter_to" type="date" />
                    </x-filament::input.wrapper>
                </div>
                <div class="space-y-2">
                    <label for="ledger-account" class="text-sm font-medium text-gray-950 dark:text-white">Akun</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="ledger-account" wire:model.live="filter_account_id">
                            <option value="">Pilih akun</option>
                            @foreach ($this->accountOptions() as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
                <div class="space-y-2">
                    <label for="ledger-warehouse" class="text-sm font-medium text-gray-950 dark:text-white">Gudang</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="ledger-warehouse" wire:model.live="filter_gudang_id">
                            <option value="">Semua gudang</option>
                            @foreach ($this->warehouseOptions() as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            </div>
        </x-filament::section>

        @if ($data['is_management_view'])
            <div class="he-callout rounded-xl bg-warning-50 p-4 text-sm text-warning-900 dark:bg-warning-400/10 dark:text-warning-100">
                <div class="flex gap-3">
                    <x-filament::icon icon="heroicon-o-information-circle" class="mt-0.5 size-5 shrink-0" />
                    <p><span class="font-semibold">Management view.</span> {{ $data['warehouse_treatment'] }}</p>
                </div>
            </div>
        @endif

        <div class="he-callout overflow-hidden rounded-xl bg-white dark:bg-gray-900">
            <dl class="grid divide-y divide-gray-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4 dark:divide-white/5">
                <div class="px-5 py-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Saldo awal</dt>
                    <dd class="mt-1 text-lg font-semibold tabular-nums text-gray-950 dark:text-white">{{ $data['opening_balance'] }}</dd>
                </div>
                <div class="px-5 py-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Mutasi debit</dt>
                    <dd class="mt-1 text-lg font-semibold tabular-nums text-success-600 dark:text-success-400">{{ $data['movement_debit'] }}</dd>
                </div>
                <div class="px-5 py-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Mutasi kredit</dt>
                    <dd class="mt-1 text-lg font-semibold tabular-nums text-danger-600 dark:text-danger-400">{{ $data['movement_credit'] }}</dd>
                </div>
                <div class="bg-primary-50 px-5 py-4 dark:bg-primary-400/10">
                    <dt class="text-xs font-medium text-primary-700 dark:text-primary-300">Saldo akhir</dt>
                    <dd class="mt-1 text-lg font-semibold tabular-nums text-primary-700 dark:text-primary-300">{{ $data['ending_balance'] }}</dd>
                </div>
            </dl>
        </div>

        <x-filament::section heading="Mutasi Akun" icon="heroicon-o-list-bullet">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[54rem] text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-600 dark:bg-white/5 dark:text-gray-300">
                        <tr>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Nomor jurnal</th>
                            <th class="px-4 py-3">Sumber</th>
                            <th class="px-4 py-3 text-right">Debit</th>
                            <th class="px-4 py-3 text-right">Kredit</th>
                            <th class="px-4 py-3 text-right">Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($data['rows'] as $row)
                            <tr class="text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5">
                                <td class="whitespace-nowrap px-4 py-3">{{ $row['journal_date'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $row['journal_number'] }}</td>
                                <td class="px-4 py-3"><span class="inline-flex rounded-md bg-gray-100 px-2 py-1 text-xs font-medium dark:bg-white/10">{{ $row['source_type'] }} #{{ $row['source_id'] }}</span></td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $row['debit'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $row['credit'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-gray-950 dark:text-white">{{ $row['running_balance'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center">
                                    <x-filament::icon icon="heroicon-o-book-open" class="mx-auto size-8 text-gray-400" />
                                    <p class="mt-3 font-medium text-gray-950 dark:text-white">Pilih akun untuk melihat Buku Besar</p>
                                    <p class="mt-1 text-gray-500 dark:text-gray-400">Mutasi posted dan saldo berjalan akan ditampilkan di sini.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
