<x-filament-panels::page>
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
    /* ===== Profile Hero ===== */
    .he-profile-hero {
        position: relative;
        overflow: hidden;
        border-radius: 1rem;
        background: linear-gradient(135deg, #1e40af 0%, #2563eb 40%, #7c3aed 75%, #db2777 100%);
        padding: 32px 28px 56px;
        color: #fff;
    }

    .he-profile-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            linear-gradient(rgba(255,255,255,.09) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,.09) 1px, transparent 1px);
        background-size: 36px 36px;
        pointer-events: none;
    }

    .he-profile-hero > * { position: relative; z-index: 1; }

    /* ===== Avatar ===== */
    .he-avatar-wrap {
        position: relative;
        width: 96px;
        height: 96px;
        flex-shrink: 0;
    }

    .he-avatar-img {
        width: 96px;
        height: 96px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid rgba(255,255,255,.85);
        box-shadow: 0 8px 28px rgba(0,0,0,.28), 0 0 0 6px rgba(255,255,255,.18);
        display: block;
    }

    .he-avatar-initials {
        width: 96px;
        height: 96px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, rgba(255,255,255,.32) 0%, rgba(255,255,255,.14) 100%);
        border: 3px solid rgba(255,255,255,.8);
        box-shadow: 0 8px 28px rgba(0,0,0,.28), 0 0 0 6px rgba(255,255,255,.15);
        font-size: 2.5rem;
        font-weight: 900;
        color: #ffffff;
        letter-spacing: -1px;
        backdrop-filter: blur(8px);
        text-shadow: 0 2px 8px rgba(0,0,0,.22);
    }

    /* ===== Card pulled up from hero ===== */
    .he-profile-card-wrap {
        margin-top: -36px;
        position: relative;
        z-index: 2;
        padding: 0 0 0 0;
    }

    .he-profile-inner {
        border-radius: 1rem;
        background: #fff;
        border: 1px solid rgba(148,163,184,.18);
        box-shadow: 0 8px 24px rgba(15,23,42,.07);
        padding: 20px 24px 24px;
    }

    .dark .he-profile-inner {
        background: rgb(17 24 39 / 1);
        border-color: rgba(148,163,184,.16);
        box-shadow: 0 8px 32px rgba(0,0,0,.3);
    }

    .he-profile-name {
        font-size: 1.35rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.2;
    }

    .dark .he-profile-name { color: #f8fafc; }

    .he-profile-email {
        font-size: 0.82rem;
        color: #64748b;
        margin-top: 2px;
    }

    .dark .he-profile-email { color: #94a3b8; }

    /* Info tiles */
    .he-info-tile {
        border-radius: .75rem;
        border: 1px solid #e2e8f0;
        padding: 12px 16px;
        background: #f8fafc;
    }

    .dark .he-info-tile {
        border-color: rgba(148,163,184,.18);
        background: rgba(30,41,59,.5);
    }

    .he-info-tile-label {
        font-size: .7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #94a3b8;
    }

    .he-info-tile-value {
        font-size: .875rem;
        font-weight: 600;
        color: #0f172a;
        margin-top: 4px;
    }

    .dark .he-info-tile-value { color: #f1f5f9; }

    /* Permission row */
    .he-perm-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 14px;
        border-radius: .75rem;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
    }

    .dark .he-perm-row {
        border-color: rgba(148,163,184,.18);
        background: rgba(30,41,59,.5);
    }

    .he-perm-label {
        font-size: .85rem;
        font-weight: 600;
        color: #1e293b;
    }

    .dark .he-perm-label { color: #e2e8f0; }
</style>

<div class="mx-auto w-full max-w-5xl">

    {{-- HERO STRIP --}}
    <div class="he-profile-hero">
        <div class="flex items-center gap-5">
            {{-- Avatar --}}
            <div class="he-avatar-wrap">
                @if ($user->avatar)
                    <img
                        src="{{ asset('storage/' . $user->avatar) }}"
                        alt="{{ $user->name }}"
                        class="he-avatar-img"
                    >
                @else
                    <div class="he-avatar-initials">{{ $initials }}</div>
                @endif
            </div>

            <div>
                <div class="flex flex-wrap items-center gap-1.5 mb-1">
                    <x-filament::badge :color="$roleColor">{{ $roleLabel }}</x-filament::badge>
                    @if ($currentGudang)
                        <x-filament::badge color="gray">{{ $currentGudang->nama_gudang }}</x-filament::badge>
                    @endif
                </div>
                <div class="text-xl font-black text-white leading-tight">{{ $user->name }}</div>
                <div class="text-sm text-white/75 mt-0.5">{{ $user->email }}</div>
            </div>
        </div>
    </div>

    {{-- MAIN GRID --}}
    <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)]">

        {{-- LEFT: Account info --}}
        <x-filament::section>
            <x-slot name="heading">Informasi Akun</x-slot>
            <x-slot name="description">Data utama dan kontak akun yang sedang login.</x-slot>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="he-info-tile">
                    <div class="he-info-tile-label">No. Telepon</div>
                    <div class="he-info-tile-value">{{ $user->no_telp ?: '—' }}</div>
                </div>

                <div class="he-info-tile">
                    <div class="he-info-tile-label">Gudang Default</div>
                    <div class="he-info-tile-value">{{ $user->gudang?->nama_gudang ?: '—' }}</div>
                </div>

                <div class="he-info-tile sm:col-span-2">
                    <div class="he-info-tile-label">Alamat</div>
                    <div class="he-info-tile-value whitespace-pre-line leading-6">{{ $user->alamat ?: '—' }}</div>
                </div>

                <div class="he-info-tile">
                    <div class="he-info-tile-label">Status Akun</div>
                    <div class="he-info-tile-value flex items-center gap-1.5">
                        <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
                        Aktif
                    </div>
                </div>

                <div class="he-info-tile">
                    <div class="he-info-tile-label">Role</div>
                    <div class="he-info-tile-value">{{ $roleLabel }}</div>
                </div>
            </div>
        </x-filament::section>

        {{-- RIGHT --}}
        <div class="grid gap-5 content-start">
            <x-filament::section>
                <x-slot name="heading">Hak Akses</x-slot>
                <x-slot name="description">Ringkasan izin yang aktif untuk akun ini.</x-slot>

                <div class="grid gap-2">
                    <div class="he-perm-row">
                        <span class="he-perm-label">Export PDF</span>
                        @if ($user->canExportPdf())
                            <x-filament::badge color="success">Aktif</x-filament::badge>
                        @else
                            <x-filament::badge color="gray">Nonaktif</x-filament::badge>
                        @endif
                    </div>

                    <div class="he-perm-row">
                        <span class="he-perm-label">Export Excel</span>
                        @if ($user->canExportExcel())
                            <x-filament::badge color="success">Aktif</x-filament::badge>
                        @else
                            <x-filament::badge color="gray">Nonaktif</x-filament::badge>
                        @endif
                    </div>

                    <div class="he-perm-row">
                        <span class="he-perm-label">Email Transaksi</span>
                        @if ($user->receives_transaction_email)
                            <x-filament::badge color="success">Aktif</x-filament::badge>
                        @else
                            <x-filament::badge color="gray">Nonaktif</x-filament::badge>
                        @endif
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
</div>
</x-filament-panels::page>

