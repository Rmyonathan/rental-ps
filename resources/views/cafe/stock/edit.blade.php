@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4><i class="bi bi-pencil"></i> Edit Stock - {{ $cafeStock->cafeItem->name }}</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('cafe.stock.update', $cafeStock) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    
                    <div class="mb-3">
                        <label class="form-label">Cafe Item</label>
                        <input type="text" class="form-control" 
                               value="{{ $cafeStock->cafeItem->name }} - ${{ number_format($cafeStock->cafeItem->price, 2) }}" 
                               readonly>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Current Quantity *</label>
                                <input type="number" name="quantity" class="form-control @error('quantity') is-invalid @enderror" 
                                       value="{{ old('quantity', $cafeStock->quantity) }}" min="0" required>
                                @error('quantity')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Minimum Stock Level *</label>
                                <input type="number" name="minimum_stock" class="form-control @error('minimum_stock') is-invalid @enderror" 
                                       value="{{ old('minimum_stock', $cafeStock->minimum_stock) }}" min="1" required>
                                @error('minimum_stock')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Cost Price (Optional)</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" name="cost_price" class="form-control @error('cost_price') is-invalid @enderror" 
                                           value="{{ old('cost_price', $cafeStock->cost_price) }}" step="1000" min="0">
                                </div>
                                @error('cost_price')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Expiry Date (Optional)</label>
                                <input type="date" name="expiry_date" class="form-control @error('expiry_date') is-invalid @enderror" 
                                       value="{{ old('expiry_date', $cafeStock->expiry_date ? $cafeStock->expiry_date->format('Y-m-d') : '') }}">
                                @error('expiry_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('cafe.stock.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Stock
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Update Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
