@extends('customer.layouts.app')

@section('title', 'Riwayat Transaksi')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="font-weight-bold mb-0">Riwayat Transaksi</h4>
        <a href="{{ route('customer.dashboard') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0 font-weight-bold text-primary"><i class="fas fa-filter mr-1"></i> Filter</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row">
                <div class="col-md-4 mb-2">
                    <input type="date" name="dari" class="form-control form-control-sm" value="{{ request('dari') }}" placeholder="Dari">
                </div>
                <div class="col-md-4 mb-2">
                    <input type="date" name="sampai" class="form-control form-control-sm" value="{{ request('sampai') }}" placeholder="Sampai">
                </div>
                <div class="col-md-4 mb-2 d-flex" style="gap:0.5rem">
                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    <a href="{{ route('customer.history') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="p-3">
                @forelse($penjualans as $item)
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="font-weight-bold mb-1">{{ $item->nomor }}</h6>
                                    <div class="text-muted small">
                                        <i class="far fa-calendar-alt mr-1"></i>{{ $item->tgl_transaksi->format('d M Y') }}
                                        @if($item->gudang)
                                            <i class="fas fa-warehouse ml-2 mr-1"></i>{{ $item->gudang->nama_gudang ?? '' }}
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-weight-bold" style="color:var(--portal-primary);font-size:1.05rem">Rp {{ number_format($item->grand_total, 0, ',', '.') }}</div>
                                    <div class="mt-1">
                                        @if($item->status === 'Lunas') <span class="badge badge-success">Lunas</span>
                                        @elseif($item->status === 'Approved') <span class="badge badge-info">Disetujui</span>
                                        @elseif($item->status === 'Pending') <span class="badge badge-warning">Pending</span>
                                        @elseif($item->status === 'Rejected') <span class="badge badge-danger">Ditolak</span>
                                        @else <span class="badge badge-secondary">{{ $item->status }}</span> @endif
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2">
                                <a href="{{ route('customer.history.detail', $item->id) }}" class="btn btn-sm btn-outline-primary">
                                    Lihat Detail <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3" style="opacity:0.4"></i>
                        <p>Belum ada riwayat transaksi.</p>
                    </div>
                @endforelse
            </div>

            @if($penjualans->hasPages())
                <div class="d-flex justify-content-center pb-3">
                    {{ $penjualans->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
