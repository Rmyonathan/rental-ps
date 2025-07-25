@extends('layouts.app')

@section('content')
<div class="container">
    <h3 class="mb-4">Riwayat Peminjaman - IP: {{ $tv->ip_address }}</h3>

    <a href="{{ route('rental.iplist') }}" class="btn btn-secondary mb-3">← Kembali ke Daftar IP TV</a>

    {{-- Search & Date Filter --}}
    <form method="GET" class="mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Cari nama penyewa atau keterangan..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <label for="start_date" class="form-label">Dari Tanggal</label>
                <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label">Sampai Tanggal</label>
                <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Cari</button>
            </div>
        </div>
    </form>

    @if ($rentals->isEmpty())
        <div class="alert alert-info">Belum ada riwayat peminjaman untuk TV ini.</div>
    @else
        <div class="card">
            <div class="card-body">
                <table class="table table-bordered table-striped mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>No</th>
                            <th>Nama Penyewa</th>
                            <th>Tanggal Mulai</th>
                            <th>Tanggal Selesai</th>
                            <th>Status</th>
                            <th>Durasi Pemakaian (menit)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rentals as $index => $rental)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $rental->customer_name ?? '-' }}</td>
                                <td>{{ $rental->start_time ? $rental->start_time->format('d/m/Y H:i') : '-' }}</td>
                                <td>{{ $rental->end_time ? $rental->end_time->format('d/m/Y H:i') : '-' }}</td>
                                <td>{{ ucfirst($rental->status) }}</td>
                                <td>{{ $rental->duration_minutes ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-light">
                <strong>Total Pemakaian:</strong> {{ $totalDuration }} menit
                <br>
                <small class="text-muted">≈ {{ $totalHours }} jam</small>
            </div>
        </div>
    @endif
</div>
@endsection
