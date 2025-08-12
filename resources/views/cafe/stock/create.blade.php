@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4><i class="bi bi-plus-circle"></i> Add New Stock</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('cafe.stock.store') }}" method="POST">
                    @csrf
                    
                    <div class="mb-3">
                        <label class="form-label">Cafe Item *</label>
                        <select name="cafe_item_id" class="form-select @error('cafe_item_id') is-invalid @enderror" required>
                            <option value="">Select an item...</option>
                            @foreach($cafeItems as $item)
                                <option value="{{ $item->id }}" {{ old('cafe_item_id') == $item->id ? 'selected' : '' }}>
                                    {{ $item->name }} - Rp {{ number_format($item->price, 2) }} ({{ ucfirst($item->category) }})
                                </option>
                            @endforeach
                        </select>
                        @error('cafe_item_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @if($cafeItems->isEmpty())
                            <div class="form-text text-warning">
                                <i class="bi bi-exclamation-triangle"></i> 
                                No items available. <a href="{{ route('cafe.items.create') }}">Create a menu item first</a>.
                            </div>
                        @endif
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Initial Quantity *</label>
                                <input type="number" name="quantity" class="form-control @error('quantity') is-invalid @enderror" 
                                       value="{{ old('quantity') }}" min="0" required>
                                @error('quantity')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Minimum Stock Level *</label>
                                <input type="number" name="minimum_stock" class="form-control @error('minimum_stock') is-invalid @enderror" 
                                       value="{{ old('minimum_stock', 5) }}" min="1" required>
                                @error('minimum_stock')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Alert when stock falls below this level</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Cost Price (Optional)</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp </span>
                                    <input type="number" name="cost_price" class="form-control @error('cost_price') is-invalid @enderror" 
                                           value="{{ old('cost_price') }}" step="1000" min="0">
                                </div>
                                @error('cost_price')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">For profit calculation and reporting</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Expiry Date (Optional)</label>
                                <input type="date" name="expiry_date" class="form-control @error('expiry_date') is-invalid @enderror" 
                                       value="{{ old('expiry_date') }}" min="{{ date('Y-m-d', strtotime('+1 day')) }}">
                                @error('expiry_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">For perishable items</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('cafe.stock.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Stock
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Add Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
