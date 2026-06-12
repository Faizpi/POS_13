<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} {{ $record->nomor ?? '' }}</title>
    <style>
        :root {
            color-scheme: light dark;
            font-family: Arial, Helvetica, sans-serif;
            color: #111827;
            background: #f3f4f6;
        }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; }
        main {
            max-width: 960px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }
        header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 28px 32px;
            border-bottom: 1px solid #e5e7eb;
        }
        h1 { margin: 0 0 8px; font-size: 28px; }
        .brand { font-weight: 700; color: #be123c; letter-spacing: .04em; text-transform: uppercase; }
        .status { display: inline-block; padding: 6px 10px; border-radius: 999px; background: #f3f4f6; font-weight: 700; }
        section { padding: 24px 32px; border-bottom: 1px solid #e5e7eb; }
        section:last-child { border-bottom: 0; }
        .meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 24px; }
        .label { display: block; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .value { display: block; margin-top: 4px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 8px; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; font-size: 14px; }
        th { background: #f9fafb; color: #374151; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .totals { max-width: 360px; margin-left: auto; }
        .total-row { display: flex; justify-content: space-between; gap: 16px; padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
        .total-row:last-child { border-bottom: 0; font-weight: 800; font-size: 18px; }
        .note { white-space: pre-line; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; }
        .actions { padding: 16px 32px 28px; text-align: right; }
        button { border: 0; border-radius: 6px; padding: 10px 14px; color: #fff; background: #111827; cursor: pointer; }
        @media (prefers-color-scheme: dark) {
            :root {
                color: #f8fafc;
                background: #020617;
            }
            main {
                background: #0f172a;
                border-color: rgba(148, 163, 184, 0.24);
            }
            header,
            section,
            th,
            td,
            .total-row {
                border-color: rgba(148, 163, 184, 0.24);
            }
            th,
            .note,
            .status {
                background: rgba(30, 41, 59, 0.72);
                color: #e2e8f0;
            }
            .brand { color: #fb7185; }
            .label { color: #94a3b8; }
            .value { color: #f8fafc; }
            button { background: #2563eb; }
        }
        @media print {
            body { padding: 0; background: #fff; }
            main { max-width: none; border: 0; border-radius: 0; }
            .actions { display: none; }
        }
        @media (max-width: 700px) {
            body { padding: 12px; }
            header, section, .actions { padding-left: 16px; padding-right: 16px; }
            header { display: block; }
            .meta { grid-template-columns: 1fr; }
            table { display: block; overflow-x: auto; }
        }
    </style>
</head>
<body>
<main>
    <header>
        <div>
            <div class="brand">Hibiscusefsya POS</div>
            <h1>{{ $title }}</h1>
            <span>{{ $record->nomor ?? '-' }}</span>
        </div>
        <div>
            <span class="label">Status</span>
            <span class="status">{{ $record->status ?? '-' }}</span>
        </div>
    </header>

    <section>
        <div class="meta">
            @foreach ($meta as $label => $value)
                <div>
                    <span class="label">{{ $label }}</span>
                    <span class="value">{{ $value ?: '-' }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <section>
        <table>
            <thead>
            <tr>
                @foreach (array_keys($rows[0] ?? ['Data' => '-']) as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td>Belum ada rincian.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>

    @if ($totals)
        <section>
            <div class="totals">
                @foreach ($totals as $label => $value)
                    <div class="total-row">
                        <span>{{ $label }}</span>
                        <span>{{ $value }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($notes)
        <section>
            <span class="label">Catatan</span>
            <div class="note">{{ $notes }}</div>
        </section>
    @endif

    <div class="actions">
        <button type="button" onclick="window.print()">Print</button>
    </div>
</main>
</body>
</html>
