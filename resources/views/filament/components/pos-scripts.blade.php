{{--
    Geolocation auto-trigger + Scanner data injection
    Render hook: BODY_END untuk semua halaman Filament
--}}
@php
    try {
        $kontakData = \Illuminate\Support\Facades\Cache::remember('pos_scanner_kontak', 300, function () {
            return \App\Models\Kontak::select('id', 'nama', 'kode_kontak')->get()
                ->map(fn($k) => ['id' => $k->id, 'kode' => $k->kode_kontak ?? '', 'nama' => $k->nama])
                ->values()->toArray();
        });

        $produkData = \Illuminate\Support\Facades\Cache::remember('pos_scanner_produk', 300, function () {
            return \App\Models\Produk::select('id', 'nama_produk', 'item_code')->get()
                ->map(fn($p) => ['id' => $p->id, 'kode' => $p->item_code ?? '', 'nama' => $p->nama_produk ?? ''])
                ->values()->toArray();
        });
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
    var ov = null, img = null, spin = null;

    function open(url) {
        if (!ov) {
            ov = document.createElement('div');
            ov.id = 'lampiran-preview';
            ov.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.88);display:none;align-items:center;justify-content:center';

            ov.addEventListener('click', function(e) {
                if (e.target === ov) close();
            });

            var x = document.createElement('button');
            x.textContent = '\u00D7';
            x.style.cssText = 'position:absolute;top:12px;right:16px;color:#fff;background:rgba(0,0,0,.5);border:none;border-radius:50%;width:44px;height:44px;font-size:28px;line-height:1;cursor:pointer;z-index:10;display:flex;align-items:center;justify-content:center';
            x.onclick = close;
            ov.appendChild(x);

            spin = document.createElement('div');
            spin.id = 'lb-spin';
            spin.textContent = '...';
            spin.style.cssText = 'position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);color:#fff;font-size:24px';
            ov.appendChild(spin);

            img = document.createElement('img');
            img.style.cssText = 'max-width:88vw;max-height:88vh;object-fit:contain;border-radius:6px';
            img.style.display = 'none';
            img.onload = function() {
                spin.style.display = 'none';
                img.style.display = 'block';
            };
            img.onerror = function() {
                spin.textContent = 'Gagal memuat gambar';
                spin.style.fontSize = '16px';
            };
            ov.appendChild(img);

            document.body.appendChild(ov);
        }
        img.style.display = 'none';
        img.src = url;
        spin.style.display = 'block';
        spin.textContent = '...';
        spin.style.fontSize = '24px';
        ov.style.display = 'flex';
    }

    function close() {
        if (!ov) return;
        ov.style.display = 'none';
        img.src = '';
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
