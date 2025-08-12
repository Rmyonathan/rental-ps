
@extends('layouts.app')

@section('content')
<div class="container">
    <h3 class="mb-4">Daftar Stasiun TV & Riwayat</h3>

    <a href="{{ route('rentals.index') }}" class="btn btn-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
    </a>

    {{-- // EDIT: Changed the variable name from $ips to $stations --}}
    @if($stations->isEmpty())
        <div class="alert alert-info">Tidak ada stasiun TV yang dikonfigurasi.</div>
    @else
        <table class="table table-bordered table-hover">
            <thead class="thead-light">
                <tr>
                    <th>No</th>
                    <th>Nama Stasiun</th>
                    <th>IP Address</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                {{-- // EDIT: Updated the loop and variables to handle the new station object --}}
                @foreach($stations as $index => $station)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td><strong>{{ $station->station_name }}</strong></td> 
                    <td>{{ $station->ip }}</td>
                    <td>
                        <a href="{{ route('rentals.history', $station->ip) }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-clock-history"></i> Lihat Riwayat
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection