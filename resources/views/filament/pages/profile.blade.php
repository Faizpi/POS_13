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

    {{-- 2-column layout: left profile card | right 3 sections --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-start">

        {{-- LEFT: Profile card (full height of left column) --}}
        <div class="lg:col-span-4">
            <div class="fi-wi-stats-overview-stat relative flex h-full flex-col overflow-hidden rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="flex flex-col items-center gap-4 text-center sm:items-start sm:text-left lg:items-center lg:text-center">
                    {{-- Avatar --}}
                    <div class="shrink-0">
                        @if ($user->avatar)
                            <img
                                src="{{ asset('storage/' . $user->avatar) }}"
                                alt="{{ $user->name }}"
                                class="h-24 w-24 rounded-full object-cover shadow-sm ring-2 ring-primary-500/30"
                            >
                        @else
                            <div class="flex h-24 w-24 items-center justify-center rounded-full bg-primary-50 text-3xl font-bold text-primary-600 ring-2 ring-primary-500/20 dark:bg-primary-900/50 dark:text-primary-400">
                                {{ $initials }}
                            </div>
                        @endif
                    </div>

                    {{-- Identity --}}
                    <div class="min-w-0 w-full">
                        <h2 class="truncate text-2xl font-bold leading-tight text-gray-900 dark:text-white">
                            {{ $user->name }}
                        </h2>
                        <p class="mt-1 break-all text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                        <div class="mt-4 flex flex-wrap items-center justify-center gap-1.5 sm:justify-start lg:justify-center">
                            <x-filament::badge :color="$roleColor">{{ $roleLabel }}</x-filament::badge>
                            @if ($currentGudang)
                                <x-filament::badge color="gray">{{ $currentGudang->nama_gudang }}</x-filament::badge>
                            @endif
                            <x-filament::badge color="success">Aktif</x-filament::badge>
                        </div>
                    </div>
                </div>

                {{-- Extra identity rows to fill the left column --}}
                <div class="mt-6 space-y-3 border-t border-gray-100 pt-5 dark:border-white/10">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Role</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $roleLabel }}</span>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Gudang Aktif</span>
                        <span class="text-right text-sm font-medium text-gray-900 dark:text-white">{{ $currentGudang?->nama_gudang ?: '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Status</span>
                        <span class="flex items-center gap-1.5 text-sm font-medium text-gray-900 dark:text-white">
                            <span class="inline-block h-2 w-2 rounded-full bg-success-500"></span>
                            Aktif
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT: 3 sections stacked --}}
        <div class="grid content-start gap-4 lg:col-span-8">

            <x-filament::section>
                <x-slot name="heading">Informasi Akun</x-slot>
                <x-slot name="description">Data utama dan kontak akun yang sedang login.</x-slot>

                <div class="space-y-4">
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="shrink-0 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">No. Telepon</dt>
                        <dd class="text-right text-sm font-medium text-gray-900 dark:text-white">{{ $user->no_telp ?: '—' }}</dd>
                    </div>

                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="shrink-0 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Gudang Default</dt>
                        <dd class="text-right text-sm font-medium text-gray-900 dark:text-white">{{ $user->gudang?->nama_gudang ?: '—' }}</dd>
                    </div>

                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="shrink-0 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Status Akun</dt>
                        <dd class="flex items-center gap-1.5 text-sm font-medium text-gray-900 dark:text-white">
                            <span class="inline-block h-2 w-2 rounded-full bg-success-500"></span>
                            Aktif
                        </dd>
                    </div>

                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="shrink-0 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Role</dt>
                        <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ $roleLabel }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Alamat</dt>
                        <dd class="mt-1 whitespace-pre-line text-sm font-medium leading-6 text-gray-900 dark:text-white">{{ $user->alamat ?: '—' }}</dd>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Hak Akses</x-slot>
                <x-slot name="description">Ringkasan izin yang aktif untuk akun ini.</x-slot>

                <div class="-my-2 divide-y divide-gray-100 dark:divide-white/5">
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
