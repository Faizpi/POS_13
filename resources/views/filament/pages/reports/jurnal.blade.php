<x-filament-panels::page>
    @php($data = $this->getData())

    <div class="space-y-4">
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <div class="grid gap-3 md:grid-cols-5">
                <input wire:model.live="filter_from" type="date" class="fi-input block w-full" aria-label="Dari tanggal">
                <input wire:model.live="filter_to" type="date" class="fi-input block w-full" aria-label="Sampai tanggal">
                <select wire:model.live="filter_account_id" class="fi-select block w-full" aria-label="Akun">
                    <option value="">Semua akun</option>
                    @foreach ($this->accountOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                <input wire:model.live="filter_source" type="text" class="fi-input block w-full" placeholder="Sumber" aria-label="Sumber">
                <select wire:model.live="filter_gudang_id" class="fi-select block w-full" aria-label="Gudang">
                    <option value="">Semua gudang</option>
                    @foreach ($this->warehouseOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            @if ($data['is_management_view'])
                <p class="mt-3 text-sm text-warning-600">Management view: {{ $data['warehouse_treatment'] }}</p>
            @endif
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm">
                <thead><tr class="border-b text-left"><th class="p-3">Tanggal</th><th class="p-3">Nomor</th><th class="p-3">Sumber</th><th class="p-3 text-right">Debit</th><th class="p-3 text-right">Kredit</th></tr></thead>
                <tbody>
                    @forelse ($data['rows'] as $row)
                        <tr class="border-b"><td class="p-3">{{ $row['journal_date'] }}</td><td class="p-3">{{ $row['journal_number'] }}</td><td class="p-3">{{ $row['source_type'] }} #{{ $row['source_id'] }}</td><td class="p-3 text-right">{{ $row['total_debit'] }}</td><td class="p-3 text-right">{{ $row['total_credit'] }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="p-3 text-center text-gray-500">Tidak ada jurnal posted untuk filter ini.</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr class="font-semibold"><td colspan="3" class="p-3">Total</td><td class="p-3 text-right">{{ $data['total_debit'] }}</td><td class="p-3 text-right">{{ $data['total_credit'] }}</td></tr></tfoot>
            </table>
        </div>
    </div>
</x-filament-panels::page>
