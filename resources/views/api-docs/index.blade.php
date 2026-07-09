<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>API Docs - Hibiscus Efsya POS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    {{-- Stoplight Elements --}}
    <script src="https://unpkg.com/@stoplight/elements/web-components.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/@stoplight/elements/styles.min.css">

    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            background-color: var(--color-canvas, #fff);
        }

        /* Download bar above docs */
        .docs-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 20px;
            background: #1e293b;
            color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            border-bottom: 1px solid #334155;
        }
        .docs-topbar-title {
            font-weight: 700;
            font-size: 15px;
            letter-spacing: -0.01em;
        }
        .docs-topbar-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .docs-topbar-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: background .15s, color .15s;
        }
        .docs-topbar-btn--primary {
            background: #3b82f6;
            color: #fff;
        }
        .docs-topbar-btn--primary:hover {
            background: #2563eb;
        }
        .docs-topbar-btn--secondary {
            background: #334155;
            color: #e2e8f0;
        }
        .docs-topbar-btn--secondary:hover {
            background: #475569;
        }
        .docs-topbar-btn svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
        }

        /* Stoplight Elements fills remaining height */
        elements-api {
            display: block;
            height: calc(100vh - 49px);
        }

        @media (max-width: 640px) {
            .docs-topbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
                padding: 12px 16px;
            }
            elements-api {
                height: calc(100vh - 90px);
            }
        }
    </style>
</head>
<body>

{{-- Top bar with branding & download buttons --}}
<div class="docs-topbar">
    <span class="docs-topbar-title">Hibiscus Efsya POS — API Documentation</span>
    <div class="docs-topbar-actions">
        <a href="{{ route('api.docs.download') }}" class="docs-topbar-btn docs-topbar-btn--primary">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z"/><path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z"/></svg>
            OpenAPI JSON
        </a>
        <a href="{{ route('api.docs.download.postman') }}" class="docs-topbar-btn docs-topbar-btn--secondary">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z"/><path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z"/></svg>
            Postman Collection
        </a>
    </div>
</div>

{{-- Stoplight Elements API Docs --}}
<elements-api
    apiDescriptionUrl="{{ route('api.docs.json') }}"
    router="hash"
    layout="sidebar"
    hideInternal="true"
/>

</body>
</html>
