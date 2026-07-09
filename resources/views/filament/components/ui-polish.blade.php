@vite(['resources/css/app.css'])

<style>
    /* ===== Global Scale (Zoom Out) ===== */
    html {
        font-size: 85% !important;
    }

    /* ===== Dashboard Stats Cards ===== */
    .fi-wi-stats-overview-stat {
        min-height: 8.5rem;
        overflow: hidden;
        border-radius: 0.875rem;
        border: 1px solid rgba(148, 163, 184, 0.22);
        background:
            linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(248, 250, 252, 0.72)),
            var(--fi-color-white, #ffffff);
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
    }

    .dark .fi-wi-stats-overview-stat {
        border-color: rgba(255, 255, 255, 0.08);
        background: rgb(9, 9, 11);
        box-shadow: 0 14px 34px rgba(0, 0, 0, 0.5);
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
        overflow: hidden;
        border-radius: 1rem;
        border: 1px solid rgba(148, 163, 184, 0.18);
        background: linear-gradient(160deg, rgba(255,255,255,0.98) 0%, rgba(248,250,252,0.92) 100%);
        box-shadow:
            0 1px 0 0 rgba(255,255,255,0.9) inset,
            0 12px 32px rgba(15, 23, 42, 0.07),
            0 2px 8px rgba(15, 23, 42, 0.04);
        transition: box-shadow 0.25s ease, transform 0.25s ease;
    }

    .fi-dashboard-page .fi-wi-chart .fi-section:hover {
        box-shadow:
            0 1px 0 0 rgba(255,255,255,0.9) inset,
            0 16px 40px rgba(15, 23, 42, 0.1),
            0 4px 12px rgba(15, 23, 42, 0.06);
        transform: translateY(-1px);
    }

    .dark .fi-dashboard-page .fi-wi-chart .fi-section {
        border-color: rgba(148, 163, 184, 0.14);
        background: linear-gradient(160deg, rgba(30, 41, 59, 0.88) 0%, rgba(15, 23, 42, 0.96) 100%);
        box-shadow:
            0 1px 0 0 rgba(255,255,255,0.04) inset,
            0 14px 36px rgba(0, 0, 0, 0.3),
            0 2px 8px rgba(0, 0, 0, 0.18);
    }

    .dark .fi-dashboard-page .fi-wi-chart .fi-section:hover {
        box-shadow:
            0 1px 0 0 rgba(255,255,255,0.04) inset,
            0 20px 48px rgba(0, 0, 0, 0.38),
            0 4px 12px rgba(0, 0, 0, 0.24);
        transform: translateY(-1px);
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

    /* Spacing between groups */
    .fi-sidebar-nav-groups {
        gap: 0.4rem !important;
    }

    /* Group header label — lebih kecil, muted, clean */
    .fi-sidebar-group > .fi-sidebar-group-label,
    .fi-sidebar-group-label {
        font-size: 0.7rem !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.08em !important;
        padding: 0.6rem 0.5rem 0.2rem 0.5rem !important;
        margin: 0 !important;
        line-height: 1.1 !important;
        color: rgb(148, 163, 184) !important;
    }
    .dark .fi-sidebar-group > .fi-sidebar-group-label,
    .dark .fi-sidebar-group-label {
        color: rgb(100, 116, 139) !important;
    }

    /* Remove excessive top margins on sibling groups */
    .fi-sidebar-group + .fi-sidebar-group,
    .fi-sidebar-group + li:not(.fi-sidebar-group),
    li:not(.fi-sidebar-group) + .fi-sidebar-group {
        margin-top: 0 !important;
        padding-top: 0 !important;
        border-top: none !important;
    }

    /* Sub-menu items container */
    .fi-sidebar-group-items {
        margin-top: 0.1rem !important;
        padding: 0 !important;
        gap: 0.1rem !important;
    }
    .fi-sidebar-group-items > li,
    .fi-sidebar-group-items .fi-sidebar-item {
        margin: 0 !important;
    }

    /* Item link / button inside sidebar */
    .fi-sidebar-item a,
    .fi-sidebar-item-btn,
    .fi-sidebar-group-items a {
        padding: 0.5rem 0.65rem !important;
        min-height: 0 !important;
        gap: 0.55rem !important;
        border-radius: 0.5rem !important;
    }

    /* Item label */
    .fi-sidebar-item-label,
    .fi-sidebar-item-label > span {
        font-size: 0.86rem !important;
        font-weight: 500 !important;
        line-height: 1.25 !important;
        color: rgb(51, 65, 85) !important;
    }
    .dark .fi-sidebar-item-label,
    .dark .fi-sidebar-item-label > span {
        color: rgb(203, 213, 225) !important;
    }

    /* Icon size */
    .fi-sidebar-item-icon {
        width: 1.15rem !important;
        height: 1.15rem !important;
        opacity: 0.75;
    }

    /* Badge in sidebar */
    .fi-sidebar-item-badge {
        font-size: 0.7rem !important;
        padding: 0.12rem 0.42rem !important;
    }

    /* Hover */
    .fi-sidebar-group-items .fi-sidebar-item.fi-sidebar-item-has-url a:hover,
    .fi-sidebar-group-items .fi-sidebar-item.fi-sidebar-item-has-url .fi-sidebar-item-btn:hover {
        background: rgba(59, 130, 246, 0.07) !important;
    }
    .dark .fi-sidebar-group-items .fi-sidebar-item.fi-sidebar-item-has-url a:hover,
    .dark .fi-sidebar-group-items .fi-sidebar-item.fi-sidebar-item-has-url .fi-sidebar-item-btn:hover {
        background: rgba(59, 130, 246, 0.12) !important;
    }

    /* Active — lebih tegas dengan left indicator */
    .fi-sidebar-group-items .fi-sidebar-item.fi-active a,
    .fi-sidebar-group-items .fi-sidebar-item.fi-active .fi-sidebar-item-btn {
        background: rgba(59, 130, 246, 0.1) !important;
        border-radius: 0.5rem !important;
        box-shadow: inset 3px 0 0 rgba(59, 130, 246, 0.7) !important;
    }
    .dark .fi-sidebar-group-items .fi-sidebar-item.fi-active a,
    .dark .fi-sidebar-group-items .fi-sidebar-item.fi-active .fi-sidebar-item-btn {
        background: rgba(59, 130, 246, 0.16) !important;
        box-shadow: inset 3px 0 0 rgba(99, 160, 255, 0.75) !important;
    }
    .fi-sidebar-group-items .fi-sidebar-item.fi-active .fi-sidebar-item-label,
    .fi-sidebar-group-items .fi-sidebar-item.fi-active .fi-sidebar-item-label > span {
        font-weight: 650 !important;
        color: rgb(37, 99, 235) !important;
    }
    .dark .fi-sidebar-group-items .fi-sidebar-item.fi-active .fi-sidebar-item-label,
    .dark .fi-sidebar-group-items .fi-sidebar-item.fi-active .fi-sidebar-item-label > span {
        color: rgb(147, 197, 253) !important;
    }
    .fi-sidebar-group-items .fi-sidebar-item.fi-active .fi-sidebar-item-icon {
        opacity: 1 !important;
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
