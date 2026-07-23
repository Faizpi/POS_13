<x-filament-panels::page>
    @php($data = $this->getData())

    <div class="space-y-6">
        <x-filament::section
            heading="Filter Laporan"
            description="Saring jurnal posted berdasarkan periode, akun, sumber transaksi, dan gudang."
            icon="heroicon-o-funnel"
            collapsible
        >
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div class="space-y-2">
                    <label for="journal-from" class="text-sm font-medium text-gray-950 dark:text-white">Dari tanggal</label>
                    <x-filament::input.wrapper>
                        <x-filament::input id="journal-from" wire:model.live="filter_from" type="date" />
                    </x-filament::input.wrapper>
                </div>
                <div class="space-y-2">
                    <label for="journal-to" class="text-sm font-medium text-gray-950 dark:text-white">Sampai tanggal</label>
                    <x-filament::input.wrapper>
                        <x-filament::input id="journal-to" wire:model.live="filter_to" type="date" />
                    </x-filament::input.wrapper>
                </div>
                <div class="space-y-2">
                    <label for="journal-account" class="text-sm font-medium text-gray-950 dark:text-white">Akun</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="journal-account" wire:model.live="filter_account_id">
                            <option value="">Semua akun</option>
                            @foreach ($this->accountOptions() as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
                <div class="space-y-2">
                    <label for="journal-source" class="text-sm font-medium text-gray-950 dark:text-white">Sumber</label>
                    <x-filament::input.wrapper>
                        <x-filament::input id="journal-source" wire:model.live.debounce.400ms="filter_source" type="text" placeholder="Contoh: sale" />
                    </x-filament::input.wrapper>
                </div>
                <div class="space-y-2">
                    <label for="journal-warehouse" class="text-sm font-medium text-gray-950 dark:text-white">Gudang</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="journal-warehouse" wire:model.live="filter_gudang_id">
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

        <x-filament::section
            heading="Jurnal Posted"
            description="Urutan jurnal mengikuti tanggal dan sequence posting yang deterministik."
            icon="heroicon-o-book-open"
        >
            <div class="overflow-x-auto">
                <table class="w-full min-w-[46rem] text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-600 dark:bg-white/5 dark:text-gray-300">
                        <tr>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Nomor jurnal</th>
                            <th class="px-4 py-3">Sumber</th>
                            <th class="px-4 py-3 text-right">Debit</th>
                            <th class="px-4 py-3 text-right">Kredit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($data['rows'] as $row)
                            <tr class="text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5">
                                <td class="whitespace-nowrap px-4 py-3">{{ $row['journal_date'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $row['journal_number'] }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">{{ $row['source_type'] }} #{{ $row['source_id'] }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums">{{ $row['total_debit'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums">{{ $row['total_credit'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12 text-center">
                                    <x-filament::icon icon="heroicon-o-document-magnifying-glass" class="mx-auto size-8 text-gray-400" />
                                    <p class="mt-3 font-medium text-gray-950 dark:text-white">Belum ada jurnal posted</p>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ubah periode atau filter untuk melihat jurnal lainnya.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-50 font-semibold text-gray-950 dark:bg-white/5 dark:text-white">
                        <tr>
                            <td colspan="3" class="px-4 py-3">Total</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $data['total_debit'] }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $data['total_credit'] }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
