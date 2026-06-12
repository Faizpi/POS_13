<div>
    @if(count($availableGudangs) > 1)
        <div class="flex items-center gap-x-3 me-4">
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400 hidden md:block">Gudang Aktif:</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="currentGudangId" wire:change="switchGudang($event.target.value)">
                    @foreach($availableGudangs as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    @endif
</div>
