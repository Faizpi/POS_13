@extends('customer.layouts.app')

@section('title', 'Detail Kunjungan #' . $kunjungan->nomor)

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="font-weight-bold mb-0">Detail Kunjungan #{{ $kunjungan->nomor }}</h4>
        <a href="{{ route('customer.kunjungan') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0 font-weight-bold text-primary"><i class="fas fa-info-circle mr-1"></i> Informasi Kunjungan</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless table-sm mb-0">
                        <tr><td class="pl-0" style="width:35%"><strong>Nomor</strong></td><td>: {{ $kunjungan->nomor }}</td></tr>
                        <tr><td class="pl-0"><strong>Tanggal</strong></td><td>: {{ $kunjungan->tgl_kunjungan->format('d M Y') }}</td></tr>
                        <tr><td class="pl-0"><strong>Tujuan</strong></td><td>: <span class="badge badge-info">{{ $kunjungan->tujuan }}</span></td></tr>
                        <tr><td class="pl-0"><strong>Sales</strong></td><td>: {{ $kunjungan->sales_nama ?? $kunjungan->user->name ?? '—' }}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless table-sm mb-0">
                        <tr>
                            <td class="pl-0" style="width:35%"><strong>Status</strong></td>
                            <td>:
                                @if($kunjungan->status === 'Approved') <span class="badge badge-success">Disetujui</span>
                                @elseif($kunjungan->status === 'Pending') <span class="badge badge-warning">Pending</span>
                                @elseif($kunjungan->status === 'Rejected') <span class="badge badge-danger">Ditolak</span>
                                @else <span class="badge badge-secondary">{{ $kunjungan->status }}</span> @endif
                            </td>
                        </tr>
                        <tr><td class="pl-0"><strong>Gudang</strong></td><td>: {{ $kunjungan->gudang->nama_gudang ?? '—' }}</td></tr>
                        @if($kunjungan->memo)
                        <tr><td class="pl-0"><strong>Memo</strong></td><td>: {{ $kunjungan->memo }}</td></tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if($kunjungan->items && $kunjungan->items->count() > 0)
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0 font-weight-bold text-primary"><i class="fas fa-box mr-1"></i> Produk Terkait</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Produk</th>
                            <th class="text-center">Qty</th>
                            <th>Batch</th>
                            <th>Expired</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($kunjungan->items as $item)
                        <tr>
                            <td><strong>{{ $item->produk->nama_produk ?? '—' }}</strong></td>
                            <td class="text-center">{{ $item->jumlah }}</td>
                            <td>{{ $item->batch_number ?? '—' }}</td>
                            <td>{{ $item->expired_date ? $item->expired_date->format('d/m/Y') : '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
@endsection
