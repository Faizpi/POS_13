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
    }

    .dark {
        --admin-surface-card: #18181b;
        --admin-surface-card-muted: #27272a;
        --admin-border-subtle: rgba(255, 255, 255, 0.10);
        --admin-border-strong: rgba(255, 255, 255, 0.16);
        --admin-text-muted: rgb(148, 163, 184);
        --admin-table-hover: rgba(255, 255, 255, 0.05);
        --admin-shadow-card: 0 12px 30px rgba(0, 0, 0, 0.24);
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

    /* ===== Sidebar: Quiet Structured Navigation ===== */
    .fi-sidebar {
        background: #ffffff;
        border-inline-end: 1px solid var(--admin-border-subtle);
        padding: 0.75rem 0;
    }

    .dark .fi-sidebar {
        background: #111113;
    }

    .fi-sidebar > nav {
        padding: 0.5rem 0.75rem 1rem !important;
    }

    .fi-sidebar .fi-sidebar-item,
    .fi-sidebar .fi-sidebar-item-btn,
    .fi-sidebar .fi-sidebar-group-label,
    .fi-sidebar > nav > ul > li {
        border-bottom: none !important;
        border-top: none !important;
    }

    .fi-sidebar-nav-groups {
        gap: 0 !important;
    }

    .fi-sidebar-group + .fi-sidebar-group,
    .fi-sidebar-group + li:not(.fi-sidebar-group),
    li:not(.fi-sidebar-group) + .fi-sidebar-group {
        margin-top: 0.9rem !important;
    }

    .fi-sidebar-group > .fi-sidebar-group-label,
    .fi-sidebar-group-label {
        font-size: 0.7rem !important;
        font-weight: 750 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.09em !important;
        padding: 0.4rem 0.65rem 0.3rem !important;
        margin: 0 !important;
        line-height: 1.2 !important;
        color: rgb(100, 116, 139) !important;
    }

    .dark .fi-sidebar-group > .fi-sidebar-group-label,
    .dark .fi-sidebar-group-label {
        color: rgb(148, 163, 184) !important;
    }

    .fi-sidebar-group-items {
        margin-top: 0.1rem !important;
        padding: 0 !important;
        gap: 0.125rem !important;
    }

    .fi-sidebar-group-items > li,
    .fi-sidebar-group-items .fi-sidebar-item {
        margin: 0 !important;
    }

    .fi-sidebar-item a,
    .fi-sidebar-item-btn,
    .fi-sidebar-group-items a {
        min-height: 2.4rem !important;
        padding: 0.55rem 0.7rem !important;
        gap: 0.65rem !important;
        border-radius: 0.5rem !important;
        transition: background-color 180ms ease, color 180ms ease, box-shadow 180ms ease !important;
    }

    .fi-sidebar-item-label,
    .fi-sidebar-item-label > span {
        font-size: 0.86rem !important;
        font-weight: 550 !important;
        line-height: 1.25 !important;
        color: rgb(51, 65, 85) !important;
    }

    .dark .fi-sidebar-item-label,
    .dark .fi-sidebar-item-label > span {
        color: rgb(203, 213, 225) !important;
    }

    .fi-sidebar-item-icon {
        width: 1.1rem !important;
        height: 1.1rem !important;
        opacity: 0.84;
        transition: color 180ms ease, opacity 180ms ease !important;
    }

    .fi-sidebar-item-badge {
        font-size: 0.7rem !important;
        padding: 0.12rem 0.42rem !important;
    }

    .fi-sidebar-item.fi-sidebar-item-has-url a:hover,
    .fi-sidebar-item.fi-sidebar-item-has-url .fi-sidebar-item-btn:hover {
        background: rgba(15, 23, 42, 0.045) !important;
    }

    .dark .fi-sidebar-item.fi-sidebar-item-has-url a:hover,
    .dark .fi-sidebar-item.fi-sidebar-item-has-url .fi-sidebar-item-btn:hover {
        background: rgba(255, 255, 255, 0.06) !important;
    }

    .fi-sidebar-item a:focus-visible,
    .fi-sidebar-item-btn:focus-visible {
        outline: 2px solid rgba(59, 130, 246, 0.72) !important;
        outline-offset: 2px;
    }

    /* Active route uses one restrained rail, not competing blue treatments. */
    .fi-sidebar-item.fi-active a,
    .fi-sidebar-item.fi-active .fi-sidebar-item-btn {
        background: rgba(59, 130, 246, 0.07) !important;
        box-shadow: inset 3px 0 0 rgb(37, 99, 235) !important;
    }

    .dark .fi-sidebar-item.fi-active a,
    .dark .fi-sidebar-item.fi-active .fi-sidebar-item-btn {
        background: rgba(96, 165, 250, 0.10) !important;
        box-shadow: inset 3px 0 0 rgb(96, 165, 250) !important;
    }

    .fi-sidebar-item.fi-active .fi-sidebar-item-label,
    .fi-sidebar-item.fi-active .fi-sidebar-item-label > span {
        font-weight: 700 !important;
        color: rgb(30, 64, 175) !important;
    }

    .dark .fi-sidebar-item.fi-active .fi-sidebar-item-label,
    .dark .fi-sidebar-item.fi-active .fi-sidebar-item-label > span {
        color: rgb(191, 219, 254) !important;
    }

    .fi-sidebar-item.fi-active .fi-sidebar-item-icon {
        color: rgb(37, 99, 235) !important;
        opacity: 1 !important;
    }

    .dark .fi-sidebar-item.fi-active .fi-sidebar-item-icon {
        color: rgb(96, 165, 250) !important;
    }

    .fi-sidebar-group-label > button,
    .fi-sidebar-group > button:first-child {
        padding: 0.35rem 0 !important;
    }

    /* Keep Filament's desktop icon rail compact when the sidebar is minimized. */
    @media (min-width: 1024px) {
        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) {
            padding-inline: 0 !important;
        }

        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) > nav {
            padding-inline: 0.5rem !important;
        }

        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-nav-groups {
            align-items: center;
        }

        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-group,
        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-group-items,
        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item {
            width: 100%;
        }

        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item a,
        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item-btn {
            width: 2.5rem !important;
            min-height: 2.5rem !important;
            margin-inline: auto !important;
            padding: 0.625rem !important;
            justify-content: center !important;
            gap: 0 !important;
        }

        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item-grouped-border,
        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item-label,
        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item-badge-ctn {
            display: none !important;
        }

        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item-btn > .fi-icon {
            flex: none !important;
            margin: 0 !important;
        }

        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item.fi-active a,
        .fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item.fi-active .fi-sidebar-item-btn {
            box-shadow: none !important;
        }
    }

    /* ===== Badge fixes ===== */
    .fi-badge {
        font-weight: 600;
    }

    /* ===== Database Notifications (dinonaktifkan) ===== */
</style>
