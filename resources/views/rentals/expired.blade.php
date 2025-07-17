@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-clock-history"></i> PlayStation Rental History</h2>
    <div class="d-flex gap-2">
        <a href="{{ route('rentals.index') }}" class="btn btn-primary">
            <i class="bi bi-arrow-left"></i> Back to Active
        </a>
        <button type="button" class="btn btn-danger btn-sm" onclick="clearAllExpired()">
            <i class="bi bi-trash"></i> Clear All
        </button>
    </div>
</div>

<!-- Statistics Card -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card bg-secondary text-white">
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <h4>{{ $expiredRentals->count() }}</h4>
                        <small>Total Records</small>
                    </div>
                    <div class="col-md-3">
                        <h4>{{ $expiredRentals->where('created_at', '>=', now()->subDay())->count() }}</h4>
                        <small>Last 24 Hours</small>
                    </div>
                    <div class="col-md-3">
                        <h4>${{ number_format($expiredRentals->sum('price'), 2) }}</h4>
                        <small>Rental Revenue</small>
                    </div>
                    <div class="col-md-3">
                        <h4>${{ number_format($expiredRentals->sum(function($rental) { return $rental->cafeOrders->sum('total'); }), 2) }}</h4>
                        <small>Cafe Revenue</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($expiredRentals->isEmpty())
    <div class="text-center py-5">
        <i class="bi bi-clock-history" style="font-size: 4rem; color: #ccc;"></i>
        <h4 class="mt-3 text-muted">No Rental History</h4>
        <p class="text-muted">No completed rentals found.</p>
        <a href="{{ route('rentals.index') }}" class="btn btn-primary btn-lg">
            <i class="bi bi-arrow-left"></i> Back to Active Rentals
        </a>
    </div>
@else
    <div class="row">
        @foreach($expiredRentals as $rental)
            <div class="col-md-6 col-lg-4 mb-4" id="expired-rental-{{ $rental->id }}">
                <div class="card h-100 border-secondary">
                    <div class="card-header d-flex justify-content-between align-items-center bg-secondary text-white">
                        <h6 class="mb-0">
                            <i class="bi bi-person"></i> {{ $rental->customer_name }}
                        </h6>
                        <span class="badge bg-dark">History</span>
                    </div>
                    <div class="card-body">
                        <!-- Rental Details -->
                        <div class="mb-3">
                            <strong><i class="bi bi-controller"></i> Station:</strong> {{ $rental->ps_station }}<br>
                            <strong><i class="bi bi-tv"></i> TV IP:</strong> {{ $rental->tv_ip }}<br>
                            @if($rental->phone)
                                <strong><i class="bi bi-telephone"></i> Phone:</strong> {{ $rental->phone }}<br>
                            @endif
                            <strong><i class="bi bi-currency-dollar"></i> Rental:</strong> ${{ number_format($rental->price, 2) }}
                        </div>

                        <!-- Time Details -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Started:</small>
                                <small>{{ $rental->start_time->format('M d, H:i') }}</small>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Ended:</small>
                                <small>{{ $rental->end_time->format('M d, H:i') }}</small>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Duration:</small>
                                <small>{{ $rental->duration_minutes }}min</small>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Completed:</small>
                                <small class="text-success">{{ $rental->end_time->diffForHumans() }}</small>
                            </div>
                        </div>

                        <!-- Cafe Orders Section -->
                        @if($rental->cafeOrders->count() > 0)
                            <div class="mb-3">
                                <h6 class="text-muted mb-2">
                                    <i class="bi bi-cup-hot"></i> Cafe Orders ({{ $rental->cafeOrders->count() }})
                                </h6>
                                @foreach($rental->cafeOrders as $cafeOrder)
                                    <div class="small mb-2 p-2 bg-light rounded">
                                        <div class="d-flex justify-content-between">
                                            <span class="fw-bold">Order #{{ $cafeOrder->order_number }}</span>
                                            <span class="badge bg-success">{{ ucfirst($cafeOrder->status) }}</span>
                                        </div>
                                        <div class="mt-1">
                                            @foreach($cafeOrder->items as $item)
                                                <div class="d-flex justify-content-between">
                                                    <span>{{ $item->cafeItem->name }} x{{ $item->quantity }}</span>
                                                    <span>${{ number_format($item->total_price, 2) }}</span>
                                                </div>
                                            @endforeach
                                            <hr class="my-1">
                                            <div class="d-flex justify-content-between fw-bold">
                                                <span>Total:</span>
                                                <span>${{ number_format($cafeOrder->total, 2) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <!-- Total Summary -->
                        <div class="mb-3 p-2 bg-info bg-opacity-10 rounded">
                            <div class="d-flex justify-content-between">
                                <strong>Rental Total:</strong>
                                <strong>${{ number_format($rental->price, 2) }}</strong>
                            </div>
                            @if($rental->cafeOrders->count() > 0)
                                <div class="d-flex justify-content-between">
                                    <strong>Cafe Total:</strong>
                                    <strong>${{ number_format($rental->cafeOrders->sum('total'), 2) }}</strong>
                                </div>
                                <hr class="my-1">
                                <div class="d-flex justify-content-between text-success">
                                    <strong>Grand Total:</strong>
                                    <strong>${{ number_format($rental->price + $rental->cafeOrders->sum('total'), 2) }}</strong>
                                </div>
                            @endif
                        </div>

                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="viewRentalDetails({{ $rental->id }})">
                                <i class="bi bi-eye"></i> View Full Details
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteExpiredRental({{ $rental->id }})">
                                <i class="bi bi-trash"></i> Remove from History
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<!-- Detail Modal -->
<div class="modal fade" id="rentalDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rental Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="rentalDetailContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function viewRentalDetails(rentalId) {
    const modal = new bootstrap.Modal(document.getElementById('rentalDetailModal'));
    const content = document.getElementById('rentalDetailContent');
    
    content.innerHTML = '<div class="text-center"><div class="spinner-border"></div><p>Loading details...</p></div>';
    modal.show();
    
    fetch(`/rentals/${rentalId}`)
        .then(response => response.text())
        .then(html => {
            content.innerHTML = html;
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Failed to load rental details.</div>';
        });
}

function deleteExpiredRental(rentalId) {
    if (confirm('Remove this rental from history? This cannot be undone.')) {
        fetch(`/rentals/${rentalId}/delete-expired`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const card = document.getElementById(`expired-rental-${rentalId}`);
                card.style.transition = 'opacity 0.3s ease-out';
                card.style.opacity = '0';
                
                setTimeout(() => {
                    card.remove();
                    checkIfNoExpiredLeft();
                }, 300);
            } else {
                alert('Failed to remove rental: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error removing rental. Please try again.');
        });
    }
}

function clearAllExpired() {
    if (confirm('Remove ALL rental history? This cannot be undone.')) {
        fetch('/rentals/clear-all-expired', {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to clear rental history: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error clearing rental history. Please try again.');
        });
    }
}

function checkIfNoExpiredLeft() {
    const expiredCards = document.querySelectorAll('[id^="expired-rental-"]');
    if (expiredCards.length === 0) {
        location.reload();
    }
}
</script>
@endsection
