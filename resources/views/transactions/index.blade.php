@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-cash-stack"></i> Cash Transactions (Kas)</h2>
    <div>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createTransactionModal">
            <i class="bi bi-plus-circle"></i> Add Manual Transaction
        </button>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                {{-- // EDIT: Changed to Rupiah format --}}
                <h4>Rp {{ number_format($balance, 0, ',', '.') }}</h4>
                <small>Total Balance</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                {{-- // EDIT: Changed to Rupiah format --}}
                <h4>Rp {{ number_format($dailyBalance, 0, ',', '.') }}</h4>
                <small>Today's Balance</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                {{-- // EDIT: Changed placeholder to Rupiah --}}
                <h4 id="monthlyCredit">Rp 0</h4>
                <small>Monthly Credit</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                {{-- // EDIT: Changed placeholder to Rupiah --}}
                <h4 id="monthlyDebit">Rp 0</h4>
                <small>Monthly Debit</small>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('transactions.index') }}">
            <div class="row">
                <div class="col-md-2">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <option value="rental" {{ request('category') == 'rental' ? 'selected' : '' }}>Rental</option>
                        <option value="cafe" {{ request('category') == 'cafe' ? 'selected' : '' }}>Cafe</option>
                        <option value="stock" {{ request('category') == 'stock' ? 'selected' : '' }}>Stock</option>
                        <option value="manual" {{ request('category') == 'manual' ? 'selected' : '' }}>Manual</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="credit" {{ request('type') == 'credit' ? 'selected' : '' }}>Credit</option>
                        <option value="debit" {{ request('type') == 'debit' ? 'selected' : '' }}>Debit</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search transactions..." value="{{ request('search') }}">
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Transaction #</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Payment Method</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $transaction)
                    <tr>
                        <td>{{ $transaction->transaction_number }}</td>
                        <td>{{ $transaction->transaction_date->format('M d, Y H:i') }}</td>
                        <td>
                            <span class="badge bg-{{ $transaction->type === 'credit' ? 'success' : 'danger' }}">
                                {{ ucfirst($transaction->type) }}
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-{{ 
                                $transaction->category === 'rental' ? 'primary' : 
                                ($transaction->category === 'cafe' ? 'warning' : 
                                ($transaction->category === 'stock' ? 'info' : 'secondary')) 
                            }}">
                                {{ ucfirst($transaction->category) }}
                            </span>
                        </td>
                        <td>{{ $transaction->description }}</td>
                         <td>
                            @php
                                $paymentMethod = '-'; // Default value
                                if (str_contains($transaction->notes, 'Payment:')) {
                                    // Split the string and get the part after "Payment: "
                                    $parts = explode('Payment:', $transaction->notes);
                                    if (isset($parts[1])) {
                                        $paymentMethod = trim($parts[1]);
                                    }
                                }
                            @endphp
                            
                            @if($paymentMethod !== '-')
                                <span class="badge bg-dark">{{ $paymentMethod }}</span>
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $transaction->customer_name ?? '-' }}</td>
                        <td>
                            {{-- // EDIT: Changed to Rupiah format --}}
                            <span class="text-{{ $transaction->type === 'credit' ? 'success' : 'danger' }}">
                                {{ $transaction->type === 'credit' ? '+' : '-' }}Rp {{ number_format($transaction->amount, 0, ',', '.') }}
                            </span>
                        </td>
                        <td>
                            @if($transaction->reference_type === 'rental')
                                <a href="{{ route('rentals.show', $transaction->reference_id) }}" class="btn btn-sm btn-outline-primary">View Rental</a>
                            @elseif($transaction->reference_type === 'cafe_order')
                                <a href="{{ route('cafe.order.confirmation', $transaction->reference_id) }}" class="btn btn-sm btn-outline-warning">View Order</a>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <i class="bi bi-cash-stack" style="font-size: 3rem; color: #dee2e6;"></i>
                            <h5 class="text-muted mt-3">No transactions found</h5>
                            <p class="text-muted">Try adjusting your filters or add a manual transaction</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        {{ $transactions->links() }}
    </div>
</div>

<div class="modal fade" id="createTransactionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('transactions.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Manual Transaction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select" required>
                            <option value="">Select Type</option>
                            <option value="credit">Credit (Income)</option>
                            <option value="debit">Debit (Expense)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        {{-- // EDIT: Changed step to be more appropriate for Rupiah --}}
                        <input type="number" name="amount" class="form-control" step="1000" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Customer Name (Optional)</label>
                        <input type="text" name="customer_name" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Transaction Date</label>
                        <input type="datetime-local" name="transaction_date" class="form-control" 
                               value="{{ now()->format('Y-m-d\TH:i') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Transaction</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
{{-- // EDIT: The entire script section is updated for Rupiah formatting --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    loadSummary();

    function loadSummary() {
        fetch('{{ route("transactions.summary") }}')
            .then(response => response.json())
            .then(data => {
                // Helper function for Rupiah formatting
                const formatRupiah = (number) => {
                    return 'Rp ' + new Intl.NumberFormat('id-ID').format(number);
                };

                document.getElementById('monthlyCredit').textContent = formatRupiah(data.monthly_credit);
                document.getElementById('monthlyDebit').textContent = formatRupiah(data.monthly_debit);
            })
            .catch(error => {
                console.error('Error loading summary:', error);
            });
    }
});
</script>
@endsection