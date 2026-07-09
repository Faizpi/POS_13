@vite(['resources/css/app.css'])

<style>
    /* ===== Global Scale + DealDeck-inspired shell ===== */
    html {
        font-size: 85% !important;
    }

    .fi-body {
        background:
            radial-gradient(circle at 12% 8%, rgba(99, 102, 241, 0.11), transparent 30rem),
            linear-gradient(135deg, #eef2ff 0%, #f8fafc 42%, #eef2f7 100%) !important;
    }

    .dark .fi-body {
        background:
            radial-gradient(circle at 12% 8%, rgba(99, 102, 241, 0.18), transparent 32rem),
            linear-gradient(135deg, #0f172a 0%, #111827 45%, #020617 100%) !important;
    }

    .fi-main {
        border-radius: 1.5rem 0 0 0;
    }

    .fi-main-ctn {
        background: transparent !important;
    }

    /* ===== Dashboard Stats Cards ===== */
    .fi-wi-stats-overview-stat {
        min-height: 7.5rem;
        overflow: hidden;
        border-radius: 1.25rem !important;
        border: 1px solid rgba(226, 232, 240, 0.95) !important;
        background:
            linear-gradient(145deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.92)),
            #ffffff !important;
        box-shadow:
            0 1px 0 rgba(255, 255, 255, 0.9) inset,
            0 18px 40px rgba(15, 23, 42, 0.07) !important;
        transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
    }

    .fi-wi-stats-overview-stat:hover {
        transform: translateY(-1px);
        border-color: rgba(99, 102, 241, 0.28) !important;
        box-shadow:
            0 1px 0 rgba(255, 255, 255, 0.95) inset,
            0 22px 48px rgba(79, 70, 229, 0.12) !important;
    }

    .dark .fi-wi-stats-overview-stat {
        border-color: rgba(71, 85, 105, 0.65) !important;
        background:
            linear-gradient(145deg, rgba(30, 41, 59, 0.94), rgba(15, 23, 42, 0.96)),
            #0f172a !important;
        box-shadow:
            0 1px 0 rgba(255, 255, 255, 0.05) inset,
            0 20px 46px rgba(0, 0, 0, 0.34) !important;
    }

    .dark .fi-wi-stats-overview-stat:hover {
        border-color: rgba(129, 140, 248, 0.38) !important;
        box-shadow:
            0 1px 0 rgba(255, 255, 255, 0.06) inset,
            0 24px 56px rgba(0, 0, 0, 0.42) !important;
    }

    .fi-wi-stats-overview-stat-content {
        gap: 0.5rem;
    }

    .fi-wi-stats-overview-stat-label {
        color: rgb(71, 85, 105) !important;
        font-size: 0.74rem;
        font-weight: 750;
        letter-spacing: -0.01em;
    }

    .dark .fi-wi-stats-overview-stat-label {
        color: rgb(203, 213, 225) !important;
    }

    .fi-wi-stats-overview-stat-value {
        color: rgb(15, 23, 42) !important;
        font-size: clamp(1.18rem, 1.65vw, 1.52rem) !important;
        letter-spacing: -0.045em !important;
        font-weight: 850 !important;
        line-height: 1.08 !important;
        word-break: break-word !important;
        overflow-wrap: anywhere !important;
        font-variant-numeric: tabular-nums;
    }

    .dark .fi-wi-stats-overview-stat-value {
        color: rgb(248, 250, 252) !important;
    }

    .fi-wi-stats-overview-stat-description {
        color: rgb(100, 116, 139) !important;
        font-size: 0.73rem;
        font-weight: 650;
    }

    .dark .fi-wi-stats-overview-stat-description {
        color: rgb(148, 163, 184) !important;
    }

    .fi-wi-stats-overview-stat-chart,
    .fi-wi-stats-overview-stat-chart canvas {
        display: none !important;
    }

    /* ===== Dashboard Charts ===== */
    .fi-dashboard-page .fi-wi-chart .fi-section {
        overflow: hidden;
        border-radius: 1.35rem !important;
        border: 1px solid rgba(226, 232, 240, 0.96) !important;
        background:
            linear-gradient(150deg, rgba(255,255,255,0.99) 0%, rgba(248,250,252,0.94) 100%),
            #ffffff !important;
        box-shadow:
            0 1px 0 0 rgba(255,255,255,0.95) inset,
            0 18px 42px rgba(15, 23, 42, 0.075),
            0 2px 10px rgba(15, 23, 42, 0.04) !important;
        transition: box-shadow 0.25s ease, transform 0.25s ease, border-color 0.25s ease;
    }

    .fi-dashboard-page .fi-wi-chart .fi-section:hover {
        border-color: rgba(99, 102, 241, 0.25) !important;
        box-shadow:
            0 1px 0 0 rgba(255,255,255,0.95) inset,
            0 22px 50px rgba(79, 70, 229, 0.12),
            0 5px 16px rgba(15, 23, 42, 0.06) !important;
        transform: translateY(-1px);
    }

    .dark .fi-dashboard-page .fi-wi-chart .fi-section {
        border-color: rgba(71, 85, 105, 0.62) !important;
        background:
            linear-gradient(150deg, rgba(30, 41, 59, 0.94) 0%, rgba(15, 23, 42, 0.98) 100%),
            #0f172a !important;
        box-shadow:
            0 1px 0 0 rgba(255,255,255,0.05) inset,
            0 20px 48px rgba(0, 0, 0, 0.34),
            0 2px 10px rgba(0, 0, 0, 0.2) !important;
    }

    .dark .fi-dashboard-page .fi-wi-chart .fi-section:hover {
        border-color: rgba(129, 140, 248, 0.36) !important;
        box-shadow:
            0 1px 0 0 rgba(255,255,255,0.06) inset,
            0 24px 58px rgba(0, 0, 0, 0.42),
            0 5px 16px rgba(0, 0, 0, 0.28) !important;
        transform: translateY(-1px);
    }

    .fi-dashboard-page .fi-wi-chart .fi-section-header {
        padding-bottom: 0.45rem;
    }

    .fi-dashboard-page .fi-wi-chart .fi-section-heading {
        color: rgb(15, 23, 42) !important;
        font-size: 0.95rem;
        font-weight: 850;
        letter-spacing: -0.02em;
    }

    .dark .fi-dashboard-page .fi-wi-chart .fi-section-heading {
        color: rgb(248, 250, 252) !important;
    }

    .fi-dashboard-page .fi-wi-chart .fi-section-description {
        color: rgb(100, 116, 139) !important;
        font-size: 0.75rem;
    }

    .dark .fi-dashboard-page .fi-wi-chart .fi-section-description {
        color: rgb(148, 163, 184) !important;
    }

    .fi-dashboard-page .fi-wi-chart .fi-section-content {
        padding-top: 0.6rem;
    }

    .fi-dashboard-page .fi-wi-chart .fi-wi-chart-canvas-ctn {
        min-height: 12rem;
        max-height: 14rem;
    }

    .fi-dashboard-page .fi-wi-chart canvas {
        max-height: 14rem;
    }

    @media (max-width: 768px) {
        .fi-dashboard-page .fi-wi-stats-overview-stat {
            min-height: 7rem;
        }

        .fi-dashboard-page .fi-wi-chart .fi-wi-chart-canvas-ctn {
            min-height: 11rem;
            max-height: 12.5rem;
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
    .fi-ta-table {
        border-radius: 0.75rem;
        overflow: hidden;
    }

    .fi-section {
        border-radius: 0.875rem;
    }

    /* ===== Sidebar polish ===== */

    /* Overall sidebar padding */
    .fi-sidebar {
        padding: 0.5rem 0 !important;
    }
    .fi-sidebar > nav {
        padding: 0.5rem 0.75rem !important;
        gap: 0 !important;
    }
    /* Remove any default Filament borders on sidebar items */
    .fi-sidebar .fi-sidebar-item,
    .fi-sidebar .fi-sidebar-item-btn,
    .fi-sidebar .fi-sidebar-group-label,
    .fi-sidebar > nav > ul > li {
        border-bottom: none !important;
        border-top: none !important;
    }

    /* Spacing between groups (Dasbor, Neraca, Kunjungan, etc.) */
    .fi-sidebar-nav-groups {
        gap: 0.65rem !important; /* Bring groups closer together (default was too sparse) */
    }

    /* Group header label */
    .fi-sidebar-group > .fi-sidebar-group-label,
    .fi-sidebar-group-label {
        font-size: 0.82rem !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.06em !important;
        padding: 0.45rem 0 0.25rem 0 !important;
        margin: 0 !important;
        line-height: 1.1 !important;
        color: rgb(100, 116, 139) !important;
    }
    .dark .fi-sidebar-group > .fi-sidebar-group-label,
    .dark .fi-sidebar-group-label {
        color: rgb(148, 163, 184) !important;
    }

    /* Remove excessive top margins on sibling groups */
    .fi-sidebar-group + .fi-sidebar-group,
    .fi-sidebar-group + li:not(.fi-sidebar-group),
    li:not(.fi-sidebar-group) + .fi-sidebar-group {
        margin-top: 0 !important;
        padding-top: 0 !important;
        border-top: none !important;
    }

    /* Sub-menu items container: add comfort gap */
    .fi-sidebar-group-items {
        margin-top: 0.15rem !important;
        padding: 0 !important;
        gap: 0.15rem !important; /* Spacing between items in the same group */
    }
    .fi-sidebar-group-items > li,
    .fi-sidebar-group-items .fi-sidebar-item {
        margin: 0 !important;
    }

    /* Item link / button inside sidebar: more breathing room */
    .fi-sidebar-item a,
    .fi-sidebar-item-btn,
    .fi-sidebar-group-items a {
        padding-top: 0.65rem !important;
        padding-bottom: 0.65rem !important;
        min-height: 0 !important;
        gap: 0.55rem !important;
    }

    /* Item label */
    .fi-sidebar-item-label,
    .fi-sidebar-item-label > span {
        font-size: 0.88rem !important;
        line-height: 1.25 !important;
    }

    /* Icon size */
    .fi-sidebar-item-icon {
        width: 1.2rem !important;
        height: 1.2rem !important;
    }

    /* Badge in sidebar */
    .fi-sidebar-item-badge {
        font-size: 0.72rem !important;
        padding: 0.15rem 0.45rem !important;
    }

    /* Hover */
    .fi-sidebar-group-items .fi-sidebar-item.fi-sidebar-item-has-url a:hover,
    .fi-sidebar-group-items .fi-sidebar-item.fi-sidebar-item-has-url .fi-sidebar-item-btn:hover {
        background: rgba(59, 130, 246, 0.07);
        border-radius: 0.375rem;
    }
    .dark .fi-sidebar-group-items .fi-sidebar-item.fi-sidebar-item-has-url a:hover,
    .dark .fi-sidebar-group-items .fi-sidebar-item.fi-sidebar-item-has-url .fi-sidebar-item-btn:hover {
        background: rgba(59, 130, 246, 0.12);
    }

    /* Active */
    .fi-sidebar-group-items .fi-sidebar-item.fi-active a,
    .fi-sidebar-group-items .fi-sidebar-item.fi-active .fi-sidebar-item-btn {
        background: rgba(59, 130, 246, 0.09);
        border-radius: 0.375rem;
    }
    .dark .fi-sidebar-group-items .fi-sidebar-item.fi-active a,
    .dark .fi-sidebar-group-items .fi-sidebar-item.fi-active .fi-sidebar-item-btn {
        background: rgba(59, 130, 246, 0.15);
    }
    .fi-sidebar-group-items .fi-sidebar-item.fi-active .fi-sidebar-item-label {
        font-weight: 600;
    }

    /* Collapsible group toggle button */
    .fi-sidebar-group-label > button,
    .fi-sidebar-group > button:first-child {
        padding: 0.35rem 0 !important;
    }

    /* ===== Badge fixes ===== */
    .fi-badge {
        font-weight: 600;
    }

    /* ===== Database Notifications (dinonaktifkan) ===== */
</style>
