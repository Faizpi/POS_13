<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: "Instrument Sans", sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-indigo-50 h-full min-h-screen flex items-center justify-center p-4 antialiased">
    <div class="max-w-xl w-full">
        <div class="bg-white/80 backdrop-blur-sm rounded-3xl shadow-xl border border-gray-100 p-10 text-center relative overflow-hidden">
            {{-- Decorative circle --}}
            <div class="absolute -top-20 -right-20 w-40 h-40 bg-blue-100/50 rounded-full blur-2xl"></div>
            <div class="absolute -bottom-20 -left-20 w-40 h-40 bg-indigo-100/50 rounded-full blur-2xl"></div>

            {{-- Icon --}}
            <div class="text-7xl mb-6 relative">@yield('icon', '🔴')</div>

            {{-- Code --}}
            <div class="text-8xl font-black text-gray-200 leading-none mb-2 relative">
                @yield('code')
            </div>

            {{-- Title --}}
            <h1 class="text-2xl font-bold text-gray-800 mb-3 relative">@yield('title')</h1>

            {{-- Description --}}
            <p class="text-gray-500 mb-8 max-w-md mx-auto relative leading-relaxed">@yield('description')</p>

            {{-- Actions --}}
            <div class="flex flex-wrap gap-3 justify-center relative">
                <a href="{{ url()->previous() }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition font-medium text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Kembali
                </a>
                <a href="{{ url('/app') }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition font-medium text-sm shadow-sm shadow-blue-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Dashboard
                </a>
                <button onclick="window.location.reload()" class="inline-flex items-center gap-2 px-5 py-2.5 border border-gray-200 text-gray-600 rounded-xl hover:bg-gray-50 transition font-medium text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Refresh
                </button>
            </div>

            {{-- Error Log Section --}}
            @if(app()->hasDebugModeEnabled() && isset($exception))
                <div x-data="{ showLog: false }" class="mt-8 relative">
                    <button @click="showLog = !showLog" class="inline-flex items-center gap-2 text-sm text-gray-400 hover:text-gray-600 transition font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span x-text="showLog ? 'Sembunyikan Error Log' : 'Show Error Log'"></span>
                        <svg class="w-3 h-3 transition" :class="showLog ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div x-show="showLog" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="mt-4">
                        <div class="bg-gray-900 rounded-xl p-5 overflow-auto max-h-80 text-left text-xs leading-relaxed">
                            <div class="flex items-center gap-2 mb-3 pb-3 border-b border-gray-700">
                                <span class="w-2 h-2 rounded-full bg-red-500"></span>
                                <span class="text-gray-400 font-medium text-xs uppercase tracking-wider">Error Details</span>
                            </div>
                            <code class="text-gray-300 block whitespace-pre-wrap font-mono">
                                <span class="text-red-400 font-semibold">{{ get_class($exception) }}</span>
                                <br><br>
                                <span class="text-yellow-300">Message:</span>
                                <span class="text-gray-100">{{ $exception->getMessage() }}</span>
                                <br><br>
                                <span class="text-yellow-300">File:</span>
                                <span class="text-blue-300">{{ $exception->getFile() }}:{{ $exception->getLine() }}</span>
                                @if($exception->getPrevious())
                                    <br><br>
                                    <span class="text-yellow-300">Previous:</span>
                                    <span class="text-gray-100">{{ $exception->getPrevious()->getMessage() }}</span>
                                @endif
                                <br><br>
                                <span class="text-yellow-300">Stack Trace:</span>
                                <br>
                                <span class="text-gray-400 text-[10px] leading-loose">{!! nl2br(e($exception->getTraceAsString())) !!}</span>
                            </code>
                        </div>
                        <div class="mt-2 text-right">
                            <button onclick="navigator.clipboard.writeText(document.querySelector('#error-trace-text').textContent)" class="text-xs text-gray-400 hover:text-gray-600 transition">
                                Salin Stack Trace
                            </button>
                        </div>
                        <pre id="error-trace-text" class="hidden">{{ get_class($exception) . ': ' . $exception->getMessage() . "\nFile: " . $exception->getFile() . ':' . $exception->getLine() . "\n\nStack Trace:\n" . $exception->getTraceAsString() }}</pre>
                    </div>
                </div>
            @elseif(!app()->hasDebugModeEnabled())
                {{-- In production, show a contact button --}}
                <div class="mt-8 text-xs text-gray-400 relative">
                    Jika masalah berlanjut, hubungi administrator.
                </div>
            @endif
        </div>
    </div>
</body>
</html>
