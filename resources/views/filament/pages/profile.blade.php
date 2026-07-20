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

    <style>
        .he-profile-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 1.25rem;
            align-items: start;
        }

        .he-profile-stack {
            display: grid;
            gap: 1.25rem;
            min-width: 0;
        }

        @media (min-width: 1024px) {
            .he-profile-layout {
                grid-template-columns: minmax(17rem, 0.8fr) minmax(0, 2fr);
                gap: 1.5rem;
            }

            .he-profile-sidebar {
                position: sticky;
                top: 1.5rem;
            }
        }
    </style>

    {{-- Desktop: profile card left, three account sections right. Mobile: single column. --}}
    <div class="he-profile-layout">
        <aside class="he-profile-sidebar min-w-0" aria-label="Ringkasan profil">
            <x-filament::section>
                <div class="flex flex-col items-center text-center">
                    @if ($user->avatar)
                        <img
                            src="{{ asset('storage/' . $user->avatar) }}"
                            alt="Foto profil {{ $user->name }}"
                            class="size-24 rounded-xl object-cover shadow-sm ring-1 ring-gray-950/10 dark:ring-white/10"
                        >
                    @else
                        <div class="flex size-24 items-center justify-center rounded-xl bg-primary-50 text-3xl font-semibold text-primary-600 ring-1 ring-primary-600/10 dark:bg-primary-950 dark:text-primary-400 dark:ring-primary-400/20" aria-hidden="true">
                            {{ $initials }}
                        </div>
                    @endif

                    <div class="mt-5 min-w-0 w-full">
                        <h2 class="truncate text-xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $user->name }}</h2>
                        <p class="mt-1 break-all text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                    </div>

                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                        <x-filament::badge :color="$roleColor">{{ $roleLabel }}</x-filament::badge>
                        <x-filament::badge color="success">Aktif</x-filament::badge>
                    </div>
                </div>

                <dl class="mt-6 divide-y divide-gray-100 border-t border-gray-100 dark:divide-white/5 dark:border-white/10">
                    <div class="flex items-start justify-between gap-4 py-3">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Role</dt>
                        <dd class="text-right text-sm font-medium text-gray-950 dark:text-white">{{ $roleLabel }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 py-3">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Gudang aktif</dt>
                        <dd class="text-right text-sm font-medium text-gray-950 dark:text-white">{{ $currentGudang?->nama_gudang ?: '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 pt-3">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Status akun</dt>
                        <dd class="flex items-center gap-2 text-sm font-medium text-gray-950 dark:text-white">
                            <span class="size-2 rounded-full bg-success-500" aria-hidden="true"></span>
                            Aktif
                        </dd>
                    </div>
                </dl>
            </x-filament::section>
        </aside>

        <div class="he-profile-stack">
            <x-filament::section icon="heroicon-o-identification">
                <x-slot name="heading">Informasi akun</x-slot>
                <x-slot name="description">Data utama dan kontak akun yang sedang digunakan.</x-slot>

                <dl class="grid grid-cols-1 gap-x-8 sm:grid-cols-2">
                    <div class="border-b border-gray-100 py-3 dark:border-white/5">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Nomor telepon</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $user->no_telp ?: '—' }}</dd>
                    </div>
                    <div class="border-b border-gray-100 py-3 dark:border-white/5">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Gudang default</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $user->gudang?->nama_gudang ?: '—' }}</dd>
                    </div>
                    <div class="border-b border-gray-100 py-3 dark:border-white/5">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Role</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $roleLabel }}</dd>
                    </div>
                    <div class="border-b border-gray-100 py-3 dark:border-white/5">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Status akun</dt>
                        <dd class="mt-1 flex items-center gap-2 text-sm font-medium text-gray-950 dark:text-white">
                            <span class="size-2 rounded-full bg-success-500" aria-hidden="true"></span>
                            Aktif
                        </dd>
                    </div>
                    <div class="pt-3 sm:col-span-2">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Alamat</dt>
                        <dd class="mt-1 whitespace-pre-line text-sm font-medium leading-6 text-gray-950 dark:text-white">{{ $user->alamat ?: '—' }}</dd>
                    </div>
                </dl>
            </x-filament::section>

            <x-filament::section icon="heroicon-o-shield-check">
                <x-slot name="heading">Hak akses</x-slot>
                <x-slot name="description">Ringkasan izin dan notifikasi yang aktif untuk akun ini.</x-slot>

                <div class="divide-y divide-gray-100 dark:divide-white/5">
                    <div class="flex items-center justify-between gap-4 py-3 first:pt-0">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Export PDF</span>
                        <x-filament::badge :color="$user->canExportPdf() ? 'success' : 'gray'">{{ $user->canExportPdf() ? 'Aktif' : 'Nonaktif' }}</x-filament::badge>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Export Excel</span>
                        <x-filament::badge :color="$user->canExportExcel() ? 'success' : 'gray'">{{ $user->canExportExcel() ? 'Aktif' : 'Nonaktif' }}</x-filament::badge>
                    </div>
                    <div class="flex items-center justify-between gap-4 pt-3">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Email transaksi</span>
                        <x-filament::badge :color="$user->receives_transaction_email ? 'success' : 'gray'">{{ $user->receives_transaction_email ? 'Aktif' : 'Nonaktif' }}</x-filament::badge>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section icon="heroicon-o-cog-6-tooth">
                <x-slot name="heading">Pengelolaan profil</x-slot>
                <x-slot name="description">Akses cepat untuk memperbarui data dan keamanan akun.</x-slot>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">Perbarui data diri</p>
                        <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">Gunakan tombol <span class="font-medium text-gray-700 dark:text-gray-200">Simpan Profil</span> untuk mengubah foto, nama, nomor telepon, dan alamat.</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">Keamanan akun</p>
                        <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">Gunakan <span class="font-medium text-gray-700 dark:text-gray-200">Ganti Password</span> untuk memperbarui akses login akun.</p>
                    </div>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
