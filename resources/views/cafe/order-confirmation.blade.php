@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0">
                    <i class="bi bi-check-circle"></i> Order Confirmed!
                </h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Order Details</h5>
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Order Number:</strong></td>
                                <td>{{ $cafeOrder->order_number }}</td>
                            </tr>
                            <tr>
                                <td><strong>Customer:</strong></td>
                                <td>{{ $cafeOrder->customer_name }}</td>
                            </tr>
                            <tr>
                                <td><strong>Order Type:</strong></td>
                                <td>
                                    <span class="badge bg-{{ $cafeOrder->order_type === 'integrated' ? 'primary' : 'secondary' }}">
                                        {{ ucfirst($cafeOrder->order_type) }}
                                    </span>
                                </td>
                            </tr>
                            @if($cafeOrder->rental)
                            <tr>
                                <td><strong>Rental Station:</strong></td>
                                <td>{{ $cafeOrder->rental->ps_station }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                    <span class="badge bg-warning">{{ ucfirst($cafeOrder->status) }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Estimated Ready:</strong></td>
                                <td>{{ $cafeOrder->estimated_ready_time->format('H:i') }}</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <h5>Order Items</h5>
                        @foreach($cafeOrder->items as $item)
                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                            <div>
                                <div class="fw-bold">{{ $item->cafeItem->name }}</div>
                                <small class="text-muted">
                                    Rp {{ number_format($item->unit_price, 2) }} × {{ $item->quantity }}
                                </small>
                                @if($item->special_instructions)
                                    <div class="small text-info">
                                        <i class="bi bi-chat-left-text"></i> {{ $item->special_instructions }}
                                    </div>
                                @endif
                            </div>
                            <div class="fw-bold">
                                Rp {{ number_format($item->total_price, 2) }}
                            </div>
                        </div>
                        @endforeach
                        
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span>Subtotal:</span>
                            <span>Rp {{ number_format($cafeOrder->subtotal, 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Tax:</span>
                            <span>Rp {{ number_format($cafeOrder->tax, 2) }}</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between fw-bold h5">
                            <span>Total:</span>
                            <span>Rp {{ number_format($cafeOrder->total, 2) }}</span>
                        </div>
                    </div>
                </div>
                
                @if($cafeOrder->notes)
                <div class="mt-3">
                    <h6>Special Notes:</h6>
                    <div class="alert alert-info">
                        {{ $cafeOrder->notes }}
                    </div>
                </div>
                @endif
                
                <div class="text-center mt-4">
                    <a href="{{ route('rentals.index') }}" class="btn btn-primary">
                        <i class="bi bi-arrow-left"></i> Back to Rentals
                    </a>
                    <a href="{{ route('cafe.orders') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-list-ul"></i> View All Orders
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
