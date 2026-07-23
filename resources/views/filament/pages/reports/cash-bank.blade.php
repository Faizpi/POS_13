<x-filament-panels::page>
    @php($data = $this->getData())

    <div class="space-y-6">
        <x-filament::section
            heading="Filter Mutasi"
            description="Pilih kas atau rekening bank dan periode mutasi yang ingin ditampilkan."
            icon="heroicon-o-funnel"
            collapsible
        >
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div class="space-y-2">
                    <label for="cash-from" class="text-sm font-medium text-gray-950 dark:text-white">Dari tanggal</label>
                    <x-filament::input.wrapper>
                        <x-filament::input id="cash-from" wire:model.live.debounce.500ms="filter_from" type="date" />
                    </x-filament::input.wrapper>
                </div>
                <div class="space-y-2">
                    <label for="cash-to" class="text-sm font-medium text-gray-950 dark:text-white">Sampai tanggal</label>
                    <x-filament::input.wrapper>
                        <x-filament::input id="cash-to" wire:model.live.debounce.500ms="filter_to" type="date" />
                    </x-filament::input.wrapper>
                </div>
                <div class="space-y-2 sm:col-span-2 xl:col-span-1">
                    <label for="cash-account" class="text-sm font-medium text-gray-950 dark:text-white">Kas / bank</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="cash-account" wire:model.live.debounce.500ms="filter_cash_bank_account_id">
                            <option value="">Pilih Kas / Bank</option>
                            @foreach ($this->cashBankOptions() as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            </div>
        </x-filament::section>

        <div class="overflow-hidden rounded-xl bg-white ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <dl class="grid divide-y divide-gray-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4 dark:divide-white/5">
                <div class="px-5 py-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Saldo awal</dt>
                    <dd class="mt-1 text-lg font-semibold tabular-nums text-gray-950 dark:text-white">{{ $data['opening_balance'] }}</dd>
                </div>
                <div class="px-5 py-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Debit masuk</dt>
                    <dd class="mt-1 text-lg font-semibold tabular-nums text-success-600 dark:text-success-400">{{ $data['movement_debit'] }}</dd>
                </div>
                <div class="px-5 py-4">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Kredit keluar</dt>
                    <dd class="mt-1 text-lg font-semibold tabular-nums text-danger-600 dark:text-danger-400">{{ $data['movement_credit'] }}</dd>
                </div>
                <div class="bg-primary-50 px-5 py-4 dark:bg-primary-400/10">
                    <dt class="text-xs font-medium text-primary-700 dark:text-primary-300">Saldo akhir</dt>
                    <dd class="mt-1 text-lg font-semibold tabular-nums text-primary-700 dark:text-primary-300">{{ $data['ending_balance'] }}</dd>
                </div>
            </dl>
        </div>

        <x-filament::section
            heading="Riwayat Mutasi"
            description="Hanya jurnal posted yang memengaruhi saldo kas atau bank."
            icon="heroicon-o-arrows-up-down"
        >
            <div class="overflow-x-auto">
                <table class="w-full min-w-[58rem] text-sm">
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
                                <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums text-success-600 dark:text-success-400">{{ $row['debit'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums text-danger-600 dark:text-danger-400">{{ $row['credit'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-gray-950 dark:text-white">{{ $row['running_balance'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center">
                                    <x-filament::icon icon="heroicon-o-banknotes" class="mx-auto size-8 text-gray-400" />
                                    <p class="mt-3 font-medium text-gray-950 dark:text-white">Pilih Kas / Bank untuk melihat mutasi</p>
                                    <p class="mt-1 text-gray-500 dark:text-gray-400">Saldo awal, transaksi masuk, dan transaksi keluar akan dirangkum otomatis.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
