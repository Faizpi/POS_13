<x-filament-panels::page>
    <div class="flex flex-col items-center justify-center py-16 text-center">
        <div class="mb-6 rounded-full bg-gray-100 p-6 dark:bg-gray-800">
            <x-filament::icon
                icon="heroicon-o-document-text"
                class="h-12 w-12 text-gray-400 dark:text-gray-500"
            />
        </div>

        <h2 class="text-xl font-semibold text-gray-700 dark:text-gray-200">
            {{ $this->getTitle() }}
        </h2>

        <p class="mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400">
            Halaman ini masih dalam tahap pengembangan. Belum ada data atau perhitungan akuntansi yang ditampilkan.
        </p>

        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
            Implementasi akan dilanjutkan pada tahap berikutnya.
        </p>
    </div>
</x-filament-panels::page>
