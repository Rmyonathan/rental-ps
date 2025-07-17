<?php
// app/Http/Controllers/CafeStockController.php

namespace App\Http\Controllers;

use App\Models\CafeItem;
use App\Models\CafeStock;
use Illuminate\Http\Request;

class CafeStockController extends Controller
{
    public function index()
    {
        $stocks = CafeStock::with('cafeItem')
            ->orderBy('quantity', 'asc')
            ->get();
            
        return view('cafe.stock.index', compact('stocks'));
    }

    public function create()
    {
        $cafeItems = CafeItem::doesntHave('stock')->get();
        return view('cafe.stock.create', compact('cafeItems'));
    }

   public function store(Request $request)
    {
        $request->validate([
            'cafe_item_id' => 'required|exists:cafe_items,id|unique:cafe_stocks',
            'quantity' => 'required|integer|min:0',
            'minimum_stock' => 'required|integer|min:1',
            'cost_price' => 'required|numeric|min:0',
            'expiry_date' => 'nullable|date|after:today'
        ]);

        // ✅ Create the stock record first
        $cafeStock = CafeStock::create($request->all());

        // ✅ Then record the transaction if there's initial stock
        if ($request->quantity > 0 && $request->cost_price > 0) {
            \App\Services\TransactionService::recordStockTransaction(
                $cafeStock, 
                $request->quantity, 
                $request->cost_price
            );
        }

        return redirect()->route('cafe.stock.index')
            ->with('success', 'Stock added successfully!');
    }


    public function edit(CafeStock $cafeStock)
    {
        return view('cafe.stock.edit', compact('cafeStock'));
    }

    public function update(Request $request, CafeStock $cafeStock)
    {
        $request->validate([
            'quantity' => 'required|integer|min:0',
            'minimum_stock' => 'required|integer|min:1',
            'cost_price' => 'nullable|numeric|min:0',
            'expiry_date' => 'nullable|date'
        ]);

        $cafeStock->update($request->all());

        return redirect()->route('cafe.stock.index')
            ->with('success', 'Stock updated successfully!');
    }

    public function addStock(Request $request, CafeStock $cafeStock)
    {
        $request->validate([
            'additional_quantity' => 'required|integer|min:1'
        ]);

        $cafeStock->increment('quantity', $request->additional_quantity);

        \App\Services\TransactionService::recordStockTransaction(
        $cafeStock, 
        $request->additional_quantity, 
        $request->cost_price
        );

        return response()->json([
            'success' => true,
            'message' => 'Stock added successfully!',
            'new_quantity' => $cafeStock->fresh()->quantity
        ]);
    }
}
