<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Rental;
use App\Models\CafeOrder;
use App\Models\CafeStock;

class TransactionService
{
    public static function recordRentalTransaction(Rental $rental, string $paymentMethod)
    {
        return Transaction::create([
            'transaction_number' => Transaction::generateTransactionNumber(),
            'type' => 'credit',
            'amount' => $rental->price,
            'description' => "Rental payment - {$rental->ps_station}",
            'category' => 'rental',
            'reference_type' => 'rental',
            'reference_id' => $rental->id,
            'customer_name' => $rental->customer_name,
            'transaction_date' => $rental->start_time,
            'notes' => "Duration: {$rental->duration_minutes} mins, Payment: {$paymentMethod}"
        ]);
    }

    public static function recordCafeTransaction(CafeOrder $cafeOrder)
    {
        return Transaction::create([
            'transaction_number' => Transaction::generateTransactionNumber(),
            'type' => 'credit',
            'amount' => $cafeOrder->total,
            'description' => "Cafe order - #{$cafeOrder->order_number}",
            'category' => 'cafe',
            'reference_type' => 'cafe_order',
            'reference_id' => $cafeOrder->id,
            'customer_name' => $cafeOrder->customer_name,
            'transaction_date' => $cafeOrder->ordered_at,
            'notes' => $cafeOrder->order_type === 'integrated' ? 'Integrated with rental' : 'Standalone order'
        ]);
    }

    public static function recordStockTransaction(CafeStock $cafeStock, $additionalQuantity, $costPrice)
    {
        $totalCost = $additionalQuantity * $costPrice;
        
        return Transaction::create([
            'transaction_number' => Transaction::generateTransactionNumber(),
            'type' => 'debit',
            'amount' => $totalCost,
            'description' => "Stock purchase - {$cafeStock->cafeItem->name}",
            'category' => 'stock',
            'reference_type' => 'cafe_stock',
            'reference_id' => $cafeStock->id,
            'transaction_date' => now(),
            'notes' => "Quantity: {$additionalQuantity} units @ $" . number_format($costPrice, 2) . " each"
        ]);
    }

    public static function recordManualTransaction($data)
    {
        return Transaction::create([
            'transaction_number' => Transaction::generateTransactionNumber(),
            'type' => $data['type'],
            'amount' => $data['amount'],
            'description' => $data['description'],
            'category' => 'manual',
            'reference_type' => 'manual',
            'customer_name' => $data['customer_name'] ?? null,
            'transaction_date' => $data['transaction_date'] ?? now(),
            'notes' => $data['notes'] ?? null,
            'created_by' => 'Admin'
        ]);
    }

    public static function getBalance()
    {
        $totalCredit = Transaction::where('type', 'credit')->sum('amount');
        $totalDebit = Transaction::where('type', 'debit')->sum('amount');
        
        return $totalCredit - $totalDebit;
    }

    public static function getDailyBalance($date = null)
    {
        $date = $date ?? today();
        
        $totalCredit = Transaction::where('type', 'credit')
            ->whereDate('transaction_date', $date)
            ->sum('amount');
            
        $totalDebit = Transaction::where('type', 'debit')
            ->whereDate('transaction_date', $date)
            ->sum('amount');
        
        return $totalCredit - $totalDebit;
    }
    public static function recordRentalExtensionTransaction(Rental $rental, $additionalPrice, string $paymentMethod)
    {
        // Do not record a transaction if the extension was free.
        if ($additionalPrice <= 0) {
            return null;
        }

        return Transaction::create([
            'transaction_number' => Transaction::generateTransactionNumber(),
            'type'               => 'credit',
            'amount'             => $additionalPrice,
            'description'        => "Rental extension - {$rental->ps_station}",
            'category'           => 'rental',
            'reference_type'     => 'rental',
            'reference_id'       => $rental->id,
            'customer_name'      => $rental->customer_name,
            'transaction_date'   => now(), // The transaction happens now
            'notes'              => "Extension Payment: {$paymentMethod}"
        ]);
    }
}
