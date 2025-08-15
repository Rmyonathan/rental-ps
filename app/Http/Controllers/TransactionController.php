<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $balance = TransactionService::getBalance();
        $dailyBalance = TransactionService::getDailyBalance();
        
        $query = Transaction::orderBy('transaction_date', 'desc');

        // Apply filters
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

          // // NEW: Add this block to filter by payment method
        if ($request->filled('payment_method')) {
            // // EDIT: Use a LIKE query to find the payment method anywhere in the notes.
            $query->where('notes', 'like', '%Payment: ' . $request->payment_method . '%');
        }


        if ($request->filled('search')) {
            $searchValue = $request->search;
            $query->where(function ($q) use ($searchValue) {
                $q->where('transaction_number', 'like', "%{$searchValue}%")
                  ->orWhere('description', 'like', "%{$searchValue}%")
                  ->orWhere('customer_name', 'like', "%{$searchValue}%")
                  ->orWhere('notes', 'like', "%{$searchValue}%");
            });
        }

        $transactions = $query->paginate(25)->appends($request->query());
        
        return view('transactions.index', compact('balance', 'dailyBalance', 'transactions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:debit,credit',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'customer_name' => 'nullable|string|max:255',
            'transaction_date' => 'required|date',
            'notes' => 'nullable|string'
        ]);

        TransactionService::recordManualTransaction($request->all());

        return redirect()->route('transactions.index')
            ->with('success', 'Transaction recorded successfully!');
    }

    public function summary()
    {
        $summary = [
            'total_balance' => TransactionService::getBalance(),
            'daily_balance' => TransactionService::getDailyBalance(),
            'monthly_credit' => Transaction::where('type', 'credit')
                ->whereMonth('transaction_date', now()->month)
                ->sum('amount'),
            'monthly_debit' => Transaction::where('type', 'debit')
                ->whereMonth('transaction_date', now()->month)
                ->sum('amount'),
            'rental_revenue' => Transaction::where('category', 'rental')
                ->whereMonth('transaction_date', now()->month)
                ->sum('amount'),
            'cafe_revenue' => Transaction::where('category', 'cafe')
                ->whereMonth('transaction_date', now()->month)
                ->sum('amount')
        ];

        return response()->json($summary);
    }
}
