@vite(['resources/css/app.css'])

<style>
    :root {
        --admin-surface-card: #ffffff;
        --admin-surface-card-muted: #f8fafc;
        --admin-border-subtle: rgba(148, 163, 184, 0.22);
        --admin-border-strong: rgba(148, 163, 184, 0.32);
        --admin-text-muted: rgb(100, 116, 139);
        --admin-table-hover: rgb(248, 250, 252);
        --admin-shadow-card: 0 10px 28px rgba(15, 23, 42, 0.06);
        --sidebar-surface: #ffffff;
        --sidebar-surface-hover: #f4f4f5;
        --sidebar-surface-active: #eff6ff;
        --sidebar-border: #e4e4e7;
        --sidebar-text: #3f3f46;
        --sidebar-text-muted: #71717a;
        --sidebar-text-active: #1d4ed8;
        --sidebar-icon: #71717a;
        --sidebar-icon-active: #2563eb;
        --sidebar-focus: rgba(37, 99, 235, 0.72);
    }

    .dark {
        --admin-surface-card: #18181b;
        --admin-surface-card-muted: #27272a;
        --admin-border-subtle: rgba(255, 255, 255, 0.10);
        --admin-border-strong: rgba(255, 255, 255, 0.16);
        --admin-text-muted: rgb(148, 163, 184);
        --admin-table-hover: rgba(255, 255, 255, 0.05);
        --admin-shadow-card: 0 12px 30px rgba(0, 0, 0, 0.24);
        --sidebar-surface: #18181b;
        --sidebar-surface-hover: #27272a;
        --sidebar-surface-active: rgba(37, 99, 235, 0.20);
        --sidebar-border: rgba(255, 255, 255, 0.10);
        --sidebar-text: #e4e4e7;
        --sidebar-text-muted: #a1a1aa;
        --sidebar-text-active: #bfdbfe;
        --sidebar-icon: #a1a1aa;
        --sidebar-icon-active: #93c5fd;
        --sidebar-focus: rgba(96, 165, 250, 0.78);
    }

    /* ===== Global Scale (Zoom Out) ===== */
    html {
        font-size: 85% !important;
    }

    /* ===== Shared Admin Card Surface ===== */
    .fi-wi-stats-overview-stat,
    .fi-dashboard-page .fi-wi-chart .fi-section,
    .he-finance-section.fi-section,
    .he-finance-filter {
        overflow: hidden;
        border: 1px solid var(--admin-border-subtle);
        border-radius: 0.875rem;
        background: var(--admin-surface-card);
        box-shadow: var(--admin-shadow-card);
    }

    /* ===== Dashboard Stats Cards ===== */
    .fi-wi-stats-overview-stat {
        min-height: 8.5rem;
    }

    .fi-wi-stats-overview-stat-content {
        gap: 0.45rem;
    }

    .fi-wi-stats-overview-stat-label {
        color: rgb(100, 116, 139);
        font-size: 0.72rem;
        font-weight: 700;
    }

    .dark .fi-wi-stats-overview-stat-label {
        color: rgb(148, 163, 184);
    }

    .fi-wi-stats-overview-stat-value {
        color: rgb(15, 23, 42) !important;
        font-size: clamp(1.2rem, 1.8vw, 1.6rem) !important;
        letter-spacing: -0.03em !important;
        font-weight: 800 !important;
        line-height: 1.15 !important;
        word-break: break-all !important;
        overflow-wrap: break-word !important;
    }

    .dark .fi-wi-stats-overview-stat-value {
        color: rgb(248, 250, 252) !important;
    }

    .fi-wi-stats-overview-stat-description {
        font-size: 0.74rem;
        font-weight: 650;
    }

    .fi-wi-stats-overview-stat-chart,
    .fi-wi-stats-overview-stat-chart canvas {
        display: none !important;
    }

    /* ===== Dashboard Charts ===== */
    .fi-dashboard-page .fi-wi-chart .fi-section {
        transition: box-shadow 0.25s ease, transform 0.25s ease;
    }

    .fi-dashboard-page .fi-wi-chart .fi-section:hover {
        box-shadow: 0 16px 40px rgba(15, 23, 42, 0.10);
        transform: translateY(-1px);
    }

    .dark .fi-dashboard-page .fi-wi-chart .fi-section:hover {
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.32);
    }

    .fi-dashboard-page .fi-wi-chart .fi-section-header {
        padding-bottom: 0.5rem;
    }

    .fi-dashboard-page .fi-wi-chart .fi-section-heading {
        font-size: 0.92rem;
        font-weight: 800;
        letter-spacing: -0.01em;
    }

    .fi-dashboard-page .fi-wi-chart .fi-section-description {
        color: rgb(100, 116, 139);
        font-size: 0.76rem;
    }

    .dark .fi-dashboard-page .fi-wi-chart .fi-section-description {
        color: rgb(148, 163, 184);
    }

    .fi-dashboard-page .fi-wi-chart .fi-section-content {
        padding-top: 0.75rem;
    }

    .fi-dashboard-page .fi-wi-chart .fi-wi-chart-canvas-ctn {
        min-height: 13rem;
        max-height: 15rem;
    }

    .fi-dashboard-page .fi-wi-chart canvas {
        max-height: 15rem;
    }

    @media (max-width: 768px) {
        .fi-dashboard-page .fi-wi-stats-overview-stat {
            min-height: 7.75rem;
        }

        .fi-dashboard-page .fi-wi-chart .fi-wi-chart-canvas-ctn {
            min-height: 11.5rem;
            max-height: 13rem;
        }
    }

    /* ===== Profile Page ===== */
    .fi-page-profile .fi-section {
        border-radius: 0.875rem;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
    }

    .dark .fi-page-profile .fi-section {
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.22);
    }

    /* ===== Modal tweaks ===== */
    .fi-modal-window {
        border-radius: 1rem !important;
    }

    .fi-modal-content {
        overflow: hidden;
    }

    /* ===== General table / resource page polish ===== */
    .fi-ta-table,
    .he-finance-table {
        border-radius: 0.75rem;
        overflow: hidden;
    }

    .he-finance-table thead th {
        background: var(--admin-surface-card-muted);
        color: var(--admin-text-muted);
    }

    .he-finance-table tbody tr:hover {
        background: var(--admin-table-hover);
    }

    .he-finance-table .he-finance-empty {
        color: var(--admin-text-muted);
    }

    .he-finance-pagination {
        border-top: 1px solid var(--admin-border-subtle);
    }

    .fi-section {
        border-radius: 0.875rem;
    }

    /* ===== Sidebar: Restrained SaaS Navigation ===== */
    .fi-sidebar {
        background: var(--sidebar-surface);
        border-inline-end: 1px solid var(--sidebar-border);
        padding-block: 10px;
    }

    .fi-sidebar-header,
    .fi-sidebar-footer {
        background: var(--sidebar-surface);
        border-color: var(--sidebar-border);
    }

    .fi-sidebar > nav {
        padding: 6px 10px 12px !important;
    }

    .fi-sidebar .fi-sidebar-item,
    .fi-sidebar .fi-sidebar-item-btn,
    .fi-sidebar .fi-sidebar-group-label,
    .fi-sidebar > nav > ul > li {
        border: 0 !important;
    }

    .fi-sidebar-nav-groups {
        gap: 0 !important;
    }

    .fi-sidebar-group + .fi-sidebar-group,
    .fi-sidebar-group + li:not(.fi-sidebar-group),
    li:not(.fi-sidebar-group) + .fi-sidebar-group {
        margin-top: 12px !important;
    }

    .fi-sidebar-group > .fi-sidebar-group-label,
    .fi-sidebar-group-label {
        min-height: 28px;
        margin: 0 !important;
        padding: 7px 10px 5px !important;
        color: var(--sidebar-text-muted) !important;
        font-size: 10px !important;
        font-weight: 650 !important;
        letter-spacing: 0.055em !important;
        line-height: 1.2 !important;
        text-transform: uppercase !important;
    }

    .fi-sidebar-group-label > button,
    .fi-sidebar-group > button:first-child {
        min-height: 28px;
        padding: 4px 0 !important;
        border-radius: 6px;
        color: var(--sidebar-text-muted) !important;
    }

    .fi-sidebar-group-label > button:hover,
    .fi-sidebar-group > button:first-child:hover {
        color: var(--sidebar-text) !important;
    }

    .fi-sidebar-group-label > button:focus-visible,
    .fi-sidebar-group > button:first-child:focus-visible {
        outline: 2px solid var(--sidebar-focus) !important;
        outline-offset: 2px;
    }

    .fi-sidebar-group-items {
        gap: 2px !important;
        margin-top: 2px !important;
        padding: 0 !important;
    }

    .fi-sidebar-group-items > li,
    .fi-sidebar-group-items .fi-sidebar-item {
        margin: 0 !important;
    }

    .fi-sidebar-item a,
    .fi-sidebar-item-btn {
        min-height: 36px !important;
        padding: 8px 10px !important;
        gap: 9px !important;
        border-radius: 7px !important;
        color: var(--sidebar-text) !important;
        transition: background-color 140ms ease, color 140ms ease, box-shadow 140ms ease !important;
    }

    .fi-sidebar-item-label,
    .fi-sidebar-item-label > span {
        color: var(--sidebar-text) !important;
        font-size: 12px !important;
        font-weight: 500 !important;
        line-height: 1.3 !important;
    }

    .fi-sidebar-item .fi-icon,
    .fi-sidebar-item-icon {
        width: 17px !important;
        height: 17px !important;
        flex: 0 0 17px !important;
        margin: 0 !important;
        color: var(--sidebar-icon) !important;
        opacity: 1 !important;
        transition: color 140ms ease !important;
    }

    .fi-sidebar-item-badge {
        padding: 2px 6px !important;
        border-radius: 999px !important;
        font-size: 10px !important;
        line-height: 1.2 !important;
    }

    .fi-sidebar-item.fi-sidebar-item-has-url a:hover,
    .fi-sidebar-item.fi-sidebar-item-has-url .fi-sidebar-item-btn:hover,
    .fi-sidebar-item a:hover,
    .fi-sidebar-item-btn:hover {
        background: var(--sidebar-surface-hover) !important;
        color: var(--sidebar-text) !important;
    }

    .fi-sidebar-item a:focus-visible,
    .fi-sidebar-item-btn:focus-visible {
        outline: 2px solid var(--sidebar-focus) !important;
        outline-offset: 2px;
    }

    .fi-sidebar-item.fi-active a,
    .fi-sidebar-item.fi-active .fi-sidebar-item-btn {
        background: var(--sidebar-surface-active) !important;
        box-shadow: inset 2px 0 0 var(--sidebar-icon-active) !important;
        color: var(--sidebar-text-active) !important;
    }

    .fi-sidebar-item.fi-active .fi-sidebar-item-label,
    .fi-sidebar-item.fi-active .fi-sidebar-item-label > span {
        color: var(--sidebar-text-active) !important;
        font-weight: 650 !important;
    }

    .fi-sidebar-item.fi-active .fi-icon,
    .fi-sidebar-item.fi-active .fi-sidebar-item-icon {
        color: var(--sidebar-icon-active) !important;
    }

    @media (max-width: 1023px) {
        .fi-sidebar > nav {
            padding-inline: 12px !important;
        }

        .fi-sidebar-item a,
        .fi-sidebar-item-btn {
            min-height: 44px !important;
            padding-block: 11px !important;
        }

        .fi-sidebar-group-label > button,
        .fi-sidebar-group > button:first-child {
            min-height: 40px;
        }
    }

    /* Pixel geometry is intentional because the global 85% root scale shrinks rem units. */
    @media (min-width: 1024px) {
        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) {
            padding-inline: 0 !important;
        }

        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) > nav {
            padding-inline: 8px !important;
        }

        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-nav-groups {
            align-items: center !important;
        }

        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-group,
        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-group-items {
            width: 100% !important;
            align-items: center !important;
        }

        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item {
            display: flex !important;
            width: 100% !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item a,
        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item-btn {
            display: flex !important;
            width: 36px !important;
            min-width: 36px !important;
            max-width: 36px !important;
            height: 36px !important;
            min-height: 36px !important;
            margin-inline: auto !important;
            padding: 0 !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 0 !important;
            overflow: hidden !important;
            border: 0 !important;
        }

        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item-grouped-border,
        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item-label,
        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item-badge,
        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item-badge-ctn,
        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-group-label,
        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-group-label-content,
        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-group-collapse-button,
        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item > :not(a):not(.fi-sidebar-item-btn) {
            display: none !important;
        }

        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item a .fi-icon,
        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item-btn .fi-icon,
        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item a .fi-sidebar-item-icon,
        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item-btn .fi-sidebar-item-icon {
            position: static !important;
            display: block !important;
            width: 18px !important;
            min-width: 18px !important;
            max-width: 18px !important;
            height: 18px !important;
            min-height: 18px !important;
            max-height: 18px !important;
            flex: 0 0 18px !important;
            margin: 0 !important;
            padding: 0 !important;
            transform: none !important;
        }

        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item.fi-active a,
        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item.fi-active .fi-sidebar-item-btn {
            box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--sidebar-icon-active) 24%, transparent) !important;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .fi-sidebar-item a,
        .fi-sidebar-item-btn,
        .fi-sidebar-item .fi-icon,
        .fi-sidebar-item-icon {
            transition-duration: 0.01ms !important;
        }
    }

    /* ===== Badge fixes ===== */
    .fi-badge {
        font-weight: 600;
    }

    /* ===== Database Notifications (dinonaktifkan) ===== */
</style>
