@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-cup-hot"></i> Cafe Orders</h2>
    <div>
        <button type="button" class="btn btn-success me-2" onclick="showCreateOrderModal()">
            <i class="bi bi-plus-circle"></i> New Order
        </button>
        <a href="{{ route('cafe.stock.index') }}" class="btn btn-outline-primary">
            <i class="bi bi-box-seam"></i> Manage Stock
        </a>
        <a href="{{ route('cafe.items.index') }}" class="btn btn-primary">
            <i class="bi bi-menu-button-wide"></i> Menu Management
        </a>
    </div>
</div>

<div class="row">
    @forelse($orders as $order)
    <div class="col-md-6 col-lg-4 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>{{ $order->order_number }}</strong>
                <div>
                    @if($order->order_type === 'separate')
                        <span class="badge bg-info me-1">Standalone</span>
                    @elseif($order->rental)
                        {{-- // EDIT: Use the custom station name from the controller --}}
                        <span class="badge bg-primary me-1">{{ $order->rental->ps_station }}</span>
                    @endif
                    <span class="badge bg-{{ 
                        $order->status === 'pending' ? 'warning' : 
                        ($order->status === 'preparing' ? 'info' : 
                        ($order->status === 'ready' ? 'success' : 
                        ($order->status === 'delivered' ? 'secondary' : 'danger'))) 
                    }}">
                        {{ ucfirst($order->status) }}
                    </span>
                </div>
            </div>
            <div class="card-body">
                <h6 class="card-title">{{ $order->customer_name }}</h6>
                <p class="card-text">
                    <small class="text-muted">
                        <i class="bi bi-clock"></i> {{ $order->ordered_at->format('M j, H:i') }}
                        @if($order->rental)
                            <br><i class="bi bi-controller"></i> {{ $order->rental->ps_station }}
                        @else
                            <br><i class="bi bi-cup-hot"></i> Standalone Order
                        @endif
                    </small>
                </p>
                
                <div class="mb-2">
                    @foreach($order->items->take(3) as $item)
                        <div class="small">
                            {{ $item->quantity }}× {{ $item->cafeItem->name }}
                        </div>
                    @endforeach
                    @if($order->items->count() > 3)
                        <small class="text-muted">+{{ $order->items->count() - 3 }} more items</small>
                    @endif
                </div>
                
                <div class="d-flex justify-content-between align-items-center">
                    {{-- // EDIT: Changed to Rupiah format --}}
                    <strong>Rp {{ number_format($order->total, 0, ',', '.') }}</strong>
                    <div class="btn-group btn-group-sm">
                        @if($order->status === 'pending')
                            <button class="btn btn-outline-primary" onclick="updateOrderStatus({{ $order->id }}, 'preparing')">
                                Start
                            </button>
                        @elseif($order->status === 'preparing')
                            <button class="btn btn-outline-success" onclick="updateOrderStatus({{ $order->id }}, 'ready')">
                                Ready
                            </button>
                        @elseif($order->status === 'ready')
                            <button class="btn btn-outline-secondary" onclick="updateOrderStatus({{ $order->id }}, 'delivered')">
                                Delivered
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="text-center py-5">
            <i class="bi bi-cup-hot" style="font-size: 3rem; color: #dee2e6;"></i>
            <h4 class="text-muted mt-3">No cafe orders yet</h4>
            <p class="text-muted">Orders will appear here when customers place them</p>
        </div>
    </div>
    @endforelse
</div>

{{ $orders->links() }}

<!-- Create Order Modal -->
<div class="modal fade" id="createOrderModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-cup-hot"></i> Create New Cafe Order
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="createOrderContent">
                <!-- Content loaded via AJAX -->
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
@endsection

@section('scripts')
<script>
function updateOrderStatus(orderId, status) {
    fetch(`/cafe/order/${orderId}/status`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ status: status })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to update order status');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update order status');
    });
}

function showCreateOrderModal() {
    const modal = new bootstrap.Modal(document.getElementById('createOrderModal'));
    const content = document.getElementById('createOrderContent');
    
    // Reset content
    content.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading menu...</span>
            </div>
            <p class="mt-2">Loading cafe menu...</p>
        </div>
    `;
    
    modal.show();
    
    // Load cafe menu for standalone order
    fetch('/cafe/menu?order_type=separate&admin_create=true')
        .then(response => response.text())
        .then(html => {
            content.innerHTML = html;
            
            // Execute any scripts in the loaded HTML
            const scripts = content.querySelectorAll('script');
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
            content.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                    Failed to load cafe menu. Please try again.
                </div>
            `;
        });
}
</script>
@endsection
