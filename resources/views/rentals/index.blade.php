    @extends('layouts.app')

    @section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-controller"></i> PlayStation Rental System</h2>
        <div class="d-flex gap-2">
            <a href="{{ route('rentals.expired') }}" class="btn btn-outline-secondary">
                <i class="bi bi-clock-history"></i> View Expired Rentals
            </a>
            <button class="btn btn-info" onclick="refreshAllStations()">
                <i class="bi bi-arrow-clockwise"></i> Refresh Status
            </button>
        </div>
    </div>

    <!-- Statistics Card -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <h4 id="activeCount">{{ $activeRentals->count() }}</h4>
                            <small>Active Rentals</small>
                        </div>
                        <div class="col-md-3">
                            <h4 id="endingSoonCount">{{ $activeRentals->where('end_time', '<=', now()->addMinutes(15))->count() }}</h4>
                            <small>Ending Soon</small>
                        </div>
                        <div class="col-md-3">
                            <h4 id="expiredCount">{{ $activeRentals->where('end_time', '<=', now())->count() }}</h4>
                            <small>Expired</small>
                        </div>
                        <div class="col-md-3">
                            <h4 id="totalRevenue">${{ number_format($activeRentals->sum('price'), 2) }}</h4>
                            <small>Total Revenue</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PlayStation Stations -->
    <div class="row mb-4">
        <!-- Xiaomi TV Station -->
        <div class="col-md-6 mb-4">
            <div class="card h-100 station-card" data-tv-ip="192.168.1.20">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-tv"></i> PlayStation Station 1
                    </h5>
                    <span class="badge bg-light text-dark">Xiaomi TV</span>
                </div>
                
                @php
                    $xiaomiRental = $activeRentals->where('tv_ip', '192.168.1.20')->first();
                @endphp
                
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="text-muted">Status:</h6>
                            @if($xiaomiRental)
                                <div class="status-info">
                                    <span class="badge bg-success mb-2">OCCUPIED</span>
                                    <p class="mb-1"><strong>Customer:</strong> {{ $xiaomiRental->customer_name }}</p>
                                    @if($xiaomiRental->phone)
                                        <p class="mb-1"><strong>Phone:</strong> {{ $xiaomiRental->phone }}</p>
                                    @endif
                                    <p class="mb-1"><strong>Started:</strong> {{ $xiaomiRental->start_time->format('H:i') }}</p>
                                    <p class="mb-1"><strong>Ends:</strong> {{ $xiaomiRental->end_time->format('H:i') }}</p>
                                    <p class="mb-1"><strong>Price:</strong> ${{ number_format($xiaomiRental->price, 2) }}</p>
                                    
                                    <!-- Real-time Countdown -->
                                    <div class="countdown-container mb-2" 
                                        data-end-time="{{ $xiaomiRental->end_time->toISOString() }}"
                                        data-rental-id="{{ $xiaomiRental->id }}">
                                        <div class="progress mb-2">
                                            <div class="progress-bar countdown-progress" style="width: 0%"></div>
                                        </div>
                                        <div class="countdown-display text-center">
                                            <span class="countdown-time">--:--:--</span>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <span class="badge bg-secondary mb-2">AVAILABLE</span>
                                <p class="text-muted">Station is ready for new rental</p>
                                <p class="mb-0"><strong>TV IP:</strong> 192.168.1.20</p>
                            @endif
                        </div>
                        <div class="col-md-4 text-center">
                            <i class="bi bi-controller display-4 text-muted mb-3"></i>
                            <div class="connection-status" id="status-192.168.1.20">
                                <span class="badge bg-secondary">Not Tested</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Add this after the countdown display and before the card-footer -->
                @if($xiaomiRental && $xiaomiRental->cafeOrders->count() > 0)
                    <div class="mt-3">
                        <h6 class="text-muted mb-2">
                            <i class="bi bi-cup-hot"></i> Cafe Orders ({{ $xiaomiRental->cafeOrders->count() }})
                        </h6>
                        <div class="cafe-orders-summary">
                            @foreach($xiaomiRental->cafeOrders as $cafeOrder)
                                <div class="small mb-2 p-2 bg-light rounded">
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-bold">Order #{{ $cafeOrder->order_number }}</span>
                                        <span class="badge bg-{{ $cafeOrder->status === 'delivered' ? 'success' : ($cafeOrder->status === 'ready' ? 'warning' : 'info') }}">
                                            {{ ucfirst($cafeOrder->status) }}
                                        </span>
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
                    </div>
                @endif

                <div class="card-footer">
                <!-- TV Control Buttons -->
                    <div class="mb-2">
                        <small class="text-muted">TV Controls:</small>
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="controlTv('192.168.1.20', 'volume_up')">
                                <i class="bi bi-volume-up"></i> Vol+
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="controlTv('192.168.1.20', 'volume_down')">
                                <i class="bi bi-volume-down"></i> Vol-
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="controlTv('192.168.1.20', 'power_off')">
                                <i class="bi bi-power"></i> Power Off
                            </button>
                        </div>
                    </div>
                
                    <div class="btn-group w-100" role="group">
                        @if($xiaomiRental)
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="showExtendModal({{ $xiaomiRental->id }})">
                                <i class="bi bi-clock-history"></i> Extend
                            </button>
                            
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="forceEndRental({{ $xiaomiRental->id }})">
                                <i class="bi bi-stop-circle"></i> End Rental
                            </button>
                            
                            <button type="button" class="btn btn-outline-success btn-sm" onclick="completeRental({{ $xiaomiRental->id }})">
                                <i class="bi bi-check-circle"></i> Complete
                            </button>
                        @else
                            <a href="{{ route('rentals.create', ['tv_ip' => '192.168.1.20', 'station' => 'PS1']) }}" 
                            class="btn btn-success btn-sm">
                                <i class="bi bi-play-circle"></i> Start Rental
                            </a>
                            
                            <button type="button" class="btn btn-outline-info btn-sm" onclick="testConnection('192.168.1.20')">
                                <i class="bi bi-wifi"></i> Test TV
                            </button>
                        @endif
                        
                        <button type="button" class="btn btn-outline-warning btn-sm" 
                                onclick="@if($xiaomiRental) showCafeModal('192.168.1.20', {{ $xiaomiRental->id }}) @else showCafeModal('192.168.1.20') @endif"
                                @if(!$xiaomiRental) disabled title="No active rental - Cafe only available during rentals or as separate order" @endif>
                            <i class="bi bi-cup-hot"></i> Cafe
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- TCL TV Station -->
        <div class="col-md-6 mb-4">
            <div class="card h-100 station-card" data-tv-ip="192.168.1.21">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-tv"></i> PlayStation Station 2
                    </h5>
                    <span class="badge bg-light text-dark">TCL TV</span>
                </div>
                
                @php
                    $tclRental = $activeRentals->where('tv_ip', '192.168.1.21')->first();
                @endphp
                
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="text-muted">Status:</h6>
                            @if($tclRental)
                                <div class="status-info">
                                    <span class="badge bg-success mb-2">OCCUPIED</span>
                                    <p class="mb-1"><strong>Customer:</strong> {{ $tclRental->customer_name }}</p>
                                    @if($tclRental->phone)
                                        <p class="mb-1"><strong>Phone:</strong> {{ $tclRental->phone }}</p>
                                    @endif
                                    <p class="mb-1"><strong>Started:</strong> {{ $tclRental->start_time->format('H:i') }}</p>
                                    <p class="mb-1"><strong>Ends:</strong> {{ $tclRental->end_time->format('H:i') }}</p>
                                    <p class="mb-1"><strong>Price:</strong> ${{ number_format($tclRental->price, 2) }}</p>
                                    
                                    <!-- Real-time Countdown -->
                                    <div class="countdown-container mb-2" 
                                        data-end-time="{{ $tclRental->end_time->toISOString() }}"
                                        data-rental-id="{{ $tclRental->id }}">
                                        <div class="progress mb-2">
                                            <div class="progress-bar countdown-progress" style="width: 0%"></div>
                                        </div>
                                        <div class="countdown-display text-center">
                                            <span class="countdown-time">--:--:--</span>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <span class="badge bg-secondary mb-2">AVAILABLE</span>
                                <p class="text-muted">Station is ready for new rental</p>
                                <p class="mb-0"><strong>TV IP:</strong> 192.168.1.21</p>
                            @endif
                        </div>
                        <div class="col-md-4 text-center">
                            <i class="bi bi-controller display-4 text-muted mb-3"></i>
                            <div class="connection-status" id="status-192.168.1.21">
                                <span class="badge bg-secondary">Not Tested</span>
                            </div>
                        </div>
                    </div>
                </div>

                @if($tclRental && $tclRental->cafeOrders->count() > 0)
                    <div class="mt-3">
                        <h6 class="text-muted mb-2">
                            <i class="bi bi-cup-hot"></i> Cafe Orders ({{ $tclRental->cafeOrders->count() }})
                        </h6>
                        <div class="cafe-orders-summary">
                            @foreach($tclRental->cafeOrders as $cafeOrder)
                                <div class="small mb-2 p-2 bg-light rounded">
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-bold">Order #{{ $cafeOrder->order_number }}</span>
                                        <span class="badge bg-{{ $cafeOrder->status === 'delivered' ? 'success' : ($cafeOrder->status === 'ready' ? 'warning' : 'info') }}">
                                            {{ ucfirst($cafeOrder->status) }}
                                        </span>
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
                    </div>
                @endif
                
                <div class="card-footer">

                    <div class="mb-2">
                        <small class="text-muted">TV Controls:</small>
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="controlTv('192.168.1.21', 'volume_up')">
                                <i class="bi bi-volume-up"></i> Vol+
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="controlTv('192.168.1.21', 'volume_down')">
                                <i class="bi bi-volume-down"></i> Vol-
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="controlTv('192.168.1.21', 'power_off')">
                                <i class="bi bi-power"></i> Power Off
                            </button>
                        </div>
                    </div>

                    <div class="btn-group w-100" role="group">
                        @if($tclRental)
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="showExtendModal({{ $tclRental->id }})">
                                <i class="bi bi-clock-history"></i> Extend
                            </button>
                            
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="forceEndRental({{ $tclRental->id }})">
                                <i class="bi bi-stop-circle"></i> End Rental
                            </button>
                            
                            <button type="button" class="btn btn-outline-success btn-sm" onclick="completeRental({{ $tclRental->id }})">
                                <i class="bi bi-check-circle"></i> Complete
                            </button>
                        @else
                            <a href="{{ route('rentals.create', ['tv_ip' => '192.168.1.21', 'station' => 'PS2']) }}" 
                            class="btn btn-success btn-sm">
                                <i class="bi bi-play-circle"></i> Start Rental
                            </a>
                            
                            <button type="button" class="btn btn-outline-info btn-sm" onclick="testConnection('192.168.1.21')">
                                <i class="bi bi-wifi"></i> Test TV
                            </button>
                        @endif
                        
                        <button type="button" class="btn btn-outline-warning btn-sm" 
                                onclick="@if($tclRental) showCafeModal('192.168.1.21', {{ $tclRental->id }}) @else showCafeModal('192.168.1.21') @endif"
                                @if(!$tclRental) disabled title="No active rental - Cafe only available during rentals or as separate order" @endif>
                            <i class="bi bi-cup-hot"></i> Cafe
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Extend Rental Modal -->
    <div class="modal fade" id="extendModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Extend Rental Time</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="extendForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Additional Minutes</label>
                            <select class="form-control" name="additional_minutes" required>
                                <option value="15">15 minutes - $2.00</option>
                                <option value="30">30 minutes - $4.00</option>
                                <option value="60">1 hour - $7.00</option>
                                <option value="120">2 hours - $13.00</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Additional Price</label>
                            <input type="number" class="form-control" name="additional_price" step="0.01" required readonly>
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
    $(document).ready(function() {
        // Initialize countdown timers
        initializeCountdowns();
        
        // Update countdowns every second
        setInterval(updateCountdowns, 1000);
        
        // Auto-update pricing in extend modal
        $('select[name="additional_minutes"]').change(function() {
            const minutes = parseInt($(this).val());
            let price = 0;
            
            switch(minutes) {
                case 15: price = 2.00; break;
                case 30: price = 4.00; break;
                case 60: price = 7.00; break;
                case 120: price = 13.00; break;
            }
            
            $('input[name="additional_price"]').val(price.toFixed(2));
        });
    });

    function initializeCountdowns() {
        $('.countdown-container').each(function() {
            const container = $(this);
            const endTime = new Date(container.data('end-time'));
            container.data('end-timestamp', endTime.getTime());
        });
    }

    function updateCountdowns() {
        $('.countdown-container').each(function() {
            const container = $(this);
            let endTimestamp = container.data('end-timestamp');
            
            if (!endTimestamp) {
                const endTime = new Date(container.data('end-time'));
                endTimestamp = endTime.getTime();
                container.data('end-timestamp', endTimestamp);
            }
            
            const now = new Date().getTime();
            const remaining = endTimestamp - now;
            
            if (remaining > 0) {
                const hours = Math.floor(remaining / (1000 * 60 * 60));
                const minutes = Math.floor((remaining % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((remaining % (1000 * 60)) / 1000);
                
                const timeDisplay = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                container.find('.countdown-time').text(timeDisplay);
                
                // Color coding based on remaining time
                if (remaining <= 60000) { // 1 minute
                    container.find('.countdown-time').removeClass('text-success text-warning').addClass('text-danger');
                    container.find('.countdown-progress').removeClass('bg-success bg-warning').addClass('bg-danger');
                } else if (remaining <= 300000) { // 5 minutes
                    container.find('.countdown-time').removeClass('text-success text-danger').addClass('text-warning');
                    container.find('.countdown-progress').removeClass('bg-success bg-danger').addClass('bg-warning');
                } else {
                    container.find('.countdown-time').removeClass('text-warning text-danger').addClass('text-success');
                    container.find('.countdown-progress').removeClass('bg-warning bg-danger').addClass('bg-success');
                }
            } else {
                container.find('.countdown-time').text('EXPIRED').addClass('text-danger');
                container.find('.countdown-progress').css('width', '100%').addClass('bg-danger');
            }
        });
    }

    function setCountdownTo10Seconds(rentalId) {
        console.log('Setting countdown to 10 seconds for rental:', rentalId);
        
        // Find the countdown container for this rental
        const countdownContainer = document.querySelector(`.countdown-container[data-rental-id="${rentalId}"]`);
        console.log('Found container:', countdownContainer);
        
        if (countdownContainer) {
            // Set new end time to 10 seconds from now
            const newEndTime = new Date(Date.now() + 10000);
            console.log('New end time:', newEndTime);
            
            // Update all data storage methods
            countdownContainer.setAttribute('data-end-time', newEndTime.toISOString());
            $(countdownContainer).data('end-time', newEndTime.toISOString());
            $(countdownContainer).data('end-timestamp', newEndTime.getTime());
            
            // Add urgent styling
            countdownContainer.classList.add('countdown-urgent');
            
            // Force immediate update
            updateSingleCountdown(countdownContainer);
            
            // Update backend and trigger timeout
            updateRentalEndTime(rentalId, newEndTime);
            
            // Show confirmation
            alert('Countdown set to 10 seconds! Timeout video will play automatically.');
            
        } else {
            console.error('Countdown container not found for rental ID:', rentalId);
            alert('Error: Could not find countdown timer to update.');
        }
    }

    function updateSingleCountdown(container) {
        const endTimestamp = $(container).data('end-timestamp');
        const now = new Date().getTime();
        const remaining = endTimestamp - now;
        
        if (remaining > 0) {
            const hours = Math.floor(remaining / (1000 * 60 * 60));
            const minutes = Math.floor((remaining % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((remaining % (1000 * 60)) / 1000);
            
            const timeDisplay = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            $(container).find('.countdown-time')
                .text(timeDisplay)
                .removeClass('text-success text-warning')
                .addClass('text-danger');
                
            $(container).find('.countdown-progress')
                .css('width', '90%')
                .removeClass('bg-success bg-warning')
                .addClass('bg-danger');
        } else {
            $(container).find('.countdown-time').text('EXPIRED').addClass('text-danger');
            $(container).find('.countdown-progress').css('width', '100%').addClass('bg-danger');
        }
    }

    function updateRentalEndTime(rentalId, newEndTime) {
        fetch('/rentals/update-end-time', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                rental_id: rentalId,
                new_end_time: newEndTime.toISOString()
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('End time updated successfully');
            }
        })
        .catch(error => {
            console.error('Failed to update end time:', error);
        });
    }

    function forceEndRental(rentalId) {
        if (confirm('Are you sure you want to end this rental immediately?')) {
            // Show loading state
            const button = event.target;
            const originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Ending...';
            
            // Call your existing force-timeout endpoint
            fetch(`/rentals/${rentalId}/force-timeout`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Rental ended successfully! Timeout video is playing.');
                    location.reload();
                } else {
                    alert('Rental ended but video playback failed: ' + data.message);
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to end rental. Please try again.');
                button.disabled = false;
                button.innerHTML = originalText;
            });
        }
    }


    function testConnection(tvIp) {
        const statusElement = document.getElementById(`status-${tvIp}`);
        statusElement.innerHTML = '<span class="badge bg-secondary">Testing...</span>';
        
        fetch('/rentals/test-connection', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ tv_ip: tvIp })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statusElement.innerHTML = '<span class="badge bg-success">Connected</span>';
            } else {
                statusElement.innerHTML = '<span class="badge bg-danger">Offline</span>';
            }
        })
        .catch(error => {
            statusElement.innerHTML = '<span class="badge bg-danger">Error</span>';
        });
    }

    function refreshAllStations() {
        testConnection('192.168.1.20');
        testConnection('192.168.1.21');
    }

    function showExtendModal(rentalId) {
        $('#extendModal').data('rental-id', rentalId);
        new bootstrap.Modal(document.getElementById('extendModal')).show();
    }

    function showCafeModal(tvIp, rentalId = null) {
        // Create dynamic modal for cafe ordering
        const modalHtml = `
            <div class="modal fade" id="cafeOrderModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="bi bi-cup-hot"></i> Cafe Order
                                ${rentalId ? ' - Integrated with Rental' : ' - Separate Order'}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="text-center">
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden">Loading menu...</span>
                                </div>
                                <p class="mt-2">Loading cafe menu...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('cafeOrderModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add new modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('cafeOrderModal'));
        modal.show();
        
        // Load cafe menu with proper script execution
        const orderType = rentalId ? 'integrated' : 'separate';
        const url = `/cafe/menu?tv_ip=${tvIp}&rental_id=${rentalId || ''}&order_type=${orderType}`;
        
        fetch(url)
            .then(response => response.text())
            .then(html => {
                const modalBody = document.querySelector('#cafeOrderModal .modal-body');
                modalBody.innerHTML = html;
                
                // ✅ Execute any scripts in the loaded HTML
                const scripts = modalBody.querySelectorAll('script');
                scripts.forEach(script => {
                    const newScript = document.createElement('script');
                    if (script.src) {
                        newScript.src = script.src;
                    } else {
                        newScript.textContent = script.textContent;
                    }
                    document.head.appendChild(newScript);
                    document.head.removeChild(newScript);
                });
            })
            .catch(error => {
                document.querySelector('#cafeOrderModal .modal-body').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        Failed to load cafe menu. Please try again.
                    </div>
                `;
            });
    }



    function completeRental(rentalId) {
        if (confirm('Mark this rental as completed?')) {
            fetch(`/rentals/${rentalId}/complete`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Failed to complete rental: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while completing the rental');
            });
        }
    }

    // Handle extend form submission
    $('#extendForm').on('submit', function(e) {
        e.preventDefault();
        
        const rentalId = $('#extendModal').data('rental-id');
        const formData = new FormData(this);
        
        fetch(`/rentals/${rentalId}/extend`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                bootstrap.Modal.getInstance(document.getElementById('extendModal')).hide();
                location.reload();
            } else {
                alert('Failed to extend rental: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while extending the rental');
        });
    });

    function controlTv(tvIp, action) {
        const button = event.target;
        const originalText = button.innerHTML;
        
        // Show loading state
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        
        fetch('/tv-control', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                tv_ip: tvIp,
                action: action
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success feedback
                button.innerHTML = '<i class="bi bi-check"></i>';
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }, 1000);
            } else {
                alert('Failed to control TV: ' + (data.message || 'Unknown error'));
                button.innerHTML = originalText;
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to control TV. Please try again.');
            button.innerHTML = originalText;
            button.disabled = false;
        });
    }
    </script>

    <style>
    .countdown-container {
        background: #f8f9fa;
        padding: 10px;
        border-radius: 5px;
        border: 1px solid #dee2e6;
    }

    .countdown-time {
        font-family: 'Courier New', monospace;
        font-size: 1.2em;
        font-weight: bold;
    }

    .countdown-urgent {
        animation: pulse-red 1s infinite;
        border: 2px solid #dc3545 !important;
        background: #fff5f5 !important;
    }

    @keyframes pulse-red {
        0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }

    .progress {
        height: 8px;
    }

    .station-card {
        transition: transform 0.2s;
    }

    .station-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .status-info {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #28a745;
    }
    </style>
    @endsection
