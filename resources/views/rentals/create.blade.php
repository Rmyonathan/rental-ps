@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4><i class="bi bi-plus-circle"></i> Start New PlayStation Rental</h4>
                <div class="alert alert-info">
                    <strong>Station:</strong> {{ request('station') }} | 
                    <strong>TV IP:</strong> {{ request('tv_ip') }}
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('rentals.store') }}" method="POST" id="rentalForm">
                    @csrf
                    
                    <!-- Hidden fields for station and TV IP -->
                    <input type="hidden" name="ps_station" value="{{ request('station') }}">
                    <input type="hidden" name="tv_ip" value="{{ request('tv_ip') }}">
                    
                    <div class="row">
                        <!-- Customer Information -->
                        <div class="col-md-12">
                            <h6 class="text-primary mb-3"><i class="bi bi-person"></i> Customer Information</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Customer Name *</label>
                                <input type="text" name="customer_name" class="form-control @error('customer_name') is-invalid @enderror" 
                                       value="{{ old('customer_name') }}" required autofocus>
                                @error('customer_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- Rental Duration & Pricing -->
                    <h6 class="text-primary mb-3"><i class="bi bi-clock"></i> Duration & Pricing</h6>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Duration *</label>
                                <select name="duration_minutes" class="form-select @error('duration_minutes') is-invalid @enderror" 
                                        required onchange="calculatePrice()">
                                    <option value="">Select duration...</option>
                                    <option value="1">1 minute (testing)</option>
                                    <option value="15">15 minutes</option>
                                    <option value="30">30 minutes</option>
                                    <option value="60">1 hour</option>
                                    <option value="120">2 hours</option>
                                    <option value="180">3 hours</option>
                                    <option value="240">4 hours</option>
                                    <option value="480">8 hours</option>
                                    <option value="custom">Custom duration...</option>
                                </select>
                                @error('duration_minutes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Custom duration input (hidden by default) -->
                            <div class="mb-3" id="customDurationDiv" style="display: none;">
                                <label class="form-label">Custom Duration (minutes)</label>
                                <input type="number" id="customDuration" class="form-control" min="15" max="480" 
                                       placeholder="Enter minutes (1-480)" onchange="calculatePrice()">
                                <div class="form-text">Minimum: 1 minutes, Maximum: 8 hours</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Price ($) *</label>
                                <input type="number" name="price" class="form-control @error('price') is-invalid @enderror" 
                                       value="{{ old('price') }}" step="0.01" min="0" required readonly>
                                @error('price')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Price calculated automatically based on duration</div>
                            </div>

                            <!-- Pricing Information -->
                            <div class="card bg-light">
                                <div class="card-body p-3">
                                    <h6 class="card-title mb-2">Pricing Structure</h6>
                                    <small class="text-muted">
                                        • 15 min: $2.00<br>
                                        • 30 min: $4.00<br>
                                        • 1 hour: $7.00<br>
                                        • 2+ hours: $6.50/hour
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rental Summary -->
                    <div class="card bg-primary text-white mb-3" id="rentalSummary" style="display: none;">
                        <div class="card-body">
                            <h6 class="card-title">Rental Summary</h6>
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Station:</strong><br>
                                    <span id="summaryStation">{{ request('station') }}</span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Duration:</strong><br>
                                    <span id="summaryDuration">-</span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Price:</strong><br>
                                    <span id="summaryPrice">$0.00</span>
                                </div>
                                <div class="col-md-3">
                                    <strong>End Time:</strong><br>
                                    <span id="summaryEndTime">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('rentals.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
                        </a>
                        <button type="submit" class="btn btn-success btn-lg" id="startRentalBtn" disabled>
                            <i class="bi bi-play-circle"></i> Start Rental
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Auto-focus on customer name field
    $('input[name="customer_name"]').focus();
    
    // Handle custom duration toggle
    $('select[name="duration_minutes"]').change(function() {
        if ($(this).val() === 'custom') {
            $('#customDurationDiv').show();
            $('#customDuration').attr('required', true);
        } else {
            $('#customDurationDiv').hide();
            $('#customDuration').attr('required', false);
            calculatePrice();
        }
    });
});

function calculatePrice() {
    const durationSelect = document.querySelector('select[name="duration_minutes"]');
    const customDurationInput = document.getElementById('customDuration');
    const priceInput = document.querySelector('input[name="price"]');
    const startBtn = document.getElementById('startRentalBtn');
    
    let minutes = 0;
    
    if (durationSelect.value === 'custom') {
        minutes = parseInt(customDurationInput.value) || 0;
    } else {
        minutes = parseInt(durationSelect.value) || 0;
    }
    
    if (minutes > 0) {
        let price = 0;
        
        // Pricing logic
        if (minutes <= 15) {
            price = 2.00;
        } else if (minutes <= 30) {
            price = 4.00;
        } else if (minutes <= 60) {
            price = 7.00;
        } else {
            // For 2+ hours: $6.50 per hour
            const hours = Math.ceil(minutes / 60);
            price = hours * 6.50;
        }
        
        priceInput.value = price.toFixed(2);
        updateSummary(minutes, price);
        startBtn.disabled = false;
    } else {
        priceInput.value = '';
        document.getElementById('rentalSummary').style.display = 'none';
        startBtn.disabled = true;
    }
}

function updateSummary(minutes, price) {
    const summaryDiv = document.getElementById('rentalSummary');
    const now = new Date();
    const endTime = new Date(now.getTime() + (minutes * 60000));
    
    // Format duration
    let durationText = '';
    if (minutes < 60) {
        durationText = `${minutes} minutes`;
    } else {
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;
        durationText = `${hours}h ${remainingMinutes}m`;
    }
    
    document.getElementById('summaryDuration').textContent = durationText;
    document.getElementById('summaryPrice').textContent = `$${price.toFixed(2)}`;
    document.getElementById('summaryEndTime').textContent = endTime.toLocaleTimeString();
    
    summaryDiv.style.display = 'block';
}

// Form validation before submit
document.getElementById('rentalForm').addEventListener('submit', function(e) {
    const customerName = document.querySelector('input[name="customer_name"]').value.trim();
    const duration = document.querySelector('select[name="duration_minutes"]').value;
    const price = document.querySelector('input[name="price"]').value;
    
    if (!customerName) {
        alert('Please enter customer name');
        e.preventDefault();
        return;
    }
    
    if (!duration || !price) {
        alert('Please select duration and verify pricing');
        e.preventDefault();
        return;
    }
    
    // Show loading state
    const submitBtn = document.getElementById('startRentalBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Starting Rental...';
});
</script>

<style>
.card-header .alert {
    margin-bottom: 0;
    padding: 8px 12px;
    font-size: 0.9em;
}

#rentalSummary {
    animation: slideIn 0.3s ease-in-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.form-control:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

.btn-success:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
@endsection
