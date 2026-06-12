<!DOCTYPE html>
<html lang="id" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Portal Customer') — Hibiscus Efsya</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --portal-bg: #f8fafc;
            --portal-surface: #ffffff;
            --portal-border: #e2e8f0;
            --portal-text: #0f172a;
            --portal-muted: #64748b;
            --portal-primary: #2563eb;
            --portal-primary-soft: rgba(37, 99, 235, 0.08);
            --portal-primary-strong: #1d4ed8;
            --portal-info: #0891b2;
            --portal-success: #059669;
            --portal-warning: #d97706;
            --portal-danger: #dc2626;
            --portal-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
            --portal-shadow-lg: 0 20px 48px rgba(15, 23, 42, 0.08);
            --portal-radius: 0.875rem;
            --portal-navbar-blur: rgba(255, 255, 255, 0.82);
        }

        .dark {
            --portal-bg: #0f172a;
            --portal-surface: #1e293b;
            --portal-border: #334155;
            --portal-text: #f1f5f9;
            --portal-muted: #94a3b8;
            --portal-primary: #3b82f6;
            --portal-primary-soft: rgba(59, 130, 246, 0.12);
            --portal-primary-strong: #60a5fa;
            --portal-info: #22d3ee;
            --portal-success: #34d399;
            --portal-warning: #fbbf24;
            --portal-danger: #f87171;
            --portal-shadow: 0 10px 28px rgba(0, 0, 0, 0.2);
            --portal-shadow-lg: 0 20px 48px rgba(0, 0, 0, 0.3);
            --portal-navbar-blur: rgba(30, 41, 59, 0.82);
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(ellipse 80% 50% at 0% 20%, rgba(37, 99, 235, 0.08), transparent 60%),
                radial-gradient(ellipse 60% 40% at 100% 80%, rgba(236, 72, 153, 0.07), transparent 60%),
                linear-gradient(180deg, #f0f5ff 0, var(--portal-bg) 16rem);
            background-color: var(--portal-bg);
            color: var(--portal-text);
            font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
            letter-spacing: 0;
            transition: background-color 0.3s, color 0.3s;
        }

        .dark body {
            background:
                radial-gradient(ellipse 80% 50% at 0% 20%, rgba(59, 130, 246, 0.06), transparent 60%),
                radial-gradient(ellipse 60% 40% at 100% 80%, rgba(236, 72, 153, 0.05), transparent 60%),
                linear-gradient(180deg, #1e293b 0, var(--portal-bg) 16rem);
        }

        /* ===== Navbar ===== */
        .customer-navbar {
            margin: 1rem auto 1.5rem;
            width: min(100% - 1.5rem, 1140px);
            border: 1px solid var(--portal-border);
            border-radius: var(--portal-radius);
            background: var(--portal-navbar-blur);
            box-shadow: var(--portal-shadow);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            transition: background 0.3s, border-color 0.3s, box-shadow 0.3s;
        }

        .navbar-brand {
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
            color: var(--portal-text) !important;
            font-weight: 800;
            letter-spacing: 0;
        }

        .navbar-brand img {
            width: 2.1rem;
            height: 2.1rem;
            object-fit: contain;
        }

        .customer-name {
            max-width: 14rem;
            overflow: hidden;
            color: var(--portal-muted);
            font-size: 0.82rem;
            font-weight: 700;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* ===== Theme Toggle ===== */
        .theme-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 0.55rem;
            border: 1px solid var(--portal-border);
            background: transparent;
            color: var(--portal-muted);
            cursor: pointer;
            transition: all 0.2s;
            font-size: 1rem;
        }

        .theme-toggle:hover {
            border-color: var(--portal-primary);
            color: var(--portal-primary);
            background: var(--portal-primary-soft);
        }

        /* ===== Container ===== */
        .container {
            max-width: 1120px;
            padding-bottom: 2rem;
        }

        h4 {
            color: var(--portal-text);
            letter-spacing: 0;
        }

        .text-muted {
            color: var(--portal-muted) !important;
        }

        /* ===== Cards ===== */
        .card {
            border: 1px solid var(--portal-border);
            border-radius: var(--portal-radius);
            background: var(--portal-surface);
            box-shadow: var(--portal-shadow);
            transition: background 0.3s, border-color 0.3s, box-shadow 0.3s;
        }

        .card .card {
            box-shadow: none;
        }

        .card-header {
            border-bottom: 1px solid var(--portal-border);
            border-radius: var(--portal-radius) var(--portal-radius) 0 0 !important;
            background: var(--portal-surface);
            padding: 1rem 1.15rem;
            transition: background 0.3s, border-color 0.3s;
        }

        .card-body {
            padding: 1.15rem;
        }

        /* ===== Stat Cards (clean, no left border) ===== */
        .stat-card {
            border-radius: var(--portal-radius);
            border: 1px solid var(--portal-border);
            background: var(--portal-surface);
            box-shadow: var(--portal-shadow);
            padding: 1.25rem;
            transition: background 0.3s, border-color 0.3s, box-shadow 0.3s, transform 0.2s;
        }

        .stat-card:hover {
            box-shadow: var(--portal-shadow-lg);
            transform: translateY(-2px);
        }

        .stat-card .stat-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .stat-card .stat-value {
            font-size: clamp(1.15rem, 1.5vw, 1.5rem);
            font-weight: 800;
            line-height: 1.2;
            margin-top: 0.25rem;
        }

        .stat-card .stat-icon {
            width: 2.75rem;
            height: 2.75rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        /* ===== Text Colors ===== */
        .text-primary { color: var(--portal-primary) !important; }
        .text-success { color: var(--portal-success) !important; }
        .text-info { color: var(--portal-info) !important; }

        /* ===== Buttons ===== */
        .btn {
            border-radius: 0.65rem;
            font-weight: 700;
            letter-spacing: 0;
            transition: all 0.2s;
        }

        .btn-primary {
            border-color: var(--portal-primary);
            background: var(--portal-primary);
            color: #fff;
        }

        .btn-primary:hover {
            border-color: var(--portal-primary-strong);
            background: var(--portal-primary-strong);
        }

        .btn-info {
            border-color: var(--portal-info);
            background: var(--portal-info);
            color: #fff;
        }

        .btn-outline-primary {
            border-color: color-mix(in srgb, var(--portal-primary) 35%, transparent);
            color: var(--portal-primary);
        }

        .btn-outline-primary:hover {
            border-color: var(--portal-primary);
            background: var(--portal-primary);
            color: #fff;
        }

        .btn-outline-secondary {
            border-color: var(--portal-border);
            color: var(--portal-muted);
        }

        .btn-outline-secondary:hover {
            border-color: var(--portal-muted);
            background: var(--portal-surface);
            color: var(--portal-text);
        }

        /* ===== Forms ===== */
        .form-control {
            min-height: 2.45rem;
            border-color: var(--portal-border);
            border-radius: 0.65rem;
            box-shadow: none;
            background: var(--portal-surface);
            color: var(--portal-text);
            transition: background 0.3s, border-color 0.3s, color 0.3s;
        }

        .form-control:focus {
            border-color: var(--portal-primary);
            box-shadow: 0 0 0 0.18rem color-mix(in srgb, var(--portal-primary) 14%, transparent);
        }

        .dark .form-control {
            background: #0f172a;
        }

        /* ===== Badges ===== */
        .badge {
            border-radius: 999px;
            padding: 0.35rem 0.6rem;
            font-weight: 700;
            font-size: 0.72rem;
            border: none;
        }

        .badge-success { background: #dcfce7; color: #166534; }
        .badge-info { background: #e0f2fe; color: #075985; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fef2f2; color: #991b1b; }
        .badge-secondary { background: #e2e8f0; color: #334155; }

        .dark .badge-success { background: rgba(52, 211, 153, 0.15); color: #6ee7b7; }
        .dark .badge-info { background: rgba(59, 130, 246, 0.15); color: #93c5fd; }
        .dark .badge-warning { background: rgba(251, 191, 36, 0.15); color: #fde68a; }
        .dark .badge-danger { background: rgba(248, 113, 113, 0.15); color: #fca5a5; }
        .dark .badge-secondary { background: rgba(148, 163, 184, 0.15); color: #cbd5e1; }

        /* ===== Alerts ===== */
        .alert {
            border: 0;
            border-radius: var(--portal-radius);
            box-shadow: var(--portal-shadow);
        }

        /* ===== Pagination ===== */
        .pagination {
            gap: 0.25rem;
            flex-wrap: wrap;
        }

        .page-link {
            border-color: var(--portal-border);
            border-radius: 0.55rem;
            color: var(--portal-primary);
            font-weight: 700;
            background: var(--portal-surface);
            transition: background 0.2s, border-color 0.2s, color 0.2s;
        }

        .page-link:hover {
            background: var(--portal-primary-soft);
            border-color: var(--portal-primary);
            color: var(--portal-primary-strong);
        }

        .page-item.active .page-link {
            border-color: var(--portal-primary);
            background: var(--portal-primary);
            color: #fff;
        }

        .dark .page-link {
            background: #1e293b;
        }

        /* ===== Tables ===== */
        .table {
            color: var(--portal-text);
            transition: color 0.3s;
        }

        .table thead th {
            border-bottom-color: var(--portal-border);
        }

        .table td, .table th {
            border-top-color: var(--portal-border);
        }

        .table-hover tbody tr:hover {
            color: var(--portal-text);
        }

        .table-primary, .table-primary > td, .table-primary > th {
            background-color: var(--portal-primary-soft);
        }

        .thead-light th {
            background-color: var(--portal-bg);
            border-bottom-color: var(--portal-border);
        }

        /* ===== Responsive ===== */
        @media (max-width: 576px) {
            .customer-navbar {
                margin-top: 0.75rem;
                border-radius: 0.75rem;
            }

            .customer-name {
                display: none;
            }

            .card-body {
                padding: 1rem;
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light customer-navbar">
        <a class="navbar-brand font-weight-bold" href="{{ route('customer.dashboard') }}">
            <img src="{{ asset('assets/img/logoHE1.png') }}" alt="Hibiscus Efsya">
            <span>Hibiscus Efsya</span>
        </a>
        <div class="ml-auto d-flex align-items-center gap-2" style="gap:0.5rem">
            <span class="customer-name mr-1">{{ session('customer_nama', '') }}</span>
            <button class="theme-toggle" id="themeToggle" title="Ganti tema" aria-label="Ganti tema gelap/terang">
                <i class="fas fa-moon" id="themeIcon"></i>
            </button>
            <form action="{{ route('customer.logout') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-sign-out-alt"></i> <span class="d-none d-sm-inline">Logout</span>
                </button>
            </form>
        </div>
    </nav>

    <div class="container">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                {{ session('error') }}
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        @endif

        @yield('content')
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            var theme = localStorage.getItem('customerTheme') || 'light';
            var html = document.documentElement;
            var icon = document.getElementById('themeIcon');

            if (theme === 'dark') {
                html.classList.remove('light');
                html.classList.add('dark');
                if (icon) icon.className = 'fas fa-sun';
            }

            document.getElementById('themeToggle')?.addEventListener('click', function() {
                var isDark = html.classList.contains('dark');
                if (isDark) {
                    html.classList.remove('dark');
                    html.classList.add('light');
                    localStorage.setItem('customerTheme', 'light');
                    if (icon) icon.className = 'fas fa-moon';
                } else {
                    html.classList.remove('light');
                    html.classList.add('dark');
                    localStorage.setItem('customerTheme', 'dark');
                    if (icon) icon.className = 'fas fa-sun';
                }
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
