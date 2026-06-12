@php
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' . urlencode($url) . '&bgcolor=ffffff&color=0f172a&qzone=1&format=svg';
@endphp

<div class="flex flex-col items-center gap-5 py-2 text-center">

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <img
            src="{{ $qrUrl }}"
            alt="QR Code"
            width="200"
            height="200"
            class="block"
            loading="lazy"
        />
    </div>

    <div class="w-full max-w-sm">
        <p class="mb-1.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">URL Publik</p>
        <div class="flex overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600">
            <input
                id="qr-public-url"
                type="text"
                value="{{ $url }}"
                readonly
                class="min-w-0 flex-1 bg-gray-50 px-3 py-2 font-mono text-xs text-gray-700 outline-none dark:bg-gray-800 dark:text-gray-300"
            />
            <button
                type="button"
                id="qr-copy-btn"
                onclick="
                    navigator.clipboard.writeText(document.getElementById('qr-public-url').value).then(function() {
                        var btn = document.getElementById('qr-copy-btn');
                        var original = btn.innerHTML;
                        btn.textContent = 'Tersalin!';
                        btn.style.backgroundColor = '#16a34a';
                        setTimeout(function() { btn.innerHTML = original; btn.style.backgroundColor = ''; }, 2000);
                    });
                "
                class="shrink-0 bg-blue-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
            >
                Salin
            </button>
        </div>
    </div>

    <a
        href="{{ $url }}"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
    >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
        </svg>
        Buka Halaman Publik
    </a>

    <p class="text-xs text-gray-400 dark:text-gray-500">
        Pelanggan dapat scan kode ini tanpa perlu login.
    </p>

</div>
