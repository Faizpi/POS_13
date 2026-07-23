<x-filament-panels::page>
    @php($data = $this->getData())
    <div class="space-y-4">
        <div class="grid gap-3 md:grid-cols-2">
            <input wire:model.live="filter_from" type="date" class="fi-input block w-full" aria-label="Dari tanggal">
            <input wire:model.live="filter_to" type="date" class="fi-input block w-full" aria-label="Sampai tanggal">
        </div>
        <p class="text-sm text-gray-500">Consolidated view only. Neraca Saldo per gudang tidak tersedia karena gudang bukan entitas yang harus seimbang sendiri.</p>
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm">
                <thead><tr class="border-b text-left"><th class="p-3">Akun</th><th class="p-3 text-right">Awal Debit</th><th class="p-3 text-right">Awal Kredit</th><th class="p-3 text-right">Mutasi Debit</th><th class="p-3 text-right">Mutasi Kredit</th><th class="p-3 text-right">Akhir Debit</th><th class="p-3 text-right">Akhir Kredit</th></tr></thead>
                <tbody>@forelse($data['rows'] as $row)<tr class="border-b"><td class="p-3">{{ $row['account_code'] }} — {{ $row['account_name'] }}</td><td class="p-3 text-right">{{ $row['opening_debit'] }}</td><td class="p-3 text-right">{{ $row['opening_credit'] }}</td><td class="p-3 text-right">{{ $row['movement_debit'] }}</td><td class="p-3 text-right">{{ $row['movement_credit'] }}</td><td class="p-3 text-right">{{ $row['ending_debit'] }}</td><td class="p-3 text-right">{{ $row['ending_credit'] }}</td></tr>@empty<tr><td class="p-3 text-center text-gray-500" colspan="7">Tidak ada jurnal posted untuk periode ini.</td></tr>@endforelse</tbody>
                <tfoot><tr class="font-semibold"><td class="p-3">Total</td><td class="p-3 text-right">{{ $data['totals']['opening_debit'] }}</td><td class="p-3 text-right">{{ $data['totals']['opening_credit'] }}</td><td class="p-3 text-right">{{ $data['totals']['movement_debit'] }}</td><td class="p-3 text-right">{{ $data['totals']['movement_credit'] }}</td><td class="p-3 text-right">{{ $data['totals']['ending_debit'] }}</td><td class="p-3 text-right">{{ $data['totals']['ending_credit'] }}</td></tr></tfoot>
            </table>
        </div>
    </div>
</x-filament-panels::page>
