<!-- Sidebar Tree Line Injector: Add L-shaped tree lines + dot to each sub-menu item -->
<script>
(function() {
    function injectTreeLines() {
        document.querySelectorAll('.fi-sidebar-group-items').forEach(groupEl => {
            const items = groupEl.querySelectorAll(':scope > li.fi-sidebar-item');
            const total = items.length;
            if (total === 0) return;

            items.forEach((item, idx) => {
                if (item.querySelector(':scope > a > .fi-sidebar-tree-line')) return;

                const isFirst = idx === 0;
                const isLast = idx === total - 1;

                // Build tree line: vertical full + horizontal half-line + dot
                const wrap = document.createElement('span');
                wrap.className = 'fi-sidebar-tree-line';

                if (total === 1) {
                    // Only one item: just the dot
                    const dot = document.createElement('span');
                    dot.className = 'fi-sidebar-tree-line-dot';
                    wrap.appendChild(dot);
                } else if (isFirst) {
                    // First: half-vertical (top → middle) + horizontal + dot
                    const v = document.createElement('span');
                    v.className = 'fi-sidebar-tree-line-vertical';
                    v.style.bottom = 'auto';
                    v.style.height = 'calc(50% + 1px)';
                    wrap.appendChild(v);
                    const h = document.createElement('span');
                    h.className = 'fi-sidebar-tree-line-horizontal';
                    wrap.appendChild(h);
                    const dot = document.createElement('span');
                    dot.className = 'fi-sidebar-tree-line-dot';
                    wrap.appendChild(dot);
                } else if (isLast) {
                    // Last: half-vertical (top → middle) + horizontal + dot
                    const v = document.createElement('span');
                    v.className = 'fi-sidebar-tree-line-vertical';
                    v.style.top = 'auto';
                    v.style.bottom = '50%';
                    v.style.height = 'calc(50% + 1px)';
                    wrap.appendChild(v);
                    const h = document.createElement('span');
                    h.className = 'fi-sidebar-tree-line-horizontal';
                    wrap.appendChild(h);
                    const dot = document.createElement('span');
                    dot.className = 'fi-sidebar-tree-line-dot';
                    wrap.appendChild(dot);
                } else {
                    // Middle: full vertical + horizontal + dot
                    const v = document.createElement('span');
                    v.className = 'fi-sidebar-tree-line-vertical';
                    wrap.appendChild(v);
                    const h = document.createElement('span');
                    h.className = 'fi-sidebar-tree-line-horizontal';
                    wrap.appendChild(h);
                    const dot = document.createElement('span');
                    dot.className = 'fi-sidebar-tree-line-dot';
                    wrap.appendChild(dot);
                }

                // Append to the anchor (a) element
                const anchor = item.querySelector(':scope > a.fi-sidebar-item-btn');
                if (anchor) {
                    anchor.appendChild(wrap);
                } else {
                    item.appendChild(wrap);
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectTreeLines);
    } else {
        injectTreeLines();
    }
    document.addEventListener('livewire:navigated', injectTreeLines);
})();
</script>
