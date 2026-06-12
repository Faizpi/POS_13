<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>@yield('title', 'Invoice')</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            color-scheme: light dark;
            --invoice-page: #f1f5f9;
            --invoice-surface: #ffffff;
            --invoice-panel: #f8fafc;
            --invoice-panel-strong: #eff6ff;
            --invoice-border: #e2e8f0;
            --invoice-text: #0f172a;
            --invoice-muted: #64748b;
            --invoice-soft: #94a3b8;
            --invoice-primary: #2563eb;
            --invoice-primary-strong: #1d4ed8;
            --invoice-danger: #e11d48;
            --invoice-shadow: 0 22px 60px rgba(15, 23, 42, 0.14);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --invoice-page: #020617;
                --invoice-surface: #0f172a;
                --invoice-panel: rgba(30, 41, 59, 0.72);
                --invoice-panel-strong: rgba(30, 64, 175, 0.18);
                --invoice-border: rgba(148, 163, 184, 0.24);
                --invoice-text: #f8fafc;
                --invoice-muted: #cbd5e1;
                --invoice-soft: #94a3b8;
                --invoice-primary: #60a5fa;
                --invoice-primary-strong: #3b82f6;
                --invoice-danger: #fb7185;
                --invoice-shadow: 0 22px 70px rgba(0, 0, 0, 0.45);
            }
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background:
                radial-gradient(circle at top, rgba(37, 99, 235, 0.12), transparent 24rem),
                var(--invoice-page);
            color: var(--invoice-text);
            font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
            min-height: 100vh;
            padding: 1.25rem;
        }

        .invoice-container {
            width: min(100%, 520px);
            margin: 0 auto;
            overflow: hidden;
            border: 1px solid var(--invoice-border);
            border-radius: 1.25rem;
            background: var(--invoice-surface);
            box-shadow: var(--invoice-shadow);
            transition: background-color 160ms ease, border-color 160ms ease;
        }

        .invoice-header {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid var(--invoice-border);
            background: linear-gradient(180deg, rgba(148, 163, 184, 0.1), transparent);
        }

        .invoice-body {
            padding: 1.5rem;
        }

        .info-card {
            margin-bottom: 1rem;
            padding: 1rem;
            border: 1px solid var(--invoice-border);
            border-radius: 1rem;
            background: var(--invoice-panel);
        }

        .info-card-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.8rem;
            color: var(--invoice-soft);
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.11em;
            line-height: 1.2;
            text-transform: uppercase;
        }

        .info-card-title i {
            color: var(--invoice-primary);
            opacity: 0.9;
        }

        .info-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.68rem 0;
            border-bottom: 1px solid var(--invoice-border);
            font-size: 0.875rem;
        }

        .info-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .info-row .label {
            min-width: 7rem;
            color: var(--invoice-muted);
            font-weight: 600;
        }

        .info-row .value {
            color: var(--invoice-text);
            font-weight: 700;
            overflow-wrap: anywhere;
            text-align: right;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            min-height: 1.55rem;
            padding: 0.25rem 0.65rem;
            border: 1px solid transparent;
            border-radius: 999px;
            font-size: 0.65rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            line-height: 1;
            text-transform: uppercase;
        }

        .status-lunas, .status-paid, .status-success {
            color: #047857;
            border-color: #a7f3d0;
            background: #ecfdf5;
        }

        .status-approved {
            color: #1d4ed8;
            border-color: #bfdbfe;
            background: #eff6ff;
        }

        .status-pending, .status-waiting {
            color: #b45309;
            border-color: #fde68a;
            background: #fffbeb;
        }

        .status-canceled, .status-rejected {
            color: #be123c;
            border-color: #fecdd3;
            background: #fff1f2;
        }

        @media (prefers-color-scheme: dark) {
            .status-lunas,
            .status-paid,
            .status-success {
                color: #6ee7b7;
                border-color: rgba(16, 185, 129, 0.34);
                background: rgba(6, 78, 59, 0.34);
            }

            .status-approved {
                color: #93c5fd;
                border-color: rgba(59, 130, 246, 0.34);
                background: rgba(30, 64, 175, 0.34);
            }

            .status-pending,
            .status-waiting {
                color: #fcd34d;
                border-color: rgba(245, 158, 11, 0.34);
                background: rgba(120, 53, 15, 0.34);
            }

            .status-canceled,
            .status-rejected {
                color: #fda4af;
                border-color: rgba(244, 63, 94, 0.34);
                background: rgba(136, 19, 55, 0.34);
            }
        }

        .items-section {
            margin-bottom: 1.25rem;
        }

        .items-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1rem 0 0.75rem;
            padding-inline: 0.15rem;
            color: var(--invoice-soft);
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.11em;
            text-transform: uppercase;
        }

        .items-title i {
            color: var(--invoice-primary);
        }

        .item-card {
            margin-bottom: 0.8rem;
            padding: 1rem;
            border: 1px solid var(--invoice-border);
            border-radius: 1rem;
            background: var(--invoice-panel);
            transition: border-color 160ms ease, transform 160ms ease;
        }

        .item-card:hover {
            border-color: rgba(37, 99, 235, 0.35);
        }

        .item-name {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.4rem;
            color: var(--invoice-text);
            font-size: 0.9rem;
            font-weight: 800;
            line-height: 1.35;
        }

        .item-code {
            display: inline-block;
            color: var(--invoice-soft);
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .item-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.65rem;
            color: var(--invoice-muted);
            font-size: 0.78rem;
        }

        .item-desc {
            margin-bottom: 0.65rem;
            padding: 0.6rem 0.7rem;
            border-left: 3px solid var(--invoice-border);
            border-radius: 0.55rem;
            background: rgba(148, 163, 184, 0.12);
            color: var(--invoice-muted);
            font-size: 0.78rem;
            font-style: italic;
        }

        .item-total {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding-top: 0.8rem;
            border-top: 1px dashed var(--invoice-border);
            color: var(--invoice-text);
            font-size: 0.9rem;
            font-weight: 800;
        }

        .item-total span:first-child {
            color: var(--invoice-soft);
            font-size: 0.68rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .totals-card {
            margin-bottom: 1.25rem;
            overflow: hidden;
            padding: 0.45rem;
            border: 1px solid var(--invoice-border);
            border-radius: 1rem;
            background: var(--invoice-surface);
        }

        .total-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.68rem 0.85rem;
            color: var(--invoice-muted);
            font-size: 0.88rem;
            font-weight: 650;
        }

        .total-row.discount {
            color: var(--invoice-danger);
        }

        .total-row.grand {
            margin-top: 0.35rem;
            padding: 1rem;
            border-radius: 0.85rem;
            background: linear-gradient(135deg, var(--invoice-primary-strong), var(--invoice-primary));
            color: #ffffff;
            font-size: 1.05rem;
            font-weight: 900;
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.24);
        }

        .total-row.grand span:first-child {
            font-size: 0.72rem;
            letter-spacing: 0.14em;
            opacity: 0.86;
            text-transform: uppercase;
        }

        .qr-section {
            margin-bottom: 1.25rem;
            padding: 1.25rem;
            border: 1px solid var(--invoice-border);
            border-radius: 1rem;
            background: var(--invoice-panel);
            text-align: center;
        }

        .qr-section img {
            width: 7.5rem;
            height: 7.5rem;
            margin: 0 auto 0.75rem;
            padding: 0.25rem;
            border-radius: 0.75rem;
            background: #ffffff;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.14);
        }

        .btn-download {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.95rem 1rem;
            border-radius: 0.85rem;
            background: var(--invoice-primary-strong);
            color: #ffffff;
            font-size: 0.85rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-decoration: none;
            text-transform: uppercase;
            transition: transform 120ms ease, background-color 120ms ease;
        }

        .btn-download:hover {
            background: var(--invoice-primary);
            transform: translateY(-1px);
        }

        .invoice-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--invoice-border);
            color: var(--invoice-soft);
            font-size: 0.72rem;
            line-height: 1.6;
            text-align: center;
        }

        .invoice-footer strong {
            display: block;
            margin-bottom: 0.25rem;
            color: var(--invoice-text);
            font-size: 0.78rem;
            font-weight: 900;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .invoice-container .text-gray-900,
        .invoice-container .text-gray-800,
        .invoice-container .text-gray-700,
        .invoice-container .dark\:text-white {
            color: var(--invoice-text) !important;
        }

        .invoice-container .text-gray-600,
        .invoice-container .text-gray-500,
        .invoice-container .text-gray-400,
        .invoice-container .dark\:text-gray-300,
        .invoice-container .dark\:text-gray-400,
        .invoice-container .dark\:text-gray-500 {
            color: var(--invoice-muted) !important;
        }

        .invoice-container .bg-gray-100,
        .invoice-container .dark\:bg-gray-800 {
            background: var(--invoice-panel-strong) !important;
            color: var(--invoice-muted) !important;
        }

        @media (max-width: 420px) {
            body {
                padding: 0.75rem;
            }

            .invoice-header,
            .invoice-body,
            .invoice-footer {
                padding: 1rem;
            }

            .info-row,
            .item-meta,
            .item-total,
            .total-row {
                gap: 0.7rem;
            }

            .info-row .label {
                min-width: 5.5rem;
            }
        }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
