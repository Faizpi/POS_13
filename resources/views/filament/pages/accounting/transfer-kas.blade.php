<x-filament-panels::page>
    <form wire:submit="initiateTransfer" class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Inisiasi Transfer Kas</x-slot>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium">Kas/Bank Sumber</label>
                    <select wire:model="sourceCashBankAccountId" class="mt-1 block w-full rounded-lg border-gray-300">
                        <option value="">Pilih sumber</option>
                        @foreach ($this->availableCashAccounts() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('sourceCashBankAccountId') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-medium">Kas/Bank Tujuan</label>
                    <select wire:model="destinationCashBankAccountId" class="mt-1 block w-full rounded-lg border-gray-300">
                        <option value="">Pilih tujuan</option>
                        @foreach ($this->availableCashAccounts() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('destinationCashBankAccountId') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-medium">Nominal</label>
                    <input wire:model="amount" inputmode="decimal" class="mt-1 block w-full rounded-lg border-gray-300" />
                    @error('amount') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-medium">Mode</label>
                    <select wire:model="mode" class="mt-1 block w-full rounded-lg border-gray-300">
                        <option value="direct">Langsung</option>
                        <option value="in_transit">Dalam Perjalanan</option>
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <label class="text-sm font-medium">Memo</label>
                <textarea wire:model="memo" rows="3" class="mt-1 block w-full rounded-lg border-gray-300"></textarea>
            </div>

            <div class="mt-6">
                <x-filament::button type="submit">Ajukan Transfer</x-filament::button>
            </div>
        </x-filament::section>
    </form>
</x-filament-panels::page>
