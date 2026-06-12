{{-- Barcode & QR display untuk Kontak --}}
@php
    $bcKode = $record->kode_kontak;
    $bcNama = $record->nama;
    $bcTelp = $record->no_telp ?? '';
    $bcElId = 'bc-kontak-' . $record->id;
    $qrElId = 'qr-kontak-' . $record->id;
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

    {{-- BARCODE --}}
    <div class="flex flex-col items-center gap-3 p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Barcode</p>
        <div class="w-full flex justify-center">
            <svg id="{{ $bcElId }}" class="max-w-full" style="height:70px"></svg>
        </div>
        <span class="font-mono text-sm font-bold text-gray-800 dark:text-gray-200">{{ $bcKode }}</span>
        <span class="text-xs text-gray-400 dark:text-gray-500">{{ $bcNama }}</span>
    </div>

    {{-- QR CODE --}}
    <div class="flex flex-col items-center gap-3 p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">QR Code</p>
        <div id="{{ $qrElId }}"></div>
        <span class="text-xs text-gray-400 dark:text-gray-500">Scan untuk info kontak</span>
    </div>

</div>

<script>
(function () {
    var kode   = @json($bcKode);
    var nama   = @json($bcNama);
    var telp   = @json($bcTelp);
    var bcElId = @json($bcElId);
    var qrElId = @json($qrElId);

    var qrData = 'Kode: ' + kode + '\nNama: ' + nama + '\nTelp: ' + telp;

    var bcDone = false;
    var qrDone = false;

    function renderBarcode() {
        if (bcDone) return;
        if (typeof JsBarcode !== 'undefined') {
            try {
                JsBarcode('#' + bcElId, kode, {
                    format: 'CODE128',
                    width: 2,
                    height: 60,
                    displayValue: false,
                    background: 'transparent',
                    lineColor: '#1e293b',
                });
                bcDone = true;
            } catch (e) { console.warn('[BC Kontak]', e); }
        } else {
            setTimeout(renderBarcode, 300);
        }
    }

    function renderQr() {
        if (qrDone) return;
        if (typeof QRCode !== 'undefined') {
            try {
                var el = document.getElementById(qrElId);
                if (el) {
                    el.innerHTML = '';
                    new QRCode(el, {
                        text: qrData,
                        width: 160,
                        height: 160,
                        colorDark: '#1e293b',
                        colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.M,
                    });
                    qrDone = true;
                }
            } catch (e) { console.warn('[QR Kontak]', e); }
        } else {
            setTimeout(renderQr, 300);
        }
    }

    function init() { renderBarcode(); renderQr(); }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    setTimeout(init, 800);
    setTimeout(init, 1500);
})();
</script>
