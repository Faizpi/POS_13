@extends('customer.layouts.app')

@section('title', 'Dashboard Customer')

@section('content')
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="font-weight-bold mb-1">Selamat datang, {{ $kontak->nama }}</h4>
            <p class="text-muted">Lihat riwayat transaksi dan kunjungan Anda</p>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="stat-card d-flex align-items-center">
                <div class="stat-icon mr-3" style="background:rgba(37,99,235,0.1);color:var(--portal-primary)">
                    <i class="fas fa-receipt"></i>
                </div>
                <div>
                    <div class="stat-label text-primary">Total Transaksi</div>
                    <div class="stat-value">{{ $totalTransaksi }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stat-card d-flex align-items-center">
                <div class="stat-icon mr-3" style="background:rgba(5,150,105,0.1);color:var(--portal-success)">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div>
                    <div class="stat-label" style="color:var(--portal-success)">Total Nilai</div>
                    <div class="stat-value">Rp {{ number_format($totalNilai, 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stat-card d-flex align-items-center">
                <div class="stat-icon mr-3" style="background:rgba(8,145,178,0.1);color:var(--portal-info)">
                    <i class="fas fa-id-card"></i>
                </div>
                <div>
                    <div class="stat-label" style="color:var(--portal-info)">Kode Kontak</div>
                    <div class="stat-value">{{ $kontak->kode_kontak ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 font-weight-bold text-primary">Riwayat Transaksi</h6>
                    <a href="{{ route('customer.history') }}" class="btn btn-sm btn-primary">Lihat Semua</a>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-0">Lihat seluruh riwayat transaksi Anda.</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 font-weight-bold text-primary">Riwayat Kunjungan</h6>
                    <a href="{{ route('customer.kunjungan') }}" class="btn btn-sm btn-primary">Lihat Semua</a>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-0">Lihat seluruh riwayat kunjungan sales ke toko Anda.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
