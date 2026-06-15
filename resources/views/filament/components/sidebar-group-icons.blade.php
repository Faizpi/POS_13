<!-- Sidebar Group Icons: Inject icon SVG ke group label -->
<!-- Filament's constraint: group icon OR item icon, not both.
     Solusi: group icon di set null di PHP, kita inject icon via JS/DOM -->
<script>
(function() {
    const GROUP_ICONS = {
        'Neraca': `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6.75h12M3 12h12M3 17.25h12M16.5 3.75h3a1.5 1.5 0 0 1 1.5 1.5v15a1.5 1.5 0 0 1-1.5 1.5h-3a1.5 1.5 0 0 1-1.5-1.5v-15a1.5 1.5 0 0 1 1.5-1.5Z"/></svg>`,
        'Kunjungan': `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>`,
        'Biaya': `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 0 0-2.25-2.25H15a3 3 0 1 1-6 0H5.25A2.25 2.25 0 0 0 3 12m18 0v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 9m18 0V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v3"/></svg>`,
        'Piutang': `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 14.25 6-6m4.5-3.75L18.5 4.5a2.25 2.25 0 0 0-2.25-2.25H8.25A2.25 2.25 0 0 0 6 4.5l-1.5 1.5m7.5 6 6-6M6 21.75h12a2.25 2.25 0 0 0 2.25-2.25v-5.25M6 21.75a2.25 2.25 0 0 1-2.25-2.25v-5.25m0 0H6m3 0H3m6 0h6M6 21.75v-2.25m6 2.25v-2.25"/></svg>`,
        'Hutang': `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 0 4.5 6h.75m6.75 0h-.75A.75.75 0 0 0 9.75 4.5H9m8.25 9.75h1.5a1.5 1.5 0 0 0 1.5-1.5v-3a1.5 1.5 0 0 0-1.5-1.5H18M3.75 4.5 2.25 18.75l8.25-3.75 8.25 3.75L18 4.5 3.75 4.5Z"/></svg>`,
        'Gudang': `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>`,
        'Kontak': `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>`,
        'Master Data': `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12M6 12h12"/></svg>`,
    };

    function injectIcons() {
        document.querySelectorAll('.fi-sidebar-group-label').forEach(labelEl => {
            const text = labelEl.textContent.trim();
            if (GROUP_ICONS[text]) {
                // Only inject if not already injected
                if (labelEl.closest('.fi-sidebar-group').querySelector('.fi-sidebar-group-icon')) return;

                const wrapper = document.createElement('span');
                wrapper.className = 'fi-sidebar-group-icon';
                wrapper.innerHTML = GROUP_ICONS[text];
                wrapper.style.cssText = 'display:inline-flex;margin-right:0.45rem;flex-shrink:0;opacity:0.75';
                labelEl.prepend(wrapper);
            }
        });
    }

    // Run after DOM is ready and on every Livewire navigation
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectIcons);
    } else {
        injectIcons();
    }
    document.addEventListener('livewire:navigated', injectIcons);
})();
</script>
