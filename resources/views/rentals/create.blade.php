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
                            @foreach($availableTvs as $index => $ip)
                                <option value="{{ $ip }}" data-station-name="PS{{ $index + 1 }}" {{ (old('tv_ip', $tv_ip ?? '') == $ip) ? 'selected' : '' }}>
                                    PS{{ $index + 1 }} ({{ $ip }})
                                </option>
                            @endforeach
                        </select>
                        @if(empty($availableTvs))
                            <div class="form-text text-danger">No TV stations are available for rental.</div>
                        @endif
                        @error('tv_ip')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <input type="hidden" name="ps_station" id="ps_station_input" value="{{ old('ps_station', $station ?? '') }}">


                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Duration *</label>
                                <select name="duration_minutes" id="duration_select" class="form-select @error('duration_minutes') is-invalid @enderror" required>
                                    <option value="1">1 minute (testing)</option>
                                    <option value="15">15 minutes</option>
                                    <option value="30">30 minutes</option>
                                    <option value="60" selected>1 hour</option>
                                    <option value="120">2 hours</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Price ($) *</label>
                                <input type="number" name="price" id="price_input" class="form-control @error('price') is-invalid @enderror" 
                                       value="{{ old('price') }}" step="0.01" min="0" required readonly>
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
                        <button type="submit" class="btn btn-success btn-lg" id="startRentalBtn" {{ empty($availableTvs) ? 'disabled' : '' }}>
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
document.addEventListener('DOMContentLoaded', function() {
    const tvSelect = document.getElementById('tv_ip_select');
    const stationInput = document.getElementById('ps_station_input');
    const durationSelect = document.getElementById('duration_select');
    const priceInput = document.getElementById('price_input');
    const startBtn = document.getElementById('startRentalBtn');

    const pricing = {
        '1': 0.10,
        '15': 2.00,
        '30': 4.00,
        '60': 7.00,
        '120': 13.00,
    };

    function updatePrice() {
        const duration = durationSelect.value;
        priceInput.value = pricing[duration] ? pricing[duration].toFixed(2) : '0.00';
    }

    function updateStationName() {
        const selectedOption = tvSelect.options[tvSelect.selectedIndex];
        if (selectedOption && selectedOption.value) {
            stationInput.value = selectedOption.dataset.stationName || '';
            startBtn.disabled = false;
        } else {
            stationInput.value = '';
            startBtn.disabled = true;
        }
    }

    tvSelect.addEventListener('change', updateStationName);
    durationSelect.addEventListener('change', updatePrice);

    // Form submission handler
    document.getElementById('rentalForm').addEventListener('submit', function(e) {
        const submitBtn = document.getElementById('startRentalBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Starting...';
    });

    // Initial setup on page load
    updateStationName();
    updatePrice();
});
</script>
@endsection
