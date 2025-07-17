<?php
// app/Http/Controllers/CafeController.php

namespace App\Http\Controllers;

use App\Models\CafeItem;
use App\Models\CafeOrder;
use App\Models\CafeOrderItem;
use App\Models\Rental;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CafeController extends Controller
{
    public function menu(Request $request)
    {
        $tvIp = $request->get('tv_ip');
        $rentalId = $request->get('rental_id');
        $orderType = $request->get('order_type', 'separate');
        $adminCreate = $request->get('admin_create', false); // New parameter
        
        $rental = null;
        if ($rentalId) {
            $rental = Rental::find($rentalId);
        }
        
        $cafeItems = CafeItem::with('stock')
            ->where('is_available', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->groupBy('category');
            
        return view('cafe.menu', compact('cafeItems', 'tvIp', 'rental', 'orderType', 'adminCreate'));
    }

    public function placeOrder(Request $request)
    {
        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:20', // Add phone validation
            'order_type' => 'required|in:integrated,separate',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:cafe_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'rental_id' => 'nullable|exists:rentals,id',
            'tv_ip' => 'nullable|ip'
        ]);

        try {
            DB::beginTransaction();

            // Calculate totals (existing code)
            $subtotal = 0;
            $orderItems = [];

            foreach ($request->items as $itemData) {
                $cafeItem = CafeItem::findOrFail($itemData['id']);
                $quantity = (int) $itemData['quantity'];
                $totalPrice = $cafeItem->price * $quantity;
                
                // Check stock
                if ($cafeItem->stock && $cafeItem->stock->quantity < $quantity) {
                    throw new \Exception("Insufficient stock for {$cafeItem->name}. Available: {$cafeItem->stock->quantity}");
                }
                
                $orderItems[] = [
                    'cafe_item_id' => $cafeItem->id,
                    'quantity' => $quantity,
                    'unit_price' => $cafeItem->price,
                    'total_price' => $totalPrice,
                    'special_instructions' => $itemData['instructions'] ?? null
                ];
                
                $subtotal += $totalPrice;
            }

            // Create cafe order
            $cafeOrder = CafeOrder::create([
                'order_number' => CafeOrder::generateOrderNumber(),
                'rental_id' => $request->order_type === 'integrated' ? $request->rental_id : null,
                'customer_name' => $request->customer_name,
                'customer_phone' => $request->customer_phone, // Add phone field
                'tv_ip' => $request->tv_ip,
                'order_type' => $request->order_type,
                'subtotal' => $subtotal,
                'tax' => $subtotal * 0.1,
                'total' => $subtotal * 1.1,
                'ordered_at' => now(),
                'notes' => $request->notes
            ]);

            // Create order items and update stock (existing code)
            foreach ($orderItems as $itemData) {
                $itemData['cafe_order_id'] = $cafeOrder->id;
                CafeOrderItem::create($itemData);
                
                // Update stock
                $cafeItem = CafeItem::find($itemData['cafe_item_id']);
                if ($cafeItem->stock) {
                    $cafeItem->stock->decrement('quantity', $itemData['quantity']);
                }
            }

            DB::commit();

            \App\Services\TransactionService::recordCafeTransaction($cafeOrder);

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully!',
                'order' => $cafeOrder->load('items.cafeItem'),
                'redirect' => route('cafe.order.confirmation', $cafeOrder->id)
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }


    public function orderConfirmation(CafeOrder $cafeOrder)
    {
        $cafeOrder->load(['items.cafeItem', 'rental']);
        return view('cafe.order-confirmation', compact('cafeOrder'));
    }

    public function orders()
    {
        $orders = CafeOrder::with(['items.cafeItem', 'rental'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        return view('cafe.orders', compact('orders'));
    }

    public function updateOrderStatus(CafeOrder $cafeOrder, Request $request)
    {
        $request->validate([
            'status' => 'required|in:pending,preparing,ready,delivered,cancelled'
        ]);

        $cafeOrder->update([
            'status' => $request->status,
            'ready_at' => $request->status === 'ready' ? now() : $cafeOrder->ready_at,
            'delivered_at' => $request->status === 'delivered' ? now() : $cafeOrder->delivered_at
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully!'
        ]);
    }
}
