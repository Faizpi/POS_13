{{--
    Geolocation auto-trigger + Scanner data injection
    Render hook: BODY_END untuk semua halaman Filament
--}}
@php
    try {
        $kontakData = \App\Models\Kontak::select('id', 'nama', 'kode_kontak')->get()
            ->map(fn($k) => ['id' => $k->id, 'kode' => $k->kode_kontak ?? '', 'nama' => $k->nama])
            ->values()->toArray();

        $produkData = \App\Models\Produk::select('id', 'nama_produk', 'item_code')->get()
            ->map(fn($p) => ['id' => $p->id, 'kode' => $p->item_code ?? '', 'nama' => $p->nama_produk ?? ''])
            ->values()->toArray();
    } catch (\Throwable $e) {
        $kontakData = [];
        $produkData = [];
    }
@endphp

<script>
// ================================================================
// Global Scanner Data (kontak & produk for barcode lookup)
// ================================================================
window.posScannerData = {
    kontaks: @json($kontakData),
    produks: @json($produkData),
};

// ================================================================
// Geolocation: Shared logic
// ================================================================
function posSetKoordinatVieLivewire(koordinat) {
    try {
        // Try finding the input directly (most reliable in Filament)
        const input = document.querySelector('input[name="data.koordinat"], input[wire\\:model="data.koordinat"]');
        if (input) {
            // Check if already set
            if (input.value && input.value.includes(',')) {
                return; // Already set
            }
            input.value = koordinat;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            console.log('[POS] Koordinat set via DOM:', koordinat);
            return;
        }

        // Fallback: Livewire 3 component set
        const components = Livewire?.all?.() || [];
        let done = false;
        components.forEach(comp => {
            if (done) return;
            try {
                // If it's a form component, just try to set it
                comp.set('data.koordinat', koordinat);
                done = true;
                console.log('[POS] Koordinat set via Livewire:', koordinat);
            } catch {}
        });
    } catch (e) {
        console.warn('[POS] Livewire set error:', e);
    }
}

// Global function dipanggil dari suffixAction di Form PHP
window.posAutoFillKoordinat = function() {
    if (!navigator.geolocation) {
        alert('Browser tidak mendukung Geolocation.');
        return;
    }
    navigator.geolocation.getCurrentPosition(
        function(pos) {
            const lat = pos.coords.latitude.toFixed(6);
            const lng = pos.coords.longitude.toFixed(6);
            posSetKoordinatVieLivewire(lat + ', ' + lng);
        },
        function(err) {
            alert('Gagal mendapatkan lokasi: ' + err.message);
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
};

// ================================================================
// Geolocation Auto-fill — runs on create/edit pages with koordinat
// ================================================================
function posRunAutoFillGPS() {
    const path = window.location.pathname;
    const isCreateOrEdit = path.endsWith('/create') || /\/edit$/.test(path);

    const needsGeo = ['/penjualan', '/pembelian', '/kunjungan', '/biaya'].some(seg => path.includes(seg));

    if (!isCreateOrEdit || !needsGeo) return;

    if (!navigator.geolocation) {
        console.warn('[POS] Browser tidak mendukung geolocation.');
        return;
    }

    const doGetLocation = () => {
        // Cek apakah koordinat sudah terisi (DOM check)
        const input = document.querySelector('input[name="data.koordinat"], input[wire\\:model="data.koordinat"]');
        if (input && input.value && input.value.includes(',')) return;

        navigator.geolocation.getCurrentPosition(
            function (pos) {
                const lat       = pos.coords.latitude.toFixed(6);
                const lng       = pos.coords.longitude.toFixed(6);
                const koordinat = lat + ', ' + lng;
                posSetKoordinatVieLivewire(koordinat);
            },
            function (err) {
                console.warn('[POS] Geolocation error:', err.code, err.message);
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    };

    // Wait for Livewire to be ready
    if (typeof Livewire !== 'undefined') {
        Livewire.hook('commit.resolve', () => {
            if (!window._posGeoFetched) {
                window._posGeoFetched = true;
                setTimeout(doGetLocation, 800);
            }
        });
        // Fallback after 2.5s
        setTimeout(() => {
            if (!window._posGeoFetched) {
                window._posGeoFetched = true;
                doGetLocation();
            }
        }, 2500);
    } else {
        document.addEventListener('livewire:init', () => setTimeout(doGetLocation, 800), { once: true });
    }
}

document.addEventListener('DOMContentLoaded', posRunAutoFillGPS);
document.addEventListener('livewire:navigated', () => {
    window._posGeoFetched = false; // Reset the flag on navigation
    posRunAutoFillGPS();
});

// ================================================================
// Refresh GPS Button: klik icon map-pin di koordinat field
// ================================================================
document.addEventListener('click', function(e) {
    // Tangkap klik pada tombol refresh GPS (semua form)
    const btn = e.target.closest('[data-pos-action="refresh-gps"], [id="btn-refresh-location"]');
    if (btn) {
        e.preventDefault();
        e.stopPropagation();
        window.posAutoFillKoordinat();
    }
});
</script>
