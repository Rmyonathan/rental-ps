<div class="row">
    <!-- Menu Items -->
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="bi bi-cup-hot"></i> Cafe Menu</h4>
                @if($rental)
                    <small class="text-muted">
                        Ordering for: <strong>{{ $rental->customer_name }}</strong> 
                        ({{ $rental->ps_station }})
                    </small>
                @endif
            </div>
            <div class="d-flex gap-2">
                <span class="badge bg-info">{{ $orderType === 'integrated' ? 'Integrated Bill' : 'Separate Bill' }}</span>
                @if($tvIp)
                    <span class="badge bg-secondary">{{ $tvIp }}</span>
                @endif
            </div>
        </div>

        <!-- Category Tabs -->
        <ul class="nav nav-pills mb-4" id="categoryTabs">
            @foreach($cafeItems as $category => $items)
                <li class="nav-item">
                    <button class="nav-link category-tab {{ $loop->first ? 'active' : '' }}" 
                            data-bs-toggle="pill" 
                            data-bs-target="#{{ $category }}-tab">
                        <i class="bi bi-{{ $category === 'food' ? 'egg-fried' : ($category === 'drink' ? 'cup-straw' : 'cookie') }}"></i>
                        {{ ucfirst($category) }}
                        <span class="badge bg-light text-dark ms-1">{{ count($items) }}</span>
                    </button>
                </li>
            @endforeach
        </ul>

        <!-- Menu Items by Category -->
        <div class="tab-content" id="menuContent">
            @foreach($cafeItems as $category => $items)
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" 
                     id="{{ $category }}-tab">
                    <div class="row">
                        @foreach($items as $item)
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card cafe-item-card h-100" 
                                     data-item-id="{{ $item->id }}"
                                     data-item-name="{{ $item->name }}"
                                     data-item-price="{{ $item->price }}"
                                     data-stock="{{ $item->stock_quantity }}"
                                     onclick="toggleItem(this)">
                                    @if($item->image)
                                        <img src="{{ asset('storage/' . $item->image) }}" 
                                             class="card-img-top" 
                                             style="height: 120px; object-fit: cover;">
                                    @else
                                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                             style="height: 120px;">
                                            <i class="bi bi-image text-muted" style="font-size: 2rem;"></i>
                                        </div>
                                    @endif
                                    
                                    <div class="card-body p-3">
                                        <h6 class="card-title mb-1">{{ $item->name }}</h6>
                                        @if($item->description)
                                            <p class="card-text small text-muted mb-2">{{ $item->description }}</p>
                                        @endif
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-primary">${{ number_format($item->price, 2) }}</span>
                                            <div class="d-flex align-items-center gap-2">
                                                @if($item->is_in_stock)
                                                    <span class="badge bg-success">{{ $item->stock_quantity }} left</span>
                                                @else
                                                    <span class="badge bg-danger">Out of Stock</span>
                                                @endif
                                            </div>
                                        </div>
                                        
                                        @if($item->preparation_time > 0)
                                            <small class="text-muted">
                                                <i class="bi bi-clock"></i> ~{{ $item->preparation_time }}min
                                            </small>
                                        @endif
                                    </div>
                                    
                                    <!-- Quantity Controls (hidden by default) -->
                                    <div class="card-footer bg-white border-0 quantity-controls" style="display: none;">
                                        <button type="button" class="quantity-btn" onclick="event.stopPropagation(); decreaseQuantity({{ $item->id }})">
                                            <i class="bi bi-dash"></i>
                                        </button>
                                        <span class="quantity-display fw-bold">1</span>
                                        <button type="button" class="quantity-btn" onclick="event.stopPropagation(); increaseQuantity({{ $item->id }})">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                        <input type="text" class="form-control form-control-sm ms-2" 
                                               placeholder="Special instructions..." 
                                               onchange="updateInstructions({{ $item->id }}, this.value)"
                                               onclick="event.stopPropagation()">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Order Summary -->
    <div class="col-lg-4">
        <div class="cart-summary">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-cart3"></i> Order Summary</h5>
                </div>
                <div class="card-body">
                    <form id="cafeOrderForm">
                        @csrf
                        <input type="hidden" name="tv_ip" value="{{ $tvIp }}">
                        <input type="hidden" name="rental_id" value="{{ $rental ? $rental->id : '' }}">
                        <input type="hidden" name="order_type" value="{{ $orderType }}">
                        
                        <div class="mb-3">
                            <label class="form-label">Customer Name *</label>
                            <input type="text" name="customer_name" class="form-control" 
                                   value="{{ $rental ? $rental->customer_name : '' }}" 
                                   {{ $rental ? 'readonly' : 'required' }}>
                        </div>

                        <!-- Order Items List -->
                        <div id="orderItemsList">
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-cart-x" style="font-size: 2rem;"></i>
                                <p class="mb-0">No items selected</p>
                                <small>Click on menu items to add them</small>
                            </div>
                        </div>

                        <!-- Order Totals -->
                        <div id="orderTotals" style="display: none;">
                            <hr>
                            <div class="d-flex justify-content-between">
                                <span>Subtotal:</span>
                                <span id="subtotalAmount">$0.00</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Tax (10%):</span>
                                <span id="taxAmount">$0.00</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fw-bold">
                                <span>Total:</span>
                                <span id="totalAmount">$0.00</span>
                            </div>
                        </div>

                        <!-- Special Notes -->
                        <div class="mt-3">
                            <label class="form-label">Special Notes</label>
                            <textarea name="notes" class="form-control" rows="2" 
                                      placeholder="Any special requests or notes..."></textarea>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg" id="placeOrderBtn" disabled>
                                <i class="bi bi-check-circle"></i> Place Order
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Estimated Preparation Time -->
            <div class="card mt-3" id="prepTimeCard" style="display: none;">
                <div class="card-body text-center">
                    <h6 class="text-primary mb-1">
                        <i class="bi bi-clock"></i> Estimated Prep Time
                    </h6>
                    <span class="h4 text-success" id="estimatedTime">0 min</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let selectedItems = {};
