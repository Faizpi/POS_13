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
// Lampiran Lightbox Preview
// ================================================================
(function() {
    let overlay = null, imgEl = null, spinnerEl = null;

    function open(url) {
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.85);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .2s';
            overlay.onclick = function(e) { if (e.target === overlay) close(); };

            const closeBtn = document.createElement('button');
            closeBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
            closeBtn.style.cssText = 'position:absolute;top:16px;right:16px;color:#fff;background:rgba(0,0,0,.5);border:none;border-radius:999px;padding:8px;cursor:pointer;z-index:10;line-height:1';
            closeBtn.onclick = close;
            overlay.appendChild(closeBtn);

            spinnerEl = document.createElement('div');
            spinnerEl.innerHTML = '<svg class="animate-spin w-10 h-10 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>';
            spinnerEl.style.cssText = 'position:absolute;top:50%;left:50%;transform:translate(-50%,-50%)';
            overlay.appendChild(spinnerEl);

            imgEl = document.createElement('img');
            imgEl.style.cssText = 'max-width:90vw;max-height:90vh;object-fit:contain;border-radius:8px;opacity:0;transition:opacity .3s';
            imgEl.onload = function() { spinnerEl.style.display = 'none'; imgEl.style.opacity = '1'; };
            imgEl.onerror = function() { spinnerEl.innerHTML = '<span class="text-white/70 text-sm">Gagal memuat gambar</span>'; };
            overlay.appendChild(imgEl);

            document.body.appendChild(overlay);
        }
        imgEl.style.opacity = '0';
        imgEl.src = url;
        spinnerEl.style.display = '';
        spinnerEl.innerHTML = '<svg class="animate-spin w-10 h-10 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>';
        overlay.style.opacity = '1';
        overlay.style.pointerEvents = 'auto';
    }

    function close() {
        if (!overlay) return;
        overlay.style.opacity = '0';
        overlay.style.pointerEvents = 'none';
        setTimeout(function() { if (imgEl) imgEl.src = ''; }, 300);
    }

    window.previewLampiran = open;
})();

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
