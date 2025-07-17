@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-box-seam"></i> Stock Management</h2>
    <div>
        <a href="{{ route('cafe.stock.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add New Stock
        </a>
        <a href="{{ route('cafe.items.create') }}" class="btn btn-outline-primary">
            <i class="bi bi-plus-square"></i> Add Menu Item
        </a>
    </div>
</div>

<!-- Stock Overview Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Total Items</h6>
                        <h3>{{ $stocks->count() }}</h3>
                    </div>
                    <i class="bi bi-box-seam" style="font-size: 2rem; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Low Stock</h6>
                        <h3>{{ $stocks->where('is_low_stock', true)->count() }}</h3>
                    </div>
                    <i class="bi bi-exclamation-triangle" style="font-size: 2rem; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Out of Stock</h6>
                        <h3>{{ $stocks->where('quantity', 0)->count() }}</h3>
                    </div>
                    <i class="bi bi-x-circle" style="font-size: 2rem; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Expiring Soon</h6>
                        <h3>{{ $stocks->filter(function($stock) { return $stock->expiry_date && $stock->expiry_date->diffInDays(now()) <= 7; })->count() }}</h3>
                    </div>
                    <i class="bi bi-calendar-x" style="font-size: 2rem; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stock Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Current Stock Levels</h5>
    </div>
    <div class="card-body">
        @if($stocks->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Current Stock</th>
                        <th>Min. Stock</th>
                        <th>Status</th>
                        <th>Cost Price</th>
                        <th>Expiry Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stocks->sortBy('quantity') as $stock)
                    <tr class="{{ $stock->quantity == 0 ? 'table-danger' : ($stock->is_low_stock ? 'table-warning' : '') }}">
                        <td>
                            <div class="d-flex align-items-center">
                                @if($stock->cafeItem->image)
                                    <img src="{{ asset('storage/' . $stock->cafeItem->image) }}" 
                                         class="rounded me-2" 
                                         style="width: 40px; height: 40px; object-fit: cover;">
                                @else
                                    <div class="bg-light rounded me-2 d-flex align-items-center justify-content-center" 
                                         style="width: 40px; height: 40px;">
                                        <i class="bi bi-image text-muted"></i>
                                    </div>
                                @endif
                                <div>
                                    <div class="fw-bold">{{ $stock->cafeItem->name }}</div>
                                    <small class="text-muted">${{ number_format($stock->cafeItem->price, 2) }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-secondary">{{ ucfirst($stock->cafeItem->category) }}</span>
                        </td>
                        <td>
                            <span class="fw-bold {{ $stock->quantity == 0 ? 'text-danger' : ($stock->is_low_stock ? 'text-warning' : 'text-success') }}">
                                {{ $stock->quantity }}
                            </span>
                        </td>
                        <td>{{ $stock->minimum_stock }}</td>
                        <td>
                            @if($stock->quantity == 0)
                                <span class="badge bg-danger">Out of Stock</span>
                            @elseif($stock->is_low_stock)
                                <span class="badge bg-warning">Low Stock</span>
                            @else
                                <span class="badge bg-success">In Stock</span>
                            @endif
                            
                            @if($stock->expiry_date && $stock->expiry_date->diffInDays(now()) <= 7)
                                <br><span class="badge bg-info mt-1">Expiring Soon</span>
                            @endif
                        </td>
                        <td>
                            @if($stock->cost_price)
                                ${{ number_format($stock->cost_price, 2) }}
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if($stock->expiry_date)
                                {{ $stock->expiry_date->format('M j, Y') }}
                                @if($stock->expiry_date->isPast())
                                    <br><span class="badge bg-danger">Expired</span>
                                @elseif($stock->expiry_date->diffInDays(now()) <= 7)
                                    <br><span class="text-warning small">{{ $stock->expiry_date->diffInDays(now()) }} days left</span>
                                @endif
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-success" 
                                        onclick="showAddStockModal({{ $stock->id }}, '{{ $stock->cafeItem->name }}', {{ $stock->quantity }})">
                                    <i class="bi bi-plus"></i>
                                </button>
                                <a href="{{ route('cafe.stock.edit', $stock) }}" class="btn btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-5">
            <i class="bi bi-box-seam" style="font-size: 3rem; color: #dee2e6;"></i>
            <h4 class="text-muted mt-3">No stock records found</h4>
            <p class="text-muted">Start by adding some cafe items and their stock levels</p>
            <a href="{{ route('cafe.stock.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add First Stock Item
            </a>
        </div>
        @endif
    </div>
</div>

<!-- Add Stock Modal -->
<div class="modal fade" id="addStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addStockForm">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Item</label>
                        <input type="text" class="form-control" id="itemName" readonly>
                        <input type="hidden" id="stockId">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Current Stock</label>
                        <input type="number" class="form-control" id="currentStock" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Additional Quantity</label>
                        <input type="number" name="additional_quantity" class="form-control" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cost Price per Unit</label>
                        <input type="number" name="cost_price" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Total</label>
                        <input type="number" class="form-control" id="newTotal" readonly>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function showAddStockModal(stockId, itemName, currentStock) {
    document.getElementById('stockId').value = stockId;
    document.getElementById('itemName').value = itemName;
    document.getElementById('currentStock').value = currentStock;
    document.querySelector('input[name="additional_quantity"]').value = ''; // ✅ Fix this line
    document.getElementById('newTotal').value = currentStock;
    
    new bootstrap.Modal(document.getElementById('addStockModal')).show();
}

// ✅ Fix the event listener
document.querySelector('input[name="additional_quantity"]').addEventListener('input', function() {
    const current = parseInt(document.getElementById('currentStock').value) || 0;
    const additional = parseInt(this.value) || 0;
    document.getElementById('newTotal').value = current + additional;
});

// ✅ Fix the form submission
document.getElementById('addStockForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const stockId = document.getElementById('stockId').value;
    const additionalQuantity = document.querySelector('input[name="additional_quantity"]').value;
    const costPrice = document.querySelector('input[name="cost_price"]').value;
    
    fetch(`/cafe/stock/${stockId}/add-stock`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            additional_quantity: parseInt(additionalQuantity),
            cost_price: parseFloat(costPrice)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('addStockModal')).hide();
            location.reload();
        } else {
            alert('Failed to add stock: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to add stock');
    });
});

</script>
@endsection