let subtotal = 0;

function toggleItem(element) {
    const itemId = element.dataset.itemId;
    const stock = parseInt(element.dataset.stock);
    
    if (stock <= 0) {
        alert('This item is out of stock!');
        return;
    }
    
    if (selectedItems[itemId]) {
        // Remove item
        delete selectedItems[itemId];
        element.classList.remove('selected');
        element.querySelector('.quantity-controls').style.display = 'none';
    } else {
        // Add item
        selectedItems[itemId] = {
            id: itemId,
            name: element.dataset.itemName,
            price: parseFloat(element.dataset.itemPrice),
            quantity: 1,
            stock: stock,
            instructions: ''
        };
        element.classList.add('selected');
        element.querySelector('.quantity-controls').style.display = 'flex';
        element.querySelector('.quantity-display').textContent = '1';
    }
    
    updateOrderSummary();
}

function increaseQuantity(itemId) {
    if (selectedItems[itemId] && selectedItems[itemId].quantity < selectedItems[itemId].stock) {
        selectedItems[itemId].quantity++;
        document.querySelector(`[data-item-id="${itemId}"] .quantity-display`).textContent = selectedItems[itemId].quantity;
        updateOrderSummary();
    }
}

function decreaseQuantity(itemId) {
    if (selectedItems[itemId] && selectedItems[itemId].quantity > 1) {
        selectedItems[itemId].quantity--;
        document.querySelector(`[data-item-id="${itemId}"] .quantity-display`).textContent = selectedItems[itemId].quantity;
        updateOrderSummary();
    }
}

function updateInstructions(itemId, instructions) {
    if (selectedItems[itemId]) {
        selectedItems[itemId].instructions = instructions;
    }
}

function updateOrderSummary() {
    const orderItemsList = document.getElementById('orderItemsList');
    const orderTotals = document.getElementById('orderTotals');
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    const prepTimeCard = document.getElementById('prepTimeCard');
    
    const itemCount = Object.keys(selectedItems).length;
    
    if (itemCount === 0) {
        orderItemsList.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="bi bi-cart-x" style="font-size: 2rem;"></i>
                <p class="mb-0">No items selected</p>
                <small>Click on menu items to add them</small>
            </div>
        `;
        orderTotals.style.display = 'none';
        prepTimeCard.style.display = 'none';
        placeOrderBtn.disabled = true;
        return;
    }
    
    // Build items list
    let itemsHtml = '';
    subtotal = 0;
    let totalPrepTime = 0;
    
    Object.values(selectedItems).forEach(item => {
        const itemTotal = item.price * item.quantity;
        subtotal += itemTotal;
        totalPrepTime += 5 * item.quantity; // Assume 5 min per item
        
        itemsHtml += `
            <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                <div class="flex-grow-1">
                    <div class="fw-bold">${item.name}</div>
                    <small class="text-muted">$${item.price.toFixed(2)} × ${item.quantity}</small>
                </div>
                <div class="text-end">
                    <div class="fw-bold">$${itemTotal.toFixed(2)}</div>
                    <button type="button" class="btn btn-sm btn-outline-danger" 
                            onclick="removeItem(${item.id})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    orderItemsList.innerHTML = itemsHtml;
    
    // Update totals
    const tax = subtotal * 0.1;
    const total = subtotal + tax;
    
    document.getElementById('subtotalAmount').textContent = `$${subtotal.toFixed(2)}`;
    document.getElementById('taxAmount').textContent = `$${tax.toFixed(2)}`;
    document.getElementById('totalAmount').textContent = `$${total.toFixed(2)}`;
    
    orderTotals.style.display = 'block';
    placeOrderBtn.disabled = false;
    
    // Update prep time
    document.getElementById('estimatedTime').textContent = `${totalPrepTime} min`;
    prepTimeCard.style.display = 'block';
}

function removeItem(itemId) {
    const element = document.querySelector(`[data-item-id="${itemId}"]`);
    if (element) {
        element.classList.remove('selected');
        element.querySelector('.quantity-controls').style.display = 'none';
    }
    delete selectedItems[itemId];
    updateOrderSummary();
}

document.getElementById('cafeOrderForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const items = Object.values(selectedItems).map(item => ({
        id: item.id,
        quantity: item.quantity,
        instructions: item.instructions
    }));

    const payload = {
        customer_name: this.customer_name.value,
        order_type: this.order_type.value,
        tv_ip: this.tv_ip ? this.tv_ip.value : null,
        rental_id: this.rental_id ? this.rental_id.value : null,
        notes: this.notes ? this.notes.value : null,
        items: items
    };

    const submitBtn = document.getElementById('placeOrderBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Placing Order...';

    fetch('/cafe/order', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // ✅ Check if we're in a modal first
            const urlParams = new URLSearchParams(window.location.search);
            const modal = document.getElementById('cafeOrderModal');
            
            if (modal && urlParams.get('admin_create') === 'true') {
                // We're in admin creation mode - close modal and reload parent
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
                if (window.parent && window.parent !== window) {
                    window.parent.location.reload();
                } else {
                    window.location.href = '/cafe/orders';
                }
            } else if (modal) {
                // We're in regular modal mode
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
                window.location.href = data.redirect;
            } else {
                // No modal, just redirect
                window.location.href = data.redirect;
            }
        } else {
            alert('Error: ' + data.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Place Order';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to place order. Please try again.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Place Order';
    });
});



</script>
