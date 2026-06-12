{{--
    Barcode Scanner Modal + Geolocation JS
    Include in any Filament CreateRecord/EditRecord page via $extraFooterActions atau view()
--}}

{{-- Modal Barcode Scanner --}}
<div
    x-data="barcodeScanner()"
    x-init="init()"
    id="barcode-scanner-modal"
    x-show="open"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95"
    style="display:none"
    class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 backdrop-blur-sm"
>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 bg-primary-600">
            <h3 class="text-white font-semibold text-lg flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" /></svg>
                <span x-text="modalTitle">Scan Barcode</span>
            </h3>
            <button @click="closeScanner()" class="text-white/80 hover:text-white transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <div class="p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400 text-center mb-3">Arahkan kamera ke barcode atau QR code</p>
            <div id="pos-reader" class="rounded-xl overflow-hidden border-2 border-gray-200 dark:border-gray-700" style="width:100%;"></div>

            <div id="pos-scanner-loading" class="text-center py-6 hidden">
                <div class="animate-spin w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full mx-auto mb-2"></div>
                <p class="text-sm text-gray-500">Memuat kamera...</p>
            </div>

            <div id="pos-scanner-result" class="mt-3 hidden">
                <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded-lg p-3 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    <span id="pos-result-text" class="text-sm text-green-700 dark:text-green-300 font-medium"></span>
                </div>
            </div>

            <div id="pos-scanner-error" class="mt-3 hidden">
                <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-lg p-3 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-red-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                    <span id="pos-error-text" class="text-sm text-red-700 dark:text-red-300"></span>
                </div>
            </div>
        </div>

        <div class="px-4 pb-4 flex justify-end">
            <button @click="closeScanner()" type="button"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                Tutup
            </button>
        </div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
window.posScanner = window.posScanner || {
    html5QrCode: null,
    currentTarget: null,
    currentCallback: null,
};

window.posFindScannerStatePath = function(trigger) {
    const root = trigger?.closest?.('[data-field-wrapper], .fi-input-wrp') || trigger?.parentElement;
    if (!root) return null;

    const candidates = [
        '[wire\\:model]',
        '[wire\\:model\\.live]',
        '[wire\\:model\\.blur]',
        '[wire\\:model\\.change]',
        '[x-data*="statePath"]',
    ];

    for (const selector of candidates) {
        const field = root.querySelector(selector);
        if (!field) continue;

        for (const attr of field.attributes || []) {
            if (attr.name.startsWith('wire:model')) return attr.value;
        }

        const alpine = field.getAttribute('x-data') || '';
        const match = alpine.match(/statePath:\s*['"]([^'"]+)['"]/);
        if (match) return match[1];
    }

    return null;
};

window.openPosScannerForField = function(event, target, title, valueKey) {
    const trigger = event?.currentTarget || event?.target;
    const statePath = window.posFindScannerStatePath(trigger);

    if (!statePath || !window.openPosScanner) {
        console.warn('[POS] Scanner target field tidak ditemukan.');
        return;
    }

    window.openPosScanner(target, title, function(item) {
        const value = item?.[valueKey];
        if (value === undefined || value === null) return;

        if (typeof Livewire !== 'undefined') {
            const components = Livewire.all?.() || [];
            for (const component of components) {
                try {
                    component.set(statePath, value);
                    return;
                } catch {}
            }
        }
    });
};

