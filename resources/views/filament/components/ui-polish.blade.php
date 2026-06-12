@vite(['resources/css/app.css'])

<style>
    /* ===== Dashboard Stats Cards ===== */
    .fi-dashboard-page .fi-wi-stats-overview-stat {
        min-height: 8.5rem;
        overflow: hidden;
        border-radius: 0.875rem;
        border: 1px solid rgba(148, 163, 184, 0.22);
        background:
            linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(248, 250, 252, 0.72)),
            var(--fi-color-white, #ffffff);
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
    }

    .dark .fi-dashboard-page .fi-wi-stats-overview-stat {
        border-color: rgba(148, 163, 184, 0.18);
        background:
            linear-gradient(135deg, rgba(30, 41, 59, 0.72), rgba(15, 23, 42, 0.92)),
            #0f172a;
        box-shadow: 0 14px 34px rgba(0, 0, 0, 0.28);
    }

    .fi-dashboard-page .fi-wi-stats-overview-stat-content {
        gap: 0.45rem;
    }

    .fi-dashboard-page .fi-wi-stats-overview-stat-label {
        color: rgb(100, 116, 139);
        font-size: 0.72rem;
        font-weight: 700;
    }

    .dark .fi-dashboard-page .fi-wi-stats-overview-stat-label {
        color: rgb(148, 163, 184);
    }

    .fi-dashboard-page .fi-wi-stats-overview-stat-value {
        color: rgb(15, 23, 42);
        font-size: clamp(1rem, 1.35vw, 1.35rem);
        font-weight: 800;
        line-height: 1.15;
        word-break: break-word;
    }

    .dark .fi-dashboard-page .fi-wi-stats-overview-stat-value {
        color: rgb(248, 250, 252);
    }

    .fi-dashboard-page .fi-wi-stats-overview-stat-description {
        font-size: 0.74rem;
        font-weight: 650;
    }

    .fi-dashboard-page .fi-wi-stats-overview-stat-chart {
        height: 3rem;
        margin-top: auto;
        opacity: 0.86;
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
    .fi-sidebar-nav-item-button:hover {
        transition: background 0.15s ease;
    }

    /* ===== Badge fixes ===== */
    .fi-badge {
        font-weight: 600;
    }

    /* ===== Database Notifications (dinonaktifkan) ===== */
</style>
