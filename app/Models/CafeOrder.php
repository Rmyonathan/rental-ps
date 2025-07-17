<?php
// app/Models/CafeOrder.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CafeOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'rental_id',
        'customer_name',
        'tv_ip',
        'order_type',
        'subtotal',
        'tax',
        'total',
        'status',
        'payment_status',
        'notes',
        'ordered_at',
        'ready_at',
        'delivered_at'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'ordered_at' => 'datetime',
        'ready_at' => 'datetime',
        'delivered_at' => 'datetime'
    ];

    public function rental()
    {
        return $this->belongsTo(Rental::class);
    }

    public function items()
    {
        return $this->hasMany(CafeOrderItem::class);
    }

    public static function generateOrderNumber()
    {
        $prefix = 'CF';
        $date = now()->format('ymd');
        $lastOrder = self::whereDate('created_at', today())
                        ->orderBy('id', 'desc')
                        ->first();
        
        $sequence = $lastOrder ? (int)substr($lastOrder->order_number, -3) + 1 : 1;
        
        return $prefix . $date . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    public function getEstimatedReadyTimeAttribute()
    {
        $totalPrepTime = $this->items->sum(function($item) {
            return $item->cafeItem->preparation_time * $item->quantity;
        });
        
        return $this->ordered_at->addMinutes($totalPrepTime);
    }
}