document.addEventListener('alpine:init', () => {
    Alpine.data('barcodeScanner', () => ({
        open: false,
        modalTitle: 'Scan Barcode',

        init() {
            window.openPosScanner = (target, title, callback) => {
                this.modalTitle = title || 'Scan Barcode';
                window.posScanner.currentTarget = target;
                window.posScanner.currentCallback = callback;
                this.open = true;
                this.$nextTick(() => this.startScanner());
            };
            window.closePosScanner = () => this.closeScanner();
        },

        startScanner() {
            const loading = document.getElementById('pos-scanner-loading');
            const errEl = document.getElementById('pos-scanner-error');
            const resEl = document.getElementById('pos-scanner-result');
            if (loading) loading.classList.remove('hidden');
            if (errEl) errEl.classList.add('hidden');
            if (resEl) resEl.classList.add('hidden');

            const doInit = () => this.initScanner();
            if (window.posScanner.html5QrCode) {
                window.posScanner.html5QrCode.stop().catch(() => {}).finally(doInit);
            } else {
                doInit();
            }
        },

        initScanner() {
            const loading = document.getElementById('pos-scanner-loading');
            window.posScanner.html5QrCode = new Html5Qrcode('pos-reader');
            const isProduk = window.posScanner.currentTarget === 'produk';
            const config = isProduk ? {
                fps: 10,
                qrbox: { width: 300, height: 100 },
                aspectRatio: 1.5,
                formatsToSupport: [
                    Html5QrcodeSupportedFormats.EAN_13,
                    Html5QrcodeSupportedFormats.EAN_8,
                    Html5QrcodeSupportedFormats.UPC_A,
                    Html5QrcodeSupportedFormats.CODE_128,
                    Html5QrcodeSupportedFormats.CODE_39,
                ],
            } : {
                fps: 10,
                qrbox: { width: 250, height: 250 },
                formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
            };

            window.posScanner.html5QrCode
                .start({ facingMode: 'environment' }, config, this.onSuccess.bind(this), () => {})
                .then(() => { if (loading) loading.classList.add('hidden'); })
                .catch(err => {
                    if (loading) loading.classList.add('hidden');
                    let msg = 'Tidak dapat mengakses kamera.';
                    if (err.name === 'NotAllowedError') msg = 'Izin kamera ditolak. Izinkan akses kamera di browser.';
                    else if (err.name === 'NotFoundError') msg = 'Kamera tidak ditemukan di perangkat ini.';
                    else if (err.name === 'NotReadableError') msg = 'Kamera sedang digunakan aplikasi lain.';
                    const el = document.getElementById('pos-error-text');
                    const elWrap = document.getElementById('pos-scanner-error');
                    if (el) el.textContent = msg;
                    if (elWrap) elWrap.classList.remove('hidden');
                });
        },

        onSuccess(decodedText) {
            const code = decodedText.trim();
            const dataList = window.posScanner.currentTarget === 'kontak'
                ? (window.posScannerData?.kontaks || [])
                : (window.posScannerData?.produks || []);

            let found = dataList.find(item => item.kode && item.kode === code);
            if (!found) {
                const m = code.match(/Kode:\s*([^\n\r]+)/i);
                if (m) found = dataList.find(item => item.kode && item.kode === m[1].trim());
            }

            if (found) {
                const resEl = document.getElementById('pos-scanner-result');
                const resText = document.getElementById('pos-result-text');
                if (resText) resText.textContent = `Ditemukan: ${found.nama} (${found.kode})`;
                if (resEl) resEl.classList.remove('hidden');
                if (window.posScanner.currentCallback) window.posScanner.currentCallback(found);
                setTimeout(() => this.closeScanner(), 1200);
            } else {
                const errEl = document.getElementById('pos-scanner-error');
                const errText = document.getElementById('pos-error-text');
                if (errText) errText.textContent = `Kode "${code}" tidak ditemukan dalam database.`;
                if (errEl) errEl.classList.remove('hidden');
            }
        },

        closeScanner() {
            this.open = false;
            if (window.posScanner.html5QrCode) {
                window.posScanner.html5QrCode.stop()
                    .then(() => { window.posScanner.html5QrCode.clear(); window.posScanner.html5QrCode = null; })
                    .catch(() => { window.posScanner.html5QrCode = null; });
            }
        },
    }));
});

// ================================================================
// Geolocation Auto-fill helper
// ================================================================
window.posAutoFillKoordinat = function() {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(
        function(pos) {
            const lat = pos.coords.latitude.toFixed(6);
            const lng = pos.coords.longitude.toFixed(6);
            const koordinat = lat + ', ' + lng;

            // Try Livewire v3 wire
            const trySet = () => {
                const components = Livewire?.all?.() || [];
                let done = false;
                components.forEach(comp => {
                    try {
                        // Check if this component has koordinat
                        const val = comp.get('data.koordinat');
                        if (val !== undefined && !done) {
                            comp.set('data.koordinat', koordinat);
                            done = true;
                        }
                    } catch {}
                });
                if (!done) {
                    // Fallback: find input by looking at text inputs with placeholder containing GPS
                    const inputs = document.querySelectorAll('input[wire\\:model\\.live]');
                    inputs.forEach(inp => {
                        if (inp.name && inp.name.includes('koordinat')) {
                            inp.value = koordinat;
                            inp.dispatchEvent(new Event('input'));
                        }
                    });
                }
            };

            if (typeof Livewire !== 'undefined') {
                trySet();
            } else {
                document.addEventListener('livewire:init', trySet, { once: true });
            }
        },
        function(err) { console.warn('Geolocation error:', err.message); },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
};
</script>
