<x-filament-panels::page class="fi-dashboard-page">
@php
    $user = auth()->user();
    $roleLabel = match ($user->role) {
        'super_admin' => 'Super Admin',
        'admin'       => 'Admin',
        'spectator'   => 'Spectator',
        default       => 'User',
    };
    $roleColor = match ($user->role) {
        'super_admin' => 'danger',
        'admin'       => 'success',
        'spectator'   => 'info',
        default       => 'primary',
    };
    $currentGudang = $user->getCurrentGudang();
    $initials = strtoupper(substr($user->name, 0, 1));
@endphp

    {{-- PROFILE HEADER CARD --}}
    <div class="fi-wi-stats-overview-stat relative mb-4 flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            {{-- Avatar --}}
            <div class="shrink-0">
                @if ($user->avatar)
                    <img
                        src="{{ asset('storage/' . $user->avatar) }}"
                        alt="{{ $user->name }}"
                        class="w-20 h-20 rounded-full object-cover ring-2 ring-primary-500/30 shadow-sm"
                    >
                @else
                    <div class="w-20 h-20 rounded-full flex items-center justify-center bg-primary-50 dark:bg-primary-900/50 text-primary-600 dark:text-primary-400 text-3xl font-bold ring-2 ring-primary-500/20">
                        {{ $initials }}
                    </div>
                @endif
            </div>

            {{-- Identity --}}
            <div class="min-w-0">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white leading-tight truncate">
                    {{ $user->name }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $user->email }}</p>
                <div class="flex flex-wrap items-center gap-1.5 mt-3">
                    <x-filament::badge :color="$roleColor">{{ $roleLabel }}</x-filament::badge>
                    @if ($currentGudang)
                        <x-filament::badge color="gray">{{ $currentGudang->nama_gudang }}</x-filament::badge>
                    @endif
                    <x-filament::badge color="success">Aktif</x-filament::badge>
                </div>
            </div>
        </div>
    </div>

    {{-- MAIN CONTENT --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">

        {{-- LEFT: Account info (2 cols) --}}
        <div class="md:col-span-2">
            <x-filament::section>
                <x-slot name="heading">Informasi Akun</x-slot>
                <x-slot name="description">Data utama dan kontak akun yang sedang login.</x-slot>

                <div class="grid grid-cols-2 gap-x-6 gap-y-5">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">No. Telepon</dt>
                        <dd class="text-sm font-medium text-gray-900 dark:text-white mt-1">{{ $user->no_telp ?: '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Gudang Default</dt>
                        <dd class="text-sm font-medium text-gray-900 dark:text-white mt-1">{{ $user->gudang?->nama_gudang ?: '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Status Akun</dt>
                        <dd class="text-sm font-medium text-gray-900 dark:text-white mt-1 flex items-center gap-1.5">
                            <span class="inline-block h-2 w-2 rounded-full bg-success-500"></span>
                            Aktif
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Role</dt>
                        <dd class="text-sm font-medium text-gray-900 dark:text-white mt-1">{{ $roleLabel }}</dd>
                    </div>

                    <div class="col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Alamat</dt>
                        <dd class="text-sm font-medium text-gray-900 dark:text-white mt-1 whitespace-pre-line leading-6">{{ $user->alamat ?: '—' }}</dd>
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- RIGHT: Permissions & help (1 col) --}}
        <div class="grid content-start gap-4">
            <x-filament::section>
                <x-slot name="heading">Hak Akses</x-slot>
                <x-slot name="description">Ringkasan izin yang aktif untuk akun ini.</x-slot>

                <div class="divide-y divide-gray-100 dark:divide-white/5 -my-2">
                    <div class="flex items-center justify-between py-2.5">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Export PDF</span>
                        <x-filament::badge :color="$user->canExportPdf() ? 'success' : 'gray'">
                            {{ $user->canExportPdf() ? 'Aktif' : 'Nonaktif' }}
                        </x-filament::badge>
                    </div>

                    <div class="flex items-center justify-between py-2.5">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Export Excel</span>
                        <x-filament::badge :color="$user->canExportExcel() ? 'success' : 'gray'">
                            {{ $user->canExportExcel() ? 'Aktif' : 'Nonaktif' }}
                        </x-filament::badge>
                    </div>

                    <div class="flex items-center justify-between py-2.5">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Email Transaksi</span>
                        <x-filament::badge :color="$user->receives_transaction_email ? 'success' : 'gray'">
                            {{ $user->receives_transaction_email ? 'Aktif' : 'Nonaktif' }}
                        </x-filament::badge>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Pengelolaan Profil</x-slot>
                <div class="space-y-2.5 text-sm leading-6 text-gray-600 dark:text-gray-300">
                    <p>Gunakan tombol <span class="font-semibold text-gray-900 dark:text-white">Simpan Profil</span> untuk mengubah foto, nama, nomor telepon, dan alamat.</p>
                    <p>Gunakan <span class="font-semibold text-gray-900 dark:text-white">Ganti Password</span> jika ingin memperbarui akses login.</p>
                </div>
            </x-filament::section>
        </div>

    </div>
</x-filament-panels::page>
