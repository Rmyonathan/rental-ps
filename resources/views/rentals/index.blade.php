@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-controller"></i> PlayStation Rental Dashboard</h2>
    <div class="d-flex gap-2">
        <a href="{{ route('rentals.ip-list') }}" class="btn btn-outline-secondary">
            <i class="bi bi-clock-history"></i> View History
        </a>
        <button class="btn btn-info" onclick="refreshAllStations()">
            <i class="bi bi-arrow-clockwise"></i> Refresh Statuses
        </button>
        <a href="{{ route('rentals.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> New Rental
        </a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h4>{{ $activeRentals->count() }}</h4>
                <small>Active Rentals</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h4>{{ $activeRentals->where('end_time', '<=', now()->addMinutes(15))->count() }}</h4>
                <small>Ending Soon</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h4>{{ count($tvStations) - $activeRentals->count() }}</h4>
                <small>Available Stations</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h4>${{ number_format($activeRentals->sum('price'), 2) }}</h4>
                <small>Active Revenue</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    @forelse($tvStations as $station)
        <div class="col-md-6 col-lg-4 mb-4" @if($station->rental) id="rental-card-{{ $station->rental->id }}" @endif>
            <div class="card h-100 station-card" data-tv-ip="{{ $station->ip }}">
                <div class="card-header d-flex justify-content-between align-items-center {{ $station->rental ? 'bg-success' : 'bg-secondary' }} text-white">
                    <h5 class="mb-0"><i class="bi bi-tv"></i> {{ $station->station_name }}</h5>
                    <span class="badge bg-light text-dark">{{ $station->ip }}</span>
                </div>
                
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        @if($station->rental)
                            <span class="badge bg-success fs-6">Occupied</span>
                        @else
                            <span class="badge bg-secondary fs-6">Available</span>
                        @endif
                        <div class="connection-status" id="status-{{ str_replace('.', '-', $station->ip) }}">
                            <span class="badge bg-secondary">Checking...</span>
                        </div>
                    </div>

                    @if($station->rental)
                        <div class="status-info flex-grow-1">
                             <p class="mb-1"><strong>Customer:</strong> {{ $station->rental->customer_name }}</p>
                            <p class="mb-1"><strong>Ends:</strong> {{ Carbon\Carbon::parse($station->rental->end_time)->format('h:i A') }}</p>
                            <div class="countdown-container my-2" 
                                 data-end-time="{{ Carbon\Carbon::parse($station->rental->end_time)->toISOString() }}" 
                                 data-rental-id="{{ $station->rental->id }}">
                                <div class="countdown-display text-center">
                                    <span class="countdown-time fw-bold fs-5">--:--:--</span>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-center text-muted my-4 flex-grow-1 d-flex flex-column justify-content-center">
                            <i class="bi bi-controller display-4"></i>
                            <p>Ready for new rental</p>
                        </div>
                    @endif
                </div>
                
                <div class="card-footer bg-light">
                    <div class="btn-group w-100 mb-2" role="group">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="controlTv('{{ $station->ip }}', 'volume_up')" title="Volume Up"><i class="bi bi-volume-up"></i></button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="controlTv('{{ $station->ip }}', 'volume_down')" title="Volume Down"><i class="bi bi-volume-down"></i></button>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="controlTv('{{ $station->ip }}', 'power_off')" title="Power Off"><i class="bi bi-power"></i></button>
                    </div>

                    <div class="d-grid gap-2">
                        @if($station->rental)
                             <div class="btn-group w-100">
                                <button type="button" class="btn btn-primary btn-sm" onclick="showExtendModal({{ $station->rental->id }})">Extend</button>
                                <button type="button" class="btn btn-danger btn-sm" onclick="forceEndRental({{ $station->rental->id }})">End Now</button>
                             </div>
                        @else
                            <a href="{{ route('rentals.create', ['tv_ip' => $station->ip, 'station' => $station->station_name]) }}" class="btn btn-success">
                                <i class="bi bi-play-circle"></i> Start Rental
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="alert alert-warning text-center">
                <h4><i class="bi bi-exclamation-triangle"></i> No TV Stations Found</h4>
                <p>Could not fetch the list of TVs from the ADB server. Please ensure the Python script is running and configured correctly.</p>
            </div>
        </div>
    @endforelse
</div>

<div class="modal fade" id="extendModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Extend Rental Time</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="extendForm">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="rental_id" id="extendRentalId">
                    <div class="mb-3">
                        <label class="form-label">Additional Time</label>
                        <select class="form-select" name="additional_minutes" id="additionalMinutes" required>
                            <option value="15" data-price="2.00">15 minutes - $2.00</option>
                            <option value="30" data-price="4.00">30 minutes - $4.00</option>
                            <option value="60" data-price="7.00">1 hour - $7.00</option>
                            <option value="120" data-price="13.00">2 hours - $13.00</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Additional Price</label>
                        <input type="number" class="form-control" name="additional_price" id="additionalPrice" step="0.01" required readonly>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Extend Rental</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all countdowns
    document.querySelectorAll('.countdown-container').forEach(initCountdown);
    setInterval(updateAllCountdowns, 1000);
    
    // Refresh all station statuses at once on page load
    refreshAllStations();
    
    // Handle extend modal price update and form submission
    const extendForm = document.getElementById('extendForm');
    const additionalMinutesSelect = document.getElementById('additionalMinutes');
    const additionalPriceInput = document.getElementById('additionalPrice');

    additionalMinutesSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        additionalPriceInput.value = selectedOption.dataset.price;
    });

    extendForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const rentalId = document.getElementById('extendRentalId').value;
        const submitButton = extendForm.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Extending...';

        fetch(`/rentals/${rentalId}/extend`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(new FormData(extendForm))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Rental extended successfully!');
                const extendModal = bootstrap.Modal.getInstance(document.getElementById('extendModal'));
                extendModal.hide();
                location.reload();
            } else {
                alert('Error extending rental: ' + data.message);
            }
        }).catch(err => {
            console.error('Extend rental error:', err);
            alert('A network error occurred.');
        }).finally(() => {
            submitButton.disabled = false;
            submitButton.innerHTML = 'Extend Rental';
        });
    });
});

