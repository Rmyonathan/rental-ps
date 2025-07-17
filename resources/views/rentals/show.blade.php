<div class="row">
    <div class="col-md-6">
        <h6>Rental Information</h6>
        <table class="table table-sm">
            <tr><td><strong>Customer:</strong></td><td>{{ $rental->customer_name }}</td></tr>
            <tr><td><strong>Phone:</strong></td><td>{{ $rental->phone ?? 'N/A' }}</td></tr>
            <tr><td><strong>Station:</strong></td><td>{{ $rental->ps_station }}</td></tr>
            <tr><td><strong>TV IP:</strong></td><td>{{ $rental->tv_ip }}</td></tr>
            <tr><td><strong>Duration:</strong></td><td>{{ $rental->duration_minutes }} minutes</td></tr>
            <tr><td><strong>Price:</strong></td><td>${{ number_format($rental->price, 2) }}</td></tr>
            <tr><td><strong>Started:</strong></td><td>{{ $rental->start_time->format('M d, Y H:i') }}</td></tr>
            <tr><td><strong>Ended:</strong></td><td>{{ $rental->end_time->format('M d, Y H:i') }}</td></tr>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6>Cafe Orders</h6>
        @if($rental->cafeOrders->count() > 0)
            @foreach($rental->cafeOrders as $cafeOrder)
                <div class="card mb-3">
                    <div class="card-header">
                        <strong>Order #{{ $cafeOrder->order_number }}</strong>
                        <span class="badge bg-success float-end">{{ ucfirst($cafeOrder->status) }}</span>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            @foreach($cafeOrder->items as $item)
                                <tr>
                                    <td>{{ $item->cafeItem->name }}</td>
                                    <td>x{{ $item->quantity }}</td>
                                    <td>${{ number_format($item->unit_price, 2) }}</td>
                                    <td><strong>${{ number_format($item->total_price, 2) }}</strong></td>
                                </tr>
                            @endforeach
                            <tr class="table-info">
                                <td colspan="3"><strong>Total:</strong></td>
                                <td><strong>${{ number_format($cafeOrder->total, 2) }}</strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            @endforeach
        @else
            <p class="text-muted">No cafe orders for this rental.</p>
        @endif
    </div>
</div>

<div class="row mt-3">
    <div class="col-12">
        <div class="alert alert-info">
            <h6>Summary</h6>
            <div class="row">
                <div class="col-md-4">
                    <strong>Rental Revenue:</strong> ${{ number_format($rental->price, 2) }}
                </div>
                <div class="col-md-4">
                    <strong>Cafe Revenue:</strong> ${{ number_format($rental->cafeOrders->sum('total'), 2) }}
                </div>
                <div class="col-md-4">
                    <strong>Total Revenue:</strong> ${{ number_format($rental->price + $rental->cafeOrders->sum('total'), 2) }}
                </div>
            </div>
        </div>
    </div>
</div>
