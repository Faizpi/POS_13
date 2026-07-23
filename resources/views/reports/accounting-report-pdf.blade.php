<html>
<head><meta charset="utf-8"><style>body{font-family:DejaVu Sans;font-size:10px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #bbb;padding:5px;text-align:left}.number{text-align:right}</style></head>
<body>
    <h2>{{ $metadata['title'] }}</h2>
    @if ($metadata['is_management_view'])<p>Management view: {{ $metadata['warehouse_treatment'] }}</p>@endif
    <table>
        <thead><tr><th>Tanggal</th><th>Nomor</th><th>Sumber</th><th class="number">Debit</th><th class="number">Kredit</th><th class="number">Saldo</th></tr></thead>
        <tbody>@foreach ($rows as $row)<tr><td>{{ $row['journal_date'] }}</td><td>{{ $row['journal_number'] }}</td><td>{{ $row['source_type'] }} #{{ $row['source_id'] }}</td><td class="number">{{ $row['debit'] ?? $row['total_debit'] }}</td><td class="number">{{ $row['credit'] ?? $row['total_credit'] }}</td><td class="number">{{ $row['running_balance'] ?? '' }}</td></tr>@endforeach</tbody>
        <tfoot><tr><th colspan="3">Total</th><th class="number">{{ $metadata['total_debit'] }}</th><th class="number">{{ $metadata['total_credit'] }}</th><th></th></tr></tfoot>
    </table>
</body>
</html>