async function refreshAllStations() {
    document.querySelectorAll('.connection-status').forEach(el => {
        el.innerHTML = `<span class="badge bg-secondary">Testing...</span>`;
    });

    try {
        const response = await fetch('{{ route("rentals.refresh-all") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        const statuses = await response.json();

        for (const ip in statuses) {
            const status = statuses[ip];
            const statusEl = document.getElementById(`status-${ip.replace(/\./g, '-')}`);
            if (statusEl) {
                if (status.success) {
                    statusEl.innerHTML = `<span class="badge bg-success" title="${status.message}">${status.message}</span>`;
                } else {
                    statusEl.innerHTML = `<span class="badge bg-danger" title="${status.error}">${status.error}</span>`;
                }
            }
        }
    } catch (error) {
        console.error('Failed to refresh station statuses:', error);
        document.querySelectorAll('.connection-status').forEach(el => {
            el.innerHTML = `<span class="badge bg-danger" title="Connection error">Error</span>`;
        });
    }
}

function initCountdown(container) {
    const endTime = new Date(container.dataset.endTime).getTime();
    container.dataset.endTimestamp = endTime;
}

function updateAllCountdowns() {
    document.querySelectorAll('.countdown-container').forEach(container => {
        const endTimestamp = parseInt(container.dataset.endTimestamp, 10);
        const rentalId = container.dataset.rentalId;
        const card = document.getElementById(`rental-card-${rentalId}`);

        if (!card || card.dataset.isExpiring) return;

        const now = new Date().getTime();
        const remaining = endTimestamp - now;
        const timeSpan = container.querySelector('.countdown-time');

        if (remaining <= 0) {
            timeSpan.textContent = 'ENDING...';
            timeSpan.classList.add('text-danger');
            card.dataset.isExpiring = 'true';
            handleRentalCompletion(rentalId);
        } else {
            const hours = Math.floor(remaining / (1000 * 60 * 60));
            const minutes = Math.floor((remaining % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((remaining % (1000 * 60)) / 1000);
            timeSpan.textContent = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            
            if (remaining < 5 * 60 * 1000) { timeSpan.className = 'countdown-time fw-bold fs-5 text-danger'; }
            else if (remaining < 15 * 60 * 1000) { timeSpan.className = 'countdown-time fw-bold fs-5 text-warning'; }
            else { timeSpan.className = 'countdown-time fw-bold fs-5 text-success'; }
        }
    });
}

async function handleRentalCompletion(rentalId) {
    console.log(`Rental ${rentalId} expired. Triggering timeout video and removing card.`);
    try {
        const response = await fetch(`/rentals/${rentalId}/force-timeout`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
        });
        const data = await response.json();
        if (data.success || response.ok) {
            console.log(data.message);
            const cardElement = document.getElementById(`rental-card-${rentalId}`);
            if (cardElement) {
                cardElement.style.transition = 'opacity 0.5s ease-out';
                cardElement.style.opacity = '0';
                setTimeout(() => {
                    // Instead of removing, we'll just reload to get the fresh state from the server
                    location.reload();
                }, 500);
            }
        } else {
            console.error('Failed to trigger timeout on backend:', data.message);
            alert('An error occurred while ending the rental automatically. Please check the TV.');
            location.reload();
        }
    } catch (error) {
        console.error('Error during automatic rental completion:', error);
        alert('A network error occurred. Reloading page.');
        location.reload();
    }
}

function showExtendModal(rentalId) {
    document.getElementById('extendRentalId').value = rentalId;
    document.getElementById('additionalMinutes').dispatchEvent(new Event('change'));
    new bootstrap.Modal(document.getElementById('extendModal')).show();
}

function forceEndRental(rentalId) {
    if (confirm('Are you sure you want to end this rental immediately? The timeout video will play on the TV.')) {
        const button = event.target;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Ending...';
        
        fetch(`/rentals/${rentalId}/force-timeout`, {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            location.reload();
        }).catch(err => {
            alert('An error occurred while ending the rental.');
            button.disabled = false;
            button.innerHTML = 'End Now';
        });
    }
}

async function controlTv(tvIp, action) {
    const button = event.target.closest('button');
    const originalContent = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    try {
        const response = await fetch('{{ route("tv.control") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ tv_ip: tvIp, action: action })
        });
        const result = await response.json();
        if (!result.success) {
            alert(`Failed to perform action '${action}' on ${tvIp}: ${result.error}`);
        }
    } catch (error) {
        alert(`Failed to perform action '${action}'. Network error or server is down.`);
    } finally {
        setTimeout(() => {
            button.disabled = false;
            button.innerHTML = originalContent;
        }, 1000);
    }
}
</script>
@endsection