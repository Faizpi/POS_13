<x-filament-panels::page>
    @php($cashAccounts = $this->availableCashAccounts())

    <form wire:submit="initiateTransfer" class="space-y-6">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <x-filament::section
                heading="Detail Transfer"
                description="Pindahkan saldo antar kas atau rekening bank yang aktif."
                icon="heroicon-o-arrows-right-left"
            >
                <div class="space-y-6">
                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="space-y-2">
                            <label for="transfer-source" class="text-sm font-medium text-gray-950 dark:text-white">
                                Kas / bank sumber
                                <span class="text-danger-600">*</span>
                            </label>
                            <x-filament::input.wrapper :valid="! $errors->has('sourceCashBankAccountId')">
                                <x-filament::input.select
                                    id="transfer-source"
                                    wire:model="sourceCashBankAccountId"
                                    required
                                >
                                    <option value="">Pilih rekening sumber</option>
                                    @foreach ($cashAccounts as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                            @error('sourceCashBankAccountId')
                                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="transfer-destination" class="text-sm font-medium text-gray-950 dark:text-white">
                                Kas / bank tujuan
                                <span class="text-danger-600">*</span>
                            </label>
                            <x-filament::input.wrapper :valid="! $errors->has('destinationCashBankAccountId')">
                                <x-filament::input.select
                                    id="transfer-destination"
                                    wire:model="destinationCashBankAccountId"
                                    required
                                >
                                    <option value="">Pilih rekening tujuan</option>
                                    @foreach ($cashAccounts as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                            @error('destinationCashBankAccountId')
                                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="space-y-2">
                            <label for="transfer-amount" class="text-sm font-medium text-gray-950 dark:text-white">
                                Nominal transfer
                                <span class="text-danger-600">*</span>
                            </label>
                            <x-filament::input.wrapper
                                prefix="Rp"
                                :valid="! $errors->has('amount')"
                            >
                                <x-filament::input
                                    id="transfer-amount"
                                    wire:model="amount"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    placeholder="0,00"
                                    required
                                />
                            </x-filament::input.wrapper>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Saldo sumber tidak boleh menjadi negatif.</p>
                            @error('amount')
                                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="transfer-mode" class="text-sm font-medium text-gray-950 dark:text-white">
                                Proses transfer
                                <span class="text-danger-600">*</span>
                            </label>
                            <x-filament::input.wrapper :valid="! $errors->has('mode')">
                                <x-filament::input.select id="transfer-mode" wire:model.live="mode" required>
                                    <option value="direct">Transfer langsung</option>
                                    <option value="in_transit">Kas dalam perjalanan</option>
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $mode === 'in_transit'
                                    ? 'Saldo tujuan dicatat setelah penerimaan dikonfirmasi.'
                                    : 'Saldo tujuan dicatat saat transfer disetujui.' }}
                            </p>
                            @error('mode')
                                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label for="transfer-memo" class="text-sm font-medium text-gray-950 dark:text-white">
                            Catatan
                            <span class="font-normal text-gray-500 dark:text-gray-400">(opsional)</span>
                        </label>
                        <x-filament::input.wrapper :valid="! $errors->has('memo')">
                            <textarea
                                id="transfer-memo"
                                wire:model="memo"
                                rows="4"
                                maxlength="1000"
                                placeholder="Tambahkan referensi atau keterangan operasional transfer..."
                                class="block w-full resize-y border-none bg-transparent px-3 py-2 text-sm text-gray-950 outline-none placeholder:text-gray-400 focus:ring-0 disabled:text-gray-500 dark:text-white dark:placeholder:text-gray-500"
                            ></textarea>
                        </x-filament::input.wrapper>
                        @error('memo')
                            <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end rounded-lg bg-gray-50 px-4 py-3 dark:bg-white/5">
                    <x-filament::button
                        type="submit"
                        icon="heroicon-m-paper-airplane"
                        wire:loading.attr="disabled"
                        wire:target="initiateTransfer"
                    >
                        <span wire:loading.remove wire:target="initiateTransfer">Ajukan transfer</span>
                        <span wire:loading wire:target="initiateTransfer">Memproses...</span>
                    </x-filament::button>
                </div>
            </x-filament::section>

            <div class="space-y-4">
                <x-filament::section
                    heading="Alur Persetujuan"
                    icon="heroicon-o-shield-check"
                >
                    <ol class="space-y-4 text-sm">
                        <li class="flex gap-3">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary-50 text-xs font-semibold text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">1</span>
                            <div>
                                <p class="font-medium text-gray-950 dark:text-white">Ajukan transfer</p>
                                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">Permintaan disimpan dengan status menunggu persetujuan.</p>
                            </div>
                        </li>
                        <li class="flex gap-3">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary-50 text-xs font-semibold text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">2</span>
                            <div>
                                <p class="font-medium text-gray-950 dark:text-white">Verifikasi saldo</p>
                                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">Sistem memeriksa saldo posted dan mencegah pengeluaran berlebih.</p>
                            </div>
                        </li>
                        <li class="flex gap-3">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary-50 text-xs font-semibold text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">3</span>
                            <div>
                                <p class="font-medium text-gray-950 dark:text-white">Posting jurnal</p>
                                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">Jurnal transfer dibuat setelah disetujui oleh super admin.</p>
                            </div>
                        </li>
                    </ol>
                </x-filament::section>

                <div class="he-callout rounded-xl bg-warning-50 p-4 text-sm text-warning-900 dark:bg-warning-400/10 dark:text-warning-100">
                    <div class="flex gap-3">
                        <x-filament::icon icon="heroicon-o-information-circle" class="mt-0.5 size-5 shrink-0" />
                        <p class="leading-6">Pastikan sumber, tujuan, dan nominal sudah benar. Transfer yang telah diposting hanya dapat dibatalkan melalui reversal.</p>
                    </div>
                </div>
            </div>
        </div>
    </form>
</x-filament-panels::page>
