@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4><i class="bi bi-pencil"></i> Edit Menu Item - {{ $cafeItem->name }}</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('cafe.items.update', $cafeItem) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PATCH')
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Item Name *</label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                       value="{{ old('name', $cafeItem->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Category *</label>
                                <select name="category" class="form-select @error('category') is-invalid @enderror" required>
                                    <option value="">Select category...</option>
                                    <option value="food" {{ old('category', $cafeItem->category) == 'food' ? 'selected' : '' }}>Food</option>
                                    <option value="drink" {{ old('category', $cafeItem->category) == 'drink' ? 'selected' : '' }}>Drink</option>
                                    <option value="snack" {{ old('category', $cafeItem->category) == 'snack' ? 'selected' : '' }}>Snack</option>
                                    <option value="dessert" {{ old('category', $cafeItem->category) == 'dessert' ? 'selected' : '' }}>Dessert</option>
                                </select>
                                @error('category')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control @error('description') is-invalid @enderror" 
                                  rows="3">{{ old('description', $cafeItem->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Price *</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" name="price" class="form-control @error('price') is-invalid @enderror" 
                                           value="{{ old('price', $cafeItem->price) }}" step="1000" min="0" required>
                                </div>
                                @error('price')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Preparation Time *</label>
                                <div class="input-group">
                                    <input type="number" name="preparation_time" class="form-control @error('preparation_time') is-invalid @enderror" 
                                           value="{{ old('preparation_time', $cafeItem->preparation_time) }}" min="1" max="60" required>
                                    <span class="input-group-text">minutes</span>
                                </div>
                                @error('preparation_time')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Item Image</label>
                        
                        @if($cafeItem->image)
                            <div class="mb-2">
                                <img src="{{ asset('storage/' . $cafeItem->image) }}" 
                                     alt="{{ $cafeItem->name }}" 
                                     class="img-thumbnail" 
                                     style="max-width: 200px; max-height: 200px;">
                                <div class="form-text">Current image</div>
                            </div>
                        @endif
                        
                        <input type="file" name="image" class="form-control @error('image') is-invalid @enderror" 
                               accept="image/*" onchange="previewImage(this)">
                        @error('image')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Upload a new image to replace the current one (JPEG, PNG, JPG, GIF - Max: 2MB)</div>
                        
                        <!-- New Image Preview -->
                        <div id="imagePreview" class="mt-3" style="display: none;">
                            <img id="preview" src="" alt="Preview" class="img-thumbnail" style="max-width: 200px; max-height: 200px;">
                            <div class="form-text">New image preview</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_available" value="1" 
                                   id="isAvailable" {{ old('is_available', $cafeItem->is_available) ? 'checked' : '' }}>
                            <label class="form-check-label" for="isAvailable">
                                Available for ordering
                            </label>
                        </div>
                    </div>

                    @if($cafeItem->stock)
                        <div class="alert alert-info">
                            <i class="bi bi-box-seam"></i>
                            <strong>Current Stock:</strong> {{ $cafeItem->stock->quantity }} units
                            <a href="{{ route('cafe.stock.edit', $cafeItem->stock) }}" class="btn btn-sm btn-outline-primary ms-2">
                                Manage Stock
                            </a>
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>No Stock Record:</strong> This item doesn't have stock tracking yet.
                            <a href="{{ route('cafe.stock.create') }}?item={{ $cafeItem->id }}" class="btn btn-sm btn-outline-success ms-2">
                                Add Stock
                            </a>
                        </div>
                    @endif

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('cafe.items.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Menu Items
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Update Menu Item
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
function previewImage(input) {
    const preview = document.getElementById('preview');
    const previewContainer = document.getElementById('imagePreview');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            previewContainer.style.display = 'block';
        }
        
        reader.readAsDataURL(input.files[0]);
    } else {
        previewContainer.style.display = 'none';
    }
}
</script>
@endsection
