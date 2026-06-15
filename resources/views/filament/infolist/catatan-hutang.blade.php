@php
    $pembelians = \App\Models\Pembelian::whereHas('kontak', fn($q) => $q->where('id', $kontak->id))
        ->whereIn('status', ['Approved'])
        ->with(['gudang', 'pembayarans' => fn($q) => $q->where('status', 'Approved')])
        ->orderBy('tgl_jatuh_tempo')
        ->get();

    $canView = in_array(auth()->user()?->role, ['user', 'admin', 'super_admin']);
@endphp

@if(!$canView)
    <div class="text-center py-6 text-sm text-gray-400">Anda tidak memiliki akses ke catatan hutang.</div>
@elseif(!\Illuminate\Support\Facades\Schema::hasColumn('pembelians', 'kontak_id'))
    <div class="text-center py-6 text-sm text-gray-400">Silakan jalankan php artisan migrate (kolom kontak_id pada pembelians belum ada).</div>
@elseif($pembelians->isEmpty())
    <div class="text-center py-6 text-sm text-gray-400">Tidak ada catatan hutang untuk kontak ini.</div>
@else
    <div class="overflow-x-auto">
        <table style="width:100%;font-size:13px;border-collapse:collapse;">
            <thead>
                <tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb;">
                    <th style="padding:6px 8px;text-align:left;">No Transaksi</th>
                    <th style="padding:6px 8px;text-align:left;">Gudang</th>
                    <th style="padding:6px 8px;text-align:left;">Jatuh Tempo</th>
                    <th style="padding:6px 8px;text-align:right;">Total</th>
                    <th style="padding:6px 8px;text-align:right;">Sisa Hutang</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pembelians as $p)
                    @php
                        $totalBayar = $p->pembayarans->sum('jumlah_bayar');
                        $sisa = max(0, $p->grand_total - $totalBayar);
                        $lewat = $p->tgl_jatuh_tempo && $p->tgl_jatuh_tempo->isPast();
                    @endphp
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:6px 8px;font-family:monospace;font-size:12px;">{{ $p->custom_number }}</td>
                        <td style="padding:6px 8px;">{{ $p->gudang?->nama_gudang ?? '—' }}</td>
                        <td style="padding:6px 8px;{{ $lewat ? 'color:red;font-weight:bold;' : '' }}">{{ $p->tgl_jatuh_tempo?->format('d/m/Y') ?? '—' }}</td>
                        <td style="padding:6px 8px;text-align:right;">Rp {{ number_format($p->grand_total, 0, ',', '.') }}</td>
                        <td style="padding:6px 8px;text-align:right;{{ $sisa > 0 ? 'color:red;font-weight:bold;' : '' }}">Rp {{ number_format($sisa, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
