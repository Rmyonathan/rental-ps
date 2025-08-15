@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4><i class="bi bi-plus-circle"></i> Start New PlayStation Rental</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('rentals.store') }}" method="POST" id="rentalForm">
                    @csrf
                    
                    @if(session('warning'))
                        <div class="alert alert-danger">{{ session('warning') }}</div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label">Customer Name *</label>
                        <input type="text" name="customer_name" class="form-control @error('customer_name') is-invalid @enderror" 
                               value="{{ old('customer_name') }}" required autofocus>
                        @error('customer_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Select TV Station *</label>
                        <select name="tv_ip" id="tv_ip_select" class="form-select @error('tv_ip') is-invalid @enderror" required>
                            <option value="">Select an available station...</option>
                            @foreach($availableTvs as $station)
                                <option 
                                    value="{{ $station->ip }}" 
                                    data-station-name="{{ $station->station_name }}" 
                                    data-price-per-hour="{{ $station->price_per_hour }}"
                                    {{ (old('tv_ip', $tv_ip ?? '') == $station->ip) ? 'selected' : '' }}>
                                    {{ $station->station_name }} ({{ $station->ip }})
                                </option>
                            @endforeach
                        </select>
                        @if($availableTvs->isEmpty())
                            <div class="form-text text-danger">No TV stations are available for rental.</div>
                        @endif
                        @error('tv_ip')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        {{-- // NEW: Add this entire payment method dropdown block --}}
                        <label class="form-label">Payment Method *</label>
                        <select name="payment_method" class="form-select @error('payment_method') is-invalid @enderror" required>
                            <option value="CASH" selected>Cash</option>
                            <option value="QRIS">QRIS</option>
                            <option value="DEBIT">Debit</option>
                            <option value="TRANSFER">Transfer</option>
                        </select>
                        @error('payment_method')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    {{-- // EDIT: Removed the problematic default value from this input. --}}
                    <input type="hidden" name="ps_station" id="ps_station_input" value="{{ old('ps_station') }}">
                    
                    <input type="hidden" id="base_price_per_hour" value="">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Duration (Hours) *</label>
                                <select name="duration_minutes" id="duration_select" class="form-select @error('duration_minutes') is-invalid @enderror" required>
                                    <option value="60" selected>1 Hour</option>
                                    <option value="120">2 Hours</option>
                                    <option value="180">3 Hours</option>
                                    <option value="240">4 Hours</option>
                                    <option value="1">1 Minute (Testing)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                             <div class="mb-3">
                                <label class="form-label">Total Price (Rp) *</label>
                                <input type="number" name="price" id="total_price_input" class="form-control @error('price') is-invalid @enderror" 
                                       value="{{ old('price') }}" required readonly>
                                @error('price')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('rentals.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
                        </a>
                        <button type="submit" class="btn btn-success btn-lg" id="startRentalBtn" {{ $availableTvs->isEmpty() ? 'disabled' : '' }}>
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
{{-- This script is already correct and does not need changes --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tvSelect = document.getElementById('tv_ip_select');
    const stationInput = document.getElementById('ps_station_input');
    const startBtn = document.getElementById('startRentalBtn');
    
    const durationSelect = document.getElementById('duration_select');
    const basePriceInput = document.getElementById('base_price_per_hour');
    const totalPriceInput = document.getElementById('total_price_input');

    function updateStationAndPrice() {
        const selectedOption = tvSelect.options[tvSelect.selectedIndex];
        if (selectedOption && selectedOption.value) {
            stationInput.value = selectedOption.dataset.stationName || '';
            basePriceInput.value = selectedOption.dataset.pricePerHour || '0';
            startBtn.disabled = false;
        } else {
            stationInput.value = '';
            basePriceInput.value = '0';
            startBtn.disabled = true;
        }
        calculateTotalPrice();
    }

    function calculateTotalPrice() {
        const durationInMinutes = parseInt(durationSelect.value, 10);
        const pricePerHour = parseInt(basePriceInput.value, 10);

        if (isNaN(durationInMinutes) || isNaN(pricePerHour)) {
            totalPriceInput.value = '';
            return;
        }
        
        if (durationInMinutes === 1) {
            totalPriceInput.value = '1000';
            return;
        }

        const hours = durationInMinutes / 60;
        const totalPrice = hours * pricePerHour;
        
        totalPriceInput.value = totalPrice;
    }

    tvSelect.addEventListener('change', updateStationAndPrice);
    durationSelect.addEventListener('change', calculateTotalPrice);

    document.getElementById('rentalForm').addEventListener('submit', function(e) {
        const submitBtn = document.getElementById('startRentalBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Starting...';
    });

    updateStationAndPrice();
});
</script>
@endsection