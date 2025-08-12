@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4><i class="bi bi-plus-circle"></i> Add New Menu Item</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('cafe.items.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Item Name *</label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                       value="{{ old('name') }}" required placeholder="e.g., Cappuccino, Chicken Sandwich">
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
                                    <option value="food" {{ old('category') == 'food' ? 'selected' : '' }}>Food</option>
                                    <option value="drink" {{ old('category') == 'drink' ? 'selected' : '' }}>Drink</option>
                                    <option value="snack" {{ old('category') == 'snack' ? 'selected' : '' }}>Snack</option>
                                    <option value="dessert" {{ old('category') == 'dessert' ? 'selected' : '' }}>Dessert</option>
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
                                  rows="3" placeholder="Brief description of the item...">{{ old('description') }}</textarea>
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
                                           value="{{ old('price') }}" step="1000" min="0" required placeholder="0">
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
                                           value="{{ old('preparation_time', 5) }}" min="1" max="60" required>
                                    <span class="input-group-text">minutes</span>
                                </div>
                                @error('preparation_time')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Estimated time to prepare this item</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Item Image</label>
                        <input type="file" name="image" class="form-control @error('image') is-invalid @enderror" 
                               accept="image/*" onchange="previewImage(this)">
                        @error('image')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Upload an image (JPEG, PNG, JPG, GIF - Max: 2MB)</div>
                        
                        <!-- Image Preview -->
                        <div id="imagePreview" class="mt-3" style="display: none;">
                            <img id="preview" src="" alt="Preview" class="img-thumbnail" style="max-width: 200px; max-height: 200px;">
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_available" value="1" 
                                   id="isAvailable" {{ old('is_available', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="isAvailable">
                                Available for ordering
                            </label>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Next Step:</strong> After creating this menu item, you'll need to add stock quantities in the Stock Management section.
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('cafe.items.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Menu Items
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Create Menu Item
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
