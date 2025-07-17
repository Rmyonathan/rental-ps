@foreach($activeRentals as $rental)
    <div class="col-md-6 col-lg-4 mb-4 rental-card" id="rental-{{ $rental->id }}">
        <div class="card h-100 {{ $rental->status == 'active' ? 'border-success' : 'border-danger' }}">
            <div class="card-header d-flex justify-content-between align-items-center {{ $rental->status == 'active' ? 'bg-success' : 'bg-danger' }} text-white">
                <h6 class="mb-0">
                    <i class="bi bi-person"></i> {{ $rental->customer_name }}
                </h6>
                <span class="badge {{ $rental->status == 'active' ? 'bg-dark' : 'bg-dark' }}">{{ ucfirst($rental->status) }}</span>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong><i class="bi bi-controller"></i> Station:</strong> {{ $rental->ps_station }}<br>
                    <strong><i class="bi bi-tv"></i> TV IP:</strong> {{ $rental->tv_ip }}<br>
                    @if($rental->phone)
                        <strong><i class="bi bi-telephone"></i> Phone:</strong> {{ $rental->phone }}<br>
                    @endif
                    <strong><i class="bi bi-currency-dollar"></i> Price:</strong> ${{ number_format($rental->price, 2) }}
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <small class="text-muted">Started:</small>
                        <small>{{ $rental->start_time->format('M d, H:i') }}</small>
                    </div>
                    <div class="d-flex justify-content-between">
                        <small class="text-muted">Ends:</small>
                        <small data-rental-id="{{ $rental->id }}" data-end-time="{{ $rental->end_time }}">{{ $rental->end_time->format('M d, H:i') }}</small>
                    </div>
                    <div class="d-flex justify-content-between">
                        <small class="text-muted">Duration:</small>
                        <small>{{ $rental->duration_minutes }}min</small>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    @if($rental->status == 'active')
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="forceTimeout({{ $rental->id }})">
                            <i class="bi bi-stop-circle"></i> Force Timeout
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm" onclick="completeRental({{ $rental->id }})">
                            <i class="bi bi-check-circle"></i> Complete
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endforeach

@if($activeRentals->isEmpty())
    <div class="text-center py-5" id="noRentalsMessage">
        <i class="bi bi-controller" style="font-size: 4rem; color: #ccc;"></i>
        <h4 class="mt-3 text-muted">No Active Rentals</h4>
        <p class="text-muted">All PlayStation stations are available for rent.</p>
        <a href="{{ route('rentals.create') }}" class="btn btn-primary btn-lg">
            <i class="bi bi-plus-circle"></i> Start New Rental
        </a>
    </div>
@endif
