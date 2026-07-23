<x-filament-panels::page>
    @php($data = $this->getData())
    <div class="space-y-4">
        <div class="grid gap-3 md:grid-cols-3">
            <input wire:model.live="filter_from" type="date" class="fi-input block w-full" aria-label="Dari tanggal">
            <input wire:model.live="filter_to" type="date" class="fi-input block w-full" aria-label="Sampai tanggal">
            <select wire:model.live="filter_cash_bank_account_id" class="fi-select block w-full" aria-label="Kas atau bank">
                <option value="">Pilih Kas / Bank</option>
                @foreach ($this->cashBankOptions() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <dl class="grid gap-3 sm:grid-cols-4">
            <div class="rounded-lg border p-3"><dt>Saldo awal</dt><dd class="font-semibold">{{ $data['opening_balance'] }}</dd></div>
            <div class="rounded-lg border p-3"><dt>Debit</dt><dd class="font-semibold">{{ $data['movement_debit'] }}</dd></div>
            <div class="rounded-lg border p-3"><dt>Kredit</dt><dd class="font-semibold">{{ $data['movement_credit'] }}</dd></div>
            <div class="rounded-lg border p-3"><dt>Saldo akhir</dt><dd class="font-semibold">{{ $data['ending_balance'] }}</dd></div>
        </dl>
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm">
                <thead><tr class="border-b text-left"><th class="p-3">Tanggal</th><th class="p-3">Jurnal</th><th class="p-3">Sumber</th><th class="p-3 text-right">Debit</th><th class="p-3 text-right">Kredit</th><th class="p-3 text-right">Saldo</th></tr></thead>
                <tbody>@forelse($data['rows'] as $row)<tr class="border-b"><td class="p-3">{{ $row['journal_date'] }}</td><td class="p-3">{{ $row['journal_number'] }}</td><td class="p-3">{{ $row['source_type'] }} #{{ $row['source_id'] }}</td><td class="p-3 text-right">{{ $row['debit'] }}</td><td class="p-3 text-right">{{ $row['credit'] }}</td><td class="p-3 text-right">{{ $row['running_balance'] }}</td></tr>@empty<tr><td class="p-3 text-center text-gray-500" colspan="6">Pilih Kas / Bank untuk melihat mutasi posted.</td></tr>@endforelse</tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
