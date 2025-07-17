@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-cup-hot"></i> Menu Items</h2>
    <div>
        <a href="{{ route('cafe.items.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add Menu Item
        </a>
        <a href="{{ route('cafe.stock.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-box-seam"></i> Manage Stock
        </a>
    </div>
</div>

<!-- Category Overview -->
<div class="row mb-4">
    @php
        $categories = $cafeItems->groupBy('category');
    @endphp
    @foreach(['food', 'drink', 'snack', 'dessert'] as $category)
        <div class="col-md-3">
            <div class="card bg-{{ $category === 'food' ? 'primary' : ($category === 'drink' ? 'info' : ($category === 'snack' ? 'warning' : 'success')) }} text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">{{ ucfirst($category) }}</h6>
                            <h3>{{ $categories->get($category, collect())->count() }}</h3>
                        </div>
                        <i class="bi bi-{{ $category === 'food' ? 'egg-fried' : ($category === 'drink' ? 'cup-straw' : ($category === 'snack' ? 'cookie' : 'cake2')) }}" style="font-size: 2rem; opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<!-- Menu Items Grid -->
@if($cafeItems->count() > 0)
    @foreach($categories as $category => $items)
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-{{ $category === 'food' ? 'egg-fried' : ($category === 'drink' ? 'cup-straw' : ($category === 'snack' ? 'cookie' : 'cake2')) }}"></i>
                    {{ ucfirst($category) }} ({{ $items->count() }})
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    @foreach($items as $item)
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card h-100">
                                @if($item->image)
                                    <img src="{{ asset('storage/' . $item->image) }}" 
                                         class="card-img-top" 
                                         style="height: 150px; object-fit: cover;">
                                @else
                                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                         style="height: 150px;">
                                        <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                                    </div>
                                @endif
                                
                                <div class="card-body d-flex flex-column">
                                    <h6 class="card-title">{{ $item->name }}</h6>
                                    @if($item->description)
                                        <p class="card-text small text-muted flex-grow-1">{{ $item->description }}</p>
                                    @endif
                                    
                                    <div class="mt-auto">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="h6 text-primary mb-0">${{ number_format($item->price, 2) }}</span>
                                            <span class="badge bg-{{ $item->is_available ? 'success' : 'danger' }}">
                                                {{ $item->is_available ? 'Available' : 'Unavailable' }}
                                            </span>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <small class="text-muted">
                                                <i class="bi bi-clock"></i> {{ $item->preparation_time }}min
                                            </small>
                                            @if($item->stock)
                                                <small class="text-muted">
                                                    Stock: <span class="fw-bold {{ $item->stock->quantity == 0 ? 'text-danger' : ($item->stock->is_low_stock ? 'text-warning' : 'text-success') }}">
                                                        {{ $item->stock->quantity }}
                                                    </span>
                                                </small>
                                            @else
                                                <small class="text-warning">No stock record</small>
                                            @endif
                                        </div>
                                        
                                        <div class="btn-group w-100">
                                            <a href="{{ route('cafe.items.edit', $item) }}" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            @if(!$item->stock)
                                                <a href="{{ route('cafe.stock.create') }}?item={{ $item->id }}" class="btn btn-outline-success btn-sm">
                                                    <i class="bi bi-plus"></i> Add Stock
                                                </a>
                                            @endif
                                            <button class="btn btn-outline-danger btn-sm" 
                                                    onclick="confirmDelete({{ $item->id }}, '{{ $item->name }}')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
@else
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-cup-hot" style="font-size: 4rem; color: #dee2e6;"></i>
            <h4 class="text-muted mt-3">No menu items yet</h4>
            <p class="text-muted">Start building your cafe menu by adding your first item</p>
            <a href="{{ route('cafe.items.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add First Menu Item
            </a>
        </div>
    </div>
@endif

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="itemName"></strong>?</p>
                <p class="text-warning"><i class="bi bi-exclamation-triangle"></i> This action cannot be undone and will also remove any associated stock records.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function confirmDelete(itemId, itemName) {
    document.getElementById('itemName').textContent = itemName;
    document.getElementById('deleteForm').action = `/cafe/items/${itemId}`;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
@endsection
