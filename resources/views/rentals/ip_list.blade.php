@extends('layouts.app')

@section('content')
<div class="container">
    <h3 class="mb-4">Daftar IP TV</h3>

    @if($ips->isEmpty())
        <div class="alert alert-info">Tidak ada data IP TV.</div>
    @else
        <table class="table table-bordered">
            <thead class="thead-light">
                <tr>
                    <th>No</th>
                    <th>IP Address</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ips as $index => $ip)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $ip->tv_ip }}</td>
                    <td>
                        <a href="{{ route('rental.history', $ip->tv_ip) }}" class="btn btn-primary btn-sm">
                            Lihat Riwayat
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
