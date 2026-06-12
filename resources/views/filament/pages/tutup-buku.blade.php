<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Info --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-information-circle class="w-5 h-5 text-primary-500" />
                    Informasi Fitur
                </div>
            </x-slot>
            <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                <p><strong>Backup Database:</strong> Mengunduh file SQL dump dari seluruh database. Berguna untuk cadangan penuh sebelum melakukan tutup buku.</p>
                <p><strong>Export Data &amp; Lampiran:</strong> Mengunduh file ZIP berisi CSV seluruh transaksi tahun terpilih beserta folder lampiran fotonya.</p>
                <p><strong>Tutup Buku Tahunan:</strong> Memindahkan data transaksi ke tabel arsip agar database utama tetap ringan. Semua transaksi tahun tersebut tidak boleh ada yang berstatus <code class="px-1 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs">Pending</code>.</p>
            </div>
        </x-filament::section>

        {{-- Tabel Riwayat Tutup Buku --}}
        <x-filament::section>
            <x-slot name="heading">Riwayat Tutup Buku</x-slot>
            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
